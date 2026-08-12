<?php
/**
 * `get_automatic_events_config` — read-only snapshot of the GLOBAL automatic
 * (page-level) events PYS fires site-wide (Free variant: form, signup, login,
 * download, comment, scroll, time_on_page, 404, search). Reports the master
 * switch, the global per-event toggles, the per-platform firing matrix, and the
 * read-only thresholds.
 *
 * An automatic event fires for a platform only when ALL of: the master switch
 * is on, its global toggle is on, and that platform's per-event toggle is on.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\EventToggleMap;

final class GetAutomaticEventsConfigAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-automatic-events-config';

	private const ADMIN_UI_PATH = 'PixelYourSite → General Settings → Automatic Events';

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
		return 'PYS MCP — Get Automatic Events Config';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Returns the GLOBAL automatic (page-level) event configuration in PixelYourSite Free — events fired site-wide regardless of WooCommerce/EDD: form, signup, login, download, comment, scroll depth, time on page, 404, search. Sections: `master_enabled` (the single switch that gates ALL automatic events), `global_events` (per-event on/off at the global level), `platform_events` (per-platform firing matrix: for every active platform, the effective on/off of each automatic event it registers — events a platform does not register are absent from its row, and the sets differ per platform, e.g. 404 is GA/GTM only; a `false` here = supported but off, NOT unavailable; an ABSENT event = unsupported), `platform_toggles` (the raw per-platform gate values — the layer `set_automatic_event_config.platform_event_toggles` writes; the admin UI shows a per-platform switch only for signup, others are MCP-only), and `settings` (scroll % threshold, time-on-page seconds, tracked download extensions). An automatic event fires on a platform only when master AND its global toggle AND that platform gate are all on. These automatic events are PixelYourSite Pro and NOT available in Free (if the user asks for one, say plainly it requires Pro — do NOT claim it does not exist): AdSense ("Track AdSense"), internal link clicks, outbound link clicks, video, phone/tel link clicks, email link clicks, rage click, video speed. Call this when the user asks about automatic / page-level / site-wide event tracking.';
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
				'available'        => array( 'type' => 'boolean' ),
				'admin_ui_path'    => array( 'type' => 'string' ),
				'master_enabled'   => array(
					'type'        => 'boolean',
					'description' => 'The single master switch (`automatic_events_enabled`). When false, NO automatic event fires regardless of the toggles below.',
				),
				'global_events'    => array(
					'type'                 => 'object',
					'description'          => 'Per-event global on/off. Keyed by event stem => bool.',
					'additionalProperties' => array( 'type' => 'boolean' ),
				),
				'platform_events'  => array(
					'type'                 => 'object',
					'description'          => 'Effective firing matrix: slug => { event_stem => bool }. true = fires now (master AND global AND platform gate). `false` = supported but off (can enable); an ABSENT event = unsupported on that platform.',
					'additionalProperties' => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'boolean' ) ),
				),
				'platform_toggles' => array(
					'type'                 => 'object',
					'description'          => 'Raw per-platform gate values (ignoring master/global) — the layer set_automatic_event_config.platform_event_toggles writes.',
					'additionalProperties' => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'boolean' ) ),
				),
				'settings'         => array(
					'type'        => 'object',
					'description' => '`scroll_value` (% depth that triggers Scroll), `time_on_page_value` (seconds), `download_extensions` (tracked file extensions, no dots). All writable via set_automatic_event_config.',
				),
			),
		);
	}

	/**
	 * Build the read-only automatic-events snapshot.
	 *
	 * @param mixed $input Validated args (none).
	 * @return array<string, mixed>
	 */
	public static function execute( $input ): array {
		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;
		if ( null === $pys ) {
			return array( 'available' => false );
		}

		$global = array();
		foreach ( EventToggleMap::AUTOMATIC_EVENTS as $event ) {
			$global[ $event ] = EventToggleMap::toBool( $pys->getOption( EventToggleMap::key( 'automatic', $event ) ) );
		}

		$master    = EventToggleMap::toBool( $pys->getOption( 'automatic_events_enabled' ) );
		$effective = array();
		$toggles   = array();
		foreach ( EventToggleMap::enabledSettings() as $slug => $settings ) {
			if ( !in_array( $slug, EventToggleMap::TARGET_PLATFORMS, true ) ) {
				continue; // gatags etc. don't fire automatic events
			}
			$rowEff = array();
			$rowRaw = array();
			foreach ( EventToggleMap::AUTOMATIC_EVENTS as $event ) {
				if ( !EventToggleMap::automaticRegisters( $settings, $event ) ) {
					continue;
				}
				$platformOn        = EventToggleMap::toBool( $settings->getOption( EventToggleMap::key( 'automatic', $event ) ) );
				$rowRaw[ $event ]  = $platformOn;
				$rowEff[ $event ]  = $master && $global[ $event ] && $platformOn;
			}
			if ( !empty( $rowEff ) ) {
				$effective[ $slug ] = $rowEff;
				$toggles[ $slug ]   = $rowRaw;
			}
		}

		$ext = $pys->getOption( 'automatic_event_download_extensions' );

		return array(
			'available'        => true,
			'admin_ui_path'    => self::ADMIN_UI_PATH,
			'master_enabled'   => $master,
			'global_events'    => $global,
			'platform_events'  => $effective,
			'platform_toggles' => $toggles,
			'settings'         => array(
				'scroll_value'        => (float) $pys->getOption( 'automatic_event_scroll_value', 0 ),
				'time_on_page_value'  => (float) $pys->getOption( 'automatic_event_time_on_page_value', 0 ),
				'download_extensions' => array_values( array_filter( is_array( $ext ) ? array_map( 'strval', $ext ) : array() ) ),
			),
		);
	}
}
