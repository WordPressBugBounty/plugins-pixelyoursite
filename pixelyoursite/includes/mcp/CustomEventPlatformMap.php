<?php
/**
 * Declarative registry of the per-platform data layout inside a custom event
 * (Free variant): one MAP row per platform with its `$data` keys, pixel-selection
 * shape, event-type source and custom-type marker. Mirrors the fields Free's
 * CustomEvent `$data` actually carries.
 *
 * Free custom-event platforms: facebook, pinterest, bing, reddit (flat event
 * lists) + google_analytics (`google_tags`) and gtm (GA-grouped). No TikTok, no
 * separate Google Ads / ga_ads.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class CustomEventPlatformMap {

	/** event-type list kind: flat per-platform list, or GA-grouped. */
	public const EVENTS_FLAT     = 'flat';
	public const EVENTS_GA_GROUP = 'ga_group';

	private const MAP = array(
		'facebook'         => array(
			'enabled' => 'facebook_enabled', 'pixel' => 'facebook_pixel_id', 'pixel_array' => true,
			'event_type' => 'facebook_event_type', 'custom_event_type' => 'facebook_custom_event_type', 'custom_values' => array( 'CustomEvent' ),
			'params_enabled' => 'facebook_params_enabled', 'params' => 'facebook_params', 'custom_params' => 'facebook_custom_params',
			'track_single' => null, 'track_cart' => null, 'conversion_label' => null,
			'pixel_fns' => array( '\\PixelYourSite\\Facebook' ), 'events_kind' => self::EVENTS_FLAT, 'events_source' => 'facebook',
		),
		'pinterest'        => array(
			'enabled' => 'pinterest_enabled', 'pixel' => 'pinterest_pixel_id', 'pixel_array' => false,
			'event_type' => 'pinterest_event_type', 'custom_event_type' => 'pinterest_custom_event_type', 'custom_values' => array( 'custom', 'partner_defined' ),
			'params_enabled' => 'pinterest_params_enabled', 'params' => 'pinterest_params', 'custom_params' => 'pinterest_custom_params',
			'track_single' => null, 'track_cart' => null, 'conversion_label' => null,
			'pixel_fns' => array( '\\PixelYourSite\\Pinterest' ), 'events_kind' => self::EVENTS_FLAT, 'events_source' => 'pinterest',
		),
		'bing'             => array(
			'enabled' => 'bing_enabled', 'pixel' => 'bing_pixel_id', 'pixel_array' => false,
			'event_type' => 'bing_event_type', 'custom_event_type' => 'bing_custom_event_type', 'custom_values' => array( 'Custom' ),
			'params_enabled' => 'bing_params_enabled', 'params' => 'bing_params', 'custom_params' => 'bing_custom_params',
			'track_single' => null, 'track_cart' => null, 'conversion_label' => null,
			'pixel_fns' => array( '\\PixelYourSite\\Bing' ), 'events_kind' => self::EVENTS_FLAT, 'events_source' => 'bing',
		),
		'reddit'           => array(
			'enabled' => 'reddit_enabled', 'pixel' => 'reddit_pixel_id', 'pixel_array' => false,
			'event_type' => 'reddit_event_type', 'custom_event_type' => 'reddit_custom_event_type', 'custom_values' => array( 'Custom' ),
			'params_enabled' => 'reddit_params_enabled', 'params' => 'reddit_params', 'custom_params' => null,
			'track_single' => 'reddit_track_single_woo_data', 'track_cart' => 'reddit_track_cart_woo_data', 'conversion_label' => null,
			'pixel_fns' => array( '\\PixelYourSite\\Reddit' ), 'events_kind' => self::EVENTS_FLAT, 'events_source' => 'reddit',
		),
		'google_analytics' => array(
			'enabled' => 'ga_ads_enabled', 'pixel' => 'ga_ads_pixel_id', 'pixel_array' => true,
			'event_type' => 'ga_ads_event_action', 'custom_event_type' => 'ga_ads_custom_event_action', 'custom_values' => array( '_custom', 'CustomEvent' ),
			'params_enabled' => null, 'params' => 'ga_ads_params', 'custom_params' => 'ga_ads_custom_params',
			'track_single' => null, 'track_cart' => null, 'conversion_label' => null,
			'pixel_fns' => array( '\\PixelYourSite\\GA' ), 'events_kind' => self::EVENTS_GA_GROUP, 'events_source' => null,
		),
		'gtm'              => array(
			'enabled' => 'gtm_enabled', 'pixel' => 'gtm_pixel_id', 'pixel_array' => true,
			'event_type' => 'gtm_event_action', 'custom_event_type' => 'gtm_custom_event_action', 'custom_values' => array( '_custom', 'CustomEvent' ),
			'params_enabled' => 'gtm_custom_params_enabled', 'params' => 'gtm_params', 'custom_params' => 'gtm_custom_params',
			'track_single' => null, 'track_cart' => null, 'conversion_label' => null,
			'pixel_fns' => array( '\\PixelYourSite\\GTM' ), 'events_kind' => self::EVENTS_GA_GROUP, 'events_source' => null,
		),
	);

	/**
	 * Platform-specific EXTRA event fields (GTM dataLayer options). Keyed by slug
	 * → mcp field name → { key (real $data key), type, label }.
	 */
	private const PLATFORM_EXTRAS = array(
		'gtm' => array(
			'automated_params'             => array( 'key' => 'gtm_automated_param',        'type' => 'bool',   'label' => 'Add the automated parameters in the dataLayer' ),
			'remove_custom_trigger_object' => array( 'key' => 'gtm_remove_customTrigger',   'type' => 'bool',   'label' => 'Remove the customTrigger object from the dataLayer after the event fires' ),
			'use_custom_object_name'       => array( 'key' => 'gtm_use_custom_object_name', 'type' => 'bool',   'label' => 'Use a custom value for the custom-parameters dataLayer object' ),
			'custom_object_name'           => array( 'key' => 'gtm_custom_object_name',     'type' => 'string', 'label' => 'Custom dataLayer object name (the GTM variable key); requires use_custom_object_name=true' ),
		),
	);

	/**
	 * Platform-specific extra fields (see PLATFORM_EXTRAS).
	 *
	 * @param string $slug Platform slug.
	 * @return array<string, array<string, string>>
	 */
	public static function extras( string $slug ): array {
		return self::PLATFORM_EXTRAS[ $slug ] ?? array();
	}

	/**
	 * All platform slugs.
	 *
	 * @return array<int, string>
	 */
	public static function slugs(): array {
		return array_keys( self::MAP );
	}

	/**
	 * Whether a slug is known.
	 *
	 * @param string $slug Platform slug.
	 * @return bool
	 */
	public static function has( string $slug ): bool {
		return isset( self::MAP[ $slug ] );
	}

	/**
	 * Full layout for a slug, or null.
	 *
	 * @param string $slug Platform slug.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $slug ): ?array {
		return self::MAP[ $slug ] ?? null;
	}

	/**
	 * Whether a slug's pixel selection is an array (multi) vs scalar (single).
	 *
	 * @param string $slug Platform slug.
	 * @return bool
	 */
	public static function pixelIsArray( string $slug ): bool {
		return !empty( self::MAP[ $slug ][ 'pixel_array' ] );
	}

	/**
	 * Valid pixel ids for a slug (excluding the `all` sentinel).
	 *
	 * @param string $slug Platform slug.
	 * @return array<int, string>
	 */
	public static function validPixelIds( string $slug ): array {
		$def = self::MAP[ $slug ] ?? null;
		if ( null === $def ) {
			return array();
		}
		$ids = array();
		foreach ( $def[ 'pixel_fns' ] as $fn ) {
			if ( !function_exists( $fn ) ) {
				continue;
			}
			$inst = $fn();
			if ( is_object( $inst ) && method_exists( $inst, 'getPixelIDs' ) ) {
				foreach ( (array) $inst->getPixelIDs() as $id ) {
					if ( is_string( $id ) && '' !== $id ) {
						$ids[] = $id;
					}
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether a platform can be ENABLED on an event now: addon active AND at
	 * least one pixel configured. Inactive add-on accessors return a stub whose
	 * `configured()` is false.
	 *
	 * @param string $slug Platform slug.
	 * @return bool
	 */
	public static function isConnectable( string $slug ): bool {
		$def = self::MAP[ $slug ] ?? null;
		if ( null === $def ) {
			return false;
		}
		foreach ( $def[ 'pixel_fns' ] as $fn ) {
			if ( !function_exists( $fn ) ) {
				continue;
			}
			$inst = $fn();
			if ( is_object( $inst ) && method_exists( $inst, 'configured' ) && $inst->configured() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Valid event-type values for a slug (for validating `event_type`), including
	 * the custom marker(s).
	 *
	 * @param string $slug Platform slug.
	 * @return array<int, string>
	 */
	public static function validEventTypes( string $slug ): array {
		$def = self::MAP[ $slug ] ?? null;
		if ( null === $def ) {
			return array();
		}
		$types = array();

		if ( self::EVENTS_FLAT === $def[ 'events_kind' ] ) {
			$getter = '\\PixelYourSite\\PYS_Event_Definitions::get_' . $def[ 'events_source' ] . '_events';
			if ( is_callable( $getter ) ) {
				$types = array_keys( (array) call_user_func( $getter ) );
			}
		} elseif ( self::EVENTS_GA_GROUP === $def[ 'events_kind' ] && is_callable( '\\PixelYourSite\\PYS_Event_Definitions::get_ga_events' ) ) {
			foreach ( (array) \PixelYourSite\PYS_Event_Definitions::get_ga_events() as $group ) {
				foreach ( (array) $group as $name => $_fields ) {
					$types[] = (string) $name;
				}
			}
		}

		$types = array_merge( $types, $def[ 'custom_values' ] );

		return array_values( array_unique( array_filter( $types, static function ( $v ) {
			return is_string( $v ) && '' !== $v;
		} ) ) );
	}

	/**
	 * Full event-type MENU for a slug with each event's params. GA / GTM are
	 * GROUPED by category; the others are flat.
	 *
	 * @param string $slug Platform slug.
	 * @return array<string, mixed>
	 */
	public static function eventTypeMenu( string $slug ): array {
		$def = self::MAP[ $slug ] ?? null;
		if ( null === $def ) {
			return array();
		}

		if ( self::EVENTS_FLAT === $def[ 'events_kind' ] ) {
			$getter = '\\PixelYourSite\\PYS_Event_Definitions::get_' . $def[ 'events_source' ] . '_events';
			if ( !is_callable( $getter ) ) {
				return array();
			}
			$list = array();
			foreach ( (array) call_user_func( $getter ) as $name => $params ) {
				if ( '' === (string) $name ) {
					continue;
				}
				$list[] = self::eventEntry( $slug, (string) $name, self::normalizeEventParams( $params ) );
			}

			return array( 'grouped' => false, 'event_types' => $list );
		}

		if ( self::EVENTS_GA_GROUP === $def[ 'events_kind' ] && is_callable( '\\PixelYourSite\\PYS_Event_Definitions::get_ga_events' ) ) {
			$groups = array();
			foreach ( (array) \PixelYourSite\PYS_Event_Definitions::get_ga_events() as $group => $events ) {
				$evs = array();
				foreach ( (array) $events as $name => $params ) {
					if ( '' === (string) $name ) {
						continue;
					}
					$evs[] = self::eventEntry( $slug, (string) $name, self::normalizeEventParams( $params ) );
				}
				$groups[] = array( 'group' => (string) $group, 'event_types' => $evs );
			}

			return array( 'grouped' => true, 'groups' => $groups );
		}

		return array();
	}

	/**
	 * One event-type menu entry: { value, params, custom_name? }.
	 *
	 * @param string $slug   Platform slug.
	 * @param string $value  Event type value.
	 * @param array  $params Normalised params.
	 * @return array<string, mixed>
	 */
	private static function eventEntry( string $slug, string $value, array $params ): array {
		$entry = array( 'value' => $value, 'params' => $params );
		if ( self::isCustomEventType( $slug, $value ) ) {
			$entry[ 'custom_name' ] = true;
		}

		return $entry;
	}

	/**
	 * Valid STANDARD param names for a (platform, event_type). [] for unknown /
	 * custom / param-less events.
	 *
	 * @param string $slug      Platform slug.
	 * @param string $eventType Event type value.
	 * @return array<int, string>
	 */
	public static function eventParamNames( string $slug, string $eventType ): array {
		$menu = self::eventTypeMenu( $slug );
		if ( empty( $menu ) ) {
			return array();
		}
		$pick = static function ( array $eventTypes ) use ( $eventType ) {
			foreach ( $eventTypes as $e ) {
				if ( ( $e[ 'value' ] ?? null ) === $eventType ) {
					return array_map( static function ( $p ) {
						return $p[ 'name' ];
					}, $e[ 'params' ] );
				}
			}

			return null;
		};
		if ( !empty( $menu[ 'grouped' ] ) ) {
			foreach ( $menu[ 'groups' ] as $g ) {
				$r = $pick( $g[ 'event_types' ] );
				if ( null !== $r ) {
					return $r;
				}
			}

			return array();
		}

		$r = $pick( $menu[ 'event_types' ] );

		return null !== $r ? $r : array();
	}

	/**
	 * First category group label containing an event_type (grouped platforms).
	 *
	 * @param string $slug      Platform slug.
	 * @param string $eventType Event type value.
	 * @return string
	 */
	public static function eventGroupFor( string $slug, string $eventType ): string {
		$menu = self::eventTypeMenu( $slug );
		if ( empty( $menu[ 'grouped' ] ) ) {
			return '';
		}
		foreach ( $menu[ 'groups' ] as $g ) {
			foreach ( $g[ 'event_types' ] as $e ) {
				if ( ( $e[ 'value' ] ?? null ) === $eventType ) {
					return (string) $g[ 'group' ];
				}
			}
		}

		return '';
	}

	/**
	 * ALL category group labels containing an event_type (grouped platforms).
	 *
	 * @param string $slug      Platform slug.
	 * @param string $eventType Event type value.
	 * @return array<int, string>
	 */
	public static function eventGroupsFor( string $slug, string $eventType ): array {
		$menu = self::eventTypeMenu( $slug );
		if ( empty( $menu[ 'grouped' ] ) ) {
			return array();
		}
		$out = array();
		foreach ( $menu[ 'groups' ] as $g ) {
			foreach ( $g[ 'event_types' ] as $e ) {
				if ( ( $e[ 'value' ] ?? null ) === $eventType ) {
					$out[] = (string) $g[ 'group' ];
					break;
				}
			}
		}

		return $out;
	}

	/**
	 * Whether an event_type value is the platform's "custom" marker.
	 *
	 * @param string $slug      Platform slug.
	 * @param string $eventType Event type value.
	 * @return bool
	 */
	public static function isCustomEventType( string $slug, string $eventType ): bool {
		$def = self::MAP[ $slug ] ?? null;

		return null !== $def && in_array( $eventType, (array) $def[ 'custom_values' ], true );
	}

	/**
	 * Normalise an event's param definitions to { name, input_type, required }.
	 *
	 * @param mixed $params Raw param definitions for one event.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalizeEventParams( $params ): array {
		$out = array();
		foreach ( (array) $params as $p ) {
			if ( !is_array( $p ) ) {
				continue;
			}
			$name = ( isset( $p[ 'label' ] ) && '' !== $p[ 'label' ] ) ? $p[ 'label' ] : ( $p[ 'name' ] ?? '' );
			$name = (string) $name;
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'name'       => $name,
				'input_type' => (string) ( $p[ 'input_type' ] ?? 'string' ),
				'required'   => !empty( $p[ 'required' ] ),
			);
		}

		return $out;
	}
}
