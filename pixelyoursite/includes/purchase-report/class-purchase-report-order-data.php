<?php
/**
 * What the plugin stored with an order: the visit data of the checkout, the
 * identifiers sent with the Purchase and the customer.
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Reads the stored data of an order for the order box, the admin emails and the summaries.
 */
class PurchaseReportOrderData {

	/** The value the plugin writes into every visit field of a renewal order. */
	const RENEWAL_PLACEHOLDER = 'recurring payment';

	/**
	 * Click parameters of the visit data, the tag that must be active and the platform name.
	 * The landing URL is stored without its query string, so these are the only clicks Free can tell.
	 */
	const VISIT_CLICK_PLATFORMS = array(
		'padid'  => array( 'pinterest', 'Pinterest' ),
		'bingid' => array( 'bing', 'Bing' ),
	);

	/**
	 * The first visit and the visit of this order, as the checkout stored them.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return array{
	 *     present: bool,
	 *     renewal: bool,
	 *     first: array{landing: string, source: string, utm: array<string, string>, click: array<string, string>},
	 *     last: array{landing: string, source: string, utm: array<string, string>, click: array<string, string>},
	 *     browser_time: string
	 * }
	 */
	public static function visit( $type, $order_id ) {
		$data = self::meta( $type, $order_id, 'pys_enrich_data' );
		$data = is_array( $data ) ? $data : array();

		$raw_sources = array(
			isset( $data['pys_source'] ) ? (string) $data['pys_source'] : '',
			isset( $data['last_pys_source'] ) ? (string) $data['last_pys_source'] : '',
		);

		return array(
			'present'      => ! empty( $data ),
			'renewal'      => in_array( self::RENEWAL_PLACEHOLDER, $raw_sources, true ),
			'first'        => self::visitPart( $data, '' ),
			'last'         => self::visitPart( $data, 'last_' ),
			'browser_time' => isset( $data['pys_browser_time'] ) ? str_replace( '|', ', ', (string) $data['pys_browser_time'] ) : '',
		);
	}

	/**
	 * One visit out of the stored data.
	 *
	 * @param array  $data   pys_enrich_data
	 * @param string $prefix '' for the first visit, 'last_' for the visit of the order
	 * @return array{landing: string, source: string, utm: array<string, string>, click: array<string, string>}
	 */
	private static function visitPart( array $data, $prefix ) {
		$value = function ( $key ) use ( $data, $prefix ) {
			$stored = isset( $data[ $prefix . $key ] ) ? html_entity_decode( (string) $data[ $prefix . $key ], ENT_QUOTES ) : '';

			// The checkout script writes "undefined" when it has nothing.
			return in_array( $stored, array( 'undefined', self::RENEWAL_PLACEHOLDER ), true ) ? '' : $stored;
		};

		return array(
			'landing' => $value( 'pys_landing' ),
			'source'  => $value( 'pys_source' ),
			'utm'     => self::pairs( $value( 'pys_utm' ) ),
			'click'   => self::pairs( $value( 'pys_utm_id' ) ),
		);
	}

	/**
	 * "name:value|name:value" into an array, without the empty values.
	 *
	 * @param string $stored
	 * @return array<string, string>
	 */
	private static function pairs( $stored ) {
		$pairs = array();
		foreach ( explode( '|', (string) $stored ) as $pair ) {
			$parts = explode( ':', $pair, 2 );
			if ( 2 === count( $parts ) && ! in_array( $parts[1], array( '', 'undefined', self::RENEWAL_PLACEHOLDER ), true ) ) {
				// Stored as it was in the URL: "Catalog+CA" is "Catalog CA".
				$pairs[ $parts[0] ] = urldecode( $parts[1] );
			}
		}

		return $pairs;
	}

	/**
	 * The identifiers stored with the order, full values.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return array{fbp: string, fbc: string, ga_client_id: string, external_id: string, ip: string, user_agent: string}
	 */
	public static function identifiers( $type, $order_id ) {
		$facebook = self::meta( $type, $order_id, 'pys_fb_cookie' );
		$ga       = self::meta( $type, $order_id, 'pys_ga_cookie' );
		$signals  = self::meta( $type, $order_id, 'pys_client_signals' );
		$external = self::meta( $type, $order_id, 'external_id' );
		if ( is_array( $external ) ) {
			$external = reset( $external );
		}

		$field = function ( $source, $key ) {
			return is_array( $source ) && isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ? (string) $source[ $key ] : '';
		};

		return array(
			'fbp'          => $field( $facebook, 'fbp' ),
			'fbc'          => $field( $facebook, 'fbc' ),
			'ga_client_id' => $field( $ga, 'clientId' ),
			'external_id'  => is_scalar( $external ) ? (string) $external : '',
			'ip'           => $field( $signals, 'ip' ),
			'user_agent'   => $field( $signals, 'ua' ),
		);
	}

	/**
	 * Ad platforms the order's visit came from, by name, for the tags that are active now:
	 * Meta from the stored click (kept only while its Conversion API is on), Pinterest and
	 * Bing from the click parameter of the visit.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return string[]
	 */
	public static function adClickPlatforms( $type, $order_id ) {
		$platforms = array();

		$facebook = self::meta( $type, $order_id, 'pys_fb_cookie' );
		if ( is_array( $facebook ) && ! empty( $facebook['fbc'] ) && self::isTagActive( 'facebook' ) ) {
			$platforms[] = 'Meta';
		}

		$visit = self::visit( $type, $order_id );
		foreach ( array( 'first', 'last' ) as $part ) {
			foreach ( array_keys( $visit[ $part ]['click'] ) as $parameter ) {
				if ( isset( self::VISIT_CLICK_PLATFORMS[ $parameter ] ) && self::isTagActive( self::VISIT_CLICK_PLATFORMS[ $parameter ][0] ) ) {
					$platforms[] = self::VISIT_CLICK_PLATFORMS[ $parameter ][1];
				}
			}
		}

		return array_values( array_unique( $platforms ) );
	}

	/**
	 * Is a tag registered and switched on?
	 *
	 * @param string $slug
	 * @return bool
	 */
	private static function isTagActive( $slug ) {
		$pixel = PYS()->getRegisteredPixel( $slug );

		return $pixel && $pixel->enabled();
	}

	/**
	 * The registered customer of an order, from the store's own totals. A guest order has none.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return array{orders_count: int, total_spent: float}|null
	 */
	public static function customer( $type, $order_id ) {
		if ( 'woo' === $type ) {
			$order   = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null;
			$user_id = $order ? (int) $order->get_user_id() : 0;
			if ( $user_id <= 0 || ! function_exists( 'wc_get_customer_order_count' ) ) {
				return null;
			}
			$count = (int) wc_get_customer_order_count( $user_id );

			if ( $count <= 0 ) {
				return null;
			}

			return array(
				'orders_count' => $count,
				'total_spent'  => (float) wc_get_customer_total_spent( $user_id ),
			);
		}

		$order = function_exists( 'edd_get_order' ) ? edd_get_order( (int) $order_id ) : null;
		if ( ! $order || empty( $order->user_id ) || ! function_exists( 'edd_get_customer' ) ) {
			return null;
		}
		$customer = edd_get_customer( (int) $order->customer_id );
		if ( ! $customer || (int) $customer->purchase_count <= 0 ) {
			return null;
		}

		return array(
			'orders_count' => (int) $customer->purchase_count,
			'total_spent'  => (float) $customer->purchase_value,
		);
	}

	/**
	 * "source facebook · medium paid · campaign autumn" from the stored UTMs.
	 *
	 * @param array<string, string> $utm
	 * @param int                   $max_value_length Longest value to print; 0 for no limit
	 * @return string Empty when there is none
	 */
	public static function utmText( array $utm, $max_value_length = 0 ) {
		$order = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
		$parts = array();
		foreach ( array_merge( $order, array_diff( array_keys( $utm ), $order ) ) as $key ) {
			if ( isset( $utm[ $key ] ) && '' !== $utm[ $key ] ) {
				$value   = $max_value_length > 0 ? self::clip( $utm[ $key ], $max_value_length ) : $utm[ $key ];
				$parts[] = ( 0 === strpos( $key, 'utm_' ) ? substr( $key, 4 ) : $key ) . ' ' . $value;
			}
		}

		return implode( " \xC2\xB7 ", $parts );
	}

	/**
	 * A URL without its host: the store is the host.
	 *
	 * @param string $url
	 * @return string
	 */
	public static function path( $url ) {
		$parts = '' !== $url ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
			return (string) $url;
		}

		return $parts['path'] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
	}

	/**
	 * A landing page short enough for an email or a table cell: the path without
	 * its query string, cut in the middle when it is still long.
	 *
	 * @param string $url
	 * @param int    $max_length
	 * @return string
	 */
	public static function shortPath( $url, $max_length = 60 ) {
		$parts = '' !== (string) $url ? wp_parse_url( (string) $url ) : false;
		$path  = is_array( $parts ) && ! empty( $parts['path'] ) ? $parts['path'] : (string) $url;

		return self::clip( $path, $max_length );
	}

	/**
	 * A text cut in the middle to a maximum length.
	 *
	 * @param string $text
	 * @param int    $max_length
	 * @return string
	 */
	public static function clip( $text, $max_length = 60 ) {
		$text   = (string) $text;
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $length <= $max_length ) {
			return $text;
		}

		$head = (int) ceil( ( $max_length - 1 ) * 0.6 );
		$tail = $max_length - 1 - $head;
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $head ) . "\xE2\x80\xA6" . mb_substr( $text, -$tail );
		}

		return substr( $text, 0, $head ) . '...' . substr( $text, -$tail );
	}

	/**
	 * "1049000000003312" as "1049...3312". Short values stay as they are.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function shorten( $value ) {
		$value = (string) $value;

		return strlen( $value ) > 11 ? substr( $value, 0, 4 ) . '...' . substr( $value, -4 ) : $value;
	}

	/**
	 * One meta value of an order. EDD is read from the order meta directly:
	 * edd_get_payment_meta() builds a whole payment object per call.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @param string $key
	 * @return mixed '' when the order has no such meta
	 */
	public static function meta( $type, $order_id, $key ) {
		if ( 'woo' === $type ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null;

			return $order ? $order->get_meta( $key, true ) : '';
		}
		if ( ! function_exists( 'edd_get_order_meta' ) ) {
			return function_exists( 'edd_get_payment_meta' ) ? edd_get_payment_meta( (int) $order_id, $key, true ) : '';
		}
		if ( 'pys_enrich_data' === $key ) {
			// EDD 3 keeps the extra keys of the old payment meta array in "payment_meta".
			$payment_meta = edd_get_order_meta( (int) $order_id, 'payment_meta', true );

			return is_array( $payment_meta ) && isset( $payment_meta['pys_enrich_data'] ) ? $payment_meta['pys_enrich_data'] : '';
		}

		return edd_get_order_meta( (int) $order_id, $key, true );
	}
}
