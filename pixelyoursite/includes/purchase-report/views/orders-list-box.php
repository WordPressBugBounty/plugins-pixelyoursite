<?php
/**
 * The summary box above the orders table: the orders on the page.
 *
 * @package PixelYourSite
 *
 * @var string   $type          'woo' or 'edd'
 * @var array    $summary       PurchaseReportSummary::fromRows() value
 * @var string   $filter        Active verdict filter, or ''
 * @var string   $orders_url
 * @var string   $settings_url
 * @var bool     $is_filling    The background fill is still adding orders
 * @var bool     $filter_capped The verdict filter was cut at PurchaseReportCache::ROWS_LIMIT
 * @var int      $window_days
 * @var string   $value_tracked Formatted amount, or ''
 * @var string[] $attention     PurchaseReportSummary::attentionLines() value
 * @var string[] $activity      PurchaseReportSummary::activityLines() value
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="pys-pr-list-box" hidden>
	<details class="pys-pr-list-box__details">
		<summary class="pys-pr-list-box__head">
			<strong><?php echo esc_html( PurchaseReportAdmin::boxTitle( $type ) ); ?></strong>
			<span class="pys-pr-sep">|</span>
			<span>
				<span class="pys-pr-dot pys-pr-dot--tracked"></span>
				<strong>
					<?php
					/* translators: 1: tracked orders, 2: all orders on the page */
					echo esc_html( sprintf( __( '%1$d of %2$d purchases on this page tracked', 'pys' ), $summary['tracked'], $summary['total'] ) );
					?>
				</strong>
				<?php if ( '' !== $value_tracked ) : ?>
					<span class="pys-pr-muted">&middot; <?php echo esc_html( $value_tracked ); ?></span>
				<?php endif; ?>
			</span>
			<span class="pys-pr-pills">
				<?php foreach ( $summary['by_verdict'] as $verdict => $count ) : ?>
					<?php if ( $count > 0 ) : ?>
						<a class="pys-pr-pill<?php echo $filter === $verdict ? ' pys-pr-pill--active' : ''; ?>" href="<?php echo esc_url( add_query_arg( PurchaseReportAdmin::FILTER_ARG, $verdict, $orders_url ) ); ?>" title="<?php esc_attr_e( 'Show every order with this status', 'pys' ); ?>">
							<span class="pys-pr-dot pys-pr-dot--<?php echo esc_attr( $verdict ); ?>"></span><?php echo esc_html( PurchaseReport::verdictLabel( $verdict ) . ' ' . $count ); ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( '' !== $filter ) : ?>
					<a class="pys-pr-pill" href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'Clear filter', 'pys' ); ?></a>
				<?php endif; ?>
			</span>
			<span class="pys-pr-toggle">
				<span class="pys-pr-toggle__open"><?php esc_html_e( 'Show details', 'pys' ); ?> &#9662;</span>
				<span class="pys-pr-toggle__close"><?php esc_html_e( 'Hide details', 'pys' ); ?> &#9652;</span>
			</span>
		</summary>

		<div class="pys-pr-list-box__table">
			<?php PurchaseReportAdmin::view( 'platform-table', array( 'summary' => $summary ) ); ?>
		</div>
		<div class="pys-pr-list-box__columns">
			<div>
				<h4><?php esc_html_e( 'Needs attention', 'pys' ); ?></h4>
				<?php if ( empty( $attention ) ) : ?>
					<p class="pys-pr-muted"><?php esc_html_e( 'Nothing on this page.', 'pys' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $attention as $line ) : ?>
					<p><span class="pys-pr-dot pys-pr-dot--not_tracked"></span><?php echo esc_html( $line ); ?></p>
				<?php endforeach; ?>
			</div>
			<div>
				<h4><?php esc_html_e( 'What PixelYourSite did for these orders', 'pys' ); ?></h4>
				<?php if ( empty( $activity ) ) : ?>
					<p class="pys-pr-muted"><?php esc_html_e( 'No Purchase was sent for these orders.', 'pys' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $activity as $line ) : ?>
					<p><?php echo esc_html( $line ); ?></p>
				<?php endforeach; ?>
			</div>
		</div>
		<p class="pys-pr-list-box__foot pys-pr-muted">
			<?php
			/* translators: %d: number of days */
			echo esc_html( sprintf( __( 'The numbers are about the orders on this page. The status filters cover the last %d days.', 'pys' ), $window_days ) );
			if ( $is_filling ) {
				echo ' ' . esc_html__( 'Older orders are still being added to the filters.', 'pys' );
			}
			if ( $filter_capped ) {
				/* translators: %s: number of orders */
				echo ' ' . esc_html( sprintf( __( 'The filter shows the newest %s orders only.', 'pys' ), number_format_i18n( PurchaseReportCache::ROWS_LIMIT ) ) );
			}
			if ( $summary['not_counted'] > 0 ) {
				/* translators: %d: number of orders */
				echo ' ' . esc_html( sprintf( _n( '%d order on this page is not a purchase and is not counted.', '%d orders on this page are not purchases and are not counted.', $summary['not_counted'], 'pys' ), $summary['not_counted'] ) );
			}
			?>
		</p>
		<?php
		PurchaseReportAdmin::view(
			'control-line',
			array(
				'type'         => $type,
				'settings_url' => $settings_url,
			)
		);
		?>
	</details>
</div>
