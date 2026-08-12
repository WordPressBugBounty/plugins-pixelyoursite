<?php
/**
 * Base class for every ability. Carries the `wp_register_ability()`
 * boilerplate (category, annotations, `meta.mcp.public`, callbacks);
 * subclasses just define id / label / description / schemas / logic.
 * Defaults to `permissionCallback => true` and `readonly: true`;
 * {@see AbstractWriteAbility} overrides both for writes.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\McpServer;

abstract class AbstractAbility {

	/**
	 * Hyphen-only ability ID (`pixelyoursite/<slug>`).
	 *
	 * @return string
	 */
	abstract public static function id(): string;

	/**
	 * Display label (admin / tools/list `title` annotation).
	 *
	 * @return string
	 */
	abstract public static function label(): string;

	/**
	 * Tool description shown to Claude in tools/list.
	 *
	 * @return string
	 */
	abstract public static function description(): string;

	/**
	 * JSON-Schema for `tools/call` arguments (object root +
	 * `additionalProperties: false`).
	 *
	 * @return array
	 */
	abstract public static function inputSchema(): array;

	/**
	 * JSON-Schema for the return shape. Return [] to omit `output_schema`.
	 *
	 * @return array
	 */
	abstract public static function outputSchema(): array;

	/**
	 * Business logic. Receive the validated args, return either a plain
	 * array (success) or a `\WP_Error` (the adapter wraps it as a
	 * `CallToolResult{isError:true}` with the message as content).
	 *
	 * @param mixed $input Validated arg array.
	 * @return array|\WP_Error
	 */
	abstract public static function execute( $input );

	/**
	 * Permission gate. Default — open (transport Bearer check is enough).
	 * Write abilities override to consult {@see Capabilities}.
	 *
	 * @param mixed $input Tool args (some gates inspect them).
	 * @return bool|\WP_Error True to allow, or a WP_Error envelope to deny.
	 */
	public static function permissionCallback( $input = null ) {
		return true;
	}

	/**
	 * MCP tool annotations (`meta.annotations`). Default `readonly:true`.
	 * Write abilities override to `destructive:true`.
	 *
	 * @return array
	 */
	protected static function annotations(): array {
		return array( 'readonly' => true );
	}

	/**
	 * Sanitise a user-derived string before putting it in a tool response —
	 * defence against prompt injection via site content (page DOM, product /
	 * campaign names, custom-event titles). Strips ALL HTML/script tags and,
	 * by default, truncates to 200 chars. Pass `$maxLen = 0` to skip truncation
	 * for values that double as exact lookup keys (e.g. an attribution value
	 * fed back into a drill query). Apply ONLY to user-controlled free text —
	 * never to enums, statuses, numbers, IDs or PYS-generated strings.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $maxLen Max length; 0 = no truncation.
	 * @return string
	 */
	protected static function sanitiseUserString( $value, int $maxLen = 200 ): string {
		$stripped = wp_strip_all_tags( (string) $value );
		if ( $maxLen > 0 ) {
			$stripped = function_exists( 'mb_substr' )
				? mb_substr( $stripped, 0, $maxLen )
				: substr( $stripped, 0, $maxLen );
		}
		return $stripped;
	}

	/**
	 * Hook from `wp_abilities_api_init`. Idempotent. Late-static-binding
	 * dispatches every `static::*` call to the concrete subclass.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( !function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$args = array(
			'label'               => static::label(),
			'description'         => static::description(),
			'category'            => McpServer::ABILITY_CATEGORY,
			'input_schema'        => static::resolvedInputSchema(),
			'execute_callback'    => array( static::class, 'execute' ),
			'permission_callback' => array( static::class, 'permissionCallback' ),
			'meta'                => array(
				'annotations' => static::annotations(),
				'mcp'         => array( 'public' => true ),
			),
		);

		$output = static::outputSchema();
		if ( !empty( $output ) ) {
			$args[ 'output_schema' ] = $output;
		}

		wp_register_ability( static::id(), $args );
	}

	/**
	 * Hook for a parent to wrap the subclass schema (e.g. inject the required
	 * `mcp_note` field). Subclasses keep returning their schema in
	 * `inputSchema()`.
	 *
	 * @return array
	 */
	protected static function resolvedInputSchema(): array {
		return static::inputSchema();
	}
}