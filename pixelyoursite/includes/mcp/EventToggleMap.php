<?php
/**
 * Per-platform WooCommerce / EDD event-firing toggles (Free variant).
 *
 * In PYS an ecommerce event fires for a platform only when BOTH the CORE global
 * master (`{domain}_{event}_enabled` on PYS()) AND that platform's OWN toggle
 * (`{domain}_{event}_enabled` on the platform Settings instance) are on. The
 * global funnel toggle is necessary but not sufficient — the per-platform layer
 * decides whether an event reaches a given pixel.
 *
 * Which platforms expose which events is NOT uniform (e.g. Woo `view_cart` is
 * Google-Analytics-only; Reddit only tracks a few events). The admin gates each
 * per-platform switcher behind `Platform()->enabled()` AND only renders a
 * switcher for events that platform actually supports. We mirror that with the
 * authoritative WOO_SUPPORT / EDD_SUPPORT maps (extracted from the admin views)
 * rather than reading option keys directly — some platforms carry vestigial
 * option keys (e.g. GTM `woo_view_cart_enabled`) that the admin never shows.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class EventToggleMap {

	/** Free WooCommerce funnel events (stems). */
	public const WOO_EVENTS = array(
		'view_content',
		'view_category',
		'add_to_cart',
		'initiate_checkout',
		'purchase',
		'view_cart',
		'remove_from_cart',
	);

	/** Free EDD funnel events (stems). */
	public const EDD_EVENTS = array(
		'view_content',
		'view_category',
		'add_to_cart',
		'initiate_checkout',
		'purchase',
		'remove_from_cart',
	);

	/**
	 * Free GLOBAL automatic (page-level) events. Pro-only automatic events
	 * (internal_link, outbound_link, video, tel_link, email_link, adsense,
	 * rage_click, video_speed) are excluded — not present in Free.
	 */
	public const AUTOMATIC_EVENTS = array(
		'form',
		'signup',
		'login',
		'download',
		'comment',
		'scroll',
		'time_on_page',
		'404',
		'search',
	);

	/**
	 * AUTHORITATIVE per-platform WooCommerce event support (which events the
	 * admin renders a switcher for). Extracted from html-main-woo.php. Note
	 * `view_cart` is Google-Analytics-only.
	 */
	private const WOO_SUPPORT = array(
		'facebook'         => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout', 'purchase', 'remove_from_cart' ),
		'google_analytics' => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout', 'purchase', 'view_cart', 'remove_from_cart' ),
		'pinterest'        => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout', 'purchase', 'remove_from_cart' ),
		'bing'             => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout', 'purchase' ),
		'reddit'           => array( 'view_content', 'add_to_cart', 'purchase' ),
		'gtm'              => array( 'view_content', 'add_to_cart', 'initiate_checkout', 'purchase' ),
		'openai'           => array( 'view_content', 'add_to_cart', 'initiate_checkout', 'purchase' ),
	);

	/**
	 * AUTHORITATIVE per-platform EDD event support. Extracted from
	 * html-main-edd.php. (Pinterest EDD tracks view_category/add_to_cart/
	 * initiate_checkout/remove_from_cart — no view_content/purchase.)
	 */
	private const EDD_SUPPORT = array(
		'facebook'         => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout', 'purchase', 'remove_from_cart' ),
		'google_analytics' => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout', 'purchase', 'remove_from_cart' ),
		'pinterest'        => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout', 'remove_from_cart' ),
		'bing'             => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout' ),
		'reddit'           => array( 'view_content', 'add_to_cart' ),
		'gtm'              => array( 'view_content', 'view_category', 'add_to_cart', 'initiate_checkout', 'purchase', 'remove_from_cart' ),
		'openai'           => array( 'view_content', 'add_to_cart', 'initiate_checkout', 'purchase' ),
	);

	/**
	 * Per-(domain, slug, event) option-KEY overrides where a platform stores a
	 * logical event under a non-standard key. Pinterest's EDD ViewContent is
	 * stored/rendered as `edd_page_visit_enabled` (the admin shows it as the
	 * PageVisit switcher inside the ViewContent card).
	 */
	private const KEY_OVERRIDES = array(
		'edd.pinterest.view_content' => 'edd_page_visit_enabled',
	);

	/**
	 * Admin-facing card label per event stem (the heading shown in
	 * PixelYourSite → WooCommerce / EDD). Lets the caller map the user's UI
	 * wording (e.g. "Track cart pages") to the right event stem / arg. Woo and
	 * EDD share these labels; `view_cart` is Woo-only.
	 */
	private const ADMIN_LABELS = array(
		'view_content'      => 'Track product pages',
		'view_category'     => 'Track product category pages',
		'add_to_cart'       => 'Track add to cart',
		'initiate_checkout' => 'Track the Checkout Page',
		'purchase'          => 'Track Purchases',
		'view_cart'         => 'Track cart pages',
		'remove_from_cart'  => 'Track remove from cart',
	);

	/** Writable target slugs for a per-platform toggle (plus the `all` fan-out). */
	public const TARGET_PLATFORMS = array(
		'facebook',
		'google_analytics',
		'pinterest',
		'bing',
		'reddit',
		'gtm',
		'openai',
		'all',
	);

	/**
	 * Canonical event stems for a domain.
	 *
	 * @param string $domain `woo` or `edd`.
	 * @return array<int, string>
	 */
	public static function events( string $domain ): array {
		if ( 'edd' === $domain ) {
			return self::EDD_EVENTS;
		}
		if ( 'automatic' === $domain ) {
			return self::AUTOMATIC_EVENTS;
		}

		return self::WOO_EVENTS;
	}

	/**
	 * Real option key for a (domain, event stem[, platform slug]). Most events
	 * use `{domain}_{event}_enabled`, but a few platforms store a logical event
	 * under a different key (see KEY_OVERRIDES) — pass `$slug` to resolve those.
	 *
	 * @param string $domain `woo` or `edd`.
	 * @param string $event  Event stem (e.g. `view_content`).
	 * @param string $slug   Platform slug (optional; needed for overrides).
	 * @return string e.g. `woo_view_content_enabled` / `edd_page_visit_enabled`.
	 */
	public static function key( string $domain, string $event, string $slug = '' ): string {
		if ( '' !== $slug && isset( self::KEY_OVERRIDES[ $domain . '.' . $slug . '.' . $event ] ) ) {
			return self::KEY_OVERRIDES[ $domain . '.' . $slug . '.' . $event ];
		}
		if ( 'automatic' === $domain ) {
			return 'automatic_event_' . $event . '_enabled';
		}

		return $domain . '_' . $event . '_enabled';
	}

	/**
	 * Whether a platform instance REGISTERS an automatic event (Free proxy for
	 * Pro's issetOption): the per-platform key returns non-null. Unregistered
	 * automatic events return null on the module (e.g. Facebook has no `404`).
	 *
	 * @param object $settings Platform Settings instance.
	 * @param string $event    Automatic event stem.
	 * @return bool
	 */
	public static function automaticRegisters( $settings, string $event ): bool {

		if ( !is_object( $settings ) || !method_exists( $settings, 'getOptionKeys' ) ) {
			return false;
		}

		return in_array( self::key( 'automatic', $event ), $settings->getOptionKeys(), true );
	}

	/**
	 * Is `$event` a recognised stem for `$domain`?
	 *
	 * @param string $domain `woo` or `edd`.
	 * @param string $event  Event stem.
	 * @return bool
	 */
	public static function isKnownEvent( string $domain, string $event ): bool {
		return in_array( $event, self::events( $domain ), true );
	}

	/**
	 * Admin-facing card label for an event stem (e.g. `view_cart` → "Track cart
	 * pages"). Falls back to the stem when unknown.
	 *
	 * @param string $stem Event stem.
	 * @return string
	 */
	public static function adminLabel( string $stem ): string {
		return self::ADMIN_LABELS[ $stem ] ?? $stem;
	}

	/**
	 * Authoritative support map for a domain.
	 *
	 * @param string $domain `woo` or `edd`.
	 * @return array<string, array<int, string>>
	 */
	private static function supportMap( string $domain ): array {
		return 'edd' === $domain ? self::EDD_SUPPORT : self::WOO_SUPPORT;
	}

	/**
	 * Does platform `$slug` expose `$event` for `$domain` (per the admin)? Uses
	 * the authoritative support map, NOT the raw option key (some platforms
	 * carry vestigial keys the admin never shows).
	 *
	 * @param string $domain `woo` or `edd`.
	 * @param string $slug   Platform slug.
	 * @param string $event  Event stem.
	 * @return bool
	 */
	public static function platformSupports( string $domain, string $slug, string $event ): bool {
		return in_array( $event, self::supportMap( $domain )[ $slug ] ?? array(), true );
	}

	/**
	 * Active AND enabled platform Settings instances, keyed by slug. The admin
	 * gates each per-platform event switcher behind `Platform()->enabled()`, so
	 * a platform that has a pixel id but whose master switch is OFF shows no
	 * event toggles — we mirror that.
	 *
	 * @return array<string, object>
	 */
	public static function enabledSettings(): array {
		$out = array();
		foreach ( Platforms::activeSettings() as $slug => $settings ) {
			if ( method_exists( $settings, 'enabled' ) && !$settings->enabled() ) {
				continue;
			}
			$out[ $slug ] = $settings;
		}

		return $out;
	}

	/**
	 * Per-platform matrix of supported event toggles and their current state.
	 * Only ENABLED platforms and only events the admin actually exposes for each
	 * platform are included (authoritative support map).
	 *
	 * @param string $domain `woo` or `edd`.
	 * @return array<string, array<string, bool>> slug => { stem => enabled }.
	 */
	public static function platformMatrix( string $domain ): array {
		$matrix = array();
		foreach ( self::enabledSettings() as $slug => $settings ) {
			if ( !method_exists( $settings, 'getOption' ) ) {
				continue;
			}
			$row = array();
			foreach ( self::events( $domain ) as $event ) {
				if ( !self::platformSupports( $domain, $slug, $event ) ) {
					continue;
				}
				$row[ $event ] = self::toBool( $settings->getOption( self::key( $domain, $event, $slug ) ) );
			}
			if ( !empty( $row ) ) {
				$matrix[ $slug ] = $row;
			}
		}

		return $matrix;
	}

	/**
	 * Event stems AVAILABLE on this site — supported by at least one ENABLED
	 * platform. Platform-specific events (e.g. Woo `view_cart`, GA-only) drop
	 * out when their only supporting platform is disabled.
	 *
	 * @param string $domain `woo` or `edd`.
	 * @return array<int, string>
	 */
	public static function availableEvents( string $domain ): array {
		$stems = array();
		foreach ( self::enabledSettings() as $slug => $settings ) {
			foreach ( self::supportMap( $domain )[ $slug ] ?? array() as $stem ) {
				if ( self::isKnownEvent( $domain, $stem ) ) {
					$stems[ $stem ] = true;
				}
			}
		}

		return array_keys( $stems );
	}

	/**
	 * Coerce a mixed option value to bool.
	 *
	 * @param mixed $value Raw option value.
	 * @return bool
	 */
	public static function toBool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return '1' === $value || 'true' === strtolower( $value );
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		return false;
	}
}
