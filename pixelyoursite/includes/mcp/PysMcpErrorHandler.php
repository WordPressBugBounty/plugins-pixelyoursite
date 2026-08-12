<?php
/**
 * MCP error handler that keeps the PHP error log clean of EXPECTED tool
 * rejections. The adapter logs every WP_Error a tool returns at `error` level
 * (ToolsHandler), but our tools use WP_Error with a 4xx `status` as the normal
 * protocol way to reject invalid / guarded input (e.g. "platform has no params
 * toggle", "addon inactive"). Those are client-input outcomes, not server
 * faults, so they must not spam the log. Genuine faults (5xx, no status,
 * permission failures, unexpected-type handler errors) are logged exactly like
 * the default ErrorLogMcpErrorHandler.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PYS_PRO_GLOBAL\WP\MCP\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface;

final class PysMcpErrorHandler implements McpErrorHandlerInterface {

	/**
	 * Log a message unless it is an expected client-input rejection.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data (tool_name, error_code, error_data, …).
	 * @param string $type    Log type ('error', 'info', 'debug', …). Default 'error'.
	 * @return void
	 */
	public function log( string $message, array $context = array(), string $type = 'error' ): void {
		// Drop expected 4xx tool rejections; let everything else through.
		if ( 'error' === $type && self::isExpectedClientError( $context ) ) {
			return;
		}

		$user_id     = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$log_message = sprintf(
			'[%s] %s | Context: %s | User ID: %d',
			strtoupper( $type ),
			$message,
			wp_json_encode( $context ),
			$user_id
		);
		error_log( $log_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Whether the logged error is an expected client-input rejection — a WP_Error
	 * whose data carries an HTTP 4xx status. Server faults (5xx) and errors with
	 * no status are NOT expected and stay in the log.
	 *
	 * @param array $context Log context; may hold `error_data` from the WP_Error.
	 * @return bool
	 */
	private static function isExpectedClientError( array $context ): bool {
		if ( !isset( $context[ 'error_data' ] ) || !is_array( $context[ 'error_data' ] ) ) {
			return false;
		}
		if ( !isset( $context[ 'error_data' ][ 'status' ] ) ) {
			return false;
		}
		$status = (int) $context[ 'error_data' ][ 'status' ];

		return $status >= 400 && $status < 500;
	}
}
