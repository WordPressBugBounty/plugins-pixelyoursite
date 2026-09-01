<?php
/**
 * REST Purchase Guard (Free)
 *
 * Central validation for purchase events coming through the public REST
 * tracking endpoints. In the free plugin the built-in endpoint is Facebook
 * (`pys-facebook/v1/event`); add-ons (Pinterest, Bing, Reddit) register their
 * own endpoints. This guard covers them all.
 *
 * The problem it solves: the /event REST endpoints are, by design, reachable
 * by anonymous visitors (tracking must work on cached pages). Historically the
 * only gate was an Origin/Referer check, which is trivially spoofable. That let
 * anyone POST a purchase event referencing any / non-existent order and inject
 * arbitrary metrics.
 *
 * This guard hooks the WordPress core `rest_pre_dispatch` filter — a single
 * choke point that runs for every REST request before the route handler, so it
 * covers the built-in Facebook endpoint AND separate add-on endpoints WITHOUT
 * editing those add-ons.
 *
 * Scope (Phase 1):
 *   - Every event: its event_slug must be a slug the system actually
 *     registers (built-ins + 'custom_event'); arbitrary/injected slugs are
 *     rejected.
 *   - Purchase events (by slug or platform event name): must reference a real,
 *     valid order (existence + status, plus a soft order_key binding when the
 *     key is provided).
 *
 * Scope (Phase 2):
 *   - Purchase / CompletePayment: the monetary value is overwritten with a
 *     server-authoritative amount derived from the order, so a tampered client
 *     value cannot inflate metrics. Only the value is touched.
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'PixelYourSite\\RestAPIGuard' ) ) {

    class RestAPIGuard {

        /**
         * @var RestAPIGuard|null
         */
        private static $instance = null;

	    /**
	     * REST namespaces we guard (built-in + free-ecosystem add-ons).
	     *
	     * @var string[]
	     */
	    private $namespaces = array( 'pys-facebook', 'pys-pinterest', 'pys-bing', 'pys-reddit', 'pys-openai' );

        /**
         * PYS event slugs that MUST be backed by a real order.
         *
         * @var string[]
         */
        private $purchase_slugs = array( 'woo_purchase', 'edd_purchase', 'edd_complete_payment' );

        /**
         * Platform purchase event names that MUST be backed by a real order,
         * matched case-insensitively. Facebook => 'Purchase'. ('checkout' is
         * intentionally omitted so a checkout without an order is not rejected; it
         * is still validated whenever it carries one.)
         *
         * @var string[]
         */
        private $purchase_names = array( 'Purchase', 'CompletePayment' );

        /**
         * Singleton accessor.
         *
         * @return RestAPIGuard
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_filter( 'rest_pre_dispatch', array( $this, 'guard' ), 10, 3 );
        }

        /**
         * Validate purchase REST requests before they reach the route handler.
         *
         * @param mixed             $result  Response to short-circuit with, or null to continue.
         * @param \WP_REST_Server   $server  Server instance (unused).
         * @param \WP_REST_Request  $request Current request.
         * @return mixed  WP_Error to reject, otherwise the untouched $result (continue).
         */
        public function guard( $result, $server, $request ) {

            // Respect a short-circuit already produced by another integration.
            if ( null !== $result ) {
                return $result;
            }

            // Fast path: rest_pre_dispatch runs for EVERY REST request site-wide,
            // so bail out immediately for anything that is not one of our /event
            // endpoints before doing any further work.
            if ( ! $this->is_guarded_route( $request ) ) {
                return $result;
            }

            // Master switch for site owners / edge setups.
            if ( ! apply_filters( 'pys_rest_purchase_guard_enabled', true ) ) {
                return $result;
            }

            $woo_order  = $this->to_id( $request->get_param( 'woo_order' ) );
            $edd_order  = $this->to_id( $request->get_param( 'edd_order' ) );
            $event_slug = trim( (string) $request->get_param( 'event_slug' ) );
            $event_name = (string) $request->get_param( 'event' );

            // Validate the event slug when one is present: it must correspond to a
            // slug the system actually registers (built-ins + 'custom_event').
            // Arbitrary / injected slugs are rejected outright. Case-sensitive:
            // system slugs are canonical lowercase, so any other casing is invalid.
            //
            // The browser sends event_slug (= the event's e_id) for every event, so
            // this applies to all real traffic. The `'' !==` guard is a safety net
            // for edge/add-on requests that omit the slug (validation is skipped
            // rather than rejecting them outright).
            if ( '' !== $event_slug && apply_filters( 'pys_rest_validate_event_slug', true, $request ) ) {
                if ( ! in_array( $event_slug, $this->valid_event_slugs(), true ) ) {
                    return $this->reject( 'invalid_event_slug', 'Unknown event slug.', $request, 0 );
                }
            }

            $has_order = ( $woo_order > 0 || $edd_order > 0 );

            // Ecommerce purchases are keyed by their PYS slug (woo_purchase /
            // edd_purchase / edd_complete_payment) and ALWAYS require a real order.
            // A custom event that fires a purchase is detected only by the platform
            // event NAME (e.g. a custom "Purchase" event); such events are NOT tied
            // to a WooCommerce/EDD order, so they are handled separately below.
            $is_ecom_purchase = $this->matches_ci( $event_slug, $this->purchase_slugs );
            $is_purchase      = $is_ecom_purchase
                || $this->matches_ci( $event_name, $this->purchase_names );

            // A purchase detected only by event name (not by an ecommerce slug) is
            // a custom event firing a purchase — not tied to a store order.
            $is_custom_purchase = $is_purchase && ! $is_ecom_purchase;

            // Not order-related and not a purchase — leave other events untouched.
            if ( ! $has_order && ! $is_purchase ) {
                return $result;
            }

            // Purchase without an order reference.
            if ( ! $has_order ) {
                if ( $is_ecom_purchase ) {
                    // Store purchase must reference a real order.
                    if ( apply_filters( 'pys_rest_require_order_for_purchase', true, $event_slug, $request ) ) {
                        return $this->reject( 'missing_order', 'Purchase event without an order reference.', $request, 0 );
                    }
                } else {
                    // Custom purchase event (not tied to a store order). Allowed by
                    // default — opt in to enforce an order via this filter.
                    if ( apply_filters( 'pys_rest_require_order_for_custom_purchase', false, $event_slug, $request ) ) {
                        return $this->reject( 'missing_order', 'Custom purchase event without an order reference.', $request, 0 );
                    }
                    // Tag it so the pixels can tell a custom purchase apart from a
                    // genuine store purchase.
                    $this->mark_custom_event( $request );
                }
                return $result;
            }

            $order_key = $this->to_key( $request->get_param( 'order_key' ) );

            if ( $woo_order > 0 ) {
                $verdict = $this->validate_woo( $woo_order, $order_key, $request );
            } else {
                $verdict = $this->validate_edd( $edd_order, $order_key, $request );
            }

            if ( is_wp_error( $verdict ) ) {
                return $verdict;
            }

            // Custom purchase that (unusually) carries an order — still tag it.
            if ( $is_custom_purchase ) {
                $this->mark_custom_event( $request );
            }

            // Phase 2: enforce a server-authoritative value for Purchase /
            // CompletePayment so a tampered client value cannot inflate metrics.
            if ( $this->matches_ci( $event_name, $this->purchase_names )
                && apply_filters( 'pys_rest_enforce_purchase_value', true, $request ) ) {
                $this->enforce_authoritative_value( $request, $woo_order, $edd_order );
            }

            return $result; // Valid — let the request continue to its handler.
        }

        /**
         * Tag the outgoing event data so downstream pixels / CAPI can tell a
         * custom purchase event apart from a genuine store purchase. Sets
         * data['pys_custom_event'] = true on the server (CAPI) copy.
         *
         * @param \WP_REST_Request $request
         * @return void
         */
        private function mark_custom_event( $request ) {
            $data = $request->get_param( 'data' );
            if ( is_string( $data ) ) {
                $decoded = json_decode( $data, true );
                $data    = is_array( $decoded ) ? $decoded : array();
            }
            if ( ! is_array( $data ) ) {
                $data = array();
            }

            $data['pys_custom_event'] = true;

            $request->set_param( 'data', wp_json_encode( $data ) );
        }

        /**
         * Overwrite the event value with a server-derived, order-authoritative
         * amount. Only the monetary value is touched:
         *   - data['value'] is set directly (Facebook reads it as-is);
         *   - if data['contents'] is present, item prices are rewritten so their
         *     quantity*price total equals the authoritative value (TikTok derives
         *     the value from contents, ignoring data['value']). content_ids and
         *     quantities are preserved.
         *
         * @param \WP_REST_Request $request
         * @param int              $woo_order
         * @param int              $edd_order
         * @return void
         */
        private function enforce_authoritative_value( $request, $woo_order, $edd_order ) {
            $value = $this->authoritative_value( $woo_order, $edd_order );
            if ( null === $value ) {
                return;
            }

            $data = $request->get_param( 'data' );
            if ( is_string( $data ) ) {
                $decoded = json_decode( $data, true );
                $data    = is_array( $decoded ) ? $decoded : array();
            }
            if ( ! is_array( $data ) ) {
                $data = array();
            }

        if ( $this->is_route_of( $request, 'pys-openai' ) ) {
            $nested       = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
            $data['data'] = $this->authoritative_openai_data( $nested, $value, $woo_order, $edd_order );
            $request->set_param( 'data', wp_json_encode( $data ) );
            return;
        }

        // Direct value (Facebook Purchase).
        $data['value'] = $value;

            // Contents-derived value (TikTok CompletePayment): the value is the sum
            // of quantity*price over items, so make that sum equal the authoritative
            // value. Scale item prices proportionally — an already-correct contents
            // array stays untouched (factor 1) and only a tampered one is corrected,
            // preserving per-item proportions. Fall back to an even per-unit split
            // only when current prices sum to zero (nothing to scale).
            if ( ! empty( $data['contents'] ) && is_array( $data['contents'] ) ) {
                $current_sum = 0;
                $total_qty   = 0;
                foreach ( $data['contents'] as $c ) {
                    $qty          = isset( $c['quantity'] ) ? (float) $c['quantity'] : 0;
                    $price        = isset( $c['price'] ) ? (float) $c['price'] : 0;
                    $current_sum += $qty * $price;
                    $total_qty   += $qty;
                }

                if ( $current_sum > 0 ) {
                    $factor = $value / $current_sum;
                    foreach ( $data['contents'] as $k => $c ) {
                        if ( isset( $c['price'] ) ) {
                            $data['contents'][ $k ]['price'] = (float) $c['price'] * $factor;
                        }
                    }
                } elseif ( $total_qty > 0 ) {
                    $unit_price = $value / $total_qty;
                    foreach ( $data['contents'] as $k => $c ) {
                        if ( isset( $c['quantity'] ) ) {
                            $data['contents'][ $k ]['price'] = $unit_price;
                        }
                    }
                }
            }

            $request->set_param( 'data', wp_json_encode( $data ) );
        }

    /**
     * Rewrite an OpenAI data object so its money is the order's, not the
     * browser's.
     *
     * @param array      $data      The nested OpenAI data object.
     * @param float      $value     Server-derived order total, in major units.
     * @param int        $woo_order
     * @param int        $edd_order
     * @return array
     */
    private function authoritative_openai_data( array $data, $value, $woo_order, $edd_order ) {

        $currency = $this->order_currency( $woo_order, $edd_order );

        if ( '' === $currency ) {
            unset( $data['amount'], $data['currency'] );
            return $data;
        }

        if ( ! function_exists( 'PixelYourSite\\OpenAI\\Helpers\\pys_openai_to_minor_units' ) ) {
            return $data;
        }

        $minor = \PixelYourSite\OpenAI\Helpers\pys_openai_to_minor_units( $value, $currency );

        if ( null === $minor ) {
            return $data;
        }

        $submitted        = isset( $data['amount'] ) ? (int) $data['amount'] : 0;
        $data['amount']   = $minor;
        $data['currency'] = $currency;

        if ( empty( $data['contents'] ) || ! is_array( $data['contents'] ) ) {
            return $data;
        }

        $reference = $submitted > 0 ? $submitted : $this->openai_contents_sum( $data['contents'] );

        if ( $reference <= 0 || $reference === $minor ) {
            return $data;
        }

        $factor = $minor / $reference;

        foreach ( $data['contents'] as $k => $item ) {

            if ( isset( $item['amount'] ) ) {
                $data['contents'][ $k ]['amount'] = (int) round( (float) $item['amount'] * $factor );
            }

            if ( isset( $item['currency'] ) ) {
                $data['contents'][ $k ]['currency'] = $currency;
            }
        }

        return $data;
    }

    /**
     * Sum of quantity * amount over an OpenAI contents array, in minor units.
     *
     * @param array $contents
     * @return int
     */
    private function openai_contents_sum( array $contents ) {

        $sum = 0;

        foreach ( $contents as $item ) {
            $qty    = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
            $amount = isset( $item['amount'] ) ? (int) $item['amount'] : 0;
            $sum   += $qty * $amount;
        }

        return $sum;
    }

    /**
     * The order's own currency, which differs from the shop's on a
     * multi-currency site.
     *
     * @param int $woo_order
     * @param int $edd_order
     * @return string Empty when it cannot be resolved.
     */
    private function order_currency( $woo_order, $edd_order ) {

        if ( $woo_order > 0 && function_exists( 'wc_get_order' ) ) {

            $order = wc_get_order( $woo_order );

            if ( $order && method_exists( $order, 'get_currency' ) && $order->get_currency() ) {
                return (string) $order->get_currency();
            }
        }

        if ( $edd_order > 0 && function_exists( 'edd_get_payment_currency' ) ) {

            $currency = edd_get_payment_currency( $edd_order );

            if ( ! empty( $currency ) ) {
                return (string) $currency;
            }
        }

        return '';
    }

    /**
     * Whether this request is for a route in the given namespace.
     *
     * @param \WP_REST_Request $request
     * @param string           $namespace
     * @return bool
     */
    private function is_route_of( $request, $namespace ) {
        return 0 === strpos( (string) $request->get_route(), '/' . $namespace . '/' );
    }

    /**
     * Compute the order-authoritative value using the SAME helpers and options
     * the plugin uses when it builds the purchase event server-side, so the
     * result matches a legitimate event.
     *
     * @param int $woo_order
     * @param int $edd_order
     * @return float|null Null when it cannot be determined.
     */
    private function authoritative_value( $woo_order, $edd_order ) {
        if ( $woo_order > 0
            && function_exists( 'wc_get_order' )
            && function_exists( 'PixelYourSite\\getWooEventValueOrder' ) ) {
            $order = wc_get_order( $woo_order );
            if ( $order instanceof \WC_Order ) {
                $option  = PYS()->getOption( 'woo_purchase_value_option' );
                $global  = PYS()->getOption( 'woo_purchase_value_global', 0 );
                $percent = PYS()->getOption( 'woo_purchase_value_percent', 100 );
                return (float) getWooEventValueOrder( $option, $order, $global, $percent );
            }
        }

            if ( $edd_order > 0
                && function_exists( 'edd_get_payment_amount' )
                && function_exists( 'PixelYourSite\\getEddEventValue' ) ) {
                $option  = PYS()->getOption( 'edd_purchase_value_option' );
                $global  = PYS()->getOption( 'edd_purchase_value_global', 0 );
                $percent = PYS()->getOption( 'edd_purchase_value_percent', 100 );
                $amount  = (float) edd_get_payment_amount( $edd_order );
                return (float) getEddEventValue( $option, $amount, $global, $percent );
            }

            return null;
        }

        /**
         * WooCommerce order validation.
         *
         * @param int              $order_id  Claimed order ID.
         * @param string           $order_key Submitted order key ('' if not provided).
         * @param \WP_REST_Request $request   Current request.
         * @return true|\WP_Error
         */
        private function validate_woo( $order_id, $order_key, $request ) {

            // Woo not available in this context — nothing to validate against.
            if ( ! function_exists( 'wc_get_order' ) ) {
                return true;
            }

            $order = wc_get_order( $order_id );
            if ( ! $order instanceof \WC_Order ) {
                return $this->reject( 'invalid_order', 'WooCommerce order does not exist.', $request, $order_id );
            }

            // Mirror the plugin's own rule (EventsWoo::getPurchaseOrderId): the
            // browser purchase is not fired for statuses listed in
            // `woo_order_purchase_disabled_status`. Values are stored WITH the
            // "wc-" prefix and compared against "wc-" . get_status( 'edit' ).
            $disabled = (array) apply_filters(
                'pys_rest_woo_blocked_statuses',
                (array) PYS()->getOption( 'woo_order_purchase_disabled_status' )
            );
            $status = 'wc-' . $order->get_status( 'edit' );
            if ( in_array( $status, $disabled, true ) ) {
                return $this->reject( 'invalid_order_status', 'WooCommerce order status is not allowed.', $request, $order_id );
            }

            // order_key is validated softly: only when the client actually sent one.
            if ( '' !== $order_key ) {
                $real_key = (string) $order->get_order_key();
                if ( '' === $real_key || ! hash_equals( $real_key, $order_key ) ) {
                    return $this->reject( 'invalid_order_key', 'WooCommerce order key mismatch.', $request, $order_id );
                }
            }

            return true;
        }

        /**
         * Easy Digital Downloads order validation.
         *
         * @param int              $order_id  Claimed payment/order ID.
         * @param string           $order_key Submitted payment key ('' if not provided).
         * @param \WP_REST_Request $request   Current request.
         * @return true|\WP_Error
         */
        private function validate_edd( $order_id, $order_key, $request ) {

            if ( ! function_exists( 'edd_get_payment' ) ) {
                return true;
            }

            $payment = edd_get_payment( $order_id );
            if ( empty( $payment ) || empty( $payment->ID ) ) {
                return $this->reject( 'invalid_order', 'EDD order does not exist.', $request, $order_id );
            }

            $status  = function_exists( 'edd_get_payment_status' ) ? edd_get_payment_status( $payment ) : '';
            $blocked = apply_filters(
                'pys_rest_edd_blocked_statuses',
                array( 'failed', 'abandoned', 'revoked', 'cancelled' )
            );
            if ( $status && in_array( $status, (array) $blocked, true ) ) {
                return $this->reject( 'invalid_order_status', 'EDD order status is not allowed.', $request, $order_id );
            }

            if ( '' !== $order_key && function_exists( 'edd_get_payment_key' ) ) {
                $real_key = (string) edd_get_payment_key( $order_id );
                if ( '' === $real_key || ! hash_equals( $real_key, $order_key ) ) {
                    return $this->reject( 'invalid_order_key', 'EDD order key mismatch.', $request, $order_id );
                }
            }

            return true;
        }

        /**
         * Is this one of the guarded /event routes?
         *
         * @param \WP_REST_Request $request
         * @return bool
         */
        private function is_guarded_route( $request ) {
            $route = (string) $request->get_route(); // e.g. /pys-facebook/v1/event
            foreach ( $this->namespaces as $ns ) {
                if ( 0 === strpos( $route, '/' . $ns . '/' ) && '/event' === substr( $route, -6 ) ) {
                    return true;
                }
            }
            return false;
        }

        /**
         * The full set of event slugs the system can legitimately fire through the
         * guarded endpoints. Built once and cached per request.
         *
         * Uses the same superset as PRO (a whitelist of known PYS slugs); extra
         * PRO-only slugs are harmless in free (they are still known PYS slugs, not
         * arbitrary injections). Covers built-in factories plus the shared
         * 'custom_event' slug used by ALL user custom events.
         *
         * Extend via the `pys_rest_valid_event_slugs` filter (e.g. for add-ons that
         * introduce new slugs).
         *
         * @return string[]
         */
        private function valid_event_slugs() {
            static $slugs = null;
            if ( null !== $slugs ) {
                return $slugs;
            }

            $builtin = array(
                // Standard
                'init_event',                 // PageView
                // User custom events (all share this slug)
                'custom_event',
                // Automatic events
                'automatic_event_internal_link', 'automatic_event_outbound_link',
                'automatic_event_video', 'automatic_event_tel_link',
                'automatic_event_email_link', 'automatic_event_form',
                'automatic_event_signup', 'automatic_event_login',
                'automatic_event_download', 'automatic_event_comment',
                'automatic_event_adsense', 'automatic_event_scroll',
                'automatic_event_time_on_page', 'automatic_event_404',
                'automatic_event_search', 'automatic_event_rage_click',
                'automatic_event_video_speed',
                // WooCommerce
                'woo_affiliate', 'woo_add_to_cart_on_button_click',
                'woo_add_to_cart_on_cart_page', 'woo_add_to_cart_on_checkout_page',
                'woo_select_content_category', 'woo_select_content_single',
                'woo_select_content_search', 'woo_select_content_shop',
                'woo_select_content_tag', 'woo_paypal', 'woo_remove_from_cart',
                'woo_initiate_checkout_progress_f', 'woo_initiate_checkout_progress_l',
                'woo_initiate_checkout_progress_e', 'woo_initiate_checkout_progress_o',
                'woo_initiate_set_checkout_option', 'woo_purchase', 'woo_complete_payment',
                'woo_ReturningCustomer', 'woo_FirstTimeBuyer',
                'woo_frequent_shopper', 'woo_vip_client', 'woo_big_whale',
                'woo_view_content', 'woo_view_category', 'woo_view_item_list',
                'woo_view_cart', 'woo_view_item_list_single',
                'woo_view_item_list_search', 'woo_view_item_list_shop',
                'woo_view_item_list_tag', 'woo_initiate_checkout', 'woo_start_trial',
                'woo_subscription_created', 'woo_subscription_renewal',
                'woo_subscription_expired', 'woo_subscription_canceled', 'woo_refund',
                // Easy Digital Downloads
                'edd_add_to_cart_on_button_click', 'edd_add_to_cart_on_checkout_page',
                'edd_purchase', 'edd_complete_payment', 'edd_initiate_checkout',
                'edd_remove_from_cart', 'edd_view_category', 'edd_view_content',
                'edd_vip_client', 'edd_big_whale', 'edd_frequent_shopper',
                'edd_start_trial', 'edd_subscription_created',
                'edd_subscription_renewal', 'edd_subscription_expired',
                'edd_subscription_canceled', 'edd_license_created',
                'edd_license_upgrade', 'edd_license_renewal', 'edd_license_expired',
                'edd_refund',
                // Facebook Dynamic Products feed
                'fdp_purchase', 'fdp_add_to_cart', 'fdp_view_content',
                'fdp_view_category',
                // CartFlows
                'wcf_view_content', 'wcf_add_to_cart_on_next_step_click',
                'wcf_add_to_cart_on_bump_click', 'wcf_remove_from_cart_on_bump_click',
                'wcf_page', 'wcf_step_page', 'wcf_bump', 'wcf_lead',
            );

            $slugs = (array) apply_filters( 'pys_rest_valid_event_slugs', $builtin );
            return $slugs;
        }

        /**
         * Case-insensitive membership test.
         *
         * @param mixed    $value
         * @param string[] $list
         * @return bool
         */
        private function matches_ci( $value, array $list ) {
            $value = strtolower( trim( (string) $value ) );
            if ( '' === $value ) {
                return false;
            }
            foreach ( $list as $item ) {
                if ( $value === strtolower( (string) $item ) ) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Normalize an order id param to a positive int (mirrors sanitize_order_id).
         *
         * @param mixed $value
         * @return int
         */
        private function to_id( $value ) {
            if ( is_string( $value ) ) {
                $value = trim( $value );
                if ( '' === $value || 'null' === $value || 'undefined' === $value ) {
                    return 0;
                }
            }
            return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
        }

        /**
         * Normalize an order key param; empty when not meaningfully provided.
         *
         * @param mixed $value
         * @return string
         */
        private function to_key( $value ) {
            if ( ! is_string( $value ) ) {
                return '';
            }
            $value = sanitize_text_field( trim( $value ) );
            if ( 'null' === $value || 'undefined' === $value || '0' === $value ) {
                return '';
            }
            return $value;
        }

        /**
         * Build a 403 WP_Error and log the rejection.
         *
         * @param string           $code
         * @param string           $message
         * @param \WP_REST_Request  $request
         * @param int              $order_id
         * @return \WP_Error
         */
        private function reject( $code, $message, $request, $order_id ) {
            $route = method_exists( $request, 'get_route' ) ? $request->get_route() : '';

            if ( function_exists( 'PixelYourSite\\PYS' ) && PYS() && PYS()->getLog() ) {
                PYS()->getLog()->debug(
                    'RestAPIGuard rejected event',
                    array(
                        'code'     => $code,
                        'route'    => $route,
                        'order_id' => $order_id,
                        'ip'       => method_exists( PYS(), 'get_user_ip' ) ? PYS()->get_user_ip() : '',
                    )
                );
            }

            return new \WP_Error( 'pys_rest_' . $code, $message, array( 'status' => 403 ) );
        }
    }

} // class_exists guard
