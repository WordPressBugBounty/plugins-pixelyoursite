<?php
/**
 * `get_woo_events_config` — read-only snapshot of WooCommerce event config
 * (Free variant). Returns `{ available: false }` when WooCommerce is off, else:
 * `funnel_events` (7 global funnel toggles), `add_to_cart_triggers` (where the
 * AddToCart event fires) and `content_id_by_platform` (source / prefix / suffix
 * per active platform — genuinely per-platform in PYS).
 *
 * Free scope: no lifecycle events, POAS, subscriptions, WPML, custom_field
 * content-id source, value settings or per-platform event matrix (all Pro).
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\Platforms;
use PixelYourSite\MCP\EventToggleMap;

final class GetWooEventsConfigAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-woo-events-config';

	private const ADMIN_UI_PATH = 'PixelYourSite → WooCommerce';

	/** Funnel event name => core option key (all default-enabled). */
	public const FUNNEL_KEYS = array(
		'ViewContent'      => 'woo_view_content_enabled',
		'ViewCategory'     => 'woo_view_category_enabled',
		'AddToCart'        => 'woo_add_to_cart_enabled',
		'InitiateCheckout' => 'woo_initiate_checkout_enabled',
		'Purchase'         => 'woo_purchase_enabled',
		'ViewCart'         => 'woo_view_cart_enabled',
		'RemoveFromCart'   => 'woo_remove_from_cart_enabled',
	);

	/** AddToCart firing-point sub-options (core, global). */
	public const ADD_TO_CART_TRIGGERS = array(
		'on_button_click'   => 'woo_add_to_cart_on_button_click',
		'on_cart_page'      => 'woo_add_to_cart_on_cart_page',
		'on_checkout_page'  => 'woo_add_to_cart_on_checkout_page',
	);

	/** Per-platform content_id sub-keys (Free: source / prefix / suffix only). */
	public const CONTENT_ID_KEYS = array(
		'source' => 'woo_content_id',
		'prefix' => 'woo_content_id_prefix',
		'suffix' => 'woo_content_id_suffix',
	);

	/**
	 * Ability ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return self::ID;
	}

	/**
	 * Display label.
	 *
	 * @return string
	 */
	public static function label(): string {
		return 'PYS MCP — Get WooCommerce Events Config';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Returns the WooCommerce event configuration for PixelYourSite (Free). Returns `{ "available": false }` immediately if WooCommerce is not active. Sections: `funnel_events` (compact `{ all_enabled: true }` when all are in their default-enabled state, else the full per-event map: ViewContent, ViewCategory, AddToCart, InitiateCheckout, Purchase, ViewCart, RemoveFromCart — these are the GLOBAL master toggles), `platform_events` (the PER-PLATFORM event-firing matrix: for each active platform, which events it is set to fire — an event actually reaches a platform ONLY when BOTH its global toggle in `funnel_events` AND that platform toggle here are on; only events a platform registers are listed and the sets differ per platform. ALWAYS consult this before answering "is event X firing on platform Y" — the global toggle alone is NOT enough. A value of `false` means the platform SUPPORTS the event but it is currently OFF — it IS available and you CAN enable it; do NOT report a `false` entry as "unavailable"/"unsupported". ONLY a platform/event pair that is entirely ABSENT from this map is unsupported (the admin shows no switcher). NEVER mark a platform as firing an event just because `funnel_events`/`all_enabled` is true. Example: ViewCart appears only under `google_analytics`, so Facebook does NOT fire ViewCart (absent = unsupported) even when the global ViewCart master is on. Write these via `set_woo_event_config` `platform_event_toggles`), `add_to_cart_triggers` (where the AddToCart event fires: on the add-to-cart button click, on the cart page, on the checkout page), and `content_id_by_platform` (per active platform: `source` [`product_id` | `product_sku`], `prefix`, `suffix`, and `variable_as_simple` [bool — "Treat variable products like simple products"; Free, write via set_woo_event_config `variable_as_simple`], plus `wpml_unified_id` [bool — present ONLY when the WPML plugin is active; the "WPML Unified ID logic" toggle; Free, write via `wpml_unified_id`; NB choosing the default language is a Pro-only option not exposed here] — content IDs are genuinely per-platform in PYS; Google GA4/Tags share the unified `gatags` entry). Call this when the audit shows `woo_events: warning`, or when the user asks about WooCommerce tracking (including whether a specific event fires on a specific pixel). Lifecycle events, POAS, subscriptions and the `custom_field` content-ID source are PixelYourSite Pro features and are not included here.';
	}

	/**
	 * Input JSON-Schema (no args).
	 *
	 * @return array
	 */
	public static function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
		);
	}

	/**
	 * Output JSON-Schema.
	 *
	 * @return array
	 */
	public static function outputSchema(): array {
		return array(
			'type'       => 'object',
			'required'   => array( 'available' ),
			'properties' => array(
				'available'              => array( 'type' => 'boolean' ),
				'admin_ui_path'          => array( 'type' => 'string' ),
				'funnel_events'          => array( 'type' => 'object' ),
				'admin_labels'           => array(
					'type'                 => 'object',
					'description'          => 'Maps each funnel event to its admin-UI card label so you can translate the user\'s wording to the right `set_woo_event_config` arg. E.g. "Track cart pages" = `view_cart` (the ViewCart event), "Track product category pages" = `view_category`. Keyed by set-arg name => label.',
					'additionalProperties' => array( 'type' => 'string' ),
				),
				'platform_events'        => array(
					'type'                 => 'object',
					'description'          => 'PER-PLATFORM event-firing toggles: slug => { event_stem => bool }. An event fires for a platform only when BOTH its global `funnel_events` toggle AND this platform toggle are on.',
					'additionalProperties' => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'boolean' ) ),
				),
				'add_to_cart_triggers'   => array( 'type' => 'object' ),
				'content_id_by_platform' => array(
					'type'                 => 'object',
					'description'          => 'Per-platform content_id values keyed by slug => { source, prefix, suffix }.',
					'additionalProperties' => array( 'type' => 'object' ),
				),
				'not_this_tool'          => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Build the read-only WooCommerce config snapshot.
	 *
	 * @param mixed $input Validated args (none).
	 * @return array<string, mixed>
	 */
	public static function execute( $input ): array {
		if ( !function_exists( '\\PixelYourSite\\isWooCommerceActive' ) || !\PixelYourSite\isWooCommerceActive() ) {
			return array( 'available' => false );
		}
		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;
		if ( null === $pys ) {
			return array( 'available' => false );
		}

		return array(
			'available'              => true,
			'admin_ui_path'          => self::ADMIN_UI_PATH,
			'funnel_events'          => self::funnelEvents( $pys ),
			'admin_labels'           => self::adminLabels(),
			'platform_events'        => EventToggleMap::platformMatrix( 'woo' ),
			'add_to_cart_triggers'   => self::addToCartTriggers( $pys ),
			'content_id_by_platform' => self::contentIdByPlatform(),
			'not_this_tool'          => 'This tool covers the GLOBAL WooCommerce funnel events (ViewContent = "Track product pages", etc.). It does NOT cover the per-platform CUSTOM-EVENT switchers "Track WooCommerce product data on single product pages" (track_single_woo_data) and "Track WooCommerce cart data when possible" (track_cart_woo_data) — those live INSIDE a custom event (get_custom_event / set_custom_event, per platform) and in Free are functional only for reddit (Pro for facebook/pinterest/bing/google_analytics/gtm). If the user asked to enable one of THOSE "for facebook" etc., this is the wrong tool — do not report the ViewContent funnel state; go to the custom event instead.',
		);
	}

	/**
	 * Compact when every funnel event is enabled (the default); else the full
	 * per-event boolean map so Claude sees exactly what's off.
	 *
	 * @param mixed $pys PYS settings instance.
	 * @return array<string, mixed>
	 */
	private static function funnelEvents( $pys ): array {
		$available = EventToggleMap::availableEvents( 'woo' );
		$map       = array();
		$allOn     = true;
		foreach ( self::FUNNEL_KEYS as $event => $key ) {
			$stem = str_replace( array( 'woo_', '_enabled' ), '', $key );
			if ( !in_array( $stem, $available, true ) ) {
				continue; // no active platform supports this event
			}
			$on            = self::toBool( $pys->getOption( $key ) );
			$map[ $event ] = $on;
			if ( !$on ) {
				$allOn = false;
			}
		}

		return ( $allOn && !empty( $map ) ) ? array( 'all_enabled' => true ) : $map;
	}

	/**
	 * Admin-UI card label per funnel event, keyed by the `set_woo_event_config`
	 * arg name (event stem). Lets the caller map user wording → the write arg.
	 *
	 * @return array<string, string>
	 */
	private static function adminLabels(): array {
		$out = array();
		foreach ( self::FUNNEL_KEYS as $key ) {
			$stem         = str_replace( array( 'woo_', '_enabled' ), '', $key );
			$out[ $stem ] = EventToggleMap::adminLabel( $stem );
		}

		return $out;
	}

	/**
	 * AddToCart firing points (core global sub-options).
	 *
	 * @param mixed $pys PYS settings instance.
	 * @return array<string, bool>
	 */
	private static function addToCartTriggers( $pys ): array {
		$out = array();
		foreach ( self::ADD_TO_CART_TRIGGERS as $label => $key ) {
			$out[ $label ] = self::toBool( $pys->getOption( $key ) );
		}

		return $out;
	}

	/**
	 * Per-platform content_id (source / prefix / suffix). GA is omitted — Google
	 * content_id is held by the unified `gatags` module (included as its own row).
	 *
	 * @return array<string, array<string, string>> slug => { source, prefix, suffix }.
	 */
	private static function contentIdByPlatform(): array {
		$wpmlActive = function_exists( '\\PixelYourSite\\isWPMLActive' ) && \PixelYourSite\isWPMLActive();
		$out        = array();
		foreach ( EventToggleMap::enabledSettings() as $slug => $inst ) {
			if ( 'google_analytics' === $slug ) {
				continue; // vestigial — Google content_id lives in `gatags`
			}
			if ( !method_exists( $inst, 'getOption' ) ) {
				continue;
			}
			$row = array();
			foreach ( self::CONTENT_ID_KEYS as $label => $key ) {
				$row[ $label ] = (string) $inst->getOption( $key, '' );
			}
			// Per-platform "Treat variable products like simple products" (Free).
			$row[ 'variable_as_simple' ] = self::toBool( $inst->getOption( 'woo_variable_as_simple' ) );
			// WPML Unified ID toggle (Free) — only meaningful when WPML is active.
			if ( $wpmlActive ) {
				$row[ 'wpml_unified_id' ] = self::toBool( $inst->getOption( 'woo_wpml_unified_id' ) );
			}
			$out[ $slug ] = $row;
		}

		return $out;
	}

	/**
	 * Coerce a mixed option value to a boolean.
	 *
	 * @param mixed $value Raw option value.
	 * @return bool
	 */
	private static function toBool( $value ): bool {
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
