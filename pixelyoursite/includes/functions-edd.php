<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Build the EDD download content id for a pixel tag, honoring the edd_variable_as_simple option.
 *
 * Single source of truth for the EDD content-id logic in this plugin: every pixel's own helper
 * (getFacebookEddDownloadContentId / getEddDownloadContentId / ...) delegates here, passing its
 * own settings object so per-tag options (edd_content_id, prefix, suffix, edd_variable_as_simple)
 * are still honored.
 *
 * @param Settings      $settings         Pixel/settings object storing edd_content_id / prefix / suffix.
 * @param int|string    $download_id      EDD download (post) id.
 * @param int|null      $price_id         Selected price option id. 0 is a valid variation; pass null for downloads without price variations.
 * @param Settings|null $variableSettings Settings object storing edd_variable_as_simple. Defaults to $settings; pass a different object where the two live apart (free Facebook stores content_id on PYS but the switcher on the Facebook tag).
 *
 * @return string
 */
function getEddContentId( $settings, $download_id, $price_id = null, $variableSettings = null ) {

	if ( null === $variableSettings ) {
		$variableSettings = $settings;
	}

	if ( $settings->getOption( 'edd_content_id' ) == 'download_sku' ) {
		$content_id = get_post_meta( $download_id, 'edd_sku', true );
		if ( empty( $content_id ) ) {
			$content_id = $download_id; // fall back to the download id when no SKU is set
		}
	} else {
		$content_id = $download_id;
	}

	// For downloads with price variations, append the price id unless the option
	// forces the parent (simple) download id for every variation.
	if ( ! $variableSettings->getOption( 'edd_variable_as_simple' ) && null !== $price_id ) {
		$content_id = $content_id . '-' . $price_id;
	}

	$prefix = $settings->getOption( 'edd_content_id_prefix' );
	$suffix = $settings->getOption( 'edd_content_id_suffix' );

	return $prefix . $content_id . $suffix;
}

/**
 * Payment key submitted with the current request.
 *
 * EDD keys are strtolower( md5( ... ) ) (see edd_generate_order_payment_key), so
 * case is not normally an issue — but sanitize_key() is still wrong here, because
 * the `edd_generate_order_payment_key` filter lets a site change the format and
 * sanitize_key() would silently mangle it.
 *
 * urldecode() is deliberately gone: PHP has already decoded $_GET, and a second
 * pass corrupts any key that legitimately contains a percent sign.
 *
 * @return string Empty string when the request carries no key.
 */
function pysEddGetSubmittedPaymentKey() {

	if ( isset( $_GET['payment_key'] ) && is_string( $_GET['payment_key'] ) && '' !== $_GET['payment_key'] ) {
		return sanitize_text_field( wp_unslash( $_GET['payment_key'] ) );
	}

	return '';

}

/**
 * Verify EDD's own receipt link token.
 *
 * EDD builds receipt links as ?id=<order id>&order=<md5( id . payment_key . email )>
 * (edd_get_receipt_page_uri()), but its receipt shortcode resolves the key from
 * the ID without ever checking that token — it relies on edd_can_view_receipt()
 * at render time instead. We check the token, so an order ID on its own is never
 * enough to get data out of this plugin.
 *
 * @param int $order_id
 * @return bool
 */
function pysEddVerifyReceiptToken( $order_id ) {

	if ( empty( $_GET['order'] ) || ! is_string( $_GET['order'] ) || ! function_exists( 'edd_get_order' ) ) {
		return false;
	}

	$order = edd_get_order( absint( $order_id ) );

	if ( empty( $order->id ) ) {
		return false;
	}

	$submitted = sanitize_text_field( wp_unslash( $_GET['order'] ) );
	$expected  = md5( $order->id . $order->payment_key . $order->email );

	return hash_equals( $expected, $submitted );

}

/**
 * Whether the current request may see this EDD order's data.
 *
 * Accepted, in order: EDD's own receipt rule (shop manager, the logged-in
 * customer, or a live purchase session), possession of the payment key, and a
 * verified receipt token.
 *
 * Possession of the key is deliberately enough. It is the secret EDD mints per
 * order, it is the same model WooCommerce uses for order keys, and it is all an
 * offsite gateway return carries when the purchase-session cookie does not
 * survive the round trip. Sites that want to match EDD core exactly — which also
 * demands a session or a login — can opt in with:
 *
 *     add_filter( 'pys_edd_require_receipt_access', '__return_true' );
 *
 * @param int $order_id
 * @return bool
 */
function pysEddRequestCanAccessOrder( $order_id ) {

	$order_id = absint( $order_id );

	if ( ! $order_id || ! function_exists( 'edd_can_view_receipt' ) || ! function_exists( 'edd_get_payment_key' ) ) {
		return false;
	}

	$real_key = (string) edd_get_payment_key( $order_id );

	if ( '' === $real_key ) {
		return false;
	}

	// EDD's own rule. Unlike WooCommerce's `view_order`, this one is sound for
	// guests: logged out, it requires the purchase session key to match.
	if ( edd_can_view_receipt( $real_key ) ) {
		return true;
	}

	if ( apply_filters( 'pys_edd_require_receipt_access', false ) ) {
		return false;
	}

	// Possession of the payment key. The submitted value is also compared
	// lowercased because EDD keys are lowercase by construction, so lowercasing a
	// candidate can only ever match the real key, never a different one.
	$submitted = pysEddGetSubmittedPaymentKey();

	if ( '' !== $submitted
	     && ( hash_equals( $real_key, $submitted ) || hash_equals( $real_key, strtolower( $submitted ) ) ) ) {
		return true;
	}

	return pysEddVerifyReceiptToken( $order_id );

}

function getEddPaymentKey() {
	global $edd_receipt_args;

	$submitted = pysEddGetSubmittedPaymentKey();

	if ( '' !== $submitted ) {
		return $submitted;
	}

	$session = edd_get_purchase_session();

	if ( $session && isset( $session['purchase_key'] ) && '' !== $session['purchase_key'] ) {
		return $session['purchase_key'];
	}

	if ( ! empty( $edd_receipt_args['payment_key'] ) ) {
		return $edd_receipt_args['payment_key'];
	}

	// EDD's own receipt link, ?id=<order id>&order=<token>. The token is verified,
	// so this never turns a bare order ID into order data.
	if ( ! empty( $_GET['id'] ) && ! empty( $_GET['order'] ) && function_exists( 'edd_get_payment_key' ) ) {
		$order_id = absint( $_GET['id'] );
		if ( $order_id && pysEddVerifyReceiptToken( $order_id ) ) {
			$key = (string) edd_get_payment_key( $order_id );
			if ( '' !== $key ) {
				return $key;
			}
		}
	}

	return false;

}

/**
 * Always returns download price as is to make Free compatible with PRO.
 * Used by Pinterest add-on.
 *
 * @return float
 */
function getEddDownloadPrice( $download_id, $price_index = null ) {
    return getEddDownloadPriceToDisplay( $download_id, $price_index );
}

function getEddDownloadPriceToDisplay( $download_id, $price_index = null  ) {

	if ( edd_has_variable_prices( $download_id ) ) {

		$prices = edd_get_variable_prices( $download_id );

		if ( $price_index !== null ) {

			// get selected price option
			$price = isset( $prices[ $price_index ] ) ? $prices[ $price_index ]['amount'] : 0;

		} else {

			// get default price option
			$default_option = edd_get_default_variable_price( $download_id );
			$price = $prices[ $default_option ]['amount'];

		}

	} else {

		$price = edd_get_download_price( $download_id );

	}

	return (float) $price;

}

function getEddEventValue( $option, $amount, $global, $percent = 100 ) {

	switch ( $option ) {
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

	return $value;

}

/**
 * Always returns array with empty values.
 * Used by Pinterest add-on.
 *
 * @return array
 */
function getEddDownloadLicenseData( $download_id ) {
    return array(
        'transaction_type' => null,
        'license_site_limit' => null,
        'license_time_limit' => null,
        'license_version' => null,
    );
}