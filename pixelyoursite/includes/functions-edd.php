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

function getEddPaymentKey() {
	global $edd_receipt_args;

	$session = edd_get_purchase_session();

	if ( isset( $_GET['payment_key'] ) ) {
		return urldecode( $_GET['payment_key'] );
	} else if ( $session && isset($session['purchase_key']) ) {
		return $session['purchase_key'];
	} elseif (  $edd_receipt_args && isset($edd_receipt_args['payment_key']) && $edd_receipt_args['payment_key'] ) {
		return $edd_receipt_args['payment_key'];
	} else {
		return false;
	}

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