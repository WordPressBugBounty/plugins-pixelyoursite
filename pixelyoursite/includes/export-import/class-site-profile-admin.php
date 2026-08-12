<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Admin controller for the Site Profile Import/Export page.
 *
 * Registers the sidebar entry, renders the Export/Import page body (via the
 * html-wrapper-single-page.php `pys_admin_{page}` hook), and streams the
 * generated profile as a JSON download through admin-post.php.
 */
class SiteProfileAdmin {

    const PAGE_SLUG      = 'pixelyoursite_import_export';
    const EXPORT_ACTION  = 'pys_export_profile';
    const EXPORT_NONCE   = 'pys_export_profile_nonce';

    const IMPORT_UPLOAD_ACTION  = 'pys_profile_import_upload';
    const IMPORT_DRYRUN_ACTION  = 'pys_profile_import_dryrun';
    const IMPORT_APPLY_ACTION   = 'pys_profile_import_apply';
    const IMPORT_RESTORE_ACTION = 'pys_profile_import_restore';
    const IMPORT_CANCEL_ACTION  = 'pys_profile_import_cancel';

    /**
     * Wire up hooks. Called once at plugin load (admin only).
     *
     * @return void
     */
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'registerMenu' ), 100 );
        add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handleExportDownload' ) );
        add_action( 'admin_post_' . self::IMPORT_UPLOAD_ACTION, array( __CLASS__, 'handleImportUpload' ) );
        add_action( 'admin_post_' . self::IMPORT_DRYRUN_ACTION, array( __CLASS__, 'handleImportDryRun' ) );
        add_action( 'admin_post_' . self::IMPORT_APPLY_ACTION, array( __CLASS__, 'handleImportApply' ) );
        add_action( 'admin_post_' . self::IMPORT_RESTORE_ACTION, array( __CLASS__, 'handleImportRestore' ) );
        add_action( 'admin_post_' . self::IMPORT_CANCEL_ACTION, array( __CLASS__, 'handleImportCancel' ) );
        add_action( 'pys_admin_' . self::PAGE_SLUG, array( __CLASS__, 'render' ) );
    }

    /**
     * Transient key holding the staged import profile for the current user.
     *
     * @return string
     */
    private static function profileKey(): string {
		return 'pys_profile_import_' . get_current_user_id();
	}

    /**
     * Transient key holding the staged import plan for the current user.
     *
     * @return string
     */
    private static function planKey(): string {
		return 'pys_profile_import_plan_' . get_current_user_id();
	}

    /**
     * Transient key holding queued import messages for the current user.
     *
     * @return string
     */
    private static function msgKey(): string {
		return 'pys_profile_import_msg_' . get_current_user_id();
	}

    /**
     * URL of the import/export page, optionally with extra query args.
     *
     * @param array $args
     *
     * @return string
     */
    private static function pageUrl( array $args = array() ): string {
        $url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        return $args ? add_query_arg( $args, $url ) : $url;
    }

    /**
     * URL of the configure stage (import page without the dry-run flag).
     *
     * @return string
     */
    public static function configureUrl(): string {
        return self::pageUrl();
    }

    /**
     * Register the Export/Import admin submenu page and its page slug.
     *
     * @return void
     */
    public static function registerMenu(): void {

        add_submenu_page(
            'pixelyoursite',
            'Export/Import',
            'Export/Import',
            'manage_pys',
            self::PAGE_SLUG,
            array( PYS(), 'adminSinglePage' ),
            9
        );

        PYS()->addAdminPageSlug( self::PAGE_SLUG );
    }

    /**
     * Render the Export/Import page body (tabs and export form).
     *
     * @return void
     */
    public static function render(): void {
        $inventory  = SiteProfileInventory::build();
        $import_ctx = self::getImportContext();
        include PYS_FREE_PATH . '/includes/views/html-import-export.php';
    }

    /**
     * Whether an import session/message is active (selects the initial tab).
     *
     * @return bool
     */
    public static function isImportActive(): bool {

        if ( isset( $_GET['pys_import_done'] ) ) {
            return true;
        }
        if ( get_transient( self::profileKey() ) !== false ) {
            return true;
        }
        $msg = get_transient( self::msgKey() );
        if ( is_array( $msg ) && ( ! empty( $msg['errors'] ) || ! empty( $msg['warnings'] ) || ! empty( $msg['success'] ) ) ) {
            return true;
        }
        return false;
    }

    /**
     * Resolve the current import stage and its data for rendering.
     *
     * @return array
     */
    private static function getImportContext(): array {

        $profile = get_transient( self::profileKey() );
        $plan    = get_transient( self::planKey() );

        $messages = get_transient( self::msgKey() );
        if ( $messages !== false ) {
            delete_transient( self::msgKey() );
        }

        $ctx = array(
            'stage'    => 'upload',
            'messages' => is_array( $messages ) ? $messages : array( 'errors' => array(), 'warnings' => array() ),
            'view'     => null,
            'diff'     => array(),
            'backup'   => SiteProfileImporter::getBackupInfo(),
            'backups'  => SiteProfileImporter::getBackups(),
            'audit'    => SiteProfileGuard::getLog( SiteProfileGuard::LOG_MAX ),
        );

        if ( isset( $_GET['pys_import_done'] ) ) {
            $ctx['stage'] = 'done';
            return $ctx;
        }

        if ( is_array( $plan ) && is_array( $profile )
            && isset( $_GET['pys_import_stage'] ) && $_GET['pys_import_stage'] === 'dryrun' ) {
            $importer     = SiteProfileImporter::fromArray( $profile );
            $ctx['stage'] = 'dryrun';
            $ctx['view']  = SiteProfileImportView::build( $profile );
            $ctx['diff']  = $importer->buildDiff( $plan );
            $ctx['plan']  = $plan;
            return $ctx;
        }

        if ( is_array( $profile ) ) {
            $ctx['stage'] = 'configure';
            $ctx['view']  = SiteProfileImportView::build( $profile );
            return $ctx;
        }

        return $ctx;
    }

    /**
     * Step 1: receive the uploaded file, validate, stage it in a transient.
     *
     * @return void
     */
    public static function handleImportUpload(): void {

        self::guard( self::IMPORT_UPLOAD_ACTION );

        if ( empty( $_FILES['pys_profile_file'] ) || ! isset( $_FILES['pys_profile_file']['tmp_name'] ) ) {
            self::redirectWithMessages( array( 'errors' => array( 'No file was uploaded.' ) ) );
        }

        $file = $_FILES['pys_profile_file'];
        if ( ! empty( $file['error'] ) || (int) $file['size'] === 0 ) {
            self::redirectWithMessages( array( 'errors' => array( 'The uploaded file is empty or failed to upload.' ) ) );
        }

        $raw      = file_get_contents( $file['tmp_name'] );
        $importer = SiteProfileImporter::fromString( $raw );

        if ( is_wp_error( $importer ) ) {
            self::redirectWithMessages( array( 'errors' => array( $importer->get_error_message() ) ) );
        }

        $validation = $importer->validate();
        if ( ! empty( $validation['errors'] ) ) {
            self::redirectWithMessages( $validation );
        }

        set_transient( self::profileKey(), $importer->getProfile(), HOUR_IN_SECONDS );
        delete_transient( self::planKey() );

        // Carry warnings (e.g. checksum) into the configure screen.
        if ( ! empty( $validation['warnings'] ) ) {
            set_transient( self::msgKey(), array( 'errors' => array(), 'warnings' => $validation['warnings'] ), HOUR_IN_SECONDS );
        }

        wp_safe_redirect( self::pageUrl() );
        exit;
    }

    /**
     * Step 2: store the chosen plan and move to the dry-run screen.
     *
     * @return void
     */
    public static function handleImportDryRun(): void {

        self::guard( self::IMPORT_DRYRUN_ACTION );

        if ( get_transient( self::profileKey() ) === false ) {
            self::redirectWithMessages( array( 'errors' => array( 'The import session expired. Please upload the file again.' ) ) );
        }

        $plan = self::buildImportPlanFromRequest();
        set_transient( self::planKey(), $plan, HOUR_IN_SECONDS );

        wp_safe_redirect( self::pageUrl( array( 'pys_import_stage' => 'dryrun' ) ) );
        exit;
    }

    /**
     * Step 3: apply the staged plan, then clear the session.
     *
     * @return void
     */
    public static function handleImportApply(): void {

        self::guard( self::IMPORT_APPLY_ACTION );

        $profile = get_transient( self::profileKey() );
        $plan    = get_transient( self::planKey() );

        if ( ! is_array( $profile ) || ! is_array( $plan ) ) {
            self::redirectWithMessages( array( 'errors' => array( 'The import session expired. Please upload the file again.' ) ) );
        }

        $importer = SiteProfileImporter::fromArray( $profile );
        $result   = $importer->apply( $plan );

        delete_transient( self::profileKey() );
        delete_transient( self::planKey() );

        $applied_count = isset( $result['applied'] ) ? count( $result['applied'] ) : 0;
        SiteProfileGuard::log( 'import', 'ok', 'admin, ' . $applied_count . ' module(s) applied' );
        $success       = $applied_count > 0
            ? sprintf( _n( 'Site profile imported — %d module updated.', 'Site profile imported — %d modules updated.', $applied_count, 'pys' ), $applied_count )
            : 'Import finished — no changes were applied.';

        set_transient(
            self::msgKey(),
            array( 'errors' => array(), 'warnings' => array(), 'success' => array( $success ) ),
            HOUR_IN_SECONDS
        );

        wp_safe_redirect( self::pageUrl( array( 'pys_import_done' => 1 ) ) );
        exit;
    }

    /**
     * Restore the pre-import snapshot.
     *
     * @return void
     */
    public static function handleImportRestore(): void {

        self::guard( self::IMPORT_RESTORE_ACTION );

        $backup_id = isset( $_POST['backup_id'] ) ? sanitize_key( wp_unslash( $_POST['backup_id'] ) ) : null;

        $ok = SiteProfileImporter::restore( $backup_id );
        SiteProfileGuard::log( 'restore', $ok ? 'ok' : 'error', 'admin, ' . ( $backup_id ? $backup_id : 'latest' ) );
        self::redirectWithMessages(
            $ok
                ? array( 'warnings' => array(), 'errors' => array(), 'success' => array( 'Previous settings restored.' ) )
                : array( 'errors' => array( 'Nothing to restore.' ) )
        );
    }

    /**
     * Discard the current import session.
     *
     * @return void
     */
    public static function handleImportCancel(): void {
        self::guard( self::IMPORT_CANCEL_ACTION );
        delete_transient( self::profileKey() );
        delete_transient( self::planKey() );
        wp_safe_redirect( self::pageUrl() );
        exit;
    }

    /**
     * Capability + nonce guard for import actions.
     *
     * @param string $action
     *
     * @return void
     */
    private static function guard( $action ): void {
        if ( ! current_user_can( 'manage_pys' ) ) {
            wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( $action );
    }

    /**
     * Store messages and redirect back to the page.
     *
     * @param array $messages
     *
     * @return void
     */
    private static function redirectWithMessages( array $messages ): void {
        $messages = wp_parse_args( $messages, array( 'errors' => array(), 'warnings' => array(), 'success' => array() ) );
        set_transient( self::msgKey(), $messages, HOUR_IN_SECONDS );
        wp_safe_redirect( self::pageUrl() );
        exit;
    }

    /**
     * Translate the submitted import checklist into an importer plan.
     *
     * @return array
     */
    private static function buildImportPlanFromRequest(): array {

        $raw = isset( $_POST['pys_import'] ) && is_array( $_POST['pys_import'] )
            ? wp_unslash( $_POST['pys_import'] )
            : array();

        $plan = array();

        // Settings-block action per module.
        if ( isset( $raw['modules'] ) && is_array( $raw['modules'] ) ) {
            foreach ( $raw['modules'] as $slug => $m ) {
                $slug = sanitize_key( $slug );
                $plan[ $slug ]['action'] = ( isset( $m['action'] ) && $m['action'] === 'overwrite' ) ? 'overwrite' : 'skip';
            }
        }

        // Core: automatic events opt-in.
        if ( ! empty( $raw['core']['automatic_events'] ) ) {
            $plan['core']['include_automatic_events'] = true;
        }

        // Flat-module credential opt-in.
        if ( isset( $raw['flat_tokens'] ) && is_array( $raw['flat_tokens'] ) ) {
            foreach ( $raw['flat_tokens'] as $slug => $v ) {
                $plan[ sanitize_key( $slug ) ]['include_token'] = ! empty( $v );
            }
        }

        if ( isset( $raw['pixels'] ) && is_array( $raw['pixels'] ) ) {
            foreach ( $raw['pixels'] as $slug => $rows ) {
                $slug = sanitize_key( $slug );
                if ( ! is_array( $rows ) ) {
                    continue;
                }
                foreach ( $rows as $ref => $row ) {
                    $ref = self::sanitizePixelRef( $ref );
                    if ( $ref === '' || ! is_array( $row ) ) {
                        continue;
                    }
                    $action = isset( $row['action'] ) && in_array( $row['action'], array( 'add', 'override' ), true )
                        ? $row['action'] : 'skip';

                    $entry = array( 'action' => $action );
                    if ( $action === 'override' ) {
                        $entry['target'] = isset( $row['target'] ) ? self::sanitizePixelRef( $row['target'] ) : 'main';
                        if ( $entry['target'] === '' ) {
                            $entry['target'] = 'main';
                        }
                    }
                    if ( ! empty( $row['token'] ) ) {
                        $entry['include_token'] = true;
                    }
                    $plan[ $slug ]['pixels'][ $ref ] = $entry;
                }
            }
        }

        // Domain / URL remap pairs (source→target + custom).
        if ( isset( $raw['remap'] ) && is_array( $raw['remap'] ) ) {
            foreach ( $raw['remap'] as $pair ) {
                if ( ! is_array( $pair ) ) {
                    continue;
                }
                $from = isset( $pair['from'] ) ? trim( $pair['from'] ) : '';
                $to   = isset( $pair['to'] ) ? trim( $pair['to'] ) : '';
                if ( $from !== '' ) {
                    $plan['_remap'][] = array(
                        'from' => sanitize_text_field( $from ),
                        'to'   => sanitize_text_field( $to ),
                    );
                }
            }
        }

        return $plan;
    }

    /**
     * Sanitize a pixel ref: "main" or "sp_<int>". Anything else -> "".
     *
     * @param mixed $ref
     *
     * @return string
     */
    private static function sanitizePixelRef( $ref ): string {
        $ref = is_scalar( $ref ) ? (string) $ref : '';
        if ( $ref === 'main' ) {
            return 'main';
        }
        if ( preg_match( '/^sp_(\d+)$/', $ref, $m ) ) {
            return 'sp_' . (int) $m[1];
        }
        return '';
    }

    /**
     * Stream the generated Site Profile as a JSON file download.
     *
     * @return void
     */
    public static function handleExportDownload(): void {

        if ( ! current_user_can( 'manage_pys' ) ) {
            wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) );
        }

        check_admin_referer( self::EXPORT_ACTION, '_wpnonce' );

        $selection = self::buildSelectionFromRequest();
        $exporter  = new SiteProfileExporter( $selection );
        $json      = $exporter->toJson();

        SiteProfileGuard::log( 'export', 'ok', 'admin download' );

        $host     = parse_url( site_url(), PHP_URL_HOST );
        $host     = $host ? preg_replace( '/[^a-z0-9\-]+/i', '-', $host ) : 'site';
        $filename = 'pys-site-profile-' . $host . '-' . gmdate( 'Ymd-His' ) . '.json';

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $json ) );

        echo $json;
        exit;
    }

    /**
     * Translate the submitted checklist into a SiteProfileExporter selection.
     *
     * @return array
     */
    private static function buildSelectionFromRequest(): array {

        $raw = isset( $_POST['pys_profile'] ) && is_array( $_POST['pys_profile'] )
            ? wp_unslash( $_POST['pys_profile'] )
            : array();

        // Module-level inclusion.
        $modules = array();
        if ( isset( $raw['modules'] ) && is_array( $raw['modules'] ) ) {
            $modules = array_keys( array_filter( $raw['modules'] ) );
        }

        $pixel_indices = array();
        $tokens        = array();

        foreach ( $modules as $module_slug ) {
            if ( SiteProfileModuleRegistry::isPixelModule( $module_slug ) ) {
                $pixel_indices[ $module_slug ] = array();
                $tokens[ $module_slug ]        = array();
            }
        }

        if ( isset( $raw['pixels'] ) && is_array( $raw['pixels'] ) ) {
            foreach ( $raw['pixels'] as $slug => $rows ) {

                $slug   = sanitize_key( $slug );
                $idxs   = array();
                $tok    = array();

                if ( is_array( $rows ) ) {
                    foreach ( $rows as $index => $checkboxes ) {
                        $index = (int) $index;
                        if ( ! empty( $checkboxes['id'] ) ) {
                            $idxs[] = $index;
                        }
                        $tok[ $index ] = ! empty( $checkboxes['token'] );
                    }
                }

                $pixel_indices[ $slug ] = $idxs;
                $tokens[ $slug ]        = $tok;
            }
        }

        // Flat-module token opt-in.
        if ( isset( $raw['flat_tokens'] ) && is_array( $raw['flat_tokens'] ) ) {
            foreach ( $raw['flat_tokens'] as $slug => $value ) {
                $tokens[ sanitize_key( $slug ) ] = ! empty( $value );
            }
        }

        return array(
            'modules'                  => $modules,
            'include_automatic_events' => ! empty( $raw['automatic_events'] ),
            'pixel_indices'            => $pixel_indices,
            'tokens'                   => $tokens,
        );
    }
}
