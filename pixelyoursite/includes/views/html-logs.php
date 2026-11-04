<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$clear_nonce = wp_create_nonce('clear_logs_nonce');
$download_nonce = wp_create_nonce('download_logs_nonce');
?>
<div class="cards-wrapper cards-wrapper-style2 gap-24 logs-wrapper">
    <div class="card card-style3 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center logs_enable">
                <?php PYS()->render_switcher_input( 'pys_logs_enable' ); ?>
                <h4 class="secondary_heading_type2 switcher-label"><?php _e( 'General plugin logs', 'pys' ); ?></h4>
            </div>
            <div class="head-button-block">
                <div class="d-flex align-items-center logs-lines">
                    <span class="font-medium mr-8"><?php _e( 'Lines to show', 'pys' ); ?></span>
                    <?php PYS()->render_number_input( 'logs_lines_general', '', false, 100000, 100, 100 ); ?>
                </div>
                <a class="btn btn-small btn-gray font-medium ico-button ico-trash" href="<?php echo esc_url( add_query_arg(['clear_plugin_logs' => 'true', '_wpnonce_clear_logs' => $clear_nonce],buildAdminUrl( 'pixelyoursite', 'logs' ))); ?>">
                    <span><?php _e( 'Clear General logs', 'pys' ); ?></span>
                </a>
                <a class="btn btn-small btn-gray font-medium ico-button ico-download" href="<?php echo esc_url( add_query_arg(['download_logs' => 'general', '_wpnonce_download_logs' => $download_nonce],buildAdminUrl( 'pixelyoursite', 'logs' ))); ?>" target="_blank" download>
                    <span><?php _e( 'Download General logs', 'pys' ); ?></span>
                </a>
            </div>
        </div>
        <div class="card-body">
        <textarea readonly <?php disabled( !PYS()->getOption('pys_logs_enable') ); ?>><?php
            // Cap an already-oversized file before reading it, even with logging off.
            PYS()->getLog()->maybe_trim();
            echo esc_html(PYS()->getLog()->getLogs( max( 100, (int) PYS()->getOption( 'logs_lines_general' ) ) ));
            ?></textarea>
        </div>
    </div>

    <?php if ( Pinterest()->enabled() && method_exists(Pinterest(), 'getLog') ) : ?>
        <div class="card card-style3 card-static">
            <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center logs_enable">
                    <?php Pinterest()->render_switcher_input( 'logs_enable' ); ?>
                    <h4 class="secondary_heading_type2 switcher-label">Pinterest API Logs</h4>
                </div>
                <div class="head-button-block">
                    <div class="d-flex align-items-center logs-lines">
                        <span class="font-medium mr-8"><?php _e( 'Lines to show', 'pys' ); ?></span>
                        <?php PYS()->render_number_input( 'logs_lines_pinterest', '', false, 100000, 100, 100 ); ?>
                    </div>
                    <a class="btn btn-small btn-gray font-medium ico-button ico-trash"
                       href="<?php echo esc_url( add_query_arg(['clear_pinterest_logs' => 'true', '_wpnonce_clear_logs' => $clear_nonce],buildAdminUrl( 'pixelyoursite', 'logs' ))); ?>">
                        <span>Clear Pinterest API Logs</span>
                    </a>
                    <a class="btn btn-small btn-gray font-medium ico-button ico-download"
                       href="<?php echo esc_url( add_query_arg(['download_logs' => 'pinterest', '_wpnonce_download_logs' => $download_nonce],buildAdminUrl( 'pixelyoursite', 'logs' ))); ?>" target="_blank" download>
                        <span>Download Pinterest API Logs</span>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <textarea readonly <?php disabled( !Pinterest()->getOption('logs_enable') ); ?>><?php
                    // Cap an already-oversized file before reading it, even with logging off.
                    Pinterest()->getLog()->maybe_trim();
                    echo esc_html(Pinterest()->getLog()->getLogs( max( 100, (int) PYS()->getOption( 'logs_lines_pinterest' ) ) ));
                    ?></textarea>
            </div>
        </div>
    <?php endif; ?>
</div>
