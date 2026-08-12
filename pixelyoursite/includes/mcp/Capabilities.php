<?php
/**
 * Capability checks for MCP tools (Free variant). Free has no Pro-feature gate
 * — every tool Free ships is fully available — so the only runtime gate is the
 * admin read-only toggle. `isPro()` stays (returning false) so files shared
 * with Pro keep compiling, but writes are gated ONLY on read-only.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Capabilities {

	/**
	 * PYS Settings option key for the read-only toggle. Stored in
	 * `wp_pys_options` like every other PYS setting and registered in
	 * `McpServer::boot()` on `init` (after PYS loads its schema).
	 */
	public const OPTION_READ_ONLY_ENABLED = 'mcp_read_only_enabled';

	/**
	 * Free is not the Pro variant. Kept so code paths shared with Pro that
	 * reference `isPro()` still resolve; Free write gating does NOT depend on it.
	 *
	 * @return bool Always false in Free.
	 */
	public static function isPro(): bool {
		return false;
	}

	/**
	 * Has the admin enabled read-only mode? When on, read tools work and write
	 * tools return the read-only envelope, letting a site owner run audit-only
	 * sessions. Default false.
	 *
	 * @return bool
	 */
	public static function isReadOnly(): bool {
		if ( !function_exists( '\\PixelYourSite\\PYS' ) ) {
			return false;
		}

		return self::toBool( \PixelYourSite\PYS()->getOption( self::OPTION_READ_ONLY_ENABLED, false ) );
	}

	/**
	 * Toggle read-only mode via `PYS()->updateOptions()`.
	 *
	 * @param bool $enabled Whether read-only mode should be on.
	 * @return bool True on success, false if PYS isn't loaded.
	 */
	public static function setReadOnly( bool $enabled ): bool {
		if ( !function_exists( '\\PixelYourSite\\PYS' ) ) {
			return false;
		}
		\PixelYourSite\PYS()->updateOptions( array( self::OPTION_READ_ONLY_ENABLED => $enabled ) );

		return true;
	}

	/**
	 * Are writes allowed in the current call? In Free this is gated ONLY on the
	 * read-only toggle — there is no Pro requirement.
	 *
	 * @return bool
	 */
	public static function isWriteAllowed(): bool {
		return !self::isReadOnly();
	}

	/**
	 * Normalize raw option value to boolean. wp_pys_options stores checkbox
	 * values as `'1'` / `'0'` strings; some callers may pass real booleans.
	 *
	 * @param mixed $value Raw option value.
	 * @return bool
	 */
	private static function toBool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return '1' === $value || 'true' === $value;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		return false;
	}
}
