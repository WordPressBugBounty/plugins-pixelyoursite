<?php
/**
 * The last line of every surface: where it is switched on and off.
 *
 * @package PixelYourSite
 *
 * @var string $type         'woo' or 'edd'
 * @var string $settings_url
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<p class="pys-pr-control">
	<?php
	echo wp_kses(
		sprintf(
			/* translators: 1: "WooCommerce" or "Easy Digital Downloads", 2: link to the settings card */
			__( 'Control this on the %1$s settings page, under %2$s.', 'pys' ),
			'edd' === $type ? 'Easy Digital Downloads' : 'WooCommerce',
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Native Data Tracking and Reporting', 'pys' ) . '</a>'
		),
		array( 'a' => array( 'href' => array() ) )
	);
	?>
</p>
