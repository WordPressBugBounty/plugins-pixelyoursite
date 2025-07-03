<?php

/**
 * Plugin Name: PixelYourSite
 * Plugin URI: http://www.pixelyoursite.com/
 * Description: Meta Pixel & CAPI, GA4, and GTM support with ZERO CODING. Track events, WooCommerce/EDD ready, with Pinterest & Bing add-ons, plus consent support.
 * Version: 11.0.0.4
 * Author: PixelYourSite
 * Author URI: http://www.pixelyoursite.com
 * License: GPLv3
 *
 * Requires at least: 4.4
 * Tested up to: 6.8
 *
 * WC requires at least: 2.6.0
 * WC tested up to: 9.9
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

if ( isPysProActive()) {
    return; // exit early when PYS PRO is active
}

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

require_once 'pixelyoursite.php';
