<?php
/**
 * The "Native Data Tracking and Reporting" card of the WooCommerce and Easy
 * Digital Downloads settings tabs.
 *
 * @package PixelYourSite
 *
 * @var string $type        'woo' or 'edd'
 * @var string $short_name
 * @var string $orders_page
 * @var string $email_name
 * @var string $summary     When the next summary email goes out
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$switch = function ( $option, $label, $description ) {
	?>
	<div>
		<div class="d-flex align-items-center mb-4">
			<?php PYS()->render_switcher_input( $option ); ?>
			<h4 class="switcher-label secondary_heading"><?php echo esc_html( $label ); ?></h4>
		</div>
		<p class="text-gray"><?php echo wp_kses( $description, array( 'i' => array() ) ); ?></p>
	</div>
	<?php
};
?>
<div class="card card-style5 pys-pr-settings" id="pys-purchase-report-settings">
	<div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
		<div class="d-flex align-items-center">
			<h4 class="secondary_heading_type2"><?php esc_html_e( 'Native Data Tracking and Reporting', 'pys' ); ?></h4>
		</div>
		<?php cardCollapseSettings(); ?>
	</div>

	<div class="card-body">
		<div class="gap-24">
			<div>
				<div class="mb-12"><label class="primary_heading"><?php esc_html_e( 'Data stored', 'pys' ); ?></label></div>
				<?php
				$switch(
					$type . '_enabled_save_data_to_orders',
					__( 'Store visit data on orders', 'pys' ),
					__( 'Saves the <i>landing page, traffic source, UTMs</i> and <i>browser time</i> on each order. Used by the order pages, the dashboard widget and the emails below.', 'pys' )
				);
				?>
			</div>
			<hr>
			<div>
				<div class="mb-12"><label class="primary_heading"><?php esc_html_e( 'Admin order pages', 'pys' ); ?></label></div>
				<div class="gap-24">
					<?php
					$switch(
						$type . '_enabled_display_data_to_orders',
						/* translators: %s: "WooCommerce" or "EDD" */
						sprintf( __( 'Show the PixelYourSite %s Purchase box on the order\'s edit page', 'pys' ), $short_name ),
						__( 'Whether the thank-you page sent the Purchase and to which tags, the visit data and the identifiers. Not visible to clients.', 'pys' )
					);
					$switch(
						$type . '_enabled_show_tracking_type',
						__( 'Show the Purchase status on the admin orders list', 'pys' ),
						/* translators: %s: admin page, e.g. "WooCommerce › Orders" */
						sprintf( __( 'On %s: the <i>PixelYourSite Purchase</i> column and the summary box above the table, with filters by status.', 'pys' ), esc_html( $orders_page ) )
					);
					?>
					<div class="pro-feature-container d-flex align-items-center justify-content-between">
						<div>
							<div class="d-flex align-items-center mb-4">
								<div class="d-flex align-items-center flex-with-badge">
									<?php renderDummySwitcher( false ); ?>
									<h4 class="switcher-label secondary_heading"><?php esc_html_e( 'Display orders data on the user\'s profile page', 'pys' ); ?></h4>
								</div>
							</div>
							<p class="text-gray"><?php echo wp_kses( __( 'Display <i>the number of orders, lifetime value, and average order</i>.', 'pys' ), array( 'i' => array() ) ); ?></p>
						</div>
						<?php renderProBadge(); ?>
					</div>
				</div>
			</div>
			<hr>
			<div>
				<div class="mb-12"><label class="primary_heading"><?php esc_html_e( 'Summaries', 'pys' ); ?></label></div>
				<div class="gap-24">
					<?php
					$switch(
						$type . '_purchase_dashboard_widget',
						/* translators: %s: "WooCommerce" or "EDD" */
						sprintf( __( 'Show the PixelYourSite %s Purchase widget on the WordPress dashboard', 'pys' ), $short_name ),
						__( 'Last 30 days: purchases tracked, value, from ad clicks, events per platform.', 'pys' )
					);
					$switch(
						'purchase_summary_email',
						__( 'Send the Purchase summary by email', 'pys' ),
						__( 'The dashboard figures plus what needs attention. One setting for WooCommerce and Easy Digital Downloads.', 'pys' )
					);
					?>
					<div class="pys-pr-settings__summary">
						<div class="d-flex align-items-center mb-12 pys-pr-settings__inline">
							<span class="secondary_heading pys-pr-settings__label"><?php esc_html_e( 'How often', 'pys' ); ?></span>
							<?php PYS()->render_radio_input( 'purchase_summary_email_period', 'week', __( 'Weekly (every Monday)', 'pys' ) ); ?>
							<?php PYS()->render_radio_input( 'purchase_summary_email_period', 'month', __( 'Monthly (first Monday)', 'pys' ) ); ?>
						</div>
						<div class="d-flex align-items-center mb-12 pys-pr-settings__inline">
							<span class="secondary_heading pys-pr-settings__label"><?php esc_html_e( 'Send to', 'pys' ); ?></span>
							<?php PYS()->render_text_input( 'purchase_summary_email_address', (string) get_option( 'admin_email' ) ); ?>
							<button type="button" class="btn btn-sm btn-primary pys-pr-settings__test" title="<?php esc_attr_e( 'The test goes to your own address.', 'pys' ); ?>"><?php esc_html_e( 'Send me a test email', 'pys' ); ?></button>
							<span class="text-gray pys-pr-settings__test-result" aria-live="polite"></span>
						</div>
						<p class="text-gray"><?php echo esc_html( $summary ); ?></p>
					</div>
				</div>
			</div>
			<hr>
			<div>
				<div class="mb-12"><label class="primary_heading"><?php esc_html_e( 'Admin email', 'pys' ); ?></label></div>
				<?php
				$switch(
					$type . '_add_enrich_to_admin_email',
					/* translators: %s: name of the store's admin email */
					sprintf( __( 'Add the PixelYourSite block to the admin %s', 'pys' ), $email_name ),
					__( 'Landing page, traffic source, UTMs, ad click, customer and a link to the order\'s edit page. Client emails are not changed.', 'pys' )
				);
				?>
			</div>
		</div>
	</div>
</div>
