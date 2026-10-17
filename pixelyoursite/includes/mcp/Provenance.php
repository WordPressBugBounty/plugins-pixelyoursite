<?php
/**
 * Append-only audit trail of every write-tool call, surfaced in the
 * Settings tab ("who / what / when changed our pixel config?"). Each row
 * holds `ts`, `tool`, `mcp_note`, `actor_ip`, `token`, `result_status` and
 * `source`; stored via {@see Storage} keyed by microtime (lex-sort =
 * time-sort), FIFO-trimmed. Calls through our endpoint are logged from the
 * adapter filter when the tool's annotations mark it as a write
 * (`readOnlyHint === false` OR `destructiveHint === true`); write calls from
 * other MCP servers / the core REST API / WP-CLI come in via
 * {@see recordExternalWrite()}.
 * Rows written before `source` existed came from our endpoint.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Provenance {

	/** Soft cap on retained provenance rows. FIFO-trimmed by Storage on each append. */
	public const SOFT_CAP = 500;

	/** Recognised `result_status` values. */
	public const STATUS_SUCCESS = 'success';
	public const STATUS_ERROR   = 'error';
	public const STATUS_PREVIEW = 'preview';

	/** Recognised `source` values. */
	public const SOURCE_PYS      = 'pys';
	public const SOURCE_EXTERNAL = 'external';

	/** `token` prefix for external rows; followed by the WP user login. */
	public const EXTERNAL_TOKEN_PREFIX = 'external:';

	/** `token` of rows written from WP-CLI; followed by the WP user login when there is one. */
	public const CLI_TOKEN = 'cli';

	/** Set while our server runs a tool; its result filter logs that call. */
	private static bool $inOurToolCall = false;

	private string $serverId;

	/**
	 * Bind this provenance logger to one adapter server.
	 *
	 * @param string $serverId Adapter server ID this instance logs for.
	 */
	public function __construct( string $serverId ) {
		$this->serverId = $serverId;
	}

	/**
	 * Hook the tool-result filter at priority 20, so RequestGuard
	 * (priority 10) updates its failure counter before we write the row.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'pys_pro_mcp_adapter_pre_tool_call', array( $this, 'onToolCallStart' ), PHP_INT_MAX, 4 );
		add_filter( 'pys_pro_mcp_adapter_tool_call_result', array( $this, 'onToolResult' ), 20, 5 );
	}

	/**
	 * Marks a tool call our server is about to run, so the ability's own
	 * logging skips it. Runs last: a call another filter refused never starts.
	 *
	 * @param mixed  $args      Tool args, or a WP_Error from an earlier filter.
	 * @param string $toolName  Tool name.
	 * @param mixed  $mcpTool   Adapter tool wrapper — opaque here.
	 * @param mixed  $mcpServer Adapter server instance.
	 * @return mixed
	 */
	public function onToolCallStart( $args, $toolName, $mcpTool, $mcpServer ) {
		if ( !is_wp_error( $args ) && $this->isOurServer( $mcpServer ) ) {
			self::$inOurToolCall = true;
		}

		return $args;
	}

	/**
	 * Whether our server is running the current tool call.
	 *
	 * @return bool
	 */
	public static function isInOurToolCall(): bool {
		return self::$inOurToolCall;
	}

	/**
	 * Filter callback. Returns `$result` unchanged (observer, not mutator);
	 * logging is best-effort and never breaks the tool call.
	 *
	 * @param mixed                                 $result    Tool execution result.
	 * @param array                                 $args      Tool args.
	 * @param string                                $toolName  Tool name.
	 * @param mixed                                 $mcpTool   Adapter tool wrapper — opaque here.
	 * @param \PYS_PRO_GLOBAL\WP\MCP\Core\McpServer $mcpServer Adapter server instance.
	 * @return mixed
	 */
	public function onToolResult( $result, $args, string $toolName, $mcpTool, $mcpServer ) {
		if ( !$this->isOurServer( $mcpServer ) ) {
			return $result;
		}
		self::$inOurToolCall = false;
		if ( !self::isWriteTool( $mcpTool ) ) {
			return $result;
		}

		$mcpNote = '';
		if ( is_array( $args ) && isset( $args[ 'mcp_note' ] ) && is_string( $args[ 'mcp_note' ] ) ) {
			$mcpNote = $args[ 'mcp_note' ];
		}

		self::recordEntry(
			array(
				'ts'            => time(),
				'tool'          => $toolName,
				'mcp_note'      => $mcpNote,
				'actor_ip'      => self::clientIp(),
				'token'         => (string) ( Auth::activeTokenLabel() ?? '' ),
				'result_status' => self::resultStatus( $result ),
				'source'        => self::SOURCE_PYS,
			)
		);

		return $result;
	}

	/**
	 * Log a write call that reached an ability outside our endpoint (another
	 * MCP server, the core REST API, WP-CLI). There is no PYS token, so the
	 * row is attributed to the WP user. Best-effort: never breaks the tool call.
	 *
	 * @param string $abilityId Ability ID.
	 * @param mixed  $input     Tool args.
	 * @param mixed  $result    Tool execution result.
	 * @return void
	 */
	public static function recordExternalWrite( string $abilityId, $input, $result ): void {
		try {
			$mcp_note = '';
			if ( is_array( $input ) && isset( $input[ 'mcp_note' ] ) && is_string( $input[ 'mcp_note' ] ) ) {
				$mcp_note = $input[ 'mcp_note' ];
			}

			self::recordEntry(
				array(
					'ts'            => time(),
					'tool'          => ToolNameMap::resolve( $abilityId ) ?? $abilityId,
					'mcp_note'      => $mcp_note,
					'actor_ip'      => self::clientIp(),
					'token'         => self::externalActor(),
					'result_status' => self::resultStatus( $result ),
					'source'        => self::SOURCE_EXTERNAL,
				)
			);
		} catch ( \Throwable $e ) {
			// Logging must not change the tool result.
		}
	}

	/**
	 * `token` of an external row: `cli:<login>` (or `cli`) from WP-CLI,
	 * `external:<login>` otherwise.
	 *
	 * @return string
	 */
	private static function externalActor(): string {
		$login = (string) wp_get_current_user()->user_login;

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return '' === $login ? self::CLI_TOKEN : self::CLI_TOKEN . ':' . $login;
		}

		return self::EXTERNAL_TOKEN_PREFIX . $login;
	}

	/**
	 * Row source; rows without one predate the field and came from our endpoint.
	 *
	 * @param array $entry Log row.
	 * @return string One of the SOURCE_* values.
	 */
	public static function entrySource( array $entry ): string {
		return self::SOURCE_EXTERNAL === ( $entry[ 'source' ] ?? '' ) ? self::SOURCE_EXTERNAL : self::SOURCE_PYS;
	}

	/**
	 * Public manual-record entry point. Used by callers that need to log
	 * something the adapter filter wouldn't see — e.g. a settings-tab action
	 * firing outside the MCP request flow.
	 *
	 * @param array $entry Entry to append (ts / tool / mcp_note / actor_ip / token / result_status / source).
	 * @return bool True on successful write.
	 */
	public static function recordEntry( array $entry ): bool {
		return Storage::provenanceAppend( $entry, self::SOFT_CAP );
	}

	/**
	 * Read API for the Settings-tab "Recent Activity" panel. Returns entries
	 * newest-first.
	 *
	 * @param int $limit Max entries to return.
	 * @return array<int, array> Decoded entries.
	 */
	public static function getRecent( int $limit = 20 ): array {
		return Storage::getRecentProvenance( $limit );
	}

	/**
	 * Full log, newest-first. The store is FIFO-capped at {@see SOFT_CAP}
	 * rows, so loading everything is cheap; the activity-log subpage filters
	 * and paginates in PHP (rows are serialized — SQL filtering would mean
	 * fragile LIKE patterns over serialized data).
	 *
	 * @return array<int, array> Decoded entries.
	 */
	public static function getAll(): array {
		return Storage::getRecentProvenance( self::SOFT_CAP );
	}

	/**
	 * Wipe the log. Bound to the Settings-tab "Clear log" button.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function clearAll(): int {
		return Storage::clearAllProvenance();
	}

	/**
	 * Annotation-driven write-tool test. True when the tool declared
	 * `readOnlyHint === false` OR `destructiveHint === true`; false for
	 * everything else (read tools and undeclared tools — default-deny).
	 * Duck-typed via `method_exists` since `$mcpTool` lives under the
	 * prefixed namespace.
	 *
	 * @param mixed $mcpTool Adapter tool wrapper (`McpTool` in practice).
	 * @return bool True when the tool is a write (audit-loggable).
	 */
	public static function isWriteTool( $mcpTool ): bool {
		if ( !is_object( $mcpTool ) || !method_exists( $mcpTool, 'get_protocol_dto' ) ) {
			return false;
		}

		$tool = $mcpTool->get_protocol_dto();
		if ( !is_object( $tool ) || !method_exists( $tool, 'getAnnotations' ) ) {
			return false;
		}

		$annotations = $tool->getAnnotations();
		if ( !is_object( $annotations ) ) {
			return false;
		}

		$readOnly    = method_exists( $annotations, 'getReadOnlyHint' ) ? $annotations->getReadOnlyHint() : null;
		$destructive = method_exists( $annotations, 'getDestructiveHint' ) ? $annotations->getDestructiveHint() : null;

		return false === $readOnly || true === $destructive;
	}

	/**
	 * Row status: an error, a preview that wrote nothing (it asks for
	 * confirmation), or a success.
	 *
	 * @param mixed $result Tool execution result.
	 * @return string One of the STATUS_* values.
	 */
	public static function resultStatus( $result ): string {
		if ( self::isErrorResult( $result ) ) {
			return self::STATUS_ERROR;
		}
		if ( is_array( $result ) && !empty( $result[ 'confirmation_required' ] ) ) {
			return self::STATUS_PREVIEW;
		}

		return self::STATUS_SUCCESS;
	}

	/**
	 * Failure detection matches RequestGuard::recordToolResult — keep them in sync.
	 *
	 * @param mixed $result Tool execution result.
	 * @return bool True when the result represents an error.
	 */
	private static function isErrorResult( $result ): bool {
		if ( is_wp_error( $result ) ) {
			return true;
		}
		if ( is_array( $result ) ) {
			if ( array_key_exists( 'success', $result ) && false === $result[ 'success' ]
			     && !empty( $result[ 'error' ] ) ) {
				return true;
			}
			if ( !empty( $result[ 'isError' ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether `$mcpServer` is the server this instance logs for.
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
	 * Same definition RequestGuard uses. Reverse-proxy environments must
	 * normalise `REMOTE_ADDR` outside of PYS — we do not parse forwarding
	 * headers here.
	 *
	 * @return string Client IP, or `0.0.0.0` when unknown.
	 */
	private static function clientIp(): string {
		$ip = isset( $_SERVER[ 'REMOTE_ADDR' ] ) ? (string) $_SERVER[ 'REMOTE_ADDR' ] : '';

		return '' === $ip ? '0.0.0.0' : $ip;
	}
}