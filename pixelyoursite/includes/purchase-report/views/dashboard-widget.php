<?php
/**
 * The WordPress dashboard widget: the last 30 days of a store.
 *
 * @package PixelYourSite
 *
 * @var string   $type          'woo' or 'edd'
 * @var array    $summary       PurchaseReportSummary::fromRows() value
 * @var int      $days
 * @var bool     $is_filling
 * @var string   $attention_url
 * @var string   $settings_url
 * @var string   $value_tracked
 * @var string   $value_total
 * @var string[] $activity
 * @var bool     $show_review
 * @var string   $pro_line      Empty when every purchase was tracked or the user hid it
 * @var string   $hide_pro_url
 * @var string   $review_later
 * @var string   $review_skip
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$percent = PurchaseReportSummary::trackedPercent( $summary );
$level   = $percent >= 95 ? 'tracked' : ( $percent >= 80 ? 'partly' : 'not_tracked' );
?>
<div class="pys-pr-widget">
	<?php if ( 0 === $summary['total'] ) : ?>
		<p class="pys-pr-muted">
			<?php
			/* translators: %d: number of days */
			echo esc_html( sprintf( __( 'No purchases in the last %d days.', 'pys' ), $days ) );
			if ( $is_filling ) {
				echo ' ' . esc_html__( 'Earlier orders are still being added.', 'pys' );
			}
			?>
		</p>
	<?php else : ?>
		<div class="pys-pr-widget__hero">
			<span class="pys-pr-widget__percent"><?php echo (int) $percent; ?>%</span>
			<span><?php esc_html_e( 'of purchases tracked', 'pys' ); ?></span>
			<span class="pys-pr-muted pys-pr-widget__period">
				<?php
				/* translators: %d: number of days */
				echo esc_html( sprintf( __( 'Last %d days', 'pys' ), $days ) );
				?>
			</span>
		</div>
		<div class="pys-pr-meter"><span class="pys-pr-meter__bar pys-pr-meter__bar--<?php echo esc_attr( $level ); ?>" style="width:<?php echo (int) $percent; ?>%"></span></div>
		<p class="pys-pr-muted">
			<?php
			/* translators: 1: tracked purchases, 2: all purchases */
			echo esc_html( sprintf( __( '%1$d of %2$d purchases', 'pys' ), $summary['tracked'], $summary['total'] ) );
			if ( '' !== $value_tracked && '' !== $value_total ) {
				/* translators: 1: amount tracked, 2: total amount */
				echo ' &middot; ' . esc_html( sprintf( __( '%1$s of %2$s', 'pys' ), $value_tracked, $value_total ) );
			}
			?>
		</p>

		<?php if ( $show_review ) : ?>
			<div class="pys-pr-review">
				<p><?php esc_html_e( 'PixelYourSite tracked almost every purchase in the last 30 days. We are a small team and every 5-star review makes us jump for joy. Give us one. It takes just a few seconds.', 'pys' ); ?></p>
				<div class="pys-pr-review__actions">
					<a class="pys-pr-btn pys-pr-btn--primary" href="<?php echo esc_url( PurchaseReportAdmin::REVIEW_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Write a review', 'pys' ); ?></a>
					<a class="pys-pr-btn pys-pr-btn--outline pys-pr-review__choice" data-choice="later" href="<?php echo esc_url( $review_later ); ?>"><?php esc_html_e( 'Later', 'pys' ); ?></a>
					<a class="pys-pr-review__dismiss pys-pr-review__choice" data-choice="dismiss" href="<?php echo esc_url( $review_skip ); ?>"><?php esc_html_e( 'Dismiss', 'pys' ); ?></a>
				</div>
			</div>
		<?php endif; ?>

		<p>
			<?php if ( $summary['attention'] > 0 ) : ?>
				<a href="<?php echo esc_url( $attention_url ); ?>">
					<?php
					/* translators: %d: number of orders */
					echo esc_html( sprintf( _n( '%d order needs attention', '%d orders need attention', $summary['attention'], 'pys' ), $summary['attention'] ) );
					?>
				</a>
			<?php else : ?>
				<span class="pys-pr-up"><?php esc_html_e( 'Nothing needs attention.', 'pys' ); ?></span>
			<?php endif; ?>
		</p>
		<?php foreach ( $activity as $line ) : ?>
			<p><?php echo esc_html( $line ); ?></p>
		<?php endforeach; ?>

		<?php PurchaseReportAdmin::view( 'platform-table', array( 'summary' => $summary ) ); ?>

		<?php if ( '' !== $pro_line ) : ?>
			<div class="pys-pr-pro">
				<p><?php echo esc_html( $pro_line ); ?></p>
				<a class="pys-pr-btn pys-pr-btn--outline pys-pr-pro__hide" href="<?php echo esc_url( $hide_pro_url ); ?>"><?php esc_html_e( 'Hide for 30 days', 'pys' ); ?></a>
			</div>
		<?php endif; ?>

		<?php if ( $is_filling ) : ?>
			<p class="pys-pr-muted"><?php esc_html_e( 'Earlier orders are still being added: the numbers will grow.', 'pys' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	PurchaseReportAdmin::view(
		'control-line',
		array(
			'type'         => $type,
			'settings_url' => $settings_url,
		)
	);
	?>
</div>
