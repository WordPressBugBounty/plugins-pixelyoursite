<?php
/**
 * `pixelyoursite/ping` — read-only health-check ability.
 * Echoes the optional `message` plus server context (version, time, user
 * id), verifying the full auth → dispatch → guard → execute pipeline
 * without a real write. Keeps its hyphen tool name (not in `ToolNameMap`)
 * and is not logged by Provenance.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class PingAbility extends AbstractAbility {

	/** Hyphen ability ID — also the public MCP tool name (no map entry). */
	public const ID = 'pixelyoursite/ping';

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
		return 'PYS MCP Ping';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Echoes the optional `message` argument back plus server context (plugin version, server time, current WP user id). Use to verify connectivity and the auth/transport pipeline.';
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
				'message' => array(
					'type'        => 'string',
					'description' => 'Optional string echoed back in the response for round-trip verification.',
					'maxLength'   => 240,
				),
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
			'required'   => array( 'ok' ),
			'properties' => array(
				'ok'             => array( 'type' => 'boolean' ),
				'echo'           => array( 'type' => 'string' ),
				'server_time'    => array( 'type' => 'integer' ),
				'plugin_version' => array( 'type' => 'string' ),
				'wp_user_id'     => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * Build the echo response.
	 *
	 * @param mixed $input Validated args (optional `message`).
	 * @return array<string, mixed>
	 */
	public static function execute( $input ): array {
		$message = '';
		if ( is_array( $input ) && isset( $input[ 'message' ] ) && is_string( $input[ 'message' ] ) ) {
			$message = $input[ 'message' ];
		}

		return array(
			'ok'             => true,
			'echo'           => $message,
			'server_time'    => time(),
			'plugin_version' => defined( 'PYS_FREE_VERSION' ) ? (string) PYS_FREE_VERSION : '',
			'wp_user_id'     => (int) get_current_user_id(),
		);
	}
}