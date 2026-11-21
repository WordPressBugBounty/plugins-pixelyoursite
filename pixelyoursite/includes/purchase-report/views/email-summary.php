<?php
/**
 * The Purchase summary email of one store.
 *
 * @package PixelYourSite
 *
 * @var string     $type          'woo' or 'edd'
 * @var array      $summary       PurchaseReportSummary::fromRows() value
 * @var array|null $previous      The week before, when it had purchases; never for the month
 * @var int        $percent
 * @var string     $period        'week' or 'month'
 * @var string     $period_label
 * @var bool       $is_test
 * @var string     $value_tracked
 * @var string     $value_total
 * @var string[]   $attention
 * @var string[]   $activity
 * @var string     $pro_line      Empty when every purchase was tracked
 * @var string     $attention_url
 * @var string     $settings_url
 * @var bool       $show_review
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$cell = 'padding:6px 12px 6px 0;border-bottom:1px solid #e5e5e5;text-align:left;font-size:14px;';
?>
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:15px;line-height:1.5;color:#1d2327;max-width:640px;word-break:break-word;overflow-wrap:anywhere;">
	<?php if ( $is_test ) : ?>
		<p style="color:#50575e;"><?php esc_html_e( 'This is a test of the Purchase summary. The scheduled email goes to the address in the settings.', 'pys' ); ?></p>
	<?php endif; ?>

	<h2 style="font-size:18px;margin:0 0 4px;"><?php echo esc_html( PurchaseReportAdmin::boxTitle( $type ) ); ?></h2>
	<p style="color:#50575e;margin:0 0 16px;"><?php echo esc_html( $period_label ); ?></p>

	<?php if ( 0 === $summary['total'] ) : ?>
		<p><?php esc_html_e( 'No purchases in this period.', 'pys' ); ?></p>
	<?php else : ?>
		<p style="font-size:28px;font-weight:600;margin:0;">
			<?php
			/* translators: %d: percentage */
			echo esc_html( sprintf( __( '%d%% of purchases tracked', 'pys' ), $percent ) );
			?>
		</p>
		<p style="color:#50575e;margin:4px 0 16px;">
			<?php
			/* translators: 1: tracked purchases, 2: all purchases */
			echo esc_html( sprintf( __( '%1$d of %2$d purchases', 'pys' ), $summary['tracked'], $summary['total'] ) );
			if ( '' !== $value_tracked && '' !== $value_total ) {
				/* translators: 1: amount tracked, 2: total amount */
				echo ' &middot; ' . esc_html( sprintf( __( '%1$s of %2$s', 'pys' ), $value_tracked, $value_total ) );
			}
			if ( null !== $previous ) {
				$change = $percent - PurchaseReportSummary::trackedPercent( $previous );
				echo ' &middot; ';
				if ( 0 === $change ) {
					esc_html_e( 'same as the period before', 'pys' );
				} elseif ( $change > 0 ) {
					/* translators: %d: percentage points */
					echo esc_html( sprintf( __( 'up %d points on the period before', 'pys' ), $change ) );
				} else {
					/* translators: %d: percentage points */
					echo esc_html( sprintf( __( 'down %d points on the period before', 'pys' ), abs( $change ) ) );
				}
			}
			?>
		</p>

		<?php if ( ! empty( $summary['platforms'] ) ) : ?>
			<table style="border-collapse:collapse;width:100%;margin:0 0 16px;">
				<tr>
					<th style="<?php echo esc_attr( $cell ); ?>"><?php esc_html_e( 'Platform', 'pys' ); ?></th>
					<th style="<?php echo esc_attr( $cell ); ?>"><?php esc_html_e( 'Purchase events', 'pys' ); ?></th>
					<th style="<?php echo esc_attr( $cell ); ?>"><?php esc_html_e( 'Sent by', 'pys' ); ?></th>
				</tr>
				<?php foreach ( $summary['platforms'] as $counts ) : ?>
					<?php
					if ( 0 === $counts['sent'] ) {
						$sent_by = "\xE2\x80\x94";
					} elseif ( $counts['api'] > 0 ) {
						$sent_by = PurchaseReport::channelLabel( PurchaseReport::CHANNEL_TAG_AND_API );
					} else {
						$sent_by = PurchaseReport::channelLabel( PurchaseReport::CHANNEL_TAG );
						if ( $counts['api_not_configured'] > 0 ) {
							$sent_by .= " \xC2\xB7 " . PurchaseReport::noteLabel( 'api_not_configured' );
						}
					}
					?>
					<tr>
						<td style="<?php echo esc_attr( $cell ); ?>"><?php echo esc_html( $counts['label'] ); ?></td>
						<td style="<?php echo esc_attr( $cell ); ?>"><b><?php echo (int) $counts['sent']; ?></b></td>
						<td style="<?php echo esc_attr( $cell ); ?>"><?php echo esc_html( $sent_by ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>

		<h3 style="font-size:15px;margin:0 0 6px;"><?php esc_html_e( 'Needs attention', 'pys' ); ?></h3>
		<?php if ( empty( $attention ) ) : ?>
			<p style="margin:0 0 16px;"><?php esc_html_e( 'Nothing.', 'pys' ); ?></p>
		<?php else : ?>
			<ul style="margin:0 0 8px;padding-left:20px;">
				<?php foreach ( $attention as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $summary['attention'] > 0 ) : ?>
				<p style="margin:0 0 16px;"><a href="<?php echo esc_url( $attention_url ); ?>"><?php esc_html_e( 'Open the orders that need attention', 'pys' ); ?></a></p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! empty( $activity ) ) : ?>
			<h3 style="font-size:15px;margin:0 0 6px;"><?php esc_html_e( 'What PixelYourSite did', 'pys' ); ?></h3>
			<ul style="margin:0 0 16px;padding-left:20px;">
				<?php foreach ( $activity as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $show_review ) : ?>
			<p style="margin:0 0 16px;">
				<?php esc_html_e( 'PixelYourSite tracked almost every purchase in this period. We are a small team and every 5-star review makes us jump for joy. Give us one. It takes just a few seconds.', 'pys' ); ?>
				<a href="<?php echo esc_url( PurchaseReportAdmin::REVIEW_URL ); ?>"><?php esc_html_e( 'Write a review', 'pys' ); ?></a>
			</p>
		<?php endif; ?>

		<?php if ( '' !== $pro_line ) : ?>
			<h3 style="font-size:15px;margin:0 0 6px;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: "last week" or "last month" */
						__( 'What PixelYourSite Pro would have recovered %s', 'pys' ),
						'month' === $period ? __( 'last month', 'pys' ) : __( 'last week', 'pys' )
					)
				);
				?>
			</h3>
			<p style="margin:0 0 16px;"><?php echo esc_html( $pro_line ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<p style="color:#50575e;font-size:13px;border-top:1px solid #e5e5e5;padding-top:12px;">
		<?php
		echo 'month' === $period ? esc_html__( 'You get this email on the first Monday of the month.', 'pys' ) : esc_html__( 'You get this email every Monday.', 'pys' );
		echo ' ';
		echo wp_kses(
			sprintf(
				/* translators: %s: link to the settings card */
				__( 'Change or stop it under %s.', 'pys' ),
				'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Native Data Tracking and Reporting', 'pys' ) . '</a>'
			),
			array( 'a' => array( 'href' => array() ) )
		);
		?>
	</p>
</div>
