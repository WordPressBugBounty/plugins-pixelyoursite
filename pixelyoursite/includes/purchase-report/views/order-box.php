<?php
/**
 * The order box: verdict, Purchase events, visit data, identifiers.
 *
 * @package PixelYourSite
 *
 * @var string   $type         'woo' or 'edd'
 * @var array    $report       PurchaseReport::forOrder() value
 * @var array    $visit        PurchaseReportOrderData::visit() value
 * @var array    $identifiers  PurchaseReportOrderData::identifiers() value
 * @var string[] $ad_click     Ad platforms the visit came from
 * @var bool     $full_values  Show identifiers in full
 * @var string   $settings_url
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$none      = '<span class="pys-pr-muted">' . esc_html__( 'none', 'pys' ) . '</span>';
$show      = function ( $value ) use ( $full_values ) {
	return $full_values ? (string) $value : PurchaseReportOrderData::shorten( $value );
};
$id_rows   = array();
$id_values = array(
	array( 'fbp / fbc', __( 'Meta; checkout cookies', 'pys' ), array( $identifiers['fbp'], $identifiers['fbc'] ) ),
	array( 'client_id', __( 'GA4; _ga cookie', 'pys' ), array( $identifiers['ga_client_id'] ) ),
	array( 'external_id', __( 'Advanced matching', 'pys' ), array( $identifiers['external_id'] ) ),
	array( __( 'IP / user agent', 'pys' ), __( 'Checkout request', 'pys' ), array( $identifiers['ip'], $identifiers['user_agent'] ) ),
);
foreach ( $id_values as $id_value ) {
	if ( '' !== implode( '', $id_value[2] ) ) {
		$id_rows[] = $id_value;
	}
}
?>
<div class="pys-pr-box" data-verdict="<?php echo esc_attr( $report['verdict'] ); ?>">
	<div class="pys-pr-box__summary">
		<span class="pys-pr-badge pys-pr-badge--<?php echo esc_attr( $report['verdict'] ); ?>"><?php echo esc_html( PurchaseReport::verdictLabel( $report['verdict'] ) ); ?></span>
		<span><?php echo esc_html( PurchaseReport::summarySentence( $report ) ); ?></span>
	</div>

	<details class="pys-pr-section">
		<summary><?php esc_html_e( 'Purchase events', 'pys' ); ?></summary>
		<div class="pys-pr-section__body">
			<table class="pys-pr-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Platform', 'pys' ); ?></th>
						<th><abbr title="<?php esc_attr_e( 'Pixel/API: the tag on the thank-you page, plus the API request with the same event id. Pixel: the tag only.', 'pys' ); ?>"><?php esc_html_e( 'Sent by', 'pys' ); ?></abbr></th>
						<th><abbr title="<?php esc_attr_e( 'Sent: the thank-you page sent the Purchase. Whether the platform accepted it is only visible in the platform.', 'pys' ); ?>"><?php esc_html_e( 'Status', 'pys' ); ?></abbr></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $report['platforms'] ) ) : ?>
					<tr><td colspan="3" class="pys-pr-muted"><?php esc_html_e( 'No platform has the Purchase event enabled.', 'pys' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $report['platforms'] as $platform => $row ) : ?>
					<tr>
						<td>
							<?php echo esc_html( $row['label'] ); ?>
							<?php foreach ( $row['pixels'] as $pixel_id ) : ?>
								<code><?php echo esc_html( PurchaseReportOrderData::shorten( $pixel_id ) ); ?></code>
							<?php endforeach; ?>
						</td>
						<td><strong><?php echo esc_html( PurchaseReport::channelLabel( $row['channel'] ) ); ?></strong></td>
						<td>
							<span class="pys-pr-state pys-pr-state--<?php echo esc_attr( $row['state'] ); ?>"><?php echo esc_html( PurchaseReport::stateLabel( $row['state'] ) ); ?></span>
							<?php if ( '' !== $row['note'] ) : ?>
								<span class="pys-pr-muted">&middot; <?php echo esc_html( PurchaseReport::noteLabel( $row['note'] ) ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</details>

	<details class="pys-pr-section">
		<summary><?php esc_html_e( 'Visit data', 'pys' ); ?></summary>
		<div class="pys-pr-section__body">
			<?php if ( ! $visit['present'] ) : ?>
				<p class="pys-pr-muted"><?php esc_html_e( 'No visit data was stored with this order.', 'pys' ); ?></p>
			<?php elseif ( $visit['renewal'] ) : ?>
				<p class="pys-pr-muted"><?php esc_html_e( 'Subscription renewal: the customer did not visit the site for this payment, so there is no visit to show.', 'pys' ); ?></p>
			<?php else : ?>
				<table class="pys-pr-table">
					<thead>
						<tr>
							<th></th>
							<th><?php esc_html_e( 'First visit', 'pys' ); ?></th>
							<th><?php esc_html_e( 'Last visit (this order)', 'pys' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class="pys-pr-muted"><?php esc_html_e( 'Landing page', 'pys' ); ?></td>
							<?php foreach ( array( 'first', 'last' ) as $part ) : ?>
								<td title="<?php echo esc_attr( PurchaseReportOrderData::path( $visit[ $part ]['landing'] ) ); ?>"><?php echo '' !== $visit[ $part ]['landing'] ? esc_html( PurchaseReportOrderData::shortPath( $visit[ $part ]['landing'], 70 ) ) : $none; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from an escaped string. ?></td>
							<?php endforeach; ?>
						</tr>
						<tr>
							<td class="pys-pr-muted"><?php esc_html_e( 'Traffic source', 'pys' ); ?></td>
							<?php foreach ( array( 'first', 'last' ) as $part ) : ?>
								<td><?php echo '' !== $visit[ $part ]['source'] ? esc_html( $visit[ $part ]['source'] ) : $none; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<?php endforeach; ?>
						</tr>
						<tr>
							<td class="pys-pr-muted"><?php esc_html_e( 'Campaign (UTM)', 'pys' ); ?></td>
							<?php foreach ( array( 'first', 'last' ) as $part ) : ?>
								<td><?php echo ! empty( $visit[ $part ]['utm'] ) ? esc_html( PurchaseReportOrderData::utmText( $visit[ $part ]['utm'] ) ) : $none; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<?php endforeach; ?>
						</tr>
						<tr>
							<td class="pys-pr-muted"><?php esc_html_e( 'Ad click', 'pys' ); ?></td>
							<td colspan="2"><?php echo ! empty( $ad_click ) ? esc_html( implode( ', ', $ad_click ) ) : $none; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
						<?php if ( '' !== $visit['browser_time'] ) : ?>
							<tr>
								<td class="pys-pr-muted"><?php esc_html_e( 'Browser time', 'pys' ); ?></td>
								<td colspan="2"><?php echo esc_html( $visit['browser_time'] ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</details>

	<?php if ( ! empty( $id_rows ) ) : ?>
		<details class="pys-pr-section">
			<summary><?php esc_html_e( 'Identifiers', 'pys' ); ?></summary>
			<div class="pys-pr-section__body">
				<table class="pys-pr-table">
					<tbody>
					<?php foreach ( $id_rows as $id_row ) : ?>
						<tr>
							<td><abbr title="<?php echo esc_attr( $id_row[1] ); ?>"><?php echo esc_html( $id_row[0] ); ?></abbr></td>
							<td>
								<?php foreach ( $id_row[2] as $index => $value ) : ?>
									<?php echo $index > 0 ? ' / ' : ''; ?>
									<?php if ( '' !== $value ) : ?>
										<code><?php echo esc_html( $show( $value ) ); ?></code>
									<?php else : ?>
										<?php echo $none; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php endif; ?>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( ! $full_values ) : ?>
					<p class="pys-pr-muted"><?php esc_html_e( 'Values are shortened.', 'pys' ); ?></p>
				<?php endif; ?>
			</div>
		</details>
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
