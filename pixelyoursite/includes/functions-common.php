<?php

namespace PixelYourSite;

use URL;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Check if EDD Recurring plugin is active.
 * Uses static caching to prevent repeated checks.
 *
 * @return bool
 */
function isEddRecurringActive() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $cache = is_plugin_active( 'edd-recurring/edd-recurring.php' );
    return $cache;
}

/**
 * Check if EDD Software Licensing plugin is active.
 * Uses static caching to prevent repeated checks.
 *
 * @return bool
 */
function isEddSoftwareLicensingActive() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $cache = is_plugin_active( 'edd-software-licensing/edd-software-licenses.php' );
    return $cache;
}

/**
 * Check if WooCommerce Subscriptions plugin is active.
 * Uses static caching to prevent repeated checks.
 *
 * @return bool
 */
function isWooCommerceSubscriptionsActive() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $cache = is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' );
    return $cache;
}
function isWcfActive() {
    return function_exists('wcf');
}

function isPinterestActive( $checkCompatibility = true ) {
	
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	
	$active = is_plugin_active( 'pixelyoursite-pinterest/pixelyoursite-pinterest.php' );
	
	if ( $checkCompatibility ) {
		return $active && ! isPinterestVersionIncompatible();
	} else {
		return $active;
	}
	
}

function getUserRoles() {
    $user = wp_get_current_user();

    if ( $user->ID !== 0 ) {
        $user_roles = implode( ',', $user->roles );
    } else {
        $user_roles = 'guest';
    }
    return $user_roles;
}

function isPinterestVersionIncompatible() {
    
    if ( ! function_exists( 'get_plugin_data' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    
    $data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-pinterest/pixelyoursite-pinterest.php', false, false );
    
    return ! version_compare( $data['Version'], PYS_FREE_PINTEREST_MIN_VERSION, '>=' );
    
}

function isSuperPackActive( $checkCompatibility = true ) {
    
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    
    $active = is_plugin_active( 'pixelyoursite-super-pack/pixelyoursite-super-pack.php' );
    
    if ( $checkCompatibility ) {
        return $active && function_exists( 'PixelYourSite\SuperPack' ) && ! isSuperPackVersionIncompatible();
    } else {
        return $active;
    }
    
}

function isBingActive( $checkCompatibility = true ) {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $active = is_plugin_active( 'pixelyoursite-bing/pixelyoursite-bing.php' );

    if ( $checkCompatibility ) {
        return $active && ! isBingVersionIncompatible()
            && function_exists( 'PixelYourSite\Bing' )
            && Bing() instanceof Plugin; // false for dummy
    } else {
        return $active;
    }

}

function isBingVersionIncompatible() {

    if ( ! function_exists( 'get_plugin_data' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-bing/pixelyoursite-bing.php', false, false );

    return ! version_compare( $data['Version'], PYS_FREE_BING_MIN_VERSION, '>=' );

}

/**
 * Check if Reddit plugin installed and activated.
 * @param bool $checkCompatibility
 * @return bool
 */
function isRedditActive( bool $checkCompatibility = true ): bool {

	if ( !function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	$active = is_plugin_active( 'pixelyoursite-reddit/pixelyoursite-reddit.php' );

	if ( $checkCompatibility ) {
		return $active && !isRedditVersionIncompatible()
		       && function_exists( 'PixelYourSite\Reddit' )
		       && Reddit() instanceof Plugin; // false for dummy
	} else {
		return $active;
	}
}

/**
 * Check if Reddit plugin version is compatible.
 * @return bool
 */
function isRedditVersionIncompatible(): bool {

	if ( !function_exists( 'get_plugin_data' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	$data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-reddit/pixelyoursite-reddit.php', false, false );

	return !version_compare( $data[ 'Version' ], PYS_FREE_REDDIT_MIN_VERSION, '>=' );
}

/**
 * Check if Pixel Cost of goods plugin installed and activated.
 * Uses static caching to prevent repeated checks.
 *
 * @return bool
 */
function isPixelCogActive() {
    static $cache = null;
    if ( $cache !== null ) {
        return $cache;
    }
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $cache = is_plugin_active( 'pixel-cost-of-goods/pixel-cost-of-goods.php' );
    return $cache;
}

/**
 * Check if WooCommerce native Cost of Goods (COGS) is available.
 * Requires WooCommerce 10.3 or later.
 * Uses static caching to prevent repeated reflection calls.
 *
 * @return bool
 */
function isWcCogAvailable() {
    static $cache = null;
    if ( $cache !== null ) {
        return $cache;
    }
    $cache = method_exists( 'WC_Order', 'get_cogs_total_value' );
    return $cache;
}

// ─────────────────────────────────────────────────────────────────────────────
// Global COG (Cost of Goods) helpers
// All COG logic for events and ConversionProfit goes through these functions.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check if the WooCommerce native COGS feature flag is enabled by the user.
 * Requires WooCommerce 8.3+ (FeaturesUtil) and COGS enabled in
 * WooCommerce → Settings → Advanced → Features.
 *
 * @return bool
 */
function isWcCogFeatureEnabled() {
    static $cache = null;
    if ( $cache !== null ) {
        return $cache;
    }
    if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        $cache = false;
        return $cache;
    }
    $cache = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' );
    return $cache;
}

/**
 * Return the active COG source selected in the global setting.
 *
 * @return string 'pixel_cog' | 'wc_cog'
 */
function pys_cog_get_source() {
    return PYS()->getOption( 'woo_cog_source' ) ?: 'pixel_cog';
}

/**
 * Check whether the currently selected COG source is fully available and ready.
 * - 'wc_cog'    → WooCommerce 10.3+ AND feature flag enabled in WC Settings.
 * - 'pixel_cog' → PixelYourSite Cost of Goods plugin active.
 *
 * @return bool
 */
function pys_cog_source_is_available() {
    $source = pys_cog_get_source();
    if ( $source === 'wc_cog' ) {
        return isWcCogAvailable() && isWcCogFeatureEnabled();
    }
    return isPixelCogActive();
}

/**
 * Calculate profit (price − COG) for a single product line.
 * Works for both 'wc_cog' and 'pixel_cog' sources.
 *
 * For 'wc_cog': amount = tax-adjusted price × qty, cog = $product->get_cogs_value() × qty.
 * For 'pixel_cog': delegates to pys_woo_get_cog_product_value() (PYS COG cascade).
 *
 * Returns '' (empty string) when no COG data is found, so callers can fall back to price.
 *
 * @param \WC_Product $product
 * @param int         $quantity
 * @param float|string $price   Unit price (before tax adjustments).
 * @return float|string Profit value, or '' if unavailable.
 */
function pys_cog_get_product_profit( $product, $quantity, $price ) {
    if ( ! pys_cog_source_is_available() ) {
        return '';
    }

    if ( pys_cog_get_source() === 'wc_cog' ) {
        $cogs_value = $product->get_cogs_value();
        if ( $cogs_value === null ) {
            return '';
        }
        $price_args = [ 'qty' => $quantity, 'price' => $price ];
        // For wc_cog: tax inclusion is controlled by our own option, not the PYS COG plugin setting
        if ( PYS()->getOption( 'woo_cog_wc_include_tax' ) ) {
            $amount = wc_get_price_including_tax( $product, $price_args );
        } else {
            $amount = wc_get_price_excluding_tax( $product, $price_args );
        }
        $profit = (float) $amount - ( (float) $cogs_value * (int) $quantity );
        return formatPriceTrimZeros( $profit );
    }

    // pixel_cog: delegate to existing PYS COG cascade function
    return pys_woo_get_cog_product_value( $product, $quantity, $price );
}

/**
 * Calculate total profit for a WooCommerce order.
 * Works for both 'wc_cog' and 'pixel_cog' sources.
 *
 * For 'wc_cog':
 *   profit = subtotal − cogs_total [+ tax] [+ shipping]
 *   tax/shipping inclusion controlled by woo_cog_wc_include_tax / woo_cog_wc_include_shipping.
 *
 * For 'pixel_cog':
 *   Uses pre-calculated '_pixel_cost_of_goods_order_profit' order meta (stored by COG plugin).
 *   Falls back to per-product cascade calculation if meta is absent.
 *
 * Returns 0.0 when profit cannot be determined.
 *
 * @param \WC_Order $order
 * @return float
 */
function pys_cog_get_order_profit( $order ) {
    if ( ! pys_cog_source_is_available() ) {
        return 0.0;
    }

    if ( pys_cog_get_source() === 'wc_cog' ) {
        $cogs_total = (float) $order->get_cogs_total_value();
        $subtotal   = (float) $order->get_subtotal();
        $profit     = $subtotal - $cogs_total;
        if ( PYS()->getOption( 'woo_cog_wc_include_tax' ) ) {
            $profit += (float) $order->get_total_tax();
        }
        if ( PYS()->getOption( 'woo_cog_wc_include_shipping' ) ) {
            $profit += (float) $order->get_shipping_total();
        }
        return max( 0.0, round( $profit, 2 ) );
    }

    // pixel_cog: prefer pre-calculated meta stored by the COG plugin on order save
    $profit = $order->get_meta( '_pixel_cost_of_goods_order_profit', true );
    if ( $profit !== '' && $profit !== false && is_numeric( $profit ) ) {
        return round( (float) $profit, 2 );
    }

    $calculated = getAvailableProductCogOrder( $order );
    return is_numeric( $calculated ) ? round( (float) $calculated, 2 ) : 0.0;
}

/**
 * Resolve Cost of Goods for a single product with 4-level cascade fallback:
 *   1. Product meta  →  2. Parent variation meta  →  3. Category meta  →  4. Global option
 *
 * @param \WC_Product $product
 * @return array{type: string, val: string}
 */
function getAvailableProductCog( $product ) {
    $product_id   = $product->get_id();
    $cost_type    = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_type', true );
    $product_cost = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_val', true );

    // Level 2: parent variation
    if ( ! $product_cost && $product->is_type( 'variation' ) ) {
        $parent_id    = $product->get_parent_id();
        $cost_type    = get_post_meta( $parent_id, '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $parent_id, '_pixel_cost_of_goods_cost_val', true );
    }

    if ( $product_cost ) {
        return [ 'type' => $cost_type, 'val' => $product_cost ];
    }

    // Level 3: category — single query via get_product_cog_by_cat()
    $cat_cog = get_product_cog_by_cat( $product_id );
    if ( $cat_cog['val'] ) {
        return [ 'type' => $cat_cog['type'], 'val' => $cat_cog['val'] ];
    }

    // Level 4: global option
    return [
        'type' => get_option( '_pixel_cost_of_goods_cost_type' ),
        'val'  => get_option( '_pixel_cost_of_goods_cost_val' ),
    ];
}


function getAvailableProductCogOrder( $order ) {
    // ── WooCommerce native COGS path ──────────────────────────────────────────
    if ( pys_cog_get_source() === 'wc_cog' && isWcCogAvailable() && isWcCogFeatureEnabled() ) {
        // For wc_cog: tax inclusion controlled by our own option, not the PYS COG plugin setting
        $wc_include_tax = PYS()->getOption( 'woo_cog_wc_include_tax' );
        $order_total    = 0.0;
        $cost           = 0.0;
        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
            $product = wc_get_product($product_id);
            if(!$product) continue;

            $args = array( 'qty'   => 1, 'price' => $product->get_price());
            $price = 0;
            $qlt = $item['quantity'] ?? 1;

            if($wc_include_tax) {
                $price = wc_get_price_excluding_tax($product, $args);
            } else {
                $price = wc_get_price_including_tax($product,$args);
            }
            $order_total += (float) $price;

            $cogs_value = $product->get_cogs_value();
            if ( $cogs_value === null ) continue;
            $cost += (float) $cogs_value * (int) $qlt;
        }
        return $order_total - $cost;
    }

    // ── PYS COG plugin path ───────────────────────────────────────────────────
    // For pixel_cog: tax inclusion controlled by the PYS COG plugin's own setting
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating' ) == 'no';
    $order_total  = 0.0;
    $cost         = 0.0;
    $custom_total = 0.0;
    $cat_isset    = 0;
    $notice       = '';
    // Read global fallback values once before the loop
    $global_cost  = get_option( '_pixel_cost_of_goods_cost_val' );
    $global_type  = get_option( '_pixel_cost_of_goods_cost_type' );

    foreach ( $order->get_items() as $item_id => $item ) {
        $product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
        $product = wc_get_product($product_id);
        if(!$product) continue;

        $args = array( 'qty'   => 1, 'price' => $product->get_price());

        if($isWithoutTax) {
            $price = wc_get_price_excluding_tax($product, $args);
        } else {
            $price = wc_get_price_including_tax($product,$args);
        }

        $order_total += (float) $price;

        $product_id    = $product->get_id();

        $cost_type    = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_val', true );

        if ( ! $product_cost && $product->is_type( 'variation' ) ) {
            $cost_type    = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        // Use a distinct variable name to avoid shadowing the outer $args parameter
        $price_args = [ 'qty' => 1, 'price' => $product->get_price() ];
        $qlt = $item['quantity'];
        $price      = $isWithoutTax
            ? wc_get_price_excluding_tax( $product, $price_args )
            : wc_get_price_including_tax( $product, $price_args );

        if ( $product_cost ) {
            $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
            $custom_total += $price * $qlt;
        } else {
            // Single query for both val + type via the combined category helper
            $cat_cog      = get_product_cog_by_cat( $product_id );
            $product_cost = $cat_cog['val'];
            $cost_type    = $cat_cog['type'];
            if ( $product_cost ) {
                $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
                $custom_total += $price * $qlt;
                $notice        = 'Category Cost of Goods was used for some products.';
                $cat_isset     = 1;
            } elseif ( $global_cost ) {
                $cost         += ( $global_type == 'percent' ) ? (float) $price * ( (float) $global_cost / 100 ) * $qlt : (float) $global_cost * $qlt;
                $custom_total += $price * $qlt;
                $notice        = $cat_isset ? 'Global and Category Cost of Goods was used for some products.' : 'Global Cost of Goods was used for some products.';
            } else {
                $notice = "Some products don't have Cost of Goods.";
            }
        }
    }

    return $order_total - $cost;
}
function getProductCogOrder( $args ) {
    if ( ! isPixelCogActive() ) {
        return [ 'cost' => 0, 'profit' => 0 ];
    }

    $order_total  = 0.0;
    $cost         = 0.0;
    $custom_total = 0.0;
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating' ) == 'no';
    // Read global fallback values once before the loop
    $global_cost  = get_option( '_pixel_cost_of_goods_cost_val' );
    $global_type  = get_option( '_pixel_cost_of_goods_cost_type' );

    foreach ( $args['products'] as $productData ) {
        $order_total += (float) $productData['total'];
        if ( ! $isWithoutTax ) {
            $order_total += (float) $productData['total_tax'];
        }

        $product_id    = $productData['product_id'];
        $productObject = wc_get_product( $product_id );
        if ( ! $productObject ) continue;

        $cost_type    = get_post_meta( $productObject->get_id(), '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $productObject->get_id(), '_pixel_cost_of_goods_cost_val', true );

        if ( ! $product_cost && $productObject->is_type( 'variation' ) ) {
            $cost_type    = get_post_meta( $productObject->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $productObject->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        // Use a distinct variable name to avoid shadowing the outer $args parameter
        $price_args = [ 'qty' => 1, 'price' => $productObject->get_price() ];
        $qlt        = $productData['quantity'];
        $price      = $isWithoutTax
            ? wc_get_price_excluding_tax( $productObject, $price_args )
            : wc_get_price_including_tax( $productObject, $price_args );

        if ( $product_cost ) {
            $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
            $custom_total += $price * $qlt;
        } else {
            // Single query for both val + type via the combined category helper
            $cat_cog      = get_product_cog_by_cat( $product_id );
            $product_cost = $cat_cog['val'];
            $cost_type    = $cat_cog['type'];
            if ( $product_cost ) {
                $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
                $custom_total += $price * $qlt;
            } elseif ( $global_cost ) {
                $cost         += ( $global_type == 'percent' ) ? (float) $price * ( (float) $global_cost / 100 ) * $qlt : (float) $global_cost * $qlt;
                $custom_total += $price * $qlt;
            }
        }
    }

    return [ 'cost' => $cost, 'profit' => $custom_total - $cost ];
}

function getAvailableProductCogCart() {
    $shipping        = WC()->cart->get_shipping_total();
    $cart_total_base = WC()->cart->get_total( 'edit' ) - $shipping;

    // ── WooCommerce native COGS path ──────────────────────────────────────────
    if ( pys_cog_get_source() === 'wc_cog' && isWcCogAvailable() && isWcCogFeatureEnabled() ) {
        // For wc_cog: tax inclusion controlled by our own option, not the PYS COG plugin setting
        $wc_include_tax = PYS()->getOption( 'woo_cog_wc_include_tax' );
        $cart_total     = $cart_total_base;
        if ( $wc_include_tax ) {
            $cart_total -= WC()->cart->get_shipping_tax(); // keep product tax, remove shipping tax
        } else {
            $cart_total -= WC()->cart->get_total_tax();    // remove all tax
        }
        $cost = 0.0;
        foreach ( WC()->cart->cart_contents as $item ) {
            $product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] )
                ? $item['variation_id']
                : $item['product_id'];
            $product = wc_get_product( $product_id );
            if ( ! $product ) continue;
            $cogs_value = $product->get_cogs_value();
            if ( $cogs_value === null ) continue;
            $cost += (float) $cogs_value * (int) $item['quantity'];
        }
        return $cart_total - $cost;
    }

    // ── PYS COG plugin path ───────────────────────────────────────────────────
    // For pixel_cog: tax inclusion controlled by the PYS COG plugin's own setting
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating' ) == 'no';
    $cart_total   = $cart_total_base;
    if ( $isWithoutTax ) {
        $cart_total -= WC()->cart->get_total_tax();
    } else {
        $cart_total -= WC()->cart->get_shipping_tax();
    }

    // Read global fallback values once before the loop
    $global_cost  = get_option( '_pixel_cost_of_goods_cost_val' );
    $global_type  = get_option( '_pixel_cost_of_goods_cost_type' );

    $cost         = 0.0;
    $custom_total = 0.0;
    $cat_isset    = 0;
    $notice       = '';

    foreach ( WC()->cart->cart_contents as $cart_item_key => $item ) {
        $product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] )
            ? $item['variation_id']
            : $item['product_id'];

        $product = wc_get_product( $product_id );
        if ( ! $product ) continue;

        $cost_type    = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_val', true );

        if ( ! $product_cost && $product->is_type( 'variation' ) ) {
            $cost_type    = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        $price_args = [ 'qty' => 1, 'price' => $product->get_price() ];
        $price      = $isWithoutTax
            ? wc_get_price_excluding_tax( $product, $price_args )
            : wc_get_price_including_tax( $product, $price_args );
        $qlt        = $item['quantity'];

        if ( $product_cost ) {
            $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
            $custom_total += $price * $qlt;
        } else {
            // Single query for both val + type via the combined category helper
            $cat_cog      = get_product_cog_by_cat( $product_id );
            $product_cost = $cat_cog['val'];
            $cost_type    = $cat_cog['type'];
            if ( $product_cost ) {
                $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
                $custom_total += $price * $qlt;
                $notice        = 'Category Cost of Goods was used for some products.';
                $cat_isset     = 1;
            } elseif ( $global_cost ) {
                $cost         += ( $global_type == 'percent' ) ? (float) $price * ( (float) $global_cost / 100 ) * $qlt : (float) $global_cost * $qlt;
                $custom_total += $price * $qlt;
                $notice        = $cat_isset ? 'Global and Category Cost of Goods was used for some products.' : 'Global Cost of Goods was used for some products.';
            } else {
                $notice = "Some products don't have Cost of Goods.";
            }
        }
    }

    return $cart_total - $cost;
}

/**
 * get_product_cog_by_cat.
 *
 * Fetches COG value AND type for a product from its WooCommerce categories in a single
 * wp_get_post_terms() call. Results are cached per product_id for the duration of the request.
 * Selects the category with the numerically highest cost value (same behaviour as the
 * original asort/end approach, but only for categories that have a numeric value set).
 *
 * @param int $product_id
 * @return array{val: string, type: string}
 */
function get_product_cog_by_cat( $product_id ) {
    static $cache = [];
    if ( isset( $cache[ $product_id ] ) ) {
        return $cache[ $product_id ];
    }

    $empty     = [ 'val' => '', 'type' => '' ];
    $term_list = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );

    if ( empty( $term_list ) || is_wp_error( $term_list ) ) {
        $cache[ $product_id ] = $empty;
        return $empty;
    }

    $candidates = [];
    foreach ( $term_list as $term_id ) {
        $val = get_term_meta( $term_id, '_pixel_cost_of_goods_cost_val', true );
        if ( $val !== '' && is_numeric( $val ) ) {
            $candidates[ $term_id ] = [
                'val'  => $val,
                'type' => get_term_meta( $term_id, '_pixel_cost_of_goods_cost_type', true ),
            ];
        }
    }

    if ( empty( $candidates ) ) {
        $cache[ $product_id ] = $empty;
        return $empty;
    }

    // Use the category with the highest numeric cost value
    uasort( $candidates, function ( $a, $b ) {
        return (float) $a['val'] <=> (float) $b['val'];
    } );

    $max                  = end( $candidates );
    $cache[ $product_id ] = $max;
    return $max;
}

/**
 * get_product_type_by_cat.
 *
 * @deprecated Use get_product_cog_by_cat() which returns both val and type in one query.
 * @version 1.0.0
 * @since   1.0.0
 */
function get_product_type_by_cat( $product_id ) {
    return get_product_cog_by_cat( $product_id )['type'];
}

/**
 * get_product_cost_by_cat.
 *
 * @deprecated Use get_product_cog_by_cat() which returns both val and type in one query.
 * @version 1.0.0
 * @since   1.0.0
 */
function get_product_cost_by_cat( $product_id ) {
    return get_product_cog_by_cat( $product_id )['val'];
}

/**
 * Check if WooCommerce plugin installed and activated.
 *
 * @return bool
 */
function isWooCommerceActive() {
    return class_exists( 'woocommerce' );
}

/**
 * Check if Easy Digital Downloads plugin installed and activated.
 *
 * @return bool
 */
function isEddActive() {
    return function_exists( 'EDD' );
}

/**
 * Check if Product Catalog Feed Pro plugin installed and activated.
 *
 * @return bool
 */
function isProductCatalogFeedProActive() {
	return class_exists( 'wpwoof_product_catalog' );
}

/**
 * Check if EDD Products Feed Pro plugin installed and activated.
 *
 * @return bool
 */
function isEddProductsFeedProActive() {
	return class_exists( 'Wpeddpcf_Product_Catalog' );
}

function isBoostActive() {

	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	return is_plugin_active( 'boost/boost.php' );

}

/**
 * Check if Smart OpenGraph plugin installed and activated.
 *
 * @return bool
 */
function isSmartOpenGraphActive() {
    
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    
    return is_plugin_active( 'smart-opengraph/catalog-plugin.php' );
    
}

/**
 * Check if WPML plugin installed and activated.
 *
 * @return bool
 */
function isWPMLActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' );
}
function isPhotoCartActive() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    return is_plugin_active( 'sunshine-photo-cart/sunshine-photo-cart.php' );
}
/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 * Non-scalar values are ignored.
 *
 * @param string|array $var
 *
 * @return string|array
 */
function deepSanitizeTextField( $var ) {

    if ( is_array( $var ) ) {
        return array_map( 'deepSanitizeTextField', $var );
    } else {
        return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
    }

}

function getAvailableUserRoles() {

	$wp_roles   = new \WP_Roles();
	$user_roles = array();

	foreach ( $wp_roles->get_names() as $slug => $name ) {
		$user_roles[ $slug ] = $name;
	}

	return $user_roles;

}

function isDisabledForCurrentRole() {

	$user = wp_get_current_user();
	$disabled_for = PYS()->getOption( 'do_not_track_user_roles' );

	foreach ( (array) $user->roles as $role ) {

		if ( in_array( $role, $disabled_for ) ) {

			add_action( 'wp_head', function() {
				echo "<script type='application/javascript' id='pys-config-warning-user-role'>console.warn('PixelYourSite is disabled for current user role.');</script>\r\n";
			} );

			return true;

		}

	}

	return false;

}
function pys_round( $val, $precision = 2, $mode = PHP_ROUND_HALF_UP )  {
    if ( ! is_numeric( $val ) ) {
        $val = floatval( $val );
    }
    return round( $val, $precision, $mode );
}

function getObjectTerms($taxonomy, $post_id) {
    $terms = get_the_terms($post_id, $taxonomy);
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    // Build a map of term_id => term object for quick parent lookups
    $terms_by_id = [];
    foreach ($terms as $term) {
        $terms_by_id[$term->term_id] = $term;
    }

    // Helper function to calculate the "depth" of a term in the hierarchy
    // (how many parent levels it has)
    $get_depth = function($term) use ($terms_by_id) {
        $depth = 0;
        while ($term->parent && isset($terms_by_id[$term->parent])) {
            $term = $terms_by_id[$term->parent];
            $depth++;
        }
        return $depth;
    };

    // Sort terms by their depth so that parent categories come before child categories
    usort($terms, function($a, $b) use ($get_depth) {
        return $get_depth($a) <=> $get_depth($b);
    });

    // Return an array of decoded term names
    $results = [];
    foreach ($terms as $term) {
        $results[] = html_entity_decode($term->name);
    }

    return $results;
}
/**
 * @param string $taxonomy Taxonomy name
 *
 * @return array Array of object term names and id
 */
function getObjectTermsWithId( $taxonomy, $post_id ) {

    $terms   = get_the_terms( $post_id, $taxonomy );
    $results = array();

    if ( is_wp_error( $terms ) || empty ( $terms ) ) {
        return array();
    }

    // decode special chars
    foreach ( $terms as $term ) {
        $results[] = [
            'name' => html_entity_decode( $term->name ),
            'id'   => $term->term_id
        ];
    }

    return $results;

}
/**
 * Sanitize event name. Only letters, numbers and underscores allowed.
 *
 * @param string $name
 *
 * @return string
 */
function sanitizeKey( $name ) {

	$name = str_replace( ' ', '_', $name );
	$name = preg_replace( '/[^0-9a-zA-z_]/', '', $name );

	return $name;

}

function getCommonEventParams() {

	return array(
		'domain'     => substr( get_home_url( null, '', 'http' ), 7 ),
		'user_roles' => getUserRoles(),
		'plugin'     => 'PixelYourSite',
	);

}

function sanitizeParams( $params ) {

	$sanitized = array();

	foreach ( $params as $key => $value ) {

		// skip empty (but not zero)
        if ( ! isset( $value )  ||
            (is_string($value) && $value == "") ||
            (is_array($value) && count($value) == 0)
        ) {
            continue;
        }

		$key = sanitizeKey( $key );

		if ( is_array( $value ) ) {
			$sanitized[ $key ] = sanitizeParams( $value );
		} elseif ( is_bool( $value ) ) {
			$sanitized[ $key ] = (bool) $value;
		} elseif (is_numeric($value)) {
            $sanitized[ $key ] = $value;
        } else {
            // Do NOT html_entity_decode() here: reversing HTML encoding would
            // re-introduce XSS-capable characters (<, >, ", ') that may have
            // been previously encoded by upstream sanitizers/templating.
            $sanitized[ $key ] = sanitize_text_field( stripslashes( $value ) );
		}

	}

	return $sanitized;

}
function formatPriceTrimZeros($number, $decimals = 2) {
    $formatted = number_format($number, $decimals, '.', '');
    // Remove extra zeros on the right and a period if necessary
    return rtrim(rtrim($formatted, '0'), '.');
}
/**
 * Checks if specified event enabled at least for one configured pixel
 *
 * @param string $eventName
 *
 * @return bool
 */
function isEventEnabled( $eventName ) {

	foreach ( PYS()->getRegisteredPixels() as $pixel ) {
		/** @var Pixel|Settings $pixel */

		if ( $pixel->configured() && $pixel->getOption( $eventName ) ) {
			return true;
		}

	}

	return false;

}

function startsWith( $haystack, $needle ) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos( $haystack, $needle, - strlen( $haystack ) ) !== false;
}

function endsWith( $haystack, $needle ) {
    // search forward starting from end minus needle length characters
    return $needle === "" || ( ( $temp = strlen( $haystack ) - strlen( $needle ) ) >= 0 && strpos( $haystack, $needle,
                $temp ) !== false );
}

function getCurrentPageUrl($removeQuery = false) {
    if(!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
        return '';
    }
    if($removeQuery && isset($_SERVER['QUERY_STRING']) && isset($_SERVER['HTTP_HOST'])){
        return $_SERVER['HTTP_HOST'] . str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']);
    }
    return  $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ;
}

function removeProtocolFromUrl( $url ) {
    
    if ( extension_loaded( 'mbstring' ) ) {
        
        $un = new URL\Normalizer();
        $un->setUrl( $url );
        $url = $un->normalize();
        
    }
    
    // remove fragment component
    $url_parts = parse_url( $url );
    if ( isset( $url_parts['fragment'] ) ) {
        $url = preg_replace( '/#' . $url_parts['fragment'] . '$/', '', $url );
    }
    
    // remove scheme and www and current host if any
    $url = str_replace( array( 'http://', 'https://', 'http://www.', 'https://www.', 'www.' ), '', $url );
    $url = trim( $url );
    $url = ltrim( $url, '/' );
    //$url = rtrim( $url, '/' );
    
    return $url;
    
}

/**
 * Compare single URL or array of URLs with base URL. If base URL is not set, current page URL will be used.
 *
 * @param string|array $url
 * @param string       $base
 * @param string       $rule
 *
 * @return bool
 */
function compareURLs( $url, $base = '', $rule = 'match' ) {
    
    // use current page url if not set
    if ( empty( $base ) ) {
        $base = getCurrentPageUrl();
    }
    
    $base = removeProtocolFromUrl( $base );
    
    if ( is_string( $url ) ) {
        
        if ( empty( $url ) || '*' === $url ) {
            return true;
        }
        
        $url = rtrim( $url, '*' );  // for backward capability
        $url = removeProtocolFromUrl( $url );
        
        if ( $rule == 'match' ) {
            return $base == $url;
        }
        
        if ( $rule == 'contains' ) {
            
            if ( $base == $url ) {
                return true;
            }

            if(empty($base) || empty($url)) {
                return false;
            }
            
            if ( strpos( $base, $url ) !== false ) {
                return true;
            }
            
            return false;
            
        }
        
        return false;
        
    } else {
        
        // recursively compare each url
        foreach ( $url as $single_url ) {
            
            if ( compareURLs( $single_url['value'], $base, $single_url['rule'] ) ) {
                return true;
            }
            
        }
        
        return false;
        
    }

}

/**
 * Currency symbols
 *
 * @return array
 * */

function getPysCurrencySymbols() {
    return array(
        'AED' => '&#x62f;.&#x625;',
        'AFN' => '&#x60b;',
        'ALL' => 'L',
        'AMD' => 'AMD',
        'ANG' => '&fnof;',
        'AOA' => 'Kz',
        'ARS' => '&#36;',
        'AUD' => '&#36;',
        'AWG' => 'Afl.',
        'AZN' => 'AZN',
        'BAM' => 'KM',
        'BBD' => '&#36;',
        'BDT' => '&#2547;&nbsp;',
        'BGN' => '&#1083;&#1074;.',
        'BHD' => '.&#x62f;.&#x628;',
        'BIF' => 'Fr',
        'BMD' => '&#36;',
        'BND' => '&#36;',
        'BOB' => 'Bs.',
        'BRL' => '&#82;&#36;',
        'BSD' => '&#36;',
        'BTC' => '&#3647;',
        'BTN' => 'Nu.',
        'BWP' => 'P',
        'BYR' => 'Br',
        'BYN' => 'Br',
        'BZD' => '&#36;',
        'CAD' => '&#36;',
        'CDF' => 'Fr',
        'CHF' => '&#67;&#72;&#70;',
        'CLP' => '&#36;',
        'CNY' => '&yen;',
        'COP' => '&#36;',
        'CRC' => '&#x20a1;',
        'CUC' => '&#36;',
        'CUP' => '&#36;',
        'CVE' => '&#36;',
        'CZK' => '&#75;&#269;',
        'DJF' => 'Fr',
        'DKK' => 'DKK',
        'DOP' => 'RD&#36;',
        'DZD' => '&#x62f;.&#x62c;',
        'EGP' => 'EGP',
        'ERN' => 'Nfk',
        'ETB' => 'Br',
        'EUR' => '&euro;',
        'FJD' => '&#36;',
        'FKP' => '&pound;',
        'GBP' => '&pound;',
        'GEL' => '&#x20be;',
        'GGP' => '&pound;',
        'GHS' => '&#x20b5;',
        'GIP' => '&pound;',
        'GMD' => 'D',
        'GNF' => 'Fr',
        'GTQ' => 'Q',
        'GYD' => '&#36;',
        'HKD' => '&#36;',
        'HNL' => 'L',
        'HRK' => 'kn',
        'HTG' => 'G',
        'HUF' => '&#70;&#116;',
        'IDR' => 'Rp',
        'ILS' => '&#8362;',
        'IMP' => '&pound;',
        'INR' => '&#8377;',
        'IQD' => '&#x639;.&#x62f;',
        'IRR' => '&#xfdfc;',
        'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
        'ISK' => 'kr.',
        'JEP' => '&pound;',
        'JMD' => '&#36;',
        'JOD' => '&#x62f;.&#x627;',
        'JPY' => '&yen;',
        'KES' => 'KSh',
        'KGS' => '&#x441;&#x43e;&#x43c;',
        'KHR' => '&#x17db;',
        'KMF' => 'Fr',
        'KPW' => '&#x20a9;',
        'KRW' => '&#8361;',
        'KWD' => '&#x62f;.&#x643;',
        'KYD' => '&#36;',
        'KZT' => 'KZT',
        'LAK' => '&#8365;',
        'LBP' => '&#x644;.&#x644;',
        'LKR' => '&#xdbb;&#xdd4;',
        'LRD' => '&#36;',
        'LSL' => 'L',
        'LYD' => '&#x644;.&#x62f;',
        'MAD' => '&#x62f;.&#x645;.',
        'MDL' => 'MDL',
        'MGA' => 'Ar',
        'MKD' => '&#x434;&#x435;&#x43d;',
        'MMK' => 'Ks',
        'MNT' => '&#x20ae;',
        'MOP' => 'P',
        'MRU' => 'UM',
        'MUR' => '&#x20a8;',
        'MVR' => '.&#x783;',
        'MWK' => 'MK',
        'MXN' => '&#36;',
        'MYR' => '&#82;&#77;',
        'MZN' => 'MT',
        'NAD' => 'N&#36;',
        'NGN' => '&#8358;',
        'NIO' => 'C&#36;',
        'NOK' => '&#107;&#114;',
        'NPR' => '&#8360;',
        'NZD' => '&#36;',
        'OMR' => '&#x631;.&#x639;.',
        'PAB' => 'B/.',
        'PEN' => 'S/',
        'PGK' => 'K',
        'PHP' => '&#8369;',
        'PKR' => '&#8360;',
        'PLN' => '&#122;&#322;',
        'PRB' => '&#x440;.',
        'PYG' => '&#8370;',
        'QAR' => '&#x631;.&#x642;',
        'RMB' => '&yen;',
        'RON' => 'lei',
        'RSD' => '&#x434;&#x438;&#x43d;.',
        'RUB' => '&#8381;',
        'RWF' => 'Fr',
        'SAR' => '&#x631;.&#x633;',
        'SBD' => '&#36;',
        'SCR' => '&#x20a8;',
        'SDG' => '&#x62c;.&#x633;.',
        'SEK' => '&#107;&#114;',
        'SGD' => '&#36;',
        'SHP' => '&pound;',
        'SLL' => 'Le',
        'SOS' => 'Sh',
        'SRD' => '&#36;',
        'SSP' => '&pound;',
        'STN' => 'Db',
        'SYP' => '&#x644;.&#x633;',
        'SZL' => 'L',
        'THB' => '&#3647;',
        'TJS' => '&#x405;&#x41c;',
        'TMT' => 'm',
        'TND' => '&#x62f;.&#x62a;',
        'TOP' => 'T&#36;',
        'TRY' => '&#8378;',
        'TTD' => '&#36;',
        'TWD' => '&#78;&#84;&#36;',
        'TZS' => 'Sh',
        'UAH' => '&#8372;',
        'UGX' => 'UGX',
        'USD' => '&#36;',
        'UYU' => '&#36;',
        'UZS' => 'UZS',
        'VEF' => 'Bs F',
        'VES' => 'Bs.S',
        'VND' => '&#8363;',
        'VUV' => 'Vt',
        'WST' => 'T',
        'XAF' => 'CFA',
        'XCD' => '&#36;',
        'XOF' => 'CFA',
        'XPF' => 'Fr',
        'YER' => '&#xfdfc;',
        'ZAR' => '&#82;',
        'ZMW' => 'ZK',
    );
}

function getStandardParams( $post_id = null ) {
    global $post;
    $cpt = get_post_type();
    $params = array(
        'page_title' => "",
        'post_type' => $cpt,
        'post_id' => "",
        'plugin' => "PixelYourSite"
    );

    if(PYS()->getOption("enable_user_role_param")) {
        $params['user_role'] = getUserRoles();
    }

    if(PYS()->getOption("enable_event_url_param")) {
        $params['event_url'] = getCurrentPageUrl(true);
    }

    // If a specific post_id is provided (e.g., during AJAX requests where
    // WordPress conditional tags are unavailable), resolve page context directly.
    if ( ! empty( $post_id ) && (int) $post_id > 0 ) {
        $queried_post = get_post( (int) $post_id );
        if ( $queried_post instanceof \WP_Post ) {
            $params['page_title'] = $queried_post->post_title;
            $params['post_type']  = $queried_post->post_type;
            $params['post_id']    = $queried_post->ID;
        }
    } elseif(is_singular( 'post' )) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;

    } elseif( is_singular( 'page' ) || is_home()) {
        $params['post_type']    = 'page';
        $params['post_id']      = is_home() ? null : $post->ID;
        $params['page_title']   = is_home() == true ? get_bloginfo( 'name' ) : $post->post_title;

    } elseif (isWooCommerceActive() && is_shop()) {
        $page_id = (int) wc_get_page_id( 'shop' );
        $params['post_type'] = 'page';
        $params['post_id']   = $page_id;
        $params['page_title'] = get_the_title( $page_id );

    } elseif ( is_category() ) {
        $cat  = get_query_var( 'cat' );
        $term = get_category( $cat );
        $params['post_type']    = 'category';
        $params['post_id']      = $cat;
        $params['page_title'] = $term->name;

    } elseif ( is_tag() ) {
        $slug = get_query_var( 'tag' );
        $term = get_term_by( 'slug', $slug, 'post_tag' );
        $params['post_type']    = 'tag';
        if($term) {
            $params['post_id']      = $term->term_id;
            $params['page_title']   = $term->name;
        }


    } elseif (is_tax()) {
        $term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
        $params['post_type'] = get_query_var( 'taxonomy' );
        if ( $term ) {
            $params['post_id']      = $term->term_id;
            $params['page_title'] = $term->name;
        }

    }elseif(is_archive()){
        $params['page_title'] = get_the_archive_title();
        $params['post_type'] = 'archive';
    } elseif ((isWooCommerceActive() && $cpt == 'product') ||
        (isEddActive() && $cpt == 'download') ) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;

    } else if ($post instanceof \WP_Post) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;
    }

    if(!PYS()->getOption("enable_post_type_param")) {
        unset($params['post_type']);
    }
    if(!PYS()->getOption("enable_post_id_param")) {
        unset($params['post_id']);
    }


    return $params;
}
function getWPMLProductId($product_id, $tag) {
    $tagOption = "woo_wpml_unified_id";
    $tagLanguageOption = "woo_wpml_language";

    if (isWPMLActive() && $tag->getOption($tagOption)) {
        $wpml_product_id = !empty($tag->getOption($tagLanguageOption))
            ? apply_filters('wpml_object_id', $product_id, 'product', false, $tag->getOption($tagLanguageOption))
            : apply_filters('wpml_original_element_id', NULL, $product_id);
        if ($wpml_product_id) {
            return $wpml_product_id;
        }
    }
    return $product_id;
}
function getTrafficSource () {
    $referrer = "";
    $source = "";
    try {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        }

        $direct = empty($referrer);
        $internal = $direct ? false : (substr($referrer, 0, strlen(site_url())) === site_url());
        $external = !$direct && !$internal;
        $cookie = sanitize_text_field(!isset($_COOKIE['pysTrafficSource']) ? null : $_COOKIE['pysTrafficSource']);
        $session = sanitize_text_field(!isset($_SESSION['TrafficSource']) ? null : $_SESSION['TrafficSource']);
        if (!$external) {
            $source = $cookie || $session ? $cookie ?? $session : 'direct';
        } else {
            $source = ($cookie && $cookie === $referrer) || ($session && $session === $referrer) ? $cookie ?? $session : $referrer;
        }

        if ($source !== 'direct') {

            $parse = parse_url($source);
            if(isset($parse['host'])) {
                return $parse['host'];// leave only domain (Issue #70)
            } elseif ($source == $cookie || $source == $session){
                return $source;
            } else {
                return defined( 'REST_REQUEST' ) && REST_REQUEST ? 'REST API' : "direct";
            }
        } else {
            return defined( 'REST_REQUEST' ) && REST_REQUEST ? 'REST API' : $source;
        }
    } catch (\Exception $e) {
        return "direct";
    }
}

function filterEmails($value) {
    return validateEmail($value) ? "undefined" : $value;
}

function validateEmail($email){
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function getUtms ($seed_undefined = false) {
    $utm = array();

    $utmTerms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    foreach ($utmTerms as $utmTerm) {
        if(isset($_GET[$utmTerm])) {
            $utm[$utmTerm] = sanitize_text_field(filterEmails($_GET[$utmTerm]));
        } elseif (isset($_COOKIE["pys_".$utmTerm])) {
            $utm[$utmTerm] = sanitize_text_field(filterEmails( $_COOKIE["pys_".$utmTerm]));
        } elseif(isset($_SESSION['TrafficUtms']) && isset($_SESSION['TrafficUtms'][$utmTerm])){
            $utm[$utmTerm] = sanitize_text_field(filterEmails( $_SESSION['TrafficUtms'][$utmTerm]));
        } else {
            if($seed_undefined){
                $utm[$utmTerm] = "undefined";
            }
        }
    }

    return $utm;
}

function getUtmsId ($seed_undefined = false) {
    $utm = array();

    $utmTerms = ['fbadid', 'gadid', 'padid', 'bingid'];
    foreach ($utmTerms as $utmTerm) {
        if(isset($_GET[$utmTerm])) {
            $utm[$utmTerm] = sanitize_text_field(filterEmails($_GET[$utmTerm]));
        } elseif (isset($_COOKIE["pys_".$utmTerm])) {
            $utm[$utmTerm] = sanitize_text_field(filterEmails( $_COOKIE["pys_".$utmTerm]));
        } elseif(isset($_SESSION['TrafficUtmsId']) &&  isset($_SESSION['TrafficUtmsId'][$utmTerm])){
            $utm[$utmTerm] = sanitize_text_field(filterEmails( $_SESSION['TrafficUtmsId'][$utmTerm]));
        } else {
            if($seed_undefined){
                $utm[$utmTerm] = "undefined";
            }
        }
    }

    return $utm;
}
function getBrowserTime(){
    $dateTime = array();
    $date = new \DateTime();

    $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    $months = array('January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December');
    $hours = array('00-01', '01-02', '02-03', '03-04', '04-05', '05-06', '06-07', '07-08',
        '08-09', '09-10', '10-11', '11-12', '12-13', '13-14', '14-15', '15-16', '16-17',
        '17-18', '18-19', '19-20', '20-21', '21-22', '22-23', '23-24');

    $dateTime[] = $hours[$date->format('G')];
    $dateTime[] = $days[$date->format('w')];
    $dateTime[] = $months[$date->format('n') - 1];

    $dateTimeString = implode("|", $dateTime);
    return $dateTimeString;
}

/**
 * Get persistence user data
 * @param $em
 * @param $fn
 * @param $ln
 * @param $tel
 * @return array
 */
function get_persistence_user_data( $em, $fn, $ln, $tel ) {

	if ( !apply_filters( 'pys_disable_advanced_form_data_cookie', false ) && !apply_filters( 'pys_disable_advance_data_cookie', false ) ) {
		if ( isset( $_COOKIE[ "pys_advanced_form_data" ] ) ) {
			$userData = json_decode( stripslashes( $_COOKIE[ "pys_advanced_form_data" ] ), true );
			$data_persistence = PYS()->getOption( 'data_persistency' );

			if ( isset( $userData[ "email" ] ) && $userData[ "email" ] != "" && ( $data_persistence == 'keep_data' || empty( $em ) ) ) {
				$em = $userData[ "email" ];
			}
			if ( isset( $userData[ "phone" ] ) && $userData[ "phone" ] != "" && ( $data_persistence == 'keep_data' || empty( $tel ) ) ) {
				$tel = $userData[ "phone" ];
			}
			if ( isset( $userData[ "first_name" ] ) && $userData[ "first_name" ] != "" && ( $data_persistence == 'keep_data' || empty( $fn ) ) ) {
				$fn = $userData[ "first_name" ];
			}
			if ( isset( $userData[ "last_name" ] ) && $userData[ "last_name" ] != "" && ( $data_persistence == 'keep_data' || empty( $ln ) ) ) {
				$ln = $userData[ "last_name" ];
			}
		}
	}

	return array(
		'em'  => $em,
		'fn'  => $fn,
		'ln'  => $ln,
		'tel' => $tel
	);
}

function getAllMetaEventParamName(){
    $metaEventParamName = array(
        'event_url'=>'Event URL',
        'landing_page'=>'Landing Page URL',
        'post_id'=>'Post ID',
        'post_title'=>'Post Title',
        'post_type'=>'Post Type',
        'page_title' => 'Page Title',
        'content_name'=>'Content Name',
        'content_type'=>'Content Type',
        'categories'=>'Categories',
        'category_name'=>'Category Name',
        'tags'=>'Tags',
        'user_role'=>'User Role',
        'plugin'=>'Plugin',
    );

    return $metaEventParamName;
}


function get_aw_feed_country_codes() {
    $country = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo - Brazzaville',
        'CD' => 'Congo - Kinshasa',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GR' => 'Greece',
        'GD' => 'Grenada',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar (Burma)',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'KP' => 'North Korea',
        'MK' => 'North Macedonia',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe'
    ];

    $result = array_map(
        function($name, $code) {
            return $name . " ($code)";
        },
        $country,
        array_keys($country)
    );
    return array_combine(array_keys($country), $result);
}

function get_aw_feed_language_codes() {
    $language = [
        'AB' => 'Abkhaz',
        'AA' => 'Afar',
        'AF' => 'Afrikaans',
        'AK' => 'Akan',
        'SQ' => 'Albanian',
        'AM' => 'Amharic',
        'AR' => 'Arabic',
        'AN' => 'Aragonese',
        'HY' => 'Armenian',
        'AS' => 'Assamese',
        'AV' => 'Avaric',
        'AE' => 'Avestan',
        'AY' => 'Aymara',
        'AZ' => 'Azerbaijani',
        'BM' => 'Bambara',
        'BA' => 'Bashkir',
        'EU' => 'Basque',
        'BE' => 'Belarusian',
        'BN' => 'Bengali',
        'BH' => 'Bihari',
        'BI' => 'Bislama',
        'BS' => 'Bosnian',
        'BR' => 'Breton',
        'BG' => 'Bulgarian',
        'MY' => 'Burmese',
        'CA' => 'Catalan',
        'CH' => 'Chamorro',
        'CE' => 'Chechen',
        'NY' => 'Chichewa',
        'ZH' => 'Chinese',
        'CV' => 'Chuvash',
        'KW' => 'Cornish',
        'CO' => 'Corsican',
        'CR' => 'Cree',
        'HR' => 'Croatian',
        'CS' => 'Czech',
        'DA' => 'Danish',
        'DV' => 'Divehi',
        'NL' => 'Dutch',
        'DZ' => 'Dzongkha',
        'EN' => 'English',
        'EO' => 'Esperanto',
        'ET' => 'Estonian',
        'EE' => 'Ewe',
        'FO' => 'Faroese',
        'FJ' => 'Fijian',
        'FI' => 'Finnish',
        'FR' => 'French',
        'FF' => 'Fula',
        'GL' => 'Galician',
        'KA' => 'Georgian',
        'DE' => 'German',
        'EL' => 'Greek',
        'GN' => 'Guarani',
        'GU' => 'Gujarati',
        'HT' => 'Haitian',
        'HA' => 'Hausa',
        'HE' => 'Hebrew',
        'HZ' => 'Herero',
        'HI' => 'Hindi',
        'HO' => 'Hiri Motu',
        'HU' => 'Hungarian',
        'IA' => 'Interlingua',
        'ID' => 'Indonesian',
        'IE' => 'Interlingue',
        'GA' => 'Irish',
        'IG' => 'Igbo',
        'IK' => 'Inupiaq',
        'IO' => 'Ido',
        'IS' => 'Icelandic',
        'IT' => 'Italian',
        'IU' => 'Inuktitut',
        'JA' => 'Japanese',
        'JV' => 'Javanese',
        'KL' => 'Kalaallisut',
        'KN' => 'Kannada',
        'KR' => 'Kanuri',
        'KS' => 'Kashmiri',
        'KK' => 'Kazakh',
        'KM' => 'Khmer',
        'KI' => 'Kikuyu',
        'RW' => 'Kinyarwanda',
        'KY' => 'Kyrgyz',
        'KV' => 'Komi',
        'KG' => 'Kongo',
        'KO' => 'Korean',
        'KU' => 'Kurdish',
        'KJ' => 'Kwanyama',
        'LA' => 'Latin',
        'LB' => 'Luxembourgish',
        'LG' => 'Luganda',
        'LI' => 'Limburgish',
        'LN' => 'Lingala',
        'LO' => 'Lao',
        'LT' => 'Lithuanian',
        'LU' => 'Luba-Katanga',
        'LV' => 'Latvian',
        'GV' => 'Manx',
        'MK' => 'Macedonian',
        'MG' => 'Malagasy',
        'MS' => 'Malay',
        'ML' => 'Malayalam',
        'MT' => 'Maltese',
        'MI' => 'Maori',
        'MR' => 'Marathi',
        'MH' => 'Marshallese',
        'MN' => 'Mongolian',
        'NA' => 'Nauru',
        'NV' => 'Navajo',
        'ND' => 'North Ndebele',
        'NE' => 'Nepali',
        'NG' => 'Ndonga',
        'NB' => 'Norwegian Bokmål',
        'NN' => 'Norwegian Nynorsk',
        'NO' => 'Norwegian',
        'II' => 'Nuosu',
        'NR' => 'South Ndebele',
        'OC' => 'Occitan',
        'OJ' => 'Ojibwe',
        'CU' => 'Old Church Slavonic',
        'OM' => 'Oromo',
        'OR' => 'Oriya',
        'OS' => 'Ossetian',
        'PA' => 'Punjabi',
        'PI' => 'Pali',
        'FA' => 'Persian',
        'PL' => 'Polish',
        'PS' => 'Pashto',
        'PT' => 'Portuguese',
        'QU' => 'Quechua',
        'RM' => 'Romansh',
        'RN' => 'Kirundi',
        'RO' => 'Romanian',
        'RU' => 'Russian',
        'SA' => 'Sanskrit',
        'SC' => 'Sardinian',
        'SD' => 'Sindhi',
        'SE' => 'Northern Sami',
        'SM' => 'Samoan',
        'SG' => 'Sango',
        'SR' => 'Serbian',
        'GD' => 'Scottish Gaelic',
        'SN' => 'Shona',
        'SI' => 'Sinhala',
        'SK' => 'Slovak',
        'SL' => 'Slovenian',
        'SO' => 'Somali',
        'ST' => 'Southern Sotho',
        'ES' => 'Spanish',
        'SU' => 'Sundanese',
        'SW' => 'Swahili',
        'SS' => 'Swati',
        'SV' => 'Swedish',
        'TA' => 'Tamil',
        'TE' => 'Telugu',
        'TG' => 'Tajik',
        'TH' => 'Thai',
        'TI' => 'Tigrinya',
        'BO' => 'Tibetan',
        'TK' => 'Turkmen',
        'TL' => 'Tagalog',
        'TN' => 'Tswana',
        'TO' => 'Tongan',
        'TR' => 'Turkish',
        'TS' => 'Tsonga',
        'TT' => 'Tatar',
        'TW' => 'Twi',
        'TY' => 'Tahitian',
        'UG' => 'Uyghur',
        'UK' => 'Ukrainian',
        'UR' => 'Urdu',
        'UZ' => 'Uzbek',
        'VE' => 'Venda',
        'VI' => 'Vietnamese',
        'VO' => 'Volapük',
        'WA' => 'Walloon',
        'CY' => 'Welsh',
        'WO' => 'Wolof',
        'XH' => 'Xhosa',
        'YI' => 'Yiddish',
        'YO' => 'Yoruba',
        'ZA' => 'Zhuang',
        'ZU' => 'Zulu'
    ];

    $result = array_map(
        function($name, $code) {
            return $name . " ($code)";
        },
        $language,
        array_keys($language)
    );
    return array_combine(array_keys($language), $result);
}

function pys_get_option( $option, $default = false ) {
    global $wpdb;

    $table = $wpdb->prefix . 'pys_options';

    // We are trying to get from our table
    $value = $wpdb->get_var( $wpdb->prepare(
        "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1",
        $option
    ) );

    if ( $value !== null ) {
        return maybe_unserialize( $value );
    }

    // If it is not in your table, try wp_options
    $value = get_option( $option, $default );

    return $value;
}
function pys_update_option( $option, $value ) {
    global $wpdb;

    $table = $wpdb->prefix . 'pys_options';

    $data = [
        'option_name'  => $option,
        'option_value' => maybe_serialize( $value ),
    ];
    $formats = [ '%s', '%s' ];

    // Update or insert
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE option_name = %s",
        $option
    ) );

    if ( $existing ) {
        $wpdb->update( $table, $data, [ 'option_name' => $option ], $formats, [ '%s' ] );
    } else {
        $wpdb->insert( $table, $data, $formats );
    }

    return true;
}