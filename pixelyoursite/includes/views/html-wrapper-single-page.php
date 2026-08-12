<?php

namespace PixelYourSite;

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/** @var PYS $this */

include_once "html-popovers.php";

?>

<div class="wrap" id="pys">
    <div class=" pys-general-menu">
        <div class="pys-logo">
            <img src="<?php echo PYS_FREE_URL; ?>/dist/images/pys-logo.svg" alt="pys-logo">
        </div>

        <nav class="nav nav-tabs">

            <?php if ( getCurrentAdminPage() === SiteProfileAdmin::PAGE_SLUG ) :
                 $import_active = SiteProfileAdmin::isImportActive();
                ?>
                <a class="nav-item nav-link <?php echo $import_active ? '' : 'active'; ?>"
                   href="#" data-pys-profile-tab="export" data-pys-title="Export Site Profile">Export</a>
                <a class="nav-item nav-link <?php echo $import_active ? 'active' : ''; ?>"
                   href="#" data-pys-profile-tab="import" data-pys-title="Import Site Profile">Import</a>
                <a class="nav-item nav-link"
                   href="#" data-pys-profile-tab="apidocs" data-pys-title="Site Profile — API &amp; CLI">API &amp; CLI</a>

            <?php else : ?>

            <?php foreach ( getAdminPrimaryNavTabs() as $tab_key => $tab_data ) : ?>

                <?php

                $classes = array(
                    'nav-item',
                    'nav-link',
                );

                if ( $tab_key == getCurrentAdminTab() ) {
                    $classes[] = 'active';
                }

                $classes = implode( ' ', $classes );

                if ( isset( $tab_data[ 'class' ] ) ) {
                    $classes .= ' ' . $tab_data[ 'class' ];
                }

                ?>

                <a class="<?php echo esc_attr( $classes ); ?>"
                   href="<?php echo esc_url( $tab_data[ 'url' ] ); ?>">
                    <?php esc_html_e( $tab_data[ 'name' ] ); ?>
                </a>

            <?php endforeach; ?>

            <?php endif; ?>

        </nav>
    </div>

    <?php

    switch ( getCurrentAdminPage() ) {
        case 'pixelyoursite_report':
            $title = 'System Report';
            break;
        case 'pixelyoursite_utm':
            $title = 'UTM Builder';
            break;
        case 'pixelyoursite_licenses':
            $title = 'Licenses';
            break;
        case 'pixelyoursite_settings':
            $title = 'Global Settings';
            break;
        case 'pixelyoursite_queue_settings':
            $title = 'Queue System PRO';
            break;
        case SiteProfileAdmin::PAGE_SLUG:
            $title = SiteProfileAdmin::isImportActive() ? 'Import Site Profile' : 'Export Site Profile';
            break;
        default:
            $title = 'Welcome to PixelYourSite Pro';
    }
    ?>

    <h1 id="pys-title" class="primary_heading"><?php _e( $title, 'pys' ); ?></h1>
    <div class="container">
        <div class="general-row d-flex">
            <div class="general-col">

                <?php
                switch ( getCurrentAdminPage() ) {
                    case 'pixelyoursite_report':
                        include_once "html-report.php";
                        break;
                    case 'pixelyoursite_utm':
                        include_once "html-utm-templates.php";
                        break;
                    case 'pixelyoursite_licenses':
                        PYS()->adminUpdateLicense();

                        /** @var Plugin|Settings $plugin */
                        foreach ( PYS()->getRegisteredPlugins() as $plugin ) {
                            if ( $plugin->getSlug() !== 'head_footer' ) {
                                $plugin->adminUpdateLicense();
                            }
                        }
                        include_once "html-licenses.php";
                        break;
                    case 'pixelyoursite_settings': ?>
                        <form method="post" enctype="multipart/form-data" id="pys-form">
                            <?php
                                wp_nonce_field( 'pys_save_settings' );
                                include_once "html-main-settings.php";
                            ?>
                        </form>
                        <?php
                        break;
                    case 'pixelyoursite_queue_settings': ?>
                        <form method="post" enctype="multipart/form-data" id="pys-form">
                            <?php
                            wp_nonce_field( 'pys_save_settings' );
                            include_once "html-queue-settings.php";
                            ?>
                        </form>
                        <?php
                        break;
                    case 'pixelyoursite_mcp': ?>
                        <form method="post" enctype="multipart/form-data" id="pys-form">
                            <?php
                            wp_nonce_field( 'pys_save_settings' );
                            do_action( 'pys_admin_' . getCurrentAdminPage() );
                            ?>
                        </form>
                        <?php
                        break;
                    default:
                        do_action( 'pys_admin_' . getCurrentAdminPage() );
                }

                ?>

            </div>
        </div>
    </div>

    <?php
        switch ( getCurrentAdminPage() ) {
            case 'pixelyoursite_report':
                include_once PYS_FREE_VIEW_PATH . '/UI/button-download-report.php';
                break;
            case 'pixelyoursite_settings':
                include_once PYS_FREE_VIEW_PATH . '/UI/button-save.php';
                break;
            case 'pixelyoursite_queue_settings':
                include_once PYS_FREE_VIEW_PATH . '/UI/button-save.php';
                break;
            case 'pixelyoursite_mcp':
                include_once PYS_FREE_VIEW_PATH . '/UI/button-save.php';
                break;
        }
    ?>
</div>

