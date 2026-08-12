<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Builds a read-only, UI-friendly inventory of what is configured on the site:
 * which modules are available, and for per-ID modules the configured pixel/
 * account IDs (masked) and whether each carries a credential. Drives the export
 * checklist without exposing raw token values on screen.
 */
class SiteProfileInventory {

    /**
     * Build the read-only export inventory of all available modules.
     *
     * @return array<int, array> One entry per available module.
     */
    public static function build(): array {

        $inventory = array();

        foreach ( SiteProfileModuleRegistry::getAvailableModules() as $module ) {

            $slug     = $module['slug'];
            $instance = $module['instance'];

            $entry = array(
                'slug'     => $slug,
                'label'    => $module['label'],
                'group'    => $module['group'],
                'is_addon' => $module['is_addon'],
            );

            if ( $slug === 'core' ) {
                $entry['type'] = 'core';
            } elseif ( SiteProfileModuleRegistry::isPixelModule( $slug ) ) {
                $entry['type'] = 'pixel';
                $entry         = array_merge( $entry, self::buildPixelEntry( $slug, $instance ) );
            } else {
                $entry['type']          = 'flat';
                $entry['has_sensitive'] = self::flatHasSensitive( $instance );
            }

            $inventory[] = $entry;
        }

        return $inventory;
    }

    /**
     * Per-ID module: list configured IDs with masked value and token presence.
     *
     * @param string   $slug
     * @param Settings $instance
     *
     * @return array
     */
    private static function buildPixelEntry( $slug, $instance ): array {

        $schema       = SiteProfileModuleRegistry::getPixelSchema( $slug );
        $token_fields = $schema['token_fields'];

        $ids     = (array) $instance->getOption( $schema['id_field'] );
        $main_id = isset( $ids[0] ) ? $ids[0] : '';
        $pixels  = array();

        if ( is_scalar( $main_id ) && trim( (string) $main_id ) !== '' ) {

            $has_token = false;
            foreach ( $token_fields as $token_field ) {
                $token_values = (array) $instance->getOption( $token_field );
                if ( isset( $token_values[0] ) && trim( (string) $token_values[0] ) !== '' ) {
                    $has_token = true;
                    break;
                }
            }

            $pixels[] = array(
                'index'     => 0,
                'id_masked' => self::maskId( (string) $main_id ),
                'has_token' => $has_token,
            );
        }

        return array(
            'has_token_fields' => ! empty( $token_fields ),
            'pixels'           => $pixels,
        );
    }

    /**
     * Whether a flat module has a non-empty credential value.
     *
     * @param Settings $instance
     *
     * @return bool
     */
    private static function flatHasSensitive( $instance ): bool {
        foreach ( SiteProfileConfig::SENSITIVE_KEYS as $key ) {
            $value = $instance->getOption( $key );
            if ( is_array( $value ) ) {
                $value = implode( '', array_map( 'strval', $value ) );
            }
            if ( trim( (string) $value ) !== '' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mask an ID, keeping only the last four characters visible.
     *
     * @param string $id
     *
     * @return string
     */
    public static function maskId( $id ): string {
        $id  = trim( $id );
        $len = strlen( $id );
        if ( $len <= 4 ) {
            return $id;
        }
        return '••••' . substr( $id, -4 );
    }
}
