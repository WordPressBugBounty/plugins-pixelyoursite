<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Static configuration for the Site Profile export/import feature.
 *
 * Holds the export-file format version and the list of option keys that must
 * never leave the site (license credentials, transactional bookkeeping).
 * Kept separate from the exporter/importer so both sides share one source of truth.
 */
class SiteProfileConfig {

    /**
     * Export file format version. Bump only on breaking schema changes.
     */
    const PROFILE_VERSION = '1.0';

    /**
     * Keys hard-excluded from export for EVERY module. License credentials live
     * on core and on every add-on module, and must never travel in a profile.
     */
    const EXCLUDED_KEYS_GLOBAL = array(
        'license_key',
        'license_status',
        'license_expires',
        'admin_permissions',
        'mcp_read_only_enabled',
    );

    /**
     * Per-module exact-match keys that are hard-excluded from export.
     * Keyed by module slug.
     */
    const EXCLUDED_KEYS = array(
        'core' => array(
            'woo_last_export_date',
            'edd_last_export_date',
        ),
    );

    /**
     * Regex patterns applied to every module's keys as a safety net,
     * so newly added bookkeeping fields are excluded without a code change.
     */
    const EXCLUDED_KEY_PATTERNS = array(
        '/_last_export_date$/',
    );

    /**
     * Credential-shaped keys that are excluded from a flat module's settings
     * block unless the user explicitly opts in (module-level "include tokens").
     * Per-ID modules handle their credentials via the pixel schema instead.
     */
    const SENSITIVE_KEYS = array(
        'server_access_api_token',
        'wcf_server_access_api_token',
        'gtm_auth',
        'gtm_preview',
    );

    /**
     * Regex safety net for credential-shaped keys, so a newly added token/secret
     * field is excluded by default (opt-in only) without a code change — the
     * denylist above fails safe.
     */
    const SENSITIVE_KEY_PATTERNS = array(
        '/token/i',
        '/secret/i',
        '/api_key/i',
        '/password/i',
        '/_auth$/i',
        '/credential/i',
    );

    /**
     * Option keys that hold URLs and are subject to source→target domain
     * remapping on import (in any module). The whole head_footer module is also
     * remapped (its raw script fields), handled in isRemapField().
     */
    const REMAP_URL_FIELDS = array(
        'server_container_url',
        'transport_url',
    );

    /**
     * Whether a field's value should be run through the import domain/URL remap.
     *
     * @param string $module_slug
     * @param string $option_key
     *
     * @return bool
     */
    public static function isRemapField( $module_slug, $option_key ): bool {
        if ( in_array( $option_key, self::REMAP_URL_FIELDS, true ) ) {
            return true;
        }
        // Head & Footer carries raw scripts that embed the site URL.
        return $module_slug === 'head_footer';
    }

    /**
     * Whether a key holds a credential excluded from export by default (opt-in only).
     *
     * @param string $option_key Option field key.
     *
     * @return bool
     */
    public static function isSensitiveKey( $option_key ): bool {
        if ( in_array( $option_key, self::SENSITIVE_KEYS, true ) ) {
            return true;
        }
        foreach ( self::SENSITIVE_KEY_PATTERNS as $pattern ) {
            if ( preg_match( $pattern, $option_key ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a given option key must be excluded from export.
     *
     * @param string $module_slug Module slug (e.g. 'core', 'facebook').
     * @param string $option_key  Option field key.
     *
     * @return bool
     */
    public static function isExcludedKey( $module_slug, $option_key ): bool {

        if ( in_array( $option_key, self::EXCLUDED_KEYS_GLOBAL, true ) ) {
            return true;
        }

        $excluded_keys = self::EXCLUDED_KEYS[ $module_slug ] ?? array();

        if ( in_array( $option_key, $excluded_keys, true ) ) {
            return true;
        }

        foreach ( self::EXCLUDED_KEY_PATTERNS as $pattern ) {
            if ( preg_match( $pattern, $option_key ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the exact-match excluded keys for a single module.
     *
     * @param string $module_slug
     *
     * @return string[]
     */
    public static function getExcludedKeys( $module_slug ): array {
        return self::EXCLUDED_KEYS[ $module_slug ] ?? array();
    }
}
