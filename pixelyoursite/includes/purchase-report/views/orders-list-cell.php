<?php
/**
 * The "PixelYourSite Purchase" cell of the orders list.
 *
 * @package PixelYourSite
 *
 * @var array $report PurchaseReport::forOrder() value
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$parts = array();
foreach ( $report['platforms'] as $row ) {
	$is_sent = PurchaseReport::STATE_SENT === $row['state'];
	$parts[] = array(
		'text'  => $row['label'] . ' ' . ( $is_sent ? PurchaseReport::channelLabel( $row['channel'] ) : PurchaseReport::stateLabel( $row['state'] ) ),
		'class' => PurchaseReport::STATE_NOT_SENT === $row['state'] ? 'pys-pr-bad' : '',
	);
}
?>
<span class="pys-pr-cell" data-verdict="<?php echo esc_attr( $report['verdict'] ); ?>">
	<?php if ( PurchaseReport::VERDICT_NOT_PURCHASE === $report['verdict'] ) : ?>
		<span class="pys-pr-muted"><?php echo esc_html( PurchaseReport::verdictLabel( $report['verdict'] ) ); ?></span>
	<?php else : ?>
		<span class="pys-pr-dot pys-pr-dot--<?php echo esc_attr( $report['verdict'] ); ?>"></span><strong><?php echo esc_html( PurchaseReport::verdictLabel( $report['verdict'] ) ); ?></strong>
		<?php if ( ! empty( $parts ) ) : ?>
			<br><span class="pys-pr-muted">
			<?php foreach ( $parts as $index => $part ) : ?>
				<?php echo $index > 0 ? '&middot; ' : ''; ?><span class="<?php echo esc_attr( $part['class'] ); ?>"><?php echo esc_html( $part['text'] ); ?></span>
			<?php endforeach; ?>
			</span>
		<?php endif; ?>
	<?php endif; ?>
</span>
