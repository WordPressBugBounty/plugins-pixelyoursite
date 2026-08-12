<?php
/**
 * `get_custom_events` — read-only list of PYS custom events (Free variant).
 *
 * One row per custom event (post type `pys_event`) with id, title, enabled
 * state, the networks it actually fires to, trigger/condition counts and fire
 * settings. Use `get_custom_event` for full per-event detail. The feature master
 * gate (`custom_events_enabled`) is reported too.
 *
 * Free networks: facebook, google_tags (GA), gtm, bing, pinterest, reddit (no
 * TikTok, no separate Google Ads). Reading LABELS every trigger type (even
 * Pro-only ones preserved on an event); WRITING is limited to the Free triggers.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class GetCustomEventsAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-custom-events';

	private const ADMIN_UI_PATH = 'PixelYourSite → Events';

	/** Readable labels for any trigger type an event may carry (read-only display). */
	private const TRIGGER_LABELS = array(
		'page_visit'        => 'Page visit',
		'home_page'         => 'Home page',
		'scroll_pos'        => 'Page Scroll',
		'post_type'         => 'Post type',
		'add_to_cart'       => 'WooCommerce add to cart',
		'purchase'          => 'WooCommerce purchase',
		'number_page_visit' => 'Number of Page Visits',
		'url_click'         => 'Click on HTML link',
		'css_click'         => 'Click on CSS selector',
		'css_mouseover'     => 'Mouse over CSS selector',
		'video_view'        => 'Embedded Video View',
		'email_link'        => 'Email Link',
	);

	/** Per-platform accessor + event-enabled method (Free platforms only). */
	private const PLATFORM_FNS = array(
		'facebook'  => '\\PixelYourSite\\Facebook',
		'gtm'       => '\\PixelYourSite\\GTM',
		'bing'      => '\\PixelYourSite\\Bing',
		'pinterest' => '\\PixelYourSite\\Pinterest',
		'reddit'    => '\\PixelYourSite\\Reddit',
	);

	private const PLATFORM_EVENT_METHODS = array(
		'facebook'  => 'isFacebookEnabled',
		'gtm'       => 'isGTMEnabled',
		'bing'      => 'isBingEnabled',
		'pinterest' => 'isPinterestEnabled',
		'reddit'    => 'isRedditEnabled',
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
		return 'PYS MCP — Get Custom Events';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Lists the PixelYourSite CUSTOM EVENTS configured on this site (PixelYourSite Free) — user-defined events that fire to one or more pixels when their triggers match. Lightweight overview (one row per event); call `get_custom_event` with an `event_id` from here for the full per-event detail. Top-level `feature_enabled` reflects the master switch `custom_events_enabled` — when false NO custom event fires regardless of its own `enabled` state, so surface that first. Each `events[]` row has: `event_id` (use it for get_custom_event / set_custom_event / manage_custom_event), `title`, `enabled` (the event\'s own active/paused state), `networks` (the networks this event ACTUALLY fires to: a network is listed only when the platform is globally enabled AND has pixels AND the event targets it. Free networks: facebook, google_tags (Google Analytics `G-…`), gtm, bing, pinterest, reddit — no TikTok, no separate Google Ads), `trigger_count`, `trigger_types` (readable labels, one per trigger in order), `condition_count` and `conditions_enabled`. An event with `trigger_count: 0` never fires. (Trigger logic AND/OR, fire frequency, and the "fire only once in N hours" time window are PixelYourSite Pro and are not reported.) Read-only. Call this when the user asks what custom events exist, or to find an event before editing it.';
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
			'required'   => array( 'feature_enabled', 'events' ),
			'properties' => array(
				'feature_enabled' => array(
					'type'        => 'boolean',
					'description' => 'Master switch `custom_events_enabled`. When false, NO custom event fires regardless of its own enabled state.',
				),
				'admin_ui_path'   => array( 'type' => 'string' ),
				'events'          => array(
					'type'        => 'array',
					'description' => 'One row per custom event. Use `event_id` for the detail / write tools.',
					'items'       => array( 'type' => 'object' ),
				),
			),
		);
	}

	/**
	 * Build the custom-events overview list.
	 *
	 * @param mixed $input Validated args (none).
	 * @return array<string, mixed>
	 */
	public static function execute( $input ): array {
		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;

		$featureEnabled = null !== $pys && self::toBool( $pys->getOption( 'custom_events_enabled' ) );

		$events = array();
		if ( class_exists( '\\PixelYourSite\\CustomEventFactory' ) ) {
			foreach ( (array) \PixelYourSite\CustomEventFactory::get( 'any' ) as $event ) {
				if ( is_object( $event ) ) {
					$events[] = self::summarize( $event );
				}
			}
		}

		return array(
			'feature_enabled' => $featureEnabled,
			'admin_ui_path'   => self::ADMIN_UI_PATH,
			'events'          => $events,
		);
	}

	/**
	 * Compact summary of one custom event.
	 *
	 * @param \PixelYourSite\CustomEvent $event Event model.
	 * @return array<string, mixed>
	 */
	private static function summarize( $event ): array {
		$triggers   = is_array( $event->getTriggers() ) ? $event->getTriggers() : array();
		$conditions = method_exists( $event, 'getConditions' ) && is_array( $event->getConditions() )
			? $event->getConditions() : array();

		$triggerTypes = array();
		foreach ( $triggers as $trigger ) {
			if ( is_object( $trigger ) && method_exists( $trigger, 'getTriggerType' ) ) {
				$slug           = (string) $trigger->getTriggerType();
				$triggerTypes[] = self::TRIGGER_LABELS[ $slug ] ?? $slug;
			}
		}

		$row = array(
			'event_id'           => (int) $event->getPostId(),
			'title'              => self::sanitiseUserString( $event->getTitle() ),
			'enabled'            => (bool) $event->__get( 'enabled' ),
			'networks'           => self::availableNetworks( $event ),
			'trigger_count'      => count( $triggers ),
			'trigger_types'      => $triggerTypes,
			'condition_count'    => count( $conditions ),
			'conditions_enabled' => self::toBool( $event->__get( 'conditions_enabled' ) ),
		);

		return $row;
	}

	/**
	 * Networks an event ACTUALLY fires to — a network counts only when the
	 * platform is globally enabled AND has pixels AND the event targets it.
	 * `google_tags` = GA present + unify-analytics on.
	 *
	 * @param \PixelYourSite\CustomEvent $event Event model.
	 * @return array<int, string> Network slugs.
	 */
	private static function availableNetworks( $event ): array {
		$nets = array();

		if ( self::platformLive( self::PLATFORM_FNS[ 'facebook' ] ) && self::eventOn( $event, 'isFacebookEnabled' ) ) {
			$nets[] = 'facebook';
		}

		// Google Tags (GA) — unify-analytics gate + a GA `G-…` present.
		if ( self::platformLive( '\\PixelYourSite\\GA' ) && self::eventOn( $event, 'isUnifyAnalyticsEnabled' )
		     && self::eventOn( $event, 'isGoogleAnalyticsPresent' ) ) {
			$nets[] = 'google_tags';
		}

		if ( self::platformLive( self::PLATFORM_FNS[ 'gtm' ] ) && self::eventOn( $event, 'isGTMEnabled' ) && self::eventOn( $event, 'isGTMPresent' ) ) {
			$nets[] = 'gtm';
		}

		foreach ( array( 'bing', 'pinterest', 'reddit' ) as $slug ) {
			if ( self::platformLive( self::PLATFORM_FNS[ $slug ] ) && self::eventOn( $event, self::PLATFORM_EVENT_METHODS[ $slug ] ) ) {
				$nets[] = $slug;
			}
		}

		return $nets;
	}

	/**
	 * Platform is globally enabled and has at least one pixel.
	 *
	 * @param string $fn Global settings accessor.
	 * @return bool
	 */
	private static function platformLive( string $fn ): bool {
		if ( !function_exists( $fn ) ) {
			return false;
		}
		$inst = $fn();

		return is_object( $inst ) && method_exists( $inst, 'enabled' ) && $inst->enabled()
		       && method_exists( $inst, 'getPixelIDs' ) && !empty( $inst->getPixelIDs() );
	}

	/**
	 * Call a boolean CustomEvent method if it exists.
	 *
	 * @param \PixelYourSite\CustomEvent $event  Event model.
	 * @param string                     $method Method name.
	 * @return bool
	 */
	private static function eventOn( $event, string $method ): bool {
		return method_exists( $event, $method ) && (bool) $event->$method();
	}

	/**
	 * Coerce a mixed value to a boolean.
	 *
	 * @param mixed $value Raw value.
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
