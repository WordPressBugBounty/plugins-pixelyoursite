<?php
/**
 * Capability checks for MCP tools (Free variant). Free has no Pro-feature gate
 * — every tool Free ships is fully available — so the only runtime gate is the
 * admin read-only toggle. `isPro()` stays (returning false) so files shared
 * with Pro keep compiling, but writes are gated ONLY on read-only. Also holds
 * the external-access gate (`isAccessAllowedInThisContext()`).
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
	 * PYS Settings option key for the "Other MCP servers" toggle: may abilities
	 * be used outside our own MCP endpoint? Default off. Registered alongside
	 * the read-only option.
	 */
	public const OPTION_EXTERNAL_ACCESS_ENABLED = 'mcp_external_access_enabled';

	/**
	 * WP capability every ability requires from the current user — the same
	 * one that gates the PYS admin pages and the site-profile REST routes.
	 */
	public const REQUIRED_CAPABILITY = 'manage_pys';

	/**
	 * Does the current user hold the capability every ability requires?
	 * On our own endpoint `Auth::verify()` sets the token owner as the current
	 * user, but when external access is allowed abilities are also usable by
	 * other MCP servers on the site and by the core abilities REST API, whose
	 * transports only require `read`. The capability therefore has to be
	 * enforced here, in the ability itself.
	 *
	 * @return bool
	 */
	public static function currentUserCanManage(): bool {
		return is_user_logged_in() && current_user_can( self::REQUIRED_CAPABILITY );
	}

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
	 * Has the admin allowed abilities to be used outside our own MCP endpoint
	 * (the official MCP Adapter, other plugins' MCP servers, the core
	 * abilities REST API)? Fails closed: false when PYS isn't loaded or the
	 * option is missing.
	 *
	 * @return bool
	 */
	public static function isExternalAccessAllowed(): bool {
		if ( !function_exists( '\\PixelYourSite\\PYS' ) ) {
			return false;
		}

		return self::toBool( \PixelYourSite\PYS()->getOption( self::OPTION_EXTERNAL_ACCESS_ENABLED, false ) );
	}

	/**
	 * Toggle external access via `PYS()->updateOptions()`.
	 *
	 * @param bool $enabled Whether other MCP servers may use the abilities.
	 * @return bool True on success, false if PYS isn't loaded.
	 */
	public static function setExternalAccessAllowed( bool $enabled ): bool {
		if ( !function_exists( '\\PixelYourSite\\PYS' ) ) {
			return false;
		}
		\PixelYourSite\PYS()->updateOptions( array( self::OPTION_EXTERNAL_ACCESS_ENABLED => $enabled ) );

		return true;
	}

	/**
	 * Is the current ability call coming through a channel PYS controls?
	 *  - our MCP endpoint: `Auth::verify()` ran and set the active token;
	 *  - WP-CLI: an operator-level shell.
	 * Everything else is external: the official MCP Adapter, other plugins'
	 * MCP servers, the `wp-abilities/v1` REST API, admin-ajax, cron and
	 * direct PHP calls.
	 *
	 * @return bool
	 */
	public static function isTrustedContext(): bool {
		if ( null !== Auth::activeTokenId() ) {
			return true;
		}

		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * May an ability call proceed in the current context at all? Checked
	 * first by every ability's permission callback.
	 *
	 * @return bool
	 */
	public static function isAccessAllowedInThisContext(): bool {
		return self::isTrustedContext() || self::isExternalAccessAllowed();
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
