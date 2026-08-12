<?php

/**
 * Plugin Name: PixelYourSite
 * Plugin URI: http://www.pixelyoursite.com/
 * Description: Meta Pixel & CAPI, GA4, and GTM support with ZERO CODING. Track events, WooCommerce/EDD ready, with Pinterest & Bing add-ons, plus consent support.
 * Version: 11.3.0
 * Author: PixelYourSite
 * Author URI: http://www.pixelyoursite.com
 * License: GPLv3
 *
 * Requires at least: 4.4
 * Tested up to: 7.0
 *
 * WC requires at least: 2.6.0
 * WC tested up to: 10.7
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

    if (!wp_next_scheduled('pys_capi_nudge_email')) {
        wp_schedule_single_event(time() + 7 * DAY_IN_SECONDS, 'pys_capi_nudge_email');
    }
}

add_action('upgrader_process_complete', 'pys_free_capi_nudge_reschedule_on_update', 10, 2);
function pys_free_capi_nudge_reschedule_on_update($upgrader, $hook_extra) {
    if (get_option('pys_capi_nudge_email_sent')) {
        return;
    }
    if (!isset($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
        return;
    }
    $plugins = isset($hook_extra['plugins']) ? $hook_extra['plugins'] : [];
    if (!in_array(PYS_FREE_PLUGIN_BASENAME, $plugins, true)) {
        return;
    }
    if (!wp_next_scheduled('pys_capi_nudge_email')) {
        wp_schedule_single_event(time() + 7 * DAY_IN_SECONDS, 'pys_capi_nudge_email');
    }
}

add_action('pys_capi_nudge_email', 'pys_free_send_capi_nudge_email');
function pys_free_send_capi_nudge_email() {
    if (get_option('pys_capi_nudge_email_sent')) {
        return;
    }
    if (!function_exists('PixelYourSite\\pys_meta_pixel_missing_capi')
        || !\PixelYourSite\pys_meta_pixel_missing_capi()) {
        return;
    }
    $pys_url   = admin_url('admin.php?page=pixelyoursite');
    $video_url = 'https://www.youtube.com/watch?v=fAwsayYLo5s';

    $body  = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;font-size:17px;line-height:1.6;color:#333;">';
    $body .= '<p style="font-size:20px;font-weight:bold;margin:0 0 16px;">Your Meta pixel does not have Conversion API configured yet.</p>';
    $body .= '<p style="margin:0 0 20px;">You should do it now because it improves conversion tracking and optimisation. Simply follow these steps:</p>';
    $body .= '<ol style="padding-left:24px;margin:0 0 24px;">';
    $body .= '<li style="margin-bottom:14px;">Login to your Events Manager.</li>';
    $body .= '<li style="margin-bottom:14px;">Select your pixel ID if you have more than one.</li>';
    $body .= '<li style="margin-bottom:14px;">Click on Settings and scroll until you find &ldquo;Conversions API&rdquo;. Click &ldquo;Generate access token&rdquo; and copy the token.</li>';
    $body .= '<li style="margin-bottom:14px;">Open <a href="' . esc_url($pys_url) . '">PixelYourSite</a>, open the Meta Pixel, and paste the token in the Conversion API field. Click Save Changes.</li>';
    $body .= '</ol>';
    $body .= '<p style="margin:0;"><a href="' . esc_url($video_url) . '" style="display:inline-block;padding:14px 28px;background:#0073aa;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;font-size:17px;">Watch the video now &rarr;</a></p>';
    $body .= '</body></html>';

    $sent = wp_mail(
        get_option('admin_email'),
        'Configure Meta CAPI inside PixelYourSite',
        $body,
        ['Content-Type: text/html; charset=UTF-8']
    );

    if ($sent) {
        update_option('pys_capi_nudge_email_sent', true);
    }
}

if ( isPysProActive()) {
    return; // exit early when PYS PRO is active
}

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {

        // HPOS
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );

        // Cache Product Objects
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'product_instance_caching',
            __FILE__,
            true
        );
    }
});

require_once 'pixelyoursite.php';
