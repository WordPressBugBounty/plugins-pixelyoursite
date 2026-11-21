<?php
/**
 * The order box on the EDD order screen, which has no meta box API.
 *
 * @package PixelYourSite
 *
 * @var string $title Box title
 * @var array  $box   PurchaseReportAdmin::orderBoxData() value
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="postbox pys-pr-postbox" id="pys_purchase_report_edd">
	<h3 class="hndle"><span><?php echo esc_html( $title ); ?></span></h3>
	<?php PurchaseReportAdmin::view( 'order-box', $box ); ?>
</div>
