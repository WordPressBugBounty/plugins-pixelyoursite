<?php
/**
 * `PixelYourSite → MCP` settings tab, rendered inside PYS's standard
 * single-page wrapper. One page with five surfaces: token state machine
 * (Generate / Regenerate / Revoke; raw token shown once), the read-only
 * toggle, a Test Connection infrastructure check + `curl` snippet, the
 * Recent Activity log, and Claude Desktop connection instructions.
 * AJAX handlers require `manage_pys` + nonce and go through `Auth`,
 * `Capabilities`, `Provenance` — never storage directly.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class AdminPage {

	/** Submenu page slug — must match the `pys_admin_<slug>` action hook. */
	public const PAGE_SLUG = 'pixelyoursite_mcp';

	/**
	 * Activity-log subpage slug. Registered as a submenu page (so WP routes
	 * it and PYS styles load) but immediately hidden from the menu — it is
	 * reached via the "View full log" link on the MCP tab.
	 */
	public const LOG_PAGE_SLUG = 'pixelyoursite_mcp_log';

	/** Rows per page on the activity-log subpage. */
	public const LOG_PER_PAGE = 25;

	/** Nonce action for all AJAX handlers on this page. */
	public const NONCE_ACTION = 'pys_mcp_admin';

	/** WP capability required to access settings + AJAX endpoints. Same as the rest of PYS Pro admin. */
	public const CAPABILITY = 'manage_pys';

	/**
	 * AJAX action names (keep in sync with the JS in `html-main-mcp.php`).
	 * The read-only toggle is not here — it's a real setting saved through
	 * PYS's standard form flow, not an AJAX button.
	 */
	public const AJAX_GENERATE_TOKEN  = 'pys_mcp_generate_token';
	public const AJAX_REVOKE_TOKEN    = 'pys_mcp_revoke_token';
	public const AJAX_TEST_CONNECTION = 'pys_mcp_test_connection';
	public const AJAX_CLEAR_LOG       = 'pys_mcp_clear_log';

	private string $routeNamespace;
	private string $route;

	/**
	 * Store the endpoint coordinates used to build the displayed URL.
	 *
	 * @param string $routeNamespace REST namespace of the MCP endpoint.
	 * @param string $route          REST route of the MCP endpoint.
	 */
	public function __construct( string $routeNamespace, string $route ) {
		$this->routeNamespace = $routeNamespace;
		$this->route          = $route;
	}

	/**
	 * Endpoint URL — computed on demand, since `rest_url()` isn't safe to
	 * call during bootstrap (before `$wp_rewrite` exists).
	 *
	 * @return string
	 */
	private function endpointUrl(): string {
		return rest_url( $this->routeNamespace . '/' . $this->route );
	}

	/**
	 * Wire everything. Idempotent — WP dedupes (hook, callable) pairs.
	 * `register()` is no-op outside admin context to keep front-end fast.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( !is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'registerSubmenu' ), 20 );

		add_action( 'pys_admin_' . self::PAGE_SLUG, array( $this, 'renderPageContent' ) );
		add_action( 'pys_admin_' . self::LOG_PAGE_SLUG, array( $this, 'renderLogPageContent' ) );

		add_action( 'wp_ajax_' . self::AJAX_GENERATE_TOKEN, array( $this, 'ajaxGenerateToken' ) );
		add_action( 'wp_ajax_' . self::AJAX_REVOKE_TOKEN, array( $this, 'ajaxRevokeToken' ) );
		add_action( 'wp_ajax_' . self::AJAX_TEST_CONNECTION, array( $this, 'ajaxTestConnection' ) );
		add_action( 'wp_ajax_' . self::AJAX_CLEAR_LOG, array( $this, 'ajaxClearLog' ) );
	}

	/**
	 * Submenu page registration + slug registration on PYS so its admin
	 * styles/scripts load for our page.
	 *
	 * @return void
	 */
	public function registerSubmenu(): void {
		add_submenu_page(
			'pixelyoursite',
			'MCP',
			'MCP',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( \PixelYourSite\PYS(), 'adminSinglePage' ),
			4
		);

		\PixelYourSite\PYS()->addAdminPageSlug( self::PAGE_SLUG );

		// Activity-log subpage.
		add_submenu_page(
			'',
			'MCP Activity Log',
			'MCP Activity Log',
			self::CAPABILITY,
			self::LOG_PAGE_SLUG,
			array( \PixelYourSite\PYS(), 'adminSinglePage' )
		);

		\PixelYourSite\PYS()->addAdminPageSlug( self::LOG_PAGE_SLUG );
	}

	/**
	 * Render the page body inside the PYS wrapper. The template reads from a
	 * passed view-model array rather than `$this`.
	 *
	 * @return void
	 */
	public function renderPageContent(): void {
		$viewModel = array(
			'endpoint_url'    => $this->endpointUrl(),
			'route_namespace' => $this->routeNamespace,
			'route'           => $this->route,
			'token_state'     => Auth::state(),
			'tokens'          => Auth::tokens(),
			'token_max'       => Auth::MAX_TOKENS,
			'is_pro'          => Capabilities::isPro(),
			'write_allowed'   => Capabilities::isWriteAllowed(),
			'recent_activity' => Provenance::getRecent( 20 ),
			'log_page_url'    => add_query_arg( array( 'page' => self::LOG_PAGE_SLUG ), admin_url( 'admin.php' ) ),
			'infra_checks'    => $this->infrastructureChecks(),
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
			'ajax_generate'   => self::AJAX_GENERATE_TOKEN,
			'ajax_revoke'     => self::AJAX_REVOKE_TOKEN,
			'ajax_test_conn'  => self::AJAX_TEST_CONNECTION,
			'ajax_clear_log'  => self::AJAX_CLEAR_LOG,
		);

		include PYS_FREE_PATH . '/includes/views/html-main-mcp.php';
	}

	/**
	 * Render the activity-log subpage. The full log is FIFO-capped at
	 * {@see Provenance::SOFT_CAP} rows, so it is loaded whole and filtered /
	 * paginated in PHP. Filters travel as GET params so pages are
	 * bookmarkable and pagination links can carry them along.
	 *
	 * @return void
	 */
	public function renderLogPageContent(): void {
		$filters = $this->logFiltersFromRequest();
		$all     = Provenance::getAll();

		$tools = array();
		foreach ( $all as $entry ) {
			$tool = (string) ( $entry[ 'tool' ] ?? '' );
			if ( '' !== $tool && !in_array( $tool, $tools, true ) ) {
				$tools[] = $tool;
			}
		}
		sort( $tools );

		$filtered = array_values( array_filter( $all, static function ( $entry ) use ( $filters ): bool {
			if ( '' !== $filters[ 'tool' ] && (string) ( $entry[ 'tool' ] ?? '' ) !== $filters[ 'tool' ] ) {
				return false;
			}
			if ( '' !== $filters[ 'status' ] && (string) ( $entry[ 'result_status' ] ?? '' ) !== $filters[ 'status' ] ) {
				return false;
			}
			if ( 0 !== $filters[ 'since' ] && (int) ( $entry[ 'ts' ] ?? 0 ) < $filters[ 'since' ] ) {
				return false;
			}
			if ( '' !== $filters[ 'search' ] ) {
				$haystack = ( (string) ( $entry[ 'mcp_note' ] ?? '' ) ) . ' '
				            . ( (string) ( $entry[ 'tool' ] ?? '' ) ) . ' '
				            . ( (string) ( $entry[ 'token' ] ?? '' ) ) . ' '
				            . ( (string) ( $entry[ 'actor_ip' ] ?? '' ) );
				if ( false === stripos( $haystack, $filters[ 'search' ] ) ) {
					return false;
				}
			}

			return true;
		} ) );

		$totalFiltered = count( $filtered );
		$totalPages    = max( 1, (int) ceil( $totalFiltered / self::LOG_PER_PAGE ) );
		$paged         = min( $filters[ 'paged' ], $totalPages );
		$entries       = array_slice( $filtered, ( $paged - 1 ) * self::LOG_PER_PAGE, self::LOG_PER_PAGE );

		$baseArgs = array( 'page' => self::LOG_PAGE_SLUG );
		if ( '' !== $filters[ 'tool' ] ) {
			$baseArgs[ 'pys_tool' ] = $filters[ 'tool' ];
		}
		if ( '' !== $filters[ 'status' ] ) {
			$baseArgs[ 'pys_status' ] = $filters[ 'status' ];
		}
		if ( '' !== $filters[ 'period' ] ) {
			$baseArgs[ 'pys_period' ] = $filters[ 'period' ];
		}
		if ( '' !== $filters[ 'search' ] ) {
			$baseArgs[ 'pys_search' ] = $filters[ 'search' ];
		}

		$viewModel = array(
			'entries'        => $entries,
			'tools'          => $tools,
			'filters'        => $filters,
			'paged'          => $paged,
			'total_pages'    => $totalPages,
			'total_filtered' => $totalFiltered,
			'total_all'      => count( $all ),
			'per_page'       => self::LOG_PER_PAGE,
			'base_url'       => add_query_arg( $baseArgs, admin_url( 'admin.php' ) ),
			'settings_url'   => add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) ),
			'log_url'        => add_query_arg( array( 'page' => self::LOG_PAGE_SLUG ), admin_url( 'admin.php' ) ),
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( self::NONCE_ACTION ),
			'ajax_clear_log' => self::AJAX_CLEAR_LOG,
		);

		include PYS_FREE_PATH . '/includes/views/html-main-mcp-log.php';
	}

	/**
	 * Sanitize the log-subpage filter params from `$_GET`. `since` is the
	 * resolved unix cutoff for the `period` shortcut (0 = no cutoff).
	 *
	 * @return array{tool:string, status:string, period:string, since:int, search:string, paged:int}
	 */
	private function logFiltersFromRequest(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter params
		$tool   = isset( $_GET[ 'pys_tool' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'pys_tool' ] ) ) : '';
		$status = isset( $_GET[ 'pys_status' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'pys_status' ] ) ) : '';
		$period = isset( $_GET[ 'pys_period' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'pys_period' ] ) ) : '';
		$search = isset( $_GET[ 'pys_search' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'pys_search' ] ) ) : '';
		$paged  = isset( $_GET[ 'paged' ] ) ? max( 1, (int) $_GET[ 'paged' ] ) : 1;
		// phpcs:enable

		if ( !in_array( $status, array( Provenance::STATUS_SUCCESS, Provenance::STATUS_ERROR ), true ) ) {
			$status = '';
		}

		$periodMap = array(
			'24h' => DAY_IN_SECONDS,
			'7d'  => 7 * DAY_IN_SECONDS,
			'30d' => 30 * DAY_IN_SECONDS,
		);
		if ( !isset( $periodMap[ $period ] ) ) {
			$period = '';
		}

		return array(
			'tool'   => $tool,
			'status' => $status,
			'period' => $period,
			'since'  => '' === $period ? 0 : time() - $periodMap[ $period ],
			'search' => $search,
			'paged'  => $paged,
		);
	}

	// ----------------------------------------------------------- AJAX handlers

	/**
	 * Generate a new named token. The raw value is returned once in this
	 * response — the only time it leaves the server.
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function ajaxGenerateToken(): void {
		$this->preflight();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in preflight()
		$label  = isset( $_POST[ 'label' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'label' ] ) ) : '';
		$result = Auth::generate( $label );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
		}

		wp_send_json_success(
			array(
				'token'  => $result[ 'token' ],
				'id'     => $result[ 'id' ],
				'label'  => $result[ 'label' ],
				'state'  => Auth::state(),
				'tokens' => Auth::tokens(),
			)
		);
	}

	/**
	 * Revoke one token by id, or all tokens when `all` is set. Always succeeds
	 * (no-op if the id is unknown).
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function ajaxRevokeToken(): void {
		$this->preflight();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked in preflight()
		$all = !empty( $_POST[ 'all' ] );
		$id  = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
		// phpcs:enable

		if ( $all ) {
			$removed = Auth::revokeAll();
		} else {
			$removed = Auth::revoke( $id ) ? 1 : 0;
		}

		wp_send_json_success(
			array(
				'removed' => $removed,
				'state'   => Auth::state(),
				'tokens'  => Auth::tokens(),
			)
		);
	}

	/**
	 * Infrastructure-level connection check (not a real round-trip ping).
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function ajaxTestConnection(): void {
		$this->preflight();
		wp_send_json_success( array( 'checks' => $this->infrastructureChecks() ) );
	}

	/**
	 * Wipe provenance log. Returns the new (empty) recent-activity list.
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function ajaxClearLog(): void {
		$this->preflight();
		$deleted = Provenance::clearAll();
		wp_send_json_success(
			array(
				'deleted'         => $deleted,
				'recent_activity' => Provenance::getRecent( 20 ),
			)
		);
	}

	// --------------------------------------------------------------- internal

	/**
	 * Nonce + capability guard for AJAX. Sends a 403 JSON response and exits
	 * if either fails — never returns to the handler.
	 *
	 * @return void
	 */
	private function preflight(): void {
		if ( !current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient capability.' ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	/**
	 * Lightweight server-side checks the Test Connection button surfaces.
	 * Each entry is `[label, ok(bool), detail(string)]`. Order matters —
	 * displayed top-down in the UI.
	 *
	 * @return array<int, array{label:string, ok:bool, detail:string}>
	 */
	private function infrastructureChecks(): array {
		$tokenActive = Auth::STATE_ACTIVE === Auth::state();

		$abilitiesApi = function_exists( 'wp_register_ability' );

		$adapterLoaded = class_exists( '\\PYS_PRO_GLOBAL\\WP\\MCP\\Plugin' );

		$routeRegistered = false;
		$server          = rest_get_server();
		if ( is_object( $server ) && method_exists( $server, 'get_routes' ) ) {
			$routes          = $server->get_routes();
			$expectedRoute   = '/' . trim( $this->routeNamespace, '/' ) . '/' . trim( $this->route, '/' );
			$routeRegistered = isset( $routes[ $expectedRoute ] );
		}

		return array(
			array(
				'label'  => 'Token state',
				'ok'     => $tokenActive,
				'detail' => $tokenActive ? 'Active' : 'No token generated yet.',
			),
			array(
				'label'  => 'Abilities API (WP 6.9+)',
				'ok'     => $abilitiesApi,
				'detail' => $abilitiesApi ? 'wp_register_ability() available' : 'Function missing — upgrade WordPress.',
			),
			array(
				'label'  => 'MCP Adapter',
				'ok'     => $adapterLoaded,
				'detail' => $adapterLoaded ? 'PYS_PRO_GLOBAL\\WP\\MCP\\Plugin loaded' : 'Prefixed adapter not found in vendor_prefix/.',
			),
			array(
				'label'  => 'REST route',
				'ok'     => $routeRegistered,
				'detail' => $routeRegistered ? $this->endpointUrl() : 'Route not registered in WP REST API.',
			),
			array(
				'label'  => 'Write mode',
				'ok'     => Capabilities::isWriteAllowed(),
				'detail' => Capabilities::isWriteAllowed() ? 'Writes enabled' : 'Read-only mode is on — set_* tools are blocked.',
			),
		);
	}
}