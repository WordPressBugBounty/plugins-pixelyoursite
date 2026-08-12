<?php
/**
 * Ability ID → MCP tool name resolver (Free variant).
 * The WP Abilities API forbids underscores in ability names, but the public
 * tool names use them (`set_woo_event_config`, …). This is the
 * `mcp_adapter_tool_name` filter callback: it swaps in the underscore name for
 * abilities Free owns and leaves other plugins' abilities untouched. `ping`
 * is intentionally absent and keeps its hyphen name.
 *
 * Free tool set only — no CAPI / advanced-matching / superpack / reports /
 * catalog-feed tools (see get_custom_events is the Pro name, kept for Free).
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class ToolNameMap {

	/**
	 * Hyphen ability ID → underscore MCP tool name. Free ability IDs use the
	 * `pixelyoursite/` prefix (Free's ability category).
	 */
	public const MAP = array(
		'pixelyoursite/get-usage-guidance'                => 'get_usage_guidance',
		'pixelyoursite/get-tracking-audit'                => 'get_tracking_audit',
		'pixelyoursite/get-credential-setup-instructions' => 'get_credential_setup_instructions',
		'pixelyoursite/get-platform-pixels'               => 'get_platform_pixels',
		'pixelyoursite/get-woo-events-config'             => 'get_woo_events_config',
		'pixelyoursite/set-woo-event-config'              => 'set_woo_event_config',
		'pixelyoursite/get-edd-events-config'             => 'get_edd_events_config',
		'pixelyoursite/set-edd-event-config'              => 'set_edd_event_config',
		'pixelyoursite/get-automatic-events-config'       => 'get_automatic_events_config',
		'pixelyoursite/set-automatic-event-config'        => 'set_automatic_event_config',
		'pixelyoursite/get-custom-events'                 => 'get_custom_events',
		'pixelyoursite/get-custom-event'                  => 'get_custom_event',
		'pixelyoursite/set-custom-event'                  => 'set_custom_event',
		'pixelyoursite/manage-custom-event'               => 'manage_custom_event',
	);

	/**
	 * Direct lookup for PHP callers that need the public name without going
	 * through the filter. Returns null if the ID isn't mapped.
	 *
	 * @param string $abilityId Hyphen ability ID.
	 * @return string|null Underscore MCP tool name, or null if unmapped.
	 */
	public static function resolve( string $abilityId ): ?string {
		return self::MAP[ $abilityId ] ?? null;
	}

	/**
	 * Callback for the `mcp_adapter_tool_name` filter. Returns the mapped
	 * underscore name for our abilities, or `$defaultName` untouched for
	 * everyone else (other plugins may hook this filter too).
	 *
	 * @param string $defaultName Sanitised default name.
	 * @param mixed  $ability     `\WP_Ability` in practice. Typed loose so a
	 *                            future signature change doesn't fatal.
	 * @return string The resolved MCP tool name.
	 */
	public static function filter( $defaultName, $ability ) {
		if ( !is_object( $ability ) || !method_exists( $ability, 'get_name' ) ) {
			return $defaultName;
		}

		$mapped = self::MAP[ $ability->get_name() ] ?? null;

		return null === $mapped ? $defaultName : $mapped;
	}
}
