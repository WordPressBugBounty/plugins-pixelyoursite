<?php
/**
 * Purchase events per tag: orders whose thank-you page sent it, and how.
 *
 * @package PixelYourSite
 *
 * @var array $summary PurchaseReportSummary::fromRows() value
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( empty( $summary['platforms'] ) ) {
	return;
}
?>
<table class="pys-pr-platforms">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Platform', 'pys' ); ?></th>
			<th><?php esc_html_e( 'Purchase events', 'pys' ); ?></th>
			<th><?php esc_html_e( 'Sent by', 'pys' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $summary['platforms'] as $platform => $counts ) : ?>
		<tr>
			<td><?php echo esc_html( $counts['label'] ); ?></td>
			<td><strong><?php echo (int) $counts['sent']; ?></strong></td>
			<td>
				<?php
				if ( 0 === $counts['sent'] ) {
					echo '&mdash;';
				} elseif ( $counts['api'] > 0 ) {
					echo esc_html( PurchaseReport::channelLabel( PurchaseReport::CHANNEL_TAG_AND_API ) );
				} else {
					echo esc_html( PurchaseReport::channelLabel( PurchaseReport::CHANNEL_TAG ) );
					if ( $counts['api_not_configured'] > 0 ) {
						echo ' <span class="pys-pr-muted">&middot; ' . esc_html( PurchaseReport::noteLabel( 'api_not_configured' ) ) . '</span>';
					}
				}
				?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
