<?php
/**
 * Prices set by discount plugins.
 *
 * WooCommerce discount plugins rarely change WC_Product::get_price(). On the
 * product page and in the catalog they rewrite the price HTML; in the cart
 * they call set_price() on the cart item's own product object. The price
 * helpers in functions-woo.php read wc_get_product() and would report the
 * undiscounted catalog price, so they ask here first and keep their own
 * price when the answer is false.
 *
 * Each supported plugin is a provider: an is_active check plus one callback
 * per question. Adding the next discount plugin is one more entry in
 * pys_woo_discount_providers(); third-party code can add one through the
 * 'pys_woo_discount_providers' filter.
 *
 * Providers:
 *   woo_discount_rules  Discount Rules for WooCommerce by Flycart (free and Pro,
 *                       the Pro plugin is an add-on to the free engine).
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * @return array slug => [ 'is_active' => callable, 'product_price' => callable, 'cart_item_price' => callable ]
 */
function pys_woo_discount_providers() {

    $providers = array(
        'woo_discount_rules' => array(
            'is_active'       => __NAMESPACE__ . '\pys_wdr_is_active',
            'product_price'   => __NAMESPACE__ . '\pys_wdr_product_price',
            'cart_item_price' => __NAMESPACE__ . '\pys_wdr_cart_item_price',
        ),
    );

    $providers = apply_filters( 'pys_woo_discount_providers', $providers );

    return is_array( $providers ) ? $providers : array();
}

/**
 * Unit price of a product after the active discount plugin's rules, or false
 * when no plugin is active or none of its rules apply.
 *
 * Quantity matters: bulk rules give a different unit price per tier.
 *
 * @param \WC_Product $product
 * @param int|float   $qty
 * @param float       $price   the price PYS would report otherwise
 *
 * @return float|false
 */
function pys_woo_get_discounted_product_price( $product, $qty, $price ) {
    return pys_woo_ask_discount_providers( 'product_price', array( $product, $qty, $price ) );
}

/**
 * Unit price of a cart line after the active discount plugin's rules, or false.
 *
 * Preferred over the product question for cart contents: the plugin has
 * already evaluated the line with its real quantity and cart conditions.
 *
 * @param array $cart_item an entry of WC()->cart->cart_contents, with its 'key'
 * @param float $price     the price PYS would report otherwise
 *
 * @return float|false
 */
function pys_woo_get_discounted_cart_item_price( $cart_item, $price ) {
    return pys_woo_ask_discount_providers( 'cart_item_price', array( $cart_item, $price ) );
}

/**
 * First active provider that answers with a price wins.
 *
 * @return float|false
 */
function pys_woo_ask_discount_providers( $question, $args ) {

    foreach ( pys_woo_discount_providers() as $provider ) {

        if ( ! is_array( $provider ) || empty( $provider['is_active'] ) || empty( $provider[ $question ] ) ) {
            continue;
        }
        if ( ! is_callable( $provider['is_active'] ) || ! is_callable( $provider[ $question ] ) ) {
            continue;
        }
        if ( ! call_user_func( $provider['is_active'] ) ) {
            continue;
        }

        $answer = pys_woo_discount_price_or_false( call_user_func_array( $provider[ $question ], $args ) );
        if ( $answer !== false ) {
            return $answer;
        }
    }

    return false;
}

/**
 * A price is a non-negative number. Zero is a price (100% off); anything else
 * a plugin returns (false, null, an array of details, text) is "no answer".
 *
 * @return float|false
 */
function pys_woo_discount_price_or_false( $value ) {

    if ( $value === false || $value === null || is_array( $value ) || is_object( $value ) || is_bool( $value ) ) {
        return false;
    }
    if ( ! is_numeric( $value ) || (float) $value < 0 ) {
        return false;
    }

    return (float) $value;
}

/**
 * The unit price a product is sold at right now: the discount plugin's price when one
 * applies, the catalog price otherwise. For callers that need a number, not "no answer".
 *
 * @param \WC_Product $product
 * @param int|float   $qty
 *
 * @return float
 */
function pys_woo_product_price_after_discounts( $product, $qty = 1 ) {
    $price      = (float) $product->get_price();
    $discounted = pys_woo_get_discounted_product_price( $product, $qty, $price );
    return $discounted !== false ? $discounted : $price;
}

/* ------------------------------------------------------------------------- *
 * Discount Rules for WooCommerce by Flycart
 * ------------------------------------------------------------------------- */

/**
 * The class is loaded early, the filters are added on plugins_loaded: both
 * must be there before the plugin can answer.
 */
function pys_wdr_is_active() {
    return class_exists( '\Wdr\App\Controllers\ManageDiscount' )
        && has_filter( 'advanced_woo_discount_rules_get_product_discount_price_from_custom_price' );
}

/**
 * Same call Flycart makes for its own Facebook for WooCommerce compatibility:
 * ($price, $product, $quantity, $custom_price, $get_only, $manual_request, $is_cart).
 *
 * custom_price is 0 on purpose: Flycart then starts from the product's regular
 * or sale price according to its own "calculate discount from" setting.
 * Returns false when no rule applies to the product.
 */
function pys_wdr_product_price( $product, $qty, $price ) {
    return apply_filters(
        'advanced_woo_discount_rules_get_product_discount_price_from_custom_price',
        $price, $product, $qty, 0, 'discounted_price', true, false
    );
}

/**
 * Flycart looks the line up by its cart item key. Without the key there is
 * nothing to look up, so do not ask.
 */
function pys_wdr_cart_item_price( $cart_item, $price ) {
    if ( ! is_array( $cart_item ) || empty( $cart_item['key'] ) ) {
        return false;
    }
    return apply_filters( 'advanced_woo_discount_rules_get_cart_item_discount_price', false, $cart_item );
}
