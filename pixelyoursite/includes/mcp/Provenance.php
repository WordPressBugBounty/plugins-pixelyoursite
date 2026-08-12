<?php
/**
 * Append-only audit trail of every write-tool call, surfaced in the
 * Settings tab ("who / what / when changed our pixel config?"). Each row
 * holds `ts`, `tool`, `mcp_note`, `actor_ip`, `result_status`; stored via
 * {@see Storage} keyed by microtime (lex-sort = time-sort), FIFO-trimmed.
 * A call is logged only when the ability's annotations mark it as a write
 * (`readOnlyHint === false` OR `destructiveHint === true`); everything else
 * is treated as read-only and skipped.
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
		add_filter( 'mcp_adapter_tool_call_result', array( $this, 'onToolResult' ), 20, 5 );
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
				'actor_ip'      => $this->clientIp(),
				'token'         => (string) ( Auth::activeTokenLabel() ?? '' ),
				'result_status' => self::isErrorResult( $result ) ? self::STATUS_ERROR : self::STATUS_SUCCESS,
			)
		);

		return $result;
	}

	/**
	 * Public manual-record entry point. Used by callers that need to log
	 * something the adapter filter wouldn't see — e.g. a settings-tab action
	 * firing outside the MCP request flow.
	 *
	 * @param array $entry Entry to append (ts / tool / mcp_note / actor_ip / result_status).
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
	private function clientIp(): string {
		$ip = isset( $_SERVER[ 'REMOTE_ADDR' ] ) ? (string) $_SERVER[ 'REMOTE_ADDR' ] : '';

		return '' === $ip ? '0.0.0.0' : $ip;
	}
}