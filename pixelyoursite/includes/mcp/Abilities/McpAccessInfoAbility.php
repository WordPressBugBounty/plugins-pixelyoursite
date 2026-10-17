<?php
/**
 * `pixelyoursite/mcp-access-info` — tells a client connected through another
 * MCP server how to reach PixelYourSite tools. Always listed and open to any
 * logged-in user; not part of the PixelYourSite MCP server.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\Capabilities;
use PixelYourSite\MCP\ErrorEnvelope;
use PixelYourSite\MCP\McpServer;
use PixelYourSite\MCP\ToolNameMap;

final class McpAccessInfoAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/mcp-access-info';

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
		return 'PixelYourSite MCP access info';
	}

	/**
	 * Tool description shown to the assistant.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Explains how to use PixelYourSite tools from this MCP server: whether they are available, the usage instructions, and which ability each tool name in their descriptions refers to. Call it before the first PixelYourSite tool, and when PixelYourSite tools are missing or refused.';
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
			'properties'           => (object) array(),
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
			'required'   => array( 'external_access_enabled', 'message' ),
			'properties' => array(
				'external_access_enabled' => array( 'type' => 'boolean' ),
				'message'                 => array( 'type' => 'string' ),
				'instructions'            => array(
					'type'        => 'string',
					'description' => 'How to work with PixelYourSite tools. Follow it as the server instructions for these tools.',
				),
				'tool_names'              => array(
					'type'                 => 'object',
					'description'          => 'Tool name used in descriptions and errors → ability name to call on this server.',
					'additionalProperties' => array( 'type' => 'string' ),
				),
			),
		);
	}

	/**
	 * @param mixed $input Unused.
	 * @return array<string, mixed>
	 */
	public static function execute( $input = null ): array {
		if ( !Capabilities::isExternalAccessAllowed() ) {
			return array(
				'external_access_enabled' => false,
				'message'                 => ErrorEnvelope::externalAccessDisabled()->get_error_message(),
			);
		}

		return array(
			'external_access_enabled' => true,
			'message'                 => 'Other MCP servers may use PixelYourSite tools. They are available to users with PixelYourSite access. Tool names in their descriptions use the PixelYourSite MCP server names; `tool_names` gives the ability to call for each.',
			'instructions'            => McpServer::instructions(),
			'tool_names'              => (object) ToolNameMap::abilityIdsByToolName(),
		);
	}

	/**
	 * Any logged-in user, in any context.
	 *
	 * @param mixed $input Unused.
	 * @return bool|\WP_Error
	 */
	public static function permissionCallback( $input = null ) {
		return is_user_logged_in() ? true : ErrorEnvelope::unauthorized();
	}

	/**
	 * @return string
	 */
	protected static function externalNamingNote(): string {
		return '';
	}

	/**
	 * @return bool
	 */
	protected static function isPublic(): bool {
		return true;
	}
}
