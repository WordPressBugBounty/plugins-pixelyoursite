<?php

namespace PixelYourSite;
use PixelYourSite\GTM\Helpers;
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<h2 class="section-title">GTM Settings</h2>

<!-- General -->
<div class="card card-static">
    <div class="card-header">
        General
    </div>
    <div class="card-body">
         <div class="row">
            <div class="col">
                <?php GTM()->render_switcher_input('container_code'); ?>
                <h4 class="switcher-label"><?php _e('Container code', 'pys');?></h4>
                <p>
                    <small>
                        <?php _e('Turning OFF the Google Tag Manager container itself will remove both the head and the body part of the container code but leave data layer codes working. This should be only used in specific cases where you need to place the container code manually or using another tool.', 'pys');?>
                    </small>
                </p>

            </div>
        </div>
    </div>
</div>

<!-- Event Settings -->
<div class="card card-static">
    <div class="card-header">
        Event Settings
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col">
                <?php GTM()->render_switcher_input( 'track_page_view' ); ?>
                <h4 class="switcher-label">Track page_view in the Data Layer</h4>
            </div>
        </div>
        <hr class="mb-3 mt-1">
    </div>

</div>
<!-- Advanced -->
<div class="card card-static">
    <div class="card-header">
        Advanced
    </div>
    <div class="card-body">
        <div class="row pt-3 pb-3">
            <div class="col-12">
                <h4 class="label"><?php _e( 'dataLayer variable name:', 'pys' );?></h4>
                <?php GTM()->render_text_input('gtm_dataLayer_name'); ?>
            </div>
        </div>
        <div class="row pt-3 pb-3">
            <div class="col-12">
                <h4 class=""><?php _e( 'Server-side Tagging (sGTM):', 'pys' );?></h4>
                <p>
                <h5 class="label"><?php _e( 'sGTM Container Domain:', 'pys' );?></h5>
                <?php GTM()->render_text_input( 'gtm_container_domain'); ?>
                <small><? _e( 'Enter your custom domain name if you are using a custom server side GTM container for tracking.', 'pys' );?></small>
                </p>
                <p>
                <h5 class="label"><?php _e( 'sGTM container identifier:', 'pys' );?></h5>
                <?php GTM()->render_text_input( 'gtm_container_identifier'); ?>
                <small><?php _e( 'Only use if you are using a custom loader.', 'pys' );?></small>
                </p>
            </div>
        </div>
        <div class="row pt-3 pb-3">
            <div class="col-12">
                <h4 class=""><?php _e( 'Google Tag Manager Environment', 'pys' );?></h4>
                <p>
                <h5 class="label">gtm_auth:</h5>
                <?php GTM()->render_text_input( 'gtm_auth', 'Enter gtm_auth code'); ?>
                <small><?php _e( 'Enter your gtm_auth code your GTM environment.', 'pys' );?></small>
                </p>
                <p>
                <h5 class="label">gtm_preview:</h5>
                <?php GTM()->render_text_input( 'gtm_preview', 'Enter gtm_preview code'); ?>
                <small><?php _e( 'Enter your gtm_preview code your GTM environment.', 'pys' );?></small>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Security -->
<div class="card card-static">
    <div class="card-header">
        Security <?php cardCollapseBtn(); ?>
    </div>
    <div class="card-body" style="display: none;">
        <div class="check-list">
            <div class="row">
                <div class="col col-offset-left">
                    <?php GTM()->render_radio_input( 'check_list', 'disabled', __('Disable feature: control everything on Google Tag Manager interface', 'pys') ); ?>
                    <?php GTM()->render_radio_input( 'check_list', 'blacklist', __('Allow all, except the checked items on all list tabs (blacklist)', 'pys') ); ?>
                    <?php GTM()->render_radio_input( 'check_list', 'whitelist', __('Block all, except the checked items on all list tabs (whitelist)', 'pys') ); ?>
                </div>
            </div>
        </div>
        <div class="tabs mt-4">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="tab1"><?php _e('List tags', 'pys');?></button>
                <button class="tab-btn" data-tab="tab2"><?php _e('List triggers', 'pys');?></button>
                <button class="tab-btn" data-tab="tab3"><?php _e('List variables', 'pys');?></button>
            </div>

            <div class="tab-contents">
                <?php
                $blacklist_tabs = Helpers\get_all_blacklist_tabs();
                ?>
                <div id="tab1" class="tab-content active">
                    <div class="row mb-1">
                        <div class="col">
                            <div class="custom-controls-stacked custom-controls-blacklist ml-2">
                                <label class="custom-control custom-checkbox">
                                    <input type="checkbox" id="select-all-tags" name="pys[gtm][select_all_blacklist][]" value="select_all_tags" <?php echo in_array('select_all_tags', GTM()->getOption('select_all_blacklist')) ? "checked" : ""?> class="select-all custom-control-input">
                                    <span class="custom-control-indicator"></span>
                                    <span class="custom-control-description"><?php _e('Select all', 'pys'); ?></span>
                                </label>
                                <?php
                                foreach ( $blacklist_tabs['tags'] as $blacklist_checkbox) {
                                    GTM()->render_checkbox_blacklist_input_array( 'check_list_contain', esc_html( $blacklist_checkbox['tag'] ),esc_attr( $blacklist_checkbox['id'] ));
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="tab2" class="tab-content">
                    <div class="row mb-1">
                        <div class="col">
                            <div class="custom-controls-stacked custom-controls-blacklist ml-2">
                                <label class="custom-control custom-checkbox">
                                    <input type="checkbox" id="select-all-triggers" name="pys[gtm][select_all_blacklist][]" value="select_all_triggers" <?php echo in_array('select_all_triggers', GTM()->getOption('select_all_blacklist')) ? "checked" : ""?> class="select-all custom-control-input">
                                    <span class="custom-control-indicator"></span>
                                    <span class="custom-control-description"><?php _e('Select all', 'pys'); ?></span>
                                </label>
                                <?php
                                foreach ( $blacklist_tabs['triggers'] as $blacklist_checkbox) {
                                    GTM()->render_checkbox_blacklist_input_array( 'check_list_contain', esc_html( $blacklist_checkbox['trigger'] ),esc_attr( $blacklist_checkbox['id'] ));
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="tab3" class="tab-content">
                    <div class="row mb-1">
                        <div class="col">
                            <div class="custom-controls-stacked custom-controls-blacklist ml-2">
                                <label class="custom-control custom-checkbox">
                                    <input type="checkbox" id="select-all-variables" name="pys[gtm][select_all_blacklist][]" value="select_all_variables" <?php echo in_array('select_all_variables', GTM()->getOption('select_all_blacklist')) ? "checked" : ""?> class="select-all custom-control-input">
                                    <span class="custom-control-indicator"></span>
                                    <span class="custom-control-description"><?php _e('Select all', 'pys'); ?></span>
                                </label>
                                <?php
                                foreach ( $blacklist_tabs['variables'] as $blacklist_checkbox) {
                                    GTM()->render_checkbox_blacklist_input_array( 'check_list_contain', esc_html( $blacklist_checkbox['variable'] ),esc_attr( $blacklist_checkbox['id'] ));
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<hr>
<div class="row justify-content-center">
    <div class="col-4">
        <button class="btn btn-block btn-sm btn-save">Save Settings</button>
    </div>
</div>

<style>
    .tabs {
        width: 100%;
    }

    .tab-buttons {
        display: flex;
    }

    #pys button.tab-btn {
        padding: 10px 20px;
        cursor: pointer;
        background-color: #fff;
        border-bottom: none;
        margin-right: 5px;
        transition: background-color 0.3s ease;
        flex: 1;
        text-align: center;
        border: 1px solid #eee;
        border-radius: 5px 5px 0 0;
    }
    #pys button.tab-btn:hover {
        background-color: #f9f9f9;
    }

    #pys button.tab-btn.active {
        background-color: #f9f9f9;
        font-weight: bold;
    }
    #pys button.tab-btn:focus {
        outline: 1px dotted;
    }

    .tab-content {
        display: none;
        margin-top: 20px;
    }

    .tab-content.active {
        display: block;
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const tabButtons = document.querySelectorAll(".tab-btn");
        const tabContents = document.querySelectorAll(".tab-content");

        tabButtons.forEach(button => {
            button.addEventListener("click", (e) => {
                e.preventDefault();
                const target = document.getElementById(button.dataset.tab);

                // Убираем класс "active" со всех кнопок и контента
                tabButtons.forEach(btn => btn.classList.remove("active"));
                tabContents.forEach(content => content.classList.remove("active"));

                // Добавляем класс "active" для выбранной вкладки и контента
                button.classList.add("active");
                target.classList.add("active");
            });
        });
    });

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.select-all').forEach(selectAllCheckbox => {
            // Проверка состояния select-all при загрузке страницы
            if (selectAllCheckbox.checked) {
                const checkboxes = selectAllCheckbox.closest('.custom-controls-blacklist').querySelectorAll('input[type="checkbox"]:not(.select-all)');
                checkboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
            }

            // Обработчик события change для select-all
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = this.closest('.custom-controls-blacklist').querySelectorAll('input[type="checkbox"]:not(.select-all)');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            });
        });
    });
</script>