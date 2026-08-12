<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Site Profile Import/Export page body.
 *
 * @var array $inventory  Provided by SiteProfileAdmin::render().
 * @var array $import_ctx Provided by SiteProfileAdmin::render().
 */

$export_url = admin_url( 'admin-post.php' );

// Open the Import tab first when an import session or message is active.
$import_messages = $import_ctx[ 'messages' ] ?? array();
$import_active   = ( isset( $import_ctx['stage'] ) && $import_ctx['stage'] !== 'upload' )
    || ! empty( $import_messages['errors'] )
    || ! empty( $import_messages['warnings'] )
    || ! empty( $import_messages['success'] );

/**
 * Render one selectable pixel row (ID checkbox + optional token checkbox).
 *
 * @param string $group         'pixels'.
 * @param string $slug
 * @param int    $index
 * @param string $id_masked
 * @param bool   $enabled
 * @param bool   $show_token    Whether to render the token checkbox at all.
 * @param bool   $token_enabled Whether the token checkbox is enabled (has a value).
 * @return void
 */
$render_pixel = function ( $group, $slug, $index, $id_masked, $enabled, $show_token, $token_enabled ): void {
    $base = 'pys_profile_' . $slug . '_' . $index;
    ?>
    <div class="pys-profile-pixel">
        <div class="pys-profile-pixel__id">
            <div class="small-checkbox">
                <input type="checkbox" id="<?php echo esc_attr( $base . '_id' ); ?>"
                       name="pys_profile[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $index ); ?>][id]"
                       value="1" class="small-control-input pys-profile-id-cb">
                <label class="small-control small-checkbox-label" for="<?php echo esc_attr( $base . '_id' ); ?>">
                    <span class="small-control-indicator"><i class="icon-check"></i></span>
                    <span class="small-control-description">ID <code><?php echo esc_html( $id_masked ); ?></code></span>
                </label>
            </div>
            <?php if ( ! $enabled ) : ?>
                <span class="pys-profile-pixel__meta">disabled</span>
            <?php endif; ?>
        </div>

        <?php if ( $show_token ) : ?>
            <div class="pys-profile-pixel__actions">
                <div class="small-checkbox">
                    <input type="checkbox" id="<?php echo esc_attr( $base . '_token' ); ?>"
                           name="pys_profile[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $index ); ?>][token]"
                           value="1" class="small-control-input pys-profile-token-cb" <?php echo $token_enabled ? '' : 'disabled'; ?>>
                    <label class="small-control small-checkbox-label" for="<?php echo esc_attr( $base . '_token' ); ?>">
                        <span class="small-control-indicator"><i class="icon-check"></i></span>
                        <span class="small-control-description">Include token<?php echo $token_enabled ? '' : ' (none)'; ?></span>
                    </label>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
};
?>

<div id="pys-profile" class="cards-wrapper cards-wrapper-style2">

    <!-- Export/Import switcher lives in the top menu (html-wrapper-single-page.php). -->

    <!-- ============================ EXPORT ============================ -->
    <div class="pys-profile-panel <?php echo $import_active ? '' : 'active'; ?>" data-pys-profile-panel="export">

        <form method="post" action="<?php echo esc_url( $export_url ); ?>" id="pys-profile-export-form">
            <input type="hidden" name="action" value="<?php echo esc_attr( SiteProfileAdmin::EXPORT_ACTION ); ?>">
            <?php wp_nonce_field( SiteProfileAdmin::EXPORT_ACTION ); ?>

            <div class="card card-style6 card-static">
                <div class="card-header card-header-style2">
                    <h4 class="secondary_heading_type2">Export Site Profile</h4>
                </div>
                <div class="card-body">
                    <p class="pys-profile-intro">
                        Module settings are selected by default. Pixel/account IDs and their tokens are
                        <b>excluded by default</b> — check them explicitly to include them.
                    </p>

                    <div class="gap-16">
                        <?php foreach ( $inventory as $module ) :
                            $slug   = $module['slug'];
                            $mod_id = 'pys_profile_mod_' . $slug;

                            $has_main   = ( $module['type'] === 'pixel' ) && ! empty( $module['pixels'] );
                            $has_body   = ( $module['type'] === 'core' )
                                || $has_main
                                || ( $module['type'] === 'flat' && ! empty( $module['has_sensitive'] ) );
                            ?>
                            <div class="card card-style6 <?php echo $has_body ? '' : 'card-static'; ?>" data-module="<?php echo esc_attr( $slug ); ?>">

                                <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
                                    <div class="disable-card d-flex align-items-center">
                                        <div class="secondary-switch">
                                            <input type="checkbox" id="<?php echo esc_attr( $mod_id ); ?>"
                                                   name="pys_profile[modules][<?php echo esc_attr( $slug ); ?>]"
                                                   value="1" class="custom-switch-input pys-profile-module-cb" checked>
                                            <label class="custom-switch-btn" for="<?php echo esc_attr( $mod_id ); ?>"></label>
                                        </div>
                                        <h4 class="secondary_heading_type2 switcher-label"><?php echo esc_html( $module['label'] ); ?></h4>
                                        <?php if ( $module['is_addon'] ) : ?>
                                            <span class="pys-profile-badge">add-on</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ( $has_body ) { cardCollapseSettings(); } ?>
                                </div>

                                <?php if ( $has_body ) : ?>
                                    <div class="card-body">
                                        <div class="gap-24">

                                            <?php if ( $module['type'] === 'core' ) : ?>
                                                <div class="small-checkbox">
                                                    <input type="checkbox" id="pys_profile_ae" name="pys_profile[automatic_events]"
                                                           value="1" class="small-control-input" checked>
                                                    <label class="small-control small-checkbox-label" for="pys_profile_ae">
                                                        <span class="small-control-indicator"><i class="icon-check"></i></span>
                                                        <span class="small-control-description">Automatic Events (global toggles)</span>
                                                    </label>
                                                </div>

                                            <?php elseif ( $module['type'] === 'pixel' ) : ?>
                                                <?php if ( $has_main ) : ?>
                                                    <div>
                                                        <div class="pys-profile-pixels">
                                                            <?php foreach ( $module['pixels'] as $pixel ) : ?>
                                                                <?php $render_pixel( 'pixels', $slug, $pixel['index'], $pixel['id_masked'], true, ! empty( $module['has_token_fields'] ), $pixel['has_token'] ); ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                            <?php elseif ( $module['type'] === 'flat' ) : ?>
                                                <div class="small-checkbox">
                                                    <input type="checkbox" id="pys_profile_flat_token_<?php echo esc_attr( $slug ); ?>"
                                                           name="pys_profile[flat_tokens][<?php echo esc_attr( $slug ); ?>]"
                                                           value="1" class="small-control-input pys-profile-token-cb">
                                                    <label class="small-control small-checkbox-label" for="pys_profile_flat_token_<?php echo esc_attr( $slug ); ?>">
                                                        <span class="small-control-indicator"><i class="icon-check"></i></span>
                                                        <span class="small-control-description">Include credentials / token</span>
                                                    </label>
                                                </div>
                                            <?php endif; ?>

                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Persistent summary strip -->
            <div class="pys-profile-summary" id="pys-profile-summary">
                <div class="pys-profile-summary__counts">
                    <b id="pys-sum-modules">0</b> settings blocks,
                    <b id="pys-sum-pixels">0</b> pixels,
                    <b id="pys-sum-tokens">0</b> tokens selected
                </div>
                <div id="pys-sum-token-warning" class="pys-profile-token-warning">
                    <i class="icon-alert-triangle"></i> This export will contain credentials.
                </div>
            </div>

            <div class="pys-profile-actions">
                <button type="submit" class="pys-profile-btn pys-profile-btn--primary">
                    <i class="icon-export"></i> Export Site Profile
                </button>
            </div>
        </form>
    </div>

    <!-- ============================ IMPORT ============================ -->
    <div class="pys-profile-panel <?php echo $import_active ? 'active' : ''; ?>" data-pys-profile-panel="import">
        <?php include PYS_FREE_PATH . '/includes/views/html-import-panel.php'; ?>
    </div>

    <!-- ============================ API & CLI ============================ -->
    <div class="pys-profile-panel" data-pys-profile-panel="apidocs">
        <?php include PYS_FREE_PATH . '/includes/views/html-api-docs.php'; ?>
    </div>
</div>

<script>
( function () {
    let root = document.getElementById( 'pys-profile' );
    if ( ! root ) { return; }

    // --- Tab switching (tabs live in the top menu, panels here) ---
    let tabs   = document.querySelectorAll( '[data-pys-profile-tab]' ),
        panels = document.querySelectorAll( '[data-pys-profile-panel]' ),
        title  = document.getElementById( 'pys-title' );
    tabs.forEach( function ( tab ) {
        tab.addEventListener( 'click', function ( e ) {
            e.preventDefault();
            let target = tab.getAttribute( 'data-pys-profile-tab' );
            tabs.forEach( function ( t ) { t.classList.toggle( 'active', t === tab ); } );
            panels.forEach( function ( p ) {
                p.classList.toggle( 'active', p.getAttribute( 'data-pys-profile-panel' ) === target );
            } );
            if ( title && tab.getAttribute( 'data-pys-title' ) ) {
                title.textContent = tab.getAttribute( 'data-pys-title' );
            }
        } );
    } );

    // --- Live summary strip ---
    let sumModules = document.getElementById( 'pys-sum-modules' ),
        sumPixels  = document.getElementById( 'pys-sum-pixels' ),
        sumTokens  = document.getElementById( 'pys-sum-tokens' ),
        warning    = document.getElementById( 'pys-sum-token-warning' );
    function recount() {
        if ( ! sumModules ) { return; }
        sumModules.textContent = root.querySelectorAll( '.pys-profile-module-cb:checked' ).length;
        sumPixels.textContent  = root.querySelectorAll( '.pys-profile-id-cb:checked' ).length;
        let tokens = root.querySelectorAll( '.pys-profile-token-cb:checked' ).length;
        sumTokens.textContent = tokens;
        warning.classList.toggle( 'is-visible', tokens > 0 );
    }
    root.addEventListener( 'change', function ( e ) {
        if ( e.target && e.target.matches( 'input[type=checkbox]' ) ) { recount(); }
    } );
    recount();
} )();
</script>
