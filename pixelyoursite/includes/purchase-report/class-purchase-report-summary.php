<?php
/**
 * Numbers about a set of orders, from the cache rows of the purchase report.
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Counts for the summary box of the orders list, the dashboard widget and
 * the summary email. Nothing is estimated: an amount is given only when every
 * order shares one currency.
 */
class PurchaseReportSummary {

	/** Tags that have an API in Pro, named by the Pro line. */
	const PRO_API_PLATFORMS = array( 'facebook', 'ga', 'pinterest', 'openai' );

	/**
	 * The summary of a period of a store.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @param int    $from Timestamp, inclusive
	 * @param int    $to   Timestamp, exclusive
	 * @return array See fromRows()
	 */
	public static function forPeriod( $type, $from, $to ) {
		return self::fromRows( PurchaseReportCache::rows( $type, $from, $to ) );
	}

	/**
	 * Counts over a set of orders.
	 *
	 * @param array $rows PurchaseReportCache::rows() value
	 * @return array{
	 *     total: int,
	 *     tracked: int,
	 *     attention: int,
	 *     attention_checkout: int,
	 *     attention_admin: int,
	 *     by_verdict: array<string, int>,
	 *     not_counted: int,
	 *     currency: string|null,
	 *     value_total: float|null,
	 *     value_tracked: float|null,
	 *     value_not_tracked: float|null,
	 *     platforms: array<string, array{label: string, sent: int, api: int, api_not_configured: int}>,
	 *     ad_clicks: array{orders: int, by_platform: array<string, int>}
	 * }
	 */
	public static function fromRows( array $rows ) {
		$summary    = array(
			'total'              => 0,
			'tracked'            => 0,
			'attention'          => 0,
			'attention_checkout' => 0,
			'attention_admin'    => 0,
			'by_verdict'         => array_fill_keys(
				array(
					PurchaseReport::VERDICT_TRACKED,
					PurchaseReport::VERDICT_NOT_TRACKED,
					PurchaseReport::VERDICT_SKIPPED,
				),
				0
			),
			'not_counted'        => 0,
			'currency'           => null,
			'value_total'        => 0.0,
			'value_tracked'      => 0.0,
			'value_not_tracked'  => 0.0,
			'platforms'          => array(),
			'pixel_and_api'      => 0,
			'ad_clicks'          => array(
				'orders'      => 0,
				'by_platform' => array(),
			),
		);
		$currencies = array();

		foreach ( $rows as $row ) {
			// Cancelled or failed orders are not purchases to count.
			if ( ! isset( $summary['by_verdict'][ $row['verdict'] ] ) ) {
				++$summary['not_counted'];
				continue;
			}

			++$summary['total'];
			++$summary['by_verdict'][ $row['verdict'] ];
			$summary['value_total'] += $row['total'];
			if ( '' !== $row['currency'] ) {
				$currencies[ $row['currency'] ] = true;
			}
			if ( PurchaseReport::VERDICT_TRACKED === $row['verdict'] ) {
				++$summary['tracked'];
				$summary['value_tracked'] += $row['total'];
			}
			if ( PurchaseReport::VERDICT_NOT_TRACKED === $row['verdict'] ) {
				$summary['value_not_tracked'] += $row['total'];
			}
			if ( $row['attention'] ) {
				++$summary['attention'];
				if ( ! empty( $row['created_in_admin'] ) ) {
					++$summary['attention_admin'];
				} else {
					++$summary['attention_checkout'];
				}
			}

			$by_api = false;
			foreach ( $row['platforms'] as $platform => $cell ) {
				list( $state, $channel, $note ) = array_pad( (array) $cell, 3, '' );

				if ( ! isset( $summary['platforms'][ $platform ] ) ) {
					$summary['platforms'][ $platform ] = array(
						'label'              => PurchaseReport::platformLabel( $platform ),
						'sent'               => 0,
						'api'                => 0,
						'api_not_configured' => 0,
					);
				}
				if ( PurchaseReport::STATE_SENT === $state ) {
					++$summary['platforms'][ $platform ]['sent'];
				}
				if ( PurchaseReport::CHANNEL_TAG_AND_API === $channel ) {
					++$summary['platforms'][ $platform ]['api'];
					$by_api = true;
				}
				if ( 'api_not_configured' === $note && PurchaseReport::STATE_SENT === $state ) {
					++$summary['platforms'][ $platform ]['api_not_configured'];
				}
			}
			if ( $by_api ) {
				++$summary['pixel_and_api'];
			}

			if ( ! empty( $row['ad_click'] ) ) {
				++$summary['ad_clicks']['orders'];
				foreach ( $row['ad_click'] as $name ) {
					if ( ! isset( $summary['ad_clicks']['by_platform'][ $name ] ) ) {
						$summary['ad_clicks']['by_platform'][ $name ] = 0;
					}
					++$summary['ad_clicks']['by_platform'][ $name ];
				}
			}
		}

		if ( 1 === count( $currencies ) ) {
			$summary['currency'] = (string) key( $currencies );
		} else {
			$summary['value_total']       = null;
			$summary['value_tracked']     = null;
			$summary['value_not_tracked'] = null;
		}

		$ordered = array();
		foreach ( PurchaseReport::TAG_PLATFORMS as $platform ) {
			if ( isset( $summary['platforms'][ $platform ] ) ) {
				$ordered[ $platform ] = $summary['platforms'][ $platform ];
			}
		}
		$summary['platforms'] = $ordered;

		return $summary;
	}

	/**
	 * Share of tracked purchases, 0 to 100.
	 *
	 * @param array $summary fromRows() value
	 * @return int
	 */
	public static function trackedPercent( array $summary ) {
		return $summary['total'] > 0 ? (int) round( 100 * $summary['tracked'] / $summary['total'] ) : 0;
	}

	/**
	 * Lines for "Needs attention": by reason, as the thank-you page sends one
	 * Purchase to every tag.
	 *
	 * @param array  $summary fromRows() value
	 * @param string $type    'woo' or 'edd'; '' leaves out the settings line
	 * @return string[]
	 */
	public static function attentionLines( array $summary, $type = '' ) {
		$lines = array();

		if ( $summary['attention_checkout'] > 0 ) {
			$lines[] = sprintf(
				/* translators: %d: number of orders */
				_n(
					'%d order: the thank-you page was never loaded, so no Purchase was sent.',
					'%d orders: the thank-you page was never loaded, so no Purchase was sent.',
					$summary['attention_checkout'],
					'pys'
				),
				$summary['attention_checkout']
			);
		}
		if ( $summary['attention_admin'] > 0 ) {
			$lines[] = sprintf(
				/* translators: %d: number of orders */
				_n(
					'%d order created in wp-admin: no checkout and no thank-you page, so no Purchase was sent.',
					'%d orders created in wp-admin: no checkout and no thank-you page, so no Purchase was sent.',
					$summary['attention_admin'],
					'pys'
				),
				$summary['attention_admin']
			);
		}
		foreach ( $summary['platforms'] as $counts ) {
			if ( $counts['api_not_configured'] > 0 ) {
				$lines[] = sprintf(
					/* translators: 1: number of orders, 2: platform name */
					_n(
						'%1$d order reached %2$s by the tag only: its API is not configured.',
						'%1$d orders reached %2$s by the tag only: its API is not configured.',
						$counts['api_not_configured'],
						'pys'
					),
					$counts['api_not_configured'],
					$counts['label']
				);
			}
		}
		if ( '' !== $type && self::isMetaApiOff( $type ) ) {
			$lines[] = __( 'The Meta Conversion API is off. Turn it on to keep the ad click on each order.', 'pys' );
		}

		return $lines;
	}

	/**
	 * Is Meta sending the store's Purchase with its Conversion API switch off?
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return bool
	 */
	public static function isMetaApiOff( $type ) {
		if ( ! isset( PurchaseReport::tagPlatforms( $type )['facebook'] ) ) {
			return false;
		}
		$pixel = PYS()->getRegisteredPixel( 'facebook' );

		return $pixel && ! $pixel->getOption( 'use_server_api' );
	}

	/**
	 * Lines for "What PixelYourSite did".
	 *
	 * @param array $summary fromRows() value
	 * @return string[]
	 */
	public static function activityLines( array $summary ) {
		$lines = array();

		if ( $summary['tracked'] > 0 ) {
			$lines[] = sprintf(
				/* translators: %d: number of orders */
				_n( '%d purchase sent by the pixel, from the thank-you page.', '%d purchases sent by the pixel, from the thank-you page.', $summary['tracked'], 'pys' ),
				$summary['tracked']
			);
		}

		if ( $summary['pixel_and_api'] > 0 ) {
			$apis = array();
			foreach ( $summary['platforms'] as $platform => $counts ) {
				if ( $counts['api'] > 0 ) {
					$apis[] = 'facebook' === $platform ? __( 'Meta Conversion API', 'pys' ) : __( 'OpenAI API', 'pys' );
				}
			}
			$lines[] = sprintf(
				/* translators: 1: number of orders, 2: "Meta Conversion API", "OpenAI API" or both */
				_n(
					'%1$d of them also sent by the %2$s, with the same event id, so the platform counts it once.',
					'%1$d of them also sent by the %2$s, with the same event id, so the platforms count each once.',
					$summary['pixel_and_api'],
					'pys'
				),
				$summary['pixel_and_api'],
				PurchaseReport::joinList( $apis )
			);
		}

		if ( $summary['ad_clicks']['orders'] > 0 ) {
			$platforms = array();
			foreach ( $summary['ad_clicks']['by_platform'] as $name => $count ) {
				$platforms[] = $name . ' ' . $count;
			}
			$lines[] = sprintf(
				/* translators: 1: number of orders, 2: list such as "Meta 4 · Pinterest 2" */
				_n( '%1$d purchase came from an ad click (%2$s).', '%1$d purchases came from an ad click (%2$s).', $summary['ad_clicks']['orders'], 'pys' ),
				$summary['ad_clicks']['orders'],
				implode( " \xC2\xB7 ", $platforms )
			);
		}

		$blocked = $summary['by_verdict'][ PurchaseReport::VERDICT_SKIPPED ];
		if ( $blocked > 0 ) {
			$lines[] = sprintf(
				/* translators: 1: number of orders, 2: reason */
				_n( '%1$d order not sent on purpose: %2$s.', '%1$d orders not sent on purpose: %2$s.', $blocked, 'pys' ),
				$blocked,
				PurchaseReport::noteLabel( 'user_role' )
			);
		}

		return $lines;
	}

	/**
	 * The one sentence that suggests Pro: the purchases that were not tracked.
	 *
	 * @param string   $type    'woo' or 'edd'
	 * @param array    $summary fromRows() value
	 * @param callable $money   function ( float $amount, string $currency ): string
	 * @return string Empty when every purchase was tracked
	 */
	public static function proLine( $type, array $summary, $money ) {
		$count = $summary['by_verdict'][ PurchaseReport::VERDICT_NOT_TRACKED ];
		if ( $count <= 0 ) {
			return '';
		}

		$names = array();
		foreach ( array_keys( PurchaseReport::tagPlatforms( $type ) ) as $platform ) {
			if ( in_array( $platform, self::PRO_API_PLATFORMS, true ) ) {
				$names[] = PurchaseReport::platformLabel( $platform );
			}
		}
		$platforms = ! empty( $names ) ? PurchaseReport::joinList( $names ) : __( 'the platforms', 'pys' );

		// Mixed currencies leave the amount out, brackets included.
		$amount = null !== $summary['value_not_tracked'] ? (string) call_user_func( $money, $summary['value_not_tracked'], $summary['currency'] ) : '';

		if ( 1 === $count ) {
			/* translators: 1: amount, 2: list of platforms */
			$line = __( 'This purchase (%1$s) never reached %2$s. PixelYourSite Pro recovers orders like this one: Advanced Purchase Tracking sends the Purchase from the server when the order status changes, for the tags with an API.', 'pys' );

			return '' !== $amount ? sprintf( $line, $amount, $platforms ) : sprintf( str_replace( ' (%1$s)', '', $line ), '', $platforms );
		}

		/* translators: 1: number of purchases, 2: amount, 3: list of platforms */
		$line = __( 'These %1$d purchases (%2$s) never reached %3$s. PixelYourSite Pro recovers orders like these: Advanced Purchase Tracking sends the Purchase from the server when the order status changes, for the tags with an API.', 'pys' );

		return '' !== $amount ? sprintf( $line, $count, $amount, $platforms ) : sprintf( str_replace( ' (%2$s)', '', $line ), $count, '', $platforms );
	}

	/**
	 * The Pro sentence of the admin email of an order created in wp-admin.
	 *
	 * @return string
	 */
	public static function proLineAdminOrder() {
		return __( 'If you create orders here regularly, for phone or in-store sales, PixelYourSite Pro can track them: Advanced Purchase Tracking sends the Purchase from the server when the order status changes, with the billing data for matching.', 'pys' );
	}
}
