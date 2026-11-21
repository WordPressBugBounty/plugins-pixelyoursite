<?php
/**
 * The purchase report in the admin emails: the block added to the store's
 * new-order notification, and the weekly or monthly summary.
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin emails only: nothing here is ever added to an email a customer gets.
 */
class PurchaseReportEmail {

	const SUMMARY_HOOK = 'pys_purchase_report_summary';
	const TEST_ACTION  = 'pys_purchase_summary_test';

	/**
	 * Hook the order email blocks and the summary email.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_email_customer_details', array( __CLASS__, 'renderWooOrderBlock' ), 80, 4 );
		add_filter( 'edd_admin_order_notification', array( __CLASS__, 'addEddOrderBlock' ), 20, 2 );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
		add_action( self::SUMMARY_HOOK, array( __CLASS__, 'maybeSendSummary' ) );
		add_action( 'wp_ajax_' . self::TEST_ACTION, array( __CLASS__, 'ajaxSendTest' ) );
	}

	/**
	 * Schedule the daily check that sends the summary email.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ( is_admin() || wp_doing_cron() ) && ! wp_next_scheduled( self::SUMMARY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::SUMMARY_HOOK );
		}
	}

	/**
	 * Remove the summary email schedule (plugin deactivation).
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::SUMMARY_HOOK );
	}

	/**
	 * What the new-order block shows: the visit, the ad click and the customer.
	 * The email leaves before the thank-you page has run, so it says where the
	 * Purchase will be shown rather than how it went.
	 *
	 * @param string $type     'woo' or 'edd'
	 * @param int    $order_id
	 * @return array
	 */
	public static function orderBlockData( $type, $order_id ) {
		$customer = PurchaseReportOrderData::customer( $type, $order_id );
		$facts    = PurchaseReport::orderFacts( $type, $order_id );

		if ( 'woo' === $type ) {
			$order  = wc_get_order( $order_id );
			$number = $order ? $order->get_order_number() : (string) $order_id;
		} else {
			$number = (string) $order_id;
			if ( function_exists( 'edd_get_order' ) ) {
				$order  = edd_get_order( $order_id );
				$number = $order && method_exists( $order, 'get_number' ) ? (string) $order->get_number() : $number;
			}
		}

		return array(
			'type'             => $type,
			'visit'            => PurchaseReportOrderData::visit( $type, $order_id ),
			'ad_click'         => PurchaseReportOrderData::adClickPlatforms( $type, $order_id ),
			'customer'         => null !== $customer ? sprintf(
				/* translators: 1: number of orders, 2: lifetime value */
				_n( '%1$s order, lifetime value %2$s', '%1$s orders, lifetime value %2$s', $customer['orders_count'], 'pys' ),
				number_format_i18n( $customer['orders_count'] ),
				PurchaseReportAdmin::money( $type, $customer['total_spent'], null !== $facts ? $facts['currency'] : '' )
			) : '',
			'created_in_admin' => null !== $facts && $facts['created_in_admin'],
			'order_url'        => PurchaseReportAdmin::orderUrl( $type, $order_id ),
			'order_number'     => $number,
			'settings_url'     => PurchaseReportAdmin::settingsUrl( $type ),
			'store_name'       => PurchaseReportAdmin::storeName( $type ),
		);
	}

	/**
	 * WooCommerce "New Order" email, admin copy only.
	 *
	 * @param \WC_Order      $order
	 * @param bool           $sent_to_admin
	 * @param bool           $plain_text
	 * @param \WC_Email|null $email Cancelled and failed order emails go to the admin too
	 * @return void
	 */
	public static function renderWooOrderBlock( $order, $sent_to_admin, $plain_text = false, $email = null ) {
		if ( ! $sent_to_admin || ! $order instanceof \WC_Order ) {
			return;
		}
		if ( ! is_object( $email ) || ! isset( $email->id ) || 'new_order' !== $email->id ) {
			return;
		}
		if ( ! PYS()->getOption( 'woo_enabled_save_data_to_orders' ) || ! PYS()->getOption( 'woo_add_enrich_to_admin_email' ) ) {
			return;
		}

		$data          = self::orderBlockData( 'woo', (int) $order->get_id() );
		$data['plain'] = (bool) $plain_text;
		PurchaseReportAdmin::view( 'email-order-block', $data );
	}

	/**
	 * EDD admin sale notification.
	 *
	 * @param string     $message
	 * @param object|int $order   EDD order (EDD 3.2 and later) or payment id
	 * @return string
	 */
	public static function addEddOrderBlock( $message, $order ) {
		if ( ! PYS()->getOption( 'edd_enabled_save_data_to_orders' ) || ! PYS()->getOption( 'edd_add_enrich_to_admin_email' ) ) {
			return $message;
		}

		if ( is_object( $order ) ) {
			$order_id = isset( $order->id ) ? (int) $order->id : ( isset( $order->ID ) ? (int) $order->ID : 0 );
		} else {
			$order_id = (int) $order;
		}
		if ( $order_id <= 0 ) {
			return $message;
		}

		$data          = self::orderBlockData( 'edd', $order_id );
		$data['plain'] = false;

		return $message . PurchaseReportAdmin::viewHtml( 'email-order-block', $data );
	}

	/**
	 * 'week' or 'month'.
	 *
	 * @return string
	 */
	public static function period() {
		return 'month' === PYS()->getOption( 'purchase_summary_email_period' ) ? 'month' : 'week';
	}

	/**
	 * Address the summary email goes to.
	 *
	 * @return string
	 */
	public static function recipient() {
		$address = sanitize_email( (string) PYS()->getOption( 'purchase_summary_email_address' ) );

		return is_email( $address ) ? $address : (string) get_option( 'admin_email' );
	}

	/**
	 * The period a summary sent now reports on, in the site's time zone: the
	 * last full week (Monday to Sunday), or the 30 days before the sending day,
	 * as Free keeps 30 days of data.
	 *
	 * @param string   $period 'week' or 'month'
	 * @param int|null $now    Timestamp; null for the current time
	 * @return array{key: string, from: int, to: int, previous_from: int|null, label: string}
	 */
	public static function periodBounds( $period, $now = null ) {
		$today = ( new \DateTimeImmutable( '@' . ( null === $now ? time() : (int) $now ) ) )->setTimezone( wp_timezone() )->setTime( 0, 0 );

		if ( 'month' === $period ) {
			$to       = $today;
			$from     = $to->modify( '-' . PurchaseReportCache::WINDOW_DAYS . ' days' );
			$previous = null;
		} else {
			$to       = $today->modify( 'monday this week' );
			$from     = $to->modify( '-7 days' );
			$previous = $from->modify( '-7 days' );
		}

		return array(
			'key'           => $period . ':' . $to->format( 'Y-m-d' ),
			'from'          => $from->getTimestamp(),
			'to'            => $to->getTimestamp(),
			// The month before is outside the 30 days Free keeps: only the week compares.
			'previous_from' => null !== $previous ? $previous->getTimestamp() : null,
			'label'         => sprintf(
				/* translators: 1: first day, 2: last day of the period */
				__( '%1$s to %2$s', 'pys' ),
				wp_date( get_option( 'date_format' ), $from->getTimestamp() ),
				wp_date( get_option( 'date_format' ), $to->getTimestamp() - 1 )
			),
		);
	}

	/**
	 * Is today a sending day? Weekly: Monday. Monthly: the first Monday.
	 *
	 * @param string   $period 'week' or 'month'
	 * @param int|null $now
	 * @return bool
	 */
	public static function isSendingDay( $period, $now = null ) {
		$today = ( new \DateTimeImmutable( '@' . ( null === $now ? time() : (int) $now ) ) )->setTimezone( wp_timezone() );
		if ( '1' !== $today->format( 'N' ) ) {
			return false;
		}

		return 'week' === $period || (int) $today->format( 'j' ) <= 7;
	}

	/**
	 * WP-Cron, daily: send the summary once per period.
	 *
	 * @return void
	 */
	public static function maybeSendSummary() {
		if ( ! PYS()->getOption( 'purchase_summary_email' ) ) {
			return;
		}

		$period = self::period();
		$bounds = self::periodBounds( $period );
		if ( ! self::isSendingDay( $period ) || PurchaseReportState::get( 'summary_sent' ) === $bounds['key'] ) {
			return;
		}

		PurchaseReportState::set( array( 'summary_sent' => $bounds['key'] ) );
		self::sendSummary( self::recipient(), $period );
	}

	/**
	 * The subject and body of the summary of a store.
	 *
	 * @param string $type    'woo' or 'edd'
	 * @param string $period  'week' or 'month'
	 * @param bool   $is_test
	 * @param int    $now     Timestamp; null for the current time
	 * @return array{subject: string, body: string, total: int}
	 */
	public static function summaryEmail( $type, $period, $is_test = false, $now = null ) {
		$bounds   = self::periodBounds( $period, $now );
		$summary  = PurchaseReportSummary::forPeriod( $type, $bounds['from'], $bounds['to'] );
		$previous = null;
		if ( null !== $bounds['previous_from'] ) {
			$previous = PurchaseReportSummary::forPeriod( $type, $bounds['previous_from'], $bounds['from'] );
			$previous = $previous['total'] > 0 ? $previous : null;
		}
		$money   = PurchaseReportAdmin::moneyFormatter( $type );
		$percent = PurchaseReportSummary::trackedPercent( $summary );
		$subject = sprintf(
			/* translators: 1: percentage, 2: store plugin name, 3: site name */
			__( '%1$d%% of %2$s purchases tracked on %3$s', 'pys' ),
			$percent,
			'edd' === $type ? 'EDD' : 'WooCommerce',
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		if ( $is_test ) {
			/* translators: %s: email subject */
			$subject = sprintf( __( '[Test] %s', 'pys' ), $subject );
		}

		$body = PurchaseReportAdmin::viewHtml(
			'email-summary',
			array(
				'type'          => $type,
				'summary'       => $summary,
				'previous'      => $previous,
				'percent'       => $percent,
				'period'        => $period,
				'period_label'  => $bounds['label'],
				'is_test'       => $is_test,
				'value_tracked' => null !== $summary['value_tracked'] ? $money( $summary['value_tracked'], $summary['currency'] ) : '',
				'value_total'   => null !== $summary['value_total'] ? $money( $summary['value_total'], $summary['currency'] ) : '',
				'attention'     => PurchaseReportSummary::attentionLines( $summary, $type ),
				'activity'      => PurchaseReportSummary::activityLines( $summary ),
				'pro_line'      => PurchaseReportSummary::proLine( $type, $summary, $money ),
				'attention_url' => add_query_arg( PurchaseReportAdmin::FILTER_ARG, 'attention', PurchaseReportAdmin::ordersUrl( $type ) ),
				'settings_url'  => PurchaseReportAdmin::settingsUrl( $type ),
				'show_review'   => ! PurchaseReportState::get( 'review_dismissed' )
					&& $summary['total'] >= PurchaseReportAdmin::REVIEW_MIN
					&& $percent >= PurchaseReportAdmin::REVIEW_PERCENT,
			)
		);

		return array(
			'subject' => $subject,
			'body'    => $body,
			'total'   => $summary['total'],
		);
	}

	/**
	 * Send the summary of each active store that had purchases.
	 *
	 * @param string $recipient
	 * @param string $period    'week' or 'month'
	 * @param bool   $is_test   A test goes out even without purchases
	 * @return int Emails sent
	 */
	public static function sendSummary( $recipient, $period, $is_test = false ) {
		$sent = 0;

		foreach ( array( 'woo', 'edd' ) as $type ) {
			if ( 'woo' === $type ? ! isWooCommerceActive() : ! isEddActive() ) {
				continue;
			}

			$email = self::summaryEmail( $type, $period, $is_test );
			if ( 0 === $email['total'] && ! $is_test ) {
				continue;
			}
			if ( wp_mail( $recipient, $email['subject'], $email['body'], self::headers( $type ) ) ) {
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * HTML email, from the store's own sender when WooCommerce has one.
	 *
	 * @param string $type 'woo' or 'edd'
	 * @return string[]
	 */
	private static function headers( $type ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$address = 'woo' === $type ? sanitize_email( (string) get_option( 'woocommerce_email_from_address' ) ) : '';
		if ( is_email( $address ) ) {
			$name      = wp_specialchars_decode( (string) get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) ), ENT_QUOTES );
			$headers[] = sprintf( 'From: %1$s <%2$s>', str_replace( array( '<', '>', "\r", "\n" ), '', $name ), $address );
		}

		return $headers;
	}

	/**
	 * AJAX: send a test summary to the user who asked for it.
	 *
	 * @return void
	 */
	public static function ajaxSendTest() {
		check_ajax_referer( self::TEST_ACTION );
		if ( ! current_user_can( 'manage_pys' ) || ! current_user_can( PurchaseReportAdmin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'pys' ) ), 403 );
		}

		$recipient = (string) wp_get_current_user()->user_email;
		$period    = isset( $_POST['period'] ) && 'month' === $_POST['period'] ? 'month' : 'week';
		$sent      = self::sendSummary( $recipient, $period, true );

		if ( $sent > 0 ) {
			/* translators: %s: email address */
			wp_send_json_success( array( 'message' => sprintf( __( 'Sent to %s.', 'pys' ), $recipient ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Could not send. Check that this site can send email.', 'pys' ) ) );
	}

	/**
	 * "Next summary: Monday, 16 November, to name@example.com."
	 *
	 * @return string
	 */
	public static function scheduleLine() {
		if ( ! PYS()->getOption( 'purchase_summary_email' ) ) {
			return __( 'The summary email is off.', 'pys' );
		}

		$period = self::period();
		$day    = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->setTime( 0, 0 );
		for ( $i = 0; $i < 40; $i++ ) {
			$day = $day->modify( '+1 day' );
			if ( self::isSendingDay( $period, $day->getTimestamp() + HOUR_IN_SECONDS ) ) {
				break;
			}
		}

		return sprintf(
			/* translators: 1: date, 2: email address */
			__( 'Next summary: %1$s, to %2$s.', 'pys' ),
			wp_date( 'l, j F', $day->getTimestamp() ),
			self::recipient()
		);
	}
}
