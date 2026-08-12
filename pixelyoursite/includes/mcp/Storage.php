<?php
/**
 * Storage layer for MCP runtime data, on PYS Pro's `wp_pys_options` table
 * under a `pys_mcp_*` prefix (no `wp_options`, transients or new tables).
 *  - TTL KV (`setTtl`/`getTtl`/…) — backs rate-limit counters and loop
 *    fingerprints; atomic upsert via `INSERT … ON DUPLICATE KEY UPDATE`.
 *  - Provenance (`provenanceAppend`/`getRecentProvenance`) — one row per
 *    audit entry, keyed by microtime so lex-sort = time-sort, FIFO-trimmed.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\Settings;

final class Storage {

	/** Option-name prefix for TTL rows. */
	public const TTL_PREFIX = 'pys_mcp_ttl_';

	/** Option-name prefix for provenance rows. */
	public const PROVENANCE_PREFIX = 'pys_mcp_prov_';

	/** Option-name prefix for permanent key-value rows. */
	public const KV_PREFIX = 'pys_mcp_kv_';

	/** Default soft cap for provenance entries retained. */
	public const PROVENANCE_MAX_ENTRIES = 500;

	// ----------------------------------------------------------------- TTL

	/**
	 * Store a value under a TTL. Overwrites if key already exists.
	 *
	 * @param string $key        Logical key. The TTL_PREFIX is added internally.
	 * @param mixed  $value      Anything serializable.
	 * @param int    $ttlSeconds Seconds until expiry. Pass 0 to "delete" (effectively immediate expiry).
	 * @return bool                True on successful write.
	 */
	public static function setTtl( string $key, $value, int $ttlSeconds ): bool {
		$payload = serialize( array(
			'v'   => $value,
			'exp' => time() + max( 0, $ttlSeconds ),
		) );

		return self::write( self::TTL_PREFIX . $key, $payload );
	}

	/**
	 * Return a TTL value, or null if missing / expired. Expired rows are
	 * deleted on access (cleanup-on-read).
	 *
	 * @param string $key Logical key (TTL_PREFIX added internally).
	 * @return mixed|null Stored value, or null if missing/expired.
	 */
	public static function getTtl( string $key ) {
		$name = self::TTL_PREFIX . $key;
		$raw  = self::read( $name );
		if ( null === $raw ) {
			return null;
		}
		$payload = @unserialize( $raw, array( 'allowed_classes' => false ) );
		if ( !is_array( $payload ) || !isset( $payload[ 'exp' ] ) ) {
			self::delete( $name );

			return null;
		}
		if ( (int) $payload[ 'exp' ] < time() ) {
			self::delete( $name );

			return null;
		}

		return $payload[ 'v' ] ?? null;
	}

	/**
	 * Delete a TTL row regardless of expiry.
	 *
	 * @param string $key Logical key (TTL_PREFIX added internally).
	 * @return bool True on successful delete.
	 */
	public static function deleteTtl( string $key ): bool {
		return self::delete( self::TTL_PREFIX . $key );
	}

	/**
	 * Scan all TTL rows and delete expired ones. Safe to call from WP cron
	 * for periodic cleanup; not needed per request (read-side handles it).
	 *
	 * @return int Number of rows deleted.
	 */
	public static function sweepExpiredTtl(): int {
		global $wpdb;
		if ( !self::ready() ) {
			return 0;
		}
		$table = self::tableName();
		$like  = $wpdb->esc_like( self::TTL_PREFIX ) . '%';
		$now   = time();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$table} WHERE option_name LIKE %s",
				$like
			),
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return 0;
		}

		$expired = array();
		foreach ( $rows as $row ) {
			$payload = @unserialize( $row[ 'option_value' ], array( 'allowed_classes' => false ) );
			if ( !is_array( $payload ) || !isset( $payload[ 'exp' ] ) || (int) $payload[ 'exp' ] < $now ) {
				$expired[] = $row[ 'option_name' ];
			}
		}
		if ( empty( $expired ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $expired ), '%s' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE option_name IN ($placeholders)",
				$expired
			)
		);

		return count( $expired );
	}

	// ----------------------------------------------------------- Provenance

	/**
	 * Append a provenance entry. The key encodes microtime so rows are
	 * naturally sortable and we never re-read the existing log to insert.
	 * After the write, soft-trims to `$maxEntries` (oldest deleted first).
	 *
	 * @param array $entry      Standardized entry shape (see Provenance).
	 * @param int   $maxEntries Soft cap. Set 0 to skip trim.
	 * @return bool True on successful write.
	 */
	public static function provenanceAppend( array $entry, int $maxEntries = self::PROVENANCE_MAX_ENTRIES ): bool {
		$name = self::PROVENANCE_PREFIX . self::sortableId();
		$ok   = self::write( $name, serialize( $entry ) );
		if ( $ok && $maxEntries > 0 ) {
			self::trimProvenance( $maxEntries );
		}

		return $ok;
	}

	/**
	 * Return the most recent N provenance entries, newest first.
	 *
	 * @param int $limit Max entries to return.
	 * @return array<int, array> Decoded entries.
	 */
	public static function getRecentProvenance( int $limit = 20 ): array {
		global $wpdb;
		if ( !self::ready() || $limit <= 0 ) {
			return array();
		}
		$table = self::tableName();
		$like  = $wpdb->esc_like( self::PROVENANCE_PREFIX ) . '%';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value FROM {$table} WHERE option_name LIKE %s ORDER BY option_name DESC LIMIT %d",
				$like,
				$limit
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$entry = @unserialize( $row[ 'option_value' ], array( 'allowed_classes' => false ) );
			if ( is_array( $entry ) ) {
				$out[] = $entry;
			}
		}

		return $out;
	}

	/**
	 * Delete every provenance row. Used by the settings-tab "Clear log"
	 * button.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function clearAllProvenance(): int {
		global $wpdb;
		if ( !self::ready() ) {
			return 0;
		}
		$table = self::tableName();
		$like  = $wpdb->esc_like( self::PROVENANCE_PREFIX ) . '%';
		$count = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE option_name LIKE %s", $like )
		);

		return false === $count ? 0 : (int) $count;
	}

	/**
	 * FIFO-trim provenance rows down to `$maxEntries` (oldest deleted first).
	 *
	 * @param int $maxEntries Soft cap to keep.
	 * @return void
	 */
	private static function trimProvenance( int $maxEntries ): void {
		global $wpdb;
		$table = self::tableName();
		$like  = $wpdb->esc_like( self::PROVENANCE_PREFIX ) . '%';

		$total    = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE option_name LIKE %s", $like )
		);
		$overflow = $total - $maxEntries;
		if ( $overflow <= 0 ) {
			return;
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE option_name LIKE %s ORDER BY option_name ASC LIMIT %d",
				$like,
				$overflow
			)
		);
		if ( empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE id IN ($placeholders)", $ids )
		);
	}

	// --------------------------------------------------- Permanent KV API

	/**
	 * Store a permanent string value (no expiry, overwrites). Used by Auth
	 * and anything needing durable state outside PYS's Settings schema.
	 *
	 * @param string $key   Logical key (KV_PREFIX added internally).
	 * @param string $value Value to store.
	 * @return bool True on successful write.
	 */
	public static function setValue( string $key, string $value ): bool {
		return self::write( self::KV_PREFIX . $key, $value );
	}

	/**
	 * Read a permanent value.
	 *
	 * @param string $key Logical key (KV_PREFIX added internally).
	 * @return string|null Stored value, or null if absent.
	 */
	public static function getValue( string $key ): ?string {
		return self::read( self::KV_PREFIX . $key );
	}

	/**
	 * Delete a permanent value.
	 *
	 * @param string $key Logical key (KV_PREFIX added internally).
	 * @return bool True on successful delete.
	 */
	public static function deleteValue( string $key ): bool {
		return self::delete( self::KV_PREFIX . $key );
	}

	// ----------------------------------------------------------- Plumbing

	/**
	 * Physical `wp_pys_options` table name.
	 *
	 * @return string
	 */
	private static function tableName(): string {
		return Settings::storage_table();
	}

	/**
	 * Whether PYS Settings (and thus the storage table) is available.
	 *
	 * @return bool
	 */
	private static function ready(): bool {
		return class_exists( Settings::class );
	}

	/**
	 * Read a raw row value by full option name.
	 *
	 * @param string $name Full option name (prefix already applied).
	 * @return string|null Row value, or null if absent.
	 */
	private static function read( string $name ): ?string {
		global $wpdb;
		if ( !self::ready() ) {
			return null;
		}
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM " . self::tableName() . " WHERE option_name = %s LIMIT 1",
				$name
			)
		);

		return null === $value ? null : (string) $value;
	}

	/**
	 * Atomic upsert. INSERT ... ON DUPLICATE KEY UPDATE — concurrent writers
	 * to the same key never race; the last writer wins cleanly.
	 *
	 * @param string $name  Full option name.
	 * @param string $value Value to store.
	 * @return bool True on successful query.
	 */
	private static function write( string $name, string $value ): bool {
		global $wpdb;
		if ( !self::ready() ) {
			return false;
		}
		$table = self::tableName();
		$sql   = $wpdb->prepare(
			"INSERT INTO {$table} (option_name, option_value, migrated)
			 VALUES (%s, %s, 1)
			 ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
			$name,
			$value
		);

		return false !== $wpdb->query( $sql );
	}

	/**
	 * Delete a row by full option name.
	 *
	 * @param string $name Full option name.
	 * @return bool True on successful delete.
	 */
	private static function delete( string $name ): bool {
		global $wpdb;
		if ( !self::ready() ) {
			return false;
		}
		$result = $wpdb->delete( self::tableName(), array( 'option_name' => $name ), array( '%s' ) );

		return false !== $result;
	}

	/**
	 * Sortable, monotonically-increasing key fragment for provenance rows.
	 * Format: 14-digit zero-padded ms-precision microtime + `_` + 6-char
	 * random suffix. Lex-sort matches time-sort; suffix avoids collisions
	 * when two writes land in the same millisecond.
	 *
	 * @return string
	 */
	private static function sortableId(): string {
		$micro = (int) ( microtime( true ) * 1000 ); // ms

		return str_pad( (string) $micro, 14, '0', STR_PAD_LEFT ) . '_' . wp_generate_password( 6, false, false );
	}
}
