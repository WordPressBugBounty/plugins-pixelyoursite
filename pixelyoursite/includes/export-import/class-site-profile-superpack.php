<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Handles SuperPack "additional pixels" for Facebook / GA / Google Ads.
 *
 * These are stored in the SuperPack module as arrays of JSON blobs
 * ({fb,ga,ads}_ext_pixel_id), one blob per extra pixel, each carrying its own
 * enabled flag and (for FB/GA) an embedded API token. We deliberately work with
 * the RAW decoded blob and never round-trip through SuperPack's SPPixelId::toArray(),
 * which is lossy (it drops use_server_api / server_access_api_token / container URLs).
 */
class SiteProfileSuperPack {

    /**
     * Parent pixel-module slug => SuperPack ext option field.
     */
    const PLATFORM_FIELDS = array(
        'facebook'   => 'fb_ext_pixel_id',
        'ga'         => 'ga_ext_pixel_id',
        'google_ads' => 'ads_ext_pixel_id',
    );

    /**
     * Whether the SuperPack module is available on this site.
     *
     * @return bool Whether SuperPack is available on this site.
     */
    public static function available(): bool {
        return SiteProfileModuleRegistry::resolve( 'superpack' ) !== null;
    }

    /**
     * List the SuperPack ext option field names for all platforms.
     *
     * @return string[] The three ext option field names.
     */
    public static function extFields(): array {
        return array_values( self::PLATFORM_FIELDS );
    }

    /**
     * Resolve the SuperPack ext option field name for a platform.
     *
     * @param string $platform Parent pixel-module slug.
     *
     * @return string|null Ext field name for the platform, or null.
     */
    public static function fieldFor( $platform ): ?string {
        return isset( self::PLATFORM_FIELDS[ $platform ] ) ? self::PLATFORM_FIELDS[ $platform ] : null;
    }

    /**
     * Read the configured additional pixels for a platform.
     *
     * @param string $platform
     *
     * @return array<int, array{index:int,blob:array,id:string,enabled:bool,has_token:bool}>
     */
    public static function readExtPixels( $platform ): array {

        $instance = SiteProfileModuleRegistry::resolve( 'superpack' );
        $field    = self::fieldFor( $platform );

        if ( $instance === null || $field === null ) {
            return array();
        }

        $raw = (array) $instance->getOption( $field );
        $out = array();

        foreach ( $raw as $i => $json ) {
            $blob = is_string( $json ) ? json_decode( $json, true ) : $json;
            if ( ! is_array( $blob ) ) {
                continue;
            }
            $id = isset( $blob['pixel_id'] ) ? (string) $blob['pixel_id'] : '';
            if ( trim( $id ) === '' ) {
                continue; // skip empty slots
            }
            $out[] = array(
                'index'     => (int) $i,
                'blob'      => $blob,
                'id'        => $id,
                'enabled'   => isset( $blob['is_enable'] ) ? (bool) $blob['is_enable'] : true,
                'has_token' => self::blobHasToken( $blob ),
            );
        }

        return $out;
    }

    /**
     * Whether the blob carries an embedded credential token.
     *
     * @param array $blob Decoded ext-pixel blob.
     *
     * @return bool
     */
    public static function blobHasToken( array $blob ): bool {
        if ( isset( $blob['extensions']['api_token'] ) && trim( (string) $blob['extensions']['api_token'] ) !== '' ) {
            return true;
        }
        if ( isset( $blob['server_access_api_token'] ) && trim( (string) $blob['server_access_api_token'] ) !== '' ) {
            return true;
        }
        return false;
    }

    /**
     * Return a copy of the blob without any embedded credential.
     *
     * @param array $blob Decoded ext-pixel blob.
     *
     * @return array
     */
    public static function stripToken( array $blob ): array {
        if ( isset( $blob['extensions']['api_token'] ) ) {
            unset( $blob['extensions']['api_token'] );
        }
        if ( isset( $blob['server_access_api_token'] ) ) {
            $blob['server_access_api_token'] = '';
        }
        return $blob;
    }

    /**
     * Copy the token from $source blob into $target blob.
     *
     * @param array $target
     * @param array $source
     *
     * @return array
     */
    public static function carryToken( array $target, array $source ): array {
        if ( isset( $source['extensions']['api_token'] ) && trim( (string) $source['extensions']['api_token'] ) !== '' ) {
            if ( ! isset( $target['extensions'] ) || ! is_array( $target['extensions'] ) ) {
                $target['extensions'] = array();
            }
            $target['extensions']['api_token'] = $source['extensions']['api_token'];
        }
        if ( isset( $source['server_access_api_token'] ) && trim( (string) $source['server_access_api_token'] ) !== '' ) {
            $target['server_access_api_token'] = $source['server_access_api_token'];
        }
        return $target;
    }

    /**
     * Build a SuperPack ext-pixel blob from an imported main-pixel entry.
     *
     * @param string $platform      facebook|ga|google_ads.
     * @param array  $incoming_pixel Exported main pixel: id, per_id, tokens.
     * @param bool   $include_token
     *
     * @return array
     */
    public static function buildBlobFromMainPixel( $platform, array $incoming_pixel, $include_token ): array {

        $blob = array(
            'pixel_id'  => isset( $incoming_pixel['id'] ) ? $incoming_pixel['id'] : '',
            'is_enable' => true,
        );

        $per_id = (array) ( isset( $incoming_pixel['per_id'] ) ? $incoming_pixel['per_id'] : array() );

        // Display conditions travel inside main_pixel ({"condition":[...]}).
        if ( ! empty( $per_id['main_pixel'] ) && is_string( $per_id['main_pixel'] ) ) {
            $decoded = json_decode( $per_id['main_pixel'], true );
            if ( isset( $decoded['condition'] ) ) {
                $blob['condition'] = $decoded['condition'];
            }
        }

        // GA carries server-container settings per pixel.
        if ( $platform === 'ga' ) {
            if ( ! empty( $per_id['server_container_url'] ) ) {
                $blob['server_container_url'] = $per_id['server_container_url'];
            }
            if ( ! empty( $per_id['transport_url'] ) ) {
                $blob['transport_url'] = $per_id['transport_url'];
            }
        }

        // Credential (only when the user opted to include it).
        $token = '';
        if ( $include_token ) {
            $tokens = (array) ( isset( $incoming_pixel['tokens'] ) ? $incoming_pixel['tokens'] : array() );
            $token  = isset( $tokens['server_access_api_token'] ) ? (string) $tokens['server_access_api_token'] : '';
        }
        if ( $token !== '' ) {
            $blob['use_server_api'] = true;
            if ( $platform === 'facebook' ) {
                $blob['extensions'] = array( 'api_token' => $token );
            } else {
                $blob['server_access_api_token'] = $token;
            }
        }

        return $blob;
    }

    /**
     * Extract the credential token from an ext blob.
     *
     * @param array $blob Decoded ext-pixel blob.
     *
     * @return string
     */
    public static function tokenFromBlob( array $blob ): string {
        if ( isset( $blob['extensions']['api_token'] ) && trim( (string) $blob['extensions']['api_token'] ) !== '' ) {
            return (string) $blob['extensions']['api_token'];
        }
        if ( isset( $blob['server_access_api_token'] ) && trim( (string) $blob['server_access_api_token'] ) !== '' ) {
            return (string) $blob['server_access_api_token'];
        }
        return '';
    }

    /**
     * Put a token into a blob in the platform-correct field.
     *
     * @param array  $blob
     * @param string $platform
     * @param string $token
     *
     * @return array
     */
    public static function withToken( array $blob, $platform, $token ): array {
        $blob = self::stripToken( $blob );
        if ( $token === '' ) {
            return $blob;
        }
        $blob['use_server_api'] = true;
        if ( $platform === 'facebook' ) {
            if ( ! isset( $blob['extensions'] ) || ! is_array( $blob['extensions'] ) ) {
                $blob['extensions'] = array();
            }
            $blob['extensions']['api_token'] = $token;
        } else {
            $blob['server_access_api_token'] = $token;
        }
        return $blob;
    }

    /**
     * Merge one incoming blob into a platform's ext JSON-string array.
     *
     * @param array    $existing     Array of JSON strings (current ext pixels).
     * @param array    $blob         Incoming pixel blob (already token-adjusted).
     * @param string   $mode         'append'|'replace'|'skip'.
     * @param int|null $target_index Existing index to replace.
     *
     * @return array New array of JSON strings.
     */
    public static function mergeExt( array $existing, array $blob, $mode, $target_index = null ): array {

        if ( $mode === 'skip' ) {
            return $existing;
        }

        $existing = array_values( $existing );

        if ( $mode === 'append' ) {
            $existing[] = wp_json_encode( $blob );
        } elseif ( $mode === 'replace' && $target_index !== null && isset( $existing[ $target_index ] ) ) {
            $existing[ $target_index ] = wp_json_encode( $blob );
        } elseif ( $mode === 'replace' ) {
            // Target out of range — fall back to append rather than lose data.
            $existing[] = wp_json_encode( $blob );
        }

        return $existing;
    }
}
