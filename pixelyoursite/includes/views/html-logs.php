<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}



?>

<div class="card card-static">
    <div class="card-header ">
        <?php PYS()->render_switcher_input('pys_logs_enable'); ?> Meta API Logs
        <div style="float: right;margin-top: 10px;">
            <a style="margin-right: 30px"
               href="<?php echo esc_url( buildAdminUrl( 'pixelyoursite', 'logs' ) ); ?>&clear_plugin_logs=true">Clear
                Meta API Logs</a>
            <a href="<?php echo esc_url( buildAdminUrl( 'pixelyoursite', 'logs' ) ); ?>&download_logs=meta"" target="_blank" download>Download Meta API logs</a>
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
                   href="<?php echo esc_url( buildAdminUrl( 'pixelyoursite', 'logs' ) ); ?>&clear_pinterest_logs=true">Clear
                    Pinterest API Logs</a>
                <a href="<?php echo esc_url( buildAdminUrl( 'pixelyoursite', 'logs' ) ); ?>&download_logs=pinterest" target="_blank" download>Download Pinterest API
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