<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Imports a Site Profile: validates the file, computes a dry-run diff, snapshots
 * the current settings for a one-click restore, and applies the changes.
 *
 * Per-ID modules are merged with a single generic helper (mergeParallelArrays)
 * that keeps the index-aligned parallel arrays (id / token / per-ID data) in
 * lockstep. Writes go through setOption()+updateOptions([]) so the array
 * sanitizers (which drop empties and reindex) cannot break that alignment.
 */
class SiteProfileImporter {

    const BACKUP_OPTION = 'pys_profile_backup';

    /** @var int How many import snapshots to keep (newest first). */
    const MAX_BACKUPS = 5;

    /** @var string Raw JSON string as uploaded. */
    private $raw;

    /** @var array Decoded profile (associative). */
    private $profile;

    /**
     * Private constructor — use fromString()/fromArray().
     *
     * @param string $raw     Raw JSON string.
     * @param array  $profile Decoded associative profile.
     */
    private function __construct( $raw, array $profile ) {
        $this->raw     = $raw;
        $this->profile = $profile;
    }

    /**
     * Build an importer from a raw JSON string.
     *
     * @param string $raw
     *
     * @return SiteProfileImporter|\WP_Error
     */
    public static function fromString( $raw ) {
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return new \WP_Error( 'pys_profile_invalid_json', 'The file is not valid JSON.' );
        }
        return new self( $raw, $decoded );
    }

    /**
     * Build an importer from an already-decoded profile array.
     *
     * @param array $profile
     *
     * @return SiteProfileImporter
     */
    public static function fromArray( array $profile ): self {
        return new self( wp_json_encode( $profile ), $profile );
    }

    /**
     * The decoded profile array.
     *
     * @return array
     */
    public function getProfile(): array {
        return $this->profile;
    }

    /**
     * A read-only summary for the import preview (source, edition, version...).
     *
     * @return array
     */
    public function getSummary(): array {
        return array(
            'export_version'   => isset( $this->profile['pys_export_version'] ) ? $this->profile['pys_export_version'] : '',
            'plugin_edition'   => isset( $this->profile['plugin_edition'] ) ? $this->profile['plugin_edition'] : '',
            'plugin_version'   => isset( $this->profile['plugin_version'] ) ? $this->profile['plugin_version'] : '',
            'generated_at'     => isset( $this->profile['generated_at'] ) ? $this->profile['generated_at'] : '',
            'source_site_url'  => isset( $this->profile['source_site_url'] ) ? $this->profile['source_site_url'] : '',
            'installed_addons' => isset( $this->profile['installed_addons'] ) ? (array) $this->profile['installed_addons'] : array(),
            'modules'          => isset( $this->profile['modules'] ) ? array_keys( (array) $this->profile['modules'] ) : array(),
        );
    }

    /**
     * Validate the profile. Errors block the import; warnings do not.
     *
     * @return array{errors:string[],warnings:string[]}
     */
    public function validate(): array {

        $errors   = array();
        $warnings = array();

        if ( empty( $this->profile['modules'] ) || ! is_array( $this->profile['modules'] ) ) {
            $errors[] = 'The file contains no modules block.';
        }

        $file_version    = isset( $this->profile['pys_export_version'] ) ? (string) $this->profile['pys_export_version'] : '';
        $current_major   = (int) SiteProfileConfig::PROFILE_VERSION;
        $file_major      = (int) $file_version;
        if ( $file_version === '' ) {
            $errors[] = 'Missing export version.';
        } elseif ( $file_major !== $current_major ) {
            $errors[] = sprintf( 'Unsupported export version %s (expected %s.x).', $file_version, $current_major );
        }

        $edition = isset( $this->profile['plugin_edition'] ) ? $this->profile['plugin_edition'] : '';
        if ( $edition !== 'pro' && $edition !== 'free' ) {
            $warnings[] = sprintf( 'Unrecognized plugin edition "%s" — importing anyway.', $edition ? $edition : 'unknown' );
        }

        if ( ! $this->verifyChecksum() ) {
            $warnings[] = 'Checksum does not match — the file may have been edited after export.';
        }

        return array( 'errors' => $errors, 'warnings' => $warnings );
    }

    /**
     * Verify the file checksum against the modules block.
     *
     * @return bool
     */
    private function verifyChecksum(): bool {
        if ( empty( $this->profile['meta']['checksum'] ) ) {
            return false;
        }
        $obj = json_decode( $this->raw, false );
        if ( ! isset( $obj->modules ) ) {
            return false;
        }
        $recomputed = hash( 'sha256', wp_json_encode( $obj->modules ) );
        return hash_equals( (string) $this->profile['meta']['checksum'], $recomputed );
    }

    /**
     * Compute resulting field values per module for a plan, without writing.
     *
     * @param array $plan
     *
     * @return array<string, array<string,mixed>> slug => [field => new value]
     */
    public function computeChanges( array $plan ): array {

        $changes = array();
        $modules = isset( $this->profile['modules'] ) ? (array) $this->profile['modules'] : array();

        foreach ( $modules as $slug => $module_data ) {

            $module_data = (array) $module_data;
            $module_plan = isset( $plan[ $slug ] ) ? $plan[ $slug ] : array();
            $instance    = SiteProfileModuleRegistry::resolve( $slug );

            if ( $instance === null ) {
                continue; // module not available on this site — skip silently here (surfaced in UI)
            }

            if ( $slug === 'core' ) {
                $changes[ $slug ] = $this->computeCoreChanges( $module_data, $module_plan );
            } elseif ( SiteProfileModuleRegistry::isPixelModule( $slug ) ) {
                $changes[ $slug ] = $this->computePlatformPixels( $slug, $instance, $module_data, $module_plan );
            } else {
                $changes[ $slug ] = $this->computeFlatChanges( $slug, $module_data, $module_plan );
            }

            if ( ! empty( $changes[ $slug ] ) ) {
                $registered = $instance->getOptionKeys();
                if ( ! empty( $registered ) ) {
                    $changes[ $slug ] = array_intersect_key( $changes[ $slug ], array_flip( $registered ) );
                }
            }

            if ( empty( $changes[ $slug ] ) ) {
                unset( $changes[ $slug ] );
            }
        }

        $remap = isset( $plan['_remap'] ) ? $plan['_remap'] : array();
        if ( ! empty( $remap ) ) {
            $changes = $this->applyRemap( $changes, $remap );
        }

        return $changes;
    }

    /**
     * Apply URL find/replace pairs to the remap-target fields of the changes set.
     *
     * @param array $changes slug => [field => value]
     * @param array $pairs   list of ['from'=>string,'to'=>string]
     *
     * @return array
     */
    private function applyRemap( array $changes, array $pairs ): array {

        $pairs = $this->normalizeRemapPairs( $pairs );
        if ( empty( $pairs ) ) {
            return $changes;
        }

        foreach ( $changes as $slug => $fields ) {
            foreach ( $fields as $field => $value ) {
                if ( ! SiteProfileConfig::isRemapField( $slug, $field ) ) {
                    continue;
                }
                $changes[ $slug ][ $field ] = $this->remapValue( $value, $pairs );
            }
        }

        return $changes;
    }

    /**
     * Recursively replace URL pairs inside a scalar or (parallel) array value.
     *
     * @param mixed $value
     * @param array $pairs
     *
     * @return mixed
     */
    private function remapValue( $value, array $pairs ) {

        if ( is_string( $value ) ) {
            foreach ( $pairs as $pair ) {
                $value = str_replace( $pair['from'], $pair['to'], $value );
            }
            return $value;
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) {
                $value[ $k ] = $this->remapValue( $v, $pairs );
            }
        }

        return $value;
    }

    /**
     * Keep only valid pairs with a non-empty "from".
     *
     * @param array $pairs
     *
     * @return array<int, array{from:string,to:string}>
     */
    private function normalizeRemapPairs( array $pairs ): array {
        $out = array();
        foreach ( $pairs as $pair ) {
            if ( ! is_array( $pair ) ) {
                continue;
            }
            $from = isset( $pair['from'] ) ? (string) $pair['from'] : '';
            $to   = isset( $pair['to'] ) ? (string) $pair['to'] : '';
            if ( $from !== '' && $from !== $to ) {
                $out[] = array( 'from' => $from, 'to' => $to );
            }
        }
        return $out;
    }

    /**
     * Compute the core module's field changes for the given plan.
     *
     * @param array $module_data Exported core module data (values, automatic_events).
     * @param array $module_plan Per-module plan entry.
     *
     * @return array
     */
    private function computeCoreChanges( array $module_data, array $module_plan ): array {

        $action = isset( $module_plan['action'] ) ? $module_plan['action'] : 'skip';
        $out    = array();

        if ( $action === 'overwrite' && isset( $module_data['values'] ) ) {
            foreach ( (array) $module_data['values'] as $key => $value ) {
                if ( SiteProfileConfig::isExcludedKey( 'core', $key ) ) {
                    continue;
                }
                $out[ $key ] = $value;
            }
        }

        if ( ! empty( $module_plan['include_automatic_events'] ) && isset( $module_data['automatic_events'] ) ) {
            foreach ( (array) $module_data['automatic_events'] as $key => $value ) {
                $out[ $key ] = $value;
            }
        }

        return $out;
    }

    /**
     * Compute a flat (non-pixel) module's field changes for the given plan.
     *
     * @param string $slug        Module slug.
     * @param array  $module_data Exported module data (values).
     * @param array  $module_plan Per-module plan entry.
     *
     * @return array
     */
    private function computeFlatChanges( $slug, array $module_data, array $module_plan ): array {

        $action = isset( $module_plan['action'] ) ? $module_plan['action'] : 'skip';
        if ( $action !== 'overwrite' || ! isset( $module_data['values'] ) ) {
            return array();
        }

        $include_token = ! empty( $module_plan['include_token'] );
        $out           = array();

        foreach ( (array) $module_data['values'] as $key => $value ) {
            if ( SiteProfileConfig::isExcludedKey( $slug, $key ) ) {
                continue;
            }
            if ( SiteProfileConfig::isSensitiveKey( $key ) && ! $include_token ) {
                continue;
            }
            $out[ $key ] = $value;
        }

        return $out;
    }

    /**
     * Pixel import for a platform: overrides the main pixel and core settings.
     *
     * @param string   $slug
     * @param Settings $instance
     * @param array    $module_data
     * @param array    $module_plan
     *
     * @return array field => new value
     */
    private function computePlatformPixels( $slug, $instance, array $module_data, array $module_plan ): array {

        $schema = SiteProfileModuleRegistry::getPixelSchema( $slug );

        // 1. Normalize the incoming main pixel.
        $incoming = array();

        $pixels = isset( $module_data['pixels'] ) ? (array) $module_data['pixels'] : array();
        if ( ! empty( $pixels ) ) {
            $incoming['main'] = $this->normalizeMainIncoming( (array) reset( $pixels ) );
        }

        // 2. Process per-pixel plan (main target only).
        $plan_pixels = isset( $module_plan['pixels'] ) ? (array) $module_plan['pixels'] : array();
        $main_out    = array();

        foreach ( $incoming as $ref => $norm ) {

            $pixel_plan    = isset( $plan_pixels[ $ref ] ) ? (array) $plan_pixels[ $ref ] : array();
            $action        = isset( $pixel_plan['action'] ) ? $pixel_plan['action'] : 'skip';
            $include_token = ! empty( $pixel_plan['include_token'] );

            if ( $action === 'override' ) {
                $target = isset( $pixel_plan['target'] ) ? (string) $pixel_plan['target'] : 'main';
                if ( $target === 'main' ) {
                    // Last override of the main target wins.
                    $main_out = $this->normToMain( $norm, $slug, $schema, $include_token, $instance );
                }
            }
        }

        $result = $main_out;

        // 3. core_settings overwrite (independent of pixel actions).
        $settings_action = isset( $module_plan['action'] ) ? $module_plan['action'] : 'skip';
        if ( $settings_action === 'overwrite' && isset( $module_data['core_settings'] ) ) {
            $token_opt_in = false;
            foreach ( $plan_pixels as $pp ) {
                if ( ! empty( $pp['include_token'] ) ) {
                    $token_opt_in = true;
                    break;
                }
            }
            foreach ( (array) $module_data['core_settings'] as $key => $value ) {
                if ( SiteProfileConfig::isExcludedKey( $slug, $key ) ) {
                    continue;
                }
                if ( SiteProfileConfig::isSensitiveKey( $key ) && ! $token_opt_in ) {
                    continue;
                }
                $result[ $key ] = $value;
            }
        }

        return $result;
    }

    /**
     * Normalize the file's main pixel into the shared pixel shape.
     *
     * @param array $incoming Exported main pixel: id, per_id, tokens.
     *
     * @return array
     */
    private function normalizeMainIncoming( array $incoming ): array {

        $per_id = (array) ( isset( $incoming['per_id'] ) ? $incoming['per_id'] : array() );

        $blob = array();
        if ( isset( $per_id['main_pixel'] ) ) {
            if ( is_string( $per_id['main_pixel'] ) && trim( $per_id['main_pixel'] ) !== '' ) {
                $decoded = json_decode( $per_id['main_pixel'], true );
                if ( is_array( $decoded ) ) {
                    $blob = $decoded;
                }
            } elseif ( is_array( $per_id['main_pixel'] ) ) {
                $blob = $per_id['main_pixel'];
            }
        }

        $tokens = (array) ( isset( $incoming['tokens'] ) ? $incoming['tokens'] : array() );

        $extra = array();
        foreach ( $per_id as $k => $v ) {
            if ( $k === 'main_pixel' || $v === null ) {
                continue;
            }
            $extra[ $k ] = $v;
        }

        return array(
            'id'           => isset( $incoming['id'] ) ? (string) $incoming['id'] : '',
            'blob'         => $blob,
            'tokens'       => $tokens,
            'token'        => isset( $tokens['server_access_api_token'] ) ? (string) $tokens['server_access_api_token'] : '',
            'per_id_extra' => $extra,
            'enabled'      => true,
        );
    }

    /**
     * Render a normalized pixel into the platform's main-pixel option arrays.
     *
     * @param array    $norm
     * @param string   $slug
     * @param array    $schema
     * @param bool     $include_token
     * @param Settings $instance
     *
     * @return array field => single-element array
     */
    private function normToMain( array $norm, $slug, array $schema, $include_token, $instance ): array {

        $out = array();
        $out[ $schema['id_field'] ] = array( $norm['id'] );

        $effective = array();
        foreach ( $schema['token_fields'] as $tf ) {
            if ( $include_token && isset( $norm['tokens'][ $tf ] ) ) {
                $effective[ $tf ] = $norm['tokens'][ $tf ];
            } else {
                $existing         = (array) $instance->getOption( $tf );
                $effective[ $tf ] = isset( $existing[0] ) ? $existing[0] : '';
            }
        }

        if ( in_array( 'main_pixel', $schema['per_id_fields'], true ) ) {
            $blob              = is_array( $norm['blob'] ) ? $norm['blob'] : array();
            $blob['pixel_id']  = $norm['id'];
            $blob['is_enable'] = true;
            foreach ( array( 'server_container_url', 'transport_url' ) as $k ) {
                if ( isset( $norm['per_id_extra'][ $k ] ) && $norm['per_id_extra'][ $k ] !== '' ) {
                    $blob[ $k ] = $norm['per_id_extra'][ $k ];
                }
            }
            $out['main_pixel'] = array( wp_json_encode( $blob ) );
        }

        // Remaining per-ID fields (server_container_url, transport_url).
        foreach ( $schema['per_id_fields'] as $field ) {
            if ( $field === 'main_pixel' ) {
                continue;
            }
            $out[ $field ] = array( isset( $norm['per_id_extra'][ $field ] ) ? $norm['per_id_extra'][ $field ] : '' );
        }

        // Credential arrays.
        foreach ( $schema['token_fields'] as $tf ) {
            $out[ $tf ] = array( isset( $effective[ $tf ] ) ? $effective[ $tf ] : '' );
        }

        return $out;
    }

    /**
     * Generic index-aligned merge for parallel arrays.
     *
     * @param array    $existing     [field => array] current parallel arrays.
     * @param array    $incoming     [field => scalar] one incoming entry's values.
     * @param string   $mode         'append'|'replace'|'skip'.
     * @param int|null $target_index Required for 'replace'.
     *
     * @return array New [field => array] set.
     */
    public static function mergeParallelArrays( array $existing, array $incoming, $mode, $target_index = null ): array {

        if ( $mode === 'skip' ) {
            return $existing;
        }

        $fields = array_unique( array_merge( array_keys( $existing ), array_keys( $incoming ) ) );

        // Normalize every field to a 0-indexed array.
        foreach ( $fields as $field ) {
            $existing[ $field ] = isset( $existing[ $field ] ) ? array_values( (array) $existing[ $field ] ) : array();
        }

        if ( $mode === 'append' ) {

            // Align to the longest array, then append one entry to every field.
            $len = 0;
            foreach ( $existing as $arr ) {
                $len = max( $len, count( $arr ) );
            }
            foreach ( $fields as $field ) {
                while ( count( $existing[ $field ] ) < $len ) {
                    $existing[ $field ][] = '';
                }
                $existing[ $field ][] = array_key_exists( $field, $incoming ) ? $incoming[ $field ] : '';
            }

        } elseif ( $mode === 'replace' && $target_index !== null ) {

            foreach ( $incoming as $field => $value ) {
                if ( ! isset( $existing[ $field ] ) ) {
                    $existing[ $field ] = array();
                }
                while ( count( $existing[ $field ] ) <= $target_index ) {
                    $existing[ $field ][] = '';
                }
                $existing[ $field ][ $target_index ] = $value;
            }
        }

        return $existing;
    }

    /**
     * Build a field-level diff for the given plan.
     *
     * @param array $plan
     *
     * @return array<int, array> rows: module, field, old, new, sensitive
     */
    public function buildDiff( array $plan ): array {

        $changes = $this->computeChanges( $plan );
        $rows    = array();

        foreach ( $changes as $slug => $fields ) {

            $instance = SiteProfileModuleRegistry::resolve( $slug );
            if ( $instance === null ) {
                continue;
            }

            foreach ( $fields as $field => $new_value ) {
                $old_value = $instance->getOption( $field );
                if ( self::valuesEqual( $old_value, $new_value ) ) {
                    continue;
                }
                $rows[] = array(
                    'module'    => $slug,
                    'field'     => $field,
                    'old'       => $old_value,
                    'new'       => $new_value,
                    'sensitive' => SiteProfileConfig::isSensitiveKey( $field ),
                );
            }
        }

        return $rows;
    }

    /**
     * Compare two values by their JSON encoding (deep, type-tolerant).
     *
     * @param mixed $a First value.
     * @param mixed $b Second value.
     *
     * @return bool
     */
    private static function valuesEqual( $a, $b ): bool {
        return wp_json_encode( $a ) === wp_json_encode( $b );
    }

    /**
     * Snapshot the current storage rows for the given module slugs and push it onto
     * the backup stack (newest first, capped at MAX_BACKUPS).
     *
     * @param string[] $slugs
     *
     * @return void
     */
    public function backup( array $slugs ): void {

        global $wpdb;
        $table = Settings::storage_table();

        $rows   = array();
        $absent = array();

        foreach ( $slugs as $slug ) {
            $option_name = 'pys_' . $slug;
            $value       = $wpdb->get_var(
                $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1", $option_name )
            );
            if ( $value === null ) {
                $absent[] = $option_name;
            } else {
                $rows[ $option_name ] = $value;
            }
        }

        $snapshots = self::readSnapshots();
        array_unshift( $snapshots, array(
            'id'         => uniqid( 'bk_' ),
            'created_at' => gmdate( 'c' ),
            'source'     => isset( $this->profile['source_site_url'] ) ? $this->profile['source_site_url'] : '',
            'rows'       => $rows,
            'absent'     => $absent,
        ) );

        // Keep only the most recent MAX_BACKUPS snapshots.
        $snapshots = array_slice( $snapshots, 0, self::MAX_BACKUPS );

        self::storeSnapshots( $snapshots );
    }

    /**
     * Read the backup stack (newest first), migrating a legacy single-slot snapshot.
     *
     * @return array<int, array>
     */
    private static function readSnapshots(): array {

        global $wpdb;
        $table = Settings::storage_table();
        $value = $wpdb->get_var(
            $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1", self::BACKUP_OPTION )
        );
        if ( $value === null ) {
            return array();
        }

        // Self-written data, but deserialize without allowing any object types.
        $data = is_serialized( $value )
            ? unserialize( (string) $value, array( 'allowed_classes' => false ) )
            : $value;

        if ( ! is_array( $data ) ) {
            return array();
        }

        // New shape: { snapshots: [ ... ] }.
        if ( isset( $data['snapshots'] ) && is_array( $data['snapshots'] ) ) {
            return array_values( $data['snapshots'] );
        }

        // Legacy single-slot shape: { created_at, source, rows, absent }.
        if ( isset( $data['rows'] ) || isset( $data['absent'] ) ) {
            if ( ! isset( $data['id'] ) ) {
                $data['id'] = uniqid( 'bk_' );
            }
            return array( $data );
        }

        return array();
    }

    /**
     * Persist the backup stack into the plugin's own options table.
     *
     * @param array<int, array> $snapshots
     *
     * @return void
     */
    private static function storeSnapshots( array $snapshots ): void {

        global $wpdb;
        $table = Settings::storage_table();
        $value = maybe_serialize( array( 'snapshots' => array_values( $snapshots ) ) );

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE option_name = %s", self::BACKUP_OPTION )
        );

        if ( $exists ) {
            $wpdb->update( $table, array( 'option_value' => $value ), array( 'option_name' => self::BACKUP_OPTION ), array( '%s' ), array( '%s' ) );
        } else {
            $wpdb->insert( $table, array( 'option_name' => self::BACKUP_OPTION, 'option_value' => $value, 'migrated' => 1 ), array( '%s', '%s', '%d' ) );
        }

        // Drop any legacy copy left in wp_options by earlier plugin versions.
        delete_option( self::BACKUP_OPTION );
    }

    /**
     * Apply the plan: snapshot first, then write each module's changes.
     *
     * @param array $plan
     *
     * @return array{applied:string[]}
     */
    public function apply( array $plan ): array {

        $changes = $this->computeChanges( $plan );
        $slugs   = array_keys( $changes );

        $this->backup( $slugs );

        $applied = array();
        foreach ( $changes as $slug => $fields ) {
            $instance = SiteProfileModuleRegistry::resolve( $slug );
            if ( $instance === null ) {
                continue;
            }
            $this->writeModuleValues( $instance, $fields );
            $applied[] = $slug;
        }

        wp_cache_flush();

        return array( 'applied' => $applied );
    }

    /**
     * Write field values to a module, bypassing the sanitizers.
     *
     * @param Settings $instance
     * @param array    $fields
     *
     * @return void
     */
    private function writeModuleValues( $instance, array $fields ): void {
        $instance->reloadOptions();
        foreach ( $fields as $key => $value ) {
            $current = $instance->getOption( $key );
            if ( is_scalar( $current ) && ( is_array( $value ) || is_object( $value ) ) ) {
                continue;
            }
            $instance->setOption( $key, $value );
        }
        $instance->updateOptions( array() );
    }

    /**
     * Restore a snapshot from the backup stack.
     *
     * @param string|null $id Snapshot id; null restores the most recent snapshot.
     *
     * @return bool True on success, false when there is nothing to restore.
     */
    public static function restore( $id = null ): bool {

        $snapshots = self::readSnapshots();
        if ( empty( $snapshots ) ) {
            return false;
        }

        $target = null;
        if ( $id === null || $id === '' ) {
            $target = $snapshots[0];
        } else {
            $id = sanitize_key( (string) $id );
            foreach ( $snapshots as $snapshot ) {
                if ( isset( $snapshot['id'] ) && sanitize_key( (string) $snapshot['id'] ) === $id ) {
                    $target = $snapshot;
                    break;
                }
            }
        }

        if ( ! is_array( $target ) ) {
            return false;
        }

        global $wpdb;
        $table = Settings::storage_table();

        foreach ( (array) ( $target['rows'] ?? array() ) as $option_name => $option_value ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE option_name = %s", $option_name )
            );
            if ( $exists ) {
                $wpdb->update( $table, array( 'option_value' => $option_value ), array( 'option_name' => $option_name ), array( '%s' ), array( '%s' ) );
            } else {
                $wpdb->insert( $table, array( 'option_name' => $option_name, 'option_value' => $option_value, 'migrated' => 1 ), array( '%s', '%s', '%d' ) );
            }
        }

        // Rows that did not exist before that import are removed again.
        foreach ( (array) ( $target['absent'] ?? array() ) as $option_name ) {
            $wpdb->delete( $table, array( 'option_name' => $option_name ), array( '%s' ) );
        }

        // Invalidate cached option table flag / loaded values.
        wp_cache_flush();

        return true;
    }

    /**
     * The most recent backup snapshot (full: rows + absent), or null.
     *
     * @return array|null
     */
    public static function getBackupInfo(): ?array {
        $snapshots = self::readSnapshots();
        return ! empty( $snapshots ) ? $snapshots[0] : null;
    }

    /**
     * The backup stack as light metadata (no row values), newest first.
     *
     * @return array<int, array{id:string,created_at:string,source:string,modules:string[]}>
     */
    public static function getBackups(): array {

        $out = array();

        foreach ( self::readSnapshots() as $snapshot ) {

            $slugs = array();
            foreach ( array_keys( (array) ( $snapshot['rows'] ?? array() ) ) as $option_name ) {
                $slugs[] = preg_replace( '/^pys_/', '', (string) $option_name );
            }
            foreach ( (array) ( $snapshot['absent'] ?? array() ) as $option_name ) {
                $slugs[] = preg_replace( '/^pys_/', '', (string) $option_name );
            }

            $out[] = array(
                'id'         => isset( $snapshot['id'] ) ? (string) $snapshot['id'] : '',
                'created_at' => isset( $snapshot['created_at'] ) ? (string) $snapshot['created_at'] : '',
                'source'     => isset( $snapshot['source'] ) ? (string) $snapshot['source'] : '',
                'modules'    => array_values( array_unique( $slugs ) ),
            );
        }

        return $out;
    }

    /**
     * Build the auto "replicate-all" plan: overwrite settings + override each main pixel.
     *
     * @param array         $profile
     * @param bool          $include_tokens
     * @param string[]|null $modules_filter Restrict to these slugs; null = all.
     * @param bool          $include_scripts Whether to plan head_footer scripts; default false.
     *
     * @return array
     */
    public static function buildAutoPlan( array $profile, $include_tokens, $modules_filter = null, $include_scripts = false ): array {

        $include_tokens  = (bool) $include_tokens;
        $include_scripts = (bool) $include_scripts;

        if ( is_array( $modules_filter ) ) {
            $modules_filter = array_map(
                function ( $s ) { return sanitize_key( trim( (string) $s ) ); },
                $modules_filter
            );
        } else {
            $modules_filter = null;
        }

        $plan         = array();
        $file_modules = isset( $profile['modules'] ) ? (array) $profile['modules'] : array();

        foreach ( array_keys( $file_modules ) as $slug ) {

            $slug = (string) $slug;
            if ( $modules_filter !== null && ! in_array( $slug, $modules_filter, true ) ) {
                continue;
            }
            // Skip modules that are not available on this target.
            if ( SiteProfileModuleRegistry::resolve( $slug ) === null ) {
                continue;
            }

            if ( $slug === 'core' ) {
                $plan['core'] = array(
                    'action'                   => 'overwrite',
                    'include_automatic_events' => true,
                );
            } elseif ( SiteProfileModuleRegistry::isPixelModule( $slug ) ) {
                $plan[ $slug ] = array(
                    'action' => 'overwrite', // core_settings
                    'pixels' => array(
                        'main' => array(
                            'action'        => 'override',
                            'target'        => 'main',
                            'include_token' => $include_tokens,
                        ),
                    ),
                );
            } else {
                if ( $slug === 'head_footer' && ! $include_scripts ) {
                    continue;
                }
                $plan[ $slug ] = array(
                    'action'        => 'overwrite',
                    'include_token' => $include_tokens,
                );
            }
        }

        return $plan;
    }

    /**
     * Sanitize a caller-supplied explicit plan into the shape computeChanges() expects.
     *
     * @param array $raw
     *
     * @return array
     */
    public static function sanitizePlan( array $raw ): array {

        $plan = array();

        foreach ( $raw as $slug => $entry ) {

            if ( $slug === '_remap' ) {
                continue; // remap is handled separately
            }
            $slug = sanitize_key( $slug );
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $out = array();

            if ( isset( $entry['action'] ) ) {
                $out['action'] = ( $entry['action'] === 'overwrite' ) ? 'overwrite' : 'skip';
            }
            if ( ! empty( $entry['include_token'] ) ) {
                $out['include_token'] = true;
            }
            if ( ! empty( $entry['include_automatic_events'] ) ) {
                $out['include_automatic_events'] = true;
            }

            if ( isset( $entry['pixels'] ) && is_array( $entry['pixels'] ) ) {
                foreach ( $entry['pixels'] as $ref => $prow ) {
                    $ref = self::sanitizePixelRef( $ref );
                    if ( $ref === '' || ! is_array( $prow ) ) {
                        continue;
                    }
                    $action = ( isset( $prow['action'] ) && in_array( $prow['action'], array( 'add', 'override' ), true ) )
                        ? $prow['action'] : 'skip';
                    $pentry = array( 'action' => $action );
                    if ( $action === 'override' ) {
                        $target           = isset( $prow['target'] ) ? self::sanitizePixelRef( $prow['target'] ) : 'main';
                        $pentry['target'] = ( $target === '' ) ? 'main' : $target;
                    }
                    if ( ! empty( $prow['include_token'] ) ) {
                        $pentry['include_token'] = true;
                    }
                    $out['pixels'][ $ref ] = $pentry;
                }
            }

            if ( ! empty( $out ) ) {
                $plan[ $slug ] = $out;
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
    public static function sanitizePixelRef( $ref ): string {
        $ref = is_scalar( $ref ) ? (string) $ref : '';
        if ( $ref === 'main' ) {
            return 'main';
        }
        if ( preg_match( '/^sp_(\d+)$/', $ref, $m ) ) {
            return 'sp_' . (int) $m[1];
        }
        return '';
    }
}
