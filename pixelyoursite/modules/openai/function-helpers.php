<?php

namespace PixelYourSite\OpenAI\Helpers;

use PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Number of minor units in one major unit, per ISO 4217.
 *
 * Deliberately not derived from wc_get_price_decimals(): that is a display
 * setting a shop owner can set to anything, while OpenAI expects the currency's
 * real exponent. Anything unlisted falls back to 2, which covers the vast
 * majority of currencies.
 *
 * @param string $currency Three-letter currency code.
 * @return int 0, 2 or 3.
 */
function pys_openai_currency_exponent( $currency ) {

    $currency = strtoupper( trim( (string) $currency ) );

    // Currencies with no minor unit at all: the amount is already an integer.
    $zero_decimal = array(
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF',
        'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    );

    // Currencies with 1000 minor units per major unit.
    $three_decimal = array( 'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' );

    if ( in_array( $currency, $zero_decimal, true ) ) {
        $exponent = 0;
    } elseif ( in_array( $currency, $three_decimal, true ) ) {
        $exponent = 3;
    } else {
        $exponent = 2;
    }

    return (int) apply_filters( 'pys_openai_currency_exponent', $exponent, $currency );
}

/**
 * Every currency code OpenAI accepts: the active ISO 4217 alphabetic codes.
 *
 * @return string[] Upper-case codes.
 */
function pys_openai_currency_codes() {

	static $codes = null;

	if ( $codes === null ) {
		$codes = array(
			'AED', 'AFN', 'ALL', 'AMD', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
			'BAM', 'BBD', 'BDT', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BOV',
			'BRL', 'BSD', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHE', 'CHF',
			'CHW', 'CLF', 'CLP', 'CNY', 'COP', 'COU', 'CRC', 'CUP', 'CVE', 'CZK',
			'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP',
			'GBP', 'GEL', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL',
			'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK', 'JMD', 'JOD',
			'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD', 'KYD', 'KZT',
			'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD',
			'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN', 'MXV', 'MYR',
			'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR', 'PAB', 'PEN',
			'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF',
			'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE', 'SOS', 'SRD',
			'SSP', 'STN', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP',
			'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'USN', 'UYI', 'UYU',
			'UYW', 'UZS', 'VED', 'VES', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XCG',
			'XOF', 'XPF', 'YER', 'ZAR', 'ZMW', 'ZWG',
		);
	}

	return $codes;
}

/**
 * Is this a currency the endpoint will accept?
 *
 * @param string $currency
 * @return bool
 */
function pys_openai_is_valid_currency( $currency ) {

	$code = strtoupper( trim( (string) $currency ) );

	return $code !== '' && in_array( $code, pys_openai_currency_codes(), true );
}

/**
 * Convert a normal decimal amount into OpenAI's integer minor units.
 *
 * @param mixed  $amount   Decimal amount as float, int or numeric string.
 * @param string $currency Three-letter currency code the amount is in.
 * @return int|null Integer minor units, or null when there is no usable amount.
 */
function pys_openai_to_minor_units( $amount, $currency = '' ) {

    if ( $amount === null || $amount === '' || is_bool( $amount ) || is_array( $amount ) ) {
        return null;
    }

    if ( is_int( $amount ) || is_float( $amount ) ) {
        $value = (float) $amount;
    } elseif ( is_numeric( $amount ) ) {
        // Already dot-decimal, which is what every PYS value helper produces.
        $value = (float) $amount;
    } else {
        $value = pys_openai_parse_localized_number( $amount );

        if ( $value === null ) {
            return null;
        }
    }

    if ( ! is_finite( $value ) ) {
        return null;
    }

    $exponent = pys_openai_currency_exponent( $currency );

    return (int) round( $value * pow( 10, $exponent ) );
}

/**
 * Best-effort read of a human-entered amount such as "1 234,56" or "1,234.56".
 *
 * @param string $amount
 * @return float|null
 */
function pys_openai_parse_localized_number( $amount ) {

    $clean = preg_replace( '/[^0-9,.\-]/', '', (string) $amount );

    if ( $clean === '' || $clean === null || ! preg_match( '/\d/', $clean ) ) {
        return null;
    }

    $last_dot   = strrpos( $clean, '.' );
    $last_comma = strrpos( $clean, ',' );

    if ( $last_dot !== false && $last_comma !== false ) {
        $decimal_pos  = max( $last_dot, $last_comma );
        $integer_part = preg_replace( '/[^0-9\-]/', '', substr( $clean, 0, $decimal_pos ) );
        $fraction     = preg_replace( '/\D/', '', substr( $clean, $decimal_pos + 1 ) );
        $clean        = $integer_part . '.' . $fraction;
    } elseif ( $last_comma !== false ) {
        $fraction = substr( $clean, $last_comma + 1 );
        if ( strlen( $fraction ) === 3 && strpos( $fraction, ',' ) === false ) {
            $clean = str_replace( ',', '', $clean ); // thousands separator
        } else {
            $clean = str_replace( ',', '.', $clean );
        }
    }

    return is_numeric( $clean ) ? (float) $clean : null;
}

/**
 * Resolve the OpenAI content id for a WooCommerce product.
 *
 * @param string|int $product_id
 * @return string
 */
function getOpenAiWooProductContentId( $product_id ) {

    if ( PixelYourSite\isWPMLActive() ) {
        $product_id = PixelYourSite\getWPMLProductId( $product_id, PixelYourSite\OpenAI() );
    }

    $id_option = PixelYourSite\OpenAI()->getOption( 'woo_content_id' );
    $prefix    = PixelYourSite\OpenAI()->getOption( 'woo_content_id_prefix' );
    $suffix    = PixelYourSite\OpenAI()->getOption( 'woo_content_id_suffix' );

    if ( $id_option == 'product_sku' ) {

        $product = wc_get_product( $product_id );

        if ( $product && $product->is_type( 'variation' ) ) {
            $content_id = $product->get_sku();

            if ( empty( $content_id ) ) {
                $parent_product = wc_get_product( $product->get_parent_id() );

                if ( $parent_product ) {
                    $content_id = $parent_product->get_sku();
                }
            }

            if ( empty( $content_id ) ) {
                $content_id = $product_id;
            }
        } elseif ( $product ) {
            $content_id = $product->get_sku();

            if ( empty( $content_id ) ) {
                $content_id = $product_id;
            }
        } else {
            $content_id = $product_id;
        }

    } else {
        $content_id = $product_id;
    }

    if ( empty( $content_id ) ) {
        return '';
    }

    return trim( $prefix ) . $content_id . trim( $suffix );
}

/**
 * Product id to report for a variation, honouring "treat variable as simple".
 *
 * @param \WC_Product $product
 * @return int
 */
function getOpenAiWooVariableToSimpleProductId( $product ) {

    if ( PixelYourSite\OpenAI()->getOption( 'woo_variable_as_simple' ) && $product->get_type() == 'variation' ) {
        return $product->get_parent_id();
    }

    return $product->get_id();
}

/**
 * Product id out of an order/cart line array.
 *
 * @param array $item
 * @return int
 */
function getOpenAiWooProductDataId( $item ) {

    if ( isset( $item['type'] ) && $item['type'] == 'variation'
        && PixelYourSite\OpenAI()->getOption( 'woo_variable_as_simple' ) ) {
        return $item['parent_id'];
    }

    return $item['product_id'];
}

/**
 * Product id out of a cart line array.
 *
 * @param array $product
 * @return int
 */
function getOpenAiWooCartProductId( $product ) {

    if ( PixelYourSite\OpenAI()->getOption( 'woo_variable_as_simple' )
        && isset( $product['parent_id'] ) && $product['parent_id'] !== 0 ) {
        return $product['parent_id'];
    }

    return $product['product_id'];
}

/**
 * Resolve the OpenAI content id for an EDD download.
 *
 * @param int|string $download_id
 * @param int|null   $price_id 0 is a real price variation; null means the
 *                             download has no price options at all.
 * @return string
 */
function getOpenAiEddDownloadContentId( $download_id, $price_id = null ) {
    return PixelYourSite\getEddContentId( PixelYourSite\OpenAI(), $download_id, $price_id );
}

/**
 * Build one contents[] item, dropping every field OpenAI does not accept.
 *
 * @param string $id
 * @param string $name
 * @param string $content_type
 * @param int    $quantity
 * @param mixed  $unit_amount Decimal unit price, converted here.
 * @param string $currency
 * @return array
 */
function pys_openai_content_item( $id, $name, $content_type, $quantity, $unit_amount, $currency ) {

    $item = array();

    if ( $id !== '' && $id !== null ) {
        $item['id'] = (string) $id;
    }

    if ( ! empty( $name ) ) {
        $item['name'] = (string) $name;
    }

    if ( ! empty( $content_type ) ) {
        $item['content_type'] = (string) $content_type;
    }

    if ( $quantity !== null && $quantity !== '' ) {
        $item['quantity'] = (int) $quantity;
    }

    $amount = pys_openai_to_minor_units( $unit_amount, $currency );

    if ( $amount !== null && pys_openai_is_valid_currency( $currency ) ) {
        $item['amount']   = $amount;
        $item['currency'] = $currency;
    }

    return $item;
}

/**
 * OpenAI's click identifier for the current visitor.
 *
 * @return string Empty string when the visitor has no click id.
 */
function pys_openai_get_click_id() {

    if ( ! empty( $_COOKIE['__oppref'] ) ) {
        return sanitize_text_field( wp_unslash( $_COOKIE['__oppref'] ) );
    }

    if ( ! empty( $_GET['oppref'] ) ) {
        return sanitize_text_field( wp_unslash( $_GET['oppref'] ) );
    }

    return '';
}

/**
 * An email address in the form OpenAI hashes: trimmed, lowercased, and actually
 * shaped like an address.
 *
 * @param string $email
 * @return string Empty string when there is nothing usable.
 */
function pys_openai_normalize_email( $email ) {

    if ( ! is_string( $email ) ) {
        return '';
    }

    $email = trim( $email );

    if ( $email === '' || strlen( $email ) > 254 ) {
        return '';
    }

    $email = function_exists( 'mb_strtolower' ) ? mb_strtolower( $email, 'UTF-8' ) : strtolower( $email );

    $pattern = '/^[\w!#$%&\'*+\/=?^`{|}~-]+(?:\.[\w!#$%&\'*+\/=?^`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i';

    return preg_match( $pattern, $email ) === 1 ? $email : '';
}

/**
 * SHA-256 of an email, normalised the way OpenAI documents.
 *
 * @param string $email
 * @return string 64 hex characters, or '' when there is nothing to hash.
 */
function pys_openai_hash_email( $email ) {

    $email = pys_openai_normalize_email( $email );

    return $email === '' ? '' : hash( 'sha256', $email );
}

/**
 * A phone number in the digits-only form OpenAI hashes.
 *
 * @param string $phone
 * @return string Empty string when the number is not usable.
 */
function pys_openai_normalize_phone( $phone ) {

    if ( ! is_string( $phone ) ) {
        return '';
    }

    $digits = preg_replace( '/[\s().-]+/', '', trim( $phone ) );

    if ( ! is_string( $digits ) || $digits === '' ) {
        return '';
    }

    if ( preg_match( '/^\+?[0-9]+$/', $digits ) !== 1 || strpos( $digits, '+0' ) === 0 ) {
        return '';
    }

    $international = ( strpos( $digits, '+' ) === 0 || strpos( $digits, '00' ) === 0 );
    $national      = preg_replace( '/^\+?0*/', '', $digits );

    if ( ! is_string( $national ) ) {
        return '';
    }

    if ( $international && strpos( $national, '1' ) === 0 && strlen( $national ) !== 11 ) {
        return '';
    }

    return preg_match( '/^[1-9][0-9]{7,14}$/', $national ) === 1 ? $national : '';
}

/**
 * SHA-256 of a phone number.
 *
 * @param string $phone
 * @return string 64 hex characters, or '' when there is nothing to hash.
 */
function pys_openai_hash_phone( $phone ) {

    $phone = pys_openai_normalize_phone( $phone );

    return $phone === '' ? '' : hash( 'sha256', $phone );
}

/**
 * A person's name in the form OpenAI hashes.
 *
 * @param string $name
 * @return string Empty string when the name is not usable.
 */
function pys_openai_normalize_name( $name ) {

    if ( ! is_string( $name ) || strlen( $name ) > 1024 ) {
        return '';
    }

    $stripped = preg_replace( '/[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E\s]+/u', '', $name );

    if ( ! is_string( $stripped ) || $stripped === '' ) {
        return '';
    }

    $stripped = function_exists( 'mb_strtolower' ) ? mb_strtolower( $stripped, 'UTF-8' ) : strtolower( $stripped );

    return preg_match( '/\D/u', $stripped ) === 1 ? $stripped : '';
}

/**
 * SHA-256 of a first or last name.
 *
 * @param string $name
 * @return string 64 hex characters, or '' when there is nothing to hash.
 */
function pys_openai_hash_name( $name ) {

    $name = pys_openai_normalize_name( $name );

    return $name === '' ? '' : hash( 'sha256', $name );
}

/**
 * The country names the SDK's own table (`qt`) resolves to a code.
 */
const PYS_OPENAI_COUNTRY_NAMES = array(
    'australia'                => 'au',
    'brazil'                   => 'br',
    'canada'                   => 'ca',
    'france'                   => 'fr',
    'germany'                  => 'de',
    'great britain'            => 'gb',
    'india'                    => 'in',
    'ireland'                  => 'ie',
    'italy'                    => 'it',
    'japan'                    => 'jp',
    'mexico'                   => 'mx',
    'new zealand'              => 'nz',
    'singapore'                => 'sg',
    'south korea'              => 'kr',
    'spain'                    => 'es',
    'switzerland'              => 'ch',
    'u.k.'                     => 'gb',
    'uk'                       => 'gb',
    'u.s.'                     => 'us',
    'u.s.a.'                   => 'us',
    'united arab emirates'     => 'ae',
    'united kingdom'           => 'gb',
    'united states'            => 'us',
    'united states of america' => 'us',
    'usa'                      => 'us',
);

/**
 * A place name -- city, region, or a country written out in words.
 *
 * @param string $place
 * @return string
 */
function pys_openai_normalize_place( $place ) {

    if ( ! is_string( $place ) ) {
        return '';
    }

    $place = trim( $place );

    if ( $place === '' ) {
        return '';
    }

    $place = function_exists( 'mb_strtolower' ) ? mb_strtolower( $place, 'UTF-8' ) : strtolower( $place );
    $place = preg_replace( '/\s+/u', ' ', $place );

    if ( ! is_string( $place ) || $place === '' ) {
        return '';
    }

    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $place, 'UTF-8' ) : strlen( $place );

    return $length <= 128 ? $place : '';
}

/**
 * Two-letter lowercase ISO country code, or '' when the value is not one.
 *
 * @param string $country
 * @return string
 */
function pys_openai_normalize_country( $country ) {

    $country = pys_openai_normalize_place( $country );

    if ( $country === '' ) {
        return '';
    }

    if ( isset( PYS_OPENAI_COUNTRY_NAMES[ $country ] ) ) {
        return PYS_OPENAI_COUNTRY_NAMES[ $country ];
    }

    return preg_match( '/^[a-z]{2}$/', $country ) === 1 ? $country : '';
}

/**
 * Lowercased city name.
 *
 * @param string $city
 * @return string
 */
function pys_openai_normalize_city( $city ) {
    return pys_openai_normalize_place( $city );
}

/**
 * State, province or region. Same rule as a city.
 *
 * @param string $region
 * @return string
 */
function pys_openai_normalize_region( $region ) {
    return pys_openai_normalize_place( $region );
}

/**
 * Postal code: letters, digits, spaces and hyphens, starting with a letter or a
 * digit, at most 32 characters.
 *
 * @param string $code
 * @return string
 */
function pys_openai_normalize_postal_code( $code ) {

    $code = pys_openai_normalize_place( $code );

    if ( $code === '' ) {
        return '';
    }

    return preg_match( '/^[a-z0-9][a-z0-9 -]{0,31}$/', $code ) === 1 ? $code : '';
}
