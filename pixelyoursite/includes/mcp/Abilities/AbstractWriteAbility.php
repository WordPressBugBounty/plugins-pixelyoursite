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
 *  - **Confirmation gate** — `confirm`-flag tools write only with the
 *    `confirmation_id` their preview issued.
 *  - **External-call log** — `executeAndLog()` records calls that did not
 *    come through our MCP server (it logs through the adapter filter).
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\Auth;
use PixelYourSite\MCP\Capabilities;
use PixelYourSite\MCP\ErrorEnvelope;
use PixelYourSite\MCP\Provenance;

abstract class AbstractWriteAbility extends AbstractAbility {

	/** Default `mcp_note` description; injected only if the subclass omits one. */
	private const MCP_NOTE_DESCRIPTION = 'Required. One-sentence summary of what is being changed and why. Recorded verbatim in the provenance log.';

	/** Length cap (also bounds the Storage row size). */
	private const MCP_NOTE_MAX_LENGTH = 240;

	/** Storage TTL key prefix and lifetime of a write confirmation. */
	private const CONFIRM_PREFIX = 'confirm_write_';
	private const CONFIRM_TTL    = 600;

	/** Told to the model with every preview: the preview is not the user's yes. */
	public const USER_ACTION_REQUIRED = 'STOP — nothing was written. Show the user the pending changes (and any notes) from this preview and ask whether to apply them, then END YOUR TURN and wait for their reply. The user\'s original request is NOT the confirmation, even when this preview matches it exactly. Only after the user explicitly agrees in a new message, call this tool again with the same arguments plus `confirm: true` and this `confirmation_id`.';

	/**
	 * Capability + read-only gate. The capability check is inherited from
	 * {@see AbstractAbility}; on top of it Free has only the admin read-only
	 * toggle — there is no Pro requirement.
	 *
	 * @param mixed $input Tool args (unused — gate is global).
	 * @return true|\WP_Error
	 */
	public static function permissionCallback( $input = null ) {
		$base = parent::permissionCallback( $input );
		if ( true !== $base ) {
			return $base;
		}
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
	 * Every write tool with a plain `confirm` flag gets the shared token gate.
	 *
	 * @return bool
	 */
	protected static function usesConfirmGate(): bool {
		$schema = static::inputSchema();

		return isset( $schema[ 'properties' ][ 'confirm' ] ) && !isset( $schema[ 'properties' ][ 'confirmation_id' ] );
	}

	/**
	 * The confirmation rule goes first.
	 *
	 * @return string[]
	 */
	protected static function descriptionPrefixes(): array {
		$prefixes = parent::descriptionPrefixes();
		if ( static::usesConfirmGate() ) {
			array_unshift( $prefixes, '**Confirmation:** the preview returns a `confirmation_id`; the write needs `confirm: true` plus that id with the same arguments, and may be sent only after the user explicitly agreed to the preview in a new message (their original request is not the confirmation).' );
		}

		return $prefixes;
	}

	/**
	 * `execute_callback` for writes: runs the tool through the confirmation
	 * gate and logs calls that did not come through our server (other MCP
	 * servers, the core REST API, WP-CLI). Calls through our server, over
	 * HTTP or STDIO, are logged by the adapter filter, so they are skipped
	 * here to avoid a second row.
	 *
	 * @param mixed $input Validated arg array.
	 * @return array|\WP_Error
	 * @throws \Throwable Re-thrown after logging; core turns it into a WP_Error.
	 */
	public static function executeAndLog( $input = null ) {
		$is_external = null === Auth::activeTokenId() && !Provenance::isInOurToolCall();

		try {
			$result = static::gatedExecute( $input );
		} catch ( \Throwable $e ) {
			if ( $is_external ) {
				Provenance::recordExternalWrite( static::id(), $input, new \WP_Error( 'exception', $e->getMessage() ) );
			}
			throw $e;
		}

		if ( $is_external ) {
			Provenance::recordExternalWrite( static::id(), $input, $result );
		}

		return $result;
	}

	/**
	 * @return callable
	 */
	protected static function executeCallback(): callable {
		return array( static::class, 'executeAndLog' );
	}

	/**
	 * Two-step write gate shared by the `confirm`-flag tools. A preview hands
	 * out a single-use `confirmation_id` bound to the exact arguments; a write
	 * needs `confirm: true` plus that id, otherwise the preview is returned
	 * again and nothing is written.
	 *
	 * @param mixed $input Tool args.
	 * @return mixed
	 */
	public static function gatedExecute( $input ) {
		if ( !static::usesConfirmGate() ) {
			return static::execute( $input );
		}
		if ( !is_array( $input ) ) {
			$input = array();
		}

		$id          = isset( $input[ 'confirmation_id' ] ) && is_string( $input[ 'confirmation_id' ] ) ? $input[ 'confirmation_id' ] : '';
		$fingerprint = self::confirmFingerprint( $input );
		unset( $input[ 'confirmation_id' ] );

		if ( !empty( $input[ 'confirm' ] ) ) {
			$stored = '' !== $id ? \PixelYourSite\MCP\Storage::getTtl( self::CONFIRM_PREFIX . $id ) : null;

			if ( is_string( $stored ) && hash_equals( $fingerprint, $stored ) ) {
				\PixelYourSite\MCP\Storage::deleteTtl( self::CONFIRM_PREFIX . $id );

				return static::execute( $input );
			}

			unset( $input[ 'confirm' ] );
			$preview = static::execute( $input );

			if ( is_array( $preview ) && !empty( $preview[ 'confirmation_required' ] ) ) {
				$preview = self::withConfirmation( $preview, $fingerprint );
				$preview[ 'confirmation_error' ] = '' === $id
					? '`confirm: true` was sent without a `confirmation_id`, so NOTHING was written. Every write needs the user\'s explicit agreement to a preview first.'
					: 'The `confirmation_id` is unknown, expired, already used or was issued for different arguments or another user, so NOTHING was written.';
			}

			return $preview;
		}

		$preview = static::execute( $input );

		return is_array( $preview ) && !empty( $preview[ 'confirmation_required' ] )
			? self::withConfirmation( $preview, $fingerprint )
			: $preview;
	}

	/**
	 * @param array  $preview
	 * @param string $fingerprint
	 * @return array
	 */
	private static function withConfirmation( array $preview, string $fingerprint ): array {
		$id = wp_generate_password( 20, false, false );
		\PixelYourSite\MCP\Storage::setTtl( self::CONFIRM_PREFIX . $id, $fingerprint, self::CONFIRM_TTL );

		$preview[ 'confirmation_id' ]      = $id;
		$preview[ 'user_action_required' ] = self::USER_ACTION_REQUIRED;

		return $preview;
	}

	/**
	 * Hash of the user, the tool and its business arguments (not the gate
	 * fields), so only the user who saw the preview can confirm it.
	 *
	 * @param array $input
	 * @return string
	 */
	private static function confirmFingerprint( array $input ): string {
		unset( $input[ 'confirm' ], $input[ 'confirmation_id' ], $input[ 'mcp_note' ] );

		return hash( 'sha256', get_current_user_id() . '|' . static::id() . '|' . wp_json_encode( self::sortKeys( $input ) ) );
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private static function sortKeys( $value ) {
		if ( !is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $k => $v ) {
			$value[ $k ] = self::sortKeys( $v );
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}

		return $value;
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

		if ( static::usesConfirmGate() ) {
			$schema[ 'properties' ][ 'confirmation_id' ] = array(
				'type'        => 'string',
				'description' => 'Required with `confirm: true`: the `confirmation_id` from the preview of these exact arguments. Send it only after the user has explicitly agreed to the preview in a new message — their original request does not count. `confirm: true` without a valid id writes nothing and returns a fresh preview.',
			);
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