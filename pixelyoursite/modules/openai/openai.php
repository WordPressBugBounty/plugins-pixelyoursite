<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once PYS_FREE_PATH . '/modules/openai/function-helpers.php';

use PixelYourSite\OpenAI\Helpers;

/**
 * OpenAI Ads pixel module (Free edition).
 */
class OpenAI extends Settings implements Pixel {

    private static $_instance;

    private $configured;

    private $moduleName = 'OpenAI';

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct() {

        parent::__construct( 'openai' );

        $this->locateOptions(
            PYS_FREE_PATH . '/modules/openai/options_fields.json',
            PYS_FREE_PATH . '/modules/openai/options_defaults.json'
        );

        add_action( 'pys_register_pixels', function ( $core ) {
            $core->registerPixel( $this );
        } );
    }

    public function getModuleName() {
        return $this->moduleName;
    }

    public function enabled() {
        return $this->getOption( 'enabled' );
    }

    public function configured() {

        if ( $this->configured === null ) {

            $pixel_id = $this->getPixelIDs();

            $this->configured = $this->enabled()
                                && count( $pixel_id ) > 0
                                && ! empty( $pixel_id[0] );
        }

        return $this->configured;
    }

    /**
     * A single primary pixel
     *
     * @return array
     */
    public function getPixelIDs() {

        $ids = (array) $this->getOption( 'pixel_id' );

        if ( count( $ids ) == 0 || empty( $ids[0] ) ) {
            return apply_filters( 'pys_openai_ids', [] );
        }

        $id = array_shift( $ids );

        return apply_filters( 'pys_openai_ids', array( $id ) );
    }

    /**
     * Front-end options for this pixel.
     *
     * `debug` is the module's own switch rather than the plugin-wide debug
     * option: it is what the OpenAI Pixel Helper add-on reads, so it has to be
     * settable for this pixel alone.
     *
     * @return array
     */
    public function getPixelOptions() {

        $options = array(
            'pixelIds'         => $this->getPixelIDs(),
            'serverApiEnabled' => $this->isServerApiEnabled(),
            'debug'            => (bool) $this->getOption( 'debug_enabled' ),
        );

        if ( $this->getOption( 'advanced_matching_enabled' ) ) {

            $advanced_matching = $this->getAdvancedMatchingParams();

            if ( ! empty( $advanced_matching ) ) {
                $options['advanced_matching'] = $advanced_matching;
            }
        }

        return $options;
    }

    /**
     * Is the Conversions API switched on AND usable (a key is present)?
     *
     * @return bool
     */
    public function isServerApiEnabled() {

        if ( ! $this->enabled() || ! $this->getOption( 'use_server_api' ) ) {
            return false;
        }

        $tokens = array_filter( (array) $this->getOption( 'server_access_api_token' ) );

        return ! empty( $tokens );
    }

    /**
     * Conversions API keys, keyed by the pixel they belong to.
     *
     * @return array
     */
    public function getApiTokens() {

        $tokens = array();

        $pixel_ids = $this->getPixelIDs();

        if ( count( $pixel_ids ) > 0 && $this->isServerApiEnabled() ) {
            $server_tokens = (array) $this->getOption( 'server_access_api_token' );
            $tokens[ $pixel_ids[0] ] = reset( $server_tokens );
        }

        return $tokens;
    }

    /**
     * Send events for validation only, without recording them.
     *
     * @return bool
     */
    public function isServerValidateOnly() {
        return (bool) $this->getOption( 'server_validate_only' );
    }

    /**
     * Identifying fields for the visitor, in the shape OpenAI documents.
     *
     * The schema is closed and short: email_sha256, external_id_sha256, country,
     * city and zip_code. There are no phone or name fields anywhere in it, so
     * that data is deliberately never collected here.
     *
     * Email and external id are hashed; country, city and zip travel in the
     * clear, which is what OpenAI asks for.
     *
     * @param int|null $wooOrder Explicit Woo order id, from trusted internal code.
     * @param int|null $eddOrder Explicit EDD payment id, from trusted internal code.
     * @return array
     */
    public function getAdvancedMatchingParams( $wooOrder = null, $eddOrder = null ) {

        $params     = array();
        $user_email = '';
        $country    = '';
        $city       = '';
        $zip        = '';

        $user = wp_get_current_user();

        if ( $user && $user->ID ) {
            $user_email = $user->get( 'user_email' );
        }

        if ( isEddActive() ) {

            $order_id = $eddOrder ? (int) $eddOrder : (int) edd_get_purchase_id_by_key( getEddPaymentKey() );

            // The same access check the events use: an order this request may
            // not see must not leak its buyer's address either.
            if ( $order_id > 0 && ! pysEddRequestCanAccessOrder( $order_id ) ) {
                $order_id = 0;
            }

            if ( $order_id > 0 ) {

                $userEdd = edd_get_payment_meta_user_info( $order_id );

                if ( ! empty( $userEdd['email'] ) ) {
                    $user_email = $userEdd['email'];
                }

                $address = isset( $userEdd['address'] ) ? $userEdd['address'] : array();

                if ( ! empty( $address['country'] ) ) $country = $address['country'];
                if ( ! empty( $address['city'] ) )    $city    = $address['city'];
                if ( ! empty( $address['zip'] ) )     $zip     = $address['zip'];
            }
        }

        if ( isWooCommerceActive() ) {

            if ( $wooOrder ) {
                $order_id = (int) $wooOrder;
            } else {
                $order_id = wooGetOrderIdFromRequest();

                // 'pii' is the stricter context: reading an address needs more
                // than the right to know the order exists.
                if ( $order_id > 0 && ! pysWooRequestCanAccessOrder( $order_id, 'pii' ) ) {
                    $order_id = 0;
                }
            }

            if ( $order_id > 0 ) {

                $order = wc_get_order( $order_id );

                if ( $order ) {
                    $user_email = $order->get_billing_email();
                    $country    = $order->get_billing_country();
                    $city       = $order->get_billing_city();
                    $zip        = $order->get_billing_postcode();
                }
            }
        }

        $user_persistence_data = get_persistence_user_data( $user_email, '', '', '' );

        if ( ! empty( $user_persistence_data['em'] ) ) {
            $params['email_sha256'] = Helpers\pys_openai_hash_email( $user_persistence_data['em'] );
        }

        if ( EventsManager::isTrackExternalId() ) {

            $external_id = $this->getExternalId( $wooOrder, $eddOrder );

            if ( ! empty( $external_id ) ) {
                $params['external_id_sha256'] = hash( 'sha256', $external_id );
            }
        }

        $country = Helpers\pys_openai_normalize_country( $country );
        $city    = Helpers\pys_openai_normalize_city( $city );
        $zip     = Helpers\pys_openai_normalize_zip( $zip );

        if ( $country !== '' ) $params['country']  = $country;
        if ( $city !== '' )    $params['city']     = $city;
        if ( $zip !== '' )     $params['zip_code'] = $zip;

        return apply_filters( 'pys_openai_advanced_matching', $params );
    }

    /**
     * The stable pseudonymous visitor id behind external_id_sha256.
     *
     * Same sources the other Free pixels use, so one visitor keeps one identity
     * across destinations: the order's own `external_id` meta when the event
     * belongs to an order, otherwise the `pbid` cookie. Returned unhashed --
     * the caller hashes it.
     *
     * @param int|null $wooOrder
     * @param int|null $eddOrder
     * @return string
     */
    public function getExternalId( $wooOrder = null, $eddOrder = null ) {

        $external_id = '';

        if ( $eddOrder && isEddActive() ) {
            $external_id = edd_get_payment_meta( (int) $eddOrder, 'external_id', true );
        }

        if ( empty( $external_id ) && $wooOrder && isWooCommerceActive() ) {

            $order = wc_get_order( (int) $wooOrder );

            if ( $order ) {
                $external_id = $order->get_meta( 'external_id' );
            }
        }

        if ( empty( $external_id ) && ! empty( $_COOKIE['pbid'] ) ) {
            $external_id = sanitize_text_field( wp_unslash( $_COOKIE['pbid'] ) );
        }

        return is_string( $external_id ) ? $external_id : '';
    }

    /**
     * Build the platform events for one PYS event.
     *
     * This is the only entry point the events manager uses -- addParamsToEvent()
     * is reached through here, not called directly -- so a pixel that returns an
     * empty list here fires nothing at all, however complete its mappers are.
     *
     * The source event is cloned because the manager hands the same instance to
     * every registered pixel in turn.
     *
     * @param SingleEvent $event
     * @return SingleEvent[]
     */
    public function generateEvents( $event ) {

        $pixelEvents = array();

        if ( ! $this->configured() ) {
            return array();
        }

        $pixel_ids = $this->getPixelIDs();

        if ( count( $pixel_ids ) == 0 ) {
            return array();
        }

        $pixelEvent = clone $event;

        if ( $this->addParamsToEvent( $pixelEvent ) ) {
            $pixelEvent->addPayload( array(
                'pixelIds' => $pixel_ids,
                'eventID'  => EventIdGenerator::guidv4(),
            ) );
            $pixelEvents[] = $pixelEvent;
        }

        return $pixelEvents;
    }

    /**
     * Attach this pixel's parameters to an event.
     *
     * `false` means "this pixel adds nothing", which is what the events manager
     * expects from a pixel that does not handle the event. WooCommerce and EDD
     * events join this switch in F3 / F4.
     *
     * @param SingleEvent $event
     * @return bool
     */
    public function addParamsToEvent( &$event ) {

        if ( ! $this->configured() ) {
            return false;
        }

        $isActive = false;

        switch ( $event->getId() ) {

            case 'init_event':
                $isActive = $this->addPageViewParams( $event );
                break;

            case 'woo_view_content':
                $isActive = $this->addWooViewContentParams( $event );
                break;

            // All three add-to-cart catch methods are one logical action for
            // OpenAI, so they share the woo_add_to_cart_enabled toggle.
            case 'woo_add_to_cart_on_button_click':
            case 'woo_add_to_cart_on_cart_page':
            case 'woo_add_to_cart_on_checkout_page':
                $isActive = $this->addWooAddToCartParams( $event );
                break;

            case 'woo_initiate_checkout':
                $isActive = $this->addWooInitiateCheckoutParams( $event );
                break;

            case 'woo_purchase':
                $isActive = $this->addWooPurchaseParams( $event );
                break;

            case 'edd_view_content':
                $isActive = $this->addEddViewContentParams( $event );
                break;

            case 'edd_add_to_cart_on_button_click':
                $isActive = $this->addEddAddToCartOnButtonParams( $event );
                break;

            // Cart-shaped EDD events differ only by name, toggle and which core
            // value settings drive the amount.
            case 'edd_add_to_cart_on_checkout_page':
                $isActive = $this->addEddCartParams( $event, 'items_added', 'edd_add_to_cart_enabled' );
                break;

            case 'edd_initiate_checkout':
                $isActive = $this->addEddCartParams( $event, 'checkout_started', 'edd_initiate_checkout_enabled' );
                break;

            case 'edd_purchase':
                $isActive = $this->addEddCartParams( $event, 'order_created', 'edd_purchase_enabled' );
                break;

            // edd_view_category and edd_remove_from_cart have no counterpart in
            // OpenAI's closed taxonomy, so they are deliberately not handled --
            // the same two events Pro leaves out.
        }

        return $isActive;
    }

    /**
     * page_viewed — the OpenAI name for the page view.
     *
     * The get_post_type() gate is deliberate: without it the event also fires on
     * requests that are not pages at all (a missing .js.map, for instance), which
     * in Pro filled the queue with hundreds of junk page views.
     *
     * @param SingleEvent $event
     * @return bool
     */
    private function addPageViewParams( &$event ) {

        if ( ! get_post_type() ) {
            return false;
        }

        $event->addPayload( [ 'name' => 'page_viewed' ] );
        $event->addParams( [ 'data' => [ 'type' => 'contents' ] ] );

        return true;
    }

    /**
     * Attach an OpenAI event to a PYS event.
     *
     * `data` is a closed object: only the fields OpenAI documents may appear, and
     * one unknown key fails the whole Conversions API request. Money is always in
     * integer minor units, and `currency` travels with `amount` or not at all.
     *
     * @param SingleEvent $event
     * @param string      $name     OpenAI event name.
     * @param string      $type     data.type: contents | customer_action | custom.
     * @param mixed       $amount   Decimal total, converted here.
     * @param string      $currency
     * @param array       $contents
     * @return bool
     */
    private function attachEvent( &$event, $name, $type, $amount = null, $currency = '', $contents = array() ) {

        $data = array( 'type' => $type );

        $minor_units = Helpers\pys_openai_to_minor_units( $amount, $currency );

        if ( $minor_units !== null && ! empty( $currency ) ) {
            $data['amount']   = $minor_units;
            $data['currency'] = $currency;
        }

        $contents = array_values( array_filter( (array) $contents ) );

        if ( ! empty( $contents ) && $type !== 'customer_action' ) {
            $data['contents'] = $contents;
        }

        $event->addPayload( array( 'name' => $name ) );
        $event->addParams( array( 'data' => $data ) );

        return true;
    }

    /**
     * woo_view_content -> contents_viewed
     *
     * Free resolves the product from the global post, as its other modules do;
     * Pro reads it from the event args. Same event, different plumbing.
     *
     * @param SingleEvent $event
     * @return bool
     */
    private function addWooViewContentParams( &$event ) {

        global $post;

        if ( ! $this->getOption( 'woo_view_content_enabled' ) || empty( $post ) ) {
            return false;
        }

        $product = wc_get_product( $post->ID );

        if ( ! $product ) {
            return false;
        }

        $currency   = get_woocommerce_currency();
        $product_id = Helpers\getOpenAiWooVariableToSimpleProductId( $product );
        $content_id = Helpers\getOpenAiWooProductContentId( $product_id );
        $unit_price = getWooProductPriceToDisplay( $product_id );

        $amount = null;

        if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {
            $amount = getWooEventValue(
                PYS()->getOption( 'woo_view_content_value_option' ),
                PYS()->getOption( 'woo_view_content_value_global', 0 ),
                100,
                $product_id,
                1
            );
        }

        $contents = array(
            Helpers\pys_openai_content_item( $content_id, $product->get_name(), 'product', 1, $unit_price, $currency ),
        );

        return $this->attachEvent( $event, 'contents_viewed', 'contents', $amount, $currency, $contents );
    }

    /**
     * woo_add_to_cart_* -> items_added
     *
     * The button-click variant carries the product in the event args; the cart
     * and checkout variants describe the whole cart.
     *
     * @param SingleEvent $event
     * @return bool
     */
    private function addWooAddToCartParams( &$event ) {

        if ( ! $this->getOption( 'woo_add_to_cart_enabled' ) ) {
            return false;
        }

        $currency = get_woocommerce_currency();

        if ( $event->getId() === 'woo_add_to_cart_on_button_click' ) {

            $product_id = isset( $event->args['productId'] ) ? $event->args['productId'] : null;

            if ( ! $product_id ) {
                return false;
            }

            $product = wc_get_product( $product_id );

            if ( ! $product ) {
                return false;
            }

            $quantity   = isset( $event->args['quantity'] ) ? (int) $event->args['quantity'] : 1;
            $product_id = Helpers\getOpenAiWooVariableToSimpleProductId( $product );
            $content_id = Helpers\getOpenAiWooProductContentId( $product_id );
            $unit_price = getWooProductPriceToDisplay( $product_id );

            $contents = array(
                Helpers\pys_openai_content_item( $content_id, $product->get_name(), 'product', $quantity, $unit_price, $currency ),
            );

            $amount = null;

            if ( PYS()->getOption( 'woo_add_to_cart_value_enabled' ) ) {
                $amount = getWooEventValue(
                    PYS()->getOption( 'woo_add_to_cart_value_option' ),
                    PYS()->getOption( 'woo_add_to_cart_value_global', 0 ),
                    100,
                    $product_id,
                    $quantity
                );
            }

            return $this->attachEvent( $event, 'items_added', 'contents', $amount, $currency, $contents );
        }

        $cart = $this->getCartContents( $currency );

        if ( $cart === null ) {
            return false;
        }

        return $this->attachEvent( $event, 'items_added', 'contents', $cart['amount'], $currency, $cart['contents'] );
    }

    /**
     * woo_initiate_checkout -> checkout_started
     *
     * @param SingleEvent $event
     * @return bool
     */
    private function addWooInitiateCheckoutParams( &$event ) {

        if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) ) {
            return false;
        }

        $currency = get_woocommerce_currency();
        $cart     = $this->getCartContents( $currency );

        if ( $cart === null ) {
            return false;
        }

        return $this->attachEvent( $event, 'checkout_started', 'contents', $cart['amount'], $currency, $cart['contents'] );
    }

    /**
     * woo_purchase -> order_created
     *
     * @param SingleEvent $event
     * @return bool
     */
    private function addWooPurchaseParams( &$event ) {

        if ( ! $this->getOption( 'woo_purchase_enabled' ) ) {
            return false;
        }

        $order_id = wooGetOrderIdFromRequest();

        if ( $order_id < 1 ) {
            return false;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return false;
        }

        // The order reference travels with the event: the browser copy carries it
        // back, and RestAPIGuard rejects a purchase that cannot be bound to a
        // real order ("missing_order").
        $event->addPayload( array(
            'woo_order' => $order_id,
            'order_key' => $order->get_order_key(),
        ) );

        // The order's own currency, not the shop's: they differ on multi-currency
        // shops, and OpenAI reads amount and currency together.
        $currency = $order->get_currency();
        $contents = array();

        foreach ( $order->get_items( 'line_item' ) as $line_item ) {

            $product_id = Helpers\getOpenAiWooProductDataId( array(
                'type'       => empty( $line_item['variation_id'] ) ? 'simple' : 'variation',
                'parent_id'  => $line_item['product_id'],
                'product_id' => empty( $line_item['variation_id'] ) ? $line_item['product_id'] : $line_item['variation_id'],
            ) );

            $content_id = Helpers\getOpenAiWooProductContentId( $product_id );
            $product    = wc_get_product( $product_id );
            $quantity   = (int) $line_item['qty'];

            $contents[] = Helpers\pys_openai_content_item(
                $content_id,
                $product ? $product->get_name() : '',
                'product',
                $quantity,
                getWooProductPriceToDisplay( $product_id ),
                $currency
            );
        }

        $amount = getWooEventValueOrder(
            PYS()->getOption( 'woo_purchase_value_option' ),
            $order,
            PYS()->getOption( 'woo_purchase_value_global', 0 )
        );

        return $this->attachEvent( $event, 'order_created', 'contents', $amount, $currency, $contents );
    }

    /**
     * edd_view_content -> contents_viewed
     *
     * @param SingleEvent $event
     * @return bool
     */
    private function addEddViewContentParams( &$event ) {

        global $post;

        if ( ! $this->getOption( 'edd_view_content_enabled' ) || empty( $post ) ) {
            return false;
        }

        $download_id = $post->ID;
        $currency    = edd_get_currency();
        $unit_price  = getEddDownloadPriceToDisplay( $download_id );

        // No price index: a download page shows the default variation, and the
        // content id must match what the catalogue calls the parent item.
        $content_id = Helpers\getOpenAiEddDownloadContentId( $download_id );

        $value  = $this->getEddValueSettings( 'edd_view_content' );
        $amount = $value['enabled'] ? getEddEventValue( $value['option'], $unit_price, $value['global'] ) : null;

        $contents = array(
            Helpers\pys_openai_content_item( $content_id, get_the_title( $download_id ), 'product', 1, $unit_price, $currency ),
        );

        return $this->attachEvent( $event, 'contents_viewed', 'contents', $amount, $currency, $contents );
    }

    /**
     * edd_add_to_cart_on_button_click -> items_added
     *
     * This one is a dynamic event: it is registered once with no args, and the
     * real payload is substituted from pysEddProductData when the button is
     * clicked. The arg-less pass must still return true, or the slot never
     * appears in options.dynamicEvents and the click fires nothing.
     *
     * The id arrives as "<download_id>_<price_index>" for downloads with price
     * variations.
     *
     * @param SingleEvent $event
     * @return bool
     */
    private function addEddAddToCartOnButtonParams( &$event ) {

        if ( ! $this->getOption( 'edd_add_to_cart_enabled' )
            || ! PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
            return false;
        }

        if ( empty( $event->args ) ) {
            return $this->attachEvent( $event, 'items_added', 'contents' );
        }

        $download_id = $event->args;
        $price_index = null;

        if ( strpos( (string) $download_id, '_' ) !== false ) {
            list( $download_id, $price_index ) = explode( '_', (string) $download_id, 2 );
        }

        $download_id = (int) $download_id;

        if ( ! $download_id ) {
            return $this->attachEvent( $event, 'items_added', 'contents' );
        }

        $currency   = edd_get_currency();
        $unit_price = getEddDownloadPriceToDisplay( $download_id, $price_index );
        $content_id = Helpers\getOpenAiEddDownloadContentId( $download_id, $price_index );

        $value  = $this->getEddValueSettings( 'edd_add_to_cart_on_checkout_page' );
        $amount = $value['enabled'] ? getEddEventValue( $value['option'], $unit_price, $value['global'] ) : null;

        $contents = array(
            Helpers\pys_openai_content_item( $content_id, get_the_title( $download_id ), 'product', 1, $unit_price, $currency ),
        );

        return $this->attachEvent( $event, 'items_added', 'contents', $amount, $currency, $contents );
    }

    /**
     * Every EDD event built from a cart: items_added on the checkout page,
     * checkout_started and order_created.
     *
     * The purchase reads the stored order rather than the live cart, and its line
     * options sit one level deeper -- that is the only structural difference
     * between the two sources.
     *
     * @param SingleEvent $event
     * @param string      $name   OpenAI event name.
     * @param string      $option Module option gating the event.
     * @return bool
     */
    private function addEddCartParams( &$event, $name, $option ) {

        if ( ! $this->getOption( $option ) ) {
            return false;
        }

        $is_purchase = $event->getId() === 'edd_purchase';

        if ( $is_purchase ) {

            $order_id = (int) edd_get_purchase_id_by_key( getEddPaymentKey() );

            // The same access check the core makes before building the event: an
            // order the request may not see must not reach OpenAI either.
            if ( $order_id < 1 || ! pysEddRequestCanAccessOrder( $order_id ) ) {
                return false;
            }

            $event->addPayload( array( 'edd_order' => $order_id ) );

            $cart = edd_get_payment_meta_cart_details( $order_id, true );
        } else {
            $cart = edd_get_cart_contents();
        }

        if ( empty( $cart ) ) {
            return false;
        }

        $currency = edd_get_currency();
        $contents = array();
        $total    = 0;

        foreach ( $cart as $cart_item_key => $cart_item ) {

            $download_id  = (int) $cart_item['id'];
            $item_options = $is_purchase
                ? ( $cart_item['item_number']['options'] ?? array() )
                : ( $cart_item['options'] ?? array() );

            // 0 is a real price variation (the first one); null means the download
            // has no price options at all, and only then is the index omitted.
            $price_index = isset( $item_options['price_id'] ) && $item_options['price_id'] !== ''
                ? $item_options['price_id']
                : null;

            $quantity   = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
            $content_id = Helpers\getOpenAiEddDownloadContentId( $download_id, $price_index );
            $unit_price = getEddDownloadPriceToDisplay( $download_id, $price_index );

            $contents[] = Helpers\pys_openai_content_item(
                $content_id,
                get_the_title( $download_id ),
                'product',
                $quantity,
                $unit_price,
                $currency
            );

            // The order line already carries what was actually paid; the cart has
            // to be asked for the final price, discounts and fees included.
            $total += $is_purchase
                ? ( $cart_item['price'] ?? 0 )
                : edd_get_cart_item_final_price( $cart_item_key );
        }

        $value  = $this->getEddValueSettings( $event->getId() );
        $amount = $value['enabled'] ? getEddEventValue( $value['option'], $total, $value['global'] ) : null;

        return $this->attachEvent( $event, $name, 'contents', $amount, $currency, $contents );
    }

    /**
     * Core value settings driving the amount of one EDD event.
     *
     * edd_purchase_value_enabled does not exist in Free -- the purchase always
     * carries its value -- so it defaults to true, which is what Pro does too. An
     * order_created with no amount would be worthless to OpenAI.
     *
     * Free has no *_value_percent option, so getEddEventValue() keeps its own 100%
     * default and the "percent" mode behaves as "price".
     *
     * @param string $event_id
     * @return array
     */
    private function getEddValueSettings( $event_id ) {

        $map = array(
            'edd_view_content'                 => 'edd_view_content',
            'edd_add_to_cart_on_checkout_page' => 'edd_add_to_cart',
            'edd_initiate_checkout'            => 'edd_initiate_checkout',
            'edd_purchase'                     => 'edd_purchase',
        );

        $prefix = isset( $map[ $event_id ] ) ? $map[ $event_id ] : 'edd_purchase';

        return array(
            'enabled' => PYS()->getOption( $prefix . '_value_enabled', $prefix === 'edd_purchase' ),
            'option'  => PYS()->getOption( $prefix . '_value_option' ),
            'global'  => PYS()->getOption( $prefix . '_value_global', 0 ),
        );
    }

    /**
     * The current cart as OpenAI contents plus its total.
     *
     * @param string $currency
     * @return array|null null when there is no usable cart.
     */
    private function getCartContents( $currency ) {

        if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
            return null;
        }

        $contents = array();

        foreach ( WC()->cart->get_cart() as $cart_item ) {

            $product_id = Helpers\getOpenAiWooCartProductId( array(
                'product_id' => empty( $cart_item['variation_id'] ) ? $cart_item['product_id'] : $cart_item['variation_id'],
                'parent_id'  => empty( $cart_item['variation_id'] ) ? 0 : $cart_item['product_id'],
            ) );

            $product = wc_get_product( $product_id );

            $contents[] = Helpers\pys_openai_content_item(
                Helpers\getOpenAiWooProductContentId( $product_id ),
                $product ? $product->get_name() : '',
                'product',
                (int) $cart_item['quantity'],
                getWooProductPriceToDisplay( $product_id ),
                $currency
            );
        }

        return array(
            'contents' => $contents,
            'amount'   => WC()->cart->get_cart_contents_total(),
        );
    }

    /**
     * @param string $eventType
     * @param mixed  $args
     * @return bool
     */
    public function getEventData( $eventType, $args = null ) {
        return false;
    }

    /**
     * Image-tag fallback for visitors without JavaScript.
     *
     * @return void
     */
    public function outputNoScriptEvents() {

        if ( ! $this->configured() || ! Consent()->checkConsent( 'openai' ) ) {
            return;
        }

        $eventsManager = PYS()->getEventsManager();

        if ( ! $eventsManager ) {
            return;
        }

        $staticEvents = $eventsManager->getStaticEvents( $this->getSlug() );

        if ( empty( $staticEvents ) ) {
            return;
        }

        $oppref = Helpers\pys_openai_get_click_id();

        foreach ( $staticEvents as $events ) {
            foreach ( $events as $event ) {

                // Only the page view: an image tag loads with the page and cannot
                // observe anything that happens afterwards, so a commerce event
                // has nothing to report here.
                if ( ( $event['name'] ?? '' ) !== 'page_viewed' ) {
                    continue;
                }

                $data = $event['params']['data'] ?? array();

                if ( empty( $data['type'] ) ) {
                    continue;
                }

                foreach ( (array) ( $event['pixelIds'] ?? array() ) as $pixelID ) {

                    $args = array(
                        'pid'   => $pixelID,
                        'event' => $event['name'],
                    );

                    foreach ( $data as $field => $value ) {
                        // contents[] has to travel as JSON inside one parameter;
                        // bracketed sub-parameters are rejected as unknown.
                        $args[ 'data[' . $field . ']' ] = is_array( $value ) ? wp_json_encode( $value ) : $value;
                    }

                    // The same id the browser and the Conversions API use, so the
                    // firings deduplicate against each other.
                    if ( ! empty( $event['eventID'] ) ) {
                        $args['event_id'] = $event['eventID'];
                    }

                    if ( $oppref !== '' ) {
                        $args['oppref'] = $oppref;
                    }

                    // The endpoint accepts pid, event, data[...], event_id and
                    // oppref and nothing else. It has no user object, and OpenAI's
                    // docs forbid personal data in a query string, so no Advanced
                    // Matching is attached here.
                    $src = 'https://bzr.openai.com/v1/sdk/events?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );

                    echo '<noscript><img height="1" width="1" style="display:none" alt="" src="' . esc_url( $src ) . '"></noscript>' . "\r\n";
                }
            }
        }
    }

}

/**
 * @return OpenAI
 */
function OpenAI() {
    return OpenAI::instance();
}

OpenAI();
