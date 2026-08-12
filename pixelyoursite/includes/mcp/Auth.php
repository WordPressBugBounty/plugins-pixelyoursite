<?php
/**
 * MCP-scoped Bearer tokens. Multiple named tokens per installation (one per
 * client / agent), each independently revocable. Wired into the server via
 * `create_server()`'s `transport_permission_callback`, replacing the adapter
 * default of `is_user_logged_in()`. Only SHA-256 hashes are stored; each raw
 * token is shown once. Hash compare is constant-time. `verify()` impersonates
 * the matched token's owner (`wp_set_current_user`) because the adapter's
 * SessionManager requires `get_current_user_id() > 0`; without it `initialize`
 * returns 401 and mcp-remote falls back to OAuth.
 *
 * Tokens live in one JSON registry under {@see KEY_REGISTRY}:
 *   { "<id>": { label, hash, created_at, last_used_at, owner_user_id } }
 * A pre-multi-token single token (old `mcp_auth_token_*` keys) is migrated
 * into the registry on first read, labelled "Default".
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Auth {

	/** No tokens exist. */
	public const STATE_NOT_GENERATED = 'not_generated';

	/** At least one token is active. */
	public const STATE_ACTIVE = 'active';

	/** Registry KV key (relative to Storage::KV_PREFIX). */
	private const KEY_REGISTRY = 'mcp_auth_tokens_free';

	/** Legacy single-token KV keys (migrated into the registry, then deleted). */
	private const LEGACY_KEY_HASH         = 'mcp_auth_token_hash';
	private const LEGACY_KEY_CREATED_AT   = 'mcp_auth_token_created_at';
	private const LEGACY_KEY_LAST_USED_AT = 'mcp_auth_token_last_used_at';
	private const LEGACY_KEY_OWNER_USER   = 'mcp_auth_token_owner_user_id';

	/** Length of generated tokens (alphanumeric from wp_generate_password). */
	private const TOKEN_LENGTH = 32;

	/** Soft cap on simultaneously-active tokens. */
	public const MAX_TOKENS = 25;

	/** Max stored label length. */
	private const LABEL_MAX = 60;

	/** ID + label of the token that authenticated the current request (set by verify()). */
	private static ?string $activeTokenId = null;
	private static ?string $activeTokenLabel = null;

	/**
	 * Generate a new named token, append it to the registry, return the raw
	 * token (shown once) plus its id and stored label.
	 *
	 * @param string $label Human-facing label (e.g. client/agent name).
	 * @return array{token:string, id:string, label:string}|\WP_Error
	 */
	public static function generate( string $label = '' ) {
		$registry = self::loadRegistry();

		if ( count( $registry ) >= self::MAX_TOKENS ) {
			return new \WP_Error(
				'pys_mcp_token_cap',
				sprintf( 'Token limit reached (%d). Revoke an existing token before creating another.', self::MAX_TOKENS )
			);
		}

		$token = wp_generate_password( self::TOKEN_LENGTH, false );
		$id    = self::newId( $registry );

		$registry[ $id ] = array(
			'label'         => self::sanitizeLabel( $label ),
			'hash'          => self::hash( $token ),
			'created_at'    => time(),
			'last_used_at'  => null,
			'owner_user_id' => (int) get_current_user_id(),
		);
		self::saveRegistry( $registry );

		return array(
			'token' => $token,
			'id'    => $id,
			'label' => $registry[ $id ][ 'label' ],
		);
	}

	/**
	 * Revoke one token by id. Returns true if a token was removed.
	 *
	 * @param string $id Token id.
	 * @return bool
	 */
	public static function revoke( string $id ): bool {
		$registry = self::loadRegistry();
		if ( !isset( $registry[ $id ] ) ) {
			return false;
		}
		unset( $registry[ $id ] );
		self::saveRegistry( $registry );

		return true;
	}

	/**
	 * Revoke every token. Returns the number removed.
	 *
	 * @return int
	 */
	public static function revokeAll(): int {
		$registry = self::loadRegistry();
		$count    = count( $registry );
		self::saveRegistry( array() );

		return $count;
	}

	/**
	 * Constant-time verify of a raw token against every stored hash. On match:
	 * impersonates that token's owner, stamps its last-used time, and records
	 * which token authenticated the request (for provenance attribution).
	 *
	 * @param string $token Raw bearer token to check.
	 * @return bool True when the token matches a stored hash.
	 */
	public static function verify( string $token ): bool {
		if ( '' === $token ) {
			return false;
		}

		$registry = self::loadRegistry();
		if ( empty( $registry ) ) {
			return false;
		}

		$candidate = self::hash( $token );
		$matchedId = null;

		foreach ( $registry as $id => $entry ) {
			$stored = isset( $entry[ 'hash' ] ) ? (string) $entry[ 'hash' ] : '';
			if ( '' !== $stored && hash_equals( $stored, $candidate ) ) {
				$matchedId = (string) $id;
			}
		}

		if ( null === $matchedId ) {
			return false;
		}

		$entry                  = $registry[ $matchedId ];
		self::$activeTokenId    = $matchedId;
		self::$activeTokenLabel = (string) ( $entry[ 'label' ] ?? '' );

		$ownerId = self::resolveOwnerUserId( $entry );
		if ( $ownerId > 0 && get_current_user_id() !== $ownerId ) {
			wp_set_current_user( $ownerId );
		}

		// Best-effort last-used stamp — never block auth on a write failure.
		try {
			$registry[ $matchedId ][ 'last_used_at' ] = time();
			self::saveRegistry( $registry );
		} catch ( \Throwable $e ) {
			error_log( '[PYS MCP Auth] last-used stamp failed: ' . $e->getMessage() );
		}

		return true;
	}

	/**
	 * Token list for the UI — every field EXCEPT the hash, newest first.
	 *
	 * @return array<int, array{id:string, label:string, created_at:?int, last_used_at:?int, owner_user_id:int, owner_name:string}>
	 */
	public static function tokens(): array {
		$registry = self::loadRegistry();
		$out      = array();
		foreach ( $registry as $id => $entry ) {
			$ownerId      = (int) ( $entry[ 'owner_user_id' ] ?? 0 );
			$ownerName    = '';
			if ( $ownerId > 0 ) {
				$user      = get_userdata( $ownerId );
				$ownerName = $user ? $user->display_name : '';
			}
			$out[] = array(
				'id'            => (string) $id,
				'label'         => (string) ( $entry[ 'label' ] ?? '' ),
				'created_at'    => isset( $entry[ 'created_at' ] ) ? (int) $entry[ 'created_at' ] : null,
				'last_used_at'  => isset( $entry[ 'last_used_at' ] ) && null !== $entry[ 'last_used_at' ] ? (int) $entry[ 'last_used_at' ] : null,
				'owner_user_id' => $ownerId,
				'owner_name'    => $ownerName,
			);
		}

		// Newest first.
		usort( $out, static function ( $a, $b ): int {
			return (int) $b[ 'created_at' ] <=> (int) $a[ 'created_at' ];
		} );

		return $out;
	}

	/**
	 * Current state for UI display.
	 *
	 * @return string STATE_NOT_GENERATED / STATE_ACTIVE.
	 */
	public static function state(): string {
		return empty( self::loadRegistry() ) ? self::STATE_NOT_GENERATED : self::STATE_ACTIVE;
	}

	/**
	 * Number of active tokens.
	 *
	 * @return int
	 */
	public static function count(): int {
		return count( self::loadRegistry() );
	}

	/**
	 * Label of the token that authenticated the current request, or null.
	 * Used by Provenance to attribute a write to a client.
	 *
	 * @return string|null
	 */
	public static function activeTokenLabel(): ?string {
		return self::$activeTokenLabel;
	}

	/**
	 * Id of the token that authenticated the current request, or null.
	 *
	 * @return string|null
	 */
	public static function activeTokenId(): ?string {
		return self::$activeTokenId;
	}

	/**
	 * REST permission callback for the MCP transport. Pulls the Bearer token
	 * from the `Authorization` header and verifies it; returns false (→ 403)
	 * on any failure. Loosely typed so a request-type mismatch can't fatal.
	 *
	 * @param mixed $request The REST request object (WP_REST_Request in practice).
	 * @return bool True when the bearer token verifies.
	 */
	public static function permissionCallback( $request ): bool {
		try {
			if ( !is_object( $request ) || !method_exists( $request, 'get_header' ) ) {
				error_log(
					'[PYS MCP Auth] permissionCallback: unexpected request type ' . ( is_object( $request ) ? get_class(
						$request
					) : gettype( $request ) )
				);

				return false;
			}

			$header = $request->get_header( 'authorization' );
			if ( !is_string( $header ) || '' === $header ) {
				return false;
			}

			if ( !preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches ) ) {
				return false;
			}

			return self::verify( $matches[ 1 ] );
		} catch ( \Throwable $e ) {
			error_log(
				'[PYS MCP Auth] permissionCallback threw: ' . $e->getMessage() . ' at ' . $e->getFile() . ':'
				. $e->getLine()
			);

			return false;
		}
	}

	// --------------------------------------------------------------- internal

	/**
	 * Load the token registry, migrating a legacy single token on first read.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function loadRegistry(): array {
		$raw = Storage::getValue( self::KEY_REGISTRY );
		if ( null !== $raw ) {
			$decoded = json_decode( $raw, true );

			return is_array( $decoded ) ? $decoded : array();
		}

		return self::migrateLegacy();
	}

	/**
	 * Persist the registry as JSON.
	 *
	 * @param array<string, array<string, mixed>> $registry Registry to store.
	 * @return void
	 */
	private static function saveRegistry( array $registry ): void {
		Storage::setValue( self::KEY_REGISTRY, (string) wp_json_encode( $registry ) );
	}

	/**
	 * One-time migration of the pre-multi-token single token into the registry
	 * (labelled "Default"), then deletes the legacy keys. Returns the resulting
	 * registry (empty if there was no legacy token).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function migrateLegacy(): array {
		$hash = Storage::getValue( self::LEGACY_KEY_HASH );
		if ( null === $hash || '' === $hash ) {
			return array();
		}

		$created  = Storage::getValue( self::LEGACY_KEY_CREATED_AT );
		$lastUsed = Storage::getValue( self::LEGACY_KEY_LAST_USED_AT );
		$owner    = Storage::getValue( self::LEGACY_KEY_OWNER_USER );

		$registry = array(
			self::newId( array() ) => array(
				'label'         => 'Default',
				'hash'          => (string) $hash,
				'created_at'    => null !== $created ? (int) $created : time(),
				'last_used_at'  => null !== $lastUsed ? (int) $lastUsed : null,
				'owner_user_id' => null !== $owner ? (int) $owner : 0,
			),
		);
		self::saveRegistry( $registry );

		Storage::deleteValue( self::LEGACY_KEY_HASH );
		Storage::deleteValue( self::LEGACY_KEY_CREATED_AT );
		Storage::deleteValue( self::LEGACY_KEY_LAST_USED_AT );
		Storage::deleteValue( self::LEGACY_KEY_OWNER_USER );

		return $registry;
	}

	/**
	 * Which WP user should `verify()` impersonate for this token? The stored
	 * owner, falling back to the lowest-ID admin for tokens with no owner.
	 *
	 * @param array<string, mixed> $entry Registry entry.
	 * @return int WP user ID, or 0 if none.
	 */
	private static function resolveOwnerUserId( array $entry ): int {
		$id = (int) ( $entry[ 'owner_user_id' ] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}

		$admins = get_users(
			array(
				'capability' => 'manage_options',
				'fields'     => 'ID',
				'number'     => 1,
				'orderby'    => 'ID',
				'order'      => 'ASC',
			)
		);

		return !empty( $admins ) ? (int) $admins[ 0 ] : 0;
	}

	/**
	 * A collision-free token id for the registry.
	 *
	 * @param array<string, mixed> $registry Current registry.
	 * @return string
	 */
	private static function newId( array $registry ): string {
		do {
			$id = strtolower( wp_generate_password( 10, false, false ) );
		} while ( isset( $registry[ $id ] ) );

		return $id;
	}

	/**
	 * Sanitize + clamp a user-supplied label, with a sensible fallback.
	 *
	 * @param string $label Raw label.
	 * @return string
	 */
	private static function sanitizeLabel( string $label ): string {
		$clean = trim( sanitize_text_field( $label ) );
		if ( '' === $clean ) {
			$clean = 'Token';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $clean, 0, self::LABEL_MAX );
		}

		return substr( $clean, 0, self::LABEL_MAX );
	}

	/**
	 * SHA-256 of the raw token. One private place so generate() and verify()
	 * cannot drift apart.
	 *
	 * @param string $token Raw token.
	 * @return string Hex SHA-256 digest.
	 */
	private static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}
}
