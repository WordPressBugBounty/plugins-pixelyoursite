<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/** @var PYS $this */

include "html-popovers.php";

?>

<div class="wrap">
    <h1><?php _e( 'PixelYourSite', 'pys' ); ?></h1>
    <div id="pys">
        <div class="container">
            <form method="post" enctype="multipart/form-data">

                <?php wp_nonce_field( 'pys_save_settings' ); ?>

            <div class="row">
                <div class="col-12">
                    <h2 class="section-title mt-3">Global Settings</h2>

                    <div class="panel">
                        <div class="row mb-3">
                            <div class="col-12 mb-2">
                                <?php PYS()->render_switcher_input("server_event_use_ajax" ); ?>
                                <h4 class="switcher-label">Use Ajax when API is enabled, or when external_id's are used. Keep this option active if you use a cache.</h4>
                                <div><small class="mt-1">Use Ajax when Meta conversion API, or Pinterest API are enabled, or when external_id's are used. This helps serving unique event_id values for each pair of browser/server events, ensuring deduplication works. It also ensures uniques external_id's are used for each user. Keep this option active if you use a cache solution that can serve the same event_id or the same external_id multiple times.</small></div>
                            </div>
                            <div class="col-12">
                                <?php PYS()->render_switcher_input("server_static_event_use_ajax" ); ?>
                                <h4 class="switcher-label">Use Ajax for <b>Static events</b> when API is enabled.</h4>
                                <div><small>Do not use AJAX requests for static events if it interferes with page loading, or if the requests during loading block other site functions (such as updating the cart during loading).</small></div>
                                <hr>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="form-inline">
                                    <?php PYS()->render_switcher_input( 'send_external_id' ); ?>
                                    <h4 class="switcher-label">Use external_id</h4>
                                </div>
                                <small class="mt-1">We will store it in cookie called pbid</small>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col">
                                <div class="form-inline">
                                    <?php PYS()->render_switcher_input( 'external_id_use_transient' ); ?>
                                    <h4 class="switcher-label">Use transient WP for storage external_id</h4>
                                </div>
                                <small class="mt-1">With this storage method, the data is saved in the WordPress database, for 10 minutes. After the lifetime expires, the data will be deleted or overwritten (the row in the database will be removed).</small>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col">
                                <div class="form-inline">
                                    <label>external_id expire days for cookie:</label>
                                    <?php PYS()->render_number_input( 'external_id_expire', '', false, 365, 1); ?>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col">
                                <div class="form-inline">
                                    <?php PYS()->render_switcher_input( 'track_cookie_for_subdomains' ); ?>
                                    <h4 class="switcher-label">Track domains and subdomains together</h4>
                                </div>
                                <small class="mt-1">Enable this option if you want unified tracking for our native landing pages, traffic sources, and UTMs data. When there are different installations, this option must be enabled on both the domain and the subdomain.</small>
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col">
                                <?php PYS()->render_switcher_input( 'debug_enabled' ); ?>
                                <h4 class="switcher-label">Debugging mode. You will be able to see details about the events inside your
                                    browser console (developer tools).</h4>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <div class="form-inline">
                                    <?php PYS()->render_switcher_input('session_disable'); ?>
                                    <h4 class="switcher-label">Disable PHP sessions</h4>
                                </div>

                                <small class="mt-1">If you are having problems with sessions or cache when the plugin is enabled due to the creation of the PHPSESSID cookie, disable this option. This may reduce the effectiveness of some of our session-based parameters, such as landing page, traffic source, or UTM.</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <?php PYS()->render_switcher_input( 'enable_remove_source_url_params' ); ?>
                                <h4 class="switcher-label">Remove URL parameters from  <i>event_source_url</i></h4>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col">
                                <?php PYS()->render_switcher_input( 'enable_remove_download_url_param' ); ?>
                                <h4 class="switcher-label">Remove download_url parameters.</h4>

                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <div class="form-inline">
                                    <?php PYS()->render_switcher_input('compress_front_js'); ?>
                                    <h4 class="switcher-label">Compress frontend js</h4>
                                </div>

                                <small class="mt-1">Compress JS files (please test all your events if you enable this option because it can create conflicts with various caches).</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <div class="form-inline">
                                    <?php PYS()->render_switcher_input('hide_version_plugin_in_console'); ?>
                                    <h4 class="switcher-label">Remove the name of the plugin from the console</h4>
                                </div>

                                <small class="mt-1">Once ON, we remove all mentions about the plugin or add-ons from the console.</small>
                            </div>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col">
                                <h4 class="switcher-label">Advanced user-data detection</h4>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col">
                                <?php renderDummySwitcher(false); ?>
                                <h4 class="switcher-label">Forms <a href="https://www.youtube.com/watch?v=snUKcsTbvCk" target="_blank">Watch video</a></h4>
                                <?php renderProBadge(); ?>
                                <small class="mt-1 d-block">
                                    You can define the form's fields we can use by adding their names in these fields.
                                </small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <?php renderDummySwitcher(false); ?>
                                <h4 class="switcher-label">URL Parameters <a href="https://www.youtube.com/watch?v=7kigOV2-tAI" target="_blank">Watch video</a></h4>
                                <?php renderProBadge(); ?>
                                <small class="mt-1 d-block">
                                    You can define URL parameters using this format: [url_parameter-name-here]. Example: [url_utm_term] will take the value from a utm_term parameter if it's present.
                                </small>
                            </div>
                        </div>

                        <hr>

                        <div class="row align-items-center mb-2">
                            <div class="col-12">
                                <div class="custom-controls-stacked">
                                    <label>Data persistency</label>
                                    <?php PYS()->render_radio_input( 'data_persistency', 'keep_data',
                                        'Keep the data in the browser for as long as possible' ); ?>
                                    <?php PYS()->render_radio_input( 'data_persistency', 'recent_data',
                                        'Use the most recent data' ); ?>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row align-items-center mb-2">
                            <div class="col-12">
                                <h4 class="switcher-label">Reports attribution</h4>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col">
                                <div class="form-inline">
                                    <label>First Visit Options:</label>
                                    <?php renderDummyNumberInput(7); ?>
                                    <label>day(s)</label>
                                    <a class="ml-3 badge badge-pill badge-pro" href="https://www.pixelyoursite.com/?utm_source=pys-free-plugin&amp;utm_medium=pro-badge&amp;utm_campaign=pro-feature/?utm_source=pys-free-plugin&amp;utm_medium=pro-badge&amp;utm_campaign=pro-feature" target="_blank">Pro Feature <i class="fa fa-external-link" aria-hidden="true"></i></a>
                                </div>
                                <small class="mt-1">Define for how long we will store cookies for the "First Visit" attribution model.
                                    Used for events parameters (<i>landing page, traffic source, UTMs</i>) and WooCommerce or EDD Reports.
                                </small>

                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col">
                                <div class="form-inline">
                                    <label>Last Visit Options:</label>
                                    <?php renderDummyNumberInput(60); ?>
                                    <label>min</label>
                                    <a class="ml-3 badge badge-pill badge-pro" href="https://www.pixelyoursite.com/?utm_source=pys-free-plugin&amp;utm_medium=pro-badge&amp;utm_campaign=pro-feature/?utm_source=pys-free-plugin&amp;utm_medium=pro-badge&amp;utm_campaign=pro-feature" target="_blank">Pro Feature <i class="fa fa-external-link" aria-hidden="true"></i></a>
                                </div>

                                <small class="mt-1">Define for how long we will store the cookies for the "Last Visit" attribution model.
                                    Used for events parameters (<i>landing page, traffic source, UTMs</i>) and WooCommerce or EDD Reports.</small>

                            </div>
                        </div>
                        <div class="row">
                            <div class="col collapse-inner">
                                <label>Attribution model for events parameters:<a class="ml-3 badge badge-pill badge-pro" href="https://www.pixelyoursite.com/?utm_source=pys-free-plugin&amp;utm_medium=pro-badge&amp;utm_campaign=pro-feature/?utm_source=pys-free-plugin&amp;utm_medium=pro-badge&amp;utm_campaign=pro-feature" target="_blank">Pro Feature <i class="fa fa-external-link" aria-hidden="true"></i></a></label>
                                <div class="custom-controls-stacked">
                                    <?php renderDummyRadioInput( 'First Visit',true ); ?>
                                    <?php renderDummyRadioInput( 'Last Visit',false ); ?>
                                </div>
                                <hr/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <?php PYS()->render_switcher_input('block_robot_enabled', false); ?>
                                <h4 class="switcher-label">Disable the plugin for known web crawlers</h4>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <h4 class="label">Exclude these robots from blocking</h4>
	                            <?php PYS()->render_tags_select_input('exclude_blocked_robots',false); ?>
                                <small class="mt-2 d-block">
                                    You can exclude robots by their user-agent. You can use either the full name or part of it.
                                </small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <?php PYS()->render_switcher_input('block_ip_enabled'); ?>
                                <h4 class="switcher-label">Disable the plugin for these IP addresses:</h4>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <?php PYS()->render_tags_select_input('blocked_ips',false); ?>
                            </div>
                        </div>

                        <hr>
                        <div class="row form-group">
                            <div class="col">
                                <h4 class="label">Ignore these user roles from tracking:</h4>
                                <?php PYS()->render_multi_select_input( 'do_not_track_user_roles', getAvailableUserRoles() ); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <h4 class="label">Permissions:</h4>
                                <?php PYS()->render_multi_select_input( 'admin_permissions', getAvailableUserRoles() ); ?>
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="row">
                            <div class="col">
                                <div class="d-flex justify-content-between">
                                    <span class="mt-2">Track more key actions with the PRO version:</span>
                                    <a target="_blank" class="btn btn-sm btn-primary float-right" href="https://www.pixelyoursite.com/facebook-pixel-plugin/buy-pixelyoursite-pro?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-upgrade-blue">UPGRADE</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="row justify-content-center">
                        <div class="col-4">
                            <button class="btn btn-block btn-save">Save Settings</button>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

    <?php function enableEventForEachPixel($event, $fb = true, $ga = true, $ads = true, $bi = true, $tic = true, $pin = true)
{ ?>
    <?php if ($fb && Facebook()->enabled()) : ?>
    <div class="row">
        <div class="col">
            <?php Facebook()->render_switcher_input($event); ?>
            <h4 class="switcher-label">Enable on Facebook</h4>
        </div>
    </div>
<?php endif; ?>
    <?php if ($ga && GA()->enabled()) : ?>
    <div class="row">
        <div class="col">
            <?php GA()->render_switcher_input($event); ?>
            <h4 class="switcher-label">Enable on Google Analytics</h4>
        </div>
    </div>

<?php endif; ?>


    <?php if ($bi && Bing()->enabled()) : ?>
    <div class="row">
        <div class="col">
            <?php Bing()->render_switcher_input($event); ?>
            <h4 class="switcher-label">Enable on Bing</h4>
        </div>
    </div>
<?php endif; ?>
    <?php if ($pin && Pinterest()->enabled()) : ?>
    <div class="row">
        <div class="col">
            <?php Pinterest()->render_switcher_input($event); ?>
            <h4 class="switcher-label">Enable on Pinterest</h4>
        </div>
    </div>
<?php endif; ?>

    <?php
}
