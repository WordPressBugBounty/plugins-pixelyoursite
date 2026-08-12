<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Registry of Settings modules eligible for Site Profile export/import.
 *
 * Maps each module slug to its global accessor and display metadata. Resolution
 * is lazy and guarded by function_exists(), so add-on modules that are not
 * installed (Bing/Pinterest/Reddit ship as separate plugins) simply resolve to
 * null and drop out of the available list — the same "skip if the class doesn't
 * resolve" pattern the plugin already uses for its optional modules.
 */
class SiteProfileModuleRegistry {

    /**
     * Ordered module definitions (label, accessor, group, is_addon) keyed by slug.
     *
     * @return array<string, array>
     */
	public static function getDefinitions(): array {
		return array(
			'core'        => array( 'label'    => 'General',
			                        'accessor' => 'core',
			                        'group'    => 'core',
			                        'is_addon' => false
			),
			'facebook'    => array(
				'label'    => 'Meta',
				'accessor' => 'PixelYourSite\\Facebook',
				'group'    => 'pixel',
				'is_addon' => false
			),
			'ga'          => array(
				'label'    => 'Google Analytics',
				'accessor' => 'PixelYourSite\\GA',
				'group'    => 'pixel',
				'is_addon' => false
			),
			'gtm'         => array(
				'label'    => 'Google Tag Manager',
				'accessor' => 'PixelYourSite\\GTM',
				'group'    => 'pixel',
				'is_addon' => false
			),
			'gatags'      => array(
				'label'    => 'Google Tag',
				'accessor' => 'PixelYourSite\\GATags',
				'group'    => 'pixel',
				'is_addon' => false
			),
			'head_footer' => array( 'label'    => 'Head & Footer',
			                        'accessor' => 'PixelYourSite\\HeadFooter',
			                        'group'    => 'plugin',
			                        'is_addon' => false
			),
			'bing'        => array( 'label'    => 'Bing',
			                        'accessor' => 'PixelYourSite\\Bing',
			                        'group'    => 'pixel',
			                        'is_addon' => true
			),
			'pinterest'   => array( 'label'    => 'Pinterest',
			                        'accessor' => 'PixelYourSite\\Pinterest',
			                        'group'    => 'pixel',
			                        'is_addon' => true
			),
			'reddit'      => array( 'label'    => 'Reddit',
			                        'accessor' => 'PixelYourSite\\Reddit',
			                        'group'    => 'pixel',
			                        'is_addon' => true
			),
		);
	}

    /**
     * Per-ID schema (id_field/token_fields/per_id_fields) for a pixel module, or null.
     *
     * @param string $slug Module slug.
     *
     * @return array|null
     */
    public static function getPixelSchema( $slug ): ?array {

        $schemas = array(
            'facebook'   => array(
                'id_field'      => 'pixel_id',
                'token_fields'  => array( 'server_access_api_token' ),
                'per_id_fields' => array(),
                'per_id_pattern' => null,
            ),
            'ga'         => array(
                'id_field'      => 'tracking_id',
                'token_fields'  => array(),
                'per_id_fields' => array( 'server_container_url', 'transport_url' ),
                'per_id_pattern' => null,
            ),
            'gtm'        => array(
                'id_field'      => 'gtm_id',
                'token_fields'  => array(),
                'per_id_fields' => array( 'main_pixel', 'server_container_url' ),
                'per_id_pattern' => null,
            ),
            'bing'       => array(
                'id_field'      => 'pixel_id',
                'token_fields'  => array(),
                'per_id_fields' => array( 'main_pixel' ),
                'per_id_pattern' => null,
            ),
            'pinterest'  => array(
                'id_field'      => 'pixel_id',
                'token_fields'  => array( 'server_access_api_token' ),
                'per_id_fields' => array( 'main_pixel', 'ad_account_id' ),
                'per_id_pattern' => null,
            ),
            'reddit'     => array(
                'id_field'      => 'pixel_id',
                'token_fields'  => array(),
                'per_id_fields' => array( 'main_pixel' ),
                'per_id_pattern' => null,
            ),
        );

        return isset( $schemas[ $slug ] ) ? $schemas[ $slug ] : null;
    }

    /**
     * Whether a module uses the per-ID (multi-pixel) schema.
     *
     * @param string $slug
     *
     * @return bool
     */
    public static function isPixelModule( $slug ): bool {
        return self::getPixelSchema( $slug ) !== null;
    }

    /**
     * Resolve a module slug to its Settings instance, or null when not loaded.
     *
     * @param string $slug Module slug.
     *
     * @return Settings|null
     */
    public static function resolve( $slug ): ?Settings {

        $definitions = self::getDefinitions();

        if ( ! isset( $definitions[ $slug ] ) ) {
            return null;
        }

        $definition = $definitions[ $slug ];

        if ( ! empty( $definition['is_addon'] ) && ! self::isAddonActive( $slug ) ) {
            return null;
        }

        $accessor = $definition['accessor'];

        if ( $accessor === 'core' ) {
            $instance = function_exists( 'PixelYourSite\\PYS' ) ? PYS() : null;
        } elseif ( function_exists( $accessor ) ) {
            $instance = call_user_func( $accessor );
        } else {
            $instance = null;
        }

        return ( $instance instanceof Settings ) ? $instance : null;
    }

    /**
     * Whether an add-on module's plugin is installed and active.
     *
     * @param string $slug Module slug.
     *
     * @return bool
     */
    private static function isAddonActive( $slug ): bool {

        $plugin_files = array(
            'bing'      => 'pixelyoursite-bing/pixelyoursite-bing.php',
            'pinterest' => 'pixelyoursite-pinterest/pixelyoursite-pinterest.php',
            'reddit'    => 'pixelyoursite-reddit/pixelyoursite-reddit.php',
        );

        if ( ! isset( $plugin_files[ $slug ] ) ) {
            return true; // not a gated add-on
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( $plugin_files[ $slug ] );
    }

    /**
     * Return every module available on this site (slug, label, group, is_addon, instance).
     *
     * @return array<int, array>
     */
    public static function getAvailableModules(): array {

        $available = array();

        foreach ( self::getDefinitions() as $slug => $definition ) {

            $instance = self::resolve( $slug );

            if ( $instance === null ) {
                continue;
            }

            $available[] = array(
                'slug'     => $slug,
                'label'    => $definition['label'],
                'group'    => $definition['group'],
                'is_addon' => $definition['is_addon'],
                'instance' => $instance,
            );
        }

        return $available;
    }

    /**
     * List of slugs available on this site (no instances).
     *
     * @return string[]
     */
    public static function getAvailableSlugs(): array {
        return array_map(
            function ( $module ) {
                return $module['slug'];
            },
            self::getAvailableModules()
        );
    }
}
