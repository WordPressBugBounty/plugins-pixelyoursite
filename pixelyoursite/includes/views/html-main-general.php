<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<!-- Pixel IDs -->
<div class="card card-static">
    <div class="card-header">
        <div class="row align-items-center mt-2">
            <div class="col-12">
                <?php PYS()->render_switcher_input("enable_all_tracking_ids"); ?>
                <h4 class="switcher-label">Tracking IDs</h4>
            </div>
        </div>
    </div>
    <div class="card-body">

        <div class="row align-items-center mb-3">
            <div class="col-2  py-2">
                <img class="tag-logo" src="<?php echo PYS_FREE_URL; ?>/dist/images/facebook-small-square.svg">
            </div>
            <div class="col-6">
                Your Meta Pixel (formerly Facebook Pixel)
            </div>
            <div class="col-4">
                <label for="fb_settings_switch" class="btn btn-block btn-sm btn-primary btn-settings">Click for settings</label>
            </div>
        </div>
        <input type="checkbox" id="fb_settings_switch" style="display: none">
        <div class="settings_content">
            <div class="row  mb-2">
                <div class="col-12">
                    <?php Facebook()->render_switcher_input("use_server_api"); ?>
                    <h4 class="switcher-label">Enable Conversion API (add the token below)</h4>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <?php Facebook()->render_switcher_input( 'advanced_matching_enabled' ); ?>
                    <h4 class="switcher-label">Enable Advanced Matching</h4>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <p>
                        Learn about Conversion API and Advanced Matching - privacy and consent:
                        <a href="https://www.youtube.com/watch?v=PsKdCkKNeLU" target="_blank">watch video</a>
                    </p>
                    <p>
                        Install multiple Facebook Pixles with CAPI support:
                        <a href="https://www.youtube.com/watch?v=HM98mGZshvc" target="_blank">watch video</a>
                    </p>
                    <p>
                        Learn about EMQ - why it matters and how to improve it:
                        <a href="https://www.youtube.com/watch?v=8d3yeg7s9A4" target="_blank">watch video</a>
                    </p>
                </div>
            </div>

            <div class="plate pt-3 pb-3 mb-3">

                <div class="row mb-3">
                    <div class="col">
                        <?php Facebook()->render_switcher_input( 'enabled' ); ?>
                        <h4 class="switcher-label">Enable Pixel</h4>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <h4 class="label">Meta Pixel (formerly Facebook Pixel) ID:</h4>
                        <?php Facebook()->render_pixel_id( 'pixel_id', 'Meta Pixel (formerly Facebook Pixel) ID' ); ?>
                        <small class="form-text">
                            <a href="https://www.pixelyoursite.com/pixelyoursite-free-version/add-your-facebook-pixel?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-ids"
                               target="_blank">How to get it?</a>
                        </small>
                        <p class="mt-3 mb-0">Add multiple Meta Pixel (formerly Facebook Pixel)s with the <a href="https://www.pixelyoursite.com/?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-ids"
                                                                                      target="_blank">pro version</a>.</p>
                    </div>
                </div>
                <div class="row align-items-center mb-3">
                    <div class="col-12">
                        <h4 class="label">Conversion API (recommended):</h4>
                        <?php Facebook()->render_text_area_array_item("server_access_api_token","Api token") ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col">
                        Send events directly from your web server to Facebook through the Conversion API. This can help you capture more events. An access token is required to use the server-side API.
                        <a href='https://www.pixelyoursite.com/facebook-conversion-api-capi' target='_blank'>Learn how to generate the token and how to test Conversion API</a>
                    </div>
                </div>

                <div class="row align-items-center mb-3">
                    <div class="col-12">
                        <h4 class="label">test_event_code :</h4>
                        <?php Facebook()->render_text_input_array_item("test_api_event_code","Code"); ?>
                        <?php Facebook()->render_text_input_array_item("test_api_event_code_expiration_at", "", 0, true); ?>

                        <small class="form-text">
                            Use this if you need to test the server-side event. <strong>Remove it after
                                testing.</strong> The code will auto-delete itself after 24 hours.
                        </small>
                    </div>
                </div>
                <?php if(isWPMLActive()) : ?>
                    <div class="row mb-3">
                        <div class="col-12">
                            <strong>WPML Detected. </strong> With the <a target="_blank" href="https://www.pixelyoursite.com/plugins/pixelyoursite-professional?utm_medium=plugin&utm_campaign=multilingual">Advanced and Agency</a> licenses, you can fire a different pixel for each language.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php addMetaTagFields(Facebook(),"https://www.pixelyoursite.com/verify-domain-facebook"); ?>
        </div>

        <hr>

            <div class="row align-items-center mb-3">
                <div class="col-2 py-2">
                    <img class="tag-logo" src="<?php echo PYS_FREE_URL; ?>/dist/images/analytics-square-small.svg">
                </div>
                <div class="col-6">
                    Your Google Analytics
                </div>
                <div class="col-4">
                    <label for="gan_settings_switch" class="btn btn-block btn-sm btn-primary btn-settings">Click for settings</label>
                </div>
            </div>

                    <?php
                    $noticeRenderNotSupportUA = false;
                    $noticeOnlyUA = true;
                    if(GA()->enabled() && !empty(GA()->getOption( 'tracking_id' )))
                    {
                        $trackingId = GA()->getOption('tracking_id');
                        if (!isGaV4($trackingId)) {
                            $noticeRenderNotSupportUA = true;
                        }
                        else{
                            $noticeOnlyUA = false;
                        }
                    }
                    if($noticeRenderNotSupportUA){
                        ?>
                        <div class="row align-items-center mb-3 py-2 not-supported">
                        <div class="col-12">
                        <?php
                        if($noticeOnlyUA){
                            ?>
                            <p>The old Universal Analytics properties are not supported by Google Analytics anymore. You must use the new GA4 properties instead. <a href="https://www.youtube.com/watch?v=KkiGbfl1q48" target="_blank">Watch this video to find how to get your GA4 tag</a>.</p>
                            <?php
                        }
                        else{
                            ?>
                            <p>Your old Universal Analytics property does't send data anymore, consider removing it. Google Analytics supports only GA4 properties. <a href="https://www.youtube.com/watch?v=KkiGbfl1q48" target="_blank">Watch this video to find how to get your GA4 tag</a>.</p>
                            <?php
                        }
                        ?>
                        </div>
                        </div>
                        <?php
                    }?>

        <input type="checkbox" id="gan_settings_switch" style="display: none">
        <div class="settings_content">
            <div class="plate pt-3 pb-3">
                <div class="row mb-2">
                    <div class="col">
						<?php GA()->render_switcher_input( 'enabled' ); ?>
                        <h4 class="switcher-label">Enable Pixel</h4>
                    </div>
                </div>
                <div class="row  mb-2">
                    <div class="col-12">
                        <?php renderDummySwitcher(false); ?>
                        <h4 class="switcher-label">Enable Measurement Protocol (add the api_secret)</h4>
                        <?php renderProBadge(); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <h4 class="label mb-3 mt-3">Google Analytics tracking ID:</h4>
                        <?php GA()->render_pixel_id( 'tracking_id', 'Google Analytics tracking ID' ); ?>
                        <p class="ga_pixel_info small">
                            <?php
                            $pixels = GA()->getPixelIDs();
                            if (count($pixels)) {
                                if (strpos($pixels[0], 'G') === 0) {
                                    echo 'We identified this tag as a GA4 property.';
                                } else {
                                    echo '<span class="not-support-tag">We identified this tag as a Google Analytics Universal property.</span>';
                                }
                            }

                            ?>
                        </p>
                        <small class="form-text" mb-2>
                            <a href="https://www.pixelyoursite.com/pixelyoursite-free-version/add-your-google-analytics-code?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-ids"
                               target="_blank">How to get it?</a>
                        </small>
                        <div class="row align-items-center mb-3">
                            <div class="col-12">
                                <h4 class="label">Measurement Protocol API secret: <?php renderProBadge(); ?></h4>

                                <?php renderDummyTextInput("API secret") ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col">
                                Generate the API secret inside your Google Analytics account: navigate to <b>Admin > Data Streams > choose your stream > Measurement Protocol API secrets</b>. The Measurement Protocol is used for WooCommerce and Easy Digital Downloads "Google Analytics Advanced Purchase Tracking" and refund tracking. Required for GA4 properties only.
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <input type="checkbox" class="custom-control-input"
                                       name="pys[ga][is_enable_debug_mode][-1]" value="0" checked/>
                                <?php GA()->render_checkbox_input_array("is_enable_debug_mode", "Enable Analytics Debug mode for this property"); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row align-items-center mb-3">
                            <div class="col-12">
                                <div class="mb-1">
                                    <input type="text" class="custom-control-input"
                                           name="pys[ga][enable_server_container][-1]" value="0" checked/>
                                    <?php GA()->render_switcher_input("enable_server_container"); ?>
                                    <h4 class="switcher-label">Enable Server container url (Beta)</h4>
                                </div>
                                <p>
                                    <?php _e('Learn how to use it: ', 'pys');?>
                                    <a href="https://www.youtube.com/watch?v=WZnmSoSJyBc" target="_blank">watch video</a>
                                </p>
                            </div>
                        </div>
                        <div class="row align-items-center mb-3">
                            <div class="col-12">
                                <h4 class="label">Server container url (optional): </h4>
                                <input type="text" class="custom-control-input"
                                       name="pys[ga][server_container_url][-1]" value="0" checked/>
                                <?php GA()->render_text_input_array_item("server_container_url", "https://analytics.example.com", 0); ?>
                            </div>
                        </div>
                        <div class="row align-items-center mb-3">
                            <div class="col-12">
                                <h4 class="label">Transport url (optional): </h4>
                                <input type="text" class="custom-control-input"
                                       name="pys[ga][transport_url][-1]" value="0" checked/>
                                <?php GA()->render_text_input_array_item("transport_url", "https://tagging.mywebsite.com", 0); ?>
                            </div>
                        </div>
                        <div class="row align-items-center mb-3">
                            <div class="col-12">
                                <input type="text" class="custom-control-input"
                                       name="pys[ga][first_party_collection][-1]" value="0" checked/>
                                <?php GA()->render_checkbox_input("first_party_collection", "First party cookies selector first_party_collection (recommended)"); ?>
                            </div>
                        </div>
                        <hr>
                        <p class="mt-1">
                            <strong>How to enable Google Consent Mode V2:</strong>
                            <a href="https://www.pixelyoursite.com/google-consent-mode-v2-wordpress?utm_source=plugin&utm_medium=free&utm_campaign=google-consent" target="_blank">click here</a>
                        </p>
                        <p>
                            Learn how to get the Google Analytics 4 tag ID and how to test it:
                            <a href="https://www.youtube.com/watch?v=KkiGbfl1q48" target="_blank">watch video</a>
                        </p>
                        <p class="mt-3 ">Add multiple Google Analytics tags with the <a href="https://www.pixelyoursite.com/?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-ids"
                                                                                        target="_blank">pro version</a>.</p>
                        <p>
                            Install the old Google Analytics UA property and the new GA4 at the same time:
                            <a href="https://www.youtube.com/watch?v=JUuss5sewxg" target="_blank">watch video</a>
                        </p>
                    </div>
                </div>

                <?php if(isWPMLActive()) : ?>
                    <div class="row mb-3">
                        <div class="col-12">
                            <strong>WPML Detected. </strong> With the <a target="_blank" href="https://www.pixelyoursite.com/plugins/pixelyoursite-professional?utm_medium=plugin&utm_campaign=multilingual">Advanced and Agency</a> licenses, you can fire a different pixel for each language.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <hr>

        <?php do_action( 'pys_admin_pixel_ids' ); ?>

        <div class="row align-items-center">
            <div class="col-2 py-4">
                <img class="tag-logo" src="<?php echo PYS_FREE_URL; ?>/dist/images/google-ads-square-small.svg">
            </div>
            <div class="col-10">
                Add the Google Ads tag with the <a
                        href="https://www.pixelyoursite.com/google-ads-tag?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-ids"
                        target="_blank">pro version</a>.
                <div class="mt-3">
                    <strong>How to enable Google Consent Mode V2:</strong>
                    <a href="https://www.pixelyoursite.com/google-consent-mode-v2-wordpress?utm_source=plugin&utm_medium=free&utm_campaign=google-consent" target="_blank">click here</a>
                </div>
                <div class="mt-3">
                    Learn how to install the Google Ads Tag:
                    <a href="https://www.youtube.com/watch?v=dft-TRigkj0" target="_blank">watch video</a>
                </div>
                <div class="mt-3">
                    How to configure Google Ads Conversions:
                    <a href="https://www.youtube.com/watch?v=5kb-jQe-Psg" target="_blank">watch video</a>
                </div>
                <div class="mt-3">
                    Lear how to use Enhanced Conversions:
                    <a href="https://www.youtube.com/watch?v=-bN5D_HJyuA" target="_blank">watch video</a>
                </div>
            </div>
        </div>
        <hr>
        <div class="row align-items-center">
            <div class="col-2 py-4">
                <img class="tag-logo" src="<?php echo PYS_FREE_URL; ?>/dist/images/tiktok-logo.svg">
            </div>
            <div class="col-10">
                Add the TikTok tag with the <a
                        href="https://www.pixelyoursite.com/google-ads-tag?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-ids"
                        target="_blank">pro version</a>.
                <div class="small">TikTok Tag integration is in beta.</div>
                <div class="mt-3">
                    How to install the TikTok tag and how to enable TikTok API: <a href="https://www.youtube.com/watch?v=OCSR6zacnFM" target="_blank">watch video</a>
                </div>
            </div>
        </div>

    </div>
</div>

    <div class="card card-static">
        <div class="card-header">
            <div class="row">
                <div class="col-12">
                    GTM Tag
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row align-items-center mb-3 py-2">
                <div class="col-2">
                    <img class="tag-logo" src="<?php echo PYS_FREE_URL; ?>/dist/images/gtm-square-small.svg">
                </div>
                <div class="col-6">
                    Your GTM Tag
                </div>
                <div class="col-4">
                    <label for="gtm_settings_switch" class="btn btn-block btn-sm btn-primary btn-settings">Click
                        for settings</label>
                </div>
            </div>

            <input type="checkbox" id="gtm_settings_switch" style="display: none">
            <div class="settings_content">

                <div class="plate pixel_info pt-3">
                    <div class="row mb-2">
                        <div class="col">
							<?php GTM()->render_switcher_input( 'enabled' ); ?>
                            <h4 class="switcher-label">Enable GTM</h4>
                        </div>
                    </div>
                    <div class="row pt-3 pb-3">
                        <div class="col-12">
                            <h4 class="label">GTM Tag:</h4>
                            <?php GTM()->render_pixel_id('gtm_id', 'GTM-123456789'); ?>
                            <p>
                                <small>
                                    If you want to use the dataLayer setting without the container itself, enter "GTM-123456789" in the field and disable the container on the <a href="<?php echo buildAdminUrl( 'pixelyoursite', 'gtm_tags_settings' );?>">GTM settings page.</a>
                                </small>
                            </p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p>
                                <b>Beta version:</b> This feature is now in Beta and can change in the future.
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div class="row ">
                        <div class="col-12">
                            <?php GTM()->render_switcher_input('gtm_just_data_layer'); ?>
                            <h4 class="switcher-label"><?php _e('Send just the data layer', 'pys');?></h4>
                            <p>
                                <small>
                                    <?php _e('Turning ON this option will remove both the head and the body part of the container code but leave data layer code working. This should be only used in specific cases where you need to place the container code manually or using another tool.', 'pys');?>
                                </small>
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div class="row pt-2 pb-3">
                        <div class="col-12">
                            <p>
                                <?php _e('<b>Important:</b> Don\'t use GTM and our native integrations for the same tag/pixel ID. A pixel installed with the native integration must not be installed with GTM.', 'pys');?>
                            </p>
                            <hr>
                            <?php $containers = new gtmContainers();
                            $download_template_nonce = wp_create_nonce('download_template_nonce');
                            if(!empty($containers)):
                                ?>
                                <p>
                                    <b><?php _e('GTM Container Import:', 'pys');?> </b>
                                </p>
                                <?php
                                foreach ($containers->getContainers() as $container) {
                                    if (!$container['enable'] || empty($container['file_name'])) continue;
                                    ?>
                                    <p>
                                        <a href="<?php echo esc_url( add_query_arg(['download_container' => $container['file_name'], '_wpnonce_template_logs' => $download_template_nonce],buildAdminUrl( 'pixelyoursite', 'containers' ))); ?>" target="_blank"><?php echo $container['show_name']; ?></a><?php echo !empty($container['description']) ? ' - '.$container['description'] : '';?>
                                    </p>
                                    <?php
                                } ?>
                                <p class="mt-2">
                                    <?php _e('Learn how to use the file: ', 'pys');?> <a href="https://www.youtube.com/watch?v=qKJ3mmCgT3M" target="_blank">watch video</a>
                                </p>
                                <hr>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row pt-3 pb-3">
                        <div class="col-12">

                            <?php if ( isSuperPackActive() ) {
                                if ( SuperPack()->getOption( 'additional_ids_enabled' ) ) : ?>
                                    <p>
                                        <?php GTM()->render_checkbox_input( 'is_fire_signal', 'Fire the active automated events for this pixel' ); ?>
                                    </p>
                                    <?php if ( isWooCommerceActive() ) : ?>
                                        <p>
                                            <?php GTM()->render_checkbox_input( 'is_fire_woo', 'Fire the WooCommerce events for this pixel' ); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ( isEddActive() ) : ?>
                                        <p>
                                            <?php GTM()->render_checkbox_input( 'is_fire_edd', 'Fire the Easy Digital Downloads events for this pixel' ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <p>
                                        <strong>Display conditions:</strong>
                                        <?php
                                        $main_pixel = GTM()->getOption( 'main_pixel' );
                                        if ( !empty( $main_pixel ) && isset( $main_pixel[ 0 ] ) ) {
                                            $conditions = SuperPack\SPPixelId::fromArray( json_decode( $main_pixel[ 0 ], true ) );
                                            SuperPack\SpPixelCondition()->renderHtml( $conditions->displayConditions );
                                        } else {
                                            SuperPack\SpPixelCondition()->renderHtml();
                                        } ?>
                                    </p>
                                <?php endif; ?>

                                <?php
                                if ( SuperPack()->getOption( 'enable_hide_this_tag_by_tags' ) || SuperPack()->getOption( 'enable_hide_this_tag_by_url' ) ) {
                                    GTM()->render_hide_pixel_block();
                                }
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<div class="panel panel-primary link_youtube">
    <div class="row">
        <div class="col">
            <p class="text-center">Subscribe to our YouTube Channel to learn how to use the plugin and improve tracking</p>
            <p class="text-center mb-0">
                <a href="https://www.youtube.com/channel/UCnie2zvwAjTLz9B4rqvAlFQ" class="btn btn-sm btn-save" target="_blank">Go to YouTube</a>
            </p>
        </div>
    </div>
</div>

<!-- video -->
<div class="card card-static">
    <div class="card-header">
        Recommended Videos:
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <p><a href="https://www.youtube.com/watch?v=Wv6KhJQqFL4" target="_blank">HOT: Secret Trick to Boost Your Meta EMQ Score: Facebook Login Integration (6:20) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=wUsqwomsYMo" target="_blank">Conditions: Improved Event Tracking - Meta, Google, TikTok, GTM (5:09) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=kWozitdarSA" target="_blank">How to use Custom Events for Meta Ads (7:49)</a></p>
                <p><a href="https://www.youtube.com/watch?v=7BNHWbLbbdg" target="_blank">Meta Limited Data Use - Privacy Options for USA States - WordPress (6:17 min) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=bEK3qaaRvNg" target="_blank">Google Tag Manager and PixelYourSite (7:48 min) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=DZzFP4pSitU" target="_blank">Meta Pixel (formerly Facebook Pixel), CAPI, and PixelYourSite MUST WATCH (8:19) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=QqAIO1ONc0I" target="_blank">How to test Facebook Conversion API (10:16 min) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=kEp5BDg7dP0" target="_blank">How to fire EVENTS with PixelYourSite (22:28) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=EvzGMAvBnbs" target="_blank">How to create Meta (Facebook) Custom Audiences & Lookalikes based on Events & Parameters (21:53) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=w97FATUy7ok" target="_blank">How to configure Custom Conversions on Meta (Facebook) based on Events & Parameters (11:03) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=snUKcsTbvCk" target="_blank">Improve META (Facebook) EMQ score with form automatic data detection (11:48) - watch now</a></p>
                <p><a href="https://www.youtube.com/watch?v=c4Hrb8WK5bw" target="_blank">Fire a LEAD event on form submit - WordPress & PixelYourSite (5:58) - watch video</a></p>
            </div>
        </div>
        <div class="row">
            <div class="col-8">
            </div>
            <div class="col-4 justify-content-end">
                <a href="https://www.youtube.com/channel/UCnie2zvwAjTLz9B4rqvAlFQ" target="_blank">Watch more on our YouTube channel</a>
            </div>
        </div>
    </div>
</div>

<!-- Global Events -->
<div class="card">
    <div class="card-header has_switch">
        <?php PYS()->render_switcher_input('automatic_events_enabled'); ?>Track key actions with the automatic
        events
        <?php
        if(!PYS()->getOption('automatic_events_enabled')) {
            cardCollapseBtn('style="display:none"');
        } else {
            cardCollapseBtn();
        } ?>
    </div>
    <div class="card-body">

        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_form_enabled'); ?>Track Forms <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <p>
                    The Form event will fire when a form is successfully submitted for the following plugins: Contact Form 7, Forminator, WP Forms, Formidable Pro, Ninja Forms, and Fluent Forms. For forms added by different means, we will fire the event when the submit button is clicked. Watch <a href="https://www.youtube.com/watch?v=c4Hrb8WK5bw" target="_blank">this video</a> to learn more.
                </p>
                <?php
                enableEventForEachPixel('automatic_event_form_enabled', true, true, true, true, true, true, true);
                ?>
                <br/>
                <p>Fires when the website visitor clicks form submit buttons.</p>
                <br>
                <?php
                $eventsFormFactory = apply_filters("pys_form_event_factory",[]);
                foreach ($eventsFormFactory as $activeFormPlugin) : ?>
                    <p><strong><?php echo $activeFormPlugin->getName(); ?> detected</strong> - we will fire the Form event for each successfully submited form.</p>

                <?php
                endforeach;
                if($eventsFormFactory) :
                    ?>
                    <div class="col">
                        <?php PYS()->render_checkbox_input( 'enable_success_send_form',
                            'Fire the event only for the supported plugins, when the form is succesfully submited.' ); ?>
                    </div>
                    <br>
                    <p>Configure Lead or other events using our <a href="<?php echo buildAdminUrl( 'pixelyoursite', 'events' ); ?>">events triggers</a>. Learn how from <a href="https://www.youtube.com/watch?v=c4Hrb8WK5bw" target="_blank">this video</a></p>
                    <br>
                <?php endif; ?>
                <p><strong>Event name: </strong>Form</p>
                <p><strong>Event name on TikTok: </strong>FormSubmit</p>
                <p><strong>Specific parameters: </strong><i>text, from_class, form_id</i></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_signup_enabled'); ?>Track user signup <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <?php if ( Facebook()->enabled()) : ?>
                    <div class="row">
                        <div class="col">
                            <?php if(isWooCommerceActive()
                                &&  Facebook()->getOption("woo_complete_registration_fire_every_time")
                            ) :
                                Facebook()->render_switcher_input('automatic_event_signup_enabled_disable',false,true);
                                ?>
                                <h4 class="switcher-label">Enable on Facebook</h4>
                                <div class="small ml-2">
                                    Facebook CompleteReservation is fired every time a WooCommerce takes place.<br/>
                                    You can change this from the WooCommerce events
                                    <a href="<?=buildAdminUrl( 'pixelyoursite', 'woo' )?>" target="_blank">
                                        settings
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php Facebook()->render_switcher_input('automatic_event_signup_enabled'); ?>
                                <h4 class="switcher-label">Enable on Facebook</h4>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ( GA()->enabled()) : ?>
                    <div class="row">
                        <div class="col">
                            <?php GA()->render_switcher_input('automatic_event_signup_enabled'); ?>
                            <h4 class="switcher-label">Enable on Google Analytics</h4>
                        </div>
                    </div>

                <?php endif; ?>

                <?php if ( Bing()->enabled()) : ?>
                    <div class="row">
                        <div class="col">
                            <?php Bing()->render_switcher_input('automatic_event_signup_enabled'); ?>
                            <h4 class="switcher-label">Enable on Bing</h4>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ( Pinterest()->enabled()) : ?>
                    <div class="row">
                        <div class="col">
                            <?php Pinterest()->render_switcher_input('automatic_event_signup_enabled'); ?>
                            <h4 class="switcher-label">Enable on Pinterest</h4>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( GTM()->enabled()) : ?>
                    <hr class="mb-3 mt-3">
                    <div class="row">
                        <div class="col">
                            <?php GTM()->render_switcher_input('automatic_event_signup_enabled'); ?>
                            <h4 class="switcher-label">Enable on GTM dataLayer</h4>
                        </div>
                    </div>
                    <hr class="mb-3 mt-3">
                <?php endif; ?>
                <br/>
                <p>Fires when the website visitor signup for a WordPress account.</p>
                <p><strong>Event name: </strong></p>
                <p>
                    On Google Analytics the event is called sign_up (standard event).<br/>
                    On Google Ads the event is called sign_up (custom event)<br/>
                    On Facebook the event is called CompleteRegistration (standard event).<br/>
                    On Pinterest the event is called Signup (standard event).<br/>
                    On Bing the event is called sign_up (custom event)
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_login_enabled'); ?>Track user login <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <?php
                enableEventForEachPixel('automatic_event_login_enabled', true, true, true, true, true, false, true);
                ?>
                <br/>
                <p>Fires when the website visitor logins a WordPress account.</p>
                <p><strong>Event name: </strong></p>
                <p>On Google Analytics the event is called login (standard event).<br/>
                    On Google Ads the event is called login (custom event)<br/>
                    On Facebook, Pinterest and Bing, the event is called Login (custom event).</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_download_enabled'); ?>Track Downloads <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <?php
                enableEventForEachPixel('automatic_event_download_enabled', true, true, true, true, true, true, true);
                ?>
                <br/>
                <div>Extension of files to track as downloads:</div>
                <?php PYS()->render_tags_select_input('automatic_event_download_extensions'); ?>

                <p class="mt-2">Fires when the website visitor open files with the designated format.</p>
                <p><strong>Event name: </strong>Download</p>
                <p><strong>Specific parameters: </strong><i>download_type, download_name, download_url</i></p>
                <p class="small">
                    *Google Analytics 4 automatically tracks this action with an event called "file_download". If you want,
                    you can disable this event for Google Analytics
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_comment_enabled'); ?>Track comments <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <?php
                enableEventForEachPixel('automatic_event_comment_enabled', true, true, true, true, true, false, true);
                ?>
                <br/>
                <p>Fires when the website visitor ads a comment.</p>
                <p><strong>Event name: </strong>Comment</p>
            </div>
        </div>



        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_scroll_enabled'); ?>Track page scroll <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <?php
                enableEventForEachPixel('automatic_event_scroll_enabled', true, true, true, true, true, false, true);
                ?>
                <br/>
                <div class="mb-2 form-inline">
                    <label>Trigger for scroll value</label>
                    <?php PYS()->render_number_input('automatic_event_scroll_value', '', false, 100); ?>
                    <div>%</div>
                </div>

                <p>Fires when the website visitor scrolls the page.</p>
                <p><strong>Event name: </strong>PageScroll</p>
                <p class="small">*Google Analytics 4 automatically tracks 90% page scroll with an event called "scroll".
                    If you want, you can disable this event for Google Analytics</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_time_on_page_enabled'); ?>Track time on page <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <?php
                enableEventForEachPixel('automatic_event_time_on_page_enabled', true, true, true, true, true, false, true);
                ?>
                <br/>
                <div class="mb-2 form-inline">
                    <label>Trigger for time</label>
                    <?php PYS()->render_number_input('automatic_event_time_on_page_value', '', false, 100); ?>
                    <div>seconds</div>
                </div>
                <p><strong>Event name: </strong>TimeOnPage</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_404_enabled'); ?>Track 404 pages <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <?php
                enableEventForEachPixel('automatic_event_404', false, true, false, true, false, false, false);
                ?>
                <br/>
                <p><strong>Event name: </strong>404</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header has_switch">
                <?php PYS()->render_switcher_input('automatic_event_search_enabled'); ?>Track searches <?php cardCollapseBtn(); ?>
            </div>
            <div class="card-body">
                <?php
                enableEventForEachPixel('automatic_event_search_enabled', true, true, true, true, true, true, true);
                ?>
                <br/>
                <p><strong>Event name: </strong></p>
                <p>
                    On Google Analytics the event is called search (standard event).<br/>
                    On Google Ads the event is called search (custom event)<br/>
                    On Facebook, Pinterest called Search (standard event).<br/>
                    On Bing the event is called search (custom event).<br/>
                    On TikTok the event is called Search (standard event).
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-header has_switch">
                <?php renderDummySwitcher(); ?>Track AdSense <?php renderProBadge(); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php renderDummySwitcher(); ?>Track internal
                links <?php renderProBadge(); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php renderDummySwitcher(); ?>Track outbound
                links <?php renderProBadge(); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php renderDummySwitcher(); ?>Track embedded YouTube or
                Vimeo video views <?php renderProBadge(); ?>
            </div>

        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php renderDummySwitcher(); ?>Track tel links <?php renderProBadge(); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header has_switch">
                <?php renderDummySwitcher(); ?>Track email links <?php renderProBadge(); ?>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Ads for Blog Setup -->
<div class="card" >
    <div class="card-header has_switch">
        <?php PYS()->render_switcher_input('fdp_enabled'); ?>Dynamic Ads for Blog Setup <?php cardCollapseBtn(); ?>
    </div>
    <div class="card-body">
        <div class="row mt-3">
            <div class="col-11">
                This setup will help you to run Facebook Dynamic Product Ads for your blog content.
            </div>
        </div>
        <div class="row mt-3">
            <div class="col">
                <a href="https://www.pixelyoursite.com/facebook-dynamic-product-ads-for-wordpress" target="_blank">Click here to learn how to do it</a>
            </div>
        </div>
        <?php if ( Facebook()->enabled() ) : ?>
            <hr/>
            <div class="row mt-3">
                <div class="col">
                    <?php Facebook()->render_switcher_input( 'fdp_use_own_pixel_id',false,true ); ?>
                    <h4 class="switcher-label">
                        Fire this events just for this Pixel ID with the
                        <a href="https://www.pixelyoursite.com/?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-ids" target="_blank">pro version</a>
                    </h4>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-6">
                    <label>Meta Pixel (formerly Facebook Pixel) ID:</label>
                    <?php Facebook()->render_text_input( 'fdp_pixel_id',"",true ); ?>
                </div>
            </div>

            <hr/>

            <div class="row mt-3">
                <div class="col">
                    <label>Content_type</label><?php
                    $options = array(
                        'product'    => 'Product',
                        ''           => 'Empty'
                    );
                    Facebook()->render_select_input( 'fdp_content_type',$options ); ?>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col">
                    <label>Currency:</label><?php
                    $options = array();
                    $cur = getPysCurrencySymbols();
                    foreach ($cur as  $key => $val) {
                        $options[$key]=$key;
                    }
                    Facebook()->render_select_input( 'fdp_currency',$options ); ?>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col">
                    <?php Facebook()->render_switcher_input( 'fdp_view_content_enabled' ); ?>
                    <h4 class="switcher-label">Enable the ViewContent on every blog page</h4>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <?php Facebook()->render_switcher_input( 'fdp_view_category_enabled' ); ?>
                    <h4 class="switcher-label">Enable the ViewCategory on every blog categories page</h4>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-11">
                    <?php Facebook()->render_switcher_input( 'fdp_add_to_cart_enabled' ); ?>
                    <h4 class="switcher-label">Enable the AddToCart event on every blog page</h4>
                </div>

                <div class="col-11 form-inline col-offset-left">
                    <label>Value:</label>
                    <?php Facebook()->render_number_input( 'fdp_add_to_cart_value',"Value" ); ?>
                </div>

                <div class="col-11 form-inline col-offset-left">
                    <label>Fire the AddToCart when scroll to</label>
                    <?php Facebook()->render_number_input( 'fdp_add_to_cart_event_fire_scroll',50 ); ?>
                    <label>%</label>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-11">
                    <?php Facebook()->render_switcher_input( 'fdp_purchase_enabled' ); ?>
                    <h4 class="switcher-label">Enable the Purchase event on every blog page</h4>
                </div>
                <div class="col-11 form-inline col-offset-left">
                    <label>Value:</label>
                    <?php Facebook()->render_number_input( 'fdp_purchase_value',"Value" ); ?>
                </div>
                <div class="col-11 form-inline col-offset-left">
                    <label>Fire the Purchase event</label>

                    <?php
                    $options = array(
                        'scroll_pos'    => 'Page Scroll',
                        'comment'     => 'User commented',
                        'css_click'     => 'Click on CSS selector',
                        //Default event fires
                    );
                    Facebook()->render_select_input( 'fdp_purchase_event_fire',$options ); ?>
                    <span id="fdp_purchase_event_fire_scroll_block">
                        <?php Facebook()->render_number_input( 'fdp_purchase_event_fire_scroll',50 ); ?> <span>%</span>
                    </span>

                    <?php Facebook()->render_text_input( 'fdp_purchase_event_fire_css',"CSS selector"); ?>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col">
                    <strong>You need to upload your blog posts into a Facebook Product Catalog.</strong> You can do this with our dedicated plugin:
                    <a href="https://www.pixelyoursite.com/wordpress-feed-facebook-dpa" target="_blank">Click Here</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Active Events stat -->
<div class="card">
    <div class="card-header">
        Active Events:
    </div>
    <div class="card-body show" style="display: block;">
        <?php
        $customCount = EventsCustom()->getCount();
        //$customFdp = EventsFdp()->getCount();
        $signalEvents = EventsAutomatic()->getCount();
        $wooEvents = EventsWoo()->getCount();
        $eddEvents = EventsEdd()->getCount();


        $total = $customCount + $signalEvents + $wooEvents + $eddEvents;
        ?>
        <p><strong>You have <?=$total?> active events in total.</strong></p>
        <p>You have <?=$signalEvents?> automated active events. You can control them on this page.</p>
        <p>You have <?=$customCount?> manually added active events. You can control them on the <a href="<?=buildAdminUrl( 'pixelyoursite', 'events' )?>">Events page</a>.</p>
        <?php if(isWooCommerceActive()) : ?>
            <p>You have <?=$wooEvents?> WooCommerce active events. You can control them on the <a href="<?=buildAdminUrl( 'pixelyoursite', 'woo' )?>">WooCommerce page</a>.</p>
        <?php endif; ?>
        <?php if(isEddActive()) : ?>
            <p>You have <?=$eddEvents?> EDD active events. You can control them on the <a href="<?=buildAdminUrl( 'pixelyoursite', 'edd' )?>">EDD page</a>.</p>
        <?php endif; ?>
        <p class="mt-5 small">We count each manually added event, regardless of its name or targeted tag.</p>
        <p class="small">We don't count the Dynamic Ads for Blog events.</p>
    </div>
</div>

<!-- About Parameters -->
<div class="card">
    <div class="card-header">
        About Parameters:
    </div>
    <div class="card-body show" style="display: block;">
        <p>Parameters add extra information to events.

        <p>They help you create Custom Audiences or Custom Conversions on Facebook, Goals, and Audiences on Google,
            Audiences on Pinterest, Conversions on Bing.</p>

        <p>The plugin tracks the following parameters by default for all the events and for all installed
            tags: <i>page_title, post_type, post_id, landing_page, event_url, user_role, plugin, event_time (pro),
                event_day (pro), event_month (pro), traffic_source (pro), UTMs (pro).</i></p>

        <p>Facebook, Pinterest, and Google Ads Page View event also tracks the following parameters: <i>tags, category</i>.</p>

        <p>You can add extra parameters to events configured on the Events tab. WooCommerce or Easy Digital
            Downloads events will have the e-commerce parameters specific to each tag.</p>

        <p>The Search event has the specific search parameter.</p>

        <p>The automatic events have various specific parameters, depending on the action that fires the event.</p>
    </div>
</div>

<!-- Control global param -->
<div class="card">
    <div class="card-header">
        Control the Global Parameters <?php cardCollapseBtn(); ?>
    </div>
    <div class="card-body" >
        <div class="row mt-3 mb-3">
            <div class="col-12">
                You will have these parameters for all events, and for all installed tags. We recommend to
                keep these parameters active, but if you start to get privacy warnings about some of them,
                you can turn those parameters OFF.
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <hr>
                <?php PYS()->render_switcher_input("enable_page_title_param"); ?>
                <h4 class="switcher-label">page_title</h4>
                <hr>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <?php PYS()->render_switcher_input("enable_post_type_param"); ?>
                <h4 class="switcher-label">post_type</h4>
                <hr>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <?php PYS()->render_switcher_input('enable_post_category_param'); ?>
                <h4 class="switcher-label">post_category</h4>
                <hr>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <?php PYS()->render_switcher_input("enable_post_id_param"); ?>
                <h4 class="switcher-label">post_id</h4>
                <hr>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <?php PYS()->render_switcher_input( 'enable_content_name_param' ); ?>
                <h4 class="switcher-label">content_name</h4>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <?php PYS()->render_switcher_input( 'enable_event_url_param' ); ?>
                <h4 class="switcher-label">event_url</h4>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <?php PYS()->render_switcher_input( 'enable_user_role_param' ); ?>
                <h4 class="switcher-label">user_role</h4>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">landing_page(PRO)</h4>
                <hr>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">event_time (PRO)</h4>
                <hr>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">event_day (PRO)</h4>
                <hr>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">event_month (PRO)</h4>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">traffic_source (PRO)</h4>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">UTMs (PRO)</h4>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">tags (PRO)</h4>
                <hr>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">categories (PRO)</h4>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(true); ?>
                <h4 class="switcher-label">search (mandatory)</h4>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(true); ?>
                <h4 class="switcher-label">plugin (mandatory)</h4>
                <hr>
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

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const checkbox = document.getElementById("pys_core_enable_all_tracking_ids");
        const parentCard = checkbox.closest(".card");
        const cardBody = parentCard.querySelector(".card-body");

        function setCardBodyState(isEnabled) {
            if (isEnabled) {
                cardBody.classList.remove("disabled");
                cardBody.style.pointerEvents = "auto";
                cardBody.style.opacity = "1";
                cardBody.style.backgroundColor = "";
                cardBody.querySelectorAll("input, select, textarea, button").forEach(function(element) {
                    element.removeAttribute("disabled");
                });
            } else {
                cardBody.classList.add("disabled");
                cardBody.style.pointerEvents = "none";
                cardBody.style.opacity = "0.3";
                cardBody.style.backgroundColor = "rgba(0, 0, 0, 0.13)";
                cardBody.querySelectorAll("input, select, textarea, button").forEach(function(element) {
                    element.setAttribute("disabled", "disabled");
                });
            }
        }

        // Set the initial state
        setCardBodyState(checkbox.checked);

        // Checkbox state change handler
        checkbox.addEventListener("change", function() {
            setCardBodyState(this.checked);
        });
    });
</script>
    <?php function enableEventForEachPixel($event, $fb = true, $ga = true, $ads = true,$gtm = true, $bi = true, $tic = true, $pin = true)
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
    <?php if ($gtm && GTM()->enabled()) : ?>
    <hr class="mb-3 mt-3">
    <div class="row">
        <div class="col">
            <?php GTM()->render_switcher_input($event); ?>
            <h4 class="switcher-label">Enable on GTM dataLayer</h4>
        </div>
    </div>
    <hr class="mb-3 mt-3">
<?php endif; ?>
    <?php
}
