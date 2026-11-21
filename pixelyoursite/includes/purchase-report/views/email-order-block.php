<?php
/**
 * The PixelYourSite block of the admin new-order email. No CSS: email
 * clients strip it.
 *
 * @package PixelYourSite
 *
 * @var string   $type             'woo' or 'edd'
 * @var array    $visit            PurchaseReportOrderData::visit() value
 * @var string[] $ad_click
 * @var string   $customer         Customer line, or ''
 * @var bool     $created_in_admin
 * @var string   $order_url
 * @var string   $order_number
 * @var string   $settings_url
 * @var string   $store_name
 * @var bool     $plain            Plain-text email
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$lines = array();
if ( $visit['present'] && ! $visit['renewal'] ) {
	$with_first = function ( $last, $first ) {
		$text = '' !== $last ? $last : __( 'none', 'pys' );
		if ( '' !== $first && $first !== $last ) {
			/* translators: %s: value of the first visit */
			$text .= ' ' . sprintf( __( '(first visit: %s)', 'pys' ), $first );
		}

		return $text;
	};

	$lines[ __( 'Landing page', 'pys' ) ]   = $with_first( PurchaseReportOrderData::shortPath( $visit['last']['landing'] ), PurchaseReportOrderData::shortPath( $visit['first']['landing'] ) );
	$lines[ __( 'Traffic source', 'pys' ) ] = $with_first( PurchaseReportOrderData::clip( $visit['last']['source'], 40 ), PurchaseReportOrderData::clip( $visit['first']['source'], 40 ) );
	$lines[ __( 'Campaign (UTM)', 'pys' ) ] = $with_first( PurchaseReportOrderData::utmText( $visit['last']['utm'], 40 ), PurchaseReportOrderData::utmText( $visit['first']['utm'], 40 ) );
}
$lines[ __( 'Ad click', 'pys' ) ] = ! empty( $ad_click ) ? implode( ', ', $ad_click ) : __( 'none recorded', 'pys' );
if ( '' !== $visit['browser_time'] ) {
	$lines[ __( 'Browser time', 'pys' ) ] = $visit['browser_time'];
}
if ( '' !== $customer ) {
	$lines[ __( 'Customer', 'pys' ) ] = $customer;
}

/* translators: %s: order number */
$open = sprintf( __( 'Open order #%s', 'pys' ), $order_number );
$pro  = $created_in_admin ? PurchaseReportSummary::proLineAdminOrder() : '';
$when = $created_in_admin ? '' : __( 'The Purchase for this order is sent from the thank-you page, usually seconds after this email. Open the order to see whether it was sent and to which tags:', 'pys' );
/* translators: %s: "WooCommerce" or "Easy Digital Downloads" */
$control = sprintf( __( 'Your clients don\'t see this block. Control it on the %s settings page, under Native Data Tracking and Reporting.', 'pys' ), $store_name );

if ( $plain ) {
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text email: tags are stripped, entities would show as text.
	echo "\nPixelYourSite\n";
	foreach ( $lines as $label => $value ) {
		echo wp_strip_all_tags( $label . ': ' . $value ) . "\n";
	}
	echo "\n";
	if ( '' !== $when ) {
		echo wp_strip_all_tags( $when ) . "\n" . wp_strip_all_tags( $open . ': ' . $order_url ) . "\n";
	}
	if ( '' !== $pro ) {
		echo wp_strip_all_tags( $pro ) . "\n";
	}
	echo wp_strip_all_tags( $control ) . ' ' . wp_strip_all_tags( __( 'Open the settings', 'pys' ) . ': ' . $settings_url ) . "\n";
	// phpcs:enable

	return;
}
// Inline style: email clients drop style sheets, and a long value must wrap inside the email, never widen it.
?>
<div style="word-break:break-word;overflow-wrap:anywhere;max-width:100%;">
<p><b>PixelYourSite</b></p>
<p>
<?php foreach ( $lines as $label => $value ) : ?>
	<b><?php echo esc_html( $label ); ?>:</b> <?php echo esc_html( $value ); ?><br>
<?php endforeach; ?>
</p>
<?php if ( '' !== $when ) : ?>
	<p><?php echo esc_html( $when ); ?> <a href="<?php echo esc_url( $order_url ); ?>"><?php echo esc_html( $open ); ?></a></p>
<?php endif; ?>
<?php if ( '' !== $pro ) : ?>
	<p><?php echo esc_html( $pro ); ?></p>
<?php endif; ?>
<p><small><?php echo esc_html( $control ); ?> <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open the settings', 'pys' ); ?></a></small></p>
</div>
