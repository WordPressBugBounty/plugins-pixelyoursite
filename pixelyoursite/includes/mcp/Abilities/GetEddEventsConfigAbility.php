<?php
/**
 * `get_edd_events_config` — read-only snapshot of Easy Digital Downloads event
 * config (Free variant). Returns `{ available: false }` when EDD is off, else:
 * `funnel_events` (6 global funnel toggles) and `content_id_by_platform`
 * (source / prefix / suffix per active platform).
 *
 * Free scope: no lifecycle events, subscriptions, WPML or custom_field
 * content-id source (all Pro).
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

final class GetEddEventsConfigAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-edd-events-config';

	private const ADMIN_UI_PATH = 'PixelYourSite → Easy Digital Downloads';

	/** Funnel event name => core option key (all default-enabled). */
	public const FUNNEL_KEYS = array(
		'ViewContent'      => 'edd_view_content_enabled',
		'ViewCategory'     => 'edd_view_category_enabled',
		'AddToCart'        => 'edd_add_to_cart_enabled',
		'InitiateCheckout' => 'edd_initiate_checkout_enabled',
		'Purchase'         => 'edd_purchase_enabled',
		'RemoveFromCart'   => 'edd_remove_from_cart_enabled',
	);

	/** Per-platform content_id sub-keys (Free: source / prefix / suffix only). */
	public const CONTENT_ID_KEYS = array(
		'source' => 'edd_content_id',
		'prefix' => 'edd_content_id_prefix',
		'suffix' => 'edd_content_id_suffix',
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
		return 'PYS MCP — Get EDD Events Config';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Returns the Easy Digital Downloads event configuration for PixelYourSite (Free). Returns `{ "available": false }` immediately if EDD is not active. Sections: `funnel_events` (compact `{ all_enabled: true }` when all are in their default-enabled state, else the full per-event map: ViewContent, ViewCategory, AddToCart, InitiateCheckout, Purchase, RemoveFromCart — the GLOBAL master toggles), `platform_events` (the PER-PLATFORM event-firing matrix: for each active platform, which events it is set to fire — an event actually reaches a platform ONLY when BOTH its global toggle in `funnel_events` AND that platform toggle here are on. ALWAYS consult this before answering "is event X firing on platform Y". A value of `false` means the platform SUPPORTS the event but it is currently OFF — it IS available and you CAN enable it; do NOT report a `false` entry as "unavailable"/"unsupported". ONLY an event that is entirely ABSENT for a platform is unsupported (no admin switcher). NEVER infer platform reach from `funnel_events`/`all_enabled`. Write via `set_edd_event_config` `platform_event_toggles`) and `content_id_by_platform` (per active platform: `source` [`download_id` | `download_sku`], `prefix`, `suffix`; Google GA4/Tags share the unified `gatags` entry). Call this when the audit shows `edd_events: warning`, or when the user asks about EDD tracking (including whether a specific event fires on a specific pixel). Lifecycle events, subscriptions and the `custom_field` content-ID source are PixelYourSite Pro features and are not included here.';
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
					'description'          => 'Maps each funnel event to its admin-UI card label so you can translate the user\'s wording to the right `set_edd_event_config` arg (e.g. "Track product category pages" = `view_category`). Keyed by set-arg name => label.',
					'additionalProperties' => array( 'type' => 'string' ),
				),
				'platform_events'        => array(
					'type'                 => 'object',
					'description'          => 'PER-PLATFORM event-firing toggles: slug => { event_stem => bool }. An event fires for a platform only when BOTH its global `funnel_events` toggle AND this platform toggle are on.',
					'additionalProperties' => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'boolean' ) ),
				),
				'content_id_by_platform' => array(
					'type'                 => 'object',
					'description'          => 'Per-platform content_id values keyed by slug => { source, prefix, suffix }.',
					'additionalProperties' => array( 'type' => 'object' ),
				),
			),
		);
	}

	/**
	 * Build the read-only EDD config snapshot.
	 *
	 * @param mixed $input Validated args (none).
	 * @return array<string, mixed>
	 */
	public static function execute( $input ): array {
		if ( !function_exists( '\\PixelYourSite\\isEddActive' ) || !\PixelYourSite\isEddActive() ) {
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
			'platform_events'        => EventToggleMap::platformMatrix( 'edd' ),
			'content_id_by_platform' => self::contentIdByPlatform(),
		);
	}

	/**
	 * Compact when every funnel event is enabled (the default); else the full
	 * per-event boolean map.
	 *
	 * @param mixed $pys PYS settings instance.
	 * @return array<string, mixed>
	 */
	private static function funnelEvents( $pys ): array {
		// Only report an event when at least one enabled platform supports it.
		$available = EventToggleMap::availableEvents( 'edd' );
		$map       = array();
		$allOn     = true;
		foreach ( self::FUNNEL_KEYS as $event => $key ) {
			$stem = str_replace( array( 'edd_', '_enabled' ), '', $key );
			if ( !in_array( $stem, $available, true ) ) {
				continue;
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
	 * Admin-UI card label per funnel event, keyed by the `set_edd_event_config`
	 * arg name (event stem).
	 *
	 * @return array<string, string>
	 */
	private static function adminLabels(): array {
		$out = array();
		foreach ( self::FUNNEL_KEYS as $key ) {
			$stem         = str_replace( array( 'edd_', '_enabled' ), '', $key );
			$out[ $stem ] = EventToggleMap::adminLabel( $stem );
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
		$out = array();
		foreach ( EventToggleMap::enabledSettings() as $slug => $inst ) {
			if ( 'google_analytics' === $slug ) {
				continue;
			}
			if ( !method_exists( $inst, 'getOption' ) ) {
				continue;
			}
			$row = array();
			foreach ( self::CONTENT_ID_KEYS as $label => $key ) {
				$row[ $label ] = (string) $inst->getOption( $key, '' );
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
