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
