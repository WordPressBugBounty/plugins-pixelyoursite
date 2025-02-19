<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if(isset( $_REQUEST['id'] )) {
    $id = sanitize_key($_REQUEST['id']);
    $event = CustomEventFactory::getById($id );
} else {
    $event =  new CustomEvent();
}
$serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

?>

<div class="panel">
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-between">
                <span class="mt-2">With the pro version, you can fire events on clicks, form submit, video views, and more:</span>
                <a target="_blank" class="btn btn-sm btn-primary float-right" href="https://www.pixelyoursite.com/facebook-pixel-plugin/buy-pixelyoursite-pro?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-upgrade-blue">UPGRADE</a>
            </div>
        </div>
    </div>
</div>

<?php wp_nonce_field( 'pys_update_event' ); ?>
<input type="hidden" name="action" value="update">
<?php Events\renderHiddenInput( $event, 'post_id' ); ?>

<div class="card card-static">
	<div class="card-header">
		General
	</div>
	<div class="card-body">
        <div class="row mb-3">
            <div class="col">
				<?php Events\renderSwitcherInput( $event, 'enabled' ); ?>
                <h4 class="switcher-label">Enable event</h4>
            </div>
        </div>
		<div class="row">
			<div class="col">
				<?php Events\renderTextInput( $event, 'title', 'Enter event title' ); ?>
                <input type="hidden" id="get_transform_title_wpnonce" value="<?=wp_create_nonce("get_transform_title_wpnonce")?>"/>
                <small class="form-text">This name will be used in the GTM data layer for the custom parameters object.</small>
			</div>
		</div>
        <div class="row mt-3">
            <div class="col form-inline" id="fire_event_once">
                <?php Events\renderSwitcherInput( $event, 'enable_time_window',true ); ?>
                <label>Fire this event only once in</label>
                <?php Events\renderNumberInput( $event, 'time_window', '24' ); ?>
                <label>hours</label>
                <?php renderProBadge( 'https://www.pixelyoursite.com/?utm_source=pys-free-plugin&utm_medium=pro-badge&utm_campaign=pro-feature' ); ?>
            </div>
        </div>
	</div>
</div>

<div class="card card-static">
    <div class="card-header">
        Event Trigger
    </div>
    <div class="pys_triggers_wrapper">

        <div class="card-body trigger_group">
            <?php
            $event_triggers = $event->getTriggers();
            $main_trigger = [];
            $trigger_types = array(
                'page_visit',
                'home_page',
                'scroll_pos',
                'post_type',
            );
            if ( !empty( $event_triggers ) ) {
                foreach ($event_triggers as $event_trigger) {

                    $trigger_type = $event_trigger->getTriggerType();
                    if (in_array($trigger_type, $trigger_types)) {
                        $main_trigger = $event_trigger;
                        break;
                    }
                }
            }
            elseif(empty( $event_triggers ) && empty($main_trigger) ){
                $main_trigger = new TriggerEvent();
            }
            ?>
            <div class="row mb-3">
                <div class="col form-inline">
                    <label>Fire event when</label>
                    <?php Events\renderTriggerTypeInput( $main_trigger, 'trigger_type' ); ?>
                    <div class="triger_post_type form-inline" style="display: none">
                        <?php Events\renderPostTypeSelect( $main_trigger, 'post_type_value' ); ?>
                    </div>
                    <div class="event-delay form-inline" >
                        <label>with delay</label>
                        <?php Events\renderTriggerNumberInput( $main_trigger, 'delay', '0' ); ?>
                        <label>seconds</label>
                    </div>
                    <div class="triger_number_page_visit form-inline">
                        <?php renderDummySelectInput( '=', true ); ?>
                    </div>
                    <div class="triger_number_page_visit form-inline">
                        <?php renderDummyNumberInput(3 ); ?>
                        <label>visited page</label>
                        <?php renderProBadge( 'https://www.pixelyoursite.com/?utm_source=pys-free-plugin&utm_medium=pro-badge&utm_campaign=pro-feature' ); ?>
                    </div>
                </div>
            </div>




            <div id="page_visit_panel" class="event_triggers_panel" data-trigger_type="page_visit" style="display: none;">
                <div class="row mt-3 event_trigger" data-trigger_id="0" style="display: none;">
                    <div class="col">
                        <div class="row">
                            <div class="col-4">
                                <select class="form-control-sm" name="" autocomplete="off" style="width: 100%;">
                                    <option value="contains">URL Contains</option>
                                    <option value="match">URL Match</option>
                                    <option value="param_contains"  disabled="disabled">URL Parameters Contains (PRO)</option>
                                    <option value="param_match"  disabled="disabled">URL Parameters Match (PRO)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <input name="" placeholder="Enter URL" class="form-control" type="text">
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm remove-row">
                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                $trigger = $main_trigger->getPageVisitTriggers()[0] ?? ['rule' => 'contains', 'value' => ''];

                ?>

                <div class="row mt-3 event_trigger" data-trigger_id="0">
                    <div class="col">
                        <div class="row">
                            <div class="col-4">
                                <select class="form-control-sm"
                                        name="<?php echo esc_attr( "pys[event][triggers][0][page_visit_triggers][0][rule]" ); ?>"
                                        autocomplete="off" style="width: 100%;">
                                    <option value="contains" <?php selected( $trigger['rule'], 'contains' ); ?>>URL Contains</option>
                                    <option value="match"  <?php selected( $trigger['rule'], 'match' ); ?>>URL Match</option>
                                    <option value="param_contains"  disabled="disabled">URL Parameters Contains (PRO)</option>
                                    <option value="param_match"  disabled="disabled">URL Parameters Match (PRO)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <input type="text" placeholder="Enter URL" class="form-control"
                                       name="<?php echo esc_attr( "pys[event][triggers][0][page_visit_triggers][0][value]" ); ?>"
                                       value="<?php esc_attr_e( $trigger['value'] ); ?>">
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm remove-row">
                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="insert-marker"></div>
                <div class="mt-3">
                    <small>You can use <b>*</b> to trigger an event on all pages.</small>
                </div>
                <div class="row mt-3">
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-primary add-event-trigger" type="button">Add another
                            URL</button>
                    </div>
                </div>
            </div>
            <div id="number_page_visit_panel" class="event_triggers_panel" data-trigger_type="number_page_visit" style="display: none;">
                <div class="row mt-3 event_trigger" data-trigger_id="0" style="display: none;">
                    <div class="col">
                        <div class="row">
                            <div class="col-4">
                                <?php renderDummySelectInput( 'Any URL`s', true ); ?>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm remove-row">
                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="insert-marker"></div>
                <div class="mt-3">
                    <small>You can use <b>*</b> to trigger an event on all pages.</small>
                </div>

                <div class="row mt-3">
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-disabled" type="button">Add another
                            URL</button>
                    </div>
                </div>
            </div>
            <div id="url_click_panel" class="event_triggers_panel" style="display: none;">
                <div class="row mt-3">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <p>This is a PRO trigger. <a
                                            href="https://www.pixelyoursite.com/facebook-pixel-plugin/buy-pixelyoursite-pro?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-pro-trigger"
                                            target="_blank">Upgrade to get all the benefits</a>.</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4">
                                <?php renderDummySelectInput( 'URL Contains', true ); ?>
                            </div>
                            <div class="col-6">
                                <?php renderDummyTextInput( 'Enter URL' ); ?>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm btn-disabled">
                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3 mb-5">
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-disabled" type="button">Add another
                            URL</button>
                    </div>
                </div>
            </div>

            <div id="css_click_panel" class="event_triggers_panel" style="display: none;">
                <div class="row mt-3">
                    <div class="col">
                        <div class="row">
                            <div class="col-10">
                                <?php renderDummyTextInput( 'Enter CSS selector' ); ?>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm btn-disabled">
                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3 mb-5">
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-disabled" type="button">Add another
                            selector</button>
                    </div>
                </div>
            </div>

            <div id="css_mouseover_panel" class="event_triggers_panel" style="display: none;">
                <div class="row mt-3">
                    <div class="col">
                        <div class="row">
                            <div class="col-10">
                                <?php renderDummyTextInput( 'Enter CSS selector' ); ?>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm btn-disabled">
                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3 mb-5">
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-disabled" type="button">Add another
                            selector</button>
                    </div>
                </div>
            </div>

            <div id="scroll_pos_panel" class="event_triggers_panel" style="display: none;">

                <?php
                $trigger = $main_trigger->getScrollPosTriggers()[0] ?? ['value' => 0];

                ?>

                <div class="row mt-3 event_trigger" data-trigger_id="0">
                    <div class="col">
                        <div class="row">
                            <div class="col-3">
                                <input type="number" min="0" max="100" class="form-control"
                                       name='<?php echo esc_attr( "pys[event][triggers][0][scroll_pos_triggers][0][value]" ); ?>'
                                       value="<?php esc_attr_e( (int) $trigger[ 'value' ] ); ?>">
                            </div>

                        </div>
                    </div>
                </div>

                <div class="insert-marker"></div>
                <div class="row mt-3 mb-5">
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-disabled" type="button">Add another
                            threshold</button>
                    </div>
                </div>
            </div>

            <div id="add_to_cart_panel" class="event_triggers_panel" data-trigger_type="add_to_cart" style="display: none;">
                <div class="row mt-3">
                    <div class="col switcher_event_track_value_and_currency">
                        <?php renderDummySwitcher(); ?>
                        <h4 class="switcher-label">Track value and currency <?php renderProBadge(); ?></h4>
                        <p><?php _e('We will add value and currency parameters, similar to the default WooCommerce add to cart event. If you use this option, don\'t manually add value or currency parameters to this event.', 'pys');?></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <h4><b><?php _e('Warning:', 'pys');?></b> <?php _e('Use it only if you must replace the default Add To Cart event with a custom event. Don\'t configure an add to cart event with this trigger, the plugin fires such an event automatically.', 'pys');?></h4>
                    </div>
                </div>
            </div>
            <div id="purchase_panel" class="event_triggers_panel" data-trigger_type="purchase" style="display: none;">
                <div class="row mt-3 event_trigger" data-trigger_id="0">
                    <div class="col switcher_event_transaction_only_action">
                        <?php renderDummySwitcher();?>
                        <h4 class="switcher-label">Fire this event for transaction only <?php renderProBadge(); ?></h4>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col switcher_event_track_transaction_ID">
                        <?php renderDummySwitcher();?>
                        <h4 class="switcher-label">Track transaction ID <?php renderProBadge(); ?></h4>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col switcher_event_track_value_and_currency">
                        <?php renderDummySwitcher();?>
                        <h4 class="switcher-label">Track value and currency <?php renderProBadge(); ?></h4>
                        <p><?php _e('We will add value and currency parameters, similar to the default WooCommerce Purchase event. If you use this option, don\'t manually add value or currency parameters to this event.', 'pys');?></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <h4><b><?php _e('Warning:', 'pys');?></b> <?php _e('Use it only if you must replace the default Purchase event with a custom event. Don\'t configure a Purchase event with this trigger, the plugin fires such an event automatically. ', 'pys');?></h4>
                    </div>
                </div>
            </div>
            <?php $eventsFormFactory = apply_filters("pys_form_event_factory",[]);
            foreach ($eventsFormFactory as $activeFormPlugin) : ?>
                <div id="<?php echo $activeFormPlugin->getSlug(); ?>_panel" class="event_triggers_panel" data-trigger_type="<?php echo $activeFormPlugin->getSlug(); ?>" style="display: none;">

                    <?php if ( $activeFormPlugin->getSlug() == "elementor_form" ) : ?>

                        <div class="row mt-3 event_trigger" data-trigger_id="0">
                            <div class="col">
                                <div class="row">
                                    <div class="col-10">
                                        <small class="form-text mb-1">Enter Elementor form pages URL</small>
                                        <?php renderDummySelectInput('Elementor form pages URL', true ); ?>
                                    </div>
                                    <div class="col-2 d-flex align-items-center pt-3">
                                        <button class="btn btn-sm btn-block btn-disabled" type="button">Scan forms</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pys_video_view_triggers" data-trigger_id="0">
                            <div class="row mt-3 event_trigger" data-trigger_id="0">
                                <div class="col">
                                    <div class="row">
                                        <div class="col-12">
                                            <small class="form-text mb-1">Select forms</small>
                                            <?php renderDummySelectInput('Select forms', true ); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3 event_trigger" data-trigger_id="0">
                                <div class="col">
                                    <div class="row">
                                        <div class="col-12">
                                            <?php renderDummySwitcher(); ?>
                                            <h4 class="switcher-label">Disable the Form event for the same forms</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>

                        <div class="row mt-3 event_trigger" data-trigger_id="0">
                            <div class="col">
                                <?php renderDummySelectInput('Forms'); ?>
                            </div>

                        </div>
                        <small class="form-text">Select Forms to Trigger the Event.</small>

                    <?php endif; ?>
                </div>
            <?php
            endforeach;
            ?>
            <div id="url_filter_panel" class="event_triggers_panel" style="display: none;">
                <div class="row mt-3">
                    <div class="col">
                        <div class="row">
                            <div class="col-10">
                                <?php renderDummyTextInput( 'Enter URL' ); ?>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm btn-disabled">
                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-disabled" type="button">Add URL filter</button>
                    </div>
                </div>
            </div>

            <div id="embedded_video_view" class="event_triggers_panel" data-trigger_type="video_view"
                 style="display: none;">
                <div class="row mt-3 event_trigger" data-trigger_id="0">
                    <div class="col">
                        <div class="row">
                            <div class="col-10">
                                <small class="form-text mb-1">Enter video pages URL</small>
                                <?php renderDummySelectInput('Video pages URL', true ); ?>
                            </div>
                            <div class="col-2 d-flex align-items-center pt-3">
                                <button class="btn btn-sm btn-block btn-disabled" type="button">Scan videos</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pys_video_view_triggers" data-trigger_id="0">
                    <div class="row mt-3 event_trigger" data-trigger_id="0">
                        <div class="col">
                            <div class="row">
                                <div class="col-12">
                                    <small class="form-text mb-1">Select videos</small>
                                    <?php renderDummySelectInput('Select videos', true ); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3 event_trigger" data-trigger_id="0">
                        <div class="col">
                            <div class="row">
                                <div class="col-12 d-flex">
                                    <div class="mb-0 mt-2 mr-2">Select trigger </div>
                                    <div class="form-inline">
                                        <?php renderDummySelectInput( 'Play' ); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3 event_trigger" data-trigger_id="0">
                        <div class="col">
                            <div class="row">
                                <div class="col-12">
                                    <?php PYS()->renderDummyCheckbox('Disable the automatic WatchVideo events for the video'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="email_link_panel" class="event_triggers_panel" data-trigger_type="email_link"
                 style="display: none;">
                <div class="row mt-3">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <p>This is a PRO trigger. <a
                                            href="https://www.pixelyoursite.com/facebook-pixel-plugin/buy-pixelyoursite-pro?utm_source=pixelyoursite-free-plugin&utm_medium=plugin&utm_campaign=free-plugin-pro-trigger"
                                            target="_blank">Upgrade to get all the benefits</a>.</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4">
                                <?php renderDummySelectInput( 'Link Match', true ); ?>
                            </div>
                            <div class="col-6">
                                <?php renderDummyTextInput( 'Enter Link' ); ?>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm btn-disabled">
                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3 mb-5">
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-disabled" type="button">Add another
                            link</button>
                    </div>
                </div>

                <div class="row mt-3" data-trigger_id="0">
                    <div class="col">
                        <div class="row">
                            <div class="col-12">
                                <?php renderDummySwitcher(); ?>
                                <h4 class="switcher-label">Disable the default Email event for the same action</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr class="m-0">
    <div id="pys-add-trigger" class="mt-4 mb-4 d-flex flex-column">
        <div class="row d-flex justify-content-center">
            <div class="col-6 d-flex justify-content-center">
                <button class="btn btn-block btn-gray add-trigger" type="button">Add trigger
                </button>
            </div>

        </div>
        <div class="row d-flex justify-content-center mt-2">
            <div class="col-6 d-flex justify-content-center">
				<?php renderProBadge(); ?>
            </div>
        </div>
    </div>

</div>


<div class="card card-static card-conditions">
    <div class="card-header">
        Conditions
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <?php Events\renderSwitcherInput( $event, 'conditions_enabled' ); ?>
                <h4 class="switcher-label">Enable conditions</h4>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col">
                <h4>Logic</h4>
                <div class="form-inline">
                    <?php Events\render_radio_input( $event, 'conditions_logic', 'AND', 'AND' ); ?>
                    <?php Events\render_radio_input( $event, 'conditions_logic', 'OR', 'OR' ); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="pys_conditions_wrapper">
        <?php
        $event_conditions = $event->getConditions();
        if ( !empty( $event_conditions ) ) :
            foreach ( $event_conditions as $event_condition ) :
                $i = $event_condition->getConditionIndex();
                $trigger_type = $event_condition->getConditionType();
                $event_condition->renderConditionalBlock(true);
                break;
            endforeach;
        endif;
        ?>
    </div>
    <hr class="m-0">
    <div id="pys-add-condition" class="mt-4 mb-4">
        <div class="row d-flex justify-content-center">
            <div class="col-6 justify-content-center">
                <button class="btn btn-block btn-grey add-condition" type="button"><?php _e('Add another Condition', 'pys'); ?></button>
            </div>
        </div>
        <div class="row d-flex justify-content-center mt-2">
            <div class="col-6 d-flex justify-content-center">
                <?php renderProBadge(); ?>
            </div>
        </div>
    </div>
    <div id="pys_add_event_condition" style="display: none;">

        <?php
        $new_condition = new ConditionalEvent('url_filters');
        $new_condition_index = $new_condition->getConditionIndex();
        ?>

        <input type="hidden" name="<?php echo esc_attr( "pys[event][conditions][$new_condition_index][cloned_event]" ); ?>"
               value="1">
        <?php $new_condition->renderConditionalBlock();?>
        <?php $new_condition->renderConditionalsPanel();?>

    </div>
</div>

<?php if ( Facebook()->enabled() ) : ?>
    <div class="card card-static">
        <div class="card-header">
            Facebook
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col">
                    <?php Events\renderSwitcherInput( $event, 'facebook_enabled' ); ?>
                    <h4 class="switcher-label">Enable on Facebook</h4>
                </div>
            </div>
            <div id="facebook_panel">
                <div class="row mt-3">
                    <div class="col col-offset-left form-inline">
                        <label>Event type:</label>
                        <?php Events\renderFacebookEventTypeInput( $event, 'facebook_event_type' ); ?>
                        <div class="facebook-custom-event-type form-inline">
                            <?php Events\renderTextInput( $event, 'facebook_custom_event_type', 'Enter name' ); ?>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col col-offset-left">
                        <?php Events\renderSwitcherInput( $event, 'facebook_params_enabled' ); ?>
                        <h4 class="indicator-label">Add Parameters</h4>
                    </div>
                </div>
                <div id="facebook_params_panel">
                    <div class="row mt-3">
                        <div class="col col-offset-left">

                            <div class="row mb-3 ViewContent Search AddToCart AddToWishlist InitiateCheckout AddPaymentInfo Purchase Lead CompleteRegistration Subscribe StartTrial">
                                <label class="col-5 control-label">value</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'value' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 ViewContent Search AddToCart AddToWishlist InitiateCheckout AddPaymentInfo Purchase Lead CompleteRegistration Subscribe StartTrial">
                                <label class="col-5 control-label">currency</label>
                                <div class="col-4">
                                    <?php Events\renderCurrencyParamInput( $event, 'currency' ); ?>
                                </div>
                                <div class="col-2 facebook-custom-currency">
                                    <?php Events\renderFacebookParamInput( $event, 'custom_currency' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 ViewContent AddToCart AddToWishlist InitiateCheckout Purchase Lead CompleteRegistration">
                                <label class="col-5 control-label">content_name</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'content_name' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 ViewContent AddToCart AddToWishlist InitiateCheckout Purchase Lead CompleteRegistration">
                                <label class="col-5 control-label">content_ids</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'content_ids' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 ViewContent AddToCart InitiateCheckout Purchase">
                                <label class="col-5 control-label">content_type</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'content_type' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 Search AddToWishlist InitiateCheckout AddPaymentInfo Lead">
                                <label class="col-5 control-label">content_category</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'content_category' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 InitiateCheckout Purchase">
                                <label class="col-5 control-label">num_items</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'num_items' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 Purchase">
                                <label class="col-5 control-label">order_id</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'order_id' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 Search">
                                <label class="col-5 control-label">search_string</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'search_string' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 CompleteRegistration">
                                <label class="col-5 control-label">status</label>
                                <div class="col-4">
                                    <?php Events\renderFacebookParamInput( $event, 'status' ); ?>
                                </div>
                            </div>
                            <div class="row mb-3 Subscribe StartTrial">
                                <label class="col-5 control-label">predicted_ltv</label>
                                <div class="col-4">
			                        <?php Events\renderFacebookParamInput( $event, 'predicted_ltv' ); ?>
                                </div>
                            </div>

                            <!-- Custom Facebook Params -->
                            <div class="row mt-3 facebook-custom-param" data-param_id="0" style="display: none;">
                                <div class="col-1"></div>
                                <div class="col-4">
                                    <input name="" placeholder="Enter name" class="form-control custom-param-name" type="text">
                                </div>
                                <div class="col-4">
                                    <input name="" placeholder="Enter value" class="form-control custom-param-value"
                                           type="text">
                                </div>
                                <div class="col-2">
                                    <button type="button" class="btn btn-sm remove-row">
                                        <i class="fa fa-trash-o" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <?php foreach ( $event->getFacebookCustomParams() as $key => $custom_param ) : ?>

                                <?php $param_id = $key + 1; ?>

                                <div class="row mt-3 facebook-custom-param" data-param_id="<?php echo $param_id; ?>">
                                    <div class="col">
                                        <div class="row">
                                            <div class="col-1"></div>
                                            <div class="col-4">
                                                <input type="text" placeholder="Enter name" class="form-control custom-param-name"
                                                       name="pys[event][facebook_custom_params][<?php echo $param_id; ?>][name]"
                                                       value="<?php esc_attr_e( $custom_param['name'] ); ?>">
                                            </div>
                                            <div class="col-4">
                                                <input type="text" placeholder="Enter value" class="form-control custom-param-value"
                                                       name="pys[event][facebook_custom_params][<?php echo $param_id; ?>][value]"
                                                       value="<?php esc_attr_e( $custom_param['value'] ); ?>">
                                            </div>
                                            <div class="col-2">
                                                <button type="button" class="btn btn-sm remove-row">
                                                    <i class="fa fa-trash-o" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>

                            <div class="insert-marker"></div>

                            <div class="row mt-3">
                                <div class="col-5"></div>
                                <div class="col-4">
                                    <button class="btn btn-sm btn-block btn-primary add-facebook-parameter" type="button">Add
                                        Custom Parameter</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <br>
						<br>
            <p>
                <b>Important:</b> verify your custom events inside your Ads Manager:
                <a href="https://www.youtube.com/watch?v=Iyu-pSbqcFI" target="_blank">watch this video to learn how</a>
            </p>
        </div>
    </div>
<?php endif; ?>

<?php if ( GA()->enabled() ) : ?>
    <div class="card card-static">
        <div class="card-header">
            Google Analytics
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col">
                    <?php Events\renderSwitcherInput( $event, 'ga_ads_enabled' ); ?>
                    <h4 class="switcher-label">Enable on Google Analytics</h4>
                </div>
            </div>
            <div id="analytics_panel">
                <div class="row mt-3">
                    <div class="col ">
                        <!-- v4 Google params  -->
                        <div class="col g4">
                            <div class="row mb-3 g4">

                                <script>
                                    <?php
                                    $fields = array();
                                    foreach ($event->GAEvents as $group => $items) {
                                        foreach ($items as $name => $elements) {
                                            $fields[] = array("name"=>$name,'fields'=>$elements);
                                        }
                                    }

                                    ?>
                                    var ga_fields = <?=json_encode($fields)?>
                                </script>
                                <label class="col-5 control-label">Event</label>
                                <div class="col-4">
                                    <?php  Events\renderGoogleAnalyticsMergedActionInput( $event, 'ga_ads_event_action' ); ?>
                                </div>
                                <div class="col-3">
                                    <div id="ga-ads-custom-action_g4">
                                        <?php Events\renderTextInput( $event, 'ga_ads_custom_event_action', 'Enter name' ); ?>
                                    </div>
                                </div>


                            </div>

                            <div class="ga-ads-param-list">
                                <?php
                                foreach($event->getMergedGaParams() as $key=>$val) : ?>
                                    <div class="row mb-3 ga_ads_param">
                                        <label class="col-5 control-label"><?=$key?></label>
                                        <div class="col-4">
                                            <?php Events\renderMergedGAParamInput( $key, $val ); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="ga-ads-custom-param-list">
                                <?php
                                foreach ( $event->getGAMergedCustomParams() as $key => $custom_param ) : ?>
                                    <?php $param_id = $key + 1; ?>

                                    <div class="row mt-3 ga-ads-custom-param" data-param_id="<?php echo $param_id; ?>">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col-1"></div>
                                                <div class="col-4">
                                                    <input type="text" placeholder="Enter name" class="form-control custom-param-name"
                                                           name="pys[event][ga_ads_custom_params][<?php echo $param_id; ?>][name]"
                                                           value="<?php esc_attr_e( $custom_param['name'] ); ?>">
                                                </div>
                                                <div class="col-4">
                                                    <input type="text" placeholder="Enter value" class="form-control custom-param-value"
                                                           name="pys[event][ga_ads_custom_params][<?php echo $param_id; ?>][value]"
                                                           value="<?php esc_attr_e( $custom_param['value'] ); ?>">
                                                </div>
                                                <div class="col-2">
                                                    <button type="button" class="btn btn-sm remove-row">
                                                        <i class="fa fa-trash-o" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            </div>

                            <div class="row mt-3">
                                <div class="col-5"></div>
                                <div class="col-4">
                                    <button class="btn btn-sm btn-block btn-primary add-ga-ads-custom-parameter" type="button">Add
                                        Custom Parameter</button>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    The following parameters are automatically tracked: content_name, event_url, post_id, post_type. The paid version tracks the event_hour, event_month, and event_day.
                                </div>
                            </div>
                            <div class="row mt-3 ga_woo_info" style="display: none">
                                <div class="col-12">
                                    <strong>ATTENTION</strong>:​ the plugin automatically tracks ecommerce specific events for WooCommerce and Easy Digital Downloads. Make sure you really need this event.
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card card-disabled">
    <div class="card-header">
        Google Ads <?php renderProBadge('https://www.pixelyoursite.com/google-ads-tag/?utm_source=pys-free-plugin&utm_medium=pro-badge&utm_campaign=pro-feature'); ?><?php cardCollapseBtn(); ?>
    </div>
    <div class="card-body">
        <div class="row mb-2">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">Enable on Google Ads</h4>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col col-offset-left" id="google_ads_params_panel">
                <div class="row mb-3">
                    <label class="col-5 control-label">Conversion ID</label>
                    <div class="col-4">
                        <?php renderDummySelectInput( 'All', true ); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-5 control-label">Conversion Label</label>
                    <div class="col-4">
                        <?php renderDummyTextInput( 'Enter name' ); ?>
                        <small class="form-text">Optional</small>
                    </div>
                </div>

                <!-- Default Params -->
                <div class="row mb-3">
                    <label class="col-5 control-label">Action</label>
                    <div class="col-4">
                        <?php renderDummySelectInput( 'Custom Action', true ); ?>
                    </div>
                    <div class="col-3">
                        <div id="ga-custom-action">
                            <?php renderDummyTextInput( 'Enter name' ); ?>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-5 control-label">Category</label>
                    <div class="col-4">
                        <?php renderDummyTextInput(); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-5 control-label">Label</label>
                    <div class="col-4">
                        <?php renderDummyTextInput(); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-5 control-label">Value</label>
                    <div class="col-4">
                        <?php renderDummyTextInput(); ?>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-5"></div>
                    <div class="col-4">
                        <button class="btn btn-sm btn-block btn-secondary" type="button" disabled>Add Custom Parameter
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-static">
    <div class="card-header">
        TikTok <?php renderProBadge('https://www.pixelyoursite.com/google-ads-tag/?utm_source=pys-free-plugin&utm_medium=pro-badge&utm_campaign=pro-feature'); ?><?php cardCollapseBtn(); ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <?php renderDummySwitcher(); ?>
                <h4 class="switcher-label">Enable on TikTok</h4>
            </div>
        </div>
        <div id="tiktok_panel">
            <div class="row mt-3">
                <label class="col-5 control-label">Fire for:</label>
                <div class="col-4">
                    <?php renderDummySelectInput( 'All pixels', true ); ?>
                </div>
            </div>
            <div class="row mt-3">
                <label class="col-5 control-label">Event type:</label>
                <div class="col-4">
                    <?php renderDummyTextInput( 'Select Event type:' ); ?>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col col-offset-left">
                    <?php renderDummySwitcher(); ?>
                    <h4 class="indicator-label">Add Parameters</h4>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ( Pinterest()->enabled() ) : ?>
    <?php Pinterest()->renderCustomEventOptions( $event ); ?>
<?php endif; ?>

<?php if ( Bing()->enabled() ) : ?>
    <?php Bing()->renderCustomEventOptions( $event ); ?>
<?php endif; ?>

<hr class="mb-4 mt-4">
<?php if ( GTM()->enabled() ) : ?>
    <div class="card card-static mb-4">
        <div class="card-header">
            GTM DataLayer
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col">
                    <?php Events\renderSwitcherInput( $event, 'gtm_enabled' ); ?>
                    <h4 class="switcher-label">Enable on GTM</h4>
                </div>
            </div>

            <div id="gtm_panel" class="mt-3">
                <hr>
                <div class="row mt-4 mb-3">
                    <div class="col">
                        <?php Events\render_checkbox_input( $event, 'gtm_automated_param', 'Add the automated parameters in the dataLayer' ); ?>
                    </div>
                </div>
                <div class="row mt-4 mb-3">
                    <div class="col">
                        <?php Events\render_checkbox_input( $event, 'gtm_remove_customTrigger', 'Remove the customTrigger object' ); ?>
                    </div>
                </div>
                <hr>
                <div class="row align-items-center mb-2">
                    <label class="col-5 control-label" style="padding-top:0;">Fire for:</label>
                    <div class="col-4">
                        <?php
                        $mainPixels = GTM()->getPixelIDs();
                        if(!empty($mainPixels) && strpos($mainPixels[0], 'GTM-') === 0 && strpos($mainPixels[0], 'GTM-') !== false) {
                            echo $mainPixels[0];
                            echo '<input type="hidden" name="pys[event][gtm_pixel_id][]" value="'.$mainPixels[0].'"/>';
                        }
                        else{
                            _e('No container ID is configured', 'pys');
                        }
                        ?>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col ">
                        <!-- v4 Google params  -->
                        <div class="col g4">
                            <div class="row mb-3 g4">

                                <script>
                                    <?php
                                    $fields = array();
                                    foreach ($event->GAEvents as $group => $items) {
                                        foreach ($items as $name => $elements) {
                                            $fields[] = array("name"=>$name,'fields'=>$elements);
                                        }
                                    }

                                    ?>
                                    var gtm_fields = <?=json_encode($fields)?>
                                </script>
                                <label class="col-5 control-label">Event</label>
                                <div class="col-4">
                                    <?php  Events\renderGTMActionInput( $event, 'gtm_event_action' ); ?>
                                </div>
                                <div class="col-3">
                                    <div id="gtm-custom-action_g4">
                                        <?php Events\renderTextInput( $event, 'gtm_custom_event_action', 'Enter name' ); ?>
                                    </div>
                                </div>


                            </div>

                            <div class="gtm-param-list">
                                <?php
                                foreach($event->getGTMParams() as $key=>$val) : ?>
                                    <div class="row mb-3 gtm_param">
                                        <label class="col-5 control-label"><?=$key?></label>
                                        <div class="col-4">
                                            <?php Events\renderGTMParamInput( $key, $val ); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="gtm-custom-param-list">
                                <?php
                                foreach ( $event->getGTMCustomParamsAdmin() as $key => $custom_param ) : ?>
                                    <?php $param_id = $key + 1; ?>

                                    <div class="row mt-3 gtm-custom-param" data-param_id="<?php echo $param_id; ?>">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col-1"></div>
                                                <div class="col-4">
                                                    <input type="text" placeholder="Enter name" class="form-control custom-param-name"
                                                           name="pys[event][gtm_custom_params][<?php echo $param_id; ?>][name]"
                                                           value="<?php esc_attr_e( $custom_param['name'] ); ?>">
                                                </div>
                                                <div class="col-4">
                                                    <input type="text" placeholder="Enter value" class="form-control custom-param-value"
                                                           name="pys[event][gtm_custom_params][<?php echo $param_id; ?>][value]"
                                                           value="<?php esc_attr_e( $custom_param['value'] ); ?>">
                                                </div>
                                                <div class="col-2">
                                                    <button type="button" class="btn btn-sm remove-row">
                                                        <i class="fa fa-trash-o" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            </div>

                            <div class="row mt-3">
                                <div class="col-5"></div>
                                <div class="col-4">
                                    <button class="btn btn-sm btn-block btn-primary add-gtm-custom-parameter" type="button">Add
                                        Custom Parameter</button>
                                </div>
                            </div>

                            <hr>
                            <div class="row mt-4 mb-2 gtm_use_custom_object_name">
                                <div class="col">
                                    <?php Events\render_checkbox_input( $event, 'gtm_use_custom_object_name', 'Use a custom value for the custom paramers object' ); ?>
                                </div>
                            </div>
                            <div class="row mt-2 mb-2">
                                <div class="col">
                                    <?php Events\renderTextInput( $event, 'gtm_custom_object_name', $event->getManualCustomObjectName() );?>
                                </div>
                            </div>
                            <hr>

                            <div class="row mt-3">
                                <div class="col-12">
                                    When configuring GTM variables for these parameters, use this key: <span id="manual_custom_object_name"><?= $event->getManualCustomObjectName(); ?></span>
                                </div>
                            </div>
                            <div class="row mt-3 gtm_woo_info" style="display: none">
                                <div class="col-12">
                                    <strong>ATTENTION</strong>:​ the plugin automatically tracks ecommerce specific events for WooCommerce and Easy Digital Downloads. Make sure you really need this event.
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card card-static card-disabled">
    <div class="card-header">
        Dynamic Parameters Help <?php renderProBadge(); ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <ul class="text-secondary">
                    <li><code>[id]</code> - it will pull the WordPress post ID</li>
                    <li><code>[title]</code> - it will pull the content title</li>
                    <li><code>[content_type]</code> - it will pull the post type (post, product, page and so on)</li>
                    <li><code>[categories]</code> - it will pull the content categories</li>
                    <li><code>[tags]</code> - it will pull the content tags</li>
                    <li><code>[total]</code> - it will pull WooCommerce or EDD order's total when it exists</li>
                    <li><code>[subtotal]</code> - it will pull WooCommerce or EDD orders's subtotal when it exists</li>
                </ul>
                <p><strong class="text-secondary">Track URL parameters:</strong></p>
                <p class="text-secondary"> Use <code>[url_ParameterName]</code> where ParameterName = the name of the parameter. <br/><br/>
                    Example:<br/><br/>
                    This is your URL: <?=$serverUrl?>?ParameterName=123<br/>
                    The parameter value will be 123.<br/>
                </p>
                <p class="text-secondary mb-0"><strong>Note:</strong> if a parameter is missing from a particular
                    page, the event won't include it.</p>
					<br/>
				<p><strong class="text-secondary">Track MemberPress plugin parameters:</strong></p>
				  <p class="text-secondary"> These parameters only work on a "thankyou page" with shortcode <code>[mepr-ecommerce-tracking]Message with %%variables%% in here[/mepr-ecommerce-tracking]</code><br/><br/>
					All variables must have the prefix "mp_".<br/><br/>
					Example:<br/><br/>
					This is your MemberPress variable: total.<br/>
					The parameter value will be: [mp_total].<br/>
				 </p>
            </div>
        </div>
    </div>
</div>

<hr>
<div class="row justify-content-center">
	<div class="col-4">
		<button class="btn btn-block btn-save">Save Event</button>
	</div>
</div>
