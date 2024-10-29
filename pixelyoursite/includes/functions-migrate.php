<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function maybeMigrate() {

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	$pys_free_7_version = get_option( 'pys_core_free_version', false );

    if (!$pys_free_7_version || ($pys_free_7_version && version_compare($pys_free_7_version, '10.0.1', '<'))) {

        migrate_10_0_0();

        update_option( 'pys_core_version', PYS_FREE_VERSION );
        update_option( 'pys_updated_at', time() );
    }

    if (!$pys_free_7_version || ($pys_free_7_version && version_compare($pys_free_7_version, '9.6.1', '<'))) {
        migrate_9_6_1();

        update_option( 'pys_core_version', PYS_FREE_VERSION );
        update_option( 'pys_updated_at', time() );
    }

    if (!$pys_free_7_version || ($pys_free_7_version && version_compare($pys_free_7_version, '9.5.6', '<'))) {

        migrate_9_5_6();

        update_option( 'pys_core_free_version', PYS_FREE_VERSION );
        update_option( 'pys_updated_at', time() );
    }

    if (!$pys_free_7_version || ($pys_free_7_version && version_compare($pys_free_7_version, '9.5.1.1', '<') && !get_option( 'pys_custom_event_migrate_free', false )) ) {
        migrate_unify_custom_events();

        update_option( 'pys_core_free_version', PYS_FREE_VERSION );
        update_option( 'pys_updated_at', time() );
    } elseif ($pys_free_7_version && version_compare($pys_free_7_version, '9.0.0', '<') ) {
        migrate_9_0_0();

        update_option( 'pys_core_free_version', PYS_FREE_VERSION );
        update_option( 'pys_updated_at', time() );
    } elseif ($pys_free_7_version && version_compare($pys_free_7_version, '7.1.0', '<')) {

        migrate_7_1_0_bing_defaults();

        update_option( 'pys_core_free_version', PYS_FREE_VERSION );
        update_option( 'pys_updated_at', time() );

    }
	
}
function migrate_unify_custom_events(){
    foreach (CustomEventFactory::get() as $event) {
        $event->migrateUnifyGA();
    }
    update_option( 'pys_custom_event_migrate_free', true );
}

function migrate_10_0_0()
{
    if(GTM()->getOption('gtm_dataLayer_name') === 'dataLayerPYS'){
        GTM()->updateOptions([
            "gtm_dataLayer_name" => 'dataLayer',
        ]);
    }
}
function migrate_9_6_1() {
        $globalOptions = [
            "block_robot_enabled" => true,
        ];
        PYS()->updateOptions($globalOptions);
}
function migrate_9_5_6() {
    $ga_tags_woo_options = [];
    $ga_tags_edd_options = [];
    if(GA()->enabled()){
        $ga_tags_woo_options = [
            'woo_variable_as_simple' => GATags()->getOption('woo_variable_as_simple') ?? GA()->getOption('woo_variable_as_simple'),
            'woo_variations_use_parent_name' => GATags()->getOption('woo_variations_use_parent_name') ?? GA()->getOption('woo_variations_use_parent_name'),
            'woo_content_id' => GATags()->getOption('woo_content_id') ?? GA()->getOption('woo_content_id'),
            'woo_content_id_prefix' => GATags()->getOption('woo_content_id_prefix') ?? GA()->getOption('woo_content_id_prefix'),
            'woo_content_id_suffix' => GATags()->getOption('woo_content_id_suffix') ?? GA()->getOption('woo_content_id_suffix'),
        ];

        $ga_tags_edd_options = [
            'edd_content_id' => GATags()->getOption('edd_content_id') ?? GA()->getOption('edd_content_id'),
            'edd_content_id_prefix' => GATags()->getOption('edd_content_id_prefix') ?? GA()->getOption('edd_content_id_prefix'),
            'edd_content_id_suffix' => GATags()->getOption('edd_content_id_suffix') ?? GA()->getOption('edd_content_id_suffix'),
        ];
    }
    else{
        return false;
    }
    GATags()->updateOptions($ga_tags_woo_options);
    GATags()->updateOptions($ga_tags_edd_options);
}
function migrate_9_0_0() {
    $globalOptions = [
        "automatic_events_enabled" => PYS()->getOption("signal_events_enabled") || PYS()->getOption("automatic_events_enabled"),
        "automatic_event_form_enabled" => PYS()->getOption("signal_form_enabled"),
        "automatic_event_download_enabled" => PYS()->getOption("signal_download_enabled"),
        "automatic_event_comment_enabled" => PYS()->getOption("signal_comment_enabled"),
        "automatic_event_scroll_enabled" => PYS()->getOption("signal_page_scroll_enabled"),
        "automatic_event_time_on_page_enabled" => PYS()->getOption("signal_time_on_page_enabled"),
        "automatic_event_scroll_value" => PYS()->getOption("signal_page_scroll_value"),
        "automatic_event_time_on_page_value" => PYS()->getOption("signal_time_on_page_value"),
        "automatic_event_download_extensions" => PYS()->getOption("download_event_extensions"),
    ];
    PYS()->updateOptions($globalOptions);
}

function migrate_7_1_0_bing_defaults() {

    $bing_defaults = array(
        'gdpr_bing_prior_consent_enabled' => true,
        'gdpr_cookiebot_bing_consent_category' => 'marketing',
    );

    // update settings
    PYS()->updateOptions( $bing_defaults );
    PYS()->reloadOptions();

}
