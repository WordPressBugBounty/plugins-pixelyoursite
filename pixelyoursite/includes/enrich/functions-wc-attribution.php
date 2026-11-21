<?php
/**
 * WooCommerce order attribution as a fallback for the LAST visit.
 *
 * PYS reads the referrer only, so a click that arrives with gclid/msclkid and no
 * referrer is stored as "direct" with empty UTMs, while WooCommerce's own
 * attribution (sourcebuster) knows it was Google / cpc. When PYS saw nothing
 * useful, the WooCommerce values fill the last visit; the first visit is never
 * touched, and the filled data is marked with last_pys_source_origin.
 */

namespace PixelYourSite;

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );

/**
 * @param \WC_Order $order
 * @return bool True when pys_enrich_data was changed.
 */
function pys_fill_last_visit_from_wc_attribution( $order ) {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
		return false;
	}

	$data = $order->get_meta( 'pys_enrich_data', true );
	if ( ! is_array( $data ) || ! empty( $data['last_pys_source_origin'] ) ) {
		return false;
	}

	// Only a last visit that is "direct without UTM".
	$source = isset( $data['last_pys_source'] ) && is_string( $data['last_pys_source'] ) ? trim( $data['last_pys_source'] ) : '';
	if ( '' !== $source && 'direct' !== strtolower( $source ) ) {
		return false;
	}
	$utm = isset( $data['last_pys_utm'] ) && is_string( $data['last_pys_utm'] ) ? $data['last_pys_utm'] : '';
	foreach ( explode( '|', $utm ) as $pair ) {
		$parts = explode( ':', $pair, 2 );
		if ( isset( $parts[1] ) && '' !== trim( $parts[1] ) && 'undefined' !== trim( $parts[1] ) ) {
			return false;
		}
	}

	$type = (string) $order->get_meta( '_wc_order_attribution_source_type', true );
	if ( ! in_array( $type, array( 'utm', 'referral', 'organic' ), true ) ) {
		return false;
	}

	$clean = function ( $value ) {
		$value = wp_kses( (string) $value, array() );
		$value = trim( str_replace( array( '|', "\r", "\n" ), ' ', $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 100 ) : substr( $value, 0, 100 );
	};

	// The referrer's domain when it is an external URL, else the utm source.
	$new_source = '';
	$referrer   = trim( (string) $order->get_meta( '_wc_order_attribution_referrer', true ) );
	if ( '' !== $referrer && false !== filter_var( $referrer, FILTER_VALIDATE_URL ) ) {
		// PYS's getDomain() drops a leading www., so do the same before comparing and storing.
		$host = preg_replace( '/^www\./', '', strtolower( (string) parse_url( $referrer, PHP_URL_HOST ) ) );
		$home = preg_replace( '/^www\./', '', strtolower( (string) parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( '' !== $host && $host !== $home ) {
			$new_source = $clean( $host );
		}
	}
	if ( '' === $new_source ) {
		$new_source = $clean( $order->get_meta( '_wc_order_attribution_utm_source', true ) );
	}
	if ( '' === $new_source ) {
		return false;
	}

	$data['last_pys_source'] = $new_source;

	// WooCommerce's utm_* of organic/referral orders are synthesized, not real.
	if ( 'utm' === $type ) {
		$terms = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) as $term ) {
			$value   = $clean( $order->get_meta( '_wc_order_attribution_' . $term, true ) );
			$terms[] = $term . ':' . ( '' === $value ? 'undefined' : str_replace( ':', ' ', $value ) );
		}
		$data['last_pys_utm'] = implode( '|', $terms );
	}

	if ( empty( $data['last_pys_landing'] ) ) {
		$entry = trim( (string) $order->get_meta( '_wc_order_attribution_session_entry', true ) );
		$entry = preg_split( '/[?#]/', $entry, 2 );
		$entry = wp_kses( (string) $entry[0], array() );
		if ( '' !== $entry ) {
			$data['last_pys_landing'] = substr( $entry, 0, 255 );
		}
	}

	$data['last_pys_source_origin'] = 'woocommerce';

	$order->update_meta_data( 'pys_enrich_data', $data );
	$order->save_meta_data();

	return true;
}
