<?php
/**
 * Wires `RateLimit` / `LoopGuard` / `ErrorEnvelope` into the request hooks:
 *  1. `rest_pre_dispatch` — throttle per IP / token before the adapter
 *     casts the permission result to bool, so we can return a shaped 429.
 *  2. `mcp_adapter_pre_tool_call` — check loop fingerprint + repeated-
 *     failure counter; a `WP_Error` short-circuits the tool call.
 *  3. `mcp_adapter_tool_call_result` — drive the failure counter (success
 *     clears it, error increments).
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class RequestGuard {

	/** Per-IP requests allowed inside `IP_RATE_LIMIT_WINDOW`. */
	public const IP_RATE_LIMIT = 60;

	/** Per-IP rate-limit window in seconds. */
	public const IP_RATE_LIMIT_WINDOW = 60;

	/** Per-token requests allowed inside `TOKEN_RATE_LIMIT_WINDOW`. */
	public const TOKEN_RATE_LIMIT = 120;

	/** Per-token rate-limit window in seconds. */
	public const TOKEN_RATE_LIMIT_WINDOW = 60;

	/**
	 * Per-tool rate limits, stricter than the global IP/token throttle:
	 * MCP tool name => [ max calls, window seconds ]. Keyed per tool + IP.
	 * `scan_page_for_form_fields` makes outbound own-domain HTTP fetches, so
	 * its description promises ~10/min — enforced here.
	 */
	private const PER_TOOL_LIMITS = array(
		'scan_page_for_form_fields' => array( 10, 60 ),
	);

	/** REST namespace + route of our MCP endpoint — must match `McpServer`. */
	private string $routeNamespace;
	private string $route;

	/** MCP server ID — used to scope the tool-level filters to OUR server. */
	private string $serverId;

	/**
	 * Store the endpoint coordinates the guards match against.
	 *
	 * @param string $routeNamespace REST namespace of the MCP endpoint.
	 * @param string $route          REST route of the MCP endpoint.
	 * @param string $serverId       Adapter server ID to scope tool filters to.
	 */
	public function __construct( string $routeNamespace, string $route, string $serverId ) {
		$this->routeNamespace = $routeNamespace;
		$this->route          = $route;
		$this->serverId       = $serverId;
	}

	/**
	 * Register all three hooks. Idempotent — `add_filter` will dedupe by
	 * (hook, callable), and our callable is the same instance every call.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'rest_pre_dispatch', array( $this, 'gateRequest' ), 10, 3 );
		add_filter( 'mcp_adapter_pre_tool_call', array( $this, 'gateToolCall' ), 10, 4 );
		add_filter( 'mcp_adapter_tool_call_result', array( $this, 'recordToolResult' ), 10, 5 );
		add_filter( 'rest_post_dispatch', array( $this, 'addAuthHeaders' ), 10, 3 );
	}

	// ---------------------------------------------------------- rest_pre_dispatch

	/**
	 * Transport-layer throttle. Bails out immediately for non-MCP routes.
	 * Returning a `WP_Error` short-circuits dispatch — WP renders it as the
	 * JSON body with the HTTP status from `data.status`.
	 *
	 * @param mixed            $result  Existing result if another filter already short-circuited.
	 * @param \WP_REST_Server  $server  WP REST server.
	 * @param \WP_REST_Request $request The incoming request.
	 * @return mixed Untouched `$result`, or `WP_Error` to short-circuit.
	 */
	public function gateRequest( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}
		if ( !$this->isMcpRoute( $request ) ) {
			return $result;
		}

		$ip = $this->clientIp();
		if ( '' !== $ip ) {
			$ipBucket = 'ip_' . substr( sha1( $ip ), 0, 16 );
			if ( !RateLimit::checkAndIncrement( $ipBucket, self::IP_RATE_LIMIT, self::IP_RATE_LIMIT_WINDOW ) ) {
				return ErrorEnvelope::rateLimitExceeded();
			}
		}

		$tokenHash = $this->bearerTokenHash( $request );
		if ( null !== $tokenHash ) {
			$tokenBucket = 'tok_' . substr( $tokenHash, 0, 16 );
			if ( !RateLimit::checkAndIncrement(
				$tokenBucket,
				self::TOKEN_RATE_LIMIT,
				self::TOKEN_RATE_LIMIT_WINDOW
			) ) {
				return ErrorEnvelope::rateLimitExceeded();
			}
		}

		return $result;
	}

	// ---------------------------------------------------------- rest_post_dispatch

	/**
	 * Add a `WWW-Authenticate` header to auth-failure responses on the MCP route.
	 *
	 * Without it an MCP client (claude.ai custom connector, Claude Desktop, …)
	 * receives a bare 401/403 and cannot tell whether to show a token-entry form
	 * or give up. The header declares this server as static-Bearer.
	 *
	 * @param mixed            $response The response object.
	 * @param \WP_REST_Server  $server   WP REST server.
	 * @param \WP_REST_Request $request  The incoming request.
	 * @return mixed Untouched `$response`, or the response with headers added.
	 */
	public function addAuthHeaders( $response, $server, $request ) {
		if ( !( $response instanceof \WP_REST_Response ) ) {
			return $response;
		}
		if ( !$this->isMcpRoute( $request ) ) {
			return $response;
		}

		$status = $response->get_status();
		if ( 401 !== $status && 403 !== $status ) {
			return $response;
		}

		$authHeader = ( is_object( $request ) && method_exists( $request, 'get_header' ) )
			? (string) $request->get_header( 'authorization' )
			: '';
		$hasBearer  = '' !== $authHeader && (bool) preg_match( '/^\s*Bearer\s+\S+/i', $authHeader );

		if ( $hasBearer ) {
			$response->header( 'WWW-Authenticate', 'Bearer realm="PixelYourSite MCP", error="invalid_token"' );
		} else {
			$response->set_status( 401 );
			$response->header( 'WWW-Authenticate', 'Bearer realm="PixelYourSite MCP"' );
		}

		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );

		return $response;
	}

	// ----------------------------------------------------- mcp_adapter_pre_tool_call

	/**
	 * Tool-layer guard, just before execution. Returns a `WP_Error` (loop or
	 * repeated-failure) that the adapter surfaces as an error tool result.
	 * Scoped to our server only.
	 *
	 * @param array|\WP_Error                       $args      Pre-execution args (may already be WP_Error from earlier filter).
	 * @param string                                $toolName  Tool name being called.
	 * @param mixed                                 $mcpTool   Adapter tool wrapper — opaque here.
	 * @param \PYS_PRO_GLOBAL\WP\MCP\Core\McpServer $mcpServer Adapter server instance.
	 * @return array|\WP_Error
	 */
	public function gateToolCall( $args, string $toolName, $mcpTool, $mcpServer ) {
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		if ( !$this->isOurServer( $mcpServer ) ) {
			return $args;
		}

		$ip       = $this->clientIp();
		$callArgs = is_array( $args ) ? $args : array();

		// Per-tool rate-limit (stricter than the global IP/token throttle).
		if ( isset( self::PER_TOOL_LIMITS[ $toolName ] ) ) {
			[ $limit, $window ] = self::PER_TOOL_LIMITS[ $toolName ];
			$bucket             = 'pt_' . substr( sha1( $toolName . '|' . $ip ), 0, 16 );
			if ( !RateLimit::checkAndIncrement( $bucket, $limit, $window ) ) {
				return ErrorEnvelope::rateLimitExceeded();
			}
		}

		if ( !LoopGuard::checkRepeatedFailure( $toolName, $ip ) ) {
			return ErrorEnvelope::repeatedFailure();
		}

		if ( !LoopGuard::checkFingerprint( $toolName, $callArgs, $ip ) ) {
			return ErrorEnvelope::loopDetected();
		}

		return $args;
	}

	// ----------------------------------------------- mcp_adapter_tool_call_result

	/**
	 * Tool-result recorder. Feeds the per-tool/IP failure counter that
	 * `gateToolCall` checks on the next call; success clears it. Error
	 * detection is conservative (WP_Error, `{success:false}`, `isError`).
	 * Always returns `$result` unchanged.
	 *
	 * @param mixed                                 $result    Tool execution result.
	 * @param array                                 $args      Tool args.
	 * @param string                                $toolName  Tool name.
	 * @param mixed                                 $mcpTool   Adapter tool wrapper — opaque here.
	 * @param \PYS_PRO_GLOBAL\WP\MCP\Core\McpServer $mcpServer Adapter server instance.
	 * @return mixed The untouched `$result`.
	 */
	public function recordToolResult( $result, $args, string $toolName, $mcpTool, $mcpServer ) {
		if ( !$this->isOurServer( $mcpServer ) ) {
			return $result;
		}

		$isError       = false;
		$isClientError = false;
		if ( is_wp_error( $result ) ) {
			$isError = true;
			$data    = $result->get_error_data();
			$status  = ( is_array( $data ) && isset( $data[ 'status' ] ) ) ? (int) $data[ 'status' ] : 0;
			$isClientError = ( $status >= 400 && $status < 500 );
		} elseif ( is_array( $result ) ) {
			if ( array_key_exists( 'success', $result ) && false === $result[ 'success' ]
			     && !empty( $result[ 'error' ] ) ) {
				$isError = true;
			} elseif ( !empty( $result[ 'isError' ] ) ) {
				$isError = true;
			}
		}

		if ( !$isError ) {
			LoopGuard::recordResult( $toolName, $this->clientIp(), true );
		} elseif ( !$isClientError ) {
			LoopGuard::recordResult( $toolName, $this->clientIp(), false );
		}

		if ( $isError ) {
			$this->logError( $toolName, $result );
		}

		return $result;
	}

	// --------------------------------------------------------------- internal

	/**
	 * True when the request path matches our MCP endpoint. A string match,
	 * since the route isn't resolved yet at `rest_pre_dispatch` time.
	 *
	 * @param mixed $request The incoming request.
	 * @return bool
	 */
	private function isMcpRoute( $request ): bool {
		if ( !is_object( $request ) || !method_exists( $request, 'get_route' ) ) {
			return false;
		}
		$route    = (string) $request->get_route();
		$expected = '/' . trim( $this->routeNamespace, '/' ) . '/' . trim( $this->route, '/' );

		return $route === $expected;
	}

	/**
	 * Whether `$mcpServer` is the server these guards apply to.
	 *
	 * @param mixed $mcpServer Adapter server instance.
	 * @return bool
	 */
	private function isOurServer( $mcpServer ): bool {
		if ( !is_object( $mcpServer ) || !method_exists( $mcpServer, 'get_server_id' ) ) {
			return false;
		}

		return $this->serverId === $mcpServer->get_server_id();
	}

	/**
	 * Best-effort client IP from `REMOTE_ADDR` (no `X-Forwarded-For` trust).
	 * A shared IP is fine — the per-token rate-limit picks up the slack.
	 *
	 * @return string Client IP, or `0.0.0.0` when unknown.
	 */
	private function clientIp(): string {
		$ip = isset( $_SERVER[ 'REMOTE_ADDR' ] ) ? (string) $_SERVER[ 'REMOTE_ADDR' ] : '';

		return '' === $ip ? '0.0.0.0' : $ip;
	}

	/**
	 * SHA-256 of the bearer token, or null if not a Bearer header. Hashed so
	 * the bucket key never contains the raw token.
	 *
	 * @param mixed $request The incoming request.
	 * @return string|null Token hash, or null when no Bearer token.
	 */
	private function bearerTokenHash( $request ): ?string {
		if ( !is_object( $request ) || !method_exists( $request, 'get_header' ) ) {
			return null;
		}
		$header = $request->get_header( 'authorization' );
		if ( !is_string( $header ) || '' === $header ) {
			return null;
		}
		if ( !preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches ) ) {
			return null;
		}

		return hash( 'sha256', $matches[ 1 ] );
	}

	/**
	 * Write a failed tool call to PYS's own log (silent — `PYS_Logger::error`
	 * no-ops unless the admin enabled logging). Best-effort; never throws.
	 *
	 * @param string $toolName Tool that errored.
	 * @param mixed  $result   The error result (WP_Error or array).
	 * @return void
	 */
	private function logError( string $toolName, $result ): void {
		if ( ! function_exists( '\\PixelYourSite\\PYS' ) ) {
			return;
		}
		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_code() . ' ' . $result->get_error_message();
		} elseif ( is_array( $result ) && isset( $result[ 'error' ] ) && is_string( $result[ 'error' ] ) ) {
			$message = $result[ 'error' ];
		} else {
			$message = 'tool returned an error result';
		}

		try {
			$log = \PixelYourSite\PYS()->getLog();
			if ( is_object( $log ) && method_exists( $log, 'error' ) ) {
				$log->error( sprintf( '[MCP] tool "%s" failed: %s', $toolName, $message ) );
			}
		} catch ( \Throwable $e ) {
			// Logging must never break the request.
		}
	}
}