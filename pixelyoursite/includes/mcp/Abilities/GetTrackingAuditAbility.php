<?php
/**
 * `get_tracking_audit` — single-call health check across every PYS Free domain,
 * the entry point for a tracking conversation. Returns a `summary` (one status
 * per domain) plus `detail` only for domains that need action. Read-only.
 *
 * Free domains: pixels, capi (Facebook + Pinterest server-side), content_id, woo_events, edd_events, custom_events,
 * automatic_events, gtm, mcp_activity. Pro-only domains (advanced matching, SuperPack, reports,
 * catalog feed alignment) are intentionally absent.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\Provenance;

final class GetTrackingAuditAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-tracking-audit';

	/**
	 * Pixel/identity platforms: slug => [accessor, main-id key]. GTM is included
	 * (its `gtm_id` is the identity); its enabled state is reported separately by
	 * the `gtm` domain. No TikTok / Google Ads in Free.
	 */
	private const PIXEL_PLATFORMS = array(
		'facebook'         => array( 'PixelYourSite\\Facebook', 'pixel_id' ),
		'google_analytics' => array( 'PixelYourSite\\GA', 'tracking_id' ),
		'pinterest'        => array( 'PixelYourSite\\Pinterest', 'pixel_id' ),
		'bing'             => array( 'PixelYourSite\\Bing', 'pixel_id' ),
		'reddit'           => array( 'PixelYourSite\\Reddit', 'pixel_id' ),
		'openai'           => array( 'PixelYourSite\\OpenAI', 'pixel_id' ),
		'gtm'              => array( 'PixelYourSite\\GTM', 'gtm_id' ),
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
		return 'PYS MCP — Tracking Audit';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Single-call health check across every PixelYourSite (Free) tracking domain: pixels, Facebook/Pinterest CAPI (server-side) status, content ID, WooCommerce events, EDD events, custom events, automatic events (informational — off is normal), GTM, and recent MCP activity. This is the entry point — call it first in any new conversation about tracking. Two layers: `summary` always has one status per domain (`ok` / `warning` / `incomplete` / `error` / `not_active` / `not_configured`; GTM uses `active`/`not_active`); `detail` only contains domains that are NOT ok and NOT not_active — healthy / non-applicable domains are absent by design. Use `summary` to decide which domain-specific read tool to call next; do NOT call a domain read tool for a domain shown `ok` or `not_active`. A third optional section `notes` carries informational, non-actionable observations (e.g. a content-ID / catalog-feed reminder) — surface relevant ones but do NOT treat them as errors. Do not call this tool again after a write — every write tool returns its own confirmation. The `pixels` domain flags CONFIGURED-BUT-DISABLED pixels (an ID whose master switch is off): the domain is `warning` and `detail.pixels.disabled` maps each platform to `primary_disabled`. When a funnel event is disabled it surfaces under the relevant ecommerce domain — you CAN re-enable it with `set_woo_event_config` / `set_edd_event_config` after confirming with the user. The `custom_events` domain: `warning` when the master feature `custom_events_enabled` is off (none fire) or when some events have no triggers (can never fire), with `detail` counts (total/active/paused) — drill in with `get_custom_events` / `get_custom_event`. The `capi` domain covers Facebook (and Pinterest when the add-on is active) server-side delivery: `ok` when delivery is on and the token (+ Pinterest ad_account_id) is saved, `warning` (with a fix hint) otherwise, `not_configured` when no such platform has a pixel. Advanced matching, attribution reports and catalog-feed checks are PixelYourSite Pro features and are not audited here.';
	}

	/**
	 * Input JSON-Schema.
	 *
	 * @return array
	 */
	public static function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
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
			'required'   => array( 'summary' ),
			'properties' => array(
				'summary' => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'string' ) ),
				'detail'  => array( 'type' => 'object' ),
				'notes'   => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Informational, non-actionable observations. Distinct from `detail`. Surface relevant notes but do not treat them as errors.',
				),
			),
		);
	}

	/**
	 * Build the audit summary / detail / notes across all domains.
	 *
	 * @param mixed $input Validated args (none).
	 * @return array<string, mixed>
	 */
	public static function execute( $input ): array {
		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;

		$summary = array();
		$detail  = array();
		$notes   = array();

		foreach ( self::domains( $pys ) as $domain => $eval ) {
			$summary[ $domain ] = $eval[ 'status' ];
			if ( !in_array( $eval[ 'status' ], array( 'ok', 'not_active', 'active' ), true )
			     && !empty( $eval[ 'detail' ] ) ) {
				$detail[ $domain ] = $eval[ 'detail' ];
			}
			if ( !empty( $eval[ 'notes' ] ) ) {
				foreach ( $eval[ 'notes' ] as $n ) {
					$notes[] = $n;
				}
			}
		}

		$out = array( 'summary' => $summary );
		if ( !empty( $detail ) ) {
			$out[ 'detail' ] = $detail;
		}
		if ( !empty( $notes ) ) {
			$out[ 'notes' ] = $notes;
		}

		return $out;
	}

	/**
	 * Evaluate every Free domain → [status, detail, notes].
	 *
	 * @param mixed $pys
	 * @return array<string, array{status:string, detail:array|null}>
	 */
	private static function domains( $pys ): array {
		$wcActive  = function_exists( '\\PixelYourSite\\isWooCommerceActive' ) && \PixelYourSite\isWooCommerceActive();
		$eddActive = function_exists( '\\PixelYourSite\\isEddActive' ) && \PixelYourSite\isEddActive();

		return array(
			'pixels'        => self::evalPixels(),
			'capi'          => self::evalCapi(),
			'content_id'    => self::evalContentId( $wcActive, $eddActive ),
			'woo_events'    => self::evalEcommerce( $pys, $wcActive, 'woo' ),
			'edd_events'    => self::evalEcommerce( $pys, $eddActive, 'edd' ),
			'custom_events'    => self::evalCustomEvents( $pys ),
			'automatic_events' => self::evalAutomaticEvents( $pys ),
			'gtm'              => self::evalGtm(),
			'mcp_activity'     => self::evalMcpActivity(),
		);
	}

	/**
	 * Pixels: which platforms have a main id, and whether any configured pixel
	 * is turned off (won't fire).
	 *
	 * @return array<string, mixed>
	 */
	private static function evalPixels(): array {
		$present  = array();
		$disabled = array();
		foreach ( self::PIXEL_PLATFORMS as $slug => $spec ) {
			$has              = self::hasId( $spec[ 0 ], $spec[ 1 ] );
			$present[ $slug ] = $has;
			if ( !$has ) {
				continue;
			}

			$entry = array();
			if ( 'gtm' !== $slug ) {
				$inst = self::instance( $spec[ 0 ] );
				$raw  = $inst ? $inst->getOption( 'main_pixel_enabled' ) : null;
				if ( null !== $raw && !self::toBool( $raw ) ) {
					$entry[ 'primary_disabled' ] = true;
				}
			}
			if ( !empty( $entry ) ) {
				$disabled[ $slug ] = $entry;
			}
		}
		$any = in_array( true, $present, true );

		if ( !$any ) {
			return array( 'status' => 'incomplete', 'detail' => array( 'pixel_present' => $present ) );
		}
		if ( !empty( $disabled ) ) {
			return array(
				'status' => 'warning',
				'detail' => array(
					'pixel_present' => $present,
					'disabled'      => $disabled,
					'note'          => 'These configured pixels are TURNED OFF and will NOT fire. `primary_disabled` = the platform master switch is off — re-enable it in the admin (PixelYourSite → the platform).',
				),
			);
		}

		return array(
			'status' => 'ok',
			'detail' => array( 'pixel_present' => $present ),
		);
	}

	/**
	 * Server-side (CAPI) audit domain — Free has functional Facebook CAPI,
	 * OpenAI CAPI and Pinterest CAPI (Pinterest add-on). Read-only: reports
	 * enabled / token-present (never the token value), plus Pinterest's
	 * ad_account_id and OpenAI's validate_only. Per-platform status: ok when
	 * delivery is on AND its token (+ Pinterest ad_account) is saved AND nothing
	 * is suppressing delivery; warning otherwise (with a fix hint). A platform is skipped when it has no
	 * pixel configured (a CAPI warning is meaningless without a pixel). Domain
	 * status: ok if all configured platforms are ok, warning if any warn,
	 * not_configured when no platform qualifies, not_active if Facebook is absent.
	 *
	 * @return array<string, mixed>
	 */
	private static function evalCapi(): array {
		if ( !function_exists( 'PixelYourSite\\Facebook' ) ) {
			return array( 'status' => 'not_active', 'detail' => null );
		}

		$platforms  = array();
		$anyWarning = false;

		// Facebook — server_access_api_token is a per-pixel array in Free.
		$fb = self::instance( 'PixelYourSite\\Facebook' );
		if ( $fb && self::hasId( 'PixelYourSite\\Facebook', 'pixel_id' ) ) {
			$tokenSet = self::hasValue( $fb->getOption( 'server_access_api_token' ) );
			$capiOn   = self::toBool( $fb->getOption( 'use_server_api' ) );
			if ( $capiOn && $tokenSet ) {
				$platforms[ 'facebook' ] = array( 'status' => 'ok', 'detail' => null );
			} else {
				$anyWarning              = true;
				$platforms[ 'facebook' ] = array(
					'status' => 'warning',
					'detail' => array(
						'token'        => $tokenSet ? 'configured' : 'missing',
						'capi_enabled' => $capiOn,
						'fix'          => $tokenSet
							? 'CAPI token is saved but server-side delivery is off. Enable it in PixelYourSite → Dashboard.'
							: 'No CAPI token saved. Go to PixelYourSite → Dashboard to add your Facebook Conversions API token and enable delivery. (Saving the token via the AI assistant is a Pro feature — do it manually in the admin.)',
					),
				);
			}
		}

		// OpenAI — ships with Free, so no add-on check; skipped without a pixel.
		$oa = function_exists( 'PixelYourSite\\OpenAI' ) ? self::instance( 'PixelYourSite\\OpenAI' ) : null;

		if ( $oa && self::hasId( 'PixelYourSite\\OpenAI', 'pixel_id' ) ) {

			$tokenSet     = self::hasValue( $oa->getOption( 'server_access_api_token' ) );
			$capiOn       = self::toBool( $oa->getOption( 'use_server_api' ) );
			$validateOnly = self::toBool( $oa->getOption( 'server_validate_only' ) );

			if ( $capiOn && $tokenSet && !$validateOnly ) {
				$platforms[ 'openai' ] = array( 'status' => 'ok', 'detail' => null );
			} else {
				$anyWarning            = true;
				$platforms[ 'openai' ] = array(
					'status' => 'warning',
					'detail' => array(
						'token'         => $tokenSet ? 'configured' : 'missing',
						'capi_enabled'  => $capiOn,
						'validate_only' => $validateOnly,
						'fix'           => !$tokenSet
							? 'No OpenAI Conversions API key saved. Go to PixelYourSite → Dashboard → Your OpenAI Ads to add it.'
							: ( !$capiOn
								? 'OpenAI Conversions API key is saved but server-side delivery is off. Enable it in PixelYourSite → Dashboard → Your OpenAI Ads.'
								: 'OpenAI server-side delivery is on, but "Send events for validation only" is ALSO on — events are validated and then DISCARDED, nothing is recorded. Turn it off in PixelYourSite → Dashboard → Your OpenAI Ads once testing is done.' ),
					),
				);
			}
		}

		// Pinterest — only when the add-on is active and a pixel is configured.
		if ( function_exists( 'PixelYourSite\\isPinterestActive' )
			&& \PixelYourSite\isPinterestActive()
			&& self::hasId( 'PixelYourSite\\Pinterest', 'pixel_id' ) ) {

			$pi       = self::instance( 'PixelYourSite\\Pinterest' );
			$tokenSet = $pi && self::hasValue( $pi->getOption( 'server_access_api_token' ) );
			$capiOn   = $pi && self::toBool( $pi->getOption( 'use_server_api' ) );
			$adAcct   = $pi && self::hasValue( $pi->getOption( 'ad_account_id' ) );

			if ( $capiOn && $tokenSet && $adAcct ) {
				$platforms[ 'pinterest' ] = array( 'status' => 'ok', 'detail' => null );
			} else {
				$anyWarning               = true;
				$platforms[ 'pinterest' ] = array(
					'status' => 'warning',
					'detail' => array(
						'token'         => $tokenSet ? 'configured' : 'missing',
						'capi_enabled'  => $capiOn,
						'ad_account_id' => $adAcct ? 'configured' : 'missing',
						'fix'           => !$tokenSet
							? 'No Pinterest CAPI token saved. Go to PixelYourSite → Dashboard to add your token.'
							: ( !$adAcct
								? 'Pinterest CAPI requires an Ad Account ID. Go to PixelYourSite → Dashboard to add it.'
								: 'Pinterest CAPI token and Ad Account ID are saved but delivery is off. Enable it in PixelYourSite → Dashboard.' ),
					),
				);
			}
		}

		foreach ( $platforms as &$p ) {
			if ( 'warning' === ( $p[ 'status' ] ?? '' ) && isset( $p[ 'detail' ][ 'fix' ] ) ) {
				$p[ 'detail' ][ 'fix' ] .= ' IMPORTANT: PixelYourSite Free MCP has NO tool to enable server-side delivery or save the CAPI token — this is a MANUAL admin action. Tell the user to do it themselves on that page in the PixelYourSite admin; do NOT offer to enable/save it via the assistant (writing CAPI settings requires PixelYourSite Pro).';
			}
		}
		unset( $p );

		if ( empty( $platforms ) ) {
			return array( 'status' => 'not_configured', 'detail' => null );
		}

		return array(
			'status' => $anyWarning ? 'warning' : 'ok',
			'detail' => $anyWarning ? $platforms : null,
		);
	}

	/**
	 * Content ID. Always `ok` when ecommerce is active (no server-detectable
	 * failure), plus an informational catalog-feed alignment reminder.
	 *
	 * @param bool $wcActive
	 * @param bool $eddActive
	 * @return array<string, mixed>
	 */
	private static function evalContentId( bool $wcActive, bool $eddActive ): array {
		if ( !$wcActive && !$eddActive ) {
			return array( 'status' => 'not_active', 'detail' => null );
		}
		$notes = array();

		$fbForWoo = class_exists( 'WC_Facebookcommerce' ) || function_exists( 'facebook_for_woocommerce' );
		$fb       = self::instance( 'PixelYourSite\\Facebook' );
		if ( $wcActive && $fb && 'facebook_for_woocommerce' === (string) $fb->getOption( 'woo_content_id_logic' )
		     && !$fbForWoo ) {
			$notes[] = 'WooCommerce content_id_logic is stored as `facebook_for_woocommerce`, but the Facebook for WooCommerce plugin is inactive — PYS currently uses DEFAULT logic, so content IDs generate normally. The stored value is dormant. To clear it, set content_id_logic to `default`.';
		}

		$notes[] = 'Content-ID reminder: confirm your platform catalog feed uses the SAME content_id source (product/download id vs SKU) and prefix/suffix that PYS sends — a mismatch makes dynamic ads show wrong items. PYS cannot see the feed, so verify it in the platform catalog manager.';

		return array( 'status' => 'ok', 'detail' => null, 'notes' => $notes );
	}

	/**
	 * Woo / EDD: not_active if the platform is off; warning if any funnel event
	 * is disabled; ok otherwise. Free funnel set (no view_item_list / checkout
	 * steps — those are Pro).
	 *
	 * @param mixed  $pys
	 * @param bool   $active
	 * @param string $prefix
	 * @return array<string, mixed>
	 */
	private static function evalEcommerce( $pys, bool $active, string $prefix ): array {
		if ( null === $pys || !$active ) {
			return array( 'status' => 'not_active', 'detail' => null );
		}
		$funnelKeys = 'woo' === $prefix ? array(
			'woo_view_content_enabled',
			'woo_view_category_enabled',
			'woo_add_to_cart_enabled',
			'woo_initiate_checkout_enabled',
			'woo_purchase_enabled',
			'woo_view_cart_enabled',
			'woo_remove_from_cart_enabled',
		) : array(
			'edd_view_content_enabled',
			'edd_view_category_enabled',
			'edd_add_to_cart_enabled',
			'edd_initiate_checkout_enabled',
			'edd_purchase_enabled',
			'edd_remove_from_cart_enabled',
		);

		$disabled = array();
		foreach ( $funnelKeys as $key ) {
			if ( !self::toBool( $pys->getOption( $key ) ) ) {
				$disabled[] = $key;
			}
		}
		$getTool = 'woo' === $prefix ? 'get_woo_events_config' : 'get_edd_events_config';

		$platformNote = sprintf(
			'This reflects the CORE %1$s funnel master toggles only — it does NOT evaluate the per-platform layer. An event whose master is ON can still be OFF on a specific platform/pixel; it fires on a pixel only when BOTH the core master AND that platform toggle are on. For the per-platform picture (and to spot events not firing on a given pixel) read `platform_events` in `%2$s`.',
			strtoupper( $prefix ),
			$getTool
		);
		if ( empty( $disabled ) ) {
			return array( 'status' => 'ok', 'detail' => null, 'notes' => array( $platformNote ) );
		}
		$path    = 'woo' === $prefix ? 'PixelYourSite → WooCommerce → Events' : 'PixelYourSite → Easy Digital Downloads → Events';
		$setTool = 'woo' === $prefix ? 'set_woo_event_config' : 'set_edd_event_config';

		return array(
			'status' => 'warning',
			'detail' => array(
				'disabled_funnel_events' => $disabled,
				'note'                   => sprintf(
					'These funnel events are off. They ARE re-enableable via `%s` (pass the funnel toggle arg, e.g. `purchase`/`initiate_checkout`/`add_to_cart`) after user confirmation — offer to do it. Or the user can toggle them manually in the admin.',
					$setTool
				),
				'admin_ui_path'          => $path,
			),
			'notes'  => array( $platformNote ),
		);
	}

	/**
	 * Custom events: not_active when none exist; warning when the master feature
	 * is off (none fire) or some events have no triggers (can never fire); ok
	 * otherwise. Counts over all events (active + paused).
	 *
	 * @param mixed $pys
	 * @return array<string, mixed>
	 */
	private static function evalCustomEvents( $pys ): array {
		if ( !class_exists( '\\PixelYourSite\\CustomEventFactory' ) ) {
			return array( 'status' => 'not_active', 'detail' => null );
		}
		$events = (array) \PixelYourSite\CustomEventFactory::get( 'any' );
		$total  = count( $events );
		if ( 0 === $total ) {
			return array( 'status' => 'not_active', 'detail' => null );
		}

		$feature    = null !== $pys && self::toBool( $pys->getOption( 'custom_events_enabled' ) );
		$paused     = 0;
		$noTriggers = array();
		foreach ( $events as $event ) {
			if ( !is_object( $event ) ) {
				continue;
			}
			if ( !(bool) $event->__get( 'enabled' ) ) {
				$paused++;
			}
			$triggers = method_exists( $event, 'getTriggers' ) && is_array( $event->getTriggers() ) ? $event->getTriggers() : array();
			if ( empty( $triggers ) ) {
				$noTriggers[] = array( 'event_id' => (int) $event->getPostId(), 'title' => (string) $event->getTitle() );
			}
		}

		$detail = array(
			'feature_enabled' => $feature,
			'total'           => $total,
			'active'          => $total - $paused,
			'paused'          => $paused,
		);
		$warning = false;
		if ( !$feature ) {
			$warning                  = true;
			$detail[ 'note_feature' ] = 'The custom-events master feature (custom_events_enabled) is OFF — none of these events fire regardless of their own state. Enable it in PixelYourSite → Events.';
		}
		if ( !empty( $noTriggers ) ) {
			$warning                      = true;
			$detail[ 'without_triggers' ] = $noTriggers;
			$detail[ 'note_triggers' ]    = sprintf( '%d custom event(s) have no triggers and can never fire — add a trigger with set_custom_event, or remove the event with manage_custom_event.', count( $noTriggers ) );
		}
		$detail[ 'tools' ] = 'get_custom_events (list) → get_custom_event (detail) → set_custom_event (edit) / manage_custom_event (enable/disable/duplicate/delete).';

		return array( 'status' => $warning ? 'warning' : 'ok', 'detail' => $detail );
	}

	/**
	 * Automatic (page-level) events — INFORMATIONAL, never a warning. Unlike the
	 * ecommerce funnel, automatic events are OPTIONAL and OFF by default, so
	 * "off" is a normal choice, not a misconfiguration. `not_active` when the
	 * master switch is off; `ok` (with an enabled/total count) when on. The Free
	 * set is exactly these 9 (others are Pro).
	 *
	 * @param mixed $pys
	 * @return array<string, mixed>
	 */
	private static function evalAutomaticEvents( $pys ): array {
		$stems = array( 'form', 'signup', 'login', 'download', 'comment', 'scroll', 'time_on_page', '404', 'search' );

		if ( null === $pys || !self::toBool( $pys->getOption( 'automatic_events_enabled' ) ) ) {
			return array(
				'status' => 'not_active',
				'detail' => null,
				'notes'  => array( 'Automatic events (form / signup / login / download / comment / scroll / time on page / 404 / search) are OPTIONAL and their master switch is OFF — this is a normal default, NOT a misconfiguration, so it is not flagged as a warning. Enable via set_automatic_event_config (master_enabled + the specific events) only if the user wants page-level tracking.' ),
			);
		}

		$enabled = array();
		foreach ( $stems as $stem ) {
			if ( self::toBool( $pys->getOption( 'automatic_event_' . $stem . '_enabled' ) ) ) {
				$enabled[] = $stem;
			}
		}

		return array(
			'status' => 'ok',
			'detail' => null,
			'notes'  => array( sprintf(
				'Automatic events: master ON, %1$d of %2$d enabled (%3$s). Events being off is OPTIONAL/normal — NOT a warning. Manage via get_/set_automatic_event_config. (Automatic events beyond these 9 — AdSense, link clicks, video, etc. — are Pro.)',
				count( $enabled ),
				count( $stems ),
				empty( $enabled ) ? 'none' : implode( ', ', $enabled )
			) ),
		);
	}

	/**
	 * GTM: active when the container is enabled.
	 *
	 * @return array<string, mixed>
	 */
	private static function evalGtm(): array {
		$gtm = self::instance( 'PixelYourSite\\GTM' );
		$on  = $gtm && method_exists( $gtm, 'enabled' ) && $gtm->enabled();

		return array( 'status' => $on ? 'active' : 'not_active', 'detail' => null );
	}

	/**
	 * Recent MCP write activity from the provenance log. Always ok.
	 *
	 * @return array<string, mixed>
	 */
	private static function evalMcpActivity(): array {
		Provenance::getRecent( 1 ); // touch — detail lives in the Settings-tab UI.

		return array( 'status' => 'ok', 'detail' => null );
	}

	// ---- helpers ----------------------------------------------------------

	/**
	 * Resolve a platform singleton that exposes getOption().
	 *
	 * @param string $fn
	 * @return object|null
	 */
	private static function instance( string $fn ) {
		if ( !function_exists( $fn ) ) {
			return null;
		}
		$inst = $fn();

		return is_object( $inst ) && method_exists( $inst, 'getOption' ) ? $inst : null;
	}

	/**
	 * Whether a platform instance has a non-empty value for the given key.
	 *
	 * @param string $fn
	 * @param string $key
	 * @return bool
	 */
	private static function hasId( string $fn, string $key ): bool {
		$inst = self::instance( $fn );

		return $inst && self::hasValue( $inst->getOption( $key ) );
	}

	/**
	 * Whether a scalar or array option holds a non-empty string value.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function hasValue( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				if ( is_string( $v ) && '' !== trim( $v ) ) {
					return true;
				}
			}

			return false;
		}

		return is_string( $value ) && '' !== trim( $value );
	}

	/**
	 * Normalize a stored option value to a boolean.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function toBool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return '1' === $value || 'true' === strtolower( $value );
		}

		return is_int( $value ) && 1 === $value;
	}
}