<?php
/**
 * `get_custom_event` — full read-only detail of ONE PYS custom event by
 * `event_id` (Free variant): event-level settings, enabled platforms, triggers
 * (stable `trigger_index`) and conditions (`condition_index`), plus the
 * authoritative menus of what can be added. Field names mirror the admin so the
 * result can be written back via `set_custom_event`.
 *
 * Reads Free's CustomEvent via `__get($key)` (its private `$data`) + typed
 * getters; triggers are TriggerEvent objects, conditions are ConditionalEvent
 * objects.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\CustomEventConditionMap;
use PixelYourSite\MCP\CustomEventTriggerMap;
use PixelYourSite\MCP\CustomEventPlatformMap;

final class GetCustomEventAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-custom-event';

	private const ADMIN_UI_PATH = 'PixelYourSite → Events';

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
		return 'PYS MCP — Get Custom Event';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Returns the FULL configuration of one PixelYourSite (Free) custom event by `event_id` (from get_custom_events). Sections: event-level (`title`, `enabled`, `conditions_enabled`, `conditions_logic` — note trigger logic AND/OR, fire frequency and the fire-once time window are Pro and not reported); `platforms` — ONLY the platforms this event is enabled for (facebook, google_analytics, gtm, bing, pinterest, reddit — no TikTok / Google Ads in Free), each with its pixel selection (`pixel_ids` array for facebook/google_analytics/gtm, or scalar `pixel_id` for bing/pinterest/reddit; `all` = every pixel), `event_type` (+ `custom_event_type` when the type is a custom-name one), `params_enabled`, `params`, `custom_params`, and GTM dataLayer extras; `triggers` — each with a stable `trigger_index` (for point-wise edits via set_custom_event), `type`, `editable` (Free can edit page_visit/home_page/scroll_pos/post_type; other types are read-only — Pro), `params` and `rules`; `conditions` — each with `condition_index`, `type`, and `rule`/`value` or `device`/`user_role`. Also `available_condition_types`, `available_trigger_types` (each flagged `editable`) and `available_event_types` (per enabled platform, with each event\'s params) — the AUTHORITATIVE menus; answer "what can I add?" from these, never guess. An event whose event `params` is EMPTY accepts NO standard params (use custom_params). Free-writable triggers: page_visit, home_page, scroll_pos, post_type; conditions: url_filters, device, user_role — other trigger/condition types are Pro. Also returns `limits` — the Free caps (max ONE trigger and ONE condition per event) plus a `note` telling you, when the event already has a trigger, to REPLACE it (op:update) or DUPLICATE the event rather than offer to add a second (adding is rejected — multiple triggers are Pro). Consult `limits` BEFORE proposing add-vs-replace to the user. Read-only. Errors if no event has that id.';
	}

	/**
	 * Input JSON-Schema.
	 *
	 * @return array
	 */
	public static function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'event_id' ),
			'additionalProperties' => false,
			'properties'           => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'The custom event post ID (from get_custom_events).' ),
			),
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
			'required'   => array( 'event_id', 'title' ),
			'properties' => array(
				'event_id'                  => array( 'type' => 'integer' ),
				'title'                     => array( 'type' => 'string' ),
				'enabled'                   => array( 'type' => 'boolean' ),
				'feature_enabled'           => array( 'type' => 'boolean' ),
				'admin_ui_path'             => array( 'type' => 'string' ),
				'conditions_enabled'        => array( 'type' => 'boolean' ),
				'conditions_logic'          => array( 'type' => 'string' ),
				'limits'                    => array( 'type' => 'object', 'additionalProperties' => true ),
				'platforms'                 => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'object' ) ),
				'triggers'                  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'conditions'                => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'available_condition_types' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'available_trigger_types'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'available_event_types'     => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'object' ) ),
				'params_note'               => array( 'type' => 'string' ),
				'connectable_platforms'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'dynamic_parameters_note'   => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Build the full detail for one custom event.
	 *
	 * @param mixed $input Validated args: { event_id }.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( $input ) {
		$eventId = is_array( $input ) && isset( $input[ 'event_id' ] ) ? (int) $input[ 'event_id' ] : 0;

		if ( $eventId <= 0 || 'pys_event' !== get_post_type( $eventId ) ) {
			return new \WP_Error(
				'pys_mcp_custom_event_not_found',
				sprintf( 'No custom event with id %d. Call get_custom_events for the list of valid event_ids.', $eventId ),
				array( 'status' => 404 )
			);
		}
		if ( !class_exists( '\\PixelYourSite\\CustomEventFactory' ) ) {
			return new \WP_Error( 'pys_mcp_custom_events_unavailable', 'Custom events are not available on this site.', array( 'status' => 503 ) );
		}

		$event = \PixelYourSite\CustomEventFactory::getById( $eventId );
		if ( !is_object( $event ) ) {
			return new \WP_Error( 'pys_mcp_custom_event_not_found', sprintf( 'Custom event %d could not be loaded.', $eventId ), array( 'status' => 404 ) );
		}

		$pys            = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;
		$featureEnabled = null !== $pys && self::toBool( $pys->getOption( 'custom_events_enabled' ) );

		return array(
			'event_id'                  => $eventId,
			'title'                     => self::sanitiseUserString( $event->getTitle() ),
			'enabled'                   => (bool) $event->__get( 'enabled' ),
			'feature_enabled'           => $featureEnabled,
			'admin_ui_path'             => self::ADMIN_UI_PATH,
			'conditions_enabled'        => self::toBool( $event->__get( 'conditions_enabled' ) ),
			'conditions_logic'          => (string) ( $event->__get( 'conditions_logic' ) ?: 'AND' ),
			'limits'                    => self::freeLimits( $event ),
			'platforms'                 => self::platforms( $event ),
			'triggers'                  => self::triggers( $event ),
			'conditions'                => self::conditions( $event ),
			'available_condition_types' => self::availableConditionTypes(),
			'available_trigger_types'   => self::availableTriggerTypes(),
			'available_event_types'     => self::availableEventTypes( $event ),
			'params_note'               => 'Standard params (and each param\'s `required` flag) apply ONLY when the platform\'s params are enabled (the "Add Parameters" switcher / `params_enabled`). With params_enabled OFF the event fires with NO params. So changing an `event_type` to one whose params are marked `required` (e.g. facebook Purchase value/currency) does NOT force you to provide them — only set params if the user actually wants them sent. Writing any `params`/`custom_params` auto-enables the toggle; conversely, to change just the event_type, omit params entirely.',
			'connectable_platforms'     => self::connectablePlatforms(),
			'dynamic_parameters_note'   => 'Dynamic parameters — PYS tokens like [id], [title], [content_type], [categories], [tags], [total], [subtotal], [url_PARAM], [field_NAME] — are a PixelYourSite Pro feature (the admin "Dynamic Parameters" section is Pro-badged). In Free, param values are STATIC literals ONLY: a token is stored and sent VERBATIM as its literal text (e.g. the string "[id]"), NOT substituted at fire time. Do NOT set a param to a token in Free expecting substitution — say it requires Pro.',
		);
	}

	/**
	 * Per-platform blocks for the enabled platforms only.
	 *
	 * @param object $event Custom event.
	 * @return array<string, array<string, mixed>>
	 */
	private static function platforms( $event ): array {
		$out = array();
		foreach ( CustomEventPlatformMap::slugs() as $slug ) {
			$m = CustomEventPlatformMap::get( $slug );
			if ( !self::toBool( $event->__get( $m[ 'enabled' ] ) ) ) {
				continue;
			}

			$block = array( 'enabled' => true );

			$pid = $event->__get( $m[ 'pixel' ] );
			if ( !empty( $m[ 'pixel_array' ] ) ) {
				$block[ 'pixel_ids' ] = array_values( array_filter( (array) $pid, static function ( $v ) {
					return null !== $v && '' !== $v;
				} ) );
			} else {
				$block[ 'pixel_id' ] = (string) $pid;
			}

			$block[ 'event_type' ] = (string) ( $event->__get( $m[ 'event_type' ] ) ?? '' );
			if ( null !== $m[ 'custom_event_type' ] ) {
				$cet = $event->__get( $m[ 'custom_event_type' ] );
				if ( !empty( $cet ) ) {
					$block[ 'custom_event_type' ] = (string) $cet;
				}
			}
			if ( null !== $m[ 'params_enabled' ] ) {
				$block[ 'params_enabled' ] = self::toBool( $event->__get( $m[ 'params_enabled' ] ) );
			}
			if ( null !== $m[ 'params' ] ) {
				$p = $event->__get( $m[ 'params' ] );
				if ( is_array( $p ) ) {
					// Keep only params that actually carry a value (drop empty slots).
					$clean = array();
					foreach ( $p as $pk => $pv ) {
						$has = is_array( $pv ) ? ( '' !== (string) ( $pv[ 'value' ] ?? '' ) ) : ( null !== $pv && '' !== $pv );
						if ( $has ) {
							$clean[ $pk ] = $pv;
						}
					}
					if ( !empty( $clean ) ) {
						$block[ 'params' ] = $clean;
					}
				}
			}
			if ( null !== $m[ 'custom_params' ] ) {
				$cp = $event->__get( $m[ 'custom_params' ] );
				if ( !empty( $cp ) ) {
					$block[ 'custom_params' ] = $cp;
				}
			}
			if ( null !== $m[ 'track_single' ] ) {
				$block[ 'track_single_woo_data' ] = self::toBool( $event->__get( $m[ 'track_single' ] ) );
			} else {
				$block[ 'track_single_woo_data' ] = array( 'pro_locked' => true );
			}
			if ( null !== $m[ 'track_cart' ] ) {
				$block[ 'track_cart_woo_data' ] = self::toBool( $event->__get( $m[ 'track_cart' ] ) );
			} else {
				$block[ 'track_cart_woo_data' ] = array( 'pro_locked' => true );
			}
			if ( null !== $m[ 'conversion_label' ] ) {
				$cl = $event->__get( $m[ 'conversion_label' ] );
				if ( !empty( $cl ) ) {
					$block[ 'conversion_label' ] = (string) $cl;
				}
			}

			// GTM dataLayer extras.
			foreach ( CustomEventPlatformMap::extras( $slug ) as $field => $spec ) {
				$val               = $event->__get( $spec[ 'key' ] );
				$block[ $field ]   = 'bool' === $spec[ 'type' ] ? self::toBool( $val ) : (string) ( $val ?? '' );
			}

			$out[ $slug ] = $block;
		}

		return $out;
	}

	/**
	 * Triggers with a stable `trigger_index` (position), type, params and rules.
	 *
	 * @param object $event Custom event.
	 * @return array<int, array<string, mixed>>
	 */
	private static function triggers( $event ): array {
		$triggers = is_array( $event->getTriggers() ) ? $event->getTriggers() : array();
		$out      = array();
		foreach ( array_values( $triggers ) as $i => $trigger ) {
			if ( !is_object( $trigger ) || !method_exists( $trigger, 'getTriggerType' ) ) {
				continue;
			}
			$type = (string) $trigger->getTriggerType();
			$row  = array(
				'trigger_index' => $i,
				'type'          => $type,
				'label'         => CustomEventTriggerMap::label( $type ),
				'editable'      => CustomEventTriggerMap::isEditable( $type ),
			);

			// Params relevant to this type (delay, post_type_value, …).
			$params = array();
			foreach ( CustomEventTriggerMap::relevantParams( $type ) as $p ) {
				if ( method_exists( $trigger, 'getParam' ) ) {
					$v = $trigger->getParam( $p );
					if ( null !== $v && '' !== $v ) {
						$params[ $p ] = $v;
					}
				}
			}
			if ( !empty( $params ) ) {
				$row[ 'params' ] = $params;
			}

			// Rules (for rule-based types) — the raw rule list.
			$def = CustomEventTriggerMap::get( $type );
			if ( null !== $def && !empty( $def[ 'rules_key' ] ) && method_exists( $trigger, 'getTriggers' ) ) {
				$rules = array();
				foreach ( (array) $trigger->getTriggers() as $r ) {
					if ( is_array( $r ) ) {
						unset( $r[ 'undefined' ] );
						$rules[] = $r;
					}
				}
				if ( !empty( $rules ) ) {
					$row[ 'rules' ] = $rules;
				}
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Conditions with a stable `condition_index`.
	 *
	 * @param object $event Custom event.
	 * @return array<int, array<string, mixed>>
	 */
	private static function conditions( $event ): array {
		$conds = method_exists( $event, 'getConditions' ) && is_array( $event->getConditions() ) ? $event->getConditions() : array();
		$out   = array();
		foreach ( array_values( $conds ) as $i => $c ) {
			if ( !is_object( $c ) || !method_exists( $c, 'getConditionType' ) ) {
				continue;
			}
			$type  = (string) $c->getConditionType();
			$shape = CustomEventConditionMap::shape( $type );
			$row   = array(
				'condition_index' => $i,
				'type'            => $type,
				'label'           => CustomEventConditionMap::label( $type ),
			);
			if ( CustomEventConditionMap::SHAPE_DEVICE === $shape ) {
				$row[ 'device' ] = (string) $c->getParam( 'device' );
			} elseif ( CustomEventConditionMap::SHAPE_USER_ROLE === $shape ) {
				$row[ 'user_role' ] = (array) $c->getParam( 'user_role' );
			} else {
				$row[ 'rule' ]  = (string) $c->getParam( 'condition_rule' );
				$row[ 'value' ] = self::sanitiseUserString( $c->getParam( 'condition_value' ), 0 );
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Per-platform event-type menus for the ENABLED platforms.
	 *
	 * @param object $event Custom event.
	 * @return array<string, array<string, mixed>>
	 */
	private static function availableEventTypes( $event ): array {
		$out = array();
		foreach ( CustomEventPlatformMap::slugs() as $slug ) {
			$m = CustomEventPlatformMap::get( $slug );
			if ( !self::toBool( $event->__get( $m[ 'enabled' ] ) ) ) {
				continue;
			}
			$menu = CustomEventPlatformMap::eventTypeMenu( $slug );
			if ( !empty( $menu ) ) {
				$menu[ 'custom_params_supported' ] = null !== ( $m[ 'custom_params' ] ?? null );
				if ( !$menu[ 'custom_params_supported' ] ) {
					$menu[ 'custom_params_note' ] = sprintf(
						'`%1$s` has no custom parameters in Free (reddit is the only platform without them) — this is a per-platform limitation, NOT a Pro gate. Custom parameters ARE a Free feature on facebook, pinterest, bing, google_analytics and gtm. `%1$s` can still send the STANDARD params defined for its selected event_type (a custom-name event_type has none). To send an arbitrary name=value pair, use a platform that supports custom_params, or pick an event_type that defines that standard param.',
						$slug
					);
				}
				$out[ $slug ] = $menu;
			}
		}

		return $out;
	}

	/**
	 * Authoritative condition-type menu.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function availableConditionTypes(): array {
		$out = array();
		foreach ( CustomEventConditionMap::types() as $type ) {
			$shape    = CustomEventConditionMap::shape( $type );
			$editable = CustomEventConditionMap::isWritable( $type );
			$row      = array(
				'type'     => $type,
				'label'    => CustomEventConditionMap::label( $type ),
				'shape'    => $shape,
				'editable' => $editable,
			);
			if ( CustomEventConditionMap::SHAPE_RULE_VALUE === $shape ) {
				$row[ 'rules' ] = CustomEventConditionMap::ruleValues( $type );
			} elseif ( CustomEventConditionMap::SHAPE_DEVICE === $shape ) {
				$row[ 'device_values' ] = CustomEventConditionMap::DEVICE_VALUES;
			} elseif ( CustomEventConditionMap::SHAPE_USER_ROLE === $shape ) {
				$row[ 'roles' ] = self::availableUserRoles();
			}
			if ( !$editable ) {
				$row[ 'note' ] = 'Read-only in Free — this condition type requires PixelYourSite Pro to add/edit. Do NOT report it as non-existent.';
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Authoritative trigger-type menu (Free-writable flagged editable).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function availableTriggerTypes(): array {
		$out = array();
		foreach ( CustomEventTriggerMap::types() as $type ) {
			$row = array(
				'type'     => $type,
				'label'    => CustomEventTriggerMap::label( $type ),
				'editable' => CustomEventTriggerMap::isEditable( $type ),
			);
			$rules = CustomEventTriggerMap::ruleValues( $type );
			if ( !empty( $rules ) ) {
				$row[ 'rules' ] = $rules;
			}
			if ( CustomEventTriggerMap::supportsUrlWildcard( $type ) ) {
				$row[ 'all_pages_value' ] = '*';
				$row[ 'all_pages_hint' ]  = 'To match ALL pages/URLs use a rule item with value "*" (e.g. {rule:"contains", value:"*"}).';
			}
			if ( CustomEventTriggerMap::isEditable( $type ) ) {
				$params = array();
				$def    = CustomEventTriggerMap::get( $type );
				foreach ( (array) ( $def[ 'params' ] ?? array() ) as $p ) {
					$spec = CustomEventTriggerMap::paramSpec( $p );
					if ( empty( $spec ) ) {
						continue;
					}
					$entry = array_merge( array( 'name' => $p ), $spec );
					if ( 'post_type_value' === $p ) {
						$entry[ 'values' ] = self::availablePostTypes();
					}
					$params[] = $entry;
				}
				if ( !empty( $params ) ) {
					$row[ 'params' ] = $params;
				}
			} else {
				$row[ 'note' ] = 'Read-only in Free — this trigger type requires PixelYourSite Pro to add/edit.';
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Free single-item limits + how to act on them, so the caller offers
	 * REPLACE / DUPLICATE (never "add a second") when the event already has a
	 * trigger/condition. Surfaced in the read output the caller inspects BEFORE
	 * editing, so it decides correctly without hitting the write-time rejection.
	 *
	 * @param object $event Custom event.
	 * @return array<string, mixed>
	 */
	private static function freeLimits( $event ): array {
		$triggerCount   = is_array( $event->getTriggers() ) ? count( $event->getTriggers() ) : 0;
		$conditionCount = method_exists( $event, 'getConditions' ) && is_array( $event->getConditions() ) ? count( $event->getConditions() ) : 0;

		$note = 'PixelYourSite Free allows exactly ONE trigger and ONE condition per custom event (multiple triggers/conditions with AND/OR logic are Pro). ';
		$note .= $triggerCount >= 1
			? 'This event ALREADY has a trigger — to change what fires it, REPLACE the trigger via set_custom_event `triggers` op:update on the current trigger_index; do NOT offer to ADD a second trigger (the write is rejected). To ALSO fire on a separate independent trigger, duplicate the event (manage_custom_event action:duplicate) and change the copy\'s trigger.'
			: 'This event has no trigger yet — add exactly one.';

		return array(
			'max_triggers'                 => 1,
			'max_conditions'               => 1,
			'trigger_count'                => $triggerCount,
			'condition_count'              => $conditionCount,
			'note'                         => $note,
			'woo_data_switchers_pro_note'  => 'The per-platform custom-event switchers "Track WooCommerce product data on single product pages" (track_single_woo_data) and "Track WooCommerce cart data when possible" (track_cart_woo_data) are functional in Free ONLY for reddit. For facebook, pinterest, bing, google_analytics and gtm they EXIST but are Pro-locked (a locked upsell in the admin). When asked to enable one for a non-reddit platform, say plainly it requires PixelYourSite Pro — do NOT claim it does not exist, and do NOT tell the user to toggle it manually in wp-admin (they cannot; it is locked). Do not confuse these with the WooCommerce ViewContent funnel event ("Track product pages") in get_woo_events_config.',
			'event_fire_controls_pro_note' => 'These event-level firing controls EXIST in the admin but are Pro-locked in Free (a locked upsell — not settable manually either): "Fire this event only once in N hours" (per-event fire-once time window), trigger logic (AND/OR across multiple triggers) and fire frequency (once vs every time). They are absent from this output because Free has no such data. If asked for one, say plainly it requires PixelYourSite Pro — do NOT claim it does not exist and do NOT tell the user to set it in wp-admin.',
			'conversion_label_pro_note'    => 'A custom event\'s Google Ads "Conversion Label" (and the whole Enable-on-Google-Ads block) is PixelYourSite Pro — Free has no Google Ads platform, so it is not reported per platform. If asked, say it requires Pro; do not claim it does not exist.',
		);
	}

	/**
	 * Free platforms that can be ENABLED on an event now.
	 *
	 * @return array<int, string>
	 */
	private static function connectablePlatforms(): array {
		$out = array();
		foreach ( CustomEventPlatformMap::slugs() as $slug ) {
			if ( CustomEventPlatformMap::isConnectable( $slug ) ) {
				$out[] = $slug;
			}
		}

		return $out;
	}

	/**
	 * Site user-role slugs.
	 *
	 * @return array<int, string>
	 */
	private static function availableUserRoles(): array {
		if ( !function_exists( 'wp_roles' ) ) {
			return array();
		}
		$roles = wp_roles();

		return is_object( $roles ) && isset( $roles->roles ) ? array_values( array_map( 'strval', array_keys( (array) $roles->roles ) ) ) : array();
	}

	/**
	 * All registered post types (value + label) for the post_type trigger — mirrors
	 * the admin's post_type <select>, which lists EVERY registered type via
	 * get_post_types(null, 'objects') (public AND non-public, e.g. shop_order_refund
	 * "Refunds", edd_payment). Label lets the caller map the user's wording (a type
	 * is referred to by its label in the UI) to the slug stored as post_type_value.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function availablePostTypes(): array {
		if ( !function_exists( 'get_post_types' ) ) {
			return array();
		}

		$out = array();
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( !is_object( $type ) || '' === (string) $type->name ) {
				continue;
			}
			$out[] = array(
				'value' => (string) $type->name,
				'label' => (string) ( $type->label ?? $type->name ),
			);
		}

		return $out;
	}

	/**
	 * Coerce a mixed value to boolean.
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
