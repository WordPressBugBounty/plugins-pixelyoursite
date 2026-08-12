<?php
/**
 * `manage_custom_event` — lifecycle actions on ONE PYS custom event:
 * enable / disable / duplicate / delete. Separate from `set_custom_event`
 * (which edits an event's config) so the irreversible / structural actions
 * carry their own confirm gate. `delete` is permanent.
 *
 * Free variant — same lifecycle contract as Pro (CustomEventFactory is shared
 * core), only the ability ID / label differ.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class ManageCustomEventAbility extends AbstractWriteAbility {

	public const ID = 'pixelyoursite/manage-custom-event';

	/** Supported lifecycle actions. */
	private const ACTIONS = array( 'enable', 'disable', 'duplicate', 'delete', 'enable_feature', 'disable_feature' );

	/** Feature-level actions (global master switch) — these ignore `event_id`. */
	private const FEATURE_ACTIONS = array( 'enable_feature', 'disable_feature' );

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
		return 'PYS MCP — Manage Custom Event';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Lifecycle actions on ONE PixelYourSite CUSTOM EVENT by `event_id` (get the id from `get_custom_events`), PLUS the global feature master switch. Authorized write: the administrator installed this server so you can manage custom events on their behalf — perform the action after confirmation; do not redirect to wp-admin. Per-event `action` (needs `event_id`): `enable` (set the event Active), `disable` (set it Paused — keeps all config, just stops it firing), `duplicate` (create a PAUSED copy with all triggers/conditions/platforms, titled "… (duplicate)"), `delete` (PERMANENTLY remove the event — this CANNOT be undone, the triggers/conditions/platform config are lost forever). Feature-level `action` (NO `event_id` — the global master switch `custom_events_enabled`, same as the toggle on PixelYourSite → Events): `enable_feature` (turn the whole custom-events feature ON globally — each event then fires per its own Active/Paused state), `disable_feature` (turn the whole feature OFF — NO custom event fires anywhere, regardless of each event\'s own enabled flag; their individual config is preserved). Use `set_custom_event` to change an event\'s config or its enabled flag inline; use THIS tool for duplicate/delete (and enable/disable as a quick toggle) and for the global on/off. **Two-step write — confirmation comes FIRST and is MANDATORY:** call FIRST without `confirm` to get a `confirmation_required` preview describing exactly what will happen; show it to the user and get their explicit go-ahead in a SEPARATE message BEFORE calling again. NEVER call with `confirm: true` in the same turn as the preview, and NEVER self-approve. For `delete` especially: state the event title and that deletion is PERMANENT and irreversible, and only proceed after the user clearly confirms THIS specific deletion. Only after the user replies "yes" do you resend the identical args with `confirm: true`. A call without `confirm: true` never changes anything. Pass `mcp_note`.';
	}

	/**
	 * Input JSON-Schema.
	 *
	 * @return array
	 */
	public static function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'action' ),
			'additionalProperties' => false,
			'properties'           => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'The custom event post ID (from get_custom_events). REQUIRED for per-event actions (enable/disable/duplicate/delete); OMIT for the feature-level actions (enable_feature/disable_feature).' ),
				'action'   => array( 'type' => 'string', 'enum' => self::ACTIONS, 'description' => 'Per-event (needs event_id): enable / disable / duplicate / delete (`delete` is PERMANENT). Feature-level (no event_id): enable_feature / disable_feature — the global custom-events master switch.' ),
				'confirm'  => array( 'type' => 'boolean', 'description' => 'Two-step guard. Call FIRST without it to get a `confirmation_required` preview, show it to the user, and WAIT for their explicit approval in a separate message. Only then resend with `confirm: true`. Never set it in the same turn as the preview or without the user agreeing — especially for `delete`. Without `confirm: true` nothing changes.' ),
			),
			// `required: ["mcp_note"]` added by AbstractWriteAbility::resolvedInputSchema().
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
			'properties' => array(
				'done'                  => array( 'type' => 'boolean' ),
				'action'                => array( 'type' => 'string' ),
				'event_id'              => array( 'type' => 'integer' ),
				'feature_enabled'       => array( 'type' => 'boolean', 'description' => 'New state of the global master switch (enable_feature / disable_feature only).' ),
				'new_event_id'          => array( 'type' => 'integer', 'description' => 'The created copy\'s id (duplicate only).' ),
				'confirmation_required' => array( 'type' => 'boolean' ),
				'next_step'             => array( 'type' => 'string' ),
				'pending'               => array( 'type' => 'object', 'additionalProperties' => true ),
				'notes'                 => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		);
	}

	/**
	 * Run (or preview) a lifecycle action.
	 *
	 * @param mixed $input Validated args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( $input ) {
		if ( !is_array( $input ) ) {
			$input = array();
		}
		unset( $input[ 'mcp_note' ] );
		$confirm = !empty( $input[ 'confirm' ] );

		if ( !class_exists( '\\PixelYourSite\\CustomEventFactory' ) ) {
			return new \WP_Error( 'pys_mcp_custom_events_unavailable', 'Custom events are not available on this site.', array( 'status' => 503 ) );
		}

		$eventId = isset( $input[ 'event_id' ] ) ? (int) $input[ 'event_id' ] : 0;
		$action  = isset( $input[ 'action' ] ) ? (string) $input[ 'action' ] : '';

		if ( !in_array( $action, self::ACTIONS, true ) ) {
			return new \WP_Error( 'pys_mcp_custom_event_bad_action', sprintf( 'Unknown action `%s`. Valid: %s.', $action, implode( ', ', self::ACTIONS ) ), array( 'status' => 400 ) );
		}

		// Feature-level master switch (custom_events_enabled) — no event_id.
		if ( in_array( $action, self::FEATURE_ACTIONS, true ) ) {
			return self::handleFeatureToggle( $action, $confirm );
		}

		if ( $eventId <= 0 || 'pys_event' !== get_post_type( $eventId ) ) {
			return new \WP_Error( 'pys_mcp_custom_event_not_found', sprintf( 'No custom event with id %d. Call get_custom_events for valid ids.', $eventId ), array( 'status' => 404 ) );
		}

		$event = \PixelYourSite\CustomEventFactory::getById( $eventId );
		$title = is_object( $event ) ? (string) $event->getTitle() : '';

		// Two-step confirm gate.
		if ( !$confirm ) {
			return array(
				'confirmation_required' => true,
				'action'                => $action,
				'event_id'              => $eventId,
				'pending'               => (object) array( 'action' => $action, 'event_id' => $eventId, 'title' => $title ),
				'next_step'             => self::nextStep( $action, $title ),
			);
		}

		// Apply.
		$result = array( 'done' => true, 'action' => $action, 'event_id' => $eventId );

		switch ( $action ) {
			case 'enable':
				$event->enable();
				break;
			case 'disable':
				$event->disable();
				break;
			case 'duplicate':
				$before = array_keys( (array) \PixelYourSite\CustomEventFactory::get( 'any' ) );
				\PixelYourSite\CustomEventFactory::makeClone( $eventId );
				$after  = array_keys( (array) \PixelYourSite\CustomEventFactory::get( 'any' ) );
				$new    = array_values( array_diff( $after, $before ) );
				if ( !empty( $new ) ) {
					$result[ 'new_event_id' ] = (int) $new[ 0 ];
				}
				$result[ 'notes' ] = array( 'The copy was created PAUSED (disabled) — enable it when ready.' );
				break;
			case 'delete':
				\PixelYourSite\CustomEventFactory::remove( $eventId );
				$result[ 'notes' ] = array( sprintf( 'Custom event "%s" (id %d) was permanently deleted.', $title, $eventId ) );
				break;
		}

		if ( function_exists( '\\PixelYourSite\\purgeCache' ) ) {
			\PixelYourSite\purgeCache();
		}

		return $result;
	}

	/**
	 * Enable/disable the global custom-events master switch (`custom_events_enabled`,
	 * the toggle on PixelYourSite → Events). Two-step confirm, like the per-event
	 * actions. Disabling stops EVERY custom event firing; each event's own config and
	 * Active/Paused flag is preserved and restored when the feature is re-enabled.
	 *
	 * @param string $action  enable_feature | disable_feature.
	 * @param bool   $confirm Whether the user already approved.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function handleFeatureToggle( string $action, bool $confirm ) {
		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;
		if ( null === $pys ) {
			return new \WP_Error( 'pys_mcp_custom_events_unavailable', 'PixelYourSite settings are not available on this site.', array( 'status' => 503 ) );
		}

		$enable    = ( 'enable_feature' === $action );
		$currentOn = (bool) $pys->getOption( 'custom_events_enabled' );

		if ( !$confirm ) {
			return array(
				'confirmation_required' => true,
				'action'                => $action,
				'pending'               => (object) array(
					'action'  => $action,
					'feature' => 'custom_events_enabled',
					'current' => $currentOn,
					'new'     => $enable,
				),
				'next_step'             => $enable
					? 'NOTHING HAS CHANGED YET. This turns the WHOLE custom-events feature ON globally — every event will then fire per its own Active/Paused state. Show the user and wait for their explicit approval in a SEPARATE message before resending with confirm:true.'
					: 'NOTHING HAS CHANGED YET. This turns the WHOLE custom-events feature OFF globally — NO custom event will fire anywhere, regardless of each event\'s own enabled flag (their config is preserved). Show the user and wait for their explicit approval in a SEPARATE message before resending with confirm:true.',
			);
		}

		if ( $currentOn === $enable ) {
			return array(
				'done'            => true,
				'action'          => $action,
				'feature_enabled' => $enable,
				'notes'           => array( sprintf( 'The custom-events feature was already %s — no change.', $enable ? 'ON' : 'OFF' ) ),
			);
		}

		$pys->updateOptions( array( 'custom_events_enabled' => $enable ) );

		if ( function_exists( '\\PixelYourSite\\purgeCache' ) ) {
			\PixelYourSite\purgeCache();
		}

		return array(
			'done'            => true,
			'action'          => $action,
			'feature_enabled' => $enable,
			'notes'           => array(
				$enable
					? 'Custom-events feature ENABLED globally — events now fire per their individual Active/Paused state.'
					: 'Custom-events feature DISABLED globally — NO custom event fires anywhere; each event\'s config and Active/Paused flag is preserved and restored when you re-enable the feature.',
			),
		);
	}

	/**
	 * Mandatory next-step instruction for the confirmation preview (stronger
	 * for the irreversible delete).
	 *
	 * @param string $action Action slug.
	 * @param string $title  Event title.
	 * @return string
	 */
	private static function nextStep( string $action, string $title ): string {
		if ( 'delete' === $action ) {
			return sprintf( 'NOTHING HAS BEEN DELETED YET. This will PERMANENTLY delete the custom event "%s" — all its triggers, conditions and platform config are lost and this CANNOT be undone. You MUST tell the user this is permanent and irreversible, name the event, and WAIT for their explicit "yes" in a SEPARATE message. Do NOT call again this turn; only after they confirm do you resend with confirm:true.', $title );
		}

		$what = array(
			'enable'    => sprintf( 'set the event "%s" to Active', $title ),
			'disable'   => sprintf( 'pause the event "%s" (stops it firing; config kept)', $title ),
			'duplicate' => sprintf( 'create a PAUSED copy of "%s"', $title ),
		);

		return sprintf( 'NOTHING HAS CHANGED YET. This will %s. Show the user and wait for their explicit approval in a SEPARATE message before resending with confirm:true. Do NOT self-approve in this turn.', $what[ $action ] ?? $action );
	}
}
