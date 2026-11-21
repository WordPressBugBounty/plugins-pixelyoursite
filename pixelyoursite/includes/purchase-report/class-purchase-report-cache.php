<?php
/**
 * Cache table of the purchase report: one compact row per recent order.
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Serves the surfaces that count orders: the filters of the orders list, the
 * dashboard widget and the summary email. The order box and the orders column
 * never read it: they compute the report of the orders on screen.
 *
 * A row is rewritten when the order changes or its thank-you page builds the
 * Purchase. Orders of the last WINDOW_DAYS without a row are added in the
 * background by WP-Cron. The table is emptied when the tracking settings
 * change, and removed on uninstall (uninstall.php).
 *
 * The table and the state are shared with PixelYourSite Pro on the same site:
 * each edition has its own DB_VERSION, so switching editions empties the table
 * and the new edition fills it again.
 */
class PurchaseReportCache {

	const DB_VERSION   = 'free-1';
	const WINDOW_DAYS  = 30;
	const FILL_HOOK    = 'pys_purchase_report_fill';
	const REFRESH_HOOK = 'pys_purchase_report_refresh';
	const FILL_BATCH   = 50;
	const FILL_SECONDS = 5;
	const ROWS_LIMIT   = 20000;
	/** Transient that pauses the install after a failure. */
	const INSTALL_FAILED = 'pys_purchase_report_install_failed';
	/** Transient of the dashboard widget, dropped whenever rows change. */
	const WIDGET_CACHE = 'pys_purchase_report_widget';

	/**
	 * Orders to rewrite at the end of the request.
	 *
	 * @var array<string, array{0: string, 1: int}>
	 */
	private static $queue = array();

	/**
	 * Hook the table install, the row updates and the background jobs.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'install' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'queueWooOrder' ), 99, 1 );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'queueWooOrder' ), 99, 2 );
		// The thank-you page saves the order after setting its Purchase flag.
		add_action( 'woocommerce_update_order', array( __CLASS__, 'queueWooOrder' ), 99, 2 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'queueWooOrder' ), 99, 1 );
		add_action( 'edd_update_payment_status', array( __CLASS__, 'queueEddOrder' ), 99, 1 );
		add_action( 'edd_complete_purchase', array( __CLASS__, 'queueEddOrder' ), 99, 1 );
		// The EDD thank-you page writes its Purchase flag as post meta.
		add_action( 'added_post_meta', array( __CLASS__, 'queueFlaggedOrder' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'queueFlaggedOrder' ), 10, 3 );
		add_action( 'woocommerce_delete_order', array( __CLASS__, 'deleteWooOrder' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'deleteWooOrder' ) );
		add_action( 'edd_order_deleted', array( __CLASS__, 'deleteEddOrder' ) );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 5 );
		add_action( self::FILL_HOOK, array( __CLASS__, 'fill' ) );
		add_action( self::REFRESH_HOOK, array( __CLASS__, 'refreshWindow' ) );
	}

	/**
	 * Name of the cache table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'pys_purchase_report';
	}

	/**
	 * Timestamp of the oldest order the table keeps.
	 *
	 * @return int
	 */
	public static function windowStart() {
		return time() - self::WINDOW_DAYS * DAY_IN_SECONDS;
	}

	/**
	 * Create the table and the schedules. Cheap on every load: one option read
	 * and one cron lookup.
	 *
	 * @return void
	 */
	public static function install() {
		$installed = (string) PurchaseReportState::get( 'db_version' );
		if ( self::DB_VERSION === $installed ) {
			// Deactivation removed the schedules; orders placed meanwhile have no row.
			if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
				self::scheduleFill();
			}

			return;
		}
		// A failed install is not repeated on every request.
		if ( get_transient( self::INSTALL_FAILED ) ) {
			return;
		}

		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
			type varchar(8) NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			verdict varchar(16) NOT NULL,
			attention tinyint(1) NOT NULL DEFAULT 0,
			order_total decimal(18,4) NOT NULL DEFAULT 0,
			currency varchar(8) NOT NULL DEFAULT '',
			date_created datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			details text NOT NULL,
			updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (type, order_id),
			KEY verdict (type, verdict),
			KEY attention (type, attention),
			KEY date_created (type, date_created)
			) {$charset};"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			self::installFailed( "table {$table} was not created: {$wpdb->last_error}" );

			return;
		}

		// The version first: a state that cannot be stored must not empty the table on every attempt.
		$stored = PurchaseReportState::set(
			array(
				'db_version' => self::DB_VERSION,
				'signature'  => self::settingsSignature(),
			)
		);
		if ( ! $stored ) {
			self::installFailed( "state not stored: {$wpdb->last_error}" );

			return;
		}

		// Rows of another version or edition hold something else: start again from an empty table.
		if ( '' !== $installed ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name is built from $wpdb->prefix.
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}
		delete_transient( self::WIDGET_CACHE );
		self::scheduleFill();
	}

	/**
	 * Log a failed install and pause the next attempt for an hour.
	 *
	 * @param string $reason
	 * @return void
	 */
	private static function installFailed( $reason ) {
		set_transient( self::INSTALL_FAILED, 1, HOUR_IN_SECONDS );
		if ( PYS()->getLog() ) {
			PYS()->getLog()->error( "Purchase report not installed: {$reason}" );
		}
	}

	/**
	 * Start the background fill from the newest order, and keep the hourly
	 * window refresh scheduled.
	 *
	 * @return void
	 */
	public static function scheduleFill() {
		PurchaseReportState::set(
			array(
				'fill_page_woo' => 1,
				'fill_page_edd' => 1,
			)
		);
		if ( ! wp_next_scheduled( self::FILL_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::FILL_HOOK );
		}
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::REFRESH_HOOK );
		}
	}

	/**
	 * Remove the schedules (plugin deactivation).
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::FILL_HOOK );
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
		delete_transient( self::WIDGET_CACHE );
	}

	/**
	 * After the plugin settings were saved: a row holds what the settings of
	 * its time made of the order, so new tracking settings empty the table and
	 * the background fill writes it again.
	 *
	 * @return void
	 */
	public static function onSettingsSaved() {
		if ( self::DB_VERSION !== (string) PurchaseReportState::get( 'db_version' ) ) {
			return;
		}
		$signature = self::settingsSignature();
		if ( (string) PurchaseReportState::get( 'signature' ) === $signature ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- table name is built from $wpdb->prefix.
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() );
		PurchaseReportState::set( array( 'signature' => $signature ) );
		delete_transient( self::WIDGET_CACHE );
		self::scheduleFill();
	}

	/**
	 * Fingerprint of the settings the report depends on: the tags that send
	 * the Purchase, their APIs and the user roles that are not tracked.
	 *
	 * @return string
	 */
	public static function settingsSignature() {
		$parts = array();
		foreach ( array( 'woo', 'edd' ) as $type ) {
			$parts[ $type ] = PurchaseReport::tagPlatforms( $type );
		}
		foreach ( PurchaseReport::API_PLATFORMS as $platform ) {
			$parts['api'][ $platform ] = PurchaseReport::isApiConfigured( $platform );
		}
		$roles = array_values( (array) PYS()->getOption( 'do_not_track_user_roles' ) );
		sort( $roles );
		$parts['roles']  = $roles;
		$parts['stores'] = array( isWooCommerceActive(), isEddActive() );

		return md5( (string) wp_json_encode( $parts ) );
	}

	/**
	 * Remember an order whose row has to be rewritten at the end of the request.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return void
	 */
	public static function queue( $type, $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id > 0 && in_array( $type, array( 'woo', 'edd' ), true ) ) {
			self::$queue[ $type . ':' . $order_id ] = array( $type, $order_id );
		}
	}

	/**
	 * Queue the row of a WooCommerce order for rewriting.
	 *
	 * @param int            $order_id
	 * @param \WC_Order|null $order    Passed by woocommerce_new_order and woocommerce_update_order
	 * @return void
	 */
	public static function queueWooOrder( $order_id, $order = null ) {
		// The block checkout creates a draft before the customer pays: not an order yet.
		if ( $order instanceof \WC_Order && self::isDraft( $order ) ) {
			return;
		}
		self::queue( 'woo', $order_id );
	}

	/**
	 * Is the WooCommerce order a checkout draft rather than an order?
	 *
	 * @param \WC_Order $order
	 * @return bool
	 */
	private static function isDraft( $order ) {
		return in_array( $order->get_status(), array( 'checkout-draft', 'auto-draft', 'draft' ), true );
	}

	/**
	 * Queue the row of an EDD order for rewriting.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public static function queueEddOrder( $order_id ) {
		self::queue( 'edd', $order_id );
	}

	/**
	 * Queue the order whose thank-you page flag was just written as post meta:
	 * an EDD order, or a WooCommerce order kept in posts.
	 *
	 * @param int    $meta_id
	 * @param int    $object_id
	 * @param string $meta_key
	 * @return void
	 */
	public static function queueFlaggedOrder( $meta_id, $object_id, $meta_key ) {
		if ( PurchaseReport::PAGE_FLAG !== $meta_key ) {
			return;
		}
		// EDD 3 orders are not posts: a post id here is a WooCommerce order.
		self::queue( 'shop_order' === get_post_type( (int) $object_id ) ? 'woo' : 'edd', $object_id );
	}

	/**
	 * Remove the row of a deleted WooCommerce order.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public static function deleteWooOrder( $order_id ) {
		if ( 'before_delete_post' === current_action() && 'shop_order' !== get_post_type( $order_id ) ) {
			return;
		}
		self::delete( 'woo', $order_id );
	}

	/**
	 * Remove the row of a deleted EDD order.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public static function deleteEddOrder( $order_id ) {
		self::delete( 'edd', $order_id );
	}

	/**
	 * Rewrite the rows of the orders that changed in this request.
	 *
	 * @return void
	 */
	public static function flush() {
		$queue       = self::$queue;
		self::$queue = array();
		if ( empty( $queue ) || self::DB_VERSION !== (string) PurchaseReportState::get( 'db_version' ) ) {
			return;
		}

		foreach ( $queue as $item ) {
			// Runs on shutdown: one order that cannot be read must not stop the others.
			try {
				self::refresh( $item[0], $item[1] );
			} catch ( \Throwable $e ) {
				if ( PYS()->getLog() ) {
					PYS()->getLog()->error( "Purchase report row not written for {$item[0]} order {$item[1]}: " . $e->getMessage() );
				}
			}
		}
		delete_transient( self::WIDGET_CACHE );
	}

	/**
	 * Write the row of one order from its report.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return bool True when the order has a row afterwards
	 */
	public static function refresh( $type, $order_id ) {
		global $wpdb;

		if ( 'woo' === $type && function_exists( 'wc_get_order' ) ) {
			// Read the stored order, not an object another hook is still changing.
			$order = wc_get_order( (int) $order_id );
			if ( ! $order || 'shop_order' !== $order->get_type() ) {
				return false;
			}
			// A draft has no report to keep: nothing is computed for it.
			if ( self::isDraft( $order ) ) {
				self::delete( $type, $order_id );

				return false;
			}
		}

		$report = PurchaseReport::forOrder( $type, (int) $order_id );
		if ( null === $report || $report['order']['created'] < self::windowStart() ) {
			self::delete( $type, $order_id );

			return false;
		}

		$platforms = array();
		foreach ( $report['platforms'] as $platform => $row ) {
			$platforms[ $platform ] = array( $row['state'], $row['channel'], $row['note'] );
		}

		$data = array(
			'type'         => $type,
			'order_id'     => (int) $order_id,
			'verdict'      => $report['verdict'],
			'attention'    => $report['needs_attention'] ? 1 : 0,
			'order_total'  => $report['order']['total'],
			'currency'     => substr( $report['order']['currency'], 0, 8 ),
			'date_created' => gmdate( 'Y-m-d H:i:s', $report['order']['created'] ),
			'details'      => (string) wp_json_encode(
				array(
					'platforms' => $platforms,
					'ad_click'  => PurchaseReportOrderData::adClickPlatforms( $type, (int) $order_id ),
					'admin'     => $report['order']['created_in_admin'],
				)
			),
			'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		return false !== $wpdb->replace( self::table(), $data, array( '%s', '%d', '%s', '%d', '%f', '%s', '%s', '%s', '%s' ) );
	}

	/**
	 * Remove the row of one order.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return void
	 */
	public static function delete( $type, $order_id ) {
		global $wpdb;
		if ( self::DB_VERSION !== (string) PurchaseReportState::get( 'db_version' ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$wpdb->delete(
			self::table(),
			array(
				'type'     => $type,
				'order_id' => (int) $order_id,
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * WP-Cron: add recent orders that have no row yet, newest first, for a few
	 * seconds at most, and come back a minute later until the window is covered.
	 *
	 * The next run is scheduled before the work starts, and a page of orders
	 * is only left behind once every order of it has a row: a run that is cut
	 * short loses nothing and the next one continues where it stopped.
	 *
	 * @return void
	 */
	public static function fill() {
		if ( self::DB_VERSION !== (string) PurchaseReportState::get( 'db_version' ) ) {
			wp_clear_scheduled_hook( self::FILL_HOOK );

			return;
		}
		// A store that is not active keeps its place: it is filled once activated
		// (the hourly refresh calls fill()).
		$types = array_values( array_filter( array( 'woo', 'edd' ), array( __CLASS__, 'isFillingActive' ) ) );
		if ( empty( $types ) ) {
			wp_clear_scheduled_hook( self::FILL_HOOK );

			return;
		}
		if ( ! wp_next_scheduled( self::FILL_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::FILL_HOOK );
		}

		$deadline = microtime( true ) + self::FILL_SECONDS;

		foreach ( $types as $type ) {
			$option = 'fill_page_' . $type;
			$page   = (int) PurchaseReportState::get( $option );

			while ( $page >= 1 && microtime( true ) < $deadline ) {
				$order_ids = self::recentOrderIds( $type, $page );
				$missing   = array_diff( $order_ids, self::existingIds( $type, $order_ids ) );
				$finished  = true;

				foreach ( $missing as $order_id ) {
					if ( microtime( true ) >= $deadline ) {
						$finished = false;
						break;
					}
					// An order that gets no row (not a shop order, outside the window) is not asked again in this run.
					self::refresh( $type, $order_id );
				}
				if ( ! $finished ) {
					break;
				}

				$page = count( $order_ids ) < self::FILL_BATCH ? 0 : $page + 1;
				PurchaseReportState::set( array( $option => $page ) );
			}
		}

		delete_transient( self::WIDGET_CACHE );
		if ( ! self::isFillingActive( 'woo' ) && ! self::isFillingActive( 'edd' ) ) {
			wp_clear_scheduled_hook( self::FILL_HOOK );
		}
	}

	/**
	 * Does the background fill still have orders of an active store to add?
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return bool
	 */
	private static function isFillingActive( $type ) {
		$active = 'woo' === $type ? isWooCommerceActive() : isEddActive();

		return $active && self::isFilling( $type );
	}

	/**
	 * WP-Cron, hourly: continue a fill whose one-off event was lost, and remove
	 * the rows that left the window. A Free row does not change with time alone.
	 *
	 * @return void
	 */
	public static function refreshWindow() {
		self::fill();

		if ( self::DB_VERSION !== (string) PurchaseReportState::get( 'db_version' ) ) {
			return;
		}

		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- custom table, name built from $wpdb->prefix.
		$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE date_created < %s", gmdate( 'Y-m-d H:i:s', self::windowStart() ) ) );
		if ( $removed ) {
			delete_transient( self::WIDGET_CACHE );
		}
	}

	/**
	 * One page of the order ids of the window, newest first.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @param int    $page 1-based
	 * @return int[]
	 */
	private static function recentOrderIds( $type, $page ) {
		if ( 'woo' === $type ) {
			if ( ! function_exists( 'wc_get_orders' ) ) {
				return array();
			}

			return array_map(
				'intval',
				(array) wc_get_orders(
					array(
						'type'         => 'shop_order',
						'limit'        => self::FILL_BATCH,
						'paged'        => $page,
						'orderby'      => 'date',
						'order'        => 'DESC',
						'date_created' => '>' . self::windowStart(),
						'return'       => 'ids',
					)
				)
			);
		}

		if ( ! function_exists( 'edd_get_orders' ) ) {
			return array();
		}

		return array_map(
			'intval',
			(array) edd_get_orders(
				array(
					'type'               => 'sale',
					'number'             => self::FILL_BATCH,
					'offset'             => ( $page - 1 ) * self::FILL_BATCH,
					'orderby'            => 'id',
					'order'              => 'DESC',
					'date_created_query' => array(
						array(
							'after' => gmdate( 'Y-m-d H:i:s', self::windowStart() ),
						),
					),
					'fields'             => 'ids',
				)
			)
		);
	}

	/**
	 * The orders of a list that already have a row.
	 *
	 * @param string $type      'woo' or 'edd'
	 * @param int[]  $order_ids
	 * @return int[] The ids that have a row
	 */
	private static function existingIds( $type, array $order_ids ) {
		global $wpdb;
		if ( empty( $order_ids ) ) {
			return array();
		}

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery -- custom table; one %d per id.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT order_id FROM {$table} WHERE type = %s AND order_id IN ({$placeholders})",
				array_merge( array( $type ), $order_ids )
			)
		);
		// phpcs:enable

		return array_map( 'intval', $ids );
	}

	/**
	 * Is the background fill still adding orders?
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return bool
	 */
	public static function isFilling( $type ) {
		return (int) PurchaseReportState::get( 'fill_page_' . $type ) > 0;
	}

	/**
	 * The rows of a period, newest first.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @param int    $from Timestamp, inclusive
	 * @param int    $to   Timestamp, exclusive
	 * @return array<int, array{order_id: int, verdict: string, attention: bool, total: float, currency: string, created: int, platforms: array<string, array{0: string, 1: string, 2: string}>, ad_click: string[]}>
	 */
	public static function rows( $type, $from, $to ) {
		global $wpdb;
		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- custom table, name built from $wpdb->prefix.
		$stored = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_id, verdict, attention, order_total, currency, date_created, details FROM {$table}
				WHERE type = %s AND date_created >= %s AND date_created < %s
				ORDER BY date_created DESC LIMIT %d",
				$type,
				gmdate( 'Y-m-d H:i:s', (int) $from ),
				gmdate( 'Y-m-d H:i:s', (int) $to ),
				self::ROWS_LIMIT
			),
			ARRAY_A
		);
		// phpcs:enable

		$rows = array();
		foreach ( (array) $stored as $row ) {
			$details = json_decode( (string) $row['details'], true );
			$details = is_array( $details ) ? $details : array();
			$rows[]  = array(
				'order_id'         => (int) $row['order_id'],
				'verdict'          => (string) $row['verdict'],
				'attention'        => (bool) $row['attention'],
				'total'            => (float) $row['order_total'],
				'currency'         => (string) $row['currency'],
				'created'          => (int) strtotime( $row['date_created'] . ' UTC' ),
				'platforms'        => isset( $details['platforms'] ) && is_array( $details['platforms'] ) ? $details['platforms'] : array(),
				'ad_click'         => isset( $details['ad_click'] ) && is_array( $details['ad_click'] ) ? $details['ad_click'] : array(),
				// Created in wp-admin: no checkout, so no thank-you page.
				'created_in_admin' => ! empty( $details['admin'] ),
			);
		}

		return $rows;
	}

	/**
	 * Order ids of the window with a verdict, for the orders list filter.
	 *
	 * @param string $type   'woo' or 'edd'
	 * @param string $filter A PurchaseReport::VERDICT_* value, or 'attention'
	 * @return int[]
	 */
	public static function orderIds( $type, $filter ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- prepared in orderIdsSubquery().
		$ids = $wpdb->get_col( self::orderIdsSubquery( $type, $filter ) . $wpdb->prepare( ' ORDER BY date_created DESC LIMIT %d', self::ROWS_LIMIT ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * The order ids of orderIds() as a subquery for a store's own query: no id
	 * list, no row limit.
	 *
	 * @param string $type   'woo' or 'edd'
	 * @param string $filter A PurchaseReport::VERDICT_* value, or 'attention'
	 * @return string SQL, values already escaped
	 */
	public static function orderIdsSubquery( $type, $filter ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', self::windowStart() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, name built from $wpdb->prefix.
		if ( 'attention' === $filter ) {
			$sql = $wpdb->prepare( "SELECT order_id FROM {$table} WHERE type = %s AND attention = 1 AND date_created >= %s", $type, $since );
		} else {
			$sql = $wpdb->prepare( "SELECT order_id FROM {$table} WHERE type = %s AND verdict = %s AND date_created >= %s", $type, $filter, $since );
		}
		// phpcs:enable

		return $sql;
	}
}
