<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * WP-CLI commands for the Site Profile export/import feature.
 *
 * Registered as `wp pys profile <export|import|restore>`. A thin wrapper around
 * the same core classes the admin UI and REST API use (SiteProfileExporter /
 * SiteProfileImporter), so all three entry points behave identically.
 */
class SiteProfileCliCommand {

    /**
     * Export the current site's Site Profile as JSON.
     *
     * ## OPTIONS
     *
     * [<file>]
     * : Write the profile to this file. Omit to print to STDOUT.
     *
     * [--modules=<slugs>]
     * : Comma-separated module slugs to include (e.g. facebook,ga). Default: all.
     *
     * [--include-tokens]
     * : Include credentials / API tokens (excluded by default).
     *
     * [--no-automatic-events]
     * : Exclude the core Automatic Events toggles (included by default).
     *
     * ## EXAMPLES
     *
     *     # Full profile with tokens to a file
     *     wp pys profile export profile.json --include-tokens
     *
     *     # Only Meta + GA, to STDOUT
     *     wp pys profile export --modules=facebook,ga
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     */
    public function export( $args, $assoc_args ): void {

        $selection = array();

        $modules = \WP_CLI\Utils\get_flag_value( $assoc_args, 'modules', null );
        if ( ! empty( $modules ) ) {
            $selection['modules'] = array_values( array_filter( array_map(
                function ( $s ) { return sanitize_key( trim( (string) $s ) ); },
                explode( ',', (string) $modules )
            ) ) );
        }

        $selection['include_automatic_events'] = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'automatic-events', true );

        if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'include-tokens', false ) ) {
            $tokens = array();
            foreach ( SiteProfileModuleRegistry::getAvailableSlugs() as $slug ) {
                $tokens[ $slug ] = true;
            }
            $selection['tokens'] = $tokens;
        }

        $json = ( new SiteProfileExporter( $selection ) )->toJson();

        SiteProfileGuard::log( 'export', 'ok', 'cli' );

        $file = isset( $args[0] ) ? (string) $args[0] : '';
        if ( $file !== '' ) {
            if ( false === file_put_contents( $file, $json ) ) {
                \WP_CLI::error( "Could not write to {$file}" );
            }
            \WP_CLI::success( sprintf( 'Profile exported to %s (%d bytes).', $file, strlen( $json ) ) );
        } else {
            \WP_CLI::line( $json );
        }
    }

    /**
     * Import a Site Profile. Dry-run by default; pass --apply to write.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to a profile JSON file, or - to read from STDIN.
     *
     * [--apply]
     * : Write the changes. Without this flag the command only prints a diff.
     *
     * [--include-tokens]
     * : Auto-plan: import credentials / API tokens.
     *
     * [--include-scripts]
     * : Auto-plan: import head_footer scripts (site-wide head/footer code). Off by default.
     *
     * [--modules=<slugs>]
     * : Auto-plan: restrict to these comma-separated module slugs.
     *
     * [--plan=<file>]
     * : Path to an explicit plan JSON file; overrides the auto-plan.
     *
     * [--remap-from=<url>]
     * : Find this URL/domain in remap-target fields.
     *
     * [--remap-to=<url>]
     * : Replace --remap-from with this value.
     *
     * [--format=<format>]
     * : Diff output format. One of: table, json, csv, yaml. Default: table.
     *
     * [--yes]
     * : Skip the confirmation prompt when applying.
     *
     * ## EXAMPLES
     *
     *     # See what would change (nothing written)
     *     wp pys profile import profile.json
     *
     *     # Apply everything including tokens, no prompt
     *     wp pys profile import profile.json --apply --include-tokens --yes
     *
     *     # Apply with a staging -> production domain swap
     *     wp pys profile import profile.json --apply --remap-from=https://staging.example --remap-to=https://example.com
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     */
    public function import( $args, $assoc_args ): void {

        if ( empty( $args[0] ) ) {
            \WP_CLI::error( 'Provide a profile file path (or - for STDIN).' );
        }

        $path = (string) $args[0];
        if ( $path === '-' ) {
            $raw = file_get_contents( 'php://stdin' );
        } else {
            $raw = file_exists( $path ) ? file_get_contents( $path ) : false;
        }
        if ( $raw === false || $raw === null || $raw === '' ) {
            \WP_CLI::error( "Cannot read profile file: {$path}" );
        }

        $profile = json_decode( $raw, true );
        if ( ! is_array( $profile ) ) {
            \WP_CLI::error( 'Profile file is not valid JSON.' );
        }

        $importer = SiteProfileImporter::fromArray( $profile );

        // Validate (errors abort; warnings are shown but do not block).
        $validation = $importer->validate();
        foreach ( $validation['warnings'] as $warning ) {
            \WP_CLI::warning( $warning );
        }
        if ( ! empty( $validation['errors'] ) ) {
            foreach ( $validation['errors'] as $error ) {
                \WP_CLI::log( '  - ' . $error );
            }
            \WP_CLI::error( 'Profile failed validation.' );
        }

        // Build the plan: explicit file or auto "replicate-all".
        $plan_file = \WP_CLI\Utils\get_flag_value( $assoc_args, 'plan', null );
        if ( ! empty( $plan_file ) ) {
            $plan_raw = file_exists( $plan_file ) ? file_get_contents( $plan_file ) : false;
            $plan_arr = ( $plan_raw !== false ) ? json_decode( $plan_raw, true ) : null;
            if ( ! is_array( $plan_arr ) ) {
                \WP_CLI::error( "Cannot read plan file: {$plan_file}" );
            }
            $plan      = SiteProfileImporter::sanitizePlan( $plan_arr );
            $plan_mode = 'explicit';
        } else {
            $include_tokens  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'include-tokens', false );
            $include_scripts = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'include-scripts', false );
            $modules         = \WP_CLI\Utils\get_flag_value( $assoc_args, 'modules', null );
            $filter          = ! empty( $modules ) ? explode( ',', (string) $modules ) : null;
            $plan            = SiteProfileImporter::buildAutoPlan( $profile, $include_tokens, $filter, $include_scripts );
            $plan_mode       = 'auto';
        }

        // Optional single remap pair.
        $remap_from = \WP_CLI\Utils\get_flag_value( $assoc_args, 'remap-from', null );
        if ( ! empty( $remap_from ) ) {
            $plan['_remap'] = array( array(
                'from' => sanitize_text_field( (string) $remap_from ),
                'to'   => sanitize_text_field( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'remap-to', '' ) ),
            ) );
        }

        $diff   = $importer->buildDiff( $plan );
        $apply  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );
        $format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

        \WP_CLI::log( sprintf( 'Plan: %s. Changes: %d.', $plan_mode, count( $diff ) ) );

        if ( ! empty( $diff ) ) {
            $rows = array();
            foreach ( $diff as $row ) {
                $rows[] = array(
                    'module'  => isset( $row['module'] ) ? $row['module'] : '',
                    'field'   => isset( $row['field'] ) ? $row['field'] : '',
                    'current' => $this->scalarize( isset( $row['old'] ) ? $row['old'] : null ),
                    'new'     => $this->scalarize( isset( $row['new'] ) ? $row['new'] : null ),
                );
            }
            \WP_CLI\Utils\format_items( $format, $rows, array( 'module', 'field', 'current', 'new' ) );
        }

        if ( ! $apply ) {
            SiteProfileGuard::log( 'import', 'dry-run', 'cli, ' . $plan_mode . ', ' . count( $diff ) . ' change(s)' );
            \WP_CLI::success( 'Dry-run complete — nothing written. Re-run with --apply to write these changes.' );
            return;
        }

        if ( empty( $diff ) ) {
            \WP_CLI::success( 'Nothing to apply — the profile matches the current settings.' );
            return;
        }

        \WP_CLI::confirm( 'Apply these changes? A backup snapshot is taken first.', $assoc_args );

        $result  = $importer->apply( $plan );
        $applied = isset( $result['applied'] ) ? $result['applied'] : array();

        SiteProfileGuard::log( 'import', 'ok', 'cli, ' . count( $applied ) . ' module(s) applied' );

        \WP_CLI::success( sprintf(
            'Applied: %s. Backup saved — run `wp pys profile restore` to roll back.',
            $applied ? implode( ', ', $applied ) : '(none)'
        ) );
    }

    /**
     * Roll back to a snapshot taken before an earlier import.
     *
     * ## OPTIONS
     *
     * [--id=<id>]
     * : Snapshot id to restore (see --list). Defaults to the most recent snapshot.
     *
     * [--list]
     * : List the available snapshots and exit without restoring.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp pys profile restore --list
     *     wp pys profile restore --yes
     *     wp pys profile restore --id=bk_abc123 --yes
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     */
    public function restore( $args, $assoc_args ): void {

        $backups = SiteProfileImporter::getBackups();
        if ( empty( $backups ) ) {
            \WP_CLI::error( 'There is no import backup to restore.' );
        }

        // --list: print the available snapshots and exit.
        if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'list', false ) ) {
            $rows = array();
            foreach ( $backups as $b ) {
                $rows[] = array(
                    'id'         => $b['id'],
                    'created_at' => $b['created_at'],
                    'source'     => $b['source'],
                    'modules'    => implode( ',', (array) $b['modules'] ),
                );
            }
            \WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'created_at', 'source', 'modules' ) );
            return;
        }

        $id = \WP_CLI\Utils\get_flag_value( $assoc_args, 'id', null );

        \WP_CLI::confirm( 'Restore the selected settings snapshot?', $assoc_args );

        if ( ! SiteProfileImporter::restore( $id ) ) {
            \WP_CLI::error( 'The backup could not be restored (unknown id?).' );
        }

        SiteProfileGuard::log( 'restore', 'ok', 'cli, ' . ( $id ? (string) $id : 'latest' ) );

        \WP_CLI::success( 'Previous settings restored.' );
    }

    /**
     * Show the Site Profile audit log (who exported/imported/restored, and when).
     *
     * ## OPTIONS
     *
     * [--limit=<n>]
     * : How many recent entries to show. Default: 20.
     *
     * [--format=<format>]
     * : Output format. One of: table, json, csv, yaml. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp pys profile log
     *     wp pys profile log --limit=50 --format=json
     *
     * @when after_wp_load
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     */
    public function log( $args, $assoc_args ): void {

        $limit  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 20 );
        $format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

        $entries = SiteProfileGuard::getLog( $limit );
        if ( empty( $entries ) ) {
            \WP_CLI::success( 'The audit log is empty.' );
            return;
        }

        $rows = array();
        foreach ( $entries as $e ) {
            $rows[] = array(
                'ts'     => $e['ts'] ?? '',
                'login'  => $e['login'] ?? '',
                'ip'     => $e['ip'] ?? '',
                'action' => $e['action'] ?? '',
                'status' => $e['status'] ?? '',
                'note'   => $e['note'] ?? '',
            );
        }

        \WP_CLI\Utils\format_items( $format, $rows, array( 'ts', 'login', 'ip', 'action', 'status', 'note' ) );
    }

    /**
     * Flatten a diff value to a short, single-line string for the table.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function scalarize( $value ): string {
        if ( is_array( $value ) || is_object( $value ) ) {
            $value = wp_json_encode( $value );
        } elseif ( is_bool( $value ) ) {
            $value = $value ? 'true' : 'false';
        } else {
            $value = (string) $value;
        }
        if ( strlen( $value ) > 60 ) {
            $value = substr( $value, 0, 57 ) . '...';
        }
        return ( $value === '' ) ? '(empty)' : $value;
    }
}
