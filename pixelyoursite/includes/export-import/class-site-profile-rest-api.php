<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * REST API for the Site Profile export/import feature (agency tooling).
 *
 * Namespace `pys-profile/v1`:
 *   GET  /export   — download the current site's profile as JSON.
 *   POST /import   — validate + dry-run diff (default) or apply a profile.
 *   POST /restore  — roll back to the snapshot taken before the last import.
 *
 * Auth: every route is gated by the `manage_pys` capability. That single check
 * covers both transports WordPress supports here — a browser request (cookie +
 * X-WP-Nonce) and, for cross-site agency automation, WP Application Passwords
 * (HTTP Basic auth) — because WP resolves the current user from whichever
 * mechanism authenticated the request before permission_callback runs. No
 * separate nonce check is needed (and nonce alone would not work cross-site).
 *
 * All work is delegated to the same core classes the admin UI uses
 * (SiteProfileExporter / SiteProfileImporter), so REST and UI stay in lockstep.
 *
 * Example (cross-site rollout with an Application Password):
 *   # Export the source site (with credentials) to a file:
 *   curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
 *        "https://source.example/wp-json/pys-profile/v1/export?include_tokens=true" \
 *        -o profile.json
 *
 *   # Dry-run against the target (default; nothing is written):
 *   curl -u "admin:yyyy ..." -H "Content-Type: application/json" \
 *        -d "{\"profile\": $(cat profile.json), \"include_tokens\": true}" \
 *        "https://target.example/wp-json/pys-profile/v1/import"
 *
 *   # Apply for real, then (if needed) roll back:
 *   ... same body with "apply": true
 *   curl -u "admin:yyyy ..." -X POST "https://target.example/wp-json/pys-profile/v1/restore"
 */
class SiteProfileRestAPI {

    const REST_NAMESPACE = 'pys-profile/v1';

    private static $_instance;

    /**
     * Return the shared singleton instance, creating it on first use.
     *
     * @return self
     */
    public static function instance(): self {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Hook route registration onto the REST API init action.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register the three profile routes.
     *
     * @return void
     */
    public function register_routes(): void {

        register_rest_route(
            self::REST_NAMESPACE,
            '/export',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_export' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'modules'                  => array(
                        'description' => 'Module slugs to include (CSV or array). Omit for all.',
                    ),
                    'include_tokens'           => array(
                        'type'        => 'boolean',
                        'default'     => false,
                        'description' => 'Include credentials / API tokens in the export.',
                    ),
                    'include_automatic_events' => array(
                        'type'        => 'boolean',
                        'default'     => true,
                        'description' => 'Include the core Automatic Events toggles.',
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/import',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_import' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'profile'        => array(
                        'required'    => true,
                        'description' => 'The exported profile (object, or its JSON string).',
                    ),
                    'apply'          => array(
                        'type'        => 'boolean',
                        'default'     => false,
                        'description' => 'false = dry-run diff only (default); true = write changes.',
                    ),
                    'include_tokens' => array(
                        'type'        => 'boolean',
                        'default'     => false,
                        'description' => 'Auto-plan: import credentials / API tokens.',
                    ),
                    'modules'        => array(
                        'description' => 'Auto-plan: restrict to these module slugs (CSV or array).',
                    ),
                    'remap'          => array(
                        'description' => 'Array of {from,to} URL/domain replacement pairs.',
                    ),
                    'plan'           => array(
                        'description' => 'Explicit granular plan; overrides the auto-plan when present.',
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/restore',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_restore' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );
    }

    /**
     * Only users who can manage PixelYourSite may touch these endpoints.
     *
     * @param \WP_REST_Request $request
     *
     * @return bool|\WP_Error
     */
    public function check_permissions( $request ) {
        if ( ! current_user_can( 'manage_pys' ) ) {
            return new \WP_Error(
                'pys_profile_forbidden',
                'You do not have permission to manage PixelYourSite profiles.',
                array( 'status' => rest_authorization_required_code() )
            );
        }
        return true;
    }

    /**
     * GET /export — return the site profile as JSON.
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_export( $request ) {

        $rate = SiteProfileGuard::checkRate( 'export' );
        if ( is_wp_error( $rate ) ) {
            SiteProfileGuard::log( 'export', 'blocked', 'rate limit' );
            return $rate;
        }

        $selection = array();

        // Restrict to specific modules when asked; otherwise export everything.
        $modules_param = $request->get_param( 'modules' );
        if ( $modules_param !== null && $modules_param !== '' ) {
            $slugs = is_array( $modules_param )
                ? $modules_param
                : explode( ',', (string) $modules_param );
            $slugs = array_values( array_filter( array_map(
                function ( $s ) { return sanitize_key( trim( (string) $s ) ); },
                $slugs
            ) ) );
            $selection['modules'] = $slugs;
        }

        // Automatic Events default ON (they are part of a full profile).
        $selection['include_automatic_events'] = ( $request->get_param( 'include_automatic_events' ) === null )
            ? true
            : (bool) rest_sanitize_boolean( $request->get_param( 'include_automatic_events' ) );

        // Credentials are excluded unless explicitly requested.
        if ( rest_sanitize_boolean( $request->get_param( 'include_tokens' ) ) ) {
            $tokens = array();
            foreach ( SiteProfileModuleRegistry::getAvailableSlugs() as $slug ) {
                $tokens[ $slug ] = true;
            }
            $selection['tokens'] = $tokens;
        }

        $exporter = new SiteProfileExporter( $selection );

        $with_tokens = ! empty( $selection['tokens'] );
        SiteProfileGuard::log( 'export', 'ok', $with_tokens ? 'with tokens' : 'no tokens' );

        $response = rest_ensure_response( $exporter->build() );
        // A profile can carry credentials (include_tokens) — keep it out of caches, proxies and logs.
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
        $response->header( 'Pragma', 'no-cache' );

        return $response;
    }

    /**
     * POST /import — validate then dry-run (default) or apply a profile.
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_import( $request ) {

        $rate = SiteProfileGuard::checkRate( 'import' );
        if ( is_wp_error( $rate ) ) {
            SiteProfileGuard::log( 'import', 'blocked', 'rate limit' );
            return $rate;
        }

        // ---- Reject oversized bodies before any work (authenticated, but still bound memory) ----
        $raw_body = $request->get_body();
        if ( is_string( $raw_body ) && strlen( $raw_body ) > 2 * MB_IN_BYTES ) {
            return new \WP_Error(
                'pys_profile_too_large',
                'The profile payload is too large (limit 2 MB).',
                array( 'status' => 413 )
            );
        }

        // ---- Parse the profile ----
        $profile = $request->get_param( 'profile' );
        if ( is_string( $profile ) ) {
            // Cap nesting depth; a legitimate profile is only a few levels deep.
            $decoded = json_decode( $profile, true, 64 );
            $profile = is_array( $decoded ) ? $decoded : null;
        }
        if ( ! is_array( $profile ) || empty( $profile ) ) {
            return new \WP_Error(
                'pys_profile_missing',
                'Request must include a "profile" object (the exported JSON).',
                array( 'status' => 400 )
            );
        }

        $importer = SiteProfileImporter::fromArray( $profile );

        // ---- Validate (errors block; warnings do not) ----
        $validation = $importer->validate();
        if ( ! empty( $validation['errors'] ) ) {
            return new \WP_Error(
                'pys_profile_invalid',
                'The profile failed validation.',
                array(
                    'status'   => 400,
                    'errors'   => array_values( $validation['errors'] ),
                    'warnings' => array_values( $validation['warnings'] ),
                )
            );
        }

        // ---- Build the plan (explicit passthrough or auto "replicate-all") ----
        $explicit = $request->get_param( 'plan' );
        if ( is_array( $explicit ) && ! empty( $explicit ) ) {
            $plan      = SiteProfileImporter::sanitizePlan( $explicit );
            $plan_mode = 'explicit';
        } else {
            $plan      = $this->buildAutoPlan( $profile, $request );
            $plan_mode = 'auto';
        }

        // Domain/URL remap pairs apply to both plan modes.
        $remap = $this->sanitizeRemap( $request->get_param( 'remap' ) );
        if ( ! empty( $remap ) ) {
            $plan['_remap'] = $remap;
        }

        $diff = $importer->buildDiff( $plan );

        $response = array(
            'valid'      => true,
            'warnings'   => array_values( $validation['warnings'] ),
            'plan_mode'  => $plan_mode,
            'dry_run'    => ! rest_sanitize_boolean( $request->get_param( 'apply' ) ),
            'diff_count' => count( $diff ),
            'diff'       => $diff,
        );

        // ---- Dry-run: stop here, nothing written ----
        if ( $response['dry_run'] ) {
            SiteProfileGuard::log( 'import', 'dry-run', $plan_mode . ', ' . count( $diff ) . ' change(s)' );
            return rest_ensure_response( $response );
        }

        SiteProfileGuard::log( 'import', 'ok', $plan_mode . ', ' . count( $diff ) . ' change(s) applied' );

        // ---- Apply (backup happens inside apply()) ----
        $result              = $importer->apply( $plan );
        $response['applied'] = isset( $result['applied'] ) ? $result['applied'] : array();
        $response['backups'] = SiteProfileImporter::getBackups();

        return rest_ensure_response( $response );
    }

    /**
     * Parse auto-plan params and delegate to the shared plan builder.
     *
     * @param array            $profile
     * @param \WP_REST_Request $request
     *
     * @return array
     */
    private function buildAutoPlan( array $profile, $request ): array {

        $include_tokens  = (bool) rest_sanitize_boolean( $request->get_param( 'include_tokens' ) );
        // Scripts (head_footer) default OFF over the API — never applied without an explicit opt-in.
        $include_scripts = (bool) rest_sanitize_boolean( $request->get_param( 'include_scripts' ) );

        $modules_filter = $request->get_param( 'modules' );
        if ( $modules_filter !== null && $modules_filter !== '' ) {
            $modules_filter = is_array( $modules_filter )
                ? $modules_filter
                : explode( ',', (string) $modules_filter );
        } else {
            $modules_filter = null;
        }

        return SiteProfileImporter::buildAutoPlan( $profile, $include_tokens, $modules_filter, $include_scripts );
    }

    /**
     * Sanitize domain/URL remap pairs from the REST body.
     *
     * @param mixed $raw
     *
     * @return array<int, array{from:string,to:string}>
     */
    private function sanitizeRemap( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return array();
        }
        $pairs = array();
        foreach ( $raw as $pair ) {
            if ( ! is_array( $pair ) ) {
                continue;
            }
            $from = isset( $pair['from'] ) ? sanitize_text_field( (string) $pair['from'] ) : '';
            $to   = isset( $pair['to'] ) ? sanitize_text_field( (string) $pair['to'] ) : '';
            if ( $from !== '' ) {
                $pairs[] = array( 'from' => $from, 'to' => $to );
            }
        }
        return $pairs;
    }

    /**
     * POST /restore — roll back to the snapshot taken before the last import.
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_restore( $request ) {

        $rate = SiteProfileGuard::checkRate( 'restore' );
        if ( is_wp_error( $rate ) ) {
            SiteProfileGuard::log( 'restore', 'blocked', 'rate limit' );
            return $rate;
        }

        $backups = SiteProfileImporter::getBackups();
        if ( empty( $backups ) ) {
            return new \WP_Error(
                'pys_profile_no_backup',
                'There is no import backup to restore.',
                array( 'status' => 404 )
            );
        }

        // Optional snapshot id; defaults to the most recent snapshot.
        $backup_id = $request->get_param( 'backup_id' );

        if ( ! SiteProfileImporter::restore( $backup_id ) ) {
            SiteProfileGuard::log( 'restore', 'error', 'unknown backup_id' );
            return new \WP_Error(
                'pys_profile_restore_failed',
                'The backup could not be restored (unknown backup_id?).',
                array( 'status' => 500 )
            );
        }

        SiteProfileGuard::log( 'restore', 'ok', $backup_id ? (string) $backup_id : 'latest' );

        return rest_ensure_response( array(
            'restored' => true,
            'backups'  => SiteProfileImporter::getBackups(),
        ) );
    }
}