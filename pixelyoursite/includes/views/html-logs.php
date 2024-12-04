<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$clear_nonce = wp_create_nonce('clear_logs_nonce');
$download_nonce = wp_create_nonce('download_logs_nonce');
?>

<div class="card card-static">
    <div class="card-header ">
        <?php PYS()->render_switcher_input('pys_logs_enable'); ?> Meta API Logs
        <div style="float: right;margin-top: 10px;">
            <a style="margin-right: 30px"
               href="<?php echo esc_url( add_query_arg(['clear_plugin_logs' => 'true', '_wpnonce_clear_logs' => $clear_nonce],buildAdminUrl( 'pixelyoursite', 'logs' ))); ?>">Clear
                Meta API Logs</a>
            <a href="<?php echo esc_url( add_query_arg(['download_logs' => 'meta', '_wpnonce_download_logs' => $download_nonce],buildAdminUrl( 'pixelyoursite', 'logs' ))); ?>" target="_blank">Download Meta API logs</a>
        </div>
    </div>
    <div class="card-body">
        <textarea style="white-space: nowrap;width: 100%;height: 500px;"><?php
            echo PYS()->getLog()->getLogs();
            ?></textarea>
    </div>
</div>

<?php if ( Pinterest()->enabled() ) : ?>
    <div class="card card-static">
        <div class="card-header ">
			<?php Pinterest()->render_switcher_input( 'logs_enable' ); ?> Pinterest API Logs
            <div style="float: right;margin-top: 10px;">
                <a style="margin-right: 30px"
                   href="<?php echo esc_url( add_query_arg(['clear_pinterest_logs' => 'true', '_wpnonce_clear_logs' => $clear_nonce],buildAdminUrl( 'pixelyoursite', 'logs' ))); ?>">Clear
                    Pinterest API Logs</a>
                <a href="<?php echo esc_url( add_query_arg(['download_logs' => 'pinterest', '_wpnonce_download_logs' => $download_nonce],buildAdminUrl( 'pixelyoursite', 'logs' ))); ?>" target="_blank">Download Pinterest API
                    Logs</a>
            </div>
        </div>
        <div class="card-body">
            <textarea style="white-space: nowrap;width: 100%;height: 500px;"><?php
				echo Pinterest()->getLog()->getLogs();
				?></textarea>
        </div>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-4">
        <button class="btn btn-block btn-sm btn-save">Save Settings</button>
    </div>
</div>