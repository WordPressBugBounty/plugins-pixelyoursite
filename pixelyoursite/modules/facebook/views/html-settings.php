<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<h2 class="section-title">Facebook Settings</h2>

<!-- General -->
<div class="card card-static">
	<div class="card-header">
		General
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col">
				<?php Facebook()->render_switcher_input( 'advanced_matching_enabled' ); ?>
				<h4 class="switcher-label">Enable Advanced Matching</h4>
			</div>
		</div>
		<div class="row">
			<div class="col">
				<?php Facebook()->render_switcher_input( 'remove_metadata' ); ?>
				<h4 class="switcher-label">autoConfig: false</h4>
                <p><small>Remove Facebook default events</small></p>
			</div>
		</div>
		<div class="row">
			<div class="col">
				<?php Facebook()->render_switcher_input( 'disable_noscript' ); ?>
				<h4 class="switcher-label">Disable noscript</h4>
			</div>
		</div>

        <!--
        <div class="row">
            <div class="col">
                <?php Facebook()->render_switcher_input( 'send_external_id_demo',false,true ); ?>
                <h4 class="switcher-label">Send external id</h4>
                <?php renderProBadge(); ?>
            </div>
        </div>
        -->
	</div>
</div>

<!-- Medical Switch -->
<div class="card card-static">
    <div class="card-header">
        Medical Content
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <?php Facebook()->render_switcher_input( 'enabled_medical' ); ?>
                <h4 class="switcher-label"><?php _e('Don\'t track parameters', 'pys');?></h4>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col">
                <p>
                    Meta imposes restrictions on tracking data for websites with medical-related content and products. Use this option to disable event parameters that might track such data. These settings apply to Meta Pixel and CAPI events. To disable parameters for all tags, use the default parameter controls.
                </p>
                <p>
                    To replace the standard WooCommerce AddToCart or Purchase events with custom events, you’ll need the Pro version, as this functionality is only available on the Events Page in Pro. <?php renderProBadge(); ?>
                </p>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col">
                <p><?php _e('Don\'t track these parameters for the Meta pixel and CAPI events:', 'pys');?></p>
                <?php Facebook()->render_multi_select_input('do_not_track_medical_param', getAllMetaEventParamName()); ?>
                <p><?php _e('If you want to disable parameters for all tags, use the default options from the plugin\'s main page, or from the WooCommerce and EDD pages.', 'pys');?></p>
            </div>
        </div>
    </div>
</div>
<div class="panel">
    <div class="row">
        <div class="col text-center">
            <p class="mb-0">Fire more events and parameters and improve your ads performance.
                <br><a href="https://www.pixelyoursite.com/facebook-pixel-plugin?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-facebook-settings"
                        target="_blank">Find more about the PRO Meta Pixel (formerly Facebook Pixel) implementation</a></p>
        </div>
    </div>
</div>

<hr>
<div class="row justify-content-center">
	<div class="col-4">
		<button class="btn btn-block btn-save">Save Settings</button>
	</div>
</div>