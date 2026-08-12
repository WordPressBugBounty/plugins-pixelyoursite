<?php
/**
 * `set_automatic_event_config` — partial write to the GLOBAL automatic
 * (page-level) event configuration (Free). `mcp_note` required, two-step
 * `confirm` gate. Write surfaces:
 *  - `master_enabled` — the single switch gating ALL automatic events (core).
 *  - `global_event_toggles[]` — per-event global on/off (core).
 *  - `platform_event_toggles[]` — per-platform firing toggles, with `all`
 *    fan-out and `unsupported_toggles` reporting.
 *  - Settings (core): scroll %, time-on-page seconds, download extensions
 *    (the extensions list REPLACES the stored list wholesale).
 *
 * Free automatic events: form, signup, login, download, comment, scroll,
 * time_on_page, 404, search. Video / rage-click / link automatic events + their
 * settings (rage_click_threshold, video_youtube/vimeo) are Pro-only.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\EventToggleMap;

final class SetAutomaticEventConfigAbility extends AbstractWriteAbility {

	public const ID = 'pixelyoursite/set-automatic-event-config';

	/** Free settings args → core PYS option keys. */
	private const SETTING_KEYS = array(
		'scroll_value'        => 'automatic_event_scroll_value',
		'time_on_page_value'  => 'automatic_event_time_on_page_value',
		'download_extensions' => 'automatic_event_download_extensions',
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
		return 'PYS MCP — Set Automatic Event Config';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Writes a partial update to the GLOBAL automatic (page-level) event configuration (PixelYourSite Free). Surfaces: `master_enabled` — the single switch gating ALL automatic events; `global_event_toggles` — per-event global on/off (`[{event, enabled}]`); `platform_event_toggles` — PER-PLATFORM toggles (`[{platform, event, enabled}]`, platform = a slug or `all`) for any event the platform registers (read the matrix from `get_automatic_events_config.platform_events`; unregistered pairs come back in `unsupported_toggles`; the admin UI shows a per-platform switch only for signup, others are MCP-only); and settings — `scroll_value` (% depth that triggers Scroll, 0–100), `time_on_page_value` (seconds before TimeOnPage fires), `download_extensions` (extensions tracked as Download, WITHOUT dots, e.g. ["pdf","zip"] — REPLACES the stored list wholesale, so read the current list from get_automatic_events_config.settings first and resend it with your changes). Free automatic events are exactly: form, signup, login, download, comment, scroll, time_on_page, 404, search. These are Pro automatic events and NOT writable in Free (say plainly it requires Pro; do NOT claim it "does not exist"): AdSense ("Track AdSense"), internal link clicks, outbound link clicks, video, phone/tel link clicks, email link clicks, rage click, video speed — plus the Pro settings rage_click_threshold and the video YouTube/Vimeo sub-toggles. Enabling a per-platform toggle whose GLOBAL toggle is off auto-enables the global one; the MASTER switch is never auto-enabled — a note tells you when it blocks everything. You MUST relay every `notes` entry to the user. An automatic event fires on a platform only when master AND its global toggle AND that platform gate are all on. **Two-step write (server-enforced):** call FIRST without `confirm` for a `confirmation_required` preview; show `pending_changes` and STOP — do NOT set `confirm: true` in the same turn or without the user actually replying with approval. Only after they agree, resend the same args with `confirm: true`. Pass `mcp_note`.';
	}

	/**
	 * Input JSON-Schema.
	 *
	 * @return array
	 */
	public static function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'master_enabled'         => array(
					'type'        => 'boolean',
					'description' => 'The master automatic-events switch (`automatic_events_enabled`). false disables ALL automatic events site-wide.',
				),
				'global_event_toggles'   => array(
					'type'        => 'array',
					'description' => 'Global per-event on/off. Each item `{ event, enabled }`. Global layer (an event also needs its platform toggle on to fire for that platform).',
					'items'       => array(
						'type'                 => 'object',
						'required'             => array( 'event', 'enabled' ),
						'additionalProperties' => false,
						'properties'           => array(
							'event'   => array( 'type' => 'string', 'enum' => EventToggleMap::AUTOMATIC_EVENTS ),
							'enabled' => array( 'type' => 'boolean' ),
						),
					),
				),
				'platform_event_toggles' => array(
					'type'        => 'array',
					'description' => 'PER-PLATFORM automatic-event toggles. Each item `{ platform, event, enabled }` for any event the platform REGISTERS (see get_automatic_events_config.platform_events; sets differ — e.g. 404 is GA/GTM only). `platform` = a slug or `all`. Unregistered pairs are returned in `unsupported_toggles` and skipped. Enabling a toggle whose global toggle is off auto-enables the global one (see `notes`).',
					'items'       => array(
						'type'                 => 'object',
						'required'             => array( 'platform', 'event', 'enabled' ),
						'additionalProperties' => false,
						'properties'           => array(
							'platform' => array( 'type' => 'string', 'enum' => EventToggleMap::TARGET_PLATFORMS ),
							'event'    => array( 'type' => 'string', 'enum' => EventToggleMap::AUTOMATIC_EVENTS ),
							'enabled'  => array( 'type' => 'boolean' ),
						),
					),
				),
				'scroll_value'           => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 100, 'description' => 'Scroll-depth percentage (0–100) that triggers the Scroll event.' ),
				'time_on_page_value'     => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 100, 'description' => 'Seconds on page before the TimeOnPage event fires.' ),
				'download_extensions'    => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string', 'minLength' => 1 ),
					'description' => 'File extensions tracked as Download events, WITHOUT dots (e.g. ["pdf","zip"]). REPLACES the stored list wholesale — read the current list from get_automatic_events_config.settings.download_extensions first. An empty array clears the list.',
				),
				'confirm'                => array(
					'type'        => 'boolean',
					'description' => 'Two-step write guard. Call FIRST WITHOUT this flag for a `confirmation_required` preview; show `pending_changes` and STOP. Do NOT set `confirm: true` in the SAME turn as the preview, and do NOT set it unless the user actually replied with approval in a SEPARATE message. Only then resend the same args with `confirm: true`. Without `confirm: true` nothing is written.',
				),
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
				'saved'                  => array( 'type' => 'boolean' ),
				'available'              => array( 'type' => 'boolean' ),
				'confirmation_required'  => array( 'type' => 'boolean' ),
				'next_step'              => array( 'type' => 'string' ),
				'pending_changes'        => array( 'type' => 'object', 'additionalProperties' => true ),
				'changed'                => array( 'type' => 'object', 'additionalProperties' => true ),
				'platform_event_toggles' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'unsupported_toggles'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'notes'                  => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Side-effect notices you did NOT request (e.g. a global toggle was auto-enabled, or the master switch is off). You MUST relay every note to the user.',
				),
			),
		);
	}

	/**
	 * Validate and write (or preview) the automatic-events config.
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

		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;
		if ( null === $pys ) {
			return new \WP_Error( 'pys_mcp_pys_unavailable', 'PixelYourSite is not loaded.', array( 'status' => 500 ) );
		}

		$hasMaster   = array_key_exists( 'master_enabled', $input ) && is_bool( $input[ 'master_enabled' ] );
		$rawGlobal   = isset( $input[ 'global_event_toggles' ] ) && is_array( $input[ 'global_event_toggles' ] ) ? $input[ 'global_event_toggles' ] : array();
		$rawPlatform = isset( $input[ 'platform_event_toggles' ] ) && is_array( $input[ 'platform_event_toggles' ] ) ? $input[ 'platform_event_toggles' ] : array();
		$rawSettings = array_intersect_key( $input, self::SETTING_KEYS );

		if ( !$hasMaster && empty( $rawGlobal ) && empty( $rawPlatform ) && empty( $rawSettings ) ) {
			return new \WP_Error(
				'pys_mcp_automatic_no_args',
				'Nothing to update. Pass at least one of `master_enabled`, `global_event_toggles`, `platform_event_toggles`, `scroll_value`, `time_on_page_value`, or `download_extensions`.',
				array( 'status' => 400 )
			);
		}

		// --- core: master ---
		$corePayload = array();
		$pending     = array();
		if ( $hasMaster ) {
			$new                                       = (bool) $input[ 'master_enabled' ];
			$corePayload[ 'automatic_events_enabled' ] = $new;
			$pending[ 'master_enabled' ]               = array( 'current' => EventToggleMap::toBool( $pys->getOption( 'automatic_events_enabled' ) ), 'new' => $new );
		}

		// --- settings (core) ---
		foreach ( $rawSettings as $arg => $value ) {
			$key = self::SETTING_KEYS[ $arg ];
			if ( 'download_extensions' === $arg ) {
				$list = array();
				foreach ( is_array( $value ) ? $value : array() as $raw ) {
					if ( !is_string( $raw ) ) {
						continue;
					}
					$ext = strtolower( ltrim( trim( $raw ), '.' ) );
					if ( '' !== $ext && !in_array( $ext, $list, true ) ) {
						$list[] = $ext;
					}
				}
				$stored  = $pys->getOption( $key );
				$current = array_values( array_filter( is_array( $stored ) ? array_map( 'strval', $stored ) : array() ) );
				$new     = $list;
			} else {
				$current = (float) $pys->getOption( $key, 0 );
				$new     = (float) $value;
			}
			$corePayload[ $key ] = $new;
			$pending[ $arg ]     = array( 'current' => $current, 'new' => $new );
		}

		// --- global per-event toggles (core) ---
		foreach ( $rawGlobal as $entry ) {
			if ( !is_array( $entry ) ) {
				continue;
			}
			$event = isset( $entry[ 'event' ] ) && is_string( $entry[ 'event' ] ) ? $entry[ 'event' ] : '';
			if ( !EventToggleMap::isKnownEvent( 'automatic', $event ) ) {
				continue;
			}
			$new                           = !empty( $entry[ 'enabled' ] );
			$key                           = EventToggleMap::key( 'automatic', $event );
			$corePayload[ $key ]           = $new;
			$pending[ 'global:' . $event ] = array( 'current' => EventToggleMap::toBool( $pys->getOption( $key ) ), 'new' => $new );
		}

		// --- per-platform toggles ---
		$activeSettings    = EventToggleMap::enabledSettings();
		$toggleWrites      = array();
		$togglePending     = array();
		$toggleUnsupported = array();
		foreach ( $rawPlatform as $entry ) {
			if ( !is_array( $entry ) ) {
				continue;
			}
			$tPlatform = isset( $entry[ 'platform' ] ) && is_string( $entry[ 'platform' ] ) ? $entry[ 'platform' ] : '';
			$tEvent    = isset( $entry[ 'event' ] ) && is_string( $entry[ 'event' ] ) ? $entry[ 'event' ] : '';
			$tEnabled  = !empty( $entry[ 'enabled' ] );
			if ( '' === $tPlatform || '' === $tEvent ) {
				continue;
			}
			if ( !EventToggleMap::isKnownEvent( 'automatic', $tEvent ) ) {
				$toggleUnsupported[] = array( 'platform' => $tPlatform, 'event' => $tEvent, 'reason' => 'unknown_event' );
				continue;
			}
			$key     = EventToggleMap::key( 'automatic', $tEvent );
			$targets = 'all' === $tPlatform ? array_keys( $activeSettings ) : array( $tPlatform );
			$matched = false;
			foreach ( $targets as $slug ) {
				$settings = $activeSettings[ $slug ] ?? null;
				if ( null === $settings || !in_array( $slug, EventToggleMap::TARGET_PLATFORMS, true )
				     || !EventToggleMap::automaticRegisters( $settings, $tEvent ) ) {
					if ( 'all' !== $tPlatform ) {
						$toggleUnsupported[] = array(
							'platform' => $slug,
							'event'    => $tEvent,
							'reason'   => null === $settings ? 'platform_inactive' : 'event_not_supported',
						);
					}
					continue;
				}
				$matched                       = true;
				$toggleWrites[ $slug ][ $key ] = $tEnabled;
				$togglePending[]               = array(
					'platform' => $slug,
					'event'    => $tEvent,
					'current'  => EventToggleMap::toBool( $settings->getOption( $key ) ),
					'new'      => $tEnabled,
				);
			}
			if ( 'all' === $tPlatform && !$matched ) {
				$toggleUnsupported[] = array( 'platform' => 'all', 'event' => $tEvent, 'reason' => 'no_active_platform_registers_event' );
			}
		}

		// Auto-enable the global toggle when a per-platform toggle is turned on.
		$notes = array();
		foreach ( $togglePending as $tp ) {
			if ( empty( $tp[ 'new' ] ) ) {
				continue;
			}
			$gKey = EventToggleMap::key( 'automatic', $tp[ 'event' ] );
			if ( isset( $corePayload[ $gKey ] ) ) {
				continue;
			}
			if ( !EventToggleMap::toBool( $pys->getOption( $gKey ) ) ) {
				$corePayload[ $gKey ]                    = true;
				$pending[ 'global:' . $tp[ 'event' ] ]   = array( 'current' => false, 'new' => true );
				$notes[]                                 = sprintf( 'Auto-enabled the global "%s" toggle — a per-platform switch has no effect while the global toggle is off.', $tp[ 'event' ] );
			}
		}

		$masterAfter = array_key_exists( 'automatic_events_enabled', $corePayload )
			? (bool) $corePayload[ 'automatic_events_enabled' ]
			: EventToggleMap::toBool( $pys->getOption( 'automatic_events_enabled' ) );
		if ( !$masterAfter && ( !empty( $toggleWrites ) || !empty( $corePayload ) ) ) {
			$notes[] = 'The automatic-events MASTER switch is OFF — no automatic event fires until `master_enabled: true` is set.';
		}

		// --- two-step confirm ---
		if ( !$confirm ) {
			$preview = array(
				'available'             => true,
				'confirmation_required' => true,
				'pending_changes'       => (object) $pending,
				'next_step'             => 'NOTHING HAS BEEN SAVED YET. Show pending_changes (and every note) to the user and STOP this turn. Do NOT call set_automatic_event_config again in this turn and do NOT set confirm: true now. WAIT for the user to reply with explicit approval in a SEPARATE message; only then resend the same args with confirm: true.',
			);
			if ( !empty( $togglePending ) ) {
				$preview[ 'platform_event_toggles' ] = $togglePending;
			}
			if ( !empty( $toggleUnsupported ) ) {
				$preview[ 'unsupported_toggles' ] = $toggleUnsupported;
			}
			if ( !empty( $notes ) ) {
				$preview[ 'notes' ] = $notes;
			}

			return $preview;
		}

		// --- confirmed write ---
		if ( !empty( $corePayload ) ) {
			$pys->updateOptions( $corePayload );
		}
		foreach ( $toggleWrites as $slug => $kv ) {
			$settings = $activeSettings[ $slug ] ?? null;
			if ( null !== $settings && !empty( $kv ) ) {
				$settings->updateOptions( $kv );
			}
		}
		if ( function_exists( '\\PixelYourSite\\purgeCache' ) ) {
			\PixelYourSite\purgeCache();
		}

		$result = array(
			'saved'     => true,
			'available' => true,
			'changed'   => (object) $pending,
		);
		if ( !empty( $togglePending ) ) {
			$result[ 'platform_event_toggles' ] = $togglePending;
		}
		if ( !empty( $toggleUnsupported ) ) {
			$result[ 'unsupported_toggles' ] = $toggleUnsupported;
		}
		if ( !empty( $notes ) ) {
			$result[ 'notes' ] = $notes;
		}

		return $result;
	}
}
