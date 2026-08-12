<?php
/**
 * Base class for write abilities (the `set_*` tools). Adds the three things
 * every write tool shares:
 *  - **Write gate** — `permissionCallback` returns `readOnly()` when read-only
 *    mode is on, otherwise `true`. (Free has no Pro gate — every tool it ships
 *    is fully available; writes are blocked only by the read-only toggle.)
 *  - **Mandatory `mcp_note`** — injected into the schema + `required` by
 *    `resolvedInputSchema()`; Provenance reads it from the call.
 *  - **`destructive:true` annotation** — so Provenance logs the call.
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

abstract class AbstractWriteAbility extends AbstractAbility {

	/** Default `mcp_note` description; injected only if the subclass omits one. */
	private const MCP_NOTE_DESCRIPTION = 'Required. One-sentence summary of what is being changed and why. Recorded verbatim in the provenance log.';

	/** Length cap (also bounds the Storage row size). */
	private const MCP_NOTE_MAX_LENGTH = 240;

	/**
	 * Read-only gate. In Free the only write gate is the admin read-only
	 * toggle — there is no Pro requirement.
	 *
	 * @param mixed $input Tool args (unused — gate is global).
	 * @return true|\WP_Error
	 */
	public static function permissionCallback( $input = null ) {
		if ( Capabilities::isReadOnly() ) {
			return ErrorEnvelope::readOnly();
		}

		return true;
	}

	/**
	 * Annotations — declares this tool as a write so Provenance logs it.
	 *
	 * @return array
	 */
	protected static function annotations(): array {
		return array( 'destructive' => true );
	}

	/**
	 * Inject `mcp_note` into the subclass's schema if missing. Subclasses
	 * keep their `inputSchema()` focused on business arguments.
	 *
	 * @return array Schema with `mcp_note` added to properties + required.
	 */
	protected static function resolvedInputSchema(): array {
		$schema = static::inputSchema();
		if ( !is_array( $schema ) ) {
			$schema = array( 'type' => 'object', 'properties' => array() );
		}
		if ( !isset( $schema[ 'properties' ] ) || !is_array( $schema[ 'properties' ] ) ) {
			$schema[ 'properties' ] = array();
		}

		if ( !isset( $schema[ 'properties' ][ 'mcp_note' ] ) ) {
			$schema[ 'properties' ][ 'mcp_note' ] = array(
				'type'        => 'string',
				'description' => self::MCP_NOTE_DESCRIPTION,
				'minLength'   => 1,
				'maxLength'   => self::MCP_NOTE_MAX_LENGTH,
			);
		}

		$required = isset( $schema[ 'required' ] )
		            && is_array(
			            $schema[ 'required' ]
		            ) ? $schema[ 'required' ] : array();
		if ( !in_array( 'mcp_note', $required, true ) ) {
			$required[] = 'mcp_note';
		}
		$schema[ 'required' ] = array_values( $required );

		return $schema;
	}
}