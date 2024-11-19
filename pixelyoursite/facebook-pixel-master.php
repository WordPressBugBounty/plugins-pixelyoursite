<?php

/**
 * Plugin Name: PixelYourSite
 * Plugin URI: http://www.pixelyoursite.com/
 * Description: The <strong>Meta Pixel with Conversion API support</strong>, <strong>Google Analytics 4</strong>, and <strong>Google Tag Manager (NEW!)</strong> with <strong>ZERO CODING</strong>. Track key actions with our Automated Events, or fire your own events using flexible triggers. WooCommerce and EDD are fully integrated. Insert custom scripts with our Head & Footer option. Add the <strong>Pinterest</strong> or <strong>Bing Tags</strong> with paid add-ons. Fully integrated with <strong>ConsentMagic</strong> and other consent plugins.
 * Version: 10.0.1
 * Author: PixelYourSite
 * Author URI: http://www.pixelyoursite.com
 * License: GPLv3
 *
 * Requires at least: 4.4
 * Tested up to: 6.7
 *
 * WC requires at least: 2.6.0
 * WC tested up to: 9.4
 *
 * Text Domain: pys
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function isPysProActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'pixelyoursite-pro/pixelyoursite-pro.php' );

}

register_activation_hook( __FILE__, 'pysFreeActivation' );
function pysFreeActivation() {

    if ( isPysProActive() ) {
        deactivate_plugins('pixelyoursite-pro/pixelyoursite-pro.php');
    }
    \PixelYourSite\manageAdminPermissions();
}
/**
 * facebook-pixel-master.php used for backward compatibility.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
require_once 'pixelyoursite.php';
