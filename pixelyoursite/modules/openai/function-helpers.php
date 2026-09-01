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

    // currency is required whenever amount is present, so the two travel together.
    if ( $amount !== null && ! empty( $currency ) ) {
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
 * SHA-256 of an email, normalised the way OpenAI documents.
 *
 * @param string $email
 * @return string 64 hex characters, or '' when there is nothing to hash.
 */
function pys_openai_hash_email( $email ) {

    if ( ! is_string( $email ) ) {
        return '';
    }

    $email = trim( $email );

    if ( $email === '' ) {
        return '';
    }

    return hash( 'sha256', function_exists( 'mb_strtolower' ) ? mb_strtolower( $email, 'UTF-8' ) : strtolower( $email ) );
}

/**
 * Two-letter lowercase ISO country code, or '' when the value is not one.
 *
 * @param string $country
 * @return string
 */
function pys_openai_normalize_country( $country ) {

    if ( ! is_string( $country ) ) {
        return '';
    }

    $country = strtolower( trim( $country ) );

    return preg_match( '/^[a-z]{2}$/', $country ) ? $country : '';
}

/**
 * Lowercased city name, capped at the length the schema allows.
 *
 * @param string $city
 * @return string
 */
function pys_openai_normalize_city( $city ) {

    if ( ! is_string( $city ) ) {
        return '';
    }

    $city = trim( $city );

    if ( $city === '' ) {
        return '';
    }

    $city = function_exists( 'mb_strtolower' ) ? mb_strtolower( $city, 'UTF-8' ) : strtolower( $city );

    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $city, 0, 128, 'UTF-8' );
    }

    return substr( $city, 0, 128 );
}

/**
 * Postal code reduced to the characters the schema accepts.
 *
 * @param string $zip
 * @return string
 */
function pys_openai_normalize_zip( $zip ) {

    if ( ! is_string( $zip ) ) {
        return '';
    }

    $zip = preg_replace( '/[^A-Za-z0-9 \-]/', '', trim( $zip ) );

    if ( $zip === '' || $zip === null ) {
        return '';
    }

    return substr( strtolower( $zip ), 0, 32 );
}
