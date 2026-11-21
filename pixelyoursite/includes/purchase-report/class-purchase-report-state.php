<?php
/**
 * Internal state of the purchase report, kept in the plugin's options table.
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Values the report keeps for itself and no settings page shows: the cache
 * table version, the settings fingerprint, the background fill position.
 * One row of pys_options, read once per request.
 */
class PurchaseReportState {

	const OPTION = 'pys_purchase_report_state';

	/** MySQL named lock around the read-modify-write. */
	const LOCK = 'pys_purchase_report_state';

	/**
	 * The state, read once per request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $values = null;

	/**
	 * One value of the report state.
	 *
	 * @param string $key
	 * @param mixed  $default_value
	 * @return mixed
	 */
	public static function get( $key, $default_value = null ) {
		if ( null === self::$values ) {
			$stored       = pys_get_option( self::OPTION, array() );
			self::$values = is_array( $stored ) ? $stored : array();
		}

		return array_key_exists( $key, self::$values ) ? self::$values[ $key ] : $default_value;
	}

	/**
	 * Change some keys and store the state; the read-modify-write runs under a
	 * lock, so two requests do not drop each other's keys.
	 *
	 * @param array<string, mixed> $values Keys to change; the others stay
	 * @return bool False when the database refused the write
	 */
	public static function set( array $values ) {
		global $wpdb;

		// A busy or missing lock still writes: losing a key is better than losing the write.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::LOCK, 5 ) );
		try {
			// Another request may have written since this one read.
			self::$values = null;
			self::get( '' );

			self::$values = array_merge( self::$values, $values );
			// pys_update_option() reports success whatever happened: the last query tells.
			pys_update_option( self::OPTION, self::$values );

			return '' === $wpdb->last_error;
		} finally {
			if ( $locked ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::LOCK ) );
			}
		}
	}
}
