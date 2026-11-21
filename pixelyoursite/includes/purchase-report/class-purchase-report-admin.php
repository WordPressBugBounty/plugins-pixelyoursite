<?php
/**
 * The purchase report in wp-admin: order box, orders list, dashboard widget
 * and settings card.
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the admin surfaces and hands their data to the templates in
 * views/. Every surface needs the manage_pys_reports capability and its own
 * switch in the "Native Data Tracking and Reporting" card.
 */
class PurchaseReportAdmin {

	const CAPABILITY      = 'manage_pys_reports';
	const COLUMN          = 'pys_purchase';
	const FILTER_ARG      = 'pys_purchase';
	const WIDGET_DAYS     = 30;
	const REVIEW_URL      = 'https://wordpress.org/support/plugin/pixelyoursite/reviews/#new-post';
	const REVIEW_ACTION   = 'pys_purchase_report_review';
	const REVIEW_MIN      = 10;
	const REVIEW_PERCENT  = 90;
	const REVIEW_LATER    = 60;
	const PRO_HIDE_ACTION = 'pys_purchase_report_hide_pro';
	const PRO_HIDE_META   = 'pys_purchase_report_pro_hidden_until';
	const PRO_HIDE_DAYS   = 30;
	const ALLOWED_FILTERS = array( 'tracked', 'not_tracked', 'skipped', 'attention' );

	/**
	 * Reports of the orders on the current list page.
	 *
	 * @var array<string, array<int, array>>
	 */
	private static $page_reports = array(
		'woo' => array(),
		'edd' => array(),
	);

	/**
	 * WooCommerce orders of the current list page (HPOS).
	 *
	 * @var array
	 */
	private static $page_orders = array();

	/**
	 * The verdict filter of this request hit PurchaseReportCache::ROWS_LIMIT.
	 *
	 * @var bool
	 */
	private static $filter_capped = false;

	/**
	 * Hook the report into the order screens, the orders lists and the dashboard.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueueAssets' ) );
		add_action( 'wp_ajax_' . self::REVIEW_ACTION, array( __CLASS__, 'ajaxReviewAsk' ) );
		add_action( 'admin_post_' . self::REVIEW_ACTION, array( __CLASS__, 'postReviewAsk' ) );
		add_action( 'wp_ajax_' . self::PRO_HIDE_ACTION, array( __CLASS__, 'ajaxHidePro' ) );
		add_action( 'admin_post_' . self::PRO_HIDE_ACTION, array( __CLASS__, 'postHidePro' ) );

		// Order box.
		add_action( 'add_meta_boxes', array( __CLASS__, 'addWooOrderBox' ), 5 );
		add_action( 'edd_view_order_details_main_before', array( __CLASS__, 'renderEddOrderBox' ) );

		// Orders list: column.
		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'addWooColumn' ), 20 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'addWooColumn' ), 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'renderWooColumn' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'renderWooColumn' ), 10, 2 );
		add_filter( 'edd_payments_table_columns', array( __CLASS__, 'addEddColumn' ), 20 );
		add_filter( 'edd_payments_table_column', array( __CLASS__, 'renderEddColumn' ), 10, 3 );

		// Orders list: summary box and filter.
		add_filter( 'woocommerce_order_query', array( __CLASS__, 'rememberWooPageOrders' ), 10, 2 );
		add_action( 'woocommerce_order_list_table_extra_tablenav', array( __CLASS__, 'renderWooListBoxHpos' ), 9, 2 );
		add_action( 'manage_posts_extra_tablenav', array( __CLASS__, 'renderWooListBoxLegacy' ) );
		add_action( 'edd_payments_page_bottom', array( __CLASS__, 'renderEddListBox' ) );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( __CLASS__, 'filterWooOrdersHpos' ) );
		add_filter( 'request', array( __CLASS__, 'filterWooOrdersLegacy' ) );
		add_filter( 'edd_orders_query_clauses', array( __CLASS__, 'filterEddOrders' ), 10, 2 );

		// Dashboard.
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'addDashboardWidgets' ) );
	}

	/**
	 * May the current user see the report, and is the surface switched on?
	 *
	 * @param string $option Option of the surface, without the store prefix
	 * @param string $type   'woo' or 'edd'
	 * @return bool
	 */
	public static function isSurfaceOn( $option, $type ) {
		if ( ! current_user_can( self::CAPABILITY ) || ! PYS()->getOption( $type . '_' . $option ) ) {
			return false;
		}

		return 'woo' === $type ? isWooCommerceActive() && function_exists( 'wc_get_orders' ) : isEddActive() && function_exists( 'edd_get_orders' );
	}

	/**
	 * Print a template of views/.
	 *
	 * @param string $name Template file name without ".php"
	 * @param array  $data Variables of the template
	 * @return void
	 */
	public static function view( $name, array $data = array() ) {
		$file = __DIR__ . '/views/' . $name . '.php';
		if ( ! is_readable( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables.
		extract( $data, EXTR_SKIP );
		include $file;
	}

	/**
	 * A template of views/ as a string.
	 *
	 * @param string $name
	 * @param array  $data
	 * @return string
	 */
	public static function viewHtml( $name, array $data = array() ) {
		ob_start();
		self::view( $name, $data );

		return (string) ob_get_clean();
	}

	/**
	 * Title of the report box for a store.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return string
	 */
	public static function boxTitle( $type ) {
		return 'edd' === $type ? __( 'PixelYourSite EDD Purchase', 'pys' ) : __( 'PixelYourSite WooCommerce Purchase', 'pys' );
	}

	/**
	 * Name of the store plugin.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return string
	 */
	public static function storeName( $type ) {
		return 'edd' === $type ? 'Easy Digital Downloads' : 'WooCommerce';
	}

	/**
	 * Link to the report settings card of a store.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return string
	 */
	public static function settingsUrl( $type ) {
		return admin_url( 'admin.php?page=pixelyoursite&tab=' . ( 'edd' === $type ? 'edd' : 'woo' ) . '#pys-purchase-report-settings' );
	}

	/**
	 * Link to the orders list of a store.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return string
	 */
	public static function ordersUrl( $type ) {
		if ( 'edd' === $type ) {
			return admin_url( 'edit.php?post_type=download&page=edd-payment-history' );
		}

		return isWooUseHPStorage() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );
	}

	/**
	 * Link to the edit page of an order.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return string
	 */
	public static function orderUrl( $type, $order_id ) {
		if ( 'edd' === $type ) {
			return admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . (int) $order_id );
		}
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null;

		return $order ? $order->get_edit_order_url() : '';
	}

	/**
	 * An amount in the store's own format, as plain text.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param float  $amount
	 * @param string $currency
	 * @return string
	 */
	public static function money( $type, $amount, $currency ) {
		if ( 'woo' === $type && function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $currency ) ) ), ENT_QUOTES, 'UTF-8' );
		}
		if ( 'edd' === $type && function_exists( 'edd_currency_filter' ) ) {
			return html_entity_decode( wp_strip_all_tags( edd_currency_filter( edd_format_amount( $amount ), $currency ) ), ENT_QUOTES, 'UTF-8' );
		}

		return number_format_i18n( (float) $amount, 2 ) . ' ' . $currency;
	}

	/**
	 * Formatter of amounts of a store, for PurchaseReportSummary.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return callable
	 */
	public static function moneyFormatter( $type ) {
		return function ( $amount, $currency ) use ( $type ) {
			return self::money( $type, $amount, $currency );
		};
	}

	/**
	 * Load the report styles and script on the admin pages that show the report.
	 *
	 * @param string $hook Admin page hook
	 * @return void
	 */
	public static function enqueueAssets( $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- admin_enqueue_scripts signature.
		if ( ! current_user_can( self::CAPABILITY ) || ! self::pageShowsReport() ) {
			return;
		}

		wp_enqueue_style( 'pys_purchase_report', PYS_FREE_URL . '/dist/styles/purchase-report.min.css', array(), PYS_FREE_VERSION );
		wp_enqueue_script( 'pys_purchase_report', PYS_FREE_URL . '/dist/scripts/purchase-report.min.js', array( 'jquery' ), PYS_FREE_VERSION, true );
		wp_localize_script(
			'pys_purchase_report',
			'pysPurchaseReport',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'reviewAction'  => self::REVIEW_ACTION,
				'reviewNonce'   => wp_create_nonce( self::REVIEW_ACTION ),
				'hideProAction' => self::PRO_HIDE_ACTION,
				'hideProNonce'  => wp_create_nonce( self::PRO_HIDE_ACTION ),
				'testAction'    => PurchaseReportEmail::TEST_ACTION,
				'testNonce'     => wp_create_nonce( PurchaseReportEmail::TEST_ACTION ),
				'sending'       => __( 'Sending…', 'pys' ),
				'sendFailed'    => __( 'Could not send.', 'pys' ),
			)
		);
	}

	/**
	 * Does the current admin page show a part of the report?
	 *
	 * @return bool
	 */
	public static function pageShowsReport() {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? (string) $screen->id : '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading the page slug and tab only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable

		if ( 'dashboard' === $screen_id ) {
			return self::isSurfaceOn( 'purchase_dashboard_widget', 'woo' ) || self::isSurfaceOn( 'purchase_dashboard_widget', 'edd' );
		}
		if ( 'pixelyoursite' === $page ) {
			// The settings card lives on the WooCommerce and EDD tabs.
			return in_array( $tab, array( 'woo', 'edd' ), true );
		}

		return in_array( $screen_id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true )
			|| in_array( $page, array( 'wc-orders', 'edd-payment-history' ), true );
	}

	/**
	 * Everything the order box shows.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return array|null Null when the order is unknown
	 */
	public static function orderBoxData( $type, $order_id ) {
		$report = PurchaseReport::forOrder( $type, (int) $order_id );
		if ( null === $report ) {
			return null;
		}

		return array(
			'type'         => $type,
			'report'       => $report,
			'visit'        => PurchaseReportOrderData::visit( $type, (int) $order_id ),
			'identifiers'  => PurchaseReportOrderData::identifiers( $type, (int) $order_id ),
			'ad_click'     => PurchaseReportOrderData::adClickPlatforms( $type, (int) $order_id ),
			'full_values'  => current_user_can( 'manage_options' ),
			'settings_url' => self::settingsUrl( $type ),
		);
	}

	/**
	 * Register the report box on the WooCommerce order screen.
	 *
	 * @return void
	 */
	public static function addWooOrderBox() {
		if ( ! self::isSurfaceOn( 'enabled_display_data_to_orders', 'woo' ) ) {
			return;
		}

		$screen = isWooUseHPStorage() && function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'pys_purchase_report_woo', self::boxTitle( 'woo' ), array( __CLASS__, 'renderWooOrderBox' ), $screen, 'normal', 'high' );
	}

	/**
	 * Print the report box of a WooCommerce order.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order
	 * @return void
	 */
	public static function renderWooOrderBox( $post_or_order ) {
		$order_id = 0;
		if ( $post_or_order instanceof \WP_Post ) {
			$order_id = (int) $post_or_order->ID;
		} elseif ( is_object( $post_or_order ) && method_exists( $post_or_order, 'get_id' ) ) {
			$order_id = (int) $post_or_order->get_id();
		}
		$data = $order_id && current_user_can( self::CAPABILITY ) ? self::orderBoxData( 'woo', $order_id ) : null;
		if ( null !== $data ) {
			self::view( 'order-box', $data );
		}
	}

	/**
	 * Print the report box on the EDD order screen.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public static function renderEddOrderBox( $order_id ) {
		if ( ! self::isSurfaceOn( 'enabled_display_data_to_orders', 'edd' ) ) {
			return;
		}

		$data = self::orderBoxData( 'edd', (int) $order_id );
		if ( null !== $data ) {
			self::view(
				'edd-order-postbox',
				array(
					'title' => self::boxTitle( 'edd' ),
					'box'   => $data,
				)
			);
		}
	}

	/**
	 * Add the "PixelYourSite Purchase" column to the WooCommerce orders list.
	 *
	 * @param array $columns
	 * @return array
	 */
	public static function addWooColumn( $columns ) {
		if ( self::isSurfaceOn( 'enabled_show_tracking_type', 'woo' ) ) {
			$columns[ self::COLUMN ] = __( 'PixelYourSite Purchase', 'pys' );
		}

		return $columns;
	}

	/**
	 * Add the "PixelYourSite Purchase" column to the EDD orders list.
	 *
	 * @param array $columns
	 * @return array
	 */
	public static function addEddColumn( $columns ) {
		if ( self::isSurfaceOn( 'enabled_show_tracking_type', 'edd' ) ) {
			$columns[ self::COLUMN ] = __( 'PixelYourSite Purchase', 'pys' );
		}

		return $columns;
	}

	/**
	 * The report of an order of the list page, computed once per request.
	 *
	 * @param string        $type        'woo' or 'edd'
	 * @param \WC_Order|int $order_or_id
	 * @return array|null
	 */
	private static function pageReport( $type, $order_or_id ) {
		$order_id = is_object( $order_or_id ) && method_exists( $order_or_id, 'get_id' ) ? (int) $order_or_id->get_id() : (int) $order_or_id;
		if ( ! array_key_exists( $order_id, self::$page_reports[ $type ] ) ) {
			self::$page_reports[ $type ][ $order_id ] = PurchaseReport::forOrder( $type, $order_or_id );
		}

		return self::$page_reports[ $type ][ $order_id ];
	}

	/**
	 * Print the report cell of a WooCommerce order in the orders list.
	 *
	 * @param string        $column
	 * @param \WC_Order|int $order_or_id Order (HPOS) or post id (legacy)
	 * @return void
	 */
	public static function renderWooColumn( $column, $order_or_id ) {
		if ( self::COLUMN !== $column || ! self::isSurfaceOn( 'enabled_show_tracking_type', 'woo' ) ) {
			return;
		}

		$report = self::pageReport( 'woo', $order_or_id );
		if ( null !== $report ) {
			self::view( 'orders-list-cell', array( 'report' => $report ) );
		}
	}

	/**
	 * The report cell of an EDD order in the orders list.
	 *
	 * @param string $value
	 * @param int    $order_id
	 * @param string $column
	 * @return string
	 */
	public static function renderEddColumn( $value, $order_id, $column ) {
		if ( self::COLUMN !== $column || ! self::isSurfaceOn( 'enabled_show_tracking_type', 'edd' ) ) {
			return $value;
		}

		$report = self::pageReport( 'edd', (int) $order_id );

		return null !== $report ? self::viewHtml( 'orders-list-cell', array( 'report' => $report ) ) : $value;
	}

	/**
	 * The HPOS list table keeps its orders private; the query result hands
	 * over the orders of the page without another query.
	 *
	 * @param object $results
	 * @param array  $args
	 * @return object
	 */
	public static function rememberWooPageOrders( $results, $args ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the page slug only.
		if ( isset( $_GET['page'] ) && 'wc-orders' === $_GET['page'] && ! empty( $args['paginate'] ) && isset( $results->orders ) ) {
			self::$page_orders = (array) $results->orders;
		}

		return $results;
	}

	/**
	 * Print the summary box above the WooCommerce orders list (HPOS).
	 *
	 * @param string $order_type
	 * @param string $which
	 * @return void
	 */
	public static function renderWooListBoxHpos( $order_type, $which ) {
		if ( 'top' !== $which || 'shop_order' !== $order_type || ! self::isSurfaceOn( 'enabled_show_tracking_type', 'woo' ) ) {
			return;
		}

		foreach ( self::$page_orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				self::pageReport( 'woo', $order );
			}
		}
		self::renderListBox( 'woo' );
	}

	/**
	 * Print the summary box above the WooCommerce orders list (posts storage).
	 *
	 * @param string $which
	 * @return void
	 */
	public static function renderWooListBoxLegacy( $which ) {
		global $wp_query, $typenow;
		if ( 'top' !== $which || 'shop_order' !== $typenow || empty( $wp_query->posts ) || ! self::isSurfaceOn( 'enabled_show_tracking_type', 'woo' ) ) {
			return;
		}

		foreach ( $wp_query->posts as $post ) {
			self::pageReport( 'woo', (int) $post->ID );
		}
		self::renderListBox( 'woo' );
	}

	/**
	 * Print the summary box of the EDD orders list.
	 *
	 * @return void
	 */
	public static function renderEddListBox() {
		if ( self::isSurfaceOn( 'enabled_show_tracking_type', 'edd' ) ) {
			self::renderListBox( 'edd' );
		}
	}

	/**
	 * The rows of the orders on the list page, in the shape of the cache rows.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return array
	 */
	public static function pageRows( $type ) {
		$rows = array();
		foreach ( array_filter( self::$page_reports[ $type ] ) as $report ) {
			$platforms = array();
			foreach ( $report['platforms'] as $platform => $row ) {
				$platforms[ $platform ] = array( $row['state'], $row['channel'], $row['note'] );
			}
			$rows[] = array(
				'order_id'         => $report['order_id'],
				'verdict'          => $report['verdict'],
				'attention'        => $report['needs_attention'],
				'total'            => $report['order']['total'],
				'currency'         => $report['order']['currency'],
				'created'          => $report['order']['created'],
				'platforms'        => $platforms,
				'ad_click'         => PurchaseReportOrderData::adClickPlatforms( $type, $report['order_id'] ),
				'created_in_admin' => $report['order']['created_in_admin'],
			);
		}

		return $rows;
	}

	/**
	 * The summary box above the orders table: the orders on the page.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return void
	 */
	private static function renderListBox( $type ) {
		$summary = PurchaseReportSummary::fromRows( self::pageRows( $type ) );
		if ( 0 === $summary['total'] ) {
			return;
		}

		$value_tracked = '';
		if ( null !== $summary['value_tracked'] && $summary['tracked'] > 0 ) {
			$value_tracked = self::money( $type, $summary['value_tracked'], $summary['currency'] );
		}

		self::view(
			'orders-list-box',
			array(
				'type'          => $type,
				'summary'       => $summary,
				'filter'        => self::currentFilter(),
				'orders_url'    => self::ordersUrl( $type ),
				'settings_url'  => self::settingsUrl( $type ),
				'is_filling'    => PurchaseReportCache::isFilling( $type ),
				'filter_capped' => self::$filter_capped,
				'window_days'   => PurchaseReportCache::WINDOW_DAYS,
				'value_tracked' => $value_tracked,
				'attention'     => PurchaseReportSummary::attentionLines( $summary ),
				'activity'      => PurchaseReportSummary::activityLines( $summary ),
			)
		);
	}

	/**
	 * The verdict filter of the request, when valid.
	 *
	 * @return string Empty when the list is not filtered
	 */
	private static function currentFilter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a list filter, like WordPress's own.
		$filter = isset( $_GET[ self::FILTER_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER_ARG ] ) ) : '';

		return in_array( $filter, self::ALLOWED_FILTERS, true ) && current_user_can( self::CAPABILITY ) ? $filter : '';
	}

	/**
	 * Limit the WooCommerce orders list (HPOS) to the orders of the chosen verdict.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function filterWooOrdersHpos( $args ) {
		$filter = self::currentFilter();
		if ( '' !== $filter ) {
			$args['id'] = self::wooFilterIds( $filter );
		}

		return $args;
	}

	/**
	 * Order ids for the WooCommerce list filter; notes when the list is cut at
	 * PurchaseReportCache::ROWS_LIMIT.
	 *
	 * @param string $filter
	 * @return int[] array( 0 ) when nothing matches
	 */
	private static function wooFilterIds( $filter ) {
		$order_ids           = PurchaseReportCache::orderIds( 'woo', $filter );
		self::$filter_capped = count( $order_ids ) >= PurchaseReportCache::ROWS_LIMIT;

		return ! empty( $order_ids ) ? $order_ids : array( 0 );
	}

	/**
	 * Limit the WooCommerce orders list (posts storage) to the orders of the chosen verdict.
	 *
	 * @param array $query_vars
	 * @return array
	 */
	public static function filterWooOrdersLegacy( $query_vars ) {
		$filter = isset( $query_vars['post_type'] ) && 'shop_order' === $query_vars['post_type'] ? self::currentFilter() : '';
		if ( '' !== $filter ) {
			$query_vars['post__in'] = self::wooFilterIds( $filter );
		}

		return $query_vars;
	}

	/**
	 * Limit the EDD orders list to the orders of the chosen verdict.
	 *
	 * @param array  $clauses
	 * @param object $query
	 * @return array
	 */
	public static function filterEddOrders( $clauses, $query ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- edd_orders_query_clauses signature.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the page slug only.
		$is_orders_page = isset( $_GET['page'] ) && 'edd-payment-history' === $_GET['page'];
		$filter         = $is_orders_page ? self::currentFilter() : '';
		if ( '' === $filter ) {
			return $clauses;
		}

		$condition        = 'edd_o.id IN (' . PurchaseReportCache::orderIdsSubquery( 'edd', $filter ) . ')';
		$clauses['where'] = ! empty( $clauses['where'] ) ? $clauses['where'] . ' AND ' . $condition : $condition;

		return $clauses;
	}

	/**
	 * Register the dashboard widget of each store that has it switched on.
	 *
	 * @return void
	 */
	public static function addDashboardWidgets() {
		foreach ( array( 'woo', 'edd' ) as $type ) {
			if ( self::isSurfaceOn( 'purchase_dashboard_widget', $type ) ) {
				wp_add_dashboard_widget(
					'pys_purchase_report_widget_' . $type,
					self::boxTitle( $type ),
					function () use ( $type ) {
						self::renderDashboardWidget( $type );
					}
				);
			}
		}
	}

	/**
	 * The last WIDGET_DAYS of a store, from the cache table.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return array PurchaseReportSummary::fromRows() value
	 */
	public static function widgetSummary( $type ) {
		$cached = get_transient( PurchaseReportCache::WIDGET_CACHE );
		$cached = is_array( $cached ) ? $cached : array();
		// Pro keeps another shape under the same key.
		if ( isset( $cached['free'][ $type ] ) ) {
			return $cached['free'][ $type ];
		}

		$now                     = time();
		$cached['free'][ $type ] = PurchaseReportSummary::forPeriod( $type, $now - self::WIDGET_DAYS * DAY_IN_SECONDS, $now + MINUTE_IN_SECONDS );
		set_transient( PurchaseReportCache::WIDGET_CACHE, array( 'free' => $cached['free'] ), HOUR_IN_SECONDS );

		return $cached['free'][ $type ];
	}

	/**
	 * Print the dashboard widget of a store.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return void
	 */
	public static function renderDashboardWidget( $type ) {
		$summary  = self::widgetSummary( $type );
		$pro_line = self::isProLineHidden() ? '' : PurchaseReportSummary::proLine( $type, $summary, self::moneyFormatter( $type ) );

		self::view(
			'dashboard-widget',
			array(
				'type'          => $type,
				'summary'       => $summary,
				'days'          => self::WIDGET_DAYS,
				'is_filling'    => PurchaseReportCache::isFilling( $type ),
				'attention_url' => add_query_arg( self::FILTER_ARG, 'attention', self::ordersUrl( $type ) ),
				'settings_url'  => self::settingsUrl( $type ),
				'value_tracked' => null !== $summary['value_tracked'] ? self::money( $type, $summary['value_tracked'], $summary['currency'] ) : '',
				'value_total'   => null !== $summary['value_total'] ? self::money( $type, $summary['value_total'], $summary['currency'] ) : '',
				'activity'      => PurchaseReportSummary::activityLines( $summary ),
				// The Pro line wins: the review ask shows only when no purchase was lost.
				'show_review'   => 0 === $summary['by_verdict'][ PurchaseReport::VERDICT_NOT_TRACKED ] && self::shouldAskForReview( $summary ),
				'pro_line'      => $pro_line,
				'hide_pro_url'  => self::actionUrl( self::PRO_HIDE_ACTION ),
				'review_later'  => self::actionUrl( self::REVIEW_ACTION, array( 'choice' => 'later' ) ),
				'review_skip'   => self::actionUrl( self::REVIEW_ACTION, array( 'choice' => 'dismiss' ) ),
			)
		);
	}

	/**
	 * A link to an admin-post action with its nonce; scripts send the same action by AJAX.
	 *
	 * @param string $action
	 * @param array  $args
	 * @return string
	 */
	private static function actionUrl( $action, array $args = array() ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ), $action );
	}

	/**
	 * Has the current user hidden the Pro line of the widget?
	 *
	 * @return bool
	 */
	public static function isProLineHidden() {
		return (int) get_user_meta( get_current_user_id(), self::PRO_HIDE_META, true ) > time();
	}

	/**
	 * The review ask shows to administrators only, after a good period, and
	 * never again once dismissed.
	 *
	 * @param array $summary PurchaseReportSummary::fromRows() value
	 * @return bool
	 */
	public static function shouldAskForReview( array $summary ) {
		if ( ! current_user_can( 'manage_options' ) || PurchaseReportState::get( 'review_dismissed' ) ) {
			return false;
		}
		if ( (int) PurchaseReportState::get( 'review_later_until', 0 ) > time() ) {
			return false;
		}

		return $summary['total'] >= self::REVIEW_MIN && PurchaseReportSummary::trackedPercent( $summary ) >= self::REVIEW_PERCENT;
	}

	/**
	 * "Later" hides the review ask for REVIEW_LATER days, "Dismiss" for good.
	 *
	 * @param string $choice 'later' or 'dismiss'
	 * @return void
	 */
	public static function saveReviewChoice( $choice ) {
		if ( 'dismiss' === $choice ) {
			PurchaseReportState::set( array( 'review_dismissed' => 1 ) );
		} else {
			PurchaseReportState::set( array( 'review_later_until' => time() + self::REVIEW_LATER * DAY_IN_SECONDS ) );
		}
		delete_transient( PurchaseReportCache::WIDGET_CACHE );
	}

	/**
	 * AJAX: the review ask choice.
	 *
	 * @return void
	 */
	public static function ajaxReviewAsk() {
		check_ajax_referer( self::REVIEW_ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		self::saveReviewChoice( isset( $_POST['choice'] ) ? sanitize_key( wp_unslash( $_POST['choice'] ) ) : '' );
		wp_send_json_success();
	}

	/**
	 * The review ask choice from its link, without scripts.
	 *
	 * @return void
	 */
	public static function postReviewAsk() {
		check_admin_referer( self::REVIEW_ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'pys' ), 403 );
		}

		self::saveReviewChoice( isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '' );
		self::redirectBack();
	}

	/**
	 * "Hide for 30 days": the Pro line of the widget, for the current user only.
	 *
	 * @return void
	 */
	public static function hideProLine() {
		update_user_meta( get_current_user_id(), self::PRO_HIDE_META, time() + self::PRO_HIDE_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * AJAX: "Hide for 30 days".
	 *
	 * @return void
	 */
	public static function ajaxHidePro() {
		check_ajax_referer( self::PRO_HIDE_ACTION );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}

		self::hideProLine();
		wp_send_json_success();
	}

	/**
	 * "Hide for 30 days" from its link, without scripts.
	 *
	 * @return void
	 */
	public static function postHidePro() {
		check_admin_referer( self::PRO_HIDE_ACTION );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'pys' ), 403 );
		}

		self::hideProLine();
		self::redirectBack();
	}

	/**
	 * Back to the page the link was on, the dashboard by default.
	 *
	 * @return void
	 */
	private static function redirectBack() {
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * The "Native Data Tracking and Reporting" card of a store's settings tab.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return void
	 */
	public static function renderSettingsCard( $type ) {
		self::view(
			'settings-card',
			array(
				'type'        => $type,
				'short_name'  => 'edd' === $type ? 'EDD' : 'WooCommerce',
				'orders_page' => 'edd' === $type ? __( 'Downloads › Orders', 'pys' ) : __( 'WooCommerce › Orders', 'pys' ),
				'email_name'  => 'edd' === $type ? __( '"New sale" notification', 'pys' ) : __( '"New Order" email', 'pys' ),
				'summary'     => PurchaseReportEmail::scheduleLine(),
			)
		);
	}
}
