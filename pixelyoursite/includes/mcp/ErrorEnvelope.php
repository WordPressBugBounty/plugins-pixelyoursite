<?php
/**
 * Standardized error envelopes — the single source of truth for the exact
 * strings the system prompt tells Claude to recognise (`"Pro required."`,
 * `"Possible loop detected."`, …). Don't drift a message without also
 * updating the prompt.
 * Each returns a `WP_Error` with a `data.status`. The adapter renders it
 * either as a 4xx JSON body (transport layer) or as a 200 `CallToolResult`
 * with `isError: true` (tool layer). Code slugs are internal — Claude only
 * sees the messages.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class ErrorEnvelope {

	/**
	 * 401 — bearer token missing, malformed, or unknown.
	 *
	 * @return \WP_Error
	 */
	public static function unauthorized(): \WP_Error {
		return new \WP_Error(
			'pys_mcp_unauthorized', 'Unauthorized.', array( 'status' => 401 )
		);
	}

	/**
	 * 403 — write tool called on PYS Free or with read-only mode toggled on.
	 *
	 * @return \WP_Error
	 */
	public static function proRequired(): \WP_Error {
		return new \WP_Error(
			'pys_mcp_pro_required', 'Pro required.', array( 'status' => 403 )
		);
	}

	/**
	 * 403 — admin has toggled MCP read-only mode; surface separately from `proRequired`.
	 *
	 * @return \WP_Error
	 */
	public static function readOnly(): \WP_Error {
		return new \WP_Error(
			'pys_mcp_read_only', 'Read-only mode is enabled.', array( 'status' => 403 )
		);
	}

	/**
	 * 429 — IP or per-token window limit exceeded.
	 *
	 * @return \WP_Error
	 */
	public static function rateLimitExceeded(): \WP_Error {
		return new \WP_Error(
			'pys_mcp_rate_limit', 'Rate limit exceeded.', array( 'status' => 429 )
		);
	}

	/**
	 * 429 — same tool + args + IP repeated within the loop fingerprint window.
	 *
	 * @return \WP_Error
	 */
	public static function loopDetected(): \WP_Error {
		return new \WP_Error(
			'pys_mcp_loop_detected', 'Possible loop detected.', array( 'status' => 429 )
		);
	}

	/**
	 * 400 — same tool failed too many times in a row from the same IP.
	 *
	 * @return \WP_Error
	 */
	public static function repeatedFailure(): \WP_Error {
		return new \WP_Error(
			'pys_mcp_repeated_failure', 'Stop retrying and report the issue to the user.', array( 'status' => 400 )
		);
	}
}