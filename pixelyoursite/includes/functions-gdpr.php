<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ConsentMagic
 */
function isConsentMagicPluginActivated() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return (is_plugin_active( 'consent-magic-pro/consent-magic-pro.php' ) || is_plugin_active( 'consent-magic/consent-magic.php' )) ;
}
function isConsentMagicPluginInstalled() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $installed_plugins = get_plugins();
    $plugin_slug = 'consent-magic/consent-magic.php';
    $plugin_slug_pro = "consent-magic-pro/consent-magic-pro.php";

    return
        array_key_exists( $plugin_slug, $installed_plugins ) ||
        in_array( $plugin_slug, $installed_plugins, true ) ||
        array_key_exists( $plugin_slug_pro, $installed_plugins ) ||
        in_array( $plugin_slug_pro, $installed_plugins, true );

}

function isConsentMagicPluginLicenceActivated() {
    $id = get_option('cs_product_id');
    if($id && get_option('wc_am_client_'.$id.'_activated') == 'Activated' || is_plugin_active( 'consent-magic/consent-magic.php')) {
        return true;
    }
    return false;
}
/**
 * @link https://wordpress.org/plugins/ginger/
 */
function isGingerPluginActivated() {
	
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	
	return is_plugin_active( 'ginger/ginger-eu-cookie-law.php' );
	
}

/**
 * @link https://wordpress.org/plugins/cookiebot/
 * @link https://www.cookiebot.com/en/developer/
 */
function isCookiebotPluginActivated() {
	
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	
	return is_plugin_active( 'cookiebot/cookiebot.php' );
	
}

/**
 * @link https://wordpress.org/plugins/cookie-notice/
 */
function isCookieNoticePluginActivated() {
	
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	
	return is_plugin_active( 'cookie-notice/cookie-notice.php' );
	
}

/**
 * GDPR Cookie Consent
 *
 * @link https://wordpress.org/plugins/cookie-law-info/
 */
function isCookieLawInfoPluginActivated() {
	
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

    return is_plugin_active( 'cookie-law-info/cookie-law-info.php' )
        || is_plugin_active( 'webtoffee-cookie-consent/webtoffee-cookie-consent.php' );
	
}

/**
 * GDPR Real Cookie Banner
 *
 * @link https://wordpress.org/plugins/real-cookie-banner/
 */
function isRealCookieBannerPluginActivated() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'real-cookie-banner-pro/index.php' )
        || is_plugin_active( 'real-cookie-banner/index.php' ) ;

}

function adminGdprAjaxNotEnabledNotice() {
    $user_id = get_current_user_id();
    $url = buildAdminUrl( 'pixelyoursite', 'gdpr', false, array(
        '_wpnonce' => wp_create_nonce( 'pys_enable_gdpr_ajax' ),
        'pys'      => array(
            'enable_gdpr_ajax' => true,
        ),
    ) );

    $meta_key = 'pys_core_gdpr_ajax_notice_dismissed_at';
    $dismissed_at = get_user_meta( $user_id, $meta_key );
    if(!$dismissed_at){
        ?>

    <div class="notice notice-error is-dismissible pys_core_gdpr_ajax_notice">
        <p>You use the <strong>GDPR Cookie Consent</strong> and <strong>PixelYourSite</strong> plugins. You
            must turn on "Enable AJAX filter values update" option to avoid problems with cache plugins.
            <a href="<?php echo esc_url( $url ); ?>"><strong>CLICK HERE TO
                    ENABLE</strong></a>.</p>
    </div>

        <script type="application/javascript">
            jQuery(document).on('click', '.pys_core_gdpr_ajax_notice .notice-dismiss', function () {

                jQuery.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'pys_notice_dismiss',
                        nonce: '<?php echo esc_attr( wp_create_nonce( 'pys_notice_dismiss' ) ); ?>',
                        user_id: '<?php echo esc_attr( $user_id ); ?>',
                        addon_slug: 'core',
                        meta_key: 'gdpr_ajax_notice'
                    }
                })

            })
        </script>
        <?php
    }
}

function adminGdprAjaxEnabledNotice() {
    ?>
    
    <div class="notice notice-success">
        <p>All good :)</p>
    </div>
    
    <?php
}