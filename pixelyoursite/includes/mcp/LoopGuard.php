<?php
/**
 * Loop fingerprint + repeated-failure detection for MCP tool calls.
 *  1. **Loop fingerprint** — N identical `tool + args + ip` calls within a
 *     window trip the `Possible loop detected.` envelope.
 *  2. **Repeated failure** — N consecutive errors per `tool + ip` (success
 *     clears the counter) trip `Stop retrying and report the issue …`.
 * Thresholds match the system-prompt stop conditions.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class LoopGuard {

	/**
	 * The Nth identical call within `FINGERPRINT_WINDOW` trips the loop
	 * envelope. With 3: calls #1 and #2 pass, the 3rd identical call is
	 * blocked with `Possible loop detected.`
	 */
	public const FINGERPRINT_THRESHOLD = 3;

	/** Window for loop fingerprint accumulation, seconds. */
	public const FINGERPRINT_WINDOW = 30;

	/** Consecutive-failure threshold within `FAILURE_WINDOW`. */
	public const FAILURE_THRESHOLD = 3;

	/** Window for failure accumulation, seconds. Reset on any success. */
	public const FAILURE_WINDOW = 60;

	/** Bucket prefix for fingerprint counters. */
	private const FINGERPRINT_PREFIX = 'lf_';

	/** Bucket prefix for repeated-failure counters. */
	private const FAILURE_PREFIX = 'rf_';

	/**
	 * Has the caller hit the fingerprint threshold? Increments the counter;
	 * false means the caller should block the call.
	 *
	 * @param string $toolName Tool being called.
	 * @param array  $args     Tool call args (canonicalized for the fingerprint).
	 * @param string $ip       Client IP.
	 * @return bool True if the call is allowed, false if the loop threshold is hit.
	 */
	public static function checkFingerprint( string $toolName, array $args, string $ip ): bool {
		// `checkAndIncrement` allows `limit` calls and blocks the next one.
		return RateLimit::checkAndIncrement(
			self::fingerprintBucket( $toolName, $args, $ip ),
			self::FINGERPRINT_THRESHOLD - 1,
			self::FINGERPRINT_WINDOW
		);
	}

	/**
	 * Has this tool tripped the consecutive-failure threshold for this IP?
	 * Read-only; false means the caller should block the call.
	 *
	 * @param string $toolName Tool being called.
	 * @param string $ip       Client IP.
	 * @return bool True if the call is allowed, false if too many failures.
	 */
	public static function checkRepeatedFailure( string $toolName, string $ip ): bool {
		$count = self::failureCount( $toolName, $ip );

		return $count < self::FAILURE_THRESHOLD;
	}

	/**
	 * Record a tool-call outcome: success clears the per-tool/IP failure
	 * counter, failure increments it (counter expires after FAILURE_WINDOW).
	 *
	 * @param string $toolName Tool being called.
	 * @param string $ip       Client IP.
	 * @param bool   $success  Whether the call succeeded.
	 * @return void
	 */
	public static function recordResult( string $toolName, string $ip, bool $success ): void {
		$key = self::failureKey( $toolName, $ip );
		if ( $success ) {
			Storage::deleteTtl( $key );

			return;
		}
		$count = (int) ( Storage::getTtl( $key ) ?? 0 );
		Storage::setTtl( $key, $count + 1, self::FAILURE_WINDOW );
	}

	/**
	 * Read-only count for diagnostics / the Settings UI.
	 *
	 * @param string $toolName Tool being called.
	 * @param string $ip       Client IP.
	 * @return int Current consecutive-failure count.
	 */
	public static function failureCount( string $toolName, string $ip ): int {
		$value = Storage::getTtl( self::failureKey( $toolName, $ip ) );

		return is_int( $value ) ? $value : (int) ( $value ?? 0 );
	}

	/**
	 * Stable bucket name for the fingerprint counter. Args are sorted
	 * recursively so key order doesn't matter.
	 *
	 * @param string $toolName Tool being called.
	 * @param array  $args     Tool call args.
	 * @param string $ip       Client IP.
	 * @return string Storage bucket key.
	 */
	private static function fingerprintBucket( string $toolName, array $args, string $ip ): string {
		$canonical = self::canonicalize( $args );
		$hash      = hash( 'sha256', $toolName . '|' . $canonical . '|' . $ip );

		return self::FINGERPRINT_PREFIX . substr( $hash, 0, 16 );
	}

	/**
	 * Storage key for the per-tool/IP failure counter (uses Storage::setTtl).
	 *
	 * @param string $toolName Tool being called.
	 * @param string $ip       Client IP.
	 * @return string TTL storage key.
	 */
	private static function failureKey( string $toolName, string $ip ): string {
		$hash = hash( 'sha256', $toolName . '|' . $ip );

		return self::FAILURE_PREFIX . substr( $hash, 0, 16 );
	}

	/**
	 * Recursively sort array keys so the JSON encoding is order-independent
	 * (enough for a fingerprint — no round-trip needed).
	 *
	 * @param array $args Tool call args.
	 * @return string Canonical JSON string.
	 */
	private static function canonicalize( array $args ): string {
		$sorted = self::sortRecursive( $args );
		$json   = wp_json_encode( $sorted );

		return false === $json ? '' : (string) $json;
	}

	/**
	 * Recursively `ksort` an array (and nested arrays).
	 *
	 * @param array $value Array to sort.
	 * @return array Key-sorted array.
	 */
	private static function sortRecursive( array $value ): array {
		ksort( $value );
		foreach ( $value as $k => $v ) {
			if ( is_array( $v ) ) {
				$value[ $k ] = self::sortRecursive( $v );
			}
		}

		return $value;
	}
}