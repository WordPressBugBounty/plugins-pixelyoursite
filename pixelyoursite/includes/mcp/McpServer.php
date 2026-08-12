<?php
/**
 * Boots the PYS Free MCP server: loads the prefixed wordpress/mcp-adapter and
 * registers the ability category, abilities and (later phases) the request
 * guard, provenance log and admin page.
 *
 * NOTE: Free reuses the SAME php-scoper prefix (`PYS_PRO_GLOBAL`) as Pro, so
 * the adapter classes are referenced identically. Pro and Free never run
 * together (Pro deactivates Free), but SERVER_ID / route / ability category
 * are distinct to keep storage and registration cleanly separated.
 *
 * Phase 0: only PingAbility is registered — enough to verify the full
 * initialize → tools/list → tools/call pipeline. Later phases add the rest.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PYS_PRO_GLOBAL\WP\MCP\Plugin;

class McpServer {

	/** Server identifier in the adapter's registry. Must be unique. */
	private const SERVER_ID = 'pixelyoursite';

	/** REST namespace — endpoint URL is /wp-json/<namespace>/<route>. */
	private const ROUTE_NAMESPACE = 'pixelyoursite/v1';

	/** REST route. */
	private const ROUTE = 'mcp';

	/** Ability category slug. Abilities reference this. */
	public const ABILITY_CATEGORY = 'pixelyoursite';

	private const SERVER_NAME = 'PixelYourSite';

	/** Returned as the `initialize` instructions (operating guidance for the LLM). */
	private const SERVER_DESCRIPTION = 'PixelYourSite (Free) — tracking pixel and ecommerce event configuration. Free platforms: Facebook, Google Analytics (GA4), Google Tags, GTM, plus Pinterest/Bing/Reddit when their add-on is active. Call get_tracking_audit first in any new conversation. Skip domains marked ok or not_active; drill only warning, incomplete or error. Every write tool returns its own confirmation — do not re-audit after a write. When a request needs a Pro-only capability, explain it once plainly and note it requires PixelYourSite Pro. Pro-only includes: Google Ads (the AW-… conversion platform, conversion IDs and conversion labels — NOT the same as GA4 / Google Tags, do not substitute those), CAPI / server-side tokens, advanced matching, extra custom-event triggers/conditions, lifecycle events, attribution reports, and TikTok.';

	private const ADAPTER_VERSION = '0.5.0';

	private static ?self $instance = null;

	/**
	 * Singleton accessor; boots the server on first call.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function __construct() {
	}

	/**
	 * Wire categories, abilities and the tool-name filter. No-op if
	 * dependencies are missing.
	 *
	 * @return void
	 */
	private function boot(): void {
		if ( !$this->dependencies_ready() ) {
			return;
		}

		$this->run_adapter();

		// WooCommerce bundles its own (un-prefixed) copy of mcp-adapter. php-scoper
		// prefixes classes but NOT hook-name strings, so both copies share the
		// global `mcp_adapter_init` action — the adapter's default factory then
		// re-fires and errors with `duplicate_server_id`. We register our own
		// server, so drop the default-factory callback before it runs.
		add_action( 'mcp_adapter_init', static function (): void {
			remove_action(
				'mcp_adapter_init',
				array( \PYS_PRO_GLOBAL\WP\MCP\Servers\DefaultServerFactory::class, 'create' )
			);
		}, 1 );

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( $this, 'register_server' ) );

		// Map hyphen ability IDs to the underscore MCP tool names.
		add_filter( 'mcp_adapter_tool_name', array( ToolNameMap::class, 'filter' ), 10, 2 );

		// Append a neutral language directive to the initialize instructions.
		add_filter( 'mcp_adapter_initialize_response', array( $this, 'add_language_instruction' ), 10, 2 );

		// Register the read-only toggle as a PYS Settings option.
		add_action( 'init', static function (): void {
			if ( function_exists( '\\PixelYourSite\\PYS' ) ) {
				\PixelYourSite\PYS()->addOption( Capabilities::OPTION_READ_ONLY_ENABLED, 'checkbox', false );
			}
		}, 11 );

		// Rate-limit + loop fingerprint + repeated-failure detection.
		( new RequestGuard( self::ROUTE_NAMESPACE, self::ROUTE, self::SERVER_ID ) )->register();

		// Audit log of every write-tool call.
		( new Provenance( self::SERVER_ID ) )->register();

		// Settings tab UI under PixelYourSite → MCP (tokens, read-only, activity log).
		( new AdminPage( self::ROUTE_NAMESPACE, self::ROUTE ) )->register();
	}

	/**
	 * Append a neutral, non-language-naming directive to the `initialize`
	 * instructions, nudging the model to reply in the user's language. Scoped
	 * to our server; other servers' responses pass through untouched.
	 *
	 * @param mixed $result The initialize result DTO.
	 * @param mixed $server The MCP server instance.
	 * @return mixed The (possibly enriched) initialize result DTO.
	 */
	public function add_language_instruction( $result, $server ) {
		if ( !is_object( $server ) || !method_exists( $server, 'get_server_id' )
			|| self::SERVER_ID !== $server->get_server_id() ) {
			return $result;
		}
		if ( !is_object( $result ) || !method_exists( $result, 'toArray' ) ) {
			return $result;
		}
		$dtoClass = '\\PYS_PRO_GLOBAL\\WP\\McpSchema\\Common\\Protocol\\DTO\\InitializeResult';
		if ( !class_exists( $dtoClass ) ) {
			return $result;
		}

		$data      = $result->toArray();
		$base      = isset( $data['instructions'] ) && is_string( $data['instructions'] ) ? trim( $data['instructions'] ) : '';
		$directive = 'Always reply in the same language the user wrote their message in.';
		$data['instructions'] = '' === $base ? $directive : $base . ' ' . $directive;

		return $dtoClass::fromArray( $data );
	}

	/**
	 * Hard requirements: the Abilities API (WP 6.9+) and the prefixed adapter
	 * classes must be available via the composer classmap.
	 *
	 * @return bool True when both dependencies are present.
	 */
	private function dependencies_ready(): bool {
		if ( !function_exists( 'wp_register_ability' ) ) {
			add_action( 'admin_notices', static function (): void {
				echo '<div class="notice notice-error"><p><strong>PixelYourSite MCP:</strong> requires WordPress 6.9+ with the Abilities API.</p></div>';
			} );

			return false;
		}

		if ( !class_exists( '\\PYS_PRO_GLOBAL\\WP\\MCP\\Plugin' ) ) {
			add_action( 'admin_notices', static function (): void {
				echo '<div class="notice notice-error"><p><strong>PixelYourSite MCP:</strong> prefixed mcp-adapter not found in <code>vendor_prefix/wordpress/</code>. Run <code>composer dump-autoload</code>.</p></div>';
			} );

			return false;
		}

		return true;
	}

	/**
	 * Boot the prefixed wordpress/mcp-adapter Plugin singleton. The adapter's
	 * own Autoloader expects a standalone vendor/ next to itself; we
	 * short-circuit it because the prefixed composer classmap already loaded
	 * everything.
	 *
	 * @return void
	 */
	private function run_adapter(): void {
		if ( !defined( 'WP_MCP_AUTOLOAD' ) ) {
			define( 'WP_MCP_AUTOLOAD', false );
		}
		if ( !defined( 'WP_MCP_DIR' ) ) {
			define( 'WP_MCP_DIR', PYS_FREE_PATH . '/vendor_prefix/wordpress/mcp-adapter' );
		}
		if ( !defined( 'WP_MCP_VERSION' ) ) {
			define( 'WP_MCP_VERSION', self::ADAPTER_VERSION );
		}

		Plugin::instance();
	}

	/**
	 * Register the `pixelyoursite` ability category. Idempotent.
	 *
	 * @return void
	 */
	public function register_category(): void {
		if ( !function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::ABILITY_CATEGORY ) ) {
			return;
		}
		wp_register_ability_category(
			self::ABILITY_CATEGORY,
			array(
				'label'       => 'PixelYourSite',
				'description' => 'Tracking pixel and ecommerce event abilities provided by PixelYourSite.',
			)
		);
	}

	/**
	 * Create the PYS Free MCP server in the adapter registry; abilities come
	 * from get_abilities(). Transport-level auth replaces the adapter default
	 * with our Bearer-token check (Auth::permissionCallback).
	 *
	 * @param mixed $adapter The McpAdapter instance (from `mcp_adapter_init`).
	 * @return void
	 */
	public function register_server( $adapter ): void {
		if ( !$adapter instanceof \PYS_PRO_GLOBAL\WP\MCP\Core\McpAdapter ) {
			return;
		}

		$result = $adapter->create_server(
			self::SERVER_ID,
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			self::SERVER_NAME,
			self::SERVER_DESCRIPTION,
			defined( 'PYS_FREE_VERSION' ) ? (string) PYS_FREE_VERSION : '0',
			array( \PYS_PRO_GLOBAL\WP\MCP\Transport\HttpTransport::class ),
			// Custom error handler: keeps expected 4xx tool rejections out of the
			// PHP error log while still logging genuine faults.
			PysMcpErrorHandler::class,
			null,
			$this->get_abilities(),
			array(),
			array(), array( Auth::class, 'permissionCallback' )
		);

		if ( is_wp_error( $result ) ) {
			error_log( '[PYS Free MCP] create_server failed: ' . $result->get_error_message() );
		}
	}

	/**
	 * Register all abilities owned by PYS Free MCP.
	 *
	 * Phases 1–2: ping, usage-guidance, credential-setup-instructions,
	 * tracking-audit, platform-pixels. Later phases append woo/edd, automatic
	 * and custom-event abilities.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		Abilities\PingAbility::register();
		Abilities\UsageGuidanceAbility::register();
		Abilities\CredentialSetupInstructionsAbility::register();
		Abilities\GetTrackingAuditAbility::register();
		Abilities\GetPlatformPixelsAbility::register();
		Abilities\GetWooEventsConfigAbility::register();
		Abilities\SetWooEventConfigAbility::register();
		Abilities\GetEddEventsConfigAbility::register();
		Abilities\SetEddEventConfigAbility::register();
		Abilities\GetAutomaticEventsConfigAbility::register();
		Abilities\SetAutomaticEventConfigAbility::register();
		Abilities\GetCustomEventsAbility::register();
		Abilities\GetCustomEventAbility::register();
		Abilities\SetCustomEventAbility::register();
		Abilities\ManageCustomEventAbility::register();
	}

	/**
	 * Ability IDs exposed by the PYS Free MCP server.
	 *
	 * @return array<int, string> Ability IDs.
	 */
	private function get_abilities(): array {
		return array(
			Abilities\PingAbility::ID,
			Abilities\UsageGuidanceAbility::ID,
			Abilities\CredentialSetupInstructionsAbility::ID,
			Abilities\GetTrackingAuditAbility::ID,
			Abilities\GetPlatformPixelsAbility::ID,
			Abilities\GetWooEventsConfigAbility::ID,
			Abilities\SetWooEventConfigAbility::ID,
			Abilities\GetEddEventsConfigAbility::ID,
			Abilities\SetEddEventConfigAbility::ID,
			Abilities\GetAutomaticEventsConfigAbility::ID,
			Abilities\SetAutomaticEventConfigAbility::ID,
			Abilities\GetCustomEventsAbility::ID,
			Abilities\GetCustomEventAbility::ID,
			Abilities\SetCustomEventAbility::ID,
			Abilities\ManageCustomEventAbility::ID,
		);
	}
}
