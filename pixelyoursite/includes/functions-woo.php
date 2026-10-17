<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function isWooCommerceVersionGte( $version ) {

	if ( defined( 'WC_VERSION' ) && WC_VERSION ) {
		return version_compare( WC_VERSION, $version, '>=' );
	} else if ( defined( 'WOOCOMMERCE_VERSION' ) && WOOCOMMERCE_VERSION ) {
		return version_compare( WOOCOMMERCE_VERSION, $version, '>=' );
	} else {
		return false;
	}

}

/**
 * @param \WC_Product|\WP_Post $product
 *
 * @return bool
 */
/**
 * Would WooCommerce list this variation on the product page?
 *
 * The selection WC_Product_Variable::get_available_variations() makes before it builds
 * anything (class-wc-product-variable.php:330-357). Code that used to read that method's
 * result inherited these three rules for free; code that walks get_children() instead has
 * to apply them, or it starts reporting variations the shop hides — a disabled variation,
 * one with no price, or an out-of-stock one on a shop that hides those from the catalogue.
 *
 * @param \WC_Product_Variation|false $variation
 *
 * @return bool
 */
function pysWooVariationIsListable( $variation ) {
	if ( ! is_object( $variation ) || ! method_exists( $variation, 'exists' ) || ! $variation->exists() ) {
		return false;
	}

	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && ! $variation->is_in_stock() ) {
		return false;
	}

	// Core passes the PARENT id as the second argument, whatever its docblock says.
	$hide = apply_filters( 'woocommerce_hide_invisible_variations', true, $variation->get_parent_id(), $variation );

	return ! ( $hide && ! $variation->variation_is_visible() );
}

/**
 * Can a shopper add this product from its own page?
 *
 * What the wp_footer prerender net asks before building data for a page: a product nobody
 * can add has no click to serve, and the build costs seconds on a large variable product.
 * WooCommerce reports every external and grouped product as not purchasable although both
 * carry a button, and a variable parent's own stock status can lag behind its variations
 * or be managed apart from them — so those are judged by type and by variation, and only a
 * simple product by itself. Backorders count as in stock, as is_in_stock() says.
 *
 * @param \WC_Product $product
 *
 * @return bool
 */
function pysWooProductCanBeAdded( $product ) {
	if ( ! is_object( $product ) || ! method_exists( $product, 'is_type' ) ) {
		return false;
	}

	if ( $product->is_type( 'external' ) || $product->is_type( 'grouped' ) ) {
		return true;
	}

	if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_children' ) ) {
		foreach ( (array) $product->get_children() as $child_id ) {
			$child = pysWooGetProduct( (int) $child_id );
			if ( $child && $child->is_purchasable() && $child->is_in_stock() ) {
				return true;
			}
		}
		return false;
	}

	return $product->is_purchasable() && $product->is_in_stock();
}

/**
 * A per-render product cache, open only while the AddToCart prerender runs.
 *
 * The prerender builds every variation's event for every pixel, and each pixel's builder
 * and the shared helpers it calls rebuild the same product with wc_get_product() — 62
 * builds per variation on the stand, ~0.45 ms each, about half the prerender's time.
 * Inside the scope each id is built once and every caller gets a clone (0.016 ms), so a
 * filter that sets a scalar on the product cannot carry state from one pixel to the
 * next. The clone is WC_Data's, taken in CLONE_MODE_CACHE so meta ids survive: meta_data
 * is deep-copied, but nested objects such as
 * the WC_Product_Attribute instances from get_attributes() or the WC_DateTime values
 * are shared with the cached original, so a filter that mutates one of those in place
 * is visible to later pixels of the same id within one prerender.
 *
 * Outside the scope nothing is cached: add-to-cart, checkout and every other event see
 * wc_get_product() exactly as before, so a cart change can never meet a stale product.
 */
final class WooProductScope {
	private static $depth    = 0;
	private static $products = array();

	public static function enter() {
		self::$depth++;
	}

	public static function leave() {
		if ( self::$depth > 0 ) {
			self::$depth--;
		}
		if ( 0 === self::$depth ) {
			self::$products = array();
		}
	}

	public static function active() {
		return self::$depth > 0;
	}

	/** @internal Use pysWooGetProduct(). */
	public static function get( $id ) {
		if ( ! array_key_exists( $id, self::$products ) ) {
			$built = wc_get_product( $id );
			// WC_Data clones in CLONE_MODE_DUPLICATE by default, which nulls every meta
			// id on the clone — a filter that then saved it would add every meta row
			// again. WC's own product cache clones in CLONE_MODE_CACHE; so does this.
			if ( is_object( $built ) && method_exists( $built, 'set_clone_mode' ) ) {
				$built->set_clone_mode( defined( '\WC_Data::CLONE_MODE_CACHE' ) ? \WC_Data::CLONE_MODE_CACHE : 'cache' );
			}
			self::$products[ $id ] = $built;
		}
		$product = self::$products[ $id ];

		return is_object( $product ) ? clone $product : $product;
	}
}

/**
 * wc_get_product(), served from WooProductScope while the prerender runs.
 *
 * Only plain ids are cached; a post, a product object or false (the global product) is
 * passed straight through, as wc_get_product() would resolve it.
 *
 * @param mixed $product Id, post or product, as wc_get_product() accepts.
 *
 * @return \WC_Product|false|null
 */
function pysWooGetProduct( $product = false ) {
	if ( WooProductScope::active() && ( is_int( $product ) || ( is_string( $product ) && ctype_digit( $product ) ) ) ) {
		return WooProductScope::get( (int) $product );
	}

	return wc_get_product( $product );
}

/**
 * The marker pysJsonDiff() returns for "nothing changed" — an object identity, so no JSON
 * value, null included, can be mistaken for it.
 *
 * @return \stdClass
 */
function pysJsonDiffSame() {
	static $same = null;
	if ( null === $same ) {
		$same = new \stdClass();
	}
	return $same;
}

/**
 * What to merge onto $a to get $b, for values as json_decode() returns them (stdClass for
 * JSON objects, arrays for JSON lists).
 *
 * Object to object gives a key diff, with {"__pys_del":1} for a removed key; list to list
 * gives an index diff plus "__pys_len"; anything else is $b whole. The browser half is
 * pysProductDataExpanderJs(), and the two must stay in step.
 *
 * @param mixed $a
 * @param mixed $b
 *
 * @return mixed The diff, or pysJsonDiffSame().
 */
function pysJsonDiff( $a, $b ) {
	if ( json_encode( $a ) === json_encode( $b ) ) {
		return pysJsonDiffSame();
	}

	if ( $a instanceof \stdClass && $b instanceof \stdClass ) {
		$out = new \stdClass();
		foreach ( get_object_vars( $b ) as $key => $value ) {
			if ( ! property_exists( $a, $key ) ) {
				$out->$key = $value;
				continue;
			}
			$diff = pysJsonDiff( $a->$key, $value );
			if ( $diff !== pysJsonDiffSame() ) {
				$out->$key = $diff;
			}
		}
		foreach ( get_object_vars( $a ) as $key => $value ) {
			if ( ! property_exists( $b, $key ) ) {
				$out->$key = (object) array( '__pys_del' => 1 );
			}
		}
		return $out;
	}

	if ( is_array( $a ) && is_array( $b ) ) {
		$out = new \stdClass();
		foreach ( $b as $index => $value ) {
			if ( ! array_key_exists( $index, $a ) ) {
				$out->$index = $value;
				continue;
			}
			$diff = pysJsonDiff( $a[ $index ], $value );
			if ( $diff !== pysJsonDiffSame() ) {
				$out->$index = $diff;
			}
		}
		$out->__pys_len = count( $b );
		return $out;
	}

	return $b;
}

/**
 * The AddToCart prerender of a product with several ids, as one base plus per-id diffs.
 *
 * Every variation's payload repeats almost everything — the pixel ids, the event names,
 * the category and the tags — and differs in a handful of fields: on the stand's
 * 104-variation product, 414 KB of payloads become about 53 KB. The base is the first
 * variation rather than the parent, because variations resemble each other more than
 * they resemble the parent.
 *
 * @param array $params Product id => pixel slug => event data, as the prerender builds it.
 *
 * @return \stdClass|null {"b": base, "d": {id: diff}}, ready for wp_json_encode(); null when
 *                        JSON cannot carry the payload, and the caller prints it in full.
 */
function pysCompactProductData( array $params ) {
	// Through JSON first, so the diff sees the shapes the browser will: an associative
	// array is an object, a list is a list.
	//
	// INF or NAN anywhere makes the encoder return false, and a property name starting
	// with "\0" makes the decoder return null. Either used to reach get_object_vars()
	// and throw from the add-to-cart button hook, cutting the page off there; the
	// caller prints the full form instead, which is what it did before compaction.
	$json = wp_json_encode( $params );
	if ( false === $json ) {
		return null;
	}
	$decoded = json_decode( $json );
	if ( ! $decoded instanceof \stdClass ) {
		return null;
	}
	$ids     = array_keys( get_object_vars( $decoded ) );
	$base    = $decoded->{ $ids[ count( $ids ) > 1 ? 1 : 0 ] };

	$diffs = new \stdClass();
	foreach ( $ids as $id ) {
		$diff        = pysJsonDiff( $base, $decoded->$id );
		$diffs->$id  = $diff === pysJsonDiffSame() ? new \stdClass() : $diff;
	}

	return (object) array( 'b' => $base, 'd' => $diffs );
}

/**
 * The browser half of pysJsonDiff(): rebuilds window.pysWooProductData from
 * pysCompactProductData()'s output, exactly as the full print would have set it, so no
 * click handler — core or add-on — sees a difference.
 *
 * It is printed inline next to the data, so it works whatever order public.js and the
 * add-ons load in. Written without "<", "&" or a closing script tag: it goes straight
 * into the page.
 *
 * @return string
 */
function pysProductDataExpanderJs() {
	return 'window.pysExpandProductData = window.pysExpandProductData || function (c) {'
		. 'var own = Object.prototype.hasOwnProperty;'
		. 'function copy(v) { return v === undefined ? v : JSON.parse(JSON.stringify(v)); }'
		. 'function isObj(v) { return v === null ? false : (typeof v === "object" ? !Array.isArray(v) : false); }'
		. 'function merge(a, d) {'
		.   'if (d === undefined) { return copy(a); }'
		.   'if (!isObj(d)) { return copy(d); }'
		.   'if (own.call(d, "__pys_del")) { if (d.__pys_del === 1) { return undefined; } }'
		.   'var isArr = own.call(d, "__pys_len");'
		.   'var src = isArr ? (Array.isArray(a) ? a : []) : (isObj(a) ? a : {});'
		.   'var out = isArr ? [] : {}, k, v;'
		.   'for (k in src) { if (own.call(src, k)) { if (!own.call(d, k)) { out[k] = copy(src[k]); } } }'
		.   'for (k in d) { if (own.call(d, k)) { if (k !== "__pys_len") { v = merge(own.call(src, k) ? src[k] : undefined, d[k]); if (v !== undefined) { out[k] = v; } } } }'
		.   'if (isArr) { out.length = d.__pys_len; }'
		.   'return out;'
		. '}'
		. 'window.pysWooProductData = window.pysWooProductData || {};'
		. 'for (var id in c.d) { if (own.call(c.d, id)) { window.pysWooProductData[id] = merge(c.b, c.d[id]); } }'
		. '};';
}

function wooProductIsType( $product, $type ) {

	if ( isWooCommerceVersionGte( '2.7' ) ) {
		return $type == $product->is_type( $type );
	} else {
		return $product->product_type == $type;
	}

}
/**
 * True when the current request references an order this visitor may see.
 *
 * This used to return true on the mere presence of one of the order-id request
 * parameters, which is what allowed anyone to read order data by guessing an
 * order ID. Access control lives in wooGetOrderIdFromRequest().
 *
 * @return bool
 */
function wooIsRequestContainOrderId() {
    return wooGetOrderIdFromRequest() > 0;
}
function getWooProductPriceToDisplay( $product_id, $qty = 1, $customPrice = -1 ) {

	if ( ! $product = pysWooGetProduct( $product_id ) ) {
		return 0;
	}


    $productPrice = "";

    // take min price for variable product
    if($product->get_type() == "variable") {
        $prices = $product->get_variation_prices( true );
        if(empty( $prices['price'] )) {
            $productPrice = $product->get_price();
        } else {
            $variation_id = key($prices['price']); // Getting the variation ID
            $variation = pysWooGetProduct($variation_id); // Creating a Variation Instance

            if ($variation && is_a($variation, 'WC_Product')) { // Check if $variation is a valid product object
                $productPrice = current( $prices['price'] ); // Getting the price of the variation
            } else {
                // Handle the case where no valid variation is found
                // For example, fallback to the parent product's price or set a default price
                $productPrice = $product->get_price(); // Fallback to the parent product's price
            }
        }

    } else {
        $productPrice = $product->get_price();
    }

    if ( is_numeric( $customPrice ) && (float) $customPrice >= 0 ) { // 0 is a price; PHP 7 said 0 == ""
        $productPrice = $customPrice;
    } else {
        // discount plugins change the price the customer pays, not get_price()
        $discounted = pys_woo_get_discounted_product_price( $product, $qty, $productPrice );
        if ( $discounted !== false ) {
            $productPrice = $discounted;
        }
    }

    return formatPriceTrimZeros((float) wc_get_price_to_display( $product, array( 'qty' => $qty,'price'=>$productPrice ) ));
}

/**
 * Unit price of a cart or order line from what the customer pays for it.
 *
 * Cart lines (line_subtotal) and order lines (subtotal) already carry the price a discount
 * plugin set on the item, and not the coupons: the same base the Cost of Goods plugin takes
 * a percent cost from.
 *
 * @param mixed $subtotal     line subtotal, before coupons
 * @param mixed $subtotal_tax its tax
 * @param mixed $qty
 * @param bool  $with_tax
 *
 * @return float|false false when the line has no usable subtotal or quantity
 */
function pys_woo_line_unit_price( $subtotal, $subtotal_tax, $qty, $with_tax ) {
    if ( ! is_numeric( $subtotal ) || ! is_numeric( $qty ) || (float) $qty <= 0 ) {
        return false;
    }
    $line = (float) $subtotal;
    if ( $with_tax && is_numeric( $subtotal_tax ) ) {
        $line += (float) $subtotal_tax;
    }
    return $line / (float) $qty;
}

/**
 * A checkout event: InitiateCheckout and the checkout-progress steps. Their value setting
 * reads "Order's total" (renderValueOptionsBlock), the other cart events' "Product price".
 *
 * @param SingleEvent $event
 */
function pys_woo_is_checkout_event( $event ) {
    $id = is_object( $event ) && method_exists( $event, 'getId' ) ? (string) $event->getId() : '';
    return $id === 'woo_initiate_checkout' || strpos( $id, 'woo_initiate_checkout_progress_' ) === 0;
}

/**
 * The cart's total the way Purchase reports the order's: after coupons and cart rules, the
 * total the customer sees at the checkout — the counterpart of $order->get_total().
 *
 * @return float
 */
function getWooCartOrderTotal() {
    if ( ! isWooCartAvailable() ) {
        return 0.0;
    }
    return pys_round( (float) WC()->cart->get_total( 'edit' ) );
}

/**
 * Value of the "price" option for a cart-level event, by the rule the settings UI labels
 * it: a checkout event promises the order's total, the others the products' price.
 *
 * @param SingleEvent $event
 */
function getWooEventCartTotal($event) {

    if ( pys_woo_is_checkout_event( $event ) ) {
        return getWooCartOrderTotal();
    }

    return getWooEventCartSubtotal($event);
}
/**
 * @param SingleEvent $event
 */
function getWooEventCartSubtotal($event) {
    $subTotal = 0;
    $include_tax = get_option( 'woocommerce_tax_display_cart' ) == 'incl';

    foreach ($event->args['products'] as $product) {
        $subTotal += $product['subtotal'];
        if($include_tax) {
            $subTotal += $product['subtotal_tax'];
        }
    }
    return pys_round($subTotal);
}
function getWooCartSubtotal() {
    if ( ! isWooCartAvailable() ) return 0;

    WC()->cart->calculate_totals();
	// subtotal is always same value on front-end and depends on PYS options
	$include_tax = get_option( 'woocommerce_tax_display_cart' ) == 'incl';

	if ( $include_tax ) {

		if ( isWooCommerceVersionGte( '3.2.0' ) ) {
			$subtotal = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
		} else {
			$subtotal = WC()->cart->subtotal;
		}

	} else {

		if ( isWooCommerceVersionGte( '3.2.0' ) ) {
			$subtotal = (float) WC()->cart->get_subtotal();
		} else {
			$subtotal = WC()->cart->subtotal_ex_tax;
		}

	}

	return $subtotal;

}

function getWooEventValue( $valueOption, $global, $percent, $product_id,$qty ) {

    $product = wc_get_product($product_id);

    if(!$product) return 0;

    if ( $valueOption == 'cog' && pys_cog_source_is_available() ) {
        return pys_cog_get_product_profit( $product, $qty, pys_woo_product_price_after_discounts( $product, $qty ) );
    }

    $amount = getWooProductPriceToDisplay( $product_id, $qty );

    switch ( $valueOption ) {
        case 'global': $value = $global; break;
        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', '', $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;
        default:$value = (float)$amount;
    }

    return formatPriceTrimZeros($value);

}
/**
 * @param $valueOption
 * @param \WC_Order $order
 * @param $global
 * @param $order_id
 * @param $content_ids
 * @param int $percent
 * @return float|int
 */
function getWooEventValueOrder( $valueOption, $order, $global, $percent = 100 ) {

    $amount = $order->get_total();
    switch ( $valueOption ) {
        case 'global':
            $value = (float) $global;
            break;

        case 'cog':
            $cog_value = pys_cog_get_order_profit($order);
            ($cog_value !== '') ? $value = (float) round($cog_value, 2) : $value = (float) $amount;
            if ( !pys_cog_source_is_available() ) $value = (float) $amount;
            break;

        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', '', $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;

        default:    // "price" option
            $value = (float) $amount;
    }

    return formatPriceTrimZeros($value);

}

function get_fees($order){
    $fees = $order->get_fees();
    $fee_amount = 0;

    foreach ($fees as $fee) {
        $fee_amount += $fee->get_total();
    }
    if($fee_amount > 0){
        return $fee_amount;
    }

    return 0;
}
/**
 * @param string $valueOption
 * @param float  $global
 * @param int    $percent
 * @param bool   $use_order_total true for a checkout event, whose "price" option reads "Order's total":
 *                            the cart total after coupons, as Purchase reports the order's.
 */
function getWooEventValueCart( $valueOption, $global, $percent = 100, $use_order_total = false ) {

    if ( ! isWooCartAvailable() ) return 0;

    if ( $valueOption == 'cog' && pys_cog_source_is_available() ) {
        // getAvailableProductCogCart() now handles both 'pixel_cog' and 'wc_cog' sources
        $cog_value = getAvailableProductCogCart();
        if ( $cog_value !== '' && is_numeric( $cog_value ) ) {
            return (float) round( $cog_value, 2 );
        }
        if ( get_option( '_pixel_cog_tax_calculating' ) == 'no' ) {
            return WC()->cart->cart_contents_total;
        }
        return WC()->cart->cart_contents_total + WC()->cart->tax_total;
    }


    $amount = $use_order_total ? getWooCartOrderTotal() : WC()->cart->subtotal;

    switch ( $valueOption ) {
        case 'global':
            $value = (float) $global;
            break;

        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', '', $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;

        default:    // "price" option
            $value = (float) $amount;
    }

    return formatPriceTrimZeros($value);
}

/*
 * -----------------------------------------------------------------------------
 * Order-received access control
 * -----------------------------------------------------------------------------
 * WooCommerce resolves the order-received endpoint from the URL path and then
 * validates the order key before it renders any order detail; on a key mismatch
 * it deliberately renders an empty thank-you page so order IDs cannot be probed.
 *
 * The plugin used to resolve the order from the same URL without ever comparing
 * the key, so any visitor could read the tracking payload — and the Facebook
 * Advanced Matching block, which carries the buyer's email, phone, name and
 * address — for any order ID. Every request-to-order-ID conversion now goes
 * through wooGetOrderIdFromRequest(), which refuses to return an ID the visitor
 * is not allowed to see.
 */

/**
 * Order key submitted with the current request, with its case preserved.
 *
 * Order keys are `wc_order_` . wp_generate_password( 13, false ) — mixed case —
 * so sanitize_key() must NOT be used here: it lowercases, and hash_equals()
 * would then fail for virtually every real order.
 *
 * @return string Empty string when the request carries no key.
 */
function pysWooGetSubmittedOrderKey() {

    foreach ( array( 'key', 'order_key' ) as $param ) {
        if ( isset( $_REQUEST[ $param ] ) && is_string( $_REQUEST[ $param ] ) && '' !== $_REQUEST[ $param ] ) {
            return sanitize_text_field( wp_unslash( $_REQUEST[ $param ] ) );
        }
    }

    return '';

}

/**
 * Order IDs created during this visitor's session.
 *
 * Read from the WooCommerce session and from our own signed cookie, because
 * neither survives every flow on its own: WC_Order::payment_complete() and
 * WC_Shortcode_Checkout::order_received() both reset the WooCommerce session
 * pointers before the thank-you page renders.
 *
 * @return int[]
 */
function pysWooGetSessionOrderIds() {

    $ids = array();

    if ( function_exists( 'WC' ) && isset( WC()->session ) && WC()->session ) {
        $stored = WC()->session->get( 'pys_session_orders' );
        if ( is_array( $stored ) ) {
            $ids = $stored;
        }
    }

    if ( ! empty( $_COOKIE['pys_woo_orders'] ) ) {
        $ids = array_merge( $ids, pysWooParseSessionOrdersCookie( wp_unslash( $_COOKIE['pys_woo_orders'] ) ) );
    }

    return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

}

/**
 * @param string $raw Raw cookie value, "1,2,3|<hmac>".
 * @return int[]
 */
function pysWooParseSessionOrdersCookie( $raw ) {

    if ( ! is_string( $raw ) || false === strpos( $raw, '|' ) ) {
        return array();
    }

    list( $payload, $signature ) = explode( '|', $raw, 2 );

    if ( ! hash_equals( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), $signature ) ) {
        return array();
    }

    return array_filter( array_map( 'absint', explode( ',', $payload ) ) );

}

/**
 * Remember that this visitor created this order, so the purchase event still
 * fires when the payment gateway returns them without the order key.
 *
 * @param int $order_id
 */
function pysWooRecordSessionOrder( $order_id ) {

    $order_id = absint( $order_id );

    if ( ! $order_id ) {
        return;
    }

    $ids = pysWooGetSessionOrderIds();

    if ( in_array( $order_id, $ids, true ) ) {
        return;
    }

    $ids[] = $order_id;

    if ( count( $ids ) > 10 ) {
        $ids = array_slice( $ids, - 10 );
    }

    if ( function_exists( 'WC' ) && isset( WC()->session ) && WC()->session ) {
        WC()->session->set( 'pys_session_orders', $ids );
    }

    pysWooSetSessionOrdersCookie( $ids );

    pysWooAccessCacheEpoch( true );

}

/**
 * Cache generation for pysWooRequestCanAccessOrder(), bumped whenever the set of
 * orders this visitor is known to own changes within the same request.
 *
 * @param bool $bump
 * @return int
 */
function pysWooAccessCacheEpoch( $bump = false ) {

    static $epoch = 0;

    if ( $bump ) {
        $epoch ++;
    }

    return $epoch;

}

/**
 * @param int[] $ids
 */
function pysWooSetSessionOrdersCookie( $ids ) {

    $payload = implode( ',', array_map( 'absint', $ids ) );
    $value   = $payload . '|' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

    // keep it readable within the current request as well
    $_COOKIE['pys_woo_orders'] = $value;

    if ( headers_sent() ) {
        return;
    }

    $expires = time() + 2 * HOUR_IN_SECONDS;
    $path    = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
    $domain  = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
    $secure  = is_ssl();

    /*
     * Gateways that return the buyer with a cross-site POST (some PayU and LATAM
     * integrations) do not send SameSite=Lax cookies. SameSite=None requires
     * Secure, so plain HTTP falls back to Lax and relies on the order key.
     */
    $samesite = $secure ? 'None' : 'Lax';

    if ( version_compare( PHP_VERSION, '7.3', '>=' ) ) {
        setcookie( 'pys_woo_orders', $value, array(
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $samesite,
        ) );
    } else {
        setcookie( 'pys_woo_orders', $value, $expires, $path . '; samesite=' . $samesite, $domain, $secure, true );
    }

}

/**
 * @param int $order_id
 * @return bool
 */
function pysWooSessionHasOrder( $order_id ) {
    return in_array( absint( $order_id ), pysWooGetSessionOrderIds(), true );
}

/**
 * Opt-in fallback for gateways that return the buyer without the order key AND
 * outside of their original session (a cross-site POST drops the session
 * cookie). Disabled by default:
 *
 *     add_filter( 'pys_woo_legacy_gateway_compat', '__return_true' );
 *
 * Even when enabled it only covers orders created within the last hour that
 * actually went through, and it never unlocks personal data.
 *
 * @param \WC_Order $order
 * @return bool
 */
function pysWooLegacyGatewayCompatAllows( $order ) {

    if ( ! apply_filters( 'pys_woo_legacy_gateway_compat', false ) ) {
        return false;
    }

    $created = $order->get_date_created();

    if ( ! $created ) {
        return false;
    }

    $window = (int) apply_filters( 'pys_woo_legacy_gateway_compat_window', HOUR_IN_SECONDS );

    if ( ( time() - $created->getTimestamp() ) > $window ) {
        return false;
    }

    // same form the rest of the plugin compares against, e.g. EventsWoo::isReadyForFire()
    $status = 'wc-' . $order->get_status( 'edit' );

    // never a customer-facing order, whatever the site is configured to track
    if ( in_array( $status, array( 'wc-trash', 'wc-draft', 'wc-auto-draft' ), true ) ) {
        return false;
    }

    /*
     * Otherwise defer to the site's own Purchase rules rather than a list of our
     * own: a store that deliberately tracks Purchase on `pending` (bank transfer
     * and similar) must keep tracking it here too, or this fallback would break
     * the very gateways it exists for.
     */
    $disabled = (array) PYS()->getOption( 'woo_order_purchase_disabled_status' );

    return ! in_array( $status, $disabled, true );

}

/**
 * Whether the current request may see this order's data.
 *
 * @param int    $order_id
 * @param string $context 'default' for tracking payloads, 'pii' for personal
 *                        data such as Advanced Matching or rendered order
 *                        details. The 'pii' context never falls back to legacy
 *                        gateway compatibility.
 * @return bool
 */
function pysWooRequestCanAccessOrder( $order_id, $context = 'default' ) {

    static $cache = array();

    $order_id = absint( $order_id );

    if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
        return false;
    }

    // everything the answer depends on that can still change within a request:
    // the known-orders marker, the current user, and the submitted order key
    $state = pysWooAccessCacheEpoch() . '|' . get_current_user_id() . '|' . pysWooGetSubmittedOrderKey();

    if ( ! isset( $cache[ $state ][ $context ][ $order_id ] ) ) {
        $cache[ $state ][ $context ][ $order_id ] = (bool) apply_filters(
            'pys_woo_request_can_access_order',
            pysWooCheckOrderAccess( $order_id, $context ),
            $order_id,
            $context
        );
    }

    return $cache[ $state ][ $context ][ $order_id ];

}

/**
 * @param int    $order_id
 * @param string $context
 * @return bool
 */
function pysWooCheckOrderAccess( $order_id, $context ) {

    $order = wc_get_order( $order_id );

    /*
     * Must be a customer order. wc_get_order() also returns refunds, and
     * WC_Order_Refund extends WC_Abstract_Order — it has neither get_order_key()
     * nor get_customer_id(). Orders and refunds share one ID sequence, so an
     * enumerating request lands on refund IDs sooner or later; without this
     * check that would be a fatal error instead of a denial.
     */
    if ( ! $order instanceof \WC_Order ) {
        return false;
    }

    // shop managers
    if ( current_user_can( 'manage_woocommerce' ) ) {
        return true;
    }

    /*
     * The order belongs to the logged-in customer.
     *
     * The is_user_logged_in() guard is load bearing. WooCommerce grants
     * `view_order` whenever the current user ID equals the order's customer ID
     * (wc_customer_has_capability()), and both are 0 for a guest order viewed by
     * a logged-out visitor — so an unguarded capability check would hand every
     * guest order to anyone. Same reason the customer ID is required to be > 0.
     */
    $customer_id = (int) $order->get_customer_id();

    if ( is_user_logged_in()
         && ( ( $customer_id > 0 && (int) get_current_user_id() === $customer_id )
              || current_user_can( 'view_order', $order_id ) ) ) {
        return true;
    }

    // the request carries the real order key
    $submitted_key = pysWooGetSubmittedOrderKey();

    if ( '' !== $submitted_key ) {
        $real_key = (string) $order->get_order_key();
        if ( '' !== $real_key && hash_equals( $real_key, $submitted_key ) ) {
            return true;
        }
    }

    // the order was placed in this visitor's session — covers gateways and
    // funnel plugins (CartFlows, PayU, ...) that drop the key from the URL
    if ( pysWooSessionHasOrder( $order_id ) ) {
        return true;
    }

    // WooCommerce's own session pointers; they are reset early, so they only
    // ever add coverage on top of our own marker
    if ( function_exists( 'WC' ) && isset( WC()->session ) && WC()->session ) {
        if ( $order_id === absint( WC()->session->get( 'order_awaiting_payment' ) )
             || $order_id === absint( WC()->session->get( 'store_api_draft_order' ) ) ) {
            return true;
        }
    }

    if ( 'pii' !== $context && pysWooLegacyGatewayCompatAllows( $order ) ) {
        return true;
    }

    return false;

}

/**
 * Order ID referenced by the current request, before any access check.
 *
 * @return int Order ID, or -1 when the request references no order.
 */
function pysWooResolveOrderIdFromRequest() {

    global $wp;

    if ( ! isWooCommerceActive() ) {
        return -1;
    }

    /*
     * When a key is submitted, resolve through the key itself. That lookup is
     * what ties the key to an order; the old code preferred the URL path
     * variable and so never validated the key on exactly the requests that
     * mattered.
     */
    $submitted_key = pysWooGetSubmittedOrderKey();

    if ( '' !== $submitted_key ) {
        $order_id = (int) wc_get_order_id_by_order_key( $submitted_key );
        if ( $order_id > 0 ) {
            return $order_id;
        }
    }

    $order_received = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;

    if ( $order_received > 0 && PYS()->woo_is_order_received_page() ) {
        return $order_received;
    }

    // gateway specific parameters: PayU, some LATAM gateways, CartFlows
    foreach ( array( 'referenceCode', 'ref_venta', 'wcf-order' ) as $param ) {
        if ( ! empty( $_REQUEST[ $param ] ) ) {
            $order_id = absint( $_REQUEST[ $param ] );
            if ( $order_id > 0 ) {
                return $order_id;
            }
        }
    }

    return -1;

}

/**
 * Order ID for the current request, or -1 when there is none the visitor is
 * allowed to see.
 *
 * @return int
 */
function wooGetOrderIdFromRequest() {

    static $resolved = null;

    if ( null !== $resolved ) {
        return $resolved;
    }

    $order_id = pysWooResolveOrderIdFromRequest();

    if ( $order_id > 0 && ! pysWooRequestCanAccessOrder( $order_id ) ) {
        $order_id = -1;
    }

    // the query vars this depends on are only final once `wp` has run
    if ( did_action( 'wp' ) ) {
        $resolved = $order_id;
    }

    return $order_id;

}

/**
 * Check is Woo Supporting High-Performance Order Storage
 * @return bool
 */
function isWooUseHPStorage() {
    if(class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )) {
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
    return false;

}