<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Builds a Site Profile export payload from the current site's settings.
 *
 * Iterates the module registry, reads each module's option values, strips
 * hard-excluded keys (license/bookkeeping), splits the core module's Automatic
 * Events toggles into their own block, and decomposes the five per-ID modules
 * into index-aligned pixel/account entries. Credentials are never included
 * unless explicitly opted in through the selection.
 */
class SiteProfileExporter {

    private $selection;

    /**
     * Build an exporter for the given selection.
     *
     * @param array $selection Optional selection overrides.
     */
    public function __construct( array $selection = array() ) {
        $this->selection = $selection;
    }

    /**
     * Build the full export payload as a PHP array.
     *
     * @return array
     */
    public function build(): array {

        $modules = array();

        foreach ( SiteProfileModuleRegistry::getAvailableModules() as $module ) {

            $slug = $module['slug'];

            $selected = $this->isModuleSelected( $slug );
            if ( SiteProfileModuleRegistry::isPixelModule( $slug ) ) {
                $selected = $selected || $this->anyPixelSelected( $slug );
            }
            if ( ! $selected ) {
                continue;
            }

            /** @var Settings $instance */
            $instance = $module['instance'];

            if ( $slug === 'core' ) {
                $modules['core'] = $this->buildCoreModule( $instance );
            } elseif ( SiteProfileModuleRegistry::isPixelModule( $slug ) ) {
                $modules[ $slug ] = $this->buildPixelModule( $slug, $instance );
            } else {
                $modules[ $slug ] = $this->buildFlatModule( $slug, $instance );
            }
        }

        $payload = array(
            'pys_export_version' => SiteProfileConfig::PROFILE_VERSION,
            'plugin_edition'     => $this->detectEdition(),
            'plugin_version'     => defined( 'PYS_FREE_VERSION' ) ? PYS_FREE_VERSION : '',
            'generated_at'       => gmdate( 'c' ),
            'source_site_url'    => site_url(),
            'installed_addons'   => $this->getInstalledAddons( $modules ),
            'modules'            => $modules,
        );

        $payload['meta'] = array(
            'checksum' => hash( 'sha256', wp_json_encode( $modules ) ),
        );

        return $payload;
    }

    /**
     * Build the export payload as a pretty-printed JSON string.
     *
     * @return string
     */
    public function toJson(): string {
        return wp_json_encode( $this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Core module: plain values plus a separate Automatic Events block.
     *
     * @param Settings $instance
     *
     * @return array
     */
    private function buildCoreModule( $instance ): array {

        $values           = array();
        $automatic_events = array();
        $include_ae       = $this->includeAutomaticEvents();

        foreach ( $instance->getOptionKeys() as $key ) {

            if ( SiteProfileConfig::isExcludedKey( 'core', $key ) ) {
                continue;
            }

            $value = $instance->getOption( $key );

            if ( strpos( $key, 'automatic_event' ) === 0 ) {
                if ( $include_ae ) {
                    $automatic_events[ $key ] = $value;
                }
                continue;
            }

            $values[ $key ] = $value;
        }

        return array(
            'values'           => $values,
            'automatic_events' => (object) $automatic_events,
        );
    }

    /**
     * Per-ID module: core settings plus one entry per configured ID.
     *
     * @param string   $slug
     * @param Settings $instance
     *
     * @return array
     */
    private function buildPixelModule( $slug, $instance ): array {

        $schema         = SiteProfileModuleRegistry::getPixelSchema( $slug );
        $id_field       = $schema['id_field'];
        $token_fields   = $schema['token_fields'];
        $per_id_fields  = $schema['per_id_fields'];
        $per_id_pattern = $schema['per_id_pattern'];

        $all_keys = $instance->getOptionKeys();

        // Keys that are decomposed per-ID and must NOT appear in core_settings.
        $reserved = array_merge( array( $id_field ), $token_fields, $per_id_fields );
        foreach ( $all_keys as $key ) {
            if ( $per_id_pattern && preg_match( $per_id_pattern, $key ) ) {
                $reserved[] = $key;
            }
        }

        $core_settings = array();
        if ( $this->isModuleSelected( $slug ) ) {
            foreach ( $all_keys as $key ) {
                if ( in_array( $key, $reserved, true ) || SiteProfileConfig::isExcludedKey( $slug, $key ) ) {
                    continue;
                }

                if ( SiteProfileConfig::isSensitiveKey( $key ) && ! $this->includeToken( $slug, 0 ) ) {
                    continue;
                }
                $core_settings[ $key ] = $instance->getOption( $key );
            }
        }

        // Per-ID field list = declared per_id_fields + any pattern matches.
        $per_id_field_list = $per_id_fields;
        foreach ( $all_keys as $key ) {
            if ( $per_id_pattern && preg_match( $per_id_pattern, $key ) ) {
                $per_id_field_list[] = $key;
            }
        }

        $ids             = (array) $instance->getOption( $id_field );
        $main_id         = $ids[ 0 ] ?? '';
        $index_whitelist = $this->getPixelIndexWhitelist( $slug );
        $main_selected   = ( $index_whitelist === null ) || in_array( 0, $index_whitelist, true );
        $pixels          = array();

        if ( $main_selected && is_scalar( $main_id ) && trim( (string) $main_id ) !== '' ) {

            $per_id = array();
            foreach ( $per_id_field_list as $field ) {
                $field_values     = (array) $instance->getOption( $field );
                $per_id[ $field ] = isset( $field_values[0] ) ? $field_values[0] : null;
            }

            $include_token = $this->includeToken( $slug, 0 );
            $tokens        = null;
            if ( $include_token && ! empty( $token_fields ) ) {
                $tokens = array();
                foreach ( $token_fields as $token_field ) {
                    $token_values           = (array) $instance->getOption( $token_field );
                    $tokens[ $token_field ] = isset( $token_values[0] ) ? $token_values[0] : null;
                }
            }

            $pixels[] = array(
                'index'         => 0,
                'id'            => $main_id,
                'per_id'        => (object) $per_id,
                'include_token' => $include_token,
                'tokens'        => $tokens === null ? null : (object) $tokens,
            );
        }

        return array(
            'core_settings' => (object) $core_settings,
            'pixels'        => $pixels,
        );
    }

    /**
     * Flat module: a single settings block, credential keys dropped unless opted in.
     *
     * @param string   $slug
     * @param Settings $instance
     *
     * @return array
     */
    private function buildFlatModule( $slug, $instance ): array {

        $include_tokens = $this->includeFlatTokens( $slug );
        $values         = array();

        foreach ( $instance->getOptionKeys() as $key ) {

            if ( SiteProfileConfig::isExcludedKey( $slug, $key ) ) {
                continue;
            }

            if ( ! $include_tokens && SiteProfileConfig::isSensitiveKey( $key ) ) {
                continue;
            }

            $values[ $key ] = $instance->getOption( $key );
        }

        return array(
            'values' => (object) $values,
        );
    }

    /**
     * Whether any main pixel index is selected for a given module.
     *
     * @param string $slug
     *
     * @return bool
     */
    private function anyPixelSelected( $slug ): bool {
        return ! empty( $this->selection['pixel_indices'][ $slug ] );
    }

    /**
     * Whether a module is included by the current selection.
     *
     * @param string $slug
     *
     * @return bool
     */
    private function isModuleSelected( $slug ): bool {
        if ( ! isset( $this->selection['modules'] ) || $this->selection['modules'] === null ) {
            return true;
        }
        return in_array( $slug, (array) $this->selection['modules'], true );
    }

    /**
     * Whether the core Automatic Events block should be exported.
     *
     * @return bool
     */
    private function includeAutomaticEvents(): bool {
        return !isset( $this->selection[ 'include_automatic_events' ] )
               || (bool) $this->selection[ 'include_automatic_events' ];
    }

    /**
     * Which pixel indices to include for a per-ID module. Null = all non-empty.
     *
     * @param string $slug
     *
     * @return int[]|null
     */
    private function getPixelIndexWhitelist( $slug ): ?array {
        if ( ! isset( $this->selection['pixel_indices'][ $slug ] ) ) {
            return null;
        }
        $list = $this->selection['pixel_indices'][ $slug ];
        return $list === null ? null : array_map( 'intval', (array) $list );
    }

    /**
     * Whether to include the credential for a given per-ID module index.
     *
     * @param string $slug
     * @param int    $index
     *
     * @return bool
     */
    private function includeToken( $slug, $index ): bool {
        if ( ! isset( $this->selection['tokens'][ $slug ] ) ) {
            return false;
        }
        $spec = $this->selection['tokens'][ $slug ];
        if ( is_array( $spec ) ) {
            return ! empty( $spec[ $index ] );
        }
        return (bool) $spec;
    }

    /**
     * Whether to include credential fields for a flat module.
     *
     * @param string $slug
     *
     * @return bool
     */
    private function includeFlatTokens( $slug ): bool {
        if ( ! isset( $this->selection['tokens'][ $slug ] ) ) {
            return false;
        }
        return (bool) $this->selection['tokens'][ $slug ];
    }

    /**
     * Detect the running edition. Google Ads is a Pro-exclusive module.
     *
     * @return string 'pro'|'free'
     */
    private function detectEdition(): string {
        return function_exists( 'PixelYourSite\\Ads' ) ? 'pro' : 'free';
    }

    /**
     * Add-on slugs whose module block is actually present in this export.
     *
     * Reflects what the file CONTAINS (the "Add-ons in file" summary), not what
     * happens to be active on the source site: a selection that excludes an
     * add-on must not report it as bundled. Add-on slugs come from the registry
     * is_addon flag, so the set stays correct per edition.
     *
     * @param array $modules The module blocks written to the payload.
     *
     * @return string[]
     */
    private function getInstalledAddons( array $modules ): array {
        $addon_slugs = array();
        foreach ( SiteProfileModuleRegistry::getDefinitions() as $slug => $definition ) {
            if ( ! empty( $definition['is_addon'] ) ) {
                $addon_slugs[] = $slug;
            }
        }
        return array_values( array_intersect( $addon_slugs, array_keys( $modules ) ) );
    }
}
