<?php
/**
 * Product feed ID check.
 *
 * PixelYourSite (free) build. Compares the WooCommerce ID settings of each module with the
 * ID settings of the catalog sources we can read: Product Catalog Feed Pro
 * feeds (through its public API), Facebook for WooCommerce (its logic is a
 * PixelYourSite setting) and Google Listings & Ads (fixed format). It renders
 * the "Product feed ID check" card on the WooCommerce tab, a compact block
 * inside each platform's ID settings card, and feeds one dismissable admin
 * notice. It never writes a setting: the only action is a button that
 * pre-fills form fields, saved by the page's own Save Changes button.
 *
 * All texts are fixed templates (see messages()).
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeedIdCheck {

	const STATE_OPTION      = 'pys_feed_id_check_state';
	const FEED_MIN_VERSION  = '5.9.3';
	const NOTICE_SLUG       = 'pys_feed_id_check_';

	/** Feed types that are localized children: they use the main feed's ID settings. */
	const LOCALIZED_FEED_TYPES = array( 'fb_localize', 'fb_country', 'google_local_inventory' );

	/** Product Catalog Feed Pro feed type => platform row. Other types (reviews, children) are ignored. */
	const FEED_TYPE_PLATFORM = array(
		'facebook'      => 'meta',
		'google'        => 'google',
		'adsensecustom' => 'google',
		'pinterest'     => 'pinterest',
		'tiktok'        => 'tiktok',
		'reddit'        => 'reddit',
	);

	/**
	 * Feed plugins we can recognise but not read. Listed, never compared.
	 * Plugin basenames to verify against the current wordpress.org versions.
	 */
	const OTHER_FEED_PLUGINS = array(
		'CTXFeed'                                        => array( 'webappick-product-feed-for-woocommerce/woo-feed.php', 'webappick-product-feed-for-woocommerce-pro/woo-feed-pro.php' ),
		'Product Feed PRO for WooCommerce (AdTribes)'    => array( 'woo-product-feed-pro/woocommerce-sea.php', 'woo-product-feed-elite/woocommerce-sea.php' ),
		'Product Feed Manager for WooCommerce (RexTheme)' => array( 'best-woocommerce-feed/rex-product-feed.php', 'rex-product-feed-pro/rex-product-feed-pro.php' ),
		'Simprosys Google Shopping Feed'                 => array( 'simprosys-google-shopping-feed/simprosys-google-shopping-feed.php', 'simprosys-google-shopping-feed-pro/simprosys-google-shopping-feed.php' ),
	);

	/** @var array|null Per-request cache of evaluate() results, keyed by platform. */
	private static $results = null;
	/** @var array|null Per-request cache of feeds grouped by platform. */
	private static $feeds_cache = null;
	/** @var bool|null Per-request cache of the Google Listings & Ads customization check. */
	private static $gla_customized = null;

	/* ------------------------------------------------------------------ */
	/* Bootstrap                                                            */
	/* ------------------------------------------------------------------ */

	/** The stored notice state is recomputed when older than this (feed edits have no hook of their own). */
	const STATE_TTL = 21600; // 6 hours

	public static function init() {
		if ( ! is_admin() ) {
			return; // nothing runs on front-end requests
		}
		// New feeds in Product Catalog Feed Pro go through update_option (wpwoof_feedlist_*); edits use a direct
		// query, so the stored state also expires after STATE_TTL and is refreshed on the feed plugin's own page.
		add_action( 'updated_option', array( __CLASS__, 'maybeRefreshOnOption' ), 10, 1 );
		add_action( 'added_option', array( __CLASS__, 'maybeRefreshOnOption' ), 10, 1 );
		add_action( 'deleted_option', array( __CLASS__, 'maybeRefreshOnOption' ), 10, 1 );
		add_action( 'admin_init', array( __CLASS__, 'maybeRefreshOnFeedPage' ), 99 );
		// A plugin was (de)activated: the stored state may be stale, drop it until the next render.
		add_action( 'activated_plugin', array( __CLASS__, 'forgetState' ) );
		add_action( 'deactivated_plugin', array( __CLASS__, 'forgetState' ) );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueueAssets' ), 20 );
	}

	public static function maybeRefreshOnFeedPage() {
		if ( 'wpwoof-settings' === self::queryArg( 'page' ) && current_user_can( 'manage_pys' ) ) {
			self::refreshState();
		}
	}

	public static function maybeRefreshOnOption( $option ) {
		if ( is_string( $option ) && 0 === strpos( $option, 'wpwoof_feedlist_' ) ) {
			self::resetCache();
			self::refreshState();
		}
	}

	/** A sanitized query-string value (read only, never echoed). */
	private static function queryArg( $key ) {
		return isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function forgetState() {
		self::resetCache();
		delete_option( self::STATE_OPTION );
	}

	/** Drop the per-request cache (settings changed within the request, tests). */
	public static function resetCache() {
		self::$results        = null;
		self::$feeds_cache    = null;
		self::$gla_customized = null;
	}

	public static function enqueueAssets() {
		if ( ! isWooCommerceActive() || ! current_user_can( 'manage_pys' ) ) {
			return;
		}
		if ( 'pixelyoursite' !== self::queryArg( 'page' ) || 'woo' !== self::queryArg( 'tab' ) ) {
			return;
		}
		wp_enqueue_style( 'pys_feed_id_check', PYS_FREE_URL . '/dist/styles/feed-id-check.css', array( 'pys_css' ), PYS_FREE_VERSION );
		wp_enqueue_script( 'pys_feed_id_check', PYS_FREE_URL . '/dist/scripts/feed-id-check.js', array( 'pys_js' ), PYS_FREE_VERSION, true );
		wp_localize_script( 'pys_feed_id_check', 'pysFeedIdCheck', array(
			'messages' => array(
				'applied'  => self::message( 'applied' ),
				'revert'   => self::message( 'revert' ),
				'unsaved'  => self::message( 'unsaved' ),
			),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Configuration                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Platform rows. Modules are PixelYourSite settings slugs; only enabled ones are read.
	 */
	public static function platforms() {
		return array(
			'meta'      => array( 'name' => 'Meta',      'modules' => array( 'facebook' ),      'logo' => 'meta-logo.svg' ),
			'google'    => array( 'name' => 'Google',    'modules' => array( 'gatags', 'gtm' ), 'logo' => 'google-ads-logo.svg' ),
			'pinterest' => array( 'name' => 'Pinterest', 'modules' => array( 'pinterest' ),     'logo' => 'pinterest-logo.svg', 'addon' => 'Pinterest add-on' ),
			'reddit'    => array( 'name' => 'Reddit',    'modules' => array( 'reddit' ),        'logo' => 'reddit-logo.svg', 'addon' => 'Reddit add-on' ),
		);
	}

	public static function moduleNames() {
		return array(
			'facebook'  => 'Facebook',
			'gatags'    => 'Google Tags',
			'gtm'       => 'GTM',
			'tiktok'    => 'TikTok',
			'pinterest' => 'Pinterest',
			'reddit'    => 'Reddit',
		);
	}

	public static function platformOfModule( $slug ) {
		foreach ( self::platforms() as $key => $platform ) {
			if ( in_array( $slug, $platform['modules'], true ) ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * @return Settings|null
	 */
	public static function module( $slug ) {
		switch ( $slug ) {
			case 'facebook':  return function_exists( 'PixelYourSite\Facebook' ) ? Facebook() : null;
			case 'gatags':    return function_exists( 'PixelYourSite\GATags' ) ? GATags() : null;
			case 'gtm':       return function_exists( 'PixelYourSite\GTM' ) ? GTM() : null;
			case 'pinterest': return function_exists( 'PixelYourSite\Pinterest' ) ? Pinterest() : null;
			case 'reddit':    return function_exists( 'PixelYourSite\Reddit' ) ? Reddit() : null;
		}
		return null;
	}

	public static function moduleEnabled( $slug ) {
		$module = self::module( $slug );
		return $module && method_exists( $module, 'enabled' ) && $module->enabled();
	}

	/** Add-on modules must ship the shared ID rule (the variation-SKU setting) to be compared. */
	public static function moduleSupported( $slug ) {
		$module = self::module( $slug );
		if ( ! $module ) {
			return false;
		}
		if ( ! in_array( $slug, array( 'pinterest', 'reddit' ), true ) ) {
			return true;
		}
		return method_exists( $module, 'issetOption' ) && $module->issetOption( WOO_CONTENT_ID_VARIATION_SKU_KEY );
	}

	/* ------------------------------------------------------------------ */
	/* Profiles                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * The ID settings of a PixelYourSite module, described as a profile.
	 *
	 * @return array|null
	 */
	public static function moduleProfile( $slug, $platform ) {
		$module = self::module( $slug );
		if ( ! $module ) {
			return null;
		}
		if ( 'facebook' === $slug && Facebook\Helpers\isFacebookForWooCommerceActive() && ! Facebook\Helpers\isDefaultWooContentIdLogic() ) {
			return array( 'kind' => 'fb4w' );
		}

		$source = (string) $module->getOption( 'woo_content_id', 'product_id' );
		if ( ! in_array( $source, array( 'product_id', 'product_sku', 'custom_field' ), true ) ) {
			$source = 'product_id';
		}
		$as_simple = (bool) $module->getOption( 'woo_variable_as_simple', false );
		$rule      = (string) $module->getOption( WOO_CONTENT_ID_VARIATION_SKU_KEY, 'parent_sku' );

		return array(
			'kind'          => 'rule',
			'source'        => $source,
			'custom_key'    => 'custom_field' === $source ? trim( (string) $module->getOption( 'woo_content_id_custom_field_name', '' ) ) : '',
			'prefix'        => (string) $module->getOption( 'woo_content_id_prefix', '' ),
			'suffix'        => (string) $module->getOption( 'woo_content_id_suffix', '' ),
			'variation_sku' => 'variation_id' === $rule ? 'variation_id' : 'parent_sku',
			'as_simple'     => $as_simple,
			'parents'       => $as_simple ? 'parent_only' : ( 'google' === $platform ? 'both' : 'variations' ),
		);
	}

	/**
	 * Profile of a Product Catalog Feed Pro feed from its id_config.
	 */
	public static function feedProfile( array $id_config ) {
		$raw = isset( $id_config['source'] ) ? (string) $id_config['source'] : 'id';
		if ( 'id' === $raw ) {
			$source = 'product_id';
			$key    = '';
		} elseif ( 'sku' === $raw ) {
			$source = 'product_sku';
			$key    = '';
		} elseif ( 0 === strpos( $raw, 'pa_' ) ) {
			$source = 'attribute';
			$key    = $raw;
		} else {
			$source = 'custom_field';
			$key    = $raw;
		}
		$mode = isset( $id_config['variable_products'] ) ? (string) $id_config['variable_products'] : 'variations';
		if ( ! in_array( $mode, array( 'variations', 'parent_only', 'both' ), true ) ) {
			$mode = 'variations';
		}
		return array(
			'kind'          => 'rule',
			'source'        => $source,
			'custom_key'    => $key,
			'prefix'        => isset( $id_config['prefix'] ) ? (string) $id_config['prefix'] : '',
			'suffix'        => isset( $id_config['suffix'] ) ? (string) $id_config['suffix'] : '',
			'variation_sku' => ( isset( $id_config['variation_sku'] ) && 'variation_id' === $id_config['variation_sku'] ) ? 'variation_id' : 'parent_sku',
			'parents'       => $mode,
		);
	}

	public static function glaProfile() {
		return array(
			'kind'          => 'rule',
			'source'        => 'product_id',
			'custom_key'    => '',
			'prefix'        => 'gla_',
			'suffix'        => '',
			'variation_sku' => 'parent_sku',
			'parents'       => 'variations',
		);
	}

	/* ------------------------------------------------------------------ */
	/* Sources                                                              */
	/* ------------------------------------------------------------------ */

	public static function feedPluginActive() {
		return defined( 'WPWOOF_VERSION' ) || class_exists( 'wpwoof_product_catalog' );
	}

	/**
	 * Installed version of Product Catalog Feed Pro, '' when it cannot be read.
	 *
	 * WPWOOF_VERSION is defined only on admin, cron and CLI requests (the plugin
	 * returns before its defines on the front end), so fall back to the plugin header.
	 */
	public static function feedPluginVersion() {
		if ( defined( 'WPWOOF_VERSION' ) ) {
			return (string) WPWOOF_VERSION;
		}
		$file = WP_PLUGIN_DIR . '/product-catalog-feed-pro/product-catalog-feed-pro.php';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$data = get_file_data( $file, array( 'Version' => 'Version' ) );
		return isset( $data['Version'] ) ? (string) $data['Version'] : '';
	}

	/**
	 * The feed plugin is at least FEED_MIN_VERSION and exposes the feed API. Both are
	 * checked: the version alone could pass on a build that lacks the functions, the
	 * functions alone would accept a version the comparison was never written for.
	 */
	public static function feedPluginUsable() {
		return version_compare( self::feedPluginVersion(), self::FEED_MIN_VERSION, '>=' )
			&& function_exists( 'wpwoof_get_feeds_summary' )
			&& function_exists( 'wpwoof_feed_id_config_from_settings' );
	}

	/**
	 * Main feeds per platform: [ platform => [ [ 'id', 'name', 'type', 'profile' ], ... ] ].
	 * Uses the feed plugin's API; falls back to a raw read of feed names when the
	 * API is missing (older plugin), so those feeds can be listed as "Not checked".
	 */
	private static function feedsByPlatform() {
		if ( null !== self::$feeds_cache ) {
			return self::$feeds_cache;
		}
		$cache = array( 'usable' => array(), 'blocked' => array() );
		self::$feeds_cache = &$cache;
		if ( ! self::feedPluginActive() ) {
			return $cache;
		}

		if ( self::feedPluginUsable() ) {
			$summary = wpwoof_get_feeds_summary();
			foreach ( (array) $summary as $feed ) {
				if ( ! is_array( $feed ) || empty( $feed['name'] ) ) {
					continue;
				}
				$type = isset( $feed['type'] ) ? (string) $feed['type'] : 'facebook';
				if ( in_array( $type, self::LOCALIZED_FEED_TYPES, true ) || ! isset( self::FEED_TYPE_PLATFORM[ $type ] ) ) {
					continue;
				}
				if ( empty( $feed['id_config'] ) || ! is_array( $feed['id_config'] ) ) {
					continue;
				}
				$platform = self::FEED_TYPE_PLATFORM[ $type ];
				$cache['usable'][ $platform ][] = array(
					'id'      => (int) $feed['id'],
					'name'    => (string) $feed['name'],
					'type'    => $type,
					'profile' => self::feedProfile( $feed['id_config'] ),
				);
			}
			return $cache;
		}

		// Older Product Catalog Feed Pro: list feed names only.
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_name LIKE 'wpwoof\\_feedlist\\_%'", \ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$config = maybe_unserialize( $row['option_value'] );
			if ( ! is_array( $config ) || empty( $config['feed_name'] ) ) {
				continue;
			}
			$type = isset( $config['feed_type'] ) ? (string) $config['feed_type'] : 'facebook';
			if ( in_array( $type, self::LOCALIZED_FEED_TYPES, true ) || ! isset( self::FEED_TYPE_PLATFORM[ $type ] ) ) {
				continue;
			}
			$cache['blocked'][ self::FEED_TYPE_PLATFORM[ $type ] ][] = array(
				'id'   => (int) $row['option_id'],
				'name' => (string) $config['feed_name'],
			);
		}
		return $cache;
	}

	public static function glaActive() {
		return defined( 'WC_GLA_VERSION' ) || class_exists( 'Automattic\WooCommerce\GoogleListingsAndAds\Product\WCProductAdapter' );
	}

	/** Merchant Center connected: Google account connected and setup completed (options gla_*). */
	public static function glaConnected() {
		return (bool) get_option( 'gla_google_connected', false ) && (bool) get_option( 'gla_mc_setup_completed_at', false );
	}

	/**
	 * Whether the offer ID format was changed by a filter. One cheap product lookup.
	 */
	public static function glaCustomized() {
		if ( null !== self::$gla_customized ) {
			return self::$gla_customized;
		}
		$cached = false;
		self::$gla_customized = &$cached;
		$class  = 'Automattic\WooCommerce\GoogleListingsAndAds\Product\WCProductAdapter';
		if ( ! is_callable( array( $class, 'get_google_product_offer_id' ) ) ) {
			return $cached;
		}
		$product_id = 0;
		if ( function_exists( 'wc_get_products' ) ) {
			$ids = wc_get_products( array( 'status' => 'publish', 'limit' => 1, 'return' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ) );
			if ( ! empty( $ids ) ) {
				$product_id = (int) reset( $ids );
			}
		}
		if ( ! $product_id ) {
			return $cached;
		}
		try {
			$offer  = (string) call_user_func( array( $class, 'get_google_product_offer_id' ), 'gla', $product_id );
			$cached = ( $offer !== 'gla_' . $product_id );
		} catch ( \Throwable $e ) {
			$cached = false;
		}
		return $cached;
	}

	/**
	 * Sources for a platform.
	 */
	public static function sourcesFor( $platform ) {
		$feeds   = self::feedsByPlatform();
		$usable  = array();
		$blocked = array();
		$notes   = array();

		$fb4w_active   = 'meta' === $platform && Facebook\Helpers\isFacebookForWooCommerceActive();
		$fb4w_selected = $fb4w_active && ! Facebook\Helpers\isDefaultWooContentIdLogic();

		foreach ( isset( $feeds['usable'][ $platform ] ) ? $feeds['usable'][ $platform ] : array() as $feed ) {
			if ( $fb4w_selected ) {
				$notes[] = self::message( 'fb4w_feed_not_compared', array( '{feed}' => self::quote( $feed['name'] ) ) );
				continue;
			}
			$usable[] = array(
				'key'     => 'feed' . $feed['id'],
				'kind'    => 'own',
				'feed'    => $feed,
				'name'    => 'Product Catalog Feed Pro: ' . $feed['name'],
				'short'   => 'feed ' . self::quote( $feed['name'] ),
				'profile' => $feed['profile'],
			);
		}
		foreach ( isset( $feeds['blocked'][ $platform ] ) ? $feeds['blocked'][ $platform ] : array() as $feed ) {
			$blocked[] = array(
				'name'   => 'Product Catalog Feed Pro feed ' . self::quote( $feed['name'] ),
				'why'    => self::message( 'feed_plugin_old' ),
				'action' => self::message( 'update_to_compare' ),
			);
		}

		if ( $fb4w_selected ) {
			$usable[] = array( 'key' => 'fb4w', 'kind' => 'fb4w', 'name' => 'Facebook for WooCommerce', 'short' => 'Facebook for WooCommerce', 'profile' => array( 'kind' => 'fb4w' ) );
		}

		if ( 'google' === $platform && self::glaActive() ) {
			if ( ! self::glaConnected() ) {
				// Not a catalog yet: mention it, but it doesn't block the row or change the badge.
				$notes[] = trim( self::message( 'not_compared', array( '{name}' => 'Google Listings & Ads', '{why}' => self::message( 'gla_not_connected' ), '{action}' => '' ) ) );
			} else {
				$usable[] = array(
					'key'     => 'gla',
					'kind'    => self::glaCustomized() ? 'gla_custom' : 'gla',
					'name'    => 'Google Listings & Ads',
					'short'   => 'Google Listings & Ads',
					'profile' => self::glaProfile(),
				);
			}
		}

		return array(
			'usable'        => $usable,
			'blocked'       => $blocked,
			'notes'         => $notes,
			'fb4w_active'   => $fb4w_active,
			'fb4w_selected' => $fb4w_selected,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Comparison                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Compare a PixelYourSite profile with a source profile.
	 *
	 * @return array rows: [ key, ok, pys, src, note ]
	 */
	public static function compareProfiles( array $pys, array $src, $platform ) {
		if ( 'fb4w' === $src['kind'] ) {
			return array( array( 'key' => 'logic', 'ok' => 'fb4w' === $pys['kind'], 'pys' => '', 'src' => '', 'note' => '' ) );
		}
		if ( 'fb4w' === $pys['kind'] ) {
			return array( array( 'key' => 'logic', 'ok' => false, 'pys' => '', 'src' => '', 'note' => '' ) );
		}

		$rows = array();

		$same_source = $pys['source'] === $src['source'];
		if ( $same_source && 'custom_field' === $pys['source'] ) {
			$same_source = $pys['custom_key'] !== '' && $pys['custom_key'] === $src['custom_key'];
		}
		$rows[] = array( 'key' => 'source', 'ok' => $same_source, 'pys' => self::sourceLabel( $pys ), 'src' => self::sourceLabel( $src ), 'note' => '' );
		$rows[] = array( 'key' => 'prefix', 'ok' => $pys['prefix'] === $src['prefix'], 'pys' => $pys['prefix'], 'src' => $src['prefix'], 'note' => '' );
		$rows[] = array( 'key' => 'suffix', 'ok' => $pys['suffix'] === $src['suffix'], 'pys' => $pys['suffix'], 'src' => $src['suffix'], 'note' => '' );

		$both_sku = 'product_sku' === $pys['source'] && 'product_sku' === $src['source'];
		$rows[]   = array(
			'key'  => 'variation_sku',
			'ok'   => ! $both_sku || $pys['variation_sku'] === $src['variation_sku'],
			'pys'  => self::ruleLabel( $pys['variation_sku'] ),
			'src'  => self::ruleLabel( $src['variation_sku'] ),
			'note' => '',
		);

		$sends_parent     = 'parent_only' === $pys['parents'];
		$catalog_parents  = 'variations' !== $src['parents'];
		$catalog_variants = 'parent_only' !== $src['parents'];
		if ( $sends_parent ) {
			$ok   = $catalog_parents;
			$note = $ok ? '' : self::message( 'diff_variable_parent' );
		} else {
			$ok   = $catalog_variants;
			$note = $ok ? '' : self::message( 'diff_variable_variations' );
		}
		$rows[] = array( 'key' => 'variable', 'ok' => $ok, 'pys' => $pys['parents'], 'src' => $src['parents'], 'note' => $note );

		return $rows;
	}

	/**
	 * Evaluate one platform.
	 */
	public static function evaluate( $platform ) {
		$platforms = self::platforms();
		if ( ! isset( $platforms[ $platform ] ) ) {
			return null;
		}
		$pf      = $platforms[ $platform ];
		$modules = array();
		foreach ( $pf['modules'] as $slug ) {
			if ( self::moduleEnabled( $slug ) ) {
				$modules[] = $slug;
			}
		}

		$out = array(
			'platform'      => $platform,
			'name'          => $pf['name'],
			'logo'          => $pf['logo'],
			'modules'       => $modules,
			'usable'        => array(),
			'blocked'       => array(),
			'notes'         => array(),
			'lines'         => array(),
			'status'        => 'none',
			'fb4w_active'   => false,
			'fb4w_selected' => false,
		);
		if ( empty( $modules ) ) {
			// Add-on platform with an own feed but the add-on inactive: say so instead of staying silent.
			if ( ! empty( $pf['addon'] ) ) {
				$feeds = self::feedsByPlatform();
				if ( ! empty( $feeds['usable'][ $platform ] ) || ! empty( $feeds['blocked'][ $platform ] ) ) {
					$out['blocked'][] = array( 'name' => $pf['addon'], 'why' => self::message( 'addon_inactive' ), 'action' => self::message( 'update_to_compare' ) );
					$out['status']    = 'notchecked';
				}
			}
			return $out;
		}

		$sources               = self::sourcesFor( $platform );
		$out['usable']         = $sources['usable'];
		$out['blocked']        = $sources['blocked'];
		$out['notes']          = $sources['notes'];
		$out['fb4w_active']    = $sources['fb4w_active'];
		$out['fb4w_selected']  = $sources['fb4w_selected'];

		// Add-on below the minimum version: nothing can be compared for this platform.
		foreach ( $modules as $slug ) {
			if ( ! self::moduleSupported( $slug ) ) {
				$out['blocked'][] = array( 'name' => isset( $pf['addon'] ) ? $pf['addon'] : self::moduleNames()[ $slug ] . ' add-on', 'why' => self::message( 'addon_old' ), 'action' => self::message( 'update_to_compare' ) );
				$out['usable']    = array();
			}
		}

		if ( empty( $out['usable'] ) ) {
			if ( ! empty( $out['blocked'] ) ) {
				$out['status'] = 'notchecked';
			} elseif ( $out['fb4w_active'] && ! $out['fb4w_selected'] ) {
				$out['status'] = 'reminder';
			}
			return $out;
		}

		foreach ( $out['usable'] as $src ) {
			$per_module = array();
			foreach ( $modules as $slug ) {
				$pys  = self::moduleProfile( $slug, $platform );
				$rows = self::compareProfiles( $pys, $src['profile'], $platform );
				$ok   = true;
				foreach ( $rows as $row ) {
					if ( ! $row['ok'] ) {
						$ok = false;
						break;
					}
				}
				$per_module[ $slug ] = array( 'rows' => $rows, 'ok' => $ok, 'pys' => $pys );
			}
			$line_ok = true;
			foreach ( $per_module as $pm ) {
				if ( ! $pm['ok'] ) {
					$line_ok = false;
				}
			}
			$out['lines'][] = array( 'src' => $src, 'per_module' => $per_module, 'ok' => $line_ok );
		}

		$any_ok = false;
		foreach ( $out['lines'] as $line ) {
			if ( $line['ok'] ) {
				$any_ok = true;
			}
		}
		$out['status'] = $any_ok ? 'match' : 'differ';
		return $out;
	}

	/**
	 * All platform results (per-request cache).
	 */
	public static function results() {
		if ( null !== self::$results ) {
			return self::$results;
		}
		self::$results = array();
		foreach ( array_keys( self::platforms() ) as $platform ) {
			$result = self::evaluate( $platform );
			if ( $result ) {
				self::$results[ $platform ] = $result;
			}
		}
		return self::$results;
	}

	public static function otherFeedPlugins() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$found = array();
		foreach ( self::OTHER_FEED_PLUGINS as $name => $basenames ) {
			foreach ( $basenames as $basename ) {
				if ( is_plugin_active( $basename ) ) {
					$found[] = $name;
					break;
				}
			}
		}
		return $found;
	}

	/* ------------------------------------------------------------------ */
	/* State for the admin notice                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Recompute and store the notice state; written only when it changed.
	 */
	public static function refreshState() {
		if ( ! isWooCommerceActive() ) {
			delete_option( self::STATE_OPTION );
			return;
		}
		self::resetCache();
		$differ = array();
		foreach ( self::results() as $platform => $result ) {
			if ( 'differ' !== $result['status'] ) {
				continue;
			}
			$sources = array();
			foreach ( $result['lines'] as $line ) {
				$sources[] = $line['src']['name'];
			}
			$differ[ $platform ] = array( 'name' => $result['name'], 'sources' => $sources );
		}
		$hash  = empty( $differ ) ? '' : substr( md5( wp_json_encode( $differ ) ), 0, 12 );
		$state = array( 'hash' => $hash, 'differ' => $differ, 'time' => time() );
		$old   = get_option( self::STATE_OPTION, array() );
		$stale = ! is_array( $old ) || ! isset( $old['time'] ) || time() - (int) $old['time'] > self::STATE_TTL;
		if ( $stale || ( isset( $old['hash'] ) ? $old['hash'] : null ) !== $hash ) {
			update_option( self::STATE_OPTION, $state, false );
		}
	}

	/**
	 * Entries for notices/fixed.php (promo type, backend, dismissable by slug = hash).
	 */
	public static function noticeEntries() {
		if ( ! isWooCommerceActive() ) {
			return array();
		}
		$state = get_option( self::STATE_OPTION, array() );
		if ( is_array( $state ) && isset( $state['time'] ) && time() - (int) $state['time'] > self::STATE_TTL && current_user_can( 'manage_pys' ) ) {
			self::refreshState();
			$state = get_option( self::STATE_OPTION, array() );
		}
		if ( ! is_array( $state ) || empty( $state['hash'] ) || empty( $state['differ'] ) ) {
			return array();
		}
		$names = array();
		foreach ( $state['differ'] as $item ) {
			$names[] = $item['name'];
		}
		if ( 1 === count( $state['differ'] ) ) {
			$item   = reset( $state['differ'] );
			$source = count( $item['sources'] ) === 1 ? reset( $item['sources'] ) : 'your product feeds';
			if ( 0 === strpos( $source, 'Product Catalog Feed Pro: ' ) ) {
				$source = 'your Product Catalog Feed Pro feed ' . self::quote( substr( $source, strlen( 'Product Catalog Feed Pro: ' ) ) );
			}
			$text = self::message( 'notice_one', array( '{platform}' => $item['name'], '{source}' => $source ) );
		} else {
			$text = self::message( 'notice_many', array( '{platforms}' => self::joinNames( $names ) ) );
		}
		$url = admin_url( 'admin.php?page=pixelyoursite&tab=woo#pys-feed-id-check' );

		return array(
			array(
				'type'          => 'promo',
				'location'      => 'backend',
				'plugins'       => array(),
				'enabelDismiss' => true,
				'slug'          => self::NOTICE_SLUG . $state['hash'],
				'title'         => esc_html( self::message( 'notice_title' ) ),
				'message'       => esc_html( $text ) . ' <a class="notice_watch_button" href="' . esc_url( $url ) . '">' . esc_html( self::message( 'notice_button' ) ) . '</a>',
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Messages (fixed templates)                                           */
	/* ------------------------------------------------------------------ */

	public static function messages() {
		return array(
			'intro'                    => 'Dynamic ads match events to catalog products by ID, so the IDs PixelYourSite sends must be the same as in your catalog. This check compares settings only. It can\'t know which catalog you use, so read each point before changing anything. It changes nothing by itself.',
			'video'                    => 'How product IDs work (video)',
			'badge_differ_one'         => '{platform}: IDs differ from your feed',
			'badge_differ_many'        => '{n} platforms: IDs differ from your feeds',
			'badge_match'              => 'IDs match your feeds',
			'badge_notchecked'         => 'Not checked: updates needed',
			'badge_none'               => 'Nothing to compare',
			'status_match'             => 'IDs match',
			'status_differ'            => 'IDs differ',
			'status_notchecked'        => 'Not checked',
			'status_reminder'          => 'Reminder',
			'sub_fb4w'                 => 'Facebook for WooCommerce',
			'sub_fb4w_active'          => 'Facebook for WooCommerce is active',
			'same'                     => 'Same IDs as {source}.',
			'same_feeds'               => 'Same IDs as feeds {feeds}.',
			'different'                => 'Different IDs from {source}:',
			'different_feeds'          => 'Different IDs from feeds {feeds}:',
			'diff_source'              => 'ID: {who} sends {a}; {source} uses {b}.',
			'diff_prefix'              => 'Prefix: {who} sends {a}; {source} uses {b}.',
			'diff_suffix'              => 'Suffix: {who} sends {a}; {source} uses {b}.',
			'diff_variation_sku'       => 'If a variation has no SKU of its own: {who} sends {a}; {source} uses {b}.',
			'diff_variable'            => 'Variable products: {note}',
			'diff_variable_parent'     => 'PixelYourSite sends only the parent product; the feed has no parent rows.',
			'diff_variable_variations' => 'PixelYourSite sends variations; the feed lists only parent products.',
			'who_pys'                  => 'PixelYourSite',
			'empty'                    => 'empty',
			'fix_own'                  => 'If {platform} uses this feed as its catalog: in the {where} ID settings set {settings}. If not, ignore this.',
			'fix_own_alt'              => 'Or change the feed {feed} instead. Changing the IDs of a catalog in use can disrupt running ads, so we suggest changing PixelYourSite.',
			'fix_own_feed_only'        => 'PixelYourSite can\'t use this feed\'s ID source. If {platform} uses this feed as its catalog, change the feed {feed}: set {settings}. If not, ignore this.',
			'fix_own_feed_only_side'   => 'Changing the IDs of a catalog in use can disrupt running ads.',
			'edit_feed'                => 'edit feed',
			'open_settings'            => 'open',
			'other_ok'                 => 'Different IDs from {source}. If {platform} doesn\'t use this feed, ignore this. If it does, give it the same ID settings as the matching feed.',
			'other_ok_feeds'           => 'Different IDs from feeds {feeds}. If {platform} doesn\'t use these feeds, ignore this. If it does, give them the same ID settings as the matching feed.',
			'other_ok_fixed'           => 'Different IDs from {source}. If {platform} doesn\'t use it, ignore this.',
			'fb4w_q'                   => 'Facebook for WooCommerce is active. PixelYourSite uses its own ID logic.',
			'fb4w_yes'                 => 'If Facebook for WooCommerce builds your Meta catalog:',
			'fb4w_yes_action'          => 'select Use Facebook for WooCommerce content_id logic.',
			'fb4w_open'                => 'Open the setting',
			'fb4w_no'                  => 'If Product Catalog Feed Pro or another feed builds your Meta catalog:',
			'fb4w_no_action'           => 'keep PixelYourSite logic.',
			'fb4w_no_feed'             => 'Your Product Catalog Feed Pro feed {feed} is compared below.',
			'fb4w_no_feeds'            => 'Your Product Catalog Feed Pro feeds {feeds} are compared below.',
			'fb4w_no_nothing'          => 'Nothing needs to change.',
			'fb4w_card_reminder'       => 'If Facebook for WooCommerce builds your Meta catalog, select its logic below. If Product Catalog Feed Pro or another feed builds it, keep PixelYourSite logic.',
			'fb4w_selected'            => 'Facebook for WooCommerce logic selected. Meta events use the IDs of the catalog it creates.',
			'fb4w_feed_not_compared'   => 'You selected the Facebook for WooCommerce logic, so Meta events use the IDs created by that plugin, not the IDs in your Product Catalog Feed Pro feed {feed}. This feed is not compared. If your Meta catalog actually uses this feed, select the PixelYourSite logic above instead.',
			'gla_fix'                  => 'If Google Listings & Ads provides your Google Ads catalog: in {modules} set {settings}.',
			'gla_side'                 => 'Google Listings & Ads lists variations only; the parent product has no catalog row, which is expected. Changing these settings also changes GA4 item IDs, so GA4 item reports before and after the change won\'t connect.',
			'gla_custom'               => 'Google Listings & Ads seems to use a customized ID format (changed by code). We can\'t suggest settings. If it provides your Google Ads catalog, match its format manually or ask your developer.',
			'gla_not_connected'        => 'Installed but not connected to Merchant Center.',
			'feed_plugin_old'          => 'Product Catalog Feed Pro is older than ' . self::FEED_MIN_VERSION . '.',
			'addon_old'                => 'The add-on is below the minimum version for this check.',
			'addon_inactive'           => 'The add-on is not active, so its ID settings can\'t be compared with your feed.',
			'update_to_compare'        => 'Update it to compare.',
			'not_compared'             => '{name}: not compared. {why} {action}',
			'others_one'               => '{plugins} is active. PixelYourSite can\'t read its ID settings, so it is not compared. Compare its ID column with the patterns below.',
			'others_many'              => '{plugins} are active. PixelYourSite can\'t read their ID settings, so they are not compared. Compare their ID column with the patterns below.',
			'patterns_title'           => 'What PixelYourSite sends',
			'patterns_intro'           => 'For catalogs PixelYourSite can\'t read (other plugins, Google Sheets, external services, manual uploads): compare their ID column with these patterns.',
			'pattern_sku_rule'         => 'a variation without its own SKU',
			'pattern_fb4w'             => '{SKU}_{post ID}, or wc_post_id_{post ID} without a SKU',
			'wpml_note'                => 'WPML unified IDs are not covered by this check.',
			'suggest_title'            => 'Suggested settings for {module}',
			'suggest_cond_own'         => 'Only if {platform} uses this feed as its catalog. If not, ignore this.',
			'suggest_cond_gla'         => 'Only if Google Listings & Ads provides your Google Ads catalog.',
			'apply'                    => 'Apply suggested settings',
			'apply_note'               => 'Fills the fields below. Nothing is saved until you click Save Changes (bottom right).',
			'applied'                  => 'Applied to the fields below. Click Save Changes (bottom right) to keep them, or',
			'revert'                   => 'revert',
			'unsaved'                  => 'Applied, not saved yet →',
			'not_used'                 => '—',
			'not_used_note'            => 'applies once ID = Product SKU',
			'notice_title'             => 'Product IDs',
			'notice_one'               => 'The product IDs PixelYourSite sends to {platform} differ from the IDs in {source}. If {platform} uses that catalog, dynamic ads may not match your products.',
			'notice_many'              => 'The product IDs PixelYourSite sends to {platforms} differ from the IDs in your product feeds. If those feeds are your catalogs, dynamic ads may not match your products.',
			'notice_button'            => 'Review product IDs',
		);
	}

	public static function message( $key, array $vars = array() ) {
		$messages = self::messages();
		$text     = isset( $messages[ $key ] ) ? $messages[ $key ] : $key;
		$text     = __( $text, 'pys' );
		return empty( $vars ) ? $text : strtr( $text, $vars );
	}

	/* ------------------------------------------------------------------ */
	/* Labels                                                               */
	/* ------------------------------------------------------------------ */

	public static function sourceLabel( array $profile ) {
		switch ( $profile['source'] ) {
			case 'product_id':   return 'Product ID';
			case 'product_sku':  return 'Product SKU';
			case 'custom_field': return 'Custom field' . ( $profile['custom_key'] !== '' ? ' (' . $profile['custom_key'] . ')' : '' );
			case 'attribute':    return 'Attribute (' . $profile['custom_key'] . ')';
		}
		return (string) $profile['source'];
	}

	public static function ruleLabel( $rule ) {
		return 'variation_id' === $rule ? 'Use the variation ID' : 'Use the parent\'s SKU';
	}

	public static function parentsLabel( $mode ) {
		switch ( $mode ) {
			case 'parent_only': return 'Parent only';
			case 'both':        return 'Parent and variations';
		}
		return 'Variations only';
	}

	public static function fieldLabel( $key ) {
		switch ( $key ) {
			case 'woo_content_id':                  return 'ID';
			case WOO_CONTENT_ID_VARIATION_SKU_KEY:  return 'If a variation has no SKU of its own';
			case 'woo_content_id_prefix':           return 'ID prefix';
			case 'woo_content_id_suffix':           return 'ID suffix';
			case 'woo_variable_as_simple':          return 'Treat variable products like simple products';
			case 'woo_content_id_custom_field_name': return 'Custom field meta key';
		}
		return $key;
	}

	public static function fieldValueLabel( $key, $value ) {
		switch ( $key ) {
			case 'woo_content_id':
				return self::sourceLabel( array( 'source' => $value, 'custom_key' => '' ) );
			case WOO_CONTENT_ID_VARIATION_SKU_KEY:
				return self::ruleLabel( $value );
			case 'woo_variable_as_simple':
				return $value ? 'On' : 'Off';
		}
		return '' === (string) $value ? '(' . self::message( 'empty' ) . ')' : '<code>' . esc_html( $value ) . '</code>';
	}

	private static function quote( $name ) {
		return '“' . $name . '”';
	}

	private static function joinNames( array $names ) {
		$names = array_values( $names );
		if ( count( $names ) <= 1 ) {
			return isset( $names[0] ) ? $names[0] : '';
		}
		$last = array_pop( $names );
		return implode( ', ', $names ) . ' and ' . $last;
	}

	private static function valueText( $value ) {
		return '' === (string) $value ? self::message( 'empty' ) : '<code>' . esc_html( $value ) . '</code>';
	}

	/* ------------------------------------------------------------------ */
	/* Patterns                                                             */
	/* ------------------------------------------------------------------ */

	public static function patternFor( array $profile ) {
		if ( 'fb4w' === $profile['kind'] ) {
			return '<code>' . esc_html( self::message( 'pattern_fb4w' ) ) . '</code>';
		}
		switch ( $profile['source'] ) {
			case 'product_sku':  $base = '{SKU}'; break;
			case 'custom_field': $base = '{' . ( $profile['custom_key'] !== '' ? $profile['custom_key'] : 'custom field' ) . '}'; break;
			default:             $base = '{post ID}';
		}
		$out = '<code>' . esc_html( $profile['prefix'] . $base . $profile['suffix'] ) . '</code>';
		if ( 'product_sku' === $profile['source'] ) {
			$fallback = 'variation_id' === $profile['variation_sku'] ? '{variation ID}' : '{parent SKU}';
			$out     .= '; ' . esc_html( self::message( 'pattern_sku_rule' ) ) . ' → <code>' . esc_html( $profile['prefix'] . $fallback . $profile['suffix'] ) . '</code>';
		}
		return $out;
	}

	public static function platformPattern( $platform ) {
		$result = isset( self::results()[ $platform ] ) ? self::results()[ $platform ] : null;
		if ( ! $result || empty( $result['modules'] ) ) {
			return '';
		}
		$parts = array();
		foreach ( $result['modules'] as $slug ) {
			$profile = self::moduleProfile( $slug, $platform );
			if ( $profile ) {
				$parts[ $slug ] = self::patternFor( $profile );
			}
		}
		if ( count( array_unique( $parts ) ) === 1 ) {
			return reset( $parts );
		}
		$out = array();
		foreach ( $parts as $slug => $pattern ) {
			$out[] = esc_html( self::moduleNames()[ $slug ] ) . ': ' . $pattern;
		}
		return implode( '; ', $out );
	}

	/* ------------------------------------------------------------------ */
	/* Rendering helpers                                                    */
	/* ------------------------------------------------------------------ */

	private static function iconOk() {
		return '<span class="pys-mcp-status pys-mcp-status--ok" aria-label="Same"><svg viewBox="0 0 12 12" fill="none"><path d="M2.5 6.2l2.3 2.3 4.7-5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
	}

	private static function iconBad() {
		return '<span class="pys-mcp-status pys-mcp-status--fail" aria-label="Different"><svg viewBox="0 0 12 12" fill="none"><path d="M3.5 3.5l5 5M8.5 3.5l-5 5" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/></svg></span>';
	}

	private static function statusBadge( $status ) {
		$map = array(
			'match'      => array( 'pfc-ok',      self::message( 'status_match' ) ),
			'differ'     => array( 'pfc-bad',     self::message( 'status_differ' ) ),
			'notchecked' => array( 'pfc-neutral', self::message( 'status_notchecked' ) ),
			'reminder'   => array( 'pfc-neutral', self::message( 'status_reminder' ) ),
		);
		if ( ! isset( $map[ $status ] ) ) {
			return '';
		}
		return '<span class="pfc-status ' . esc_attr( $map[ $status ][0] ) . '">' . esc_html( $map[ $status ][1] ) . '</span>';
	}

	/**
	 * One sentence per differing field.
	 *
	 * @return string[] HTML lines
	 */
	public static function differenceLines( array $rows, $src_short, $who = null ) {
		$who   = $who ? $who : self::message( 'who_pys' );
		$lines = array();
		foreach ( $rows as $row ) {
			if ( $row['ok'] ) {
				continue;
			}
			switch ( $row['key'] ) {
				case 'source':
					$lines[] = self::message( 'diff_source', array( '{who}' => esc_html( $who ), '{a}' => esc_html( $row['pys'] ), '{b}' => esc_html( $row['src'] ), '{source}' => esc_html( $src_short ) ) );
					break;
				case 'prefix':
				case 'suffix':
					$lines[] = self::message( 'diff_' . $row['key'], array( '{who}' => esc_html( $who ), '{a}' => self::valueText( $row['pys'] ), '{b}' => self::valueText( $row['src'] ), '{source}' => esc_html( $src_short ) ) );
					break;
				case 'variation_sku':
					$lines[] = self::message( 'diff_variation_sku', array( '{who}' => esc_html( $who ), '{a}' => esc_html( $row['pys'] ), '{b}' => esc_html( $row['src'] ), '{source}' => esc_html( $src_short ) ) );
					break;
				case 'variable':
					$lines[] = self::message( 'diff_variable', array( '{note}' => esc_html( $row['note'] ) ) );
					break;
			}
		}
		// Bold the field name (text before the first colon).
		foreach ( $lines as $i => $line ) {
			$pos = strpos( $line, ':' );
			if ( false !== $pos ) {
				$lines[ $i ] = '<b>' . substr( $line, 0, $pos ) . ':</b>' . substr( $line, $pos + 1 );
			}
		}
		return $lines;
	}

	/**
	 * Settings PixelYourSite would need for a module to match an own feed, and
	 * the phrases that describe them. Only differing fields.
	 *
	 * @return array [ 'values' => [ option_key => value ], 'phrases' => string[], 'feed_phrases' => string[] ]
	 */
	public static function ownFeedSuggestion( array $pm, array $src_profile ) {
		$pys     = $pm['pys'];
		$values  = array();
		$phrases = array();
		$feed    = array();
		$diffs   = array();
		foreach ( $pm['rows'] as $row ) {
			if ( ! $row['ok'] ) {
				$diffs[] = $row['key'];
			}
		}
		$can_copy = in_array( $src_profile['source'], array( 'product_id', 'product_sku', 'custom_field' ), true );

		if ( in_array( 'source', $diffs, true ) ) {
			$values['woo_content_id'] = $src_profile['source'];
			$phrases[]                = 'ID = <b>' . esc_html( self::sourceLabel( $src_profile ) ) . '</b>';
			if ( 'custom_field' === $src_profile['source'] ) {
				$values['woo_content_id_custom_field_name'] = $src_profile['custom_key'];
			}
			$feed[] = 'ID = <b>' . esc_html( self::sourceLabel( $pys ) ) . '</b>';
		}
		if ( in_array( 'variation_sku', $diffs, true ) || ( in_array( 'source', $diffs, true ) && 'product_sku' === $src_profile['source'] ) ) {
			$values[ WOO_CONTENT_ID_VARIATION_SKU_KEY ] = $src_profile['variation_sku'];
			$phrases[] = '“' . esc_html( self::fieldLabel( WOO_CONTENT_ID_VARIATION_SKU_KEY ) ) . '” = <b>' . esc_html( self::ruleLabel( $src_profile['variation_sku'] ) ) . '</b>';
		}
		if ( in_array( 'variation_sku', $diffs, true ) || ( in_array( 'source', $diffs, true ) && 'product_sku' === $pys['source'] ) ) {
			$feed[] = 'variation rule = <b>' . esc_html( self::ruleLabel( $pys['variation_sku'] ) ) . '</b>';
		}
		if ( in_array( 'prefix', $diffs, true ) ) {
			$values['woo_content_id_prefix'] = $src_profile['prefix'];
			$phrases[]                       = 'prefix = ' . self::valueText( $src_profile['prefix'] );
			$feed[]                          = 'prefix = ' . self::valueText( $pys['prefix'] );
		}
		if ( in_array( 'suffix', $diffs, true ) ) {
			$values['woo_content_id_suffix'] = $src_profile['suffix'];
			$phrases[]                       = 'suffix = ' . self::valueText( $src_profile['suffix'] );
			$feed[]                          = 'suffix = ' . self::valueText( $pys['suffix'] );
		}
		if ( in_array( 'variable', $diffs, true ) ) {
			$values['woo_variable_as_simple'] = ! $pys['as_simple'];
			$phrases[] = '“' . esc_html( self::fieldLabel( 'woo_variable_as_simple' ) ) . '” <b>' . ( $pys['as_simple'] ? 'off' : 'on' ) . '</b>';
			$feed[]    = '“Variable products” = <b>' . esc_html( self::parentsLabel( 'both' ) ) . '</b>';
		}
		return array( 'can_copy' => $can_copy && ! empty( $values ), 'values' => $values, 'phrases' => $phrases, 'feed_phrases' => $feed );
	}

	/**
	 * Settings PixelYourSite would need for a module to match Google Listings & Ads.
	 */
	public static function glaSuggestion( array $pm ) {
		$values  = array();
		$phrases = array();
		foreach ( $pm['rows'] as $row ) {
			if ( $row['ok'] ) {
				continue;
			}
			switch ( $row['key'] ) {
				case 'source':
					$values['woo_content_id'] = 'product_id';
					$phrases[]                = 'ID = <b>Product ID</b>';
					break;
				case 'prefix':
					$values['woo_content_id_prefix'] = 'gla_';
					$phrases[]                       = 'prefix = <code>gla_</code>';
					break;
				case 'suffix':
					$values['woo_content_id_suffix'] = '';
					$phrases[]                       = 'suffix ' . self::message( 'empty' );
					break;
				case 'variable':
					$values['woo_variable_as_simple'] = false;
					$phrases[] = '“' . esc_html( self::fieldLabel( 'woo_variable_as_simple' ) ) . '” <b>off</b>';
					break;
			}
		}
		return array( 'can_copy' => ! empty( $values ), 'values' => $values, 'phrases' => $phrases );
	}

	private static function feedEditLink( array $feed ) {
		if ( ! function_exists( 'menu_page_url' ) ) {
			return '';
		}
		$url = add_query_arg( array( 'tab' => 1, 'edit' => (int) $feed['id'], '_wpnonce' => wp_create_nonce( 'wooffeed-nonce' ) ), menu_page_url( 'wpwoof-settings', false ) );
		if ( ! $url ) {
			return '';
		}
		return ' <a class="link link-small" href="' . esc_url( $url ) . '">(' . esc_html( self::message( 'edit_feed' ) ) . ')</a>';
	}

	private static function settingsLink( $slug ) {
		return ' <a class="link link-small pfc-open-settings" href="#" data-pfc-module="' . esc_attr( $slug ) . '">(' . esc_html( self::message( 'open_settings' ) ) . ')</a>';
	}

	/* ------------------------------------------------------------------ */
	/* Rendering: the check card                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Group lines with identical source profiles (own feeds with the same settings).
	 */
	private static function groupLines( array $lines ) {
		$groups = array();
		foreach ( $lines as $line ) {
			$sig = $line['src']['kind'] . '|' . wp_json_encode( $line['src']['profile'] );
			if ( ! isset( $groups[ $sig ] ) ) {
				$groups[ $sig ] = array( 'lines' => array(), 'ok' => $line['ok'] );
			}
			$groups[ $sig ]['lines'][] = $line;
		}
		return array_values( $groups );
	}

	private static function feedList( array $group ) {
		$names = array();
		foreach ( $group['lines'] as $line ) {
			$names[] = self::quote( $line['src']['feed']['name'] );
		}
		return implode( ', ', $names );
	}

	private static function groupLabel( array $group ) {
		$first = $group['lines'][0]['src'];
		if ( count( $group['lines'] ) === 1 ) {
			return esc_html( $first['name'] );
		}
		return esc_html( self::feedList( $group ) );
	}

	private static function renderFb4wQuestion( array $result ) {
		$feeds = array();
		foreach ( $result['lines'] as $line ) {
			if ( 'own' === $line['src']['kind'] ) {
				$feeds[] = self::quote( $line['src']['feed']['name'] );
			}
		}
		if ( empty( $feeds ) ) {
			$tail = self::message( 'fb4w_no_nothing' );
		} elseif ( 1 === count( $feeds ) ) {
			$tail = self::message( 'fb4w_no_feed', array( '{feed}' => $feeds[0] ) );
		} else {
			$tail = self::message( 'fb4w_no_feeds', array( '{feeds}' => implode( ', ', $feeds ) ) );
		}
		?>
		<div class="pfc-remind">
			<div class="pfc-remind-q"><?php echo esc_html( self::message( 'fb4w_q' ) ); ?></div>
			<div class="pfc-remind-opts">
				<div><b><?php echo esc_html( self::message( 'fb4w_yes' ) ); ?></b> <?php echo esc_html( self::message( 'fb4w_yes_action' ) ); ?> <a class="link link-small pfc-open-settings" href="#" data-pfc-module="fblogic"><?php echo esc_html( self::message( 'fb4w_open' ) ); ?></a></div>
				<div><b><?php echo esc_html( self::message( 'fb4w_no' ) ); ?></b> <?php echo esc_html( self::message( 'fb4w_no_action' ) ); ?> <?php echo esc_html( $tail ); ?></div>
			</div>
		</div>
		<?php
	}

	private static function renderFix( array $line, array $result, $compact_module = null ) {
		$src      = $line['src'];
		$platform = $result['platform'];
		$name     = $result['name'];

		if ( 'gla_custom' === $src['kind'] ) {
			echo '<p class="pfc-fix">' . esc_html( self::message( 'gla_custom' ) ) . '</p>';
			return;
		}

		if ( 'gla' === $src['kind'] ) {
			if ( $compact_module ) {
				$sugg = self::glaSuggestion( $line['per_module'][ $compact_module ] );
				self::renderSuggestionBox( $compact_module, $sugg['values'], self::message( 'suggest_cond_gla' ) );
			} else {
				$modules = array();
				$phrases = array();
				foreach ( $line['per_module'] as $slug => $pm ) {
					if ( $pm['ok'] ) {
						continue;
					}
					$modules[] = self::moduleNames()[ $slug ];
					$sugg      = self::glaSuggestion( $pm );
					$phrases   = array_unique( array_merge( $phrases, $sugg['phrases'] ) );
				}
				$links = '';
				foreach ( $line['per_module'] as $slug => $pm ) {
					if ( ! $pm['ok'] ) {
						$links .= self::settingsLink( $slug );
					}
				}
				echo '<p class="pfc-fix">' . self::message( 'gla_fix', array( '{modules}' => esc_html( implode( ', ', $modules ) ), '{settings}' => implode( ', ', $phrases ) ) ) . $links . '</p>';
			}
			echo '<p class="pfc-side">' . esc_html( self::message( 'gla_side' ) ) . '</p>';
			return;
		}

		// Own feed: the module whose settings differ (in the card) or the card's own module.
		$slug = $compact_module;
		if ( ! $slug ) {
			foreach ( $line['per_module'] as $m => $pm ) {
				if ( ! $pm['ok'] ) {
					$slug = $m;
					break;
				}
			}
			$slug = $slug ? $slug : $result['modules'][0];
		}
		$pm   = $line['per_module'][ $slug ];
		$sugg = self::ownFeedSuggestion( $pm, $src['profile'] );
		$feed = self::quote( $src['feed']['name'] );
		$edit = self::feedEditLink( $src['feed'] );

		if ( $sugg['can_copy'] ) {
			if ( $compact_module ) {
				self::renderSuggestionBox( $slug, $sugg['values'], self::message( 'suggest_cond_own', array( '{platform}' => $name ) ) );
			} else {
				$where = self::moduleNames()[ $slug ];
				echo '<p class="pfc-fix">' . self::message( 'fix_own', array( '{platform}' => esc_html( $name ), '{where}' => esc_html( $where ), '{settings}' => implode( ', ', $sugg['phrases'] ) ) ) . self::settingsLink( $slug ) . '</p>';
			}
			echo '<p class="pfc-side">' . self::message( 'fix_own_alt', array( '{feed}' => esc_html( $feed ) ) ) . $edit . '</p>';
			return;
		}

		echo '<p class="pfc-fix">' . self::message( 'fix_own_feed_only', array( '{platform}' => esc_html( $name ), '{feed}' => esc_html( $feed ), '{settings}' => implode( ', ', $sugg['feed_phrases'] ) ) ) . $edit . '</p>';
		echo '<p class="pfc-side">' . esc_html( self::message( 'fix_own_feed_only_side' ) ) . '</p>';
	}

	/**
	 * The in-card suggestion box: before → after for the fields that change,
	 * plus the Apply button. Values are carried as JSON for the JS; nothing is saved here.
	 */
	private static function renderSuggestionBox( $slug, array $values, $condition ) {
		static $box_index = 0;
		$module = self::module( $slug );
		if ( ! $module || empty( $values ) ) {
			return;
		}
		$rows = array();
		foreach ( $values as $key => $value ) {
			$current = $module->getOption( $key );
			$rule_becomes_active = WOO_CONTENT_ID_VARIATION_SKU_KEY === $key && 'product_sku' !== $module->getOption( 'woo_content_id' );
			if ( 'woo_variable_as_simple' === $key ) {
				$current = (bool) $current;
				if ( $current === (bool) $value ) {
					continue;
				}
			} elseif ( (string) $current === (string) $value && ! $rule_becomes_active ) {
				continue;
			}
			$before = $rule_becomes_active ? esc_html( self::message( 'not_used' ) ) . ' <span class="pfc-note">' . esc_html( self::message( 'not_used_note' ) ) . '</span>' : self::fieldValueLabel( $key, $current );
			$rows[] = '<tr><td>' . esc_html( self::fieldLabel( $key ) ) . '</td><td class="' . ( $rule_becomes_active ? 'pfc-cur pfc-cur-none' : 'pfc-cur' ) . '">' . $before . '</td><td class="pfc-arrow">→</td><td class="pfc-new">' . self::fieldValueLabel( $key, $value ) . '</td></tr>';
		}
		if ( empty( $rows ) ) {
			return;
		}
		?>
		<div class="pfc-suggest" data-pfc-module="<?php echo esc_attr( $slug ); ?>" data-pfc-box="<?php echo esc_attr( $slug . '-' . ( ++$box_index ) ); ?>" data-pfc-values="<?php echo esc_attr( wp_json_encode( $values ) ); ?>">
			<div class="pfc-suggest-title"><?php echo esc_html( self::message( 'suggest_title', array( '{module}' => self::moduleNames()[ $slug ] ) ) ); ?></div>
			<p class="pfc-side"><?php echo esc_html( $condition ); ?></p>
			<table class="pfc-suggest-table"><tbody><?php echo implode( '', $rows ); ?></tbody></table>
			<div class="pfc-suggest-actions">
				<button type="button" class="btn btn-primary pfc-apply"><?php echo esc_html( self::message( 'apply' ) ); ?></button>
				<span class="pfc-side"><?php echo esc_html( self::message( 'apply_note' ) ); ?></span>
			</div>
		</div>
		<?php
	}

	private static function renderRowBody( array $result ) {
		$name = $result['name'];

		if ( 'notchecked' === $result['status'] ) {
			foreach ( $result['blocked'] as $b ) {
				echo '<p class="pfc-line">' . esc_html( $b['name'] ) . ': ' . esc_html( trim( $b['why'] . ' ' . $b['action'] ) ) . '</p>';
			}
			return;
		}
		if ( 'reminder' === $result['status'] ) {
			self::renderFb4wQuestion( $result );
			return;
		}
		if ( $result['fb4w_active'] && ! $result['fb4w_selected'] ) {
			self::renderFb4wQuestion( $result );
		}

		$any_ok = false;
		foreach ( $result['lines'] as $line ) {
			if ( $line['ok'] ) {
				$any_ok = true;
			}
		}
		$matching_feeds = 0;
		foreach ( $result['lines'] as $line ) {
			if ( $line['ok'] && 'own' === $line['src']['kind'] ) {
				$matching_feeds++;
			}
		}

		foreach ( self::groupLines( $result['lines'] ) as $group ) {
			$line = $group['lines'][0];
			$src  = $line['src'];
			if ( 'fb4w' === $src['kind'] ) {
				echo '<p class="pfc-line">' . self::iconOk() . ' ' . esc_html( self::message( 'fb4w_selected' ) ) . '</p>';
				continue;
			}
			$label = self::groupLabel( $group );
			$many  = count( $group['lines'] ) > 1;
			if ( $group['ok'] ) {
				$text = $many
					? self::message( 'same_feeds', array( '{feeds}' => self::feedList( $group ) ) )
					: self::message( 'same', array( '{source}' => $src['name'] ) );
				echo '<p class="pfc-line">' . self::iconOk() . ' ' . esc_html( $text ) . '</p>';
				continue;
			}
			if ( $any_ok ) {
				if ( 'own' !== $src['kind'] ) {
					$text = self::message( 'other_ok_fixed', array( '{source}' => $label, '{platform}' => esc_html( $name ) ) );
				} else {
					$text = $many
						? self::message( 'other_ok_feeds', array( '{feeds}' => $label, '{platform}' => esc_html( $name ) ) )
						: self::message( 'other_ok', array( '{source}' => $label, '{platform}' => esc_html( $name ) ) );
				}
				echo '<p class="pfc-side">' . $text . ( 'own' === $src['kind'] ? self::feedEditLink( $src['feed'] ) : '' ) . '</p>';
				continue;
			}
			// Differs and nothing matches: difference lines + fix.
			$agree = count( $line['per_module'] ) === 1;
			if ( ! $agree ) {
				$sig = null;
				$agree = true;
				foreach ( $line['per_module'] as $pm ) {
					$s = wp_json_encode( array_map( function ( $r ) { return array( $r['ok'], $r['pys'] ); }, $pm['rows'] ) );
					if ( null !== $sig && $s !== $sig ) {
						$agree = false;
					}
					$sig = $s;
				}
			}
			$lines = array();
			if ( $agree ) {
				$pm    = reset( $line['per_module'] );
				$lines = self::differenceLines( $pm['rows'], $src['short'] );
			} else {
				foreach ( $line['per_module'] as $slug => $pm ) {
					$lines = array_merge( $lines, self::differenceLines( $pm['rows'], $src['short'], self::moduleNames()[ $slug ] ) );
				}
			}
			echo '<p class="pfc-line">' . self::iconBad() . ' ' . ( $many ? self::message( 'different_feeds', array( '{feeds}' => $label ) ) : self::message( 'different', array( '{source}' => $label ) ) ) . '</p>';
			if ( ! empty( $lines ) ) {
				echo '<ul class="pfc-diffs"><li>' . implode( '</li><li>', $lines ) . '</li></ul>';
			}
			self::renderFix( $line, $result );
		}

		foreach ( $result['blocked'] as $b ) {
			echo '<p class="pfc-side">' . esc_html( self::message( 'not_compared', array( '{name}' => $b['name'], '{why}' => $b['why'], '{action}' => $b['action'] ) ) ) . '</p>';
		}
		foreach ( $result['notes'] as $note ) {
			echo '<p class="pfc-side">' . esc_html( $note ) . '</p>';
		}
		if ( isWPMLActive() && in_array( $result['platform'], array( 'meta', 'google' ), true ) ) {
			foreach ( $result['modules'] as $slug ) {
				$module = self::module( $slug );
				if ( $module && $module->getOption( 'woo_wpml_unified_id', false ) ) {
					echo '<p class="pfc-side">' . esc_html( self::message( 'wpml_note' ) ) . '</p>';
					break;
				}
			}
		}
	}

	/**
	 * The "Product feed ID check" card. Call from the WooCommerce tab view.
	 */
	public static function renderCard() {
		if ( ! isWooCommerceActive() ) {
			return;
		}
		self::refreshState(); // one computation per render: refreshState() recomputes and warms the cache
		$results = self::results();

		$rows   = array();
		$counts = array( 'differ' => 0, 'match' => 0, 'notchecked' => 0, 'reminder' => 0 );
		foreach ( $results as $result ) {
			if ( 'none' === $result['status'] ) {
				continue;
			}
			$rows[] = $result;
			$counts[ $result['status'] ]++;
		}
		$rank = array( 'differ' => 0, 'notchecked' => 1, 'match' => 2, 'reminder' => 3 );
		usort( $rows, function ( $a, $b ) use ( $rank ) {
			return $rank[ $a['status'] ] - $rank[ $b['status'] ];
		} );

		if ( $counts['differ'] ) {
			$first = null;
			foreach ( $rows as $r ) {
				if ( 'differ' === $r['status'] ) {
					$first = $r;
					break;
				}
			}
			$badge = '<span class="pfc-status pfc-bad">' . esc_html( $counts['differ'] > 1
				? self::message( 'badge_differ_many', array( '{n}' => $counts['differ'] ) )
				: self::message( 'badge_differ_one', array( '{platform}' => $first['name'] ) ) ) . '</span>';
		} elseif ( $counts['match'] ) {
			$badge = '<span class="pfc-status pfc-ok">' . esc_html( self::message( 'badge_match' ) ) . '</span>';
		} elseif ( $counts['notchecked'] ) {
			$badge = '<span class="pfc-status pfc-neutral">' . esc_html( self::message( 'badge_notchecked' ) ) . '</span>';
		} else {
			$badge = '<span class="pfc-status pfc-neutral">' . esc_html( self::message( 'badge_none' ) ) . '</span>';
		}
		$open      = $counts['differ'] > 0;
		$video_url = apply_filters( 'pys_feed_id_check_video_url', '' );
		$others    = self::otherFeedPlugins();
		?>
		<!-- Product feed ID check -->
		<div class="card card-style5 pfc" id="pys-feed-id-check">
			<div class="card-header card-header-style3 d-flex justify-content-between align-items-center <?php echo $open ? 'header-opened' : ''; ?>">
				<div class="d-flex align-items-center pfc-head">
					<h4 class="secondary_heading_type2"><?php esc_html_e( 'Product feed ID check', 'pys' ); ?></h4>
					<?php echo $badge; ?>
				</div>
				<?php cardCollapseSettings(); ?>
			</div>
			<div class="card-body" style="display: <?php echo $open ? 'block' : 'none'; ?>;">
				<div class="gap-16">
					<p class="pfc-intro"><?php echo esc_html( self::message( 'intro' ) ); ?>
						<?php if ( $video_url ) : ?>
							<a class="link link-small" href="<?php echo esc_url( $video_url ); ?>" target="_blank"><?php echo esc_html( self::message( 'video' ) ); ?></a>
						<?php endif; ?>
					</p>

					<?php foreach ( $rows as $result ) :
						$row_open = in_array( $result['status'], array( 'differ', 'notchecked' ), true );
						if ( ! empty( $result['lines'] ) ) {
							$names = array();
							foreach ( $result['lines'] as $line ) {
								$names[] = $line['src']['name'];
							}
							$sub = implode( ', ', $names );
						} elseif ( 'reminder' === $result['status'] ) {
							$sub = self::message( 'sub_fb4w_active' );
						} else {
							$names = array();
							foreach ( $result['blocked'] as $b ) {
								$names[] = $b['name'];
							}
							$sub = implode( ', ', $names );
						}
						?>
						<div class="card card-style6 card-static pfc-row" id="pfc-row-<?php echo esc_attr( $result['platform'] ); ?>">
							<div class="card-header card-header-style2 d-flex justify-content-between align-items-center pfc-row-header <?php echo $row_open ? 'header-opened' : ''; ?>" tabindex="0" role="button" aria-expanded="<?php echo $row_open ? 'true' : 'false'; ?>">
								<div class="pfc-title">
									<img src="<?php echo esc_url( PYS_FREE_URL . '/dist/images/' . $result['logo'] ); ?>" alt="">
									<div>
										<div class="pfc-name"><?php echo esc_html( $result['name'] ); ?><?php if ( count( $result['modules'] ) > 1 ) : ?><span class="pfc-badge"><?php echo esc_html( implode( ' · ', array_map( function ( $m ) { return self::moduleNames()[ $m ]; }, $result['modules'] ) ) ); ?></span><?php endif; ?></div>
										<div class="pfc-sub"><?php echo esc_html( $sub ); ?></div>
									</div>
								</div>
								<div class="pfc-right"><?php echo self::statusBadge( $result['status'] ); ?><span class="pfc-chev" aria-hidden="true">&#9662;</span></div>
							</div>
							<div class="card-body" style="display: <?php echo $row_open ? 'block' : 'none'; ?>;">
								<div class="gap-16"><?php self::renderRowBody( $result ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>

					<?php if ( ! empty( $others ) ) : ?>
						<p class="pfc-side"><?php echo esc_html( self::message( count( $others ) > 1 ? 'others_many' : 'others_one', array( '{plugins}' => implode( ', ', $others ) ) ) ); ?></p>
					<?php endif; ?>

					<details class="pfc-ref" <?php echo empty( $rows ) ? 'open' : ''; ?>>
						<summary><span class="pfc-chev" aria-hidden="true">&#9662;</span><?php echo esc_html( self::message( 'patterns_title' ) ); ?></summary>
						<p class="pfc-side"><?php echo esc_html( self::message( 'patterns_intro' ) ); ?></p>
						<div class="gap-16 pfc-patterns">
							<?php foreach ( $results as $platform => $result ) :
								if ( empty( $result['modules'] ) ) {
									continue;
								}
								?>
								<div class="pfc-mod-row"><span><b><?php echo esc_html( $result['name'] ); ?></b></span><span><?php echo self::platformPattern( $platform ); ?></span></div>
							<?php endforeach; ?>
						</div>
					</details>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Rendering: in-card blocks                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Compact block for one module's ID settings card.
	 */
	public static function renderModuleBlock( $slug ) {
		if ( ! isWooCommerceActive() ) {
			return;
		}
		$platform = self::platformOfModule( $slug );
		if ( ! $platform ) {
			return;
		}
		$results = self::results();
		if ( ! isset( $results[ $platform ] ) ) {
			return;
		}
		$result = $results[ $platform ];
		if ( ! in_array( $slug, $result['modules'], true ) || in_array( $result['status'], array( 'none', 'reminder' ), true ) ) {
			return; // the Facebook reminder lives in the Integration card only
		}

		echo '<div class="pfc-incard">';
		if ( 'notchecked' === $result['status'] ) {
			foreach ( $result['blocked'] as $b ) {
				echo '<p class="pfc-line">' . esc_html( $b['name'] ) . ': ' . esc_html( trim( $b['why'] . ' ' . $b['action'] ) ) . '</p>';
			}
			echo '</div>';
			return;
		}

		$any_ok = false;
		foreach ( $result['lines'] as $line ) {
			if ( isset( $line['per_module'][ $slug ] ) && $line['per_module'][ $slug ]['ok'] ) {
				$any_ok = true;
			}
		}
		foreach ( $result['lines'] as $line ) {
			$src = $line['src'];
			if ( 'fb4w' === $src['kind'] ) {
				echo '<p class="pfc-line">' . self::iconOk() . ' ' . esc_html( self::message( 'fb4w_selected' ) ) . '</p>';
				continue;
			}
			$pm = $line['per_module'][ $slug ];
			if ( $pm['ok'] ) {
				echo '<p class="pfc-line">' . self::iconOk() . ' ' . esc_html( self::message( 'same', array( '{source}' => $src['name'] ) ) ) . '</p>';
				continue;
			}
			if ( $any_ok ) {
				echo '<p class="pfc-side">' . esc_html( self::message( 'own' === $src['kind'] ? 'other_ok' : 'other_ok_fixed', array( '{source}' => $src['name'], '{platform}' => $result['name'] ) ) ) . ( 'own' === $src['kind'] ? self::feedEditLink( $src['feed'] ) : '' ) . '</p>';
				continue;
			}
			$lines = self::differenceLines( $pm['rows'], $src['short'] );
			echo '<p class="pfc-line">' . self::iconBad() . ' ' . esc_html( self::message( 'different', array( '{source}' => $src['name'] ) ) ) . '</p>';
			if ( ! empty( $lines ) ) {
				echo '<ul class="pfc-diffs"><li>' . implode( '</li><li>', $lines ) . '</li></ul>';
			}
			self::renderFix( $line, $result, $slug );
		}
		foreach ( $result['notes'] as $note ) {
			echo '<p class="pfc-side">' . esc_html( $note ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Block for the Facebook for WooCommerce Integration card: the reminder or the green confirmation.
	 */
	public static function renderIntegrationBlock() {
		if ( ! isWooCommerceActive() || ! Facebook\Helpers\isFacebookForWooCommerceActive() ) {
			return;
		}
		echo '<div class="pfc-incard">';
		if ( ! Facebook\Helpers\isDefaultWooContentIdLogic() ) {
			echo '<p class="pfc-line">' . self::iconOk() . ' ' . esc_html( self::message( 'fb4w_selected' ) ) . '</p>';
		} else {
			echo '<p class="pfc-line">' . self::statusBadge( 'reminder' ) . ' ' . esc_html( self::message( 'fb4w_card_reminder' ) ) . '</p>';
		}
		echo '</div>';
	}
}
