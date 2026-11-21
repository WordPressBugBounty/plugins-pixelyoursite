<?php
/**
 * Purchase report of an order: whether the thank-you page sent its Purchase, and to which tags.
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Turns what Free knows about an order into one row per tag and one verdict.
 *
 * Free keeps no delivery record: it knows whether the thank-you page built the
 * Purchase, not whether a platform accepted it. A tag counts as sent when the
 * page was built and the tag is enabled now; "Pixel/API" means the API request
 * was made too, read from the current token or key.
 */
class PurchaseReport {

	const VERDICT_TRACKED      = 'tracked';
	const VERDICT_NOT_TRACKED  = 'not_tracked';
	const VERDICT_SKIPPED      = 'skipped';
	const VERDICT_NOT_PURCHASE = 'not_purchase';

	const STATE_SENT     = 'sent';
	const STATE_NOT_SENT = 'not_sent';
	const STATE_SKIPPED  = 'skipped';
	const STATE_NOT_DUE  = 'not_due';

	const CHANNEL_TAG_AND_API = 'tag_api';
	const CHANNEL_TAG         = 'tag';
	const CHANNEL_NONE        = 'none';

	/** Tags Free can name, in display order. Pinterest, Bing and Reddit come with their add-ons. */
	const TAG_PLATFORMS = array( 'facebook', 'ga', 'openai', 'pinterest', 'bing', 'reddit' );

	/** Tags that also send the Purchase by their API from the thank-you page. */
	const API_PLATFORMS = array( 'facebook', 'openai' );

	/** Meta key of the thank-you page flag. */
	const PAGE_FLAG = '_pys_purchase_event_fired';

	/**
	 * The purchase report of an order.
	 *
	 * @param string             $type        'woo' or 'edd'
	 * @param \WC_Order|int|null $order_or_id Woo order or id; EDD order id
	 * @return array{
	 *     type: string,
	 *     order_id: int,
	 *     verdict: string,
	 *     needs_attention: bool,
	 *     order: array{status: string, total: float, currency: string, created: int, created_in_admin: bool, is_refunded: bool, is_cancelled: bool},
	 *     platforms: array<string, array{label: string, pixels: string[], state: string, channel: string, note: string}>
	 * }|null Null when the order is unknown
	 */
	public static function forOrder( $type, $order_or_id ) {
		$order = self::orderFacts( $type, $order_or_id );
		if ( null === $order ) {
			return null;
		}

		$platforms = self::tagPlatforms( $type );
		$with_api  = array_values( array_filter( array_keys( $platforms ), array( __CLASS__, 'isApiConfigured' ) ) );

		return self::fromFacts( $type, $order, $platforms, $with_api );
	}

	/**
	 * The report, from the facts of the order and the tags enabled now.
	 *
	 * @param string                  $type      'woo' or 'edd'
	 * @param array                   $order     orderFacts() value
	 * @param array<string, string[]> $platforms tagPlatforms() value
	 * @param string[]                $with_api  Tags whose API is configured now
	 * @return array See forOrder()
	 */
	public static function fromFacts( $type, array $order, array $platforms, array $with_api = array() ) {
		$rows = array();
		foreach ( $platforms as $platform => $pixel_ids ) {
			$rows[ $platform ] = self::platformRow( $platform, $pixel_ids, $order, in_array( $platform, $with_api, true ) );
		}

		$verdict = self::verdict( $order );

		return array(
			'type'            => $type,
			'order_id'        => $order['id'],
			'verdict'         => $verdict,
			'needs_attention' => self::VERDICT_NOT_TRACKED === $verdict && ! $order['is_refunded'],
			'order'           => array(
				'status'           => $order['status'],
				'total'            => $order['total'],
				'currency'         => $order['currency'],
				'created'          => $order['created'],
				'created_in_admin' => $order['created_in_admin'],
				'is_refunded'      => $order['is_refunded'],
				'is_cancelled'     => $order['is_cancelled'],
			),
			'platforms'       => $rows,
		);
	}

	/**
	 * One tag's row.
	 *
	 * @param string   $platform  Pixel slug
	 * @param string[] $pixel_ids
	 * @param array    $order     orderFacts() value
	 * @param bool     $api_ready Is the tag's API configured now?
	 * @return array{label: string, pixels: string[], state: string, channel: string, note: string}
	 */
	private static function platformRow( $platform, array $pixel_ids, array $order, $api_ready ) {
		$row = array(
			'label'   => self::platformLabel( $platform ),
			'pixels'  => $pixel_ids,
			'state'   => self::STATE_NOT_SENT,
			'channel' => self::CHANNEL_NONE,
			'note'    => '',
		);

		if ( $order['page_built'] ) {
			$has_api        = in_array( $platform, self::API_PLATFORMS, true );
			$row['state']   = self::STATE_SENT;
			$row['channel'] = $has_api && $api_ready ? self::CHANNEL_TAG_AND_API : self::CHANNEL_TAG;
			$row['note']    = $has_api && self::CHANNEL_TAG === $row['channel'] ? 'api_not_configured' : '';

			return $row;
		}

		if ( $order['is_not_purchase'] ) {
			$row['state'] = self::STATE_NOT_DUE;
			$row['note']  = 'not_purchase';
		} elseif ( $order['is_refunded'] ) {
			$row['state'] = self::STATE_NOT_DUE;
			$row['note']  = 'refunded';
		} elseif ( $order['role_excluded'] ) {
			$row['state'] = self::STATE_SKIPPED;
			$row['note']  = 'user_role';
		} else {
			$row['note'] = 'no_thank_you_page';
		}

		return $row;
	}

	/**
	 * The order's verdict. The thank-you page sends one Purchase to every tag,
	 * so the verdict follows the page, not the rows.
	 *
	 * @param array $order orderFacts() value
	 * @return string One of the VERDICT_* constants
	 */
	private static function verdict( array $order ) {
		if ( $order['page_built'] ) {
			return self::VERDICT_TRACKED;
		}
		if ( $order['is_not_purchase'] ) {
			return self::VERDICT_NOT_PURCHASE;
		}
		if ( $order['role_excluded'] && ! $order['is_refunded'] ) {
			return self::VERDICT_SKIPPED;
		}

		return self::VERDICT_NOT_TRACKED;
	}

	/**
	 * What the report needs to know about the order itself.
	 *
	 * @param string             $type        'woo' or 'edd'
	 * @param \WC_Order|int|null $order_or_id
	 * @return array{object: mixed, id: int, status: string, total: float, currency: string, created: int, created_in_admin: bool, is_refunded: bool, is_cancelled: bool, is_not_purchase: bool, page_built: bool, role_excluded: bool}|null
	 */
	public static function orderFacts( $type, $order_or_id ) {
		if ( 'woo' === $type ) {
			$order = is_object( $order_or_id ) ? $order_or_id : ( function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_or_id ) : null );
			if ( ! $order || ! method_exists( $order, 'get_status' ) || ! method_exists( $order, 'get_order_key' ) ) {
				return null;
			}

			$status  = (string) $order->get_status();
			$created = $order->get_date_created();

			return array(
				'object'           => $order,
				'id'               => (int) $order->get_id(),
				'status'           => $status,
				'total'            => (float) $order->get_total(),
				'currency'         => (string) $order->get_currency(),
				'created'          => $created ? (int) $created->getTimestamp() : 0,
				'created_in_admin' => 'admin' === (string) $order->get_created_via(),
				'is_refunded'      => 'refunded' === $status,
				'is_cancelled'     => 'cancelled' === $status,
				'is_not_purchase'  => in_array( $status, array( 'cancelled', 'failed', 'pending', 'checkout-draft' ), true ),
				'page_built'       => (bool) $order->get_meta( self::PAGE_FLAG, true ),
				'role_excluded'    => self::isRoleExcluded( $order->get_user() ),
			);
		}

		if ( 'edd' !== $type || ! function_exists( 'edd_get_order' ) ) {
			return null;
		}

		$order = is_object( $order_or_id ) ? $order_or_id : edd_get_order( (int) $order_or_id );
		if ( ! $order || empty( $order->id ) || 'sale' !== $order->type ) {
			return null;
		}

		$status = (string) $order->status;

		return array(
			'object'           => $order,
			'id'               => (int) $order->id,
			'status'           => $status,
			'total'            => (float) $order->total,
			'currency'         => (string) $order->currency,
			'created'          => (int) strtotime( $order->date_created . ' UTC' ),
			// EDD keeps no trace of where an order was created (decision F7).
			'created_in_admin' => false,
			'is_refunded'      => in_array( $status, array( 'refunded', 'partially_refunded' ), true ),
			'is_cancelled'     => in_array( $status, array( 'revoked', 'cancelled' ), true ),
			'is_not_purchase'  => in_array( $status, array( 'abandoned', 'failed', 'revoked', 'cancelled', 'pending' ), true ),
			'page_built'       => self::eddPageBuilt( (int) $order->id ),
			'role_excluded'    => self::isRoleExcluded( ! empty( $order->user_id ) ? get_userdata( (int) $order->user_id ) : null ),
		);
	}

	/**
	 * Did the thank-you page build the Purchase of an EDD order? The flag is
	 * written with update_post_meta() on the order id, so both places are read.
	 *
	 * @param int $order_id
	 * @return bool
	 */
	private static function eddPageBuilt( $order_id ) {
		if ( function_exists( 'edd_get_order_meta' ) && edd_get_order_meta( $order_id, self::PAGE_FLAG, true ) ) {
			return true;
		}

		return (bool) get_post_meta( $order_id, self::PAGE_FLAG, true );
	}

	/**
	 * Is the customer's role excluded from tracking in the settings (read now)?
	 *
	 * @param \WP_User|false|null $user
	 * @return bool
	 */
	private static function isRoleExcluded( $user ) {
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return (bool) array_intersect( (array) $user->roles, (array) PYS()->getOption( 'do_not_track_user_roles' ) );
	}

	/**
	 * Tags whose Purchase tag the thank-you page prints now: registered, switched
	 * on, with pixel IDs and the store's Purchase event enabled.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return array<string, string[]> Pixel IDs by tag, in display order
	 */
	public static function tagPlatforms( $type ) {
		$platforms = array();

		foreach ( self::TAG_PLATFORMS as $slug ) {
			$pixel = PYS()->getRegisteredPixel( $slug );
			if ( ! $pixel || ! $pixel->enabled() ) {
				continue;
			}
			// Add-on tags without a Purchase switch of their own always send it.
			$key = "{$type}_purchase_enabled";
			if ( method_exists( $pixel, 'issetOption' ) && $pixel->issetOption( $key ) && ! $pixel->getOption( $key ) ) {
				continue;
			}

			$pixel_ids = array_values( array_filter( array_map( 'strval', (array) $pixel->getPixelIDs() ) ) );
			if ( ! empty( $pixel_ids ) ) {
				$platforms[ $slug ] = $pixel_ids;
			}
		}

		return $platforms;
	}

	/**
	 * Can the tag's API be used with the current settings?
	 *
	 * @param string $platform Pixel slug
	 * @return bool
	 */
	public static function isApiConfigured( $platform ) {
		$pixel = PYS()->getRegisteredPixel( $platform );

		return $pixel && method_exists( $pixel, 'isServerApiEnabled' ) && $pixel->isServerApiEnabled();
	}

	/**
	 * Display name of a tag.
	 *
	 * @param string $platform Pixel slug
	 * @return string
	 */
	public static function platformLabel( $platform ) {
		$labels = array(
			'facebook'  => 'Meta',
			'ga'        => 'GA4',
			'pinterest' => 'Pinterest',
			'openai'    => 'OpenAI',
			'bing'      => 'Bing',
			'reddit'    => 'Reddit',
		);

		return isset( $labels[ $platform ] ) ? $labels[ $platform ] : $platform;
	}

	/**
	 * Display name of a verdict.
	 *
	 * @param string $verdict One of the VERDICT_* constants
	 * @return string
	 */
	public static function verdictLabel( $verdict ) {
		$labels = array(
			self::VERDICT_TRACKED      => __( 'Tracked', 'pys' ),
			self::VERDICT_NOT_TRACKED  => __( 'Not tracked', 'pys' ),
			self::VERDICT_SKIPPED      => __( 'Blocked', 'pys' ),
			self::VERDICT_NOT_PURCHASE => __( 'Not a purchase', 'pys' ),
		);

		return isset( $labels[ $verdict ] ) ? $labels[ $verdict ] : $verdict;
	}

	/**
	 * Display name of a row state.
	 *
	 * @param string $state One of the STATE_* constants
	 * @return string
	 */
	public static function stateLabel( $state ) {
		$labels = array(
			self::STATE_SENT     => __( 'Sent', 'pys' ),
			self::STATE_NOT_SENT => __( 'Not sent', 'pys' ),
			self::STATE_SKIPPED  => __( 'Blocked', 'pys' ),
			self::STATE_NOT_DUE  => __( 'Not sent', 'pys' ),
		);

		return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
	}

	/**
	 * Display name of the channel a Purchase was sent by.
	 *
	 * @param string $channel One of the CHANNEL_* constants
	 * @return string
	 */
	public static function channelLabel( $channel ) {
		$labels = array(
			self::CHANNEL_TAG_AND_API => __( 'Pixel/API', 'pys' ),
			self::CHANNEL_TAG         => __( 'Pixel', 'pys' ),
			self::CHANNEL_NONE        => "\xE2\x80\x94",
		);

		return isset( $labels[ $channel ] ) ? $labels[ $channel ] : $channel;
	}

	/**
	 * The sentence fragment that explains a row's note.
	 *
	 * @param string $note Note code of a row
	 * @return string Empty for an unknown or empty code
	 */
	public static function noteLabel( $note ) {
		$labels = array(
			'api_not_configured' => __( 'API not configured', 'pys' ),
			'no_thank_you_page'  => __( 'no thank-you page', 'pys' ),
			'user_role'          => __( 'tracking is disabled for the customer\'s user role', 'pys' ),
			'not_purchase'       => __( 'the order is not a purchase', 'pys' ),
			'refunded'           => __( 'the order was refunded', 'pys' ),
		);

		return isset( $labels[ $note ] ) ? $labels[ $note ] : '';
	}

	/**
	 * One sentence that says what happened to the order's Purchase.
	 *
	 * @param array $report forOrder() value
	 * @return string
	 */
	public static function summarySentence( array $report ) {
		$sent = array();
		foreach ( $report['platforms'] as $row ) {
			if ( self::STATE_SENT === $row['state'] ) {
				$sent[] = $row['label'];
			}
		}

		switch ( $report['verdict'] ) {
			case self::VERDICT_TRACKED:
				$sentence = empty( $sent )
					? __( 'The thank-you page sent the Purchase.', 'pys' )
					/* translators: %s: list of tags, e.g. "Meta, GA4 and OpenAI" */
					: sprintf( __( 'The thank-you page sent this Purchase to %s.', 'pys' ), self::joinList( $sent ) );

				return $report['order']['is_refunded'] ? $sentence . ' ' . __( 'The order was refunded later.', 'pys' ) : $sentence;
			case self::VERDICT_SKIPPED:
				/* translators: %s: reason */
				return sprintf( __( 'PixelYourSite did not send this Purchase: %s.', 'pys' ), self::noteLabel( 'user_role' ) );
			case self::VERDICT_NOT_PURCHASE:
				return __( 'This order is not a purchase: nothing was sent.', 'pys' );
			default:
				if ( $report['order']['is_refunded'] ) {
					return __( 'No Purchase was sent for this order before it was refunded.', 'pys' );
				}
				if ( $report['order']['created_in_admin'] ) {
					return __( 'This order was created in wp-admin: no checkout and no thank-you page, so no Purchase was sent.', 'pys' );
				}

				return __( 'The thank-you page was never loaded, so no Purchase was sent.', 'pys' );
		}
	}

	/**
	 * Join items into a readable list.
	 *
	 * @param string[] $items
	 * @return string "a", "a and b", "a, b and c"
	 */
	public static function joinList( array $items ) {
		$items = array_values( array_filter( $items ) );
		if ( count( $items ) < 2 ) {
			return implode( '', $items );
		}
		$last = array_pop( $items );

		/* translators: 1: comma-separated list, 2: the last item */
		return sprintf( __( '%1$s and %2$s', 'pys' ), implode( ', ', $items ), $last );
	}
}
