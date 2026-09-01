<?php
/**
 * `set_woo_event_config` — partial write of WooCommerce event config (Free).
 * Writable: the 7 global funnel master toggles, the AddToCart firing-point
 * sub-options, and per-platform content_id (source / prefix / suffix). Strict
 * whitelist; unknown keys ignored. Two-step confirm (call without `confirm`
 * for a preview, then again with `confirm: true`).
 *
 * Free scope: no lifecycle / POAS / subscriptions / WPML / custom_field source /
 * value settings / per-platform event matrix (all Pro).
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

final class SetWooEventConfigAbility extends AbstractWriteAbility {

	public const ID = 'pixelyoursite/set-woo-event-config';

	/** Global funnel master-toggle args => core option key. */
	private const FUNNEL_ARGS = array(
		'view_content'      => 'woo_view_content_enabled',
		'view_category'     => 'woo_view_category_enabled',
		'add_to_cart'       => 'woo_add_to_cart_enabled',
		'initiate_checkout' => 'woo_initiate_checkout_enabled',
		'purchase'          => 'woo_purchase_enabled',
		'view_cart'         => 'woo_view_cart_enabled',
		'remove_from_cart'  => 'woo_remove_from_cart_enabled',
	);

	/** AddToCart firing-point args => core option key. */
	private const ADD_TO_CART_ARGS = array(
		'add_to_cart_on_button_click'  => 'woo_add_to_cart_on_button_click',
		'add_to_cart_on_cart_page'     => 'woo_add_to_cart_on_cart_page',
		'add_to_cart_on_checkout_page' => 'woo_add_to_cart_on_checkout_page',
	);

	/** Per-platform content_id args => platform option key. */
	private const CONTENT_ID_ARGS = array(
		'content_id_source' => 'woo_content_id',
		'content_id_prefix' => 'woo_content_id_prefix',
		'content_id_suffix' => 'woo_content_id_suffix',
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
		return 'PYS MCP — Set WooCommerce Event Config';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Writes a partial update to WooCommerce event configuration (PixelYourSite Free). Strict whitelist — keys not listed are ignored. Writable: GLOBAL funnel master toggles (`view_content`, `view_category`, `add_to_cart`, `initiate_checkout`, `purchase`, `view_cart`, `remove_from_cart` — master on/off for the event across ALL platforms). These 7 are the ONLY WooCommerce events in Free; anything else the user names — ViewItemList / "product list performance", affiliate button clicks, PayPal Standard clicks, CheckoutSteps / checkout progress, SelectContent, AdvancePurchase — is a Pro WooCommerce event and is NOT toggleable here (say so plainly; do not offer a custom event as a substitute for a click-based Pro event). Also writable: AddToCart firing points (`add_to_cart_on_button_click`, `add_to_cart_on_cart_page`, `add_to_cart_on_checkout_page`); and per-platform content_id (`content_id_source` [`product_id` | `product_sku`], `content_id_prefix`, `content_id_suffix`). content_id is stored PER-PLATFORM: by default the content_id args write to every active platform; pass `content_id_platform` (a platform slug) to scope them to ONE. When changing `content_id_source`, first ASK the user whether the platform catalog feed uses a prefix/suffix and pass `content_id_prefix`/`content_id_suffix` in the same call — mismatched IDs break dynamic ads. Lifecycle events, POAS, subscriptions, the `custom_field` content-ID source, the Transaction/Order ID prefix (`woo_order_id_prefix` — the prefix for the ORDER id in Purchase events; NOT the same as content_id_prefix, which is for product ids), and Event Value Settings (tax/shipping/fees) are PixelYourSite Pro — NOT writable here (say plainly it requires Pro; do not claim the setting "does not exist"). **Two-step write (server-enforced):** call FIRST without `confirm` to get a `confirmation_required` preview with the current→new diff; show it to the user, get their go-ahead, then call again with `confirm: true` and the same args. A call without `confirm: true` never writes. Pass `mcp_note`.';
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
				'view_content'                 => $bool + array( 'description' => 'Global master toggle for the ViewContent event. Admin label: "Track product pages".' ),
				'view_category'                => $bool + array( 'description' => 'Global master toggle for the ViewCategory event. Admin label: "Track product category pages".' ),
				'add_to_cart'                  => $bool + array( 'description' => 'Global master toggle for the AddToCart EVENT. Admin label: "Track add to cart". (The WHERE-it-fires sub-options are add_to_cart_on_button_click / _on_cart_page / _on_checkout_page.)' ),
				'initiate_checkout'            => $bool + array( 'description' => 'Global master toggle for the InitiateCheckout event. Admin label: "Track the Checkout Page".' ),
				'purchase'                     => $bool + array( 'description' => 'Global master toggle for the Purchase event. Admin label: "Track Purchases".' ),
				'view_cart'                    => $bool + array( 'description' => 'Global master toggle for the ViewCart event — a page view of the CART page. Admin label: "Track cart pages". This is what "Track cart pages" / "disable cart-page tracking" refers to. Do NOT confuse it with add_to_cart_on_cart_page (only WHERE the AddToCart event fires). ViewCart is Google-Analytics-only in Free — needs GA enabled.' ),
				'remove_from_cart'             => $bool + array( 'description' => 'Global master toggle for the RemoveFromCart event. Admin label: "Track remove from cart".' ),
				'add_to_cart_on_button_click'  => $bool + array( 'description' => 'A firing POINT of the AddToCart event: fire it when the add-to-cart button is clicked. NOT a separate event.' ),
				'add_to_cart_on_cart_page'     => $bool + array( 'description' => 'A firing POINT of the AddToCart event: fire it on the cart page. NOT the ViewCart / "Track cart pages" event — to disable cart-page tracking use `view_cart`, not this.' ),
				'add_to_cart_on_checkout_page' => $bool + array( 'description' => 'Fire AddToCart on the checkout page.' ),
				'content_id_source'            => array( 'type' => 'string', 'enum' => array( 'product_id', 'product_sku' ), 'description' => 'Content-ID source. `product_id` = the WooCommerce product/variation post ID (always present). `product_sku` = the product SKU (blank for products without one → they drop from catalog matching). The `custom_field` source is Pro-only.' ),
				'content_id_prefix'            => array( 'type' => 'string', 'description' => 'Content-ID prefix (must match the platform catalog feed). Pass an empty string to clear.' ),
				'content_id_suffix'            => array( 'type' => 'string', 'description' => 'Content-ID suffix (must match the platform catalog feed). Pass an empty string to clear.' ),
				'variable_as_simple'           => array( 'type' => 'boolean', 'description' => 'Per-platform "Treat variable products like simple products" (admin: Facebook/GA/… ID settings → `woo_variable_as_simple`). When ON, a variable product reports the parent product id instead of the selected variation id. Written to the same platform(s) as the content_id args (all active, or the one in `content_id_platform`). This IS a Free setting. (Not to be confused with the Pro-only "track the variation data when a variation is selected".)' ),
				'wpml_unified_id'              => array( 'type' => 'boolean', 'description' => 'Per-platform "WPML Unified ID logic" (`woo_wpml_unified_id`): send the default-language product id for all languages so multilingual variants share one content id. This IS a Free setting, but it REQUIRES the WPML plugin to be active (rejected with 409 otherwise) and only appears in the admin then. NOTE: choosing WHICH language is the default is a Pro-only "Select language" option and is NOT controllable via MCP (neither Free nor Pro) — this arg only flips the unified-id toggle. Written to the same platform(s) as content_id (all active, or `content_id_platform`).' ),
				'content_id_platform'          => array( 'type' => 'string', 'description' => 'Scope the content_id / variable_as_simple / wpml_unified_id args to ONE active platform slug (e.g. `facebook`, `gatags`, `openai`, `pinterest`, `bing`, `reddit`). Omit to write to every active content-id platform. Read current per-platform values from get_woo_events_config `content_id_by_platform`.' ),
				'platform_event_toggles'       => array(
					'type'        => 'array',
					'description'  => 'PER-PLATFORM event-firing toggles — whether a SPECIFIC platform fires a specific WooCommerce event. This is the per-platform layer beneath the global funnel toggle: an event reaches a platform only when BOTH the global toggle AND this platform toggle are on. Read the current matrix from get_woo_events_config `platform_events`. Each item: `{ platform, event, enabled }`. `platform` = a slug or `all` (fan out to every active platform that registers the event). Platforms that do not register the event are skipped (reported in `unsupported_toggles`).',
					'items'        => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'platform', 'event', 'enabled' ),
						'properties'           => array(
							'platform' => array( 'type' => 'string', 'enum' => EventToggleMap::TARGET_PLATFORMS ),
							'event'    => array( 'type' => 'string', 'enum' => EventToggleMap::WOO_EVENTS ),
							'enabled'  => array( 'type' => 'boolean' ),
						),
					),
				),
				'confirm'                      => array( 'type' => 'boolean', 'description' => 'Two-step write guard. Call FIRST WITHOUT this flag to get a `confirmation_required` preview; show `pending_changes` to the user and STOP. Do NOT set `confirm: true` in the SAME turn as the preview, and do NOT set it unless the user has actually replied with their approval in a SEPARATE message. Re-asking "confirm?" yourself and immediately confirming is NOT allowed — wait for the human. Only after the user agrees, resend the SAME args with `confirm: true`. Without `confirm: true` nothing is written.' ),
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
					'description' => 'Explicitly-named platform.event toggles that were skipped because the platform does not register that event.',
				),
			),
		);
	}

	/**
	 * Validate and write (or preview) a WooCommerce config patch.
	 *
	 * @param mixed $input Validated args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( $input ) {
		if ( !function_exists( '\\PixelYourSite\\isWooCommerceActive' ) || !\PixelYourSite\isWooCommerceActive() ) {
			return new \WP_Error( 'pys_mcp_woo_inactive', 'WooCommerce is not active — nothing to configure.', array( 'status' => 409 ) );
		}
		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;
		if ( null === $pys ) {
			return new \WP_Error( 'pys_mcp_pys_unavailable', 'PixelYourSite settings are unavailable.', array( 'status' => 503 ) );
		}
		$input = is_array( $input ) ? $input : array();

		// --- 1. Core (global) toggle changes: funnel + add_to_cart triggers.
		$coreNew   = array();
		$pending   = array();
		$available = EventToggleMap::availableEvents( 'woo' );
		// Funnel event masters — gated on availability (e.g. ViewCart needs GA enabled).
		foreach ( self::FUNNEL_ARGS as $arg => $key ) {
			if ( !array_key_exists( $arg, $input ) ) {
				continue;
			}
			if ( !in_array( $arg, $available, true ) ) {
				return new \WP_Error(
					'pys_mcp_woo_event_unavailable',
					sprintf( 'The `%s` WooCommerce event is not available on this site — no active platform supports it (e.g. ViewCart is Google-Analytics-only and needs GA enabled). Enable a supporting platform first.', $arg ),
					array( 'status' => 409 )
				);
			}
			$new             = (bool) $input[ $arg ];
			$coreNew[ $key ] = $new;
			$pending[ $arg ] = array( 'current' => self::toBool( $pys->getOption( $key ) ), 'new' => $new );
		}
		// AddToCart firing-point sub-options (not events — no availability gate).
		foreach ( self::ADD_TO_CART_ARGS as $arg => $key ) {
			if ( !array_key_exists( $arg, $input ) ) {
				continue;
			}
			$new             = (bool) $input[ $arg ];
			$coreNew[ $key ] = $new;
			$pending[ $arg ] = array( 'current' => self::toBool( $pys->getOption( $key ) ), 'new' => $new );
		}

		// --- 2. Per-platform ID-settings changes: content_id + variable_as_simple.
		$contentNew = array();
		foreach ( self::CONTENT_ID_ARGS as $arg => $key ) {
			if ( array_key_exists( $arg, $input ) ) {
				$contentNew[ $key ] = (string) $input[ $arg ];
			}
		}
		$varAsSimple  = array_key_exists( 'variable_as_simple', $input ) ? (bool) $input[ 'variable_as_simple' ] : null;
		$wpmlUnified  = array_key_exists( 'wpml_unified_id', $input ) ? (bool) $input[ 'wpml_unified_id' ] : null;
		if ( null !== $wpmlUnified && ( !function_exists( '\\PixelYourSite\\isWPMLActive' ) || !\PixelYourSite\isWPMLActive() ) ) {
			return new \WP_Error( 'pys_mcp_wpml_inactive', '`wpml_unified_id` requires the WPML plugin to be active — it has no effect otherwise.', array( 'status' => 409 ) );
		}
		$platformWrite = $contentNew; // combined per-platform payload (strings + bools)
		if ( null !== $varAsSimple ) {
			$platformWrite[ 'woo_variable_as_simple' ] = $varAsSimple;
		}
		if ( null !== $wpmlUnified ) {
			$platformWrite[ 'woo_wpml_unified_id' ] = $wpmlUnified;
		}
		$targets = array();
		if ( !empty( $platformWrite ) ) {
			$active = EventToggleMap::enabledSettings();
			$scoped = isset( $input[ 'content_id_platform' ] ) ? trim( (string) $input[ 'content_id_platform' ] ) : '';
			if ( '' !== $scoped ) {
				if ( !isset( $active[ $scoped ] ) || 'google_analytics' === $scoped ) {
					$valid = array_values( array_filter( array_keys( $active ), static function ( $s ) {
						return 'google_analytics' !== $s;
					} ) );
					return new \WP_Error(
						'pys_mcp_bad_content_id_platform',
						sprintf( '`content_id_platform: %s` is not an active platform. Active: %s.', $scoped, empty( $valid ) ? '(none)' : implode( ', ', $valid ) ),
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
				if ( null !== $varAsSimple ) {
					$row[ 'variable_as_simple' ] = array( 'current' => self::toBool( $inst->getOption( 'woo_variable_as_simple' ) ), 'new' => $varAsSimple );
				}
				if ( null !== $wpmlUnified ) {
					$row[ 'wpml_unified_id' ] = array( 'current' => self::toBool( $inst->getOption( 'woo_wpml_unified_id' ) ), 'new' => $wpmlUnified );
				}
				$cidPreview[ $slug ] = $row;
			}
			$pending[ 'content_id' ] = $cidPreview;
		}

		// --- 3. Per-platform event-firing toggles.
		$togglePlan    = array(); // slug => [ optionKey => bool ]
		$toggleInsts   = array(); // slug => Settings instance
		$togglePreview = array(); // slug => [ event => {current,new} ]
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
				if ( !EventToggleMap::isKnownEvent( 'woo', $ev ) ) {
					return new \WP_Error( 'pys_mcp_woo_bad_event', sprintf( 'Unknown WooCommerce event `%s`. Valid: %s.', $ev, implode( ', ', EventToggleMap::WOO_EVENTS ) ), array( 'status' => 409 ) );
				}
				$slugs = ( 'all' === $pl ) ? array_keys( $active ) : array( $pl );
				foreach ( $slugs as $slug ) {
					if ( !isset( $active[ $slug ] ) ) {
						if ( 'all' !== $pl ) {
							return new \WP_Error( 'pys_mcp_woo_bad_platform', sprintf( '`%s` is not an active platform.', $slug ), array( 'status' => 409 ) );
						}
						continue;
					}
					$inst = $active[ $slug ];
					if ( !EventToggleMap::platformSupports( 'woo', $slug, $ev ) ) {
						if ( 'all' !== $pl ) {
							$unsupported[] = $slug . '.' . $ev;
						}
						continue;
					}
					$key                            = EventToggleMap::key( 'woo', $ev, $slug );
					$togglePlan[ $slug ][ $key ]    = $en;
					$toggleInsts[ $slug ]           = $inst;
					$togglePreview[ $slug ][ $ev ]  = array( 'current' => EventToggleMap::toBool( $inst->getOption( $key ) ), 'new' => $en );
				}
			}
			if ( !empty( $togglePreview ) ) {
				$pending[ 'platform_event_toggles' ] = $togglePreview;
			}
		}

		if ( empty( $coreNew ) && empty( $platformWrite ) && empty( $togglePlan ) ) {
			return new \WP_Error(
				'pys_mcp_woo_no_args',
				'No writable WooCommerce config args provided (or platform_event_toggles targeted only platforms that do not register the event). Pass a funnel toggle, an add_to_cart trigger, a content_id field, variable_as_simple, wpml_unified_id, or platform_event_toggles.',
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
				'next_step'             => 'NOTHING HAS BEEN SAVED YET. Show pending_changes to the user and STOP this turn. Do NOT call set_woo_event_config again in this turn and do NOT set confirm: true now. WAIT for the user to reply with explicit approval in a SEPARATE message; only then resend the same args with confirm: true.',
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
			$inst->updateOptions( $platformWrite );
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
