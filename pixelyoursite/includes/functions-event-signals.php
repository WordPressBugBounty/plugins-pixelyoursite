<?php
/**
 * Which request-level signals a server event may carry.
 *
 * A server event built for a WooCommerce order does not always run in the
 * customer's request. A status change in wp-admin runs in the administrator's;
 * a gateway webhook runs in the gateway's; cron runs in nobody's. Reading
 * client_ip_address, client_user_agent and the _fbp/_fbc cookies from the
 * current request there describes the wrong person — and when an administrator
 * once clicked the store's own ad, it hands Meta that click as the purchase's,
 * so a manual order is attributed to a campaign.
 *
 * WooCommerce stores the IP and user agent of the checkout request on the
 * order, so an order-bound event takes them from there. The cookies have their
 * own order-side copy (the pys_fb_cookie meta); what this file decides is
 * whether the *request's* cookies may stand in when the order has none.
 */

namespace PixelYourSite;

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );

/**
 * True when the current request is a wp-admin page: the person behind it is
 * a site administrator, not the visitor an event describes.
 *
 * admin-ajax.php also answers true to is_admin(), but the front end calls it
 * for a visitor, so it is excluded. REST, cron and webhooks answer false; they
 * carry no cookies, and for order events the order's own IP takes precedence.
 *
 * @return bool
 */
function pys_is_admin_page_request() {
	return is_admin() && ! wp_doing_ajax();
}

/**
 * The IP and user agent for a server event, and whether the request's own
 * cookies may be read for it.
 *
 * The order wins when it has a value: it was captured when the order was
 * placed and describes that customer. The request stands in only when it is
 * the visitor's own. In wp-admin it is the administrator's, so nothing stands
 * in, and an order the administrator created has no checkout request behind
 * it at all — HPOS stores nothing, the legacy wc_create_order() path stores
 * the administrator's address — so its stored values are not used either.
 *
 * @param object|null $order         The WooCommerce order the event belongs to: a WC_Order,
 *                                   or anything with get_customer_ip_address(),
 *                                   get_customer_user_agent() and get_created_via().
 *                                   Null for an event without one.
 * @param string      $request_ip    Routable IP of the current request, '' when none.
 * @param string|null $request_ua    User agent of the current request, null when none.
 * @param bool        $admin_request The current request is a wp-admin page.
 * @return array{ip: string, user_agent: ?string, request_cookies: bool}
 *               'ip' is '' and 'user_agent' is null when the event should carry none.
 */
function pys_event_client_signals( $order, $request_ip, $request_ua, $admin_request ) {
	$ip = '';
	$ua = null;

	if ( $order && 'admin' !== (string) $order->get_created_via() ) {
		// Same bar as a header value: a private or malformed address occupies
		// the field Meta weighs most with something that cannot match.
		$ip = pys_normalize_ip( (string) $order->get_customer_ip_address() );
		if ( '' !== $ip && ! pys_is_public_ip( $ip ) ) {
			$ip = '';
		}

		$ua = (string) $order->get_customer_user_agent();
		$ua = '' !== $ua ? $ua : null;
	}

	if ( ! $admin_request ) {
		if ( '' === $ip ) {
			$ip = (string) $request_ip;
		}
		if ( null === $ua ) {
			$ua = $request_ua;
		}
	}

	return array(
		'ip'              => $ip,
		'user_agent'      => $ua,
		'request_cookies' => ! $admin_request,
	);
}

/**
 * Whether the checkout request that created an EDD payment may be recorded on it.
 *
 * The same bar for every saver that copies request state onto the payment:
 * a payment added by hand in wp-admin was not placed by a buyer, so the
 * administrator's IP, browser and cookies must not be stored as theirs.
 * Checkout through admin-ajax.php is the buyer's own request and stays allowed.
 *
 * @param int|string $payment_id
 * @return bool
 */
function pys_edd_order_snapshot_allowed( $payment_id ) {
	return ! empty( $payment_id ) && ! pys_is_admin_page_request();
}

/**
 * The IP and user agent of an EDD payment, behind the getters
 * pys_event_client_signals() reads from a WooCommerce order.
 *
 * EDD keeps the checkout IP but not the user agent, and it has no created_via,
 * so the cores keep their own copy (see pys_save_client_signals_in_edd_order()).
 * Only payments with a snapshot get one of these, so created_via is always
 * 'checkout': a payment added in wp-admin never gets a snapshot to read.
 */
final class EddOrderSignals {

	/** @var string */
	private $ip;

	/** @var string */
	private $ua;

	/**
	 * @param string $ip
	 * @param string $ua
	 */
	public function __construct( $ip, $ua ) {
		$this->ip = (string) $ip;
		$this->ua = (string) $ua;
	}

	public function get_customer_ip_address() {
		return $this->ip;
	}

	public function get_customer_user_agent() {
		return $this->ua;
	}

	public function get_created_via() {
		return 'checkout';
	}
}

/**
 * The saved buyer signals of an EDD payment, as an order-like object.
 *
 * Null means "no snapshot": an EDD site that is not loaded, or a payment placed
 * before this release or in wp-admin. The caller then treats it like a
 * WooCommerce order that stored nothing.
 *
 * @param int|string $payment_id
 * @return EddOrderSignals|null
 */
function pys_edd_order_signal_source( $payment_id ) {
	if ( ! $payment_id || ! function_exists( 'edd_get_payment_meta' ) ) {
		return null;
	}

	$meta = edd_get_payment_meta( $payment_id, 'pys_client_signals', true );
	if ( ! is_array( $meta ) ) {
		return null;
	}

	return new EddOrderSignals(
		isset( $meta['ip'] ) ? (string) $meta['ip'] : '',
		isset( $meta['ua'] ) ? (string) $meta['ua'] : ''
	);
}

/**
 * Keep the buyer's IP and user agent on the EDD payment at checkout.
 *
 * Later server events for the payment (gateway webhook, wp-admin status
 * change, cron) do not run in the buyer's request. Hooked to
 * edd_insert_payment, which normally fires in the checkout request (also from
 * `wp edd create_payments` and the deprecated Recurring record_subscription_payment,
 * where nothing, or the request's own signals, get saved).
 *
 * @param int $payment_id
 * @return void
 */
function pys_save_client_signals_in_edd_order( $payment_id ) {
	if ( ! pys_edd_order_snapshot_allowed( $payment_id ) || ! function_exists( 'edd_update_payment_meta' ) ) {
		return;
	}

	$ip = (string) pys_client_ip_for_capi();
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
		? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
		: '';

	if ( '' === $ip && '' === $ua ) {
		return;
	}

	edd_update_payment_meta( $payment_id, 'pys_client_signals', array( 'ip' => $ip, 'ua' => $ua ) );
}

/**
 * Drop the PYS snapshots from an EDD order that is being anonymised.
 *
 * EDD's eraser anonymises the order's own IP and then hands add-ons the
 * rest through edd_anonymize_payment. The snapshots are the person's click
 * and browser identifiers and request signals, so they go too.
 * pys_ga_cookie and external_id are deliberately not in the default list:
 * they are older keys with their own semantics. The filter lets a site widen it.
 *
 * @param object $order EDD\Orders\Order
 * @return void
 */
function pys_anonymize_edd_order_snapshots( $order ) {
	if ( ! is_object( $order ) || empty( $order->id ) || ! function_exists( 'edd_delete_order_meta' ) ) {
		return;
	}

	$keys = apply_filters( 'pys_edd_anonymize_meta_keys', array( 'pys_client_signals', 'pys_fb_cookie', 'pys_tt_cookie', 'pys_pin_cookie', 'pys_oai_cookie' ) );

	foreach ( (array) $keys as $key ) {
		edd_delete_order_meta( (int) $order->id, $key );
	}
}
