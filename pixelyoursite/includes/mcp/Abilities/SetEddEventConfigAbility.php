<?php
/**
 * `set_edd_event_config` — partial write of Easy Digital Downloads event config
 * (Free). Writable: the 6 global funnel master toggles and per-platform
 * content_id (source / prefix / suffix). Strict whitelist; unknown keys ignored.
 * Two-step confirm (call without `confirm` for a preview, then with `confirm`).
 *
 * Free scope: no lifecycle / subscriptions / WPML / custom_field source (Pro).
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\Platforms;
use PixelYourSite\MCP\EventToggleMap;

final class SetEddEventConfigAbility extends AbstractWriteAbility {

	public const ID = 'pixelyoursite/set-edd-event-config';

	/** Global funnel master-toggle args => core option key. */
	private const FUNNEL_ARGS = array(
		'view_content'      => 'edd_view_content_enabled',
		'view_category'     => 'edd_view_category_enabled',
		'add_to_cart'       => 'edd_add_to_cart_enabled',
		'initiate_checkout' => 'edd_initiate_checkout_enabled',
		'purchase'          => 'edd_purchase_enabled',
		'remove_from_cart'  => 'edd_remove_from_cart_enabled',
	);

	/** Per-platform content_id args => platform option key. */
	private const CONTENT_ID_ARGS = array(
		'content_id_source' => 'edd_content_id',
		'content_id_prefix' => 'edd_content_id_prefix',
		'content_id_suffix' => 'edd_content_id_suffix',
	);

	/**
	 * Ability ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return self::ID;
	}

	/**
	 * Display label.
	 *
	 * @return string
	 */
	public static function label(): string {
		return 'PYS MCP — Set EDD Event Config';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Writes a partial update to Easy Digital Downloads event configuration (PixelYourSite Free). Strict whitelist — keys not listed are ignored. Writable: GLOBAL funnel master toggles (`view_content`, `view_category`, `add_to_cart`, `initiate_checkout`, `purchase`, `remove_from_cart` — master on/off across ALL platforms) and per-platform content_id (`content_id_source` [`download_id` | `download_sku`], `content_id_prefix`, `content_id_suffix`). content_id is PER-PLATFORM: by default written to every active platform; pass `content_id_platform` (a slug) to scope to ONE. When changing `content_id_source`, ASK the user whether the platform catalog feed uses a prefix/suffix and pass them in the same call. Lifecycle events, subscriptions, the `custom_field` content-ID source, the Transaction/Order ID prefix and Event Value Settings (tax etc.) are all PixelYourSite Pro — NOT writable here (say plainly it requires Pro; do not claim the setting "does not exist"). **Two-step write (server-enforced):** call FIRST without `confirm` to get a `confirmation_required` preview; show it, get approval, then resend with `confirm: true`. A call without `confirm: true` never writes. Pass `mcp_note`.';
	}

	/**
	 * Input JSON-Schema.
	 *
	 * @return array
	 */
	public static function inputSchema(): array {
		$bool = array( 'type' => 'boolean' );

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'view_content'        => $bool + array( 'description' => 'Global master toggle for the EDD ViewContent event. Admin label: "Track product pages".' ),
				'view_category'       => $bool + array( 'description' => 'Global master toggle for the ViewCategory event. Admin label: "Track product category pages".' ),
				'add_to_cart'         => $bool + array( 'description' => 'Global master toggle for the AddToCart event. Admin label: "Track add to cart".' ),
				'initiate_checkout'   => $bool + array( 'description' => 'Global master toggle for the InitiateCheckout event. Admin label: "Track the Checkout Page".' ),
				'purchase'            => $bool + array( 'description' => 'Global master toggle for the Purchase event. Admin label: "Track Purchases".' ),
				'remove_from_cart'    => $bool + array( 'description' => 'Global master toggle for the RemoveFromCart event. Admin label: "Track remove from cart".' ),
				'content_id_source'   => array( 'type' => 'string', 'enum' => array( 'download_id', 'download_sku' ), 'description' => 'Content-ID source (EDD uses download IDs, not product IDs). `download_id` = the EDD download post ID (always present). `download_sku` = the SKU (blank for downloads without one → they drop from catalog matching). The `custom_field` source is Pro-only.' ),
				'content_id_prefix'   => array( 'type' => 'string', 'description' => 'Content-ID prefix (must match the platform catalog feed). Empty string clears it.' ),
				'content_id_suffix'   => array( 'type' => 'string', 'description' => 'Content-ID suffix (must match the platform catalog feed). Empty string clears it.' ),
				'content_id_platform' => array( 'type' => 'string', 'description' => 'Scope the content_id args to ONE active platform slug (e.g. `facebook`, `gatags`, `pinterest`, `bing`, `reddit`). Omit to write to every active content-id platform.' ),
				'platform_event_toggles' => array(
					'type'        => 'array',
					'description'  => 'PER-PLATFORM event-firing toggles — whether a SPECIFIC platform fires a specific EDD event. Per-platform layer beneath the global funnel toggle: an event reaches a platform only when BOTH the global toggle AND this platform toggle are on. Read the current matrix from get_edd_events_config `platform_events`. Each item: `{ platform, event, enabled }`. `platform` = a slug or `all`. Platforms that do not register the event are skipped (reported in `unsupported_toggles`).',
					'items'        => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'platform', 'event', 'enabled' ),
						'properties'           => array(
							'platform' => array( 'type' => 'string', 'enum' => EventToggleMap::TARGET_PLATFORMS ),
							'event'    => array( 'type' => 'string', 'enum' => EventToggleMap::EDD_EVENTS ),
							'enabled'  => array( 'type' => 'boolean' ),
						),
					),
				),
				'confirm'             => array( 'type' => 'boolean', 'description' => 'Two-step write guard. Call FIRST WITHOUT this flag to get a `confirmation_required` preview; show `pending_changes` to the user and STOP. Do NOT set `confirm: true` in the SAME turn as the preview, and do NOT set it unless the user has actually replied with approval in a SEPARATE message. Re-asking "confirm?" yourself and immediately confirming is NOT allowed — wait for the human. Only after the user agrees, resend the SAME args with `confirm: true`. Without `confirm: true` nothing is written.' ),
			),
			// `mcp_note` added by AbstractWriteAbility::resolvedInputSchema().
		);
	}

	/**
	 * Output JSON-Schema.
	 *
	 * @return array
	 */
	public static function outputSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'saved'                 => array( 'type' => 'boolean' ),
				'available'             => array( 'type' => 'boolean' ),
				'confirmation_required' => array( 'type' => 'boolean' ),
				'next_step'             => array( 'type' => 'string' ),
				'pending_changes'       => array( 'type' => 'object', 'additionalProperties' => true ),
				'changed'               => array( 'type' => 'object', 'additionalProperties' => true ),
				'aggregated_to'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'unsupported_toggles'   => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Explicitly-named platform.event toggles skipped because the platform does not register that event.',
				),
			),
		);
	}

	/**
	 * Validate and write (or preview) an EDD config patch.
	 *
	 * @param mixed $input Validated args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( $input ) {
		if ( !function_exists( '\\PixelYourSite\\isEddActive' ) || !\PixelYourSite\isEddActive() ) {
			return new \WP_Error( 'pys_mcp_edd_inactive', 'Easy Digital Downloads is not active — nothing to configure.', array( 'status' => 409 ) );
		}
		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;
		if ( null === $pys ) {
			return new \WP_Error( 'pys_mcp_pys_unavailable', 'PixelYourSite settings are unavailable.', array( 'status' => 503 ) );
		}
		$input = is_array( $input ) ? $input : array();

		// --- 1. Core (global) funnel toggle changes.
		$coreNew = array();
		$pending = array();
		foreach ( self::FUNNEL_ARGS as $arg => $key ) {
			if ( !array_key_exists( $arg, $input ) ) {
				continue;
			}
			$new             = (bool) $input[ $arg ];
			$current         = self::toBool( $pys->getOption( $key ) );
			$coreNew[ $key ] = $new;
			$pending[ $arg ] = array( 'current' => $current, 'new' => $new );
		}

		// --- 2. Per-platform content_id changes.
		$contentNew = array();
		foreach ( self::CONTENT_ID_ARGS as $arg => $key ) {
			if ( array_key_exists( $arg, $input ) ) {
				$contentNew[ $key ] = (string) $input[ $arg ];
			}
		}
		$targets = array();
		if ( !empty( $contentNew ) ) {
			$active = EventToggleMap::enabledSettings();
			$scoped = isset( $input[ 'content_id_platform' ] ) ? trim( (string) $input[ 'content_id_platform' ] ) : '';
			if ( '' !== $scoped ) {
				if ( !isset( $active[ $scoped ] ) || 'google_analytics' === $scoped ) {
					$valid = array_values( array_filter( array_keys( $active ), static function ( $s ) {
						return 'google_analytics' !== $s;
					} ) );
					return new \WP_Error(
						'pys_mcp_bad_content_id_platform',
						sprintf( '`content_id_platform: %s` is not an active content-id platform. Active: %s.', $scoped, empty( $valid ) ? '(none)' : implode( ', ', $valid ) ),
						array( 'status' => 409 )
					);
				}
				$targets = array( $scoped => $active[ $scoped ] );
			} else {
				foreach ( $active as $slug => $inst ) {
					if ( 'google_analytics' !== $slug ) {
						$targets[ $slug ] = $inst;
					}
				}
			}

			$cidPreview = array();
			foreach ( $targets as $slug => $inst ) {
				$row = array();
				foreach ( self::CONTENT_ID_ARGS as $arg => $key ) {
					if ( array_key_exists( $key, $contentNew ) ) {
						$row[ $arg ] = array( 'current' => (string) $inst->getOption( $key, '' ), 'new' => $contentNew[ $key ] );
					}
				}
				$cidPreview[ $slug ] = $row;
			}
			$pending[ 'content_id' ] = $cidPreview;
		}

		// --- 3. Per-platform event-firing toggles.
		$togglePlan    = array();
		$toggleInsts   = array();
		$togglePreview = array();
		$unsupported   = array();
		$rawToggles    = ( isset( $input[ 'platform_event_toggles' ] ) && is_array( $input[ 'platform_event_toggles' ] ) ) ? $input[ 'platform_event_toggles' ] : array();
		if ( !empty( $rawToggles ) ) {
			$active = EventToggleMap::enabledSettings();
			foreach ( $rawToggles as $t ) {
				if ( !is_array( $t ) ) {
					continue;
				}
				$ev = isset( $t[ 'event' ] ) ? (string) $t[ 'event' ] : '';
				$pl = isset( $t[ 'platform' ] ) ? (string) $t[ 'platform' ] : '';
				$en = !empty( $t[ 'enabled' ] );
				if ( !EventToggleMap::isKnownEvent( 'edd', $ev ) ) {
					return new \WP_Error( 'pys_mcp_edd_bad_event', sprintf( 'Unknown EDD event `%s`. Valid: %s.', $ev, implode( ', ', EventToggleMap::EDD_EVENTS ) ), array( 'status' => 409 ) );
				}
				$slugs = ( 'all' === $pl ) ? array_keys( $active ) : array( $pl );
				foreach ( $slugs as $slug ) {
					if ( !isset( $active[ $slug ] ) ) {
						if ( 'all' !== $pl ) {
							return new \WP_Error( 'pys_mcp_edd_bad_platform', sprintf( '`%s` is not an active platform.', $slug ), array( 'status' => 409 ) );
						}
						continue;
					}
					$inst = $active[ $slug ];
					if ( !EventToggleMap::platformSupports( 'edd', $slug, $ev ) ) {
						if ( 'all' !== $pl ) {
							$unsupported[] = $slug . '.' . $ev;
						}
						continue;
					}
					$key                           = EventToggleMap::key( 'edd', $ev, $slug );
					$togglePlan[ $slug ][ $key ]   = $en;
					$toggleInsts[ $slug ]          = $inst;
					$togglePreview[ $slug ][ $ev ] = array( 'current' => EventToggleMap::toBool( $inst->getOption( $key ) ), 'new' => $en );
				}
			}
			if ( !empty( $togglePreview ) ) {
				$pending[ 'platform_event_toggles' ] = $togglePreview;
			}
		}

		if ( empty( $coreNew ) && empty( $contentNew ) && empty( $togglePlan ) ) {
			return new \WP_Error(
				'pys_mcp_edd_no_args',
				'No writable EDD config args provided (or platform_event_toggles targeted only platforms that do not register the event). Pass a funnel toggle, a content_id field, or platform_event_toggles.',
				array( 'status' => 400 )
			);
		}

		$aggregatedTo = array_values( array_unique( array_merge( array_keys( $targets ), array_keys( $togglePlan ) ) ) );

		// --- 4. Two-step confirm: preview unless confirm:true.
		if ( empty( $input[ 'confirm' ] ) ) {
			$out = array(
				'saved'                 => false,
				'available'             => true,
				'confirmation_required' => true,
				'pending_changes'       => $pending,
				'aggregated_to'         => $aggregatedTo,
				'next_step'             => 'NOTHING HAS BEEN SAVED YET. Show pending_changes to the user and STOP this turn. Do NOT call set_edd_event_config again in this turn and do NOT set confirm: true now. WAIT for the user to reply with explicit approval in a SEPARATE message; only then resend the same args with confirm: true.',
			);
			if ( !empty( $unsupported ) ) {
				$out[ 'unsupported_toggles' ] = $unsupported;
			}
			return $out;
		}

		// --- 5. Write.
		if ( !empty( $coreNew ) ) {
			$pys->updateOptions( $coreNew );
		}
		foreach ( $targets as $inst ) {
			$inst->updateOptions( $contentNew );
		}
		foreach ( $togglePlan as $slug => $kv ) {
			$toggleInsts[ $slug ]->updateOptions( $kv );
		}

		$out = array(
			'saved'         => true,
			'available'     => true,
			'changed'       => $pending,
			'aggregated_to' => $aggregatedTo,
		);
		if ( !empty( $unsupported ) ) {
			$out[ 'unsupported_toggles' ] = $unsupported;
		}
		return $out;
	}

	/**
	 * Coerce a mixed option value to a boolean.
	 *
	 * @param mixed $value Raw option value.
	 * @return bool
	 */
	private static function toBool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return '1' === $value || 'true' === strtolower( $value );
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		return false;
	}
}
