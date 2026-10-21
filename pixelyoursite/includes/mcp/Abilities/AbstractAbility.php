<?php
/**
 * Base class for every ability. Carries the `wp_register_ability()`
 * boilerplate (category, annotations, visibility meta, callbacks);
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

use PixelYourSite\MCP\Capabilities;
use PixelYourSite\MCP\ErrorEnvelope;
use PixelYourSite\MCP\McpServer;
use PixelYourSite\MCP\ToolNameMap;

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
	 * Permission gate. First the context: outside our own MCP endpoint (and
	 * WP-CLI) a call is refused unless the "Other MCP servers" toggle is on.
	 * This is the security boundary — the visibility meta only hides the
	 * abilities. Then the current user must hold
	 * `Capabilities::REQUIRED_CAPABILITY`: other MCP servers and the core REST
	 * API authenticate anyone with `read`. Write abilities extend this with
	 * the read-only gate.
	 *
	 * @param mixed $input Tool args (some gates inspect them).
	 * @return bool|\WP_Error True to allow, or a WP_Error envelope to deny.
	 */
	public static function permissionCallback( $input = null ) {
		if ( !Capabilities::isAccessAllowedInThisContext() ) {
			return ErrorEnvelope::externalAccessDisabled();
		}
		if ( !Capabilities::currentUserCanManage() ) {
			return ErrorEnvelope::forbidden();
		}

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

		$external = static::isPublic();

		$args = array(
			'label'               => static::label(),
			'description'         => static::resolvedDescription(),
			'category'            => McpServer::ABILITY_CATEGORY,
			'input_schema'        => static::resolvedInputSchema(),
			'execute_callback'    => static::executeCallback(),
			'permission_callback' => array( static::class, 'permissionCallback' ),
			'meta'                => array(
				'annotations'  => static::annotations(),
				'public'       => $external,                     // core: seeds the channel flags
				'show_in_rest' => $external,                     // core wp-abilities/v1
				'mcp'          => array( 'public' => $external ), // MCP Adapter default server
			),
		);

		$output = static::outputSchema();
		if ( !empty( $output ) ) {
			$args[ 'output_schema' ] = $output;
		}

		wp_register_ability( static::id(), $args );
	}

	/**
	 * Whether other MCP servers and the core REST API may list the ability.
	 *
	 * @return bool
	 */
	protected static function isPublic(): bool {
		return Capabilities::isExternalAccessAllowed();
	}

	/**
	 * The description registered for the tool: the prefixes, then the
	 * subclass description.
	 *
	 * @return string
	 */
	protected static function resolvedDescription(): string {
		return implode( ' ', array_merge( static::descriptionPrefixes(), array( static::description() ) ) );
	}

	/**
	 * Texts placed ahead of the description, so clients that cut long
	 * descriptions still show them. A parent can add its own.
	 *
	 * @return string[]
	 */
	protected static function descriptionPrefixes(): array {
		$note = static::externalNamingNote();

		return '' === $note ? array() : array( $note );
	}

	/**
	 * How to read our tool names on other servers; empty while they have no
	 * access or the ability has no mapped name.
	 *
	 * @return string
	 */
	protected static function externalNamingNote(): string {
		if ( !Capabilities::isExternalAccessAllowed() || null === ToolNameMap::resolve( static::id() ) ) {
			return '';
		}

		return ToolNameMap::EXTERNAL_NAMING_NOTE;
	}

	/**
	 * The callable registered as the ability's `execute_callback`. A parent
	 * can wrap `execute()` here (e.g. the write confirmation gate).
	 *
	 * @return callable
	 */
	protected static function executeCallback(): callable {
		return array( static::class, 'execute' );
	}

	/**
	 * Hook for a parent to wrap the subclass schema (e.g. inject the required
	 * `mcp_note` field). Subclasses keep returning their schema in
	 * `inputSchema()`.
	 *
	 * Two adjustments for the core REST run endpoint (`wp-abilities/v1`):
	 *
	 * - Empty `properties` (an `stdClass` or `array()`) are dropped. The core
	 *   validator does `isset( $args['properties'][ $key ] )` for every input
	 *   key, which is a fatal on an `stdClass`; the MCP adapter strips them
	 *   anyway (`SchemaTransformer::normalize()`), so nothing is lost.
	 * - A schema with no required field gets `default: {}`: the run endpoint
	 *   (and its JS client) send no input for a GET without arguments, and
	 *   `WP_Ability::normalize_input()` substitutes only the schema default,
	 *   so without one a `type: object` schema fails validation on `null`.
	 *
	 * @return array
	 */
	protected static function resolvedInputSchema(): array {
		$schema = static::inputSchema();

		if ( array_key_exists( 'properties', $schema )
		     && ( is_object( $schema[ 'properties' ] ) || is_array( $schema[ 'properties' ] ) )
		     && empty( (array) $schema[ 'properties' ] ) ) {
			unset( $schema[ 'properties' ] );
		}

		if ( isset( $schema[ 'type' ] ) && 'object' === $schema[ 'type' ]
		     && empty( $schema[ 'required' ] ) && !array_key_exists( 'default', $schema ) ) {
			$schema[ 'default' ] = (object) array();
		}

		return $schema;
	}
}