<?php
/**
 * Reverse serializer (Free variant): a stored CustomEvent → the admin-POST-shaped
 * `$args` that CustomEvent::update() consumes. Required because Free's update()
 * fully REBUILDS every platform block, trigger and condition from `$args` — any
 * key it reads but the caller omits is reset to its default. So the MCP write path
 * is read → toArgs() (full snapshot) → apply the partial patch → update().
 *
 * Free's data model has no getAllData(); values are read one key at a time via
 * CustomEvent::__get(). Platform keys come from CustomEventPlatformMap; triggers
 * and conditions are reconstructed from their TriggerEvent / ConditionalEvent
 * objects into the exact shape update()'s parser expects.
 *
 * NOTE: Free's update() only rebuilds the 4 writable trigger types (page_visit,
 * home_page, scroll_pos, post_type) and keeps a SINGLE condition — the set ability
 * blocks writes to events carrying other trigger types (which update() would drop)
 * before ever calling update().
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class CustomEventArgsSerializer {

	/** Map-row fields whose value is a real `$data` key to snapshot. */
	private const KEY_FIELDS = array(
		'enabled', 'pixel', 'event_type', 'custom_event_type',
		'params_enabled', 'params', 'custom_params', 'track_single', 'track_cart', 'conversion_label',
	);

	/**
	 * Data keys that CustomEvent::update() reads from $args and rewrites, but which
	 * the platform map does NOT expose as writable fields — they must still be
	 * snapshotted so a partial write round-trips (not resets) them. Pinterest's
	 * WooCommerce track-data switchers are Pro (map track = null) yet update()
	 * unconditionally rebuilds them, so without this a partial write blanks them.
	 */
	private const PRESERVE_KEYS = array(
		'pinterest_track_single_woo_data',
		'pinterest_track_cart_woo_data',
	);

	/**
	 * Build the full admin-POST `$args` from a stored custom event.
	 *
	 * @param \PixelYourSite\CustomEvent $event Event model.
	 * @return array<string, mixed>
	 */
	public static function toArgs( $event ): array {
		$args = array();

		// Event-level scalars.
		$args[ 'title' ]              = (string) $event->getTitle();
		$args[ 'enabled' ]           = $event->isEnabled() ? 1 : 0;
		$args[ 'conditions_enabled' ] = self::truthy( $event->__get( 'conditions_enabled' ) ) ? 1 : 0;
		$args[ 'conditions_logic' ]  = (string) ( $event->__get( 'conditions_logic' ) ?: 'AND' );

		foreach ( CustomEventPlatformMap::slugs() as $slug ) {
			$def = CustomEventPlatformMap::get( $slug );
			if ( null === $def ) {
				continue;
			}
			foreach ( self::KEY_FIELDS as $field ) {
				$key = $def[ $field ] ?? null;
				if ( !is_string( $key ) || '' === $key ) {
					continue;
				}
				$value = $event->__get( $key );

				if ( 'pixel' === $field && !empty( $def[ 'pixel_array' ] ) && !is_array( $value ) ) {
					$value = ( null === $value || '' === $value ) ? array() : array( $value );
				}
				$args[ $key ] = $value;
			}
			foreach ( CustomEventPlatformMap::extras( $slug ) as $spec ) {
				if ( isset( $spec[ 'key' ] ) ) {
					$args[ $spec[ 'key' ] ] = $event->__get( $spec[ 'key' ] );
				}
			}

			if ( CustomEventPlatformMap::EVENTS_GA_GROUP === ( $def[ 'events_kind' ] ?? '' ) && is_string( $def[ 'event_type' ] ?? null ) ) {
				$groupKey          = $def[ 'event_type' ] . '_group';
				$args[ $groupKey ] = $event->__get( $groupKey );
			}
		}

		foreach ( self::PRESERVE_KEYS as $key ) {
			$args[ $key ] = $event->__get( $key );
		}

		// Triggers / conditions: rebuilt from their objects (separate metas).
		$args[ 'triggers' ]   = self::triggersToArgs( $event );
		$args[ 'conditions' ] = self::conditionsToArgs( $event );

		return $args;
	}

	/**
	 * Reconstruct `$args['triggers']` from the event's TriggerEvent objects, in the
	 * shape CustomEvent::update() reads (trigger_type + delay / post_type_value +
	 * page_visit_triggers / scroll_pos_triggers). Non-writable types are emitted as
	 * a bare `{trigger_type}` — the set ability blocks such events beforehand.
	 *
	 * @param \PixelYourSite\CustomEvent $event Event model.
	 * @return array<int, array<string, mixed>>
	 */
	private static function triggersToArgs( $event ): array {
		$out      = array();
		$triggers = is_array( $event->getTriggers() ) ? $event->getTriggers() : array();
		foreach ( array_values( $triggers ) as $t ) {
			if ( !is_object( $t ) || !method_exists( $t, 'getTriggerType' ) ) {
				continue;
			}
			$type = (string) $t->getTriggerType();
			$row  = array( 'trigger_type' => $type );

			if ( in_array( $type, array( 'page_visit', 'home_page', 'post_type' ), true ) && method_exists( $t, 'getParam' ) ) {
				$delay = $t->getParam( 'delay' );
				if ( null !== $delay && '' !== $delay ) {
					$row[ 'delay' ] = (int) $delay;
				}
			}
			if ( 'post_type' === $type && method_exists( $t, 'getParam' ) ) {
				$pv = $t->getParam( 'post_type_value' );
				if ( null !== $pv && '' !== $pv ) {
					$row[ 'post_type_value' ] = $pv;
				}
			}
			if ( 'page_visit' === $type && method_exists( $t, 'getTriggers' ) ) {
				$row[ 'page_visit_triggers' ] = self::cleanRules( (array) $t->getTriggers() );
			}
			if ( 'scroll_pos' === $type && method_exists( $t, 'getTriggers' ) ) {
				$row[ 'scroll_pos_triggers' ] = self::cleanRules( (array) $t->getTriggers() );
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Drop the stray `undefined` key some stored rule rows carry.
	 *
	 * @param array $rules Rule rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function cleanRules( array $rules ): array {
		$out = array();
		foreach ( $rules as $r ) {
			if ( is_array( $r ) ) {
				unset( $r[ 'undefined' ] );
				$out[] = $r;
			}
		}

		return $out;
	}

	/**
	 * Reconstruct `$args['conditions']` from the event's ConditionalEvent objects.
	 *
	 * @param \PixelYourSite\CustomEvent $event Event model.
	 * @return array<int, array<string, mixed>>
	 */
	private static function conditionsToArgs( $event ): array {
		$out        = array();
		$conditions = method_exists( $event, 'getConditions' ) && is_array( $event->getConditions() )
			? $event->getConditions() : array();
		foreach ( array_values( $conditions ) as $condition ) {
			if ( !is_object( $condition ) || !method_exists( $condition, 'getConditionType' ) ) {
				continue;
			}
			$type = (string) $condition->getConditionType();
			$row  = array( 'condition_type' => $type );

			switch ( CustomEventConditionMap::shape( $type ) ) {
				case CustomEventConditionMap::SHAPE_DEVICE:
					$row[ 'device' ] = $condition->getParam( 'device' );
					break;
				case CustomEventConditionMap::SHAPE_USER_ROLE:
					$row[ 'user_role' ] = (array) $condition->getParam( 'user_role' );
					break;
				default: // rule_value — nested under the type key
					$row[ $type ] = array(
						'condition_rule'  => $condition->getParam( 'condition_rule' ),
						'condition_value' => $condition->getParam( 'condition_value' ),
					);
					break;
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Loose truthiness for stored option values.
	 *
	 * @param mixed $v Value.
	 * @return bool
	 */
	private static function truthy( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_string( $v ) ) {
			return '1' === $v || 'true' === strtolower( $v );
		}

		return is_int( $v ) && 1 === $v;
	}
}
