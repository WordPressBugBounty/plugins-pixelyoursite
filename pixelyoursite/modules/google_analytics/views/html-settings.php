<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\GA\Helpers;

?>

<h2 class="section-title">Google Tags Settings</h2>

<!-- General -->
<div class="card card-static">
    <div class="card-header">
        General
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col">
				<?php PYS()->render_switcher_input('google_consent_mode'); ?>
                <h4 class="switcher-label">Fire Google tags with consent mode granted</h4>

                <p class="mt-1 mb-0">
                    How to enable Google Consent Mode V2:
                    <a href="https://www.pixelyoursite.com/google-consent-mode-v2-wordpress?utm_source=plugin&utm_medium=free&utm_campaign=google-consent" target="_blank">click here</a>
                </p>
            </div>
        </div>
        <hr>
        <h3>DataLayer Setting:</h3>
        <div class="row mb-3">
            <div class="col">
                <?php GATags()->render_radio_input('gtag_datalayer_type', 'default', 'Use PYS recommended name for data layer variable (recommended).'); ?>
                <?php GATags()->render_radio_input('gtag_datalayer_type', 'disable', 'Disable name transformation for the data layer (use dataLayer).'); ?>
                <?php GATags()->render_radio_input('gtag_datalayer_type', 'custom', 'Use this custom name for the data layer variable.'); ?>
                <div class="col col-offset-left mb-2">
                    <?php GATags()->render_text_input('gtag_datalayer_name', 'custom name for the data layer'); ?>
                </div>
            </div>
        </div>
        <hr>
        <div class="row mb-3">
            <div class="col">
                <?php GA()->renderDummySwitcher(); ?>
                <h4 class="switcher-label">Pass through ad click, client ID, and session ID information in URLs (url_passthrough) <?php renderProBadge(); ?></h4>
                <p class="mt-1 mb-0">
                    Reference:
                    <a href="https://developers.google.com/tag-platform/security/guides/consent?consentmode=advanced#gtag.js_5" target="_blank">click here</a>
                </p>
                <p class="mt-1 mb-0">
                    This option can improve tracking when cookies are denied: <a href="https://www.youtube.com/watch?v=wsNAbaoD5pM" target="_blank">watch video</a>
                </p>
            </div>
        </div>
        <hr>
        <div class="row mb-3">
            <div class="col">
                <?php GA()->renderDummySwitcher(); ?>
                <h4 class="switcher-label">Send user provided data when possible <?php renderProBadge(); ?></h4>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col col-offset-left">
                <div class="mb-1">
					<?php GA()->renderDummyCheckbox("Send multiple values when possible (up to 3 emails and phone numbers, up to 2 address fields)", true); ?>
                </div>
                <div class="mb-1">
                    <?php GA()->renderDummyCheckbox("Use encoding", true); ?>
                </div>
                <p>
                    <?php _e('Learn how to configure it: ', 'pys');?>
                    <a href="https://www.youtube.com/watch?v=uQ8t7RJhVvI" target="_blank">watch video</a>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Google Analytics Settings -->
<div class="card card-static">
	<div class="card-header">
        Google Analytics
	</div>
	<div class="card-body">
        <div class="row mb-4">
            <div class="col">
                <?php GA()->render_switcher_input( 'custom_page_view_event' ); ?>
                <h4 class="switcher-label">Control the page_view event</h4>
                <p class="mt-1 mb-0">
                    <small>Enable it if you use a GTM server container to fire API events. When we control the page_view event we can sent an event_id that is used for deduplication of API events.</small>
                </p>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col">
                <?php GA()->render_switcher_input( 'disable_noscript' ); ?>
                <h4 class="switcher-label">Disable noscript</h4>
            </div>
        </div>

            <div class="row">
                <div class="col">
                    <?php GA()->render_switcher_input( 'disable_advertising_features' ); ?>
                    <h4 class="switcher-label">Disable all advertising features</h4>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <?php GA()->render_switcher_input( 'disable_advertising_personalization' ); ?>
                    <h4 class="switcher-label">Disable advertising personalization</h4>
                </div>
            </div>

	</div>
</div>

<!-- Ads Settings -->
<div class="card card-static">
    <div class="card-header">
        Google Ads <?php renderSpBadge(); ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
				<?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">Fire the page_view_event on posts</h4>
            </div>
        </div>
        <div class="row">
            <div class="col">
				<?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">Fire the page_view event on pages</h4>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col mt-2">
                <label>Fire the page_view event on custom post type:</label>
				<?php renderDummyTextInput( 'Post types' ); ?>
            </div>
        </div>
        <div class="row">
            <div class="col  form-inline">
                <label>google_business_vertical:</label><?php renderDummyTextInput( 'google_business_vertical' ); ?>
            </div>
        </div>
    </div>
</div>


<!-- Cross-Domain Tracking -->
<!-- @link: https://developers.google.com/analytics/devguides/collection/gtagjs/cross-domain -->
<div class="card card-static">
    <div class="card-header">
        Cross-Domain Tracking
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-11">
                <?php GA()->render_switcher_input( 'cross_domain_enabled' ); ?>
                <h4 class="switcher-label">Enable Cross-Domain Tracking</h4>
            </div>
            <div class="col-1">
                <?php renderPopoverButton( 'ga_cross_domain_tracking' ); ?>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col col-offset-left">
                <?php GA()->render_switcher_input( 'cross_domain_accept_incoming' ); ?>
                <h4 class="switcher-label">Accept incoming</h4>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-5 col-offset-left">
                <?php Helpers\renderCrossDomainDomain( 0 ); ?>
            </div>
        </div>

        <?php foreach ( GA()->getOption('cross_domain_domains') as $index => $domain ) : ?>

            <?php

            if ( $index === 0 ) {
                continue; // skip default ID
            }

            ?>

            <div class="row mt-3">
                <div class="col-5 col-offset-left">
                    <?php Helpers\renderCrossDomainDomain( $index ); ?>
                </div>
                <div class="col-2">
                    <button type="button" class="btn btn-sm remove-row">
                        <i class="fa fa-trash-o" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

        <?php endforeach; ?>

        <div class="row mt-3" id="pys_ga_cross_domain_domain" style="display: none;">
            <div class="col-5 col-offset-left">
                <input type="text" name="" id="" value="" placeholder="Enter domain" class="form-control">
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-sm remove-row">
                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-5 col-offset-left">
                <button class="btn btn-sm btn-block btn-primary" type="button"
                        id="pys_ga_add_cross_domain_domain">
                    Add Extra Domain
                </button>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="row">
        <div class="col text-center">
            <p class="mb-0">Track more actions with the PRO version.
                <br><a href="https://www.pixelyoursite.com/google-analytics?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-analytics-settings"
                        target="_blank">Find more about the Google Analytics pro implementation</a></p>
        </div>
    </div>
</div>

<hr>
<div class="row justify-content-center">
	<div class="col-4">
		<button class="btn btn-block btn-save">Save Settings</button>
	</div>
</div>

<script type="application/javascript">
    jQuery(document).ready(function ($) {

        $('#pys_ga_add_cross_domain_domain').click(function (e) {

            e.preventDefault();

            var $row = $('#pys_ga_cross_domain_domain').clone()
                .insertBefore('#pys_ga_cross_domain_domain')
                .attr('id', '')
                .css('display', 'flex');

            $('input[type="text"]', $row)
                .attr('name', 'pys[ga][cross_domain_domains][]');

        });

        $(document).on('click', '.remove-row', function () {
            $(this).closest('.row').remove();
        });

    });
</script>