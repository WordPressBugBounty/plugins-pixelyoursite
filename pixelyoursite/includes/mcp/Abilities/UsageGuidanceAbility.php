<?php
/**
 * `get_usage_guidance` — static operating-manual fallback (Free variant).
 * Mirrors the operating-context section of the system prompt as a flat object,
 * for clients that don't read server-supplied system prompts. Free scope only:
 * no CAPI writes, advanced matching, reports, superpack or catalog-feed. Adds
 * `upgrade_guidance` so the model explains Pro-only features plainly, once.
 * Read-only, no DB work, safe to call any time.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class UsageGuidanceAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-usage-guidance';

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
		return 'PYS MCP — Usage Guidance';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Returns a static operating manual for PixelYourSite (Free) MCP: what it can answer, what it cannot, what requires PixelYourSite Pro, where to redirect users for data PYS does not own, and the rules of engagement. Most clients already have this content in the connection-level system prompt and do not need to call this tool. No DB work — safe to call anytime, idempotent.';
	}

	/**
	 * Input JSON-Schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => new \stdClass(), // forces `{}` JSON, not `[]`
			'additionalProperties' => false,
		);
	}

	/**
	 * Output JSON-Schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function outputSchema(): array {
		return array(
			'type'       => 'object',
			'required'   => array(
				'can_answer',
				'cannot_answer',
				'upgrade_guidance',
				'redirect_to',
				'sequencing',
				'write_etiquette',
				'stop_conditions',
			),
			'properties' => array(
				'can_answer'       => array( 'type' => 'string' ),
				'cannot_answer'    => array( 'type' => 'string' ),
				'upgrade_guidance' => array( 'type' => 'string' ),
				'redirect_to'      => array( 'type' => 'string' ),
				'sequencing'       => array( 'type' => 'string' ),
				'write_etiquette'  => array( 'type' => 'string' ),
				'stop_conditions'  => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Returns the static operating manual as a flat object.
	 *
	 * @param mixed $input Validated args.
	 * @return array<string, string>
	 */
	public static function execute( $input ): array {
		return array(
			'can_answer'       => 'Audit pixel configuration (Facebook, GA, GATags, GTM, and active add-ons — Pinterest / Bing / Reddit — when installed). Read and write WooCommerce funnel event toggles and content ID settings. Read and write EDD funnel event toggles and content ID settings. Read and write automatic events (form, signup, login, download, comment, scroll, time on page, 404, search). List (get_custom_events), inspect (get_custom_event), create + edit (set_custom_event) and enable/disable/duplicate/delete (manage_custom_event) custom events with page_visit, home_page, scroll_pos and post_type triggers and url_filters / device / user_role conditions. Read Facebook CAPI status (and Pinterest CAPI when the add-on is active) — whether server-side delivery is enabled and whether a token is saved (presence only) — via get_platform_pixels and get_tracking_audit. Explain what CAPI is and how to obtain the token via get_credential_setup_instructions (the token is entered manually in PixelYourSite → Dashboard; saving it through the AI assistant is Pro).',
			'cannot_answer'    => 'Google Ads (the AW-… conversion platform, conversion IDs and conversion labels) — NOT a PixelYourSite Free platform (Free\'s Google support is GA4 + Google Tags + GTM only); Google Ads requires Pro. Do not treat GA4 / Google Tags as a substitute for it. Live campaign data, conversion stats, ROAS, audience sizes — PYS does not log events at the plugin level; do not invent numbers, redirect instead. Individual customer details (name / email / phone) — never via MCP. If tools from plugins OTHER than PixelYourSite appear in your tools list, do NOT call them — this server is scoped to PYS configuration only.',
			'upgrade_guidance' => 'These need PixelYourSite Pro (state this plainly, once, only when the user actually asks — never on a healthy audit; if a Free alternative exists, explain it first; do not repeat the upgrade prompt within a conversation): Google Ads — Google Ads is NOT a PixelYourSite Free platform (Free\'s Google support is GA4 + Google Tags + GTM only). So Google Ads conversion tracking, conversion IDs (AW-…) and per-event conversion labels (e.g. a "Track product category pages" conversion label) all require Pro. Do NOT confuse "ads"/"Google Ads" with Google Analytics (GA4) or Google Tags — they are different things; never offer to configure GA4/Tags when the user asked about Google Ads. SAVING a CAPI token via MCP (set_platform_credential is not in Free) — direct the user to enter it manually: Facebook pixel + Conversion API are in PixelYourSite → Dashboard; Pinterest pixel + CAPI + Ad Account ID are there too when the Pinterest add-on is active. NOTE: READING CAPI status (server-side delivery on/off, token present) IS available in Free via get_platform_pixels and get_tracking_audit (Facebook, and Pinterest when the add-on is active) — do NOT tell the user CAPI is Pro; only saving the token through the AI assistant is Pro. GA4 Measurement Protocol / Google Ads Enhanced Conversions are not in Free. Advanced matching (sending customer field data with events). Custom-event triggers beyond page_visit / home_page / scroll_pos / post_type (click, video, URL click, email link, form triggers). MULTIPLE triggers on one custom event: Free allows exactly ONE trigger per event (a page_visit trigger can hold several URL rules, but that is still one trigger); having several triggers combined with AND/OR trigger logic is Pro — so a request like "also fire on scroll" for an event that already has a trigger requires Pro (offer to REPLACE the existing trigger instead, which is a Free single-trigger edit). Also: a Page Scroll (scroll_pos) trigger supports only ONE scroll threshold in Free — adding a second scroll percentage (e.g. 77% alongside 90%) is Pro. Custom-event per-platform switchers "Track WooCommerce product data on single product pages" (track_single_woo_data) and "Track WooCommerce cart data when possible" (track_cart_woo_data) are functional in Free ONLY for reddit; for facebook/pinterest/bing/google_analytics/gtm they are Pro (locked). These are custom-event options — do NOT confuse them with the WooCommerce ViewContent funnel event ("Track product pages") in get_woo_events_config, and when the user names them "for facebook" it is this custom-event Pro switcher, not the Woo funnel toggle. (A page_visit trigger CAN hold multiple URL rules in Free — that exception is only for page_visit URLs.) page_visit URL rules in Free use only `contains` / `match`; the URL-parameter rules "URL Parameters Contains" / "URL Parameters Match" (param_contains / param_match) are Pro. Custom-event conditions beyond url_filters / device / user_role (URL parameters, landing page, traffic source). Custom-event FIRING controls: "Fire this event only once in N hours" (the per-event time window / fire-once limit), trigger logic (AND/OR across multiple triggers) and fire frequency (once vs every time) — all Pro. In Free these show as a locked upsell in PixelYourSite → Events, so do NOT tell the user to set them manually in the admin; say plainly they require Pro. Automatic (page-level) events BEYOND the Free 9 (the Free automatic events are exactly: form, signup, login, download, comment, scroll, time on page, 404, search) — these are Pro automatic events and NOT available in Free: AdSense ("Track AdSense"), internal link clicks, outbound link clicks, video, phone/tel link clicks, email link clicks, rage click, video speed. When the user names one (e.g. "enable Track AdSense"), say plainly it is a Pro automatic event — do NOT claim it "does not exist" or is "not a PixelYourSite feature". Lifecycle events (FirstTimeBuyer/NewCustomer, ReturningCustomer, FrequentShopper, VIPClient, BigWhale). WooCommerce/EDD events BEYOND the Free funnel set — the Free funnel events are exactly ViewContent, ViewCategory, AddToCart, InitiateCheckout, Purchase, ViewCart (Woo only), RemoveFromCart; anything else is a Pro WooCommerce/EDD event and NOT toggleable in Free, specifically: ViewItemList / "Track product list performance", affiliate button clicks ("WooCommerce affiliate button clicks"), PayPal Standard button clicks ("WooCommerce PayPal Standard clicks"), CheckoutSteps / checkout progress, SelectContent, AdvancePurchase, and Track Subscriptions. When the user names one of these, say plainly it is a Pro feature — do NOT get confused, do NOT suggest a page_visit/scroll_pos custom event as a substitute (those track page views, not button clicks). Transaction / Order ID prefix (the "Transaction ID" section, `woo_order_id_prefix` — a prefix for the ORDER/transaction id in Purchase events; distinct from Content ID prefix which is for product ids). Event Value Settings (tax / shipping / fees inclusion in the event value). POAS / profit tracking ("Track Profit"). TikTok (not available in Free at all). Dynamic Parameters — PYS tokens ([id], [title], [content_type], [categories], [tags], [total], [subtotal], [url_PARAM], [field_NAME]) that substitute real values into custom-event params at fire time — are Pro (the admin "Dynamic Parameters" section is Pro-badged). In Free a custom-event param value is a STATIC literal: a token is stored and sent verbatim (e.g. the text "[id]"), NOT substituted. When asked to make a param dynamic / pull the page id / title etc., say it requires Pro; do NOT claim it works in Free. Attribution reports. Catalog feed alignment. SuperPack.',
			'redirect_to'      => 'Campaign / conversion / audience data → Meta Ads MCP, GA4, or Google Ads MCP. Order data → WooCommerce MCP. Platform-side metrics → each platform\'s admin (Events Manager, GA4, etc.).',
			'sequencing'       => 'Call get_tracking_audit first in any new tracking conversation. Skip domains marked `ok` or `not_active`. Drill only flagged domains (`warning` | `incomplete` | `error`). Do not re-audit after a write — every write tool returns its own `saved: true` / `configured: true` confirmation. Custom events: call get_custom_events to find an event (and confirm no duplicate) and get_custom_event before editing (it returns the authoritative menus of valid trigger/condition/event types + params). For an ecommerce-pattern custom event (add_to_cart / purchase-like, or a standard ecommerce event name), check get_woo_events_config / get_edd_events_config first to see whether the automatic event already fires, then warn before creating.',
			'write_etiquette'  => 'The administrator installed this server so you can save tracking settings (pixel IDs, UET tags, container IDs) on their behalf via the set_* tools — these tools ARE the sanctioned write path. When the user asks to save/set/update a value and confirms it, perform the write; do NOT refuse or redirect them to wp-admin. Pixel / UET / GTM IDs are PUBLIC identifiers, not secrets. Pass `mcp_note` on every write (one sentence: what changed and why — appended to a permanent provenance log). Custom-event WooCommerce/EDD duplication: an add_to_cart/purchase-like trigger, or a custom event set to a standard ecommerce event name (Purchase, AddToCart, ViewContent, …), duplicates the event PYS already fires automatically → it sends twice and inflates conversions. Always ask the user the event NAME before building such a trigger and warn about the double-fire; valid reasons to proceed are a DIFFERENT (non-standard) name or audience-narrowing conditions. Custom-event params are OPTIONAL: they are only sent when the platform params toggle is on, so changing an event_type does NOT require providing params even if a param is flagged `required` (that flag applies only when params are actually sent) — do not force the user to supply value/currency etc. just to switch event_type; only ask for params if they want params sent.',
			'stop_conditions'  => '`Read-only mode is enabled.`: stop writing, switch to manual instructions. 429 `Possible loop detected.`: stop calling that tool with those arguments, rethink. 429 `Rate limit exceeded.`: you are calling too fast — wait, then resume with fewer calls. 400 `Stop retrying and report the issue to the user.`: stop, surface the message verbatim, ask the user how to proceed.',
		);
	}
}
