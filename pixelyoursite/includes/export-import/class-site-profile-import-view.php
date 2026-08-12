<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Builds the render model for the Import checklist: cross-references what the
 * uploaded profile contains against what this site actually supports, so the UI
 * can show per-module action selectors, "Replace with existing ID" dropdowns,
 * and disabled rows with a reason when a module is not available here.
 */
class SiteProfileImportView {

    /**
     * Build the Import checklist render model from an uploaded profile.
     *
     * @param array $profile Decoded profile.
     *
     * @return array{summary:array,modules:array<int,array>}
     */
    public static function build( array $profile ): array {

        $definitions = SiteProfileModuleRegistry::getDefinitions();
        $file_modules = isset( $profile['modules'] ) ? (array) $profile['modules'] : array();

        $modules = array();

        foreach ( $file_modules as $slug => $data ) {

            $data       = (array) $data;
            $definition = isset( $definitions[ $slug ] ) ? $definitions[ $slug ] : array();
            $label      = isset( $definition['label'] ) ? $definition['label'] : $slug;
            $is_addon   = ! empty( $definition['is_addon'] );
            $instance   = SiteProfileModuleRegistry::resolve( $slug );
            $available  = ( $instance !== null );

            $entry = array(
                'slug'      => $slug,
                'label'     => $label,
                'available' => $available,
                'reason'    => $available ? null : self::unavailableReason( $slug, $label, $is_addon ),
            );

            if ( $slug === 'core' ) {
                $entry['type']                 = 'core';
                $entry['has_values']           = ! empty( $data['values'] );
                $entry['has_automatic_events'] = ! empty( $data['automatic_events'] );
            } elseif ( SiteProfileModuleRegistry::isPixelModule( $slug ) ) {
                $entry['type']              = 'pixel';
                $entry['has_core_settings'] = ! empty( $data['core_settings'] );

                $entry['incoming_pixels']   = self::buildIncoming( $slug, $data );
                // Targets on THIS site any incoming pixel may override.
                $entry['override_targets']  = $available ? self::buildOverrideTargets( $slug, $instance ) : array();
            } else {
                $entry['type']               = 'flat';
                $entry['has_values']         = ! empty( $data['values'] );
                $entry['has_token_in_file']  = self::flatHasTokenInFile( $data );
            }

            $modules[] = $entry;
        }

        return array(
            'summary' => array(
	            'export_version'   => $profile[ 'pys_export_version' ] ?? '',
	            'plugin_edition'   => $profile[ 'plugin_edition' ] ?? '',
	            'plugin_version'   => $profile[ 'plugin_version' ] ?? '',
	            'generated_at'     => $profile[ 'generated_at' ] ?? '',
	            'source_site_url'  => $profile[ 'source_site_url' ] ?? '',
	            'installed_addons' => isset( $profile['installed_addons'] ) ? (array) $profile['installed_addons'] : array(),
            ),
            'modules' => $modules,
        );
    }

    /**
     * Human-readable reason a module in the file cannot be imported here.
     *
     * @param string $slug
     * @param string $label
     * @param bool   $is_addon
     *
     * @return string
     */
    private static function unavailableReason( $slug, $label, $is_addon ): string {
        if ( $is_addon ) {
            return sprintf( '%s add-on is not active', $label );
        }
        return 'Not available on this site';
    }

    /**
     * Incoming pixel list from the file (the main pixel).
     *
     * @param string $slug Platform module slug.
     * @param array  $data Platform module block from the file.
     *
     * @return array<int, array{ref:string,source:string,source_label:string,id_masked:string,has_token:bool,enabled:bool}>
     */
    private static function buildIncoming( $slug, array $data ): array {

        $out = array();

        // Main pixel (index 0).
        $pixels = isset( $data['pixels'] ) ? (array) $data['pixels'] : array();
        if ( ! empty( $pixels ) ) {
            $pixel = (array) reset( $pixels );
            $id    = isset( $pixel['id'] ) ? (string) $pixel['id'] : '';
            if ( trim( $id ) !== '' ) {
                $out[] = array(
                    'ref'          => 'main',
                    'source'       => 'main',
                    'source_label' => 'Main pixel',
                    'id_masked'    => SiteProfileInventory::maskId( $id ),
                    'has_token'    => ! empty( $pixel['tokens'] ),
                    'enabled'      => true,
                );
            }
        }

        return $out;
    }

    /**
     * Existing override targets on this site for an incoming pixel (the main pixel).
     *
     * @param string   $slug     Platform module slug.
     * @param Settings $instance Platform settings instance.
     *
     * @return array<int, array{ref:string,label:string,id_masked:string}>
     */
    private static function buildOverrideTargets( $slug, $instance ): array {

        $out    = array();
        $schema = SiteProfileModuleRegistry::getPixelSchema( $slug );
        $ids    = (array) $instance->getOption( $schema['id_field'] );
        $main_id = isset( $ids[0] ) ? $ids[0] : '';

        $out[] = array(
            'ref'       => 'main',
            'label'     => 'Main pixel',
            'id_masked' => ( is_scalar( $main_id ) && trim( (string) $main_id ) !== '' )
                ? SiteProfileInventory::maskId( (string) $main_id )
                : '(empty)',
        );

        return $out;
    }

    /**
     * Whether the file carries a credential for a flat module.
     *
     * @param array $data
     *
     * @return bool
     */
    private static function flatHasTokenInFile( array $data ): bool {
        $values = isset( $data['values'] ) ? (array) $data['values'] : array();
        foreach ( SiteProfileConfig::SENSITIVE_KEYS as $key ) {
            if ( isset( $values[ $key ] ) ) {
                $v = $values[ $key ];
                if ( is_array( $v ) ) {
                    $v = implode( '', array_map( 'strval', $v ) );
                }
                if ( trim( (string) $v ) !== '' ) {
                    return true;
                }
            }
        }
        return false;
    }
}
