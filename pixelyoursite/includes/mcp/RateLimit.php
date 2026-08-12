<?php
/**
 * Fixed-window rate-limit counter, backing the per-IP / per-token
 * transport throttles and the LoopGuard fingerprint counter.
 * Uses Storage's permanent KV API (not the TTL API, which would reset
 * expiry on every hit and lock out a hammering client). Payload is
 * `"<count>:<exp_unix_ts>"`; on increment the window stays anchored to the
 * first hit, and an expired bucket opens a fresh window.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class RateLimit {

	/** Storage key prefix for rate-limit buckets (added on top of Storage::KV_PREFIX). */
	private const BUCKET_PREFIX = 'rl_';

	/**
	 * Increment the counter for `$bucket` and return whether we are still
	 * under `$limit`. Opens a fixed `$windowSeconds` window on the first hit;
	 * an over-limit bucket returns false without extending the window.
	 *
	 * @param string $bucket        Logical bucket name.
	 * @param int    $limit         Max hits allowed within the window.
	 * @param int    $windowSeconds Window length in seconds.
	 * @return bool                 True if the hit is allowed, false if over limit.
	 */
	public static function checkAndIncrement( string $bucket, int $limit, int $windowSeconds ): bool {
		$key   = self::BUCKET_PREFIX . $bucket;
		$now   = time();
		$state = self::read( $key, $now );

		if ( null === $state ) {
			Storage::setValue( $key, self::encode( 1, $now + $windowSeconds ) );

			return 1 <= $limit;
		}

		if ( $state[ 'count' ] >= $limit ) {
			return false;
		}

		Storage::setValue( $key, self::encode( $state[ 'count' ] + 1, $state[ 'exp' ] ) );

		return true;
	}

	/**
	 * Current counter value for `$bucket`. Returns 0 if the bucket does
	 * not exist or its window has expired. Read-only — does not increment
	 * or open a new window.
	 *
	 * @param string $bucket Logical bucket name.
	 * @return int Current hit count within the window.
	 */
	public static function current( string $bucket ): int {
		$state = self::read( self::BUCKET_PREFIX . $bucket, time() );

		return null === $state ? 0 : $state[ 'count' ];
	}

	/**
	 * Forget the bucket — next `checkAndIncrement` opens a new window.
	 * Used by LoopGuard to clear failure counters on success.
	 *
	 * @param string $bucket Logical bucket name.
	 * @return void
	 */
	public static function reset( string $bucket ): void {
		Storage::deleteValue( self::BUCKET_PREFIX . $bucket );
	}

	/**
	 * Parse the stored payload, ignoring buckets whose window has expired.
	 *
	 * @param string $key Full storage key (prefix already applied).
	 * @param int    $now Current Unix timestamp.
	 * @return array{count:int, exp:int}|null Parsed state, or null if missing/expired.
	 */
	private static function read( string $key, int $now ): ?array {
		$raw = Storage::getValue( $key );
		if ( null === $raw ) {
			return null;
		}
		$parts = explode( ':', $raw, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		$count = (int) $parts[ 0 ];
		$exp   = (int) $parts[ 1 ];
		if ( $exp <= $now ) {
			return null;
		}

		return array( 'count' => $count, 'exp' => $exp );
	}

	/**
	 * Encode counter state into the `"<count>:<exp>"` storage payload.
	 *
	 * @param int $count Current hit count.
	 * @param int $exp   Window expiry Unix timestamp.
	 * @return string Encoded payload.
	 */
	private static function encode( int $count, int $exp ): string {
		return $count . ':' . $exp;
	}
}