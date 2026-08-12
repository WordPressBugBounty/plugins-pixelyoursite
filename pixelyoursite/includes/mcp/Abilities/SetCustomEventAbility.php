<?php
/**
 * `set_custom_event` — create or update a PixelYourSite custom event (Free variant):
 * event-level settings, per-platform pixel config, and point-wise trigger /
 * condition ops. Every write goes through read→merge→write
 * ({@see CustomEventArgsSerializer}), so a partial patch never drops the parts it
 * does not touch. Read-only gated, `mcp_note` required, two-step `confirm`.
 *
 * Free differs from Pro: only 4 trigger types are writable (page_visit, home_page,
 * scroll_pos, post_type); 3 condition types (url_filters, device, user_role) with a
 * SINGLE condition per event; platform params are FLAT name→string values (dynamic
 * tokens are passed verbatim as the string — no {value,selector,dynamic} objects);
 * Google is the unified `google_analytics` block (no ga_ads / google_ads split, no
 * TikTok). Events that carry non-writable triggers are refused (Free's update()
 * would drop them).
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use PixelYourSite\MCP\CustomEventArgsSerializer;
use PixelYourSite\MCP\CustomEventPlatformMap;
use PixelYourSite\MCP\CustomEventTriggerMap;
use PixelYourSite\MCP\CustomEventConditionMap;

final class SetCustomEventAbility extends AbstractWriteAbility {

	public const ID = 'pixelyoursite/set-custom-event';

	/** Trigger types Free can create/edit here. */
	private const WRITABLE_TRIGGER_TYPES = array( 'page_visit', 'home_page', 'scroll_pos', 'post_type' );

	/** Trigger types that need at least one rule when added. */
	private const RULE_BASED_TYPES = array( 'page_visit', 'scroll_pos' );

	/** Point-wise trigger op params (name => coercion type). */
	private const TRIGGER_OP_PARAMS = array(
		'delay'           => 'int',
		'post_type_value' => 'string',
	);

	/** Platforms writable here (all Free custom-event platforms). */
	public const WRITABLE_PLATFORMS = array( 'facebook', 'pinterest', 'bing', 'reddit', 'google_analytics', 'gtm' );

	/**
	 * Per-event pixel selection is NOT writable for ANY platform in Free: the site
	 * has a single pixel per platform and choosing which pixel(s) an event fires to
	 * (or firing to multiple) is a SuperPack / Pro feature — the Free custom-event
	 * admin exposes no functional per-event pixel selector (facebook/pinterest have
	 * none and update() ignores their pixel_id; gtm is a fixed hidden single pixel;
	 * bing/reddit default to `all`; the multi-pixel "All pixels" selector is a Pro
	 * teaser). The event simply fires to each enabled platform's configured pixel.
	 */
	private const PIXEL_LOCKED_PLATFORMS = array( 'facebook', 'pinterest', 'bing', 'reddit', 'google_analytics', 'gtm' );

	private const PLATFORM_FIELDS = array(
		'enabled', 'pixel_ids', 'pixel_id', 'event_type', 'event_type_group', 'custom_event_type',
		'params_enabled', 'params', 'custom_params', 'track_single_woo_data', 'track_cart_woo_data', 'conversion_label',
	);

	private const EVENT_FIELDS = array(
		'title'              => array( 'title', 'string' ),
		'enabled'            => array( 'enabled', 'bool' ),
		'conditions_enabled' => array( 'conditions_enabled', 'bool' ),
		'conditions_logic'   => array( 'conditions_logic', 'logic' ),
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
		return 'PYS MCP — Set Custom Event';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Creates or updates a PixelYourSite CUSTOM EVENT. Authorized write: the administrator installed this server so you can manage custom events on their behalf — perform the write after confirmation; do not redirect to wp-admin. Omit `event_id` to CREATE a new event; pass an `event_id` (from `get_custom_events`) to UPDATE one. Writes EVENT-LEVEL settings: `title`, `enabled` (active/paused), `conditions_enabled` + `conditions_logic` (`OR`/`AND`). It also writes the per-platform pixel config (`platforms`) and the event\'s TRIGGERS (`triggers`, point-wise add/update/remove — a trigger is a condition under which the event fires) and the event\'s display CONDITIONS (`conditions`, point-wise add/update/remove — gate where/for-whom the event fires; pair with `conditions_enabled`). Any partial write NEVER removes the parts you did not touch. Read the event with `get_custom_event` first so you change real values. **Free-specific limits (this is the Free plugin):** only 4 trigger types are editable here — `page_visit`, `home_page`, `scroll_pos`, `post_type`; the event may have only ONE trigger AND only ONE condition (of type `url_filters`, `device` or `user_role`). Multiple triggers per event (combined with AND/OR trigger logic) are PixelYourSite Pro — adding a second trigger is rejected; to change the trigger use op:update, or op:remove the existing one first. Richer triggers/conditions (url_click, css_click, add_to_cart, purchase, video_view, form triggers, url_parameters/landing_page/source conditions, event-fire logic/frequency/time-window) are PixelYourSite Pro. If the event already contains a non-editable trigger, this tool refuses the write (saving would drop it) — tell the user to edit that event in PixelYourSite → Events or upgrade to Pro. **When ADDING a trigger, do NOT silently accept defaults:** look up the type\'s OPTIONAL params in get_custom_event `available_trigger_types` (e.g. `delay` for page_visit/home_page/post_type) and ASK the user in the same message where you confirm required values. Note: a custom event only fires when the master feature `custom_events_enabled` is on (get_custom_events.feature_enabled) AND the event has at least one trigger. **WooCommerce / EDD duplication:** before setting a platform to a STANDARD ecommerce event name (Purchase, AddToCart, ViewContent, begin_checkout, view_item, …), ASK the user what event NAME they want — PixelYourSite very likely already fires that ecommerce event automatically, so a same-named custom event would send it TWICE. The preview `notes` flag this. **Platforms:** writable slugs are `facebook`, `pinterest`, `bing`, `reddit`, `google_analytics` (unified GA4 — Google Ads / TikTok are Pro), `gtm`. Each platform object may set: `enabled` (bool). NOTE: per-event PIXEL selection (`pixel_ids`/`pixel_id`) is NOT available in Free for any platform — the event fires to each enabled platform\'s single configured pixel; choosing specific pixels per event, or firing to multiple pixels, is a SuperPack/Pro feature (passing pixel_ids/pixel_id is rejected). Also settable: `event_type` (must be valid for the platform — see get_custom_event `available_event_types`; type names are platform- and CASE-specific, e.g. AddToCart for facebook/bing/reddit, addtocart for pinterest, add_to_cart for GA/GTM; GA/GTM are grouped GA actions), `event_type_group` (GA/GTM only — the category group; if omitted the first matching group is stored), `custom_event_type` (the free event NAME — REQUIRED when event_type is a custom-name type: facebook `CustomEvent`, pinterest `custom`/`partner_defined`, bing `Custom`, reddit `Custom`, GA/GTM `CustomEvent`/`_custom`), `params_enabled` (bool), `track_single_woo_data` (the custom-event switcher "Track WooCommerce product data on single product pages") / `track_cart_woo_data` ("Track WooCommerce cart data when possible") — these are per-platform CUSTOM-EVENT options and in Free are functional ONLY for `reddit`; for facebook/pinterest/bing/google_analytics/gtm they are Pro (locked switchers) and rejected. Do NOT confuse them with the WooCommerce ViewContent funnel event ("Track product pages" in get_woo_events_config) — different thing. `conversion_label` (gtm). `params` (standard event params): an OBJECT keyed by param name → a STRING value. Free stores flat STATIC string values only; the object/selector shapes AND PixelYourSite dynamic-parameter TOKENS (`[id]`, `[title]`, `[url_*]`, `[field_*]`, `[total]`, …) are PixelYourSite Pro — in Free a token is stored and sent VERBATIM as literal text (e.g. `"[id]"`), NOT substituted, so do NOT set a param to a token expecting substitution (say it requires Pro; see get_custom_event `dynamic_parameters_note`). `params` is a PARTIAL MERGE: only the keys you pass change; to CLEAR a param pass it with an empty string/null. Valid param names depend on the current `event_type` — see get_custom_event `available_event_types`; a custom event_type has no standard params. IMPORTANT: params are OPTIONAL — they are only sent when the platform\'s params toggle is on. Changing `event_type` alone does NOT require you to send params, even for an event whose params are marked `required` (e.g. facebook Purchase value/currency): the `required` flag applies ONLY when the user chooses to send params. Do NOT force the user to provide "required" params just to switch event_type — omit `params` to fire the event with none. `custom_params`: an ARRAY of `{name, value}` (replaces the existing list; facebook/pinterest/bing/GA/GTM — reddit is the only platform with none). Writing params/custom_params auto-enables the platform\'s params toggle. GTM-only extras: `automated_params` (bool), `remove_custom_trigger_object` (bool), `use_custom_object_name` (bool) + `custom_object_name` (string). Only pass what you want to change; the rest is preserved. **Triggers arg:** each item `op` (`add`/`update`/`remove`), `index` (the `trigger_index` from get_custom_event — required for update/remove), `type` (required for add; one of page_visit/home_page/scroll_pos/post_type). `rules`: for page_visit each item `{rule, value}` with `rule` ∈ contains/match ONLY (value `*` matches all pages; the URL-parameter rules `param_contains`/`param_match` — "URL Parameters Contains/Match" — are PixelYourSite Pro and are rejected); for scroll_pos each item `{value}` (scroll percent, no rule). page_visit/scroll_pos need ≥1 rule when added; home_page/post_type need none. Params: `delay` (page_visit/home_page/post_type, in SECONDS), `post_type_value` (post_type) — pass at the op top level or nested under `params`. **Conditions arg:** each item `op`/`index`/`type`. Types: `url_filters` (`rule` ∈ contains/match + `value`), `device` (`device` ∈ Desktop/Mobile), `user_role` (`user_role` = array of role slugs, e.g. `["guest","administrator"]` — get valid slugs from get_custom_event `available_condition_types`). The event keeps a SINGLE condition; adding a second is rejected. Remember to set `conditions_enabled: true` or conditions are ignored. **Two-step write — confirmation FIRST and MANDATORY:** call FIRST without `confirm` to get a `confirmation_required` preview (`pending_changes` shows current→new, `created: true` for a new event). Show it to the user and get explicit go-ahead in a SEPARATE message BEFORE calling again. NEVER call with `confirm: true` in the same turn as the preview, and NEVER self-approve — even for creating a new event. Only after the user replies "yes" do you resend the identical args with `confirm: true`. A call without `confirm: true` never writes. Pass `mcp_note`.';
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
				'event_id'           => array( 'type' => 'integer', 'description' => 'Existing custom event id to UPDATE (from get_custom_events). Omit to CREATE a new event.' ),
				'title'              => array( 'type' => 'string', 'description' => 'Event title (admin-facing name).' ),
				'enabled'            => $bool + array( 'description' => 'Event active (true) or paused (false).' ),
				'conditions_enabled' => $bool + array( 'description' => 'Enable the event\'s display condition.' ),
				'conditions_logic'   => array( 'type' => 'string', 'enum' => array( 'OR', 'AND' ), 'description' => 'How conditions combine: OR / AND (Free keeps a single condition, so this rarely matters).' ),
				'platforms'          => array(
					'type'                 => 'object',
					'description'          => 'Per-platform config patch, keyed by platform slug. Writable: facebook, pinterest, bing, reddit, google_analytics (unified GA4), gtm. See the tool description for each field. Only pass the platforms/fields you want to change; others are preserved. `google_analytics` has no per-event pixel field. Params are flat name→string values.',
					'additionalProperties' => array( 'type' => 'object' ),
				),
				'triggers'           => array(
					'type'        => 'array',
					'description' => 'Point-wise trigger operations (Free keeps ONE trigger per event). Each item: `op` (add/update/remove), `index` (trigger_index from get_custom_event, for update/remove), `type` (for add: page_visit/home_page/scroll_pos/post_type). `rules` for page_visit ({rule,value}) / scroll_pos ({value}); params `delay` (seconds) / `post_type_value`. A page_visit trigger may hold MULTIPLE URL rules (that is still one trigger). A scroll_pos trigger, however, supports only ONE threshold in Free — passing multiple scroll percentages (e.g. 77 and 90) is rejected (multiple scroll thresholds are Pro). The event may have only ONE trigger overall — adding a second is rejected (multiple triggers with AND/OR logic are Pro). To change the trigger use op:update on index 0, or op:remove it before adding a different type.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'op'   => array( 'type' => 'string', 'enum' => array( 'add', 'update', 'remove' ) ),
							'type' => array( 'type' => 'string', 'enum' => array( 'page_visit', 'home_page', 'scroll_pos', 'post_type' ) ),
						),
					),
				),
				'conditions'         => array(
					'type'        => 'array',
					'description' => 'Point-wise condition operations (Free keeps ONE condition). Each item: `op` (add/update/remove), `index`, `type` (url_filters/device/user_role). url_filters: `rule` (contains/match) + `value`; device: `device` (Desktop/Mobile); user_role: `user_role` (array of role slugs). Set `conditions_enabled: true` or the condition is ignored.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'op'   => array( 'type' => 'string', 'enum' => array( 'add', 'update', 'remove' ) ),
							'type' => array( 'type' => 'string', 'enum' => array( 'url_filters', 'device', 'user_role' ) ),
						),
					),
				),
				'confirm'            => array( 'type' => 'boolean', 'description' => 'Two-step write guard. Call FIRST without it to get a `confirmation_required` preview, show it to the user, and WAIT for their explicit approval in a separate message. Only then resend the same args with `confirm: true`. Do NOT set it in the same turn as the preview. Without `confirm: true` nothing is written.' ),
			),
			// `required: ["mcp_note"]` added by AbstractWriteAbility::resolvedInputSchema().
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
				'created'               => array( 'type' => 'boolean' ),
				'event_id'              => array( 'type' => 'integer' ),
				'confirmation_required' => array( 'type' => 'boolean' ),
				'next_step'             => array( 'type' => 'string' ),
				'pending_changes'       => array( 'type' => 'object', 'additionalProperties' => true ),
				'changed'               => array( 'type' => 'object', 'additionalProperties' => true ),
				'notes'                 => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		);
	}

	/**
	 * Validate and write (or preview) a custom event.
	 *
	 * @param mixed $input Validated args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( $input ) {
		if ( !is_array( $input ) ) {
			$input = array();
		}
		unset( $input[ 'mcp_note' ] );
		$confirm = !empty( $input[ 'confirm' ] );
		unset( $input[ 'confirm' ] );

		if ( !class_exists( '\\PixelYourSite\\CustomEventFactory' ) || !class_exists( '\\PixelYourSite\\CustomEvent' ) ) {
			return new \WP_Error( 'pys_mcp_custom_events_unavailable', 'Custom events are not available on this site.', array( 'status' => 503 ) );
		}

		$eventId  = isset( $input[ 'event_id' ] ) ? (int) $input[ 'event_id' ] : 0;
		$isCreate = $eventId <= 0;

		if ( $isCreate ) {
			$base = CustomEventArgsSerializer::toArgs( new \PixelYourSite\CustomEvent() );
		} else {
			if ( 'pys_event' !== get_post_type( $eventId ) ) {
				return new \WP_Error(
					'pys_mcp_custom_event_not_found',
					sprintf( 'No custom event with id %d. Call get_custom_events for valid ids, or omit event_id to create one.', $eventId ),
					array( 'status' => 404 )
				);
			}
			$base = CustomEventArgsSerializer::toArgs( \PixelYourSite\CustomEventFactory::getById( $eventId ) );
		}

		// Refuse to touch an event whose triggers/conditions update() cannot round-trip.
		$guard = self::guardUnwritableParts( $base );
		if ( $guard instanceof \WP_Error ) {
			return $guard;
		}

		$supplied       = array_intersect_key( $input, self::EVENT_FIELDS );
		$platformsInput = ( isset( $input[ 'platforms' ] ) && is_array( $input[ 'platforms' ] ) ) ? $input[ 'platforms' ] : array();
		$triggerOps     = ( isset( $input[ 'triggers' ] ) && is_array( $input[ 'triggers' ] ) ) ? $input[ 'triggers' ] : array();
		$conditionOps   = ( isset( $input[ 'conditions' ] ) && is_array( $input[ 'conditions' ] ) ) ? $input[ 'conditions' ] : array();
		if ( !$isCreate && empty( $supplied ) && empty( $platformsInput ) && empty( $triggerOps ) && empty( $conditionOps ) ) {
			return new \WP_Error(
				'pys_mcp_custom_event_no_args',
				'Nothing to update. Pass at least one event-level field, a `platforms` patch, `triggers` ops, or `conditions` ops.',
				array( 'status' => 400 )
			);
		}

		$merged  = $base;
		$pending = array();
		$notes   = array();

		foreach ( $supplied as $arg => $raw ) {
			list( $key, $type ) = self::EVENT_FIELDS[ $arg ];
			$new = self::coerce( $type, $raw );
			if ( null === $new ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_value', sprintf( 'Invalid value for `%s`.', $arg ), array( 'status' => 400 ) );
			}
			$merged[ $key ]  = $new;
			$pending[ $arg ] = array( 'current' => self::currentFor( $type, $base, $key ), 'new' => $new );
		}

		if ( !empty( $platformsInput ) ) {
			$platformPending = array();
			$err             = self::applyPlatforms( $merged, $platformsInput, $platformPending, $notes );
			if ( $err instanceof \WP_Error ) {
				return $err;
			}
			if ( !empty( $platformPending ) ) {
				$pending[ 'platforms' ] = $platformPending;
			}
		}

		if ( !empty( $triggerOps ) ) {
			$triggerPending = array();
			$err            = self::applyTriggerOps( $merged, $triggerOps, $triggerPending );
			if ( $err instanceof \WP_Error ) {
				return $err;
			}
			if ( !empty( $triggerPending ) ) {
				$pending[ 'triggers' ] = $triggerPending;
			}
		}

		if ( !empty( $conditionOps ) ) {
			$conditionPending = array();
			$err              = self::applyConditionOps( $merged, $conditionOps, $conditionPending );
			if ( $err instanceof \WP_Error ) {
				return $err;
			}
			if ( !empty( $conditionPending ) ) {
				$pending[ 'conditions' ] = $conditionPending;
			}
		}

		$notes = array_merge( $notes, self::pruneStaleParams( $merged, $pending ), self::advisoryNotes( $isCreate, $merged ) );

		// Two-step confirm gate.
		if ( !$confirm ) {
			$preview = array(
				'confirmation_required' => true,
				'created'               => $isCreate,
				'pending_changes'       => (object) $pending,
				'next_step'             => 'NOTHING HAS BEEN SAVED YET. This is a preview only. You MUST now: (1) show the user the `pending_changes` above, (2) STOP and wait for the user to reply with their explicit approval in a SEPARATE message. Do NOT call set_custom_event again in this turn. Only after the user actually replies "yes"/"да" may you resend the identical args with confirm:true. A direct instruction like "enable conditions" is NOT advance approval — you still preview first and wait.',
			);
			if ( !$isCreate ) {
				$preview[ 'event_id' ] = $eventId;
			}
			if ( !empty( $notes ) ) {
				$preview[ 'notes' ] = $notes;
			}

			return $preview;
		}

		// Apply.
		if ( $isCreate ) {
			$event = \PixelYourSite\CustomEventFactory::create( array( 'title' => $merged[ 'title' ] ?? 'Untitled' ) );
			if ( !$event || !is_object( $event ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_create_failed', 'Failed to create the custom event post.', array( 'status' => 500 ) );
			}
			$eventId = (int) $event->getPostId();
		} else {
			$event = \PixelYourSite\CustomEventFactory::getById( $eventId );
		}

		$event->update( $merged );

		if ( function_exists( '\\PixelYourSite\\purgeCache' ) ) {
			\PixelYourSite\purgeCache();
		}

		$result = array( 'saved' => true, 'created' => $isCreate, 'event_id' => $eventId, 'changed' => (object) $pending );
		if ( !empty( $notes ) ) {
			$result[ 'notes' ] = $notes;
		}

		return $result;
	}

	/**
	 * Block the write when the event carries triggers/conditions Free's update()
	 * cannot round-trip (it rebuilds only the 4 writable trigger types and a single
	 * condition, dropping the rest). Protects Pro-created config from silent loss.
	 *
	 * @param array $base Serialized current state.
	 * @return \WP_Error|null
	 */
	private static function guardUnwritableParts( array $base ): ?\WP_Error {
		$badTriggers = array();
		foreach ( ( $base[ 'triggers' ] ?? array() ) as $t ) {
			$type = is_array( $t ) ? (string) ( $t[ 'trigger_type' ] ?? '' ) : '';
			if ( '' !== $type && !in_array( $type, self::WRITABLE_TRIGGER_TYPES, true ) ) {
				$badTriggers[] = $type;
			}
		}
		if ( !empty( $badTriggers ) ) {
			return new \WP_Error(
				'pys_mcp_custom_event_has_pro_trigger',
				sprintf(
					'This event uses trigger type(s) not editable in Free (%s). Saving it via MCP would drop those triggers, so the write is refused. Edit this event in PixelYourSite → Events, or upgrade to PixelYourSite Pro to manage these trigger types. Free MCP can edit events whose triggers are all: %s.',
					implode( ', ', array_values( array_unique( $badTriggers ) ) ),
					implode( ', ', self::WRITABLE_TRIGGER_TYPES )
				),
				array( 'status' => 409 )
			);
		}

		$badConditions = array();
		foreach ( ( $base[ 'conditions' ] ?? array() ) as $c ) {
			$type = is_array( $c ) ? (string) ( $c[ 'condition_type' ] ?? '' ) : '';
			if ( '' !== $type && !CustomEventConditionMap::isWritable( $type ) ) {
				$badConditions[] = $type;
			}
		}
		if ( !empty( $badConditions ) ) {
			return new \WP_Error(
				'pys_mcp_custom_event_has_pro_condition',
				sprintf(
					'This event uses condition type(s) not editable in Free (%s) — they are PixelYourSite Pro. Saving via MCP would drop them, so the write is refused. Edit this event in PixelYourSite → Events, or upgrade to Pro. Free condition types: %s.',
					implode( ', ', array_values( array_unique( $badConditions ) ) ),
					implode( ', ', CustomEventConditionMap::writableTypes() )
				),
				array( 'status' => 409 )
			);
		}

		return null;
	}

	/**
	 * Coerce/validate an incoming event-level value by field type.
	 *
	 * @param string $type Field type.
	 * @param mixed  $raw  Incoming value.
	 * @return mixed|null
	 */
	private static function coerce( string $type, $raw ) {
		switch ( $type ) {
			case 'string':
				return is_string( $raw ) ? sanitize_text_field( $raw ) : null;
			case 'bool':
				if ( is_bool( $raw ) ) {
					return $raw ? 1 : 0;
				}
				if ( is_int( $raw ) ) {
					return 1 === $raw ? 1 : 0;
				}
				if ( is_string( $raw ) ) {
					return ( '1' === $raw || 'true' === strtolower( $raw ) ) ? 1 : 0;
				}

				return null;
			case 'logic':
				return in_array( $raw, array( 'OR', 'AND' ), true ) ? $raw : null;
		}

		return null;
	}

	/**
	 * Current value of an event-level field, normalised for the diff.
	 *
	 * @param string $type Field type.
	 * @param array  $base Base args.
	 * @param string $key  Data key.
	 * @return mixed
	 */
	private static function currentFor( string $type, array $base, string $key ) {
		$cur = $base[ $key ] ?? null;
		if ( 'bool' === $type ) {
			return self::truthy( $cur ) ? 1 : 0;
		}

		return is_scalar( $cur ) ? (string) $cur : '';
	}

	/**
	 * Apply the per-platform patch onto $merged.
	 *
	 * @param array $merged         Merged args (mutated).
	 * @param array $platformsInput Raw `platforms` arg.
	 * @param array $pending        Per-platform pending diffs (mutated).
	 * @param array $notes          Advisory notes (mutated).
	 * @return \WP_Error|null
	 */
	private static function applyPlatforms( array &$merged, array $platformsInput, array &$pending, array &$notes ) {
		foreach ( $platformsInput as $slug => $cfg ) {
			$slug = (string) $slug;
			if ( !CustomEventPlatformMap::has( $slug ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_unknown_platform', sprintf( 'Unknown platform `%s`. Writable: %s.', $slug, implode( ', ', self::WRITABLE_PLATFORMS ) ), array( 'status' => 409 ) );
			}
			if ( !in_array( $slug, self::WRITABLE_PLATFORMS, true ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_platform_deferred', sprintf( 'Platform `%s` is not writable here. Writable: %s.', $slug, implode( ', ', self::WRITABLE_PLATFORMS ) ), array( 'status' => 409 ) );
			}
			if ( !is_array( $cfg ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_platform', sprintf( 'Platform `%s` config must be an object.', $slug ), array( 'status' => 400 ) );
			}

			$def      = CustomEventPlatformMap::get( $slug );
			$isArrPix = CustomEventPlatformMap::pixelIsArray( $slug );
			$extras   = CustomEventPlatformMap::extras( $slug );

			foreach ( $cfg as $field => $val ) {
				if ( !in_array( $field, self::PLATFORM_FIELDS, true ) && !isset( $extras[ $field ] ) ) {
					return new \WP_Error( 'pys_mcp_custom_event_bad_platform_field', sprintf( 'Unknown field `%s` for platform `%s`.', $field, $slug ), array( 'status' => 400 ) );
				}

				// Platform-specific extra fields (GTM dataLayer options).
				if ( isset( $extras[ $field ] ) ) {
					$spec = $extras[ $field ];
					$new  = ( 'bool' === $spec[ 'type' ] ) ? self::coerce( 'bool', $val ) : ( is_string( $val ) ? sanitize_text_field( $val ) : null );
					if ( null === $new ) {
						return self::badField( $slug, $field );
					}
					self::setPlatformField( $merged, $pending, $slug, $field, $spec[ 'key' ], $new );
					if ( 'custom_object_name' === $field && '' !== (string) $new && !array_key_exists( 'use_custom_object_name', $cfg ) && isset( $extras[ 'use_custom_object_name' ] ) ) {
						self::setPlatformField( $merged, $pending, $slug, 'use_custom_object_name', $extras[ 'use_custom_object_name' ][ 'key' ], true );
					}
					continue;
				}

				switch ( $field ) {
					case 'enabled':
						$new = self::coerce( 'bool', $val );
						if ( null === $new ) {
							return self::badField( $slug, $field );
						}
						if ( $new && !CustomEventPlatformMap::isConnectable( $slug ) ) {
							return new \WP_Error(
								'pys_mcp_custom_event_platform_not_connected',
								sprintf( '`%s` cannot be enabled: its addon is inactive or no pixel is configured. Configure it in PixelYourSite first (see get_custom_event `connectable_platforms`).', $slug ),
								array( 'status' => 409 )
							);
						}
						self::setPlatformField( $merged, $pending, $slug, 'enabled', $def[ 'enabled' ], $new );
						break;

					case 'pixel_ids':
						if ( in_array( $slug, self::PIXEL_LOCKED_PLATFORMS, true ) ) {
							return self::pixelLocked( $slug );
						}
						if ( !$isArrPix ) {
							return new \WP_Error( 'pys_mcp_custom_event_pixel_shape', sprintf( '`%s` uses a single `pixel_id` (string), not `pixel_ids`.', $slug ), array( 'status' => 400 ) );
						}
						$err = self::validatePixelList( $slug, $val );
						if ( $err instanceof \WP_Error ) {
							return $err;
						}
						self::setPlatformField( $merged, $pending, $slug, 'pixel_ids', $def[ 'pixel' ], array_values( $val ) );
						break;

					case 'pixel_id':
						if ( in_array( $slug, self::PIXEL_LOCKED_PLATFORMS, true ) ) {
							return self::pixelLocked( $slug );
						}
						if ( $isArrPix ) {
							return new \WP_Error( 'pys_mcp_custom_event_pixel_shape', sprintf( '`%s` uses multiple `pixel_ids` (array), not `pixel_id`.', $slug ), array( 'status' => 400 ) );
						}
						if ( !is_string( $val ) || ( 'all' !== $val && !in_array( $val, CustomEventPlatformMap::validPixelIds( $slug ), true ) ) ) {
							return self::badPixel( $slug, $val );
						}
						self::setPlatformField( $merged, $pending, $slug, 'pixel_id', $def[ 'pixel' ], $val );
						break;

					case 'event_type':
						$valid = CustomEventPlatformMap::validEventTypes( $slug );
						if ( !is_string( $val ) || ( !empty( $valid ) && !in_array( $val, $valid, true ) ) ) {
							return new \WP_Error(
								'pys_mcp_custom_event_bad_event_type',
								sprintf( 'Invalid event_type `%s` for `%s`. Valid: %s.', is_string( $val ) ? $val : gettype( $val ), $slug, implode( ', ', array_slice( $valid, 0, 40 ) ) ),
								array( 'status' => 409 )
							);
						}
						if ( CustomEventPlatformMap::isCustomEventType( $slug, $val ) ) {
							$nameInCfg   = isset( $cfg[ 'custom_event_type' ] ) && '' !== trim( (string) $cfg[ 'custom_event_type' ] );
							$nameInStore = null !== $def[ 'custom_event_type' ] && '' !== trim( (string) ( $merged[ $def[ 'custom_event_type' ] ] ?? '' ) );
							if ( !$nameInCfg && !$nameInStore ) {
								return new \WP_Error( 'pys_mcp_custom_event_needs_custom_name', sprintf( 'event_type `%s` for `%s` is a CUSTOM-name type — also pass `custom_event_type` (the free event name). Ask the user what to name it.', $val, $slug ), array( 'status' => 409 ) );
							}
						}
						self::setPlatformField( $merged, $pending, $slug, 'event_type', $def[ 'event_type' ], $val );
						if ( CustomEventPlatformMap::EVENTS_GA_GROUP === ( $def[ 'events_kind' ] ?? '' ) && !array_key_exists( 'event_type_group', $cfg ) ) {
							$group = CustomEventPlatformMap::eventGroupFor( $slug, $val );
							if ( '' !== $group ) {
								self::setPlatformField( $merged, $pending, $slug, 'event_type_group', $def[ 'event_type' ] . '_group', $group );
							}
						}
						break;

					case 'event_type_group':
						if ( CustomEventPlatformMap::EVENTS_GA_GROUP !== ( $def[ 'events_kind' ] ?? '' ) ) {
							return new \WP_Error( 'pys_mcp_custom_event_no_event_group', sprintf( '`%s` has no event-type groups.', $slug ), array( 'status' => 400 ) );
						}
						$grp = is_string( $val ) ? $val : null;
						if ( null === $grp ) {
							return self::badField( $slug, $field );
						}
						$et     = isset( $cfg[ 'event_type' ] ) && is_string( $cfg[ 'event_type' ] ) ? $cfg[ 'event_type' ] : (string) ( $merged[ $def[ 'event_type' ] ] ?? '' );
						$groups = CustomEventPlatformMap::eventGroupsFor( $slug, $et );
						if ( !in_array( $grp, $groups, true ) ) {
							return new \WP_Error( 'pys_mcp_custom_event_bad_event_group', sprintf( 'Group `%s` does not contain event_type `%s` for `%s`. Valid groups: %s.', $grp, $et, $slug, empty( $groups ) ? '(none)' : implode( ', ', $groups ) ), array( 'status' => 409 ) );
						}
						self::setPlatformField( $merged, $pending, $slug, 'event_type_group', $def[ 'event_type' ] . '_group', $grp );
						break;

					case 'custom_event_type':
						if ( null === $def[ 'custom_event_type' ] ) {
							return new \WP_Error( 'pys_mcp_custom_event_no_custom_type', sprintf( '`%s` has no custom event type.', $slug ), array( 'status' => 400 ) );
						}
						$str = is_string( $val ) ? sanitize_text_field( $val ) : null;
						if ( null === $str ) {
							return self::badField( $slug, $field );
						}
						$key = function_exists( '\\PixelYourSite\\sanitizeKey' )
							? \PixelYourSite\sanitizeKey( $str )
							: preg_replace( '/[^0-9A-Za-z_]/', '', str_replace( ' ', '_', $str ) );
						if ( '' === (string) $key ) {
							return new \WP_Error( 'pys_mcp_custom_event_bad_custom_name', sprintf( 'custom_event_type `%s` has no usable characters — use latin letters / digits / underscore (spaces become `_`); Cyrillic / emoji are not supported. Ask the user for a latin name.', $str ), array( 'status' => 409 ) );
						}
						self::setPlatformField( $merged, $pending, $slug, 'custom_event_type', $def[ 'custom_event_type' ], $key );
						break;

					case 'params_enabled':
						$new = self::coerce( 'bool', $val );
						if ( null === $new ) {
							return self::badField( $slug, $field );
						}
						if ( null === $def[ 'params_enabled' ] ) {
							if ( false === (bool) $new ) {
								return new \WP_Error( 'pys_mcp_custom_event_no_params_toggle', sprintf( '`%s` has no params toggle.', $slug ), array( 'status' => 400 ) );
							}
							break;
						}
						self::setPlatformField( $merged, $pending, $slug, 'params_enabled', $def[ 'params_enabled' ], $new );
						break;

					case 'params':
						$err = self::applyPlatformParams( $merged, $pending, $slug, $def, $cfg, $val );
						if ( $err instanceof \WP_Error ) {
							return $err;
						}
						break;

					case 'custom_params':
						$err = self::applyPlatformCustomParams( $merged, $pending, $slug, $def, $val );
						if ( $err instanceof \WP_Error ) {
							return $err;
						}
						break;

					case 'track_single_woo_data':
					case 'track_cart_woo_data':
						$mapKey = 'track_single_woo_data' === $field ? 'track_single' : 'track_cart';
						if ( null === $def[ $mapKey ] ) {
							$label = 'track_single_woo_data' === $field ? 'Track WooCommerce product data on single product pages' : 'Track WooCommerce cart data when possible';
							return new \WP_Error( 'pys_mcp_custom_event_no_track', sprintf( 'The custom-event switcher "%s" (`%s`) is NOT available for `%s` in Free — it is a locked (Pro) switcher in that platform\'s custom-event block; functional only for `reddit` in Free. NOTE: this is a per-platform CUSTOM-EVENT option, NOT the WooCommerce ViewContent funnel event ("Track product pages" in get_woo_events_config) — do not confuse them.', $label, $field, $slug ), array( 'status' => 409 ) );
						}
						$new = self::coerce( 'bool', $val );
						if ( null === $new ) {
							return self::badField( $slug, $field );
						}
						self::setPlatformField( $merged, $pending, $slug, $field, $def[ $mapKey ], $new );
						break;

					case 'conversion_label':
						if ( null === $def[ 'conversion_label' ] ) {
							return new \WP_Error( 'pys_mcp_custom_event_no_label', sprintf( '`%s` has no conversion label.', $slug ), array( 'status' => 400 ) );
						}
						$str = is_string( $val ) ? sanitize_text_field( $val ) : null;
						if ( null === $str ) {
							return self::badField( $slug, $field );
						}
						self::setPlatformField( $merged, $pending, $slug, 'conversion_label', $def[ 'conversion_label' ], $str );
						break;
				}
			}

			// Warn if params/config was written to a platform that stays disabled.
			if ( !self::truthy( $merged[ $def[ 'enabled' ] ] ?? false ) && isset( $pending[ $slug ] ) ) {
				$notes[] = sprintf( 'Platform `%s` is disabled — the changes are stored but will not fire until you enable it (`platforms.%s.enabled: true`).', $slug, $slug );
			}
		}

		return null;
	}

	/**
	 * Set a platform data key in $merged and record the diff.
	 *
	 * @param array  $merged  Merged args (mutated).
	 * @param array  $pending Per-platform pending (mutated).
	 * @param string $slug    Platform slug.
	 * @param string $field   Public field name.
	 * @param string $dataKey Real data key.
	 * @param mixed  $new     New value.
	 * @return void
	 */
	private static function setPlatformField( array &$merged, array &$pending, string $slug, string $field, string $dataKey, $new ): void {
		$pending[ $slug ][ $field ] = array( 'current' => $merged[ $dataKey ] ?? null, 'new' => $new );
		$merged[ $dataKey ]         = $new;
	}

	/**
	 * Overlay standard `params` (object keyed by name → flat string) onto the
	 * platform's `<slug>_params`. Free stores flat scalar values only.
	 *
	 * @param array  $merged  Merged args (mutated).
	 * @param array  $pending Per-platform pending (mutated).
	 * @param string $slug    Platform slug.
	 * @param array  $def     Platform map row.
	 * @param array  $cfg     The platform's full patch (for a same-call event_type).
	 * @param mixed  $val     The `params` value.
	 * @return \WP_Error|null
	 */
	private static function applyPlatformParams( array &$merged, array &$pending, string $slug, array $def, array $cfg, $val ) {
		if ( null === $def[ 'params' ] ) {
			return new \WP_Error( 'pys_mcp_custom_event_no_params', sprintf( '`%s` has no standard params.', $slug ), array( 'status' => 400 ) );
		}
		if ( !is_array( $val ) || empty( $val ) ) {
			return new \WP_Error( 'pys_mcp_custom_event_bad_params', sprintf( '`%s.params` must be a non-empty object keyed by param name → string value (e.g. {"value":"9.99","currency":"USD"}).', $slug ), array( 'status' => 400 ) );
		}

		$eventType = ( isset( $cfg[ 'event_type' ] ) && is_string( $cfg[ 'event_type' ] ) ) ? $cfg[ 'event_type' ] : (string) ( $merged[ $def[ 'event_type' ] ] ?? '' );
		if ( '' === $eventType ) {
			return new \WP_Error( 'pys_mcp_custom_event_no_event_type', sprintf( 'Set an `event_type` for `%s` before adding params.', $slug ), array( 'status' => 409 ) );
		}

		$allowed = CustomEventPlatformMap::eventParamNames( $slug, $eventType );
		if ( empty( $allowed ) ) {
			$hasCustom = null !== $def[ 'custom_params' ];
			if ( CustomEventPlatformMap::isCustomEventType( $slug, $eventType ) ) {
				$alt = $hasCustom ? 'use `custom_params` instead, or set a standard `event_type` first' : sprintf( '`%s` has no custom params either, so a `%s` event cannot carry params — set a standard `event_type`', $slug, $eventType );
				return new \WP_Error( 'pys_mcp_custom_event_params_custom_type', sprintf( 'The current `%s` event_type (`%s`) takes no standard params — %s.', $slug, $eventType, $alt ), array( 'status' => 409 ) );
			}
			$alt = $hasCustom ? 'use custom_params' : sprintf( '`%s` has no custom params either — choose an event_type that has standard params', $slug );
			return new \WP_Error( 'pys_mcp_custom_event_event_no_params', sprintf( 'Event type `%s` for `%s` has no standard params (%s).', $eventType, $slug, $alt ), array( 'status' => 409 ) );
		}

		$current = ( isset( $merged[ $def[ 'params' ] ] ) && is_array( $merged[ $def[ 'params' ] ] ) ) ? $merged[ $def[ 'params' ] ] : array();
		$applied = array();
		foreach ( $val as $name => $pv ) {
			$name = (string) $name;
			if ( !in_array( $name, $allowed, true ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_param_name', sprintf( 'Param `%s` is not valid for `%s` event_type `%s`. Valid: %s.', $name, $slug, $eventType, implode( ', ', $allowed ) ), array( 'status' => 409 ) );
			}
			if ( null === $pv || '' === $pv ) {
				unset( $current[ $name ] );
				$applied[ $name ] = null;
				continue;
			}
			if ( !is_scalar( $pv ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_param_value', sprintf( 'Param `%s` must be a plain string value (Free stores flat static values; the object/selector shape is Pro). Dynamic-parameter tokens are Pro too — in Free a value is sent verbatim, not substituted.', $name ), array( 'status' => 400 ) );
			}
			$current[ $name ] = sanitize_text_field( (string) $pv );
			$applied[ $name ] = $current[ $name ];
		}

		$merged[ $def[ 'params' ] ]   = $current;
		$pending[ $slug ][ 'params' ] = $applied;
		self::autoEnableParamsFlag( $merged, $pending, $slug, $def );

		return null;
	}

	/**
	 * Replace the platform's `<slug>_custom_params` with a list of {name, value}.
	 *
	 * @param array  $merged  Merged args (mutated).
	 * @param array  $pending Per-platform pending (mutated).
	 * @param string $slug    Platform slug.
	 * @param array  $def     Platform map row.
	 * @param mixed  $val     The `custom_params` value.
	 * @return \WP_Error|null
	 */
	private static function applyPlatformCustomParams( array &$merged, array &$pending, string $slug, array $def, $val ) {
		if ( null === $def[ 'custom_params' ] ) {
			return new \WP_Error( 'pys_mcp_custom_event_no_custom_params', sprintf( '`%s` has no custom params.', $slug ), array( 'status' => 400 ) );
		}
		if ( !is_array( $val ) ) {
			return new \WP_Error( 'pys_mcp_custom_event_bad_custom_params', sprintf( '`%s.custom_params` must be an array of {name, value} objects.', $slug ), array( 'status' => 400 ) );
		}

		$clean = array();
		foreach ( $val as $cp ) {
			if ( !is_array( $cp ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_custom_params', 'Each custom param must be an object with `name` and `value`.', array( 'status' => 400 ) );
			}
			$name  = isset( $cp[ 'name' ] ) ? sanitize_text_field( (string) $cp[ 'name' ] ) : '';
			$value = isset( $cp[ 'value' ] ) ? sanitize_text_field( (string) $cp[ 'value' ] ) : '';
			if ( '' === $name || '' === $value ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_custom_params', 'Each custom param needs a non-empty `name` and `value`.', array( 'status' => 400 ) );
			}
			$clean[] = array( 'name' => $name, 'value' => $value );
		}

		$merged[ $def[ 'custom_params' ] ]   = $clean;
		$pending[ $slug ][ 'custom_params' ] = $clean;
		self::autoEnableParamsFlag( $merged, $pending, $slug, $def );

		return null;
	}

	/**
	 * Turn the platform's params toggle on (if it has one and it is off) when
	 * params / custom_params are written.
	 *
	 * @param array  $merged  Merged args (mutated).
	 * @param array  $pending Per-platform pending (mutated).
	 * @param string $slug    Platform slug.
	 * @param array  $def     Platform map row.
	 * @return void
	 */
	private static function autoEnableParamsFlag( array &$merged, array &$pending, string $slug, array $def ): void {
		$key = $def[ 'params_enabled' ];
		if ( null !== $key && !self::truthy( $merged[ $key ] ?? false ) ) {
			self::setPlatformField( $merged, $pending, $slug, 'params_enabled', $key, true );
		}
	}

	/**
	 * Validate a pixel_ids array (each entry `all` or a configured pixel id).
	 *
	 * @param string $slug Platform slug.
	 * @param mixed  $val  Incoming value.
	 * @return \WP_Error|null
	 */
	private static function validatePixelList( string $slug, $val ) {
		if ( !is_array( $val ) || empty( $val ) ) {
			return new \WP_Error( 'pys_mcp_custom_event_bad_pixels', sprintf( '`%s` pixel_ids must be a non-empty array (e.g. ["all"]).', $slug ), array( 'status' => 400 ) );
		}
		$valid = CustomEventPlatformMap::validPixelIds( $slug );
		foreach ( $val as $pid ) {
			if ( !is_string( $pid ) || ( 'all' !== $pid && !in_array( $pid, $valid, true ) ) ) {
				return self::badPixel( $slug, $pid );
			}
		}

		return null;
	}

	/**
	 * GA per-event pixel is not writable.
	 *
	 * @param string $slug Platform slug.
	 * @return \WP_Error
	 */
	private static function pixelLocked( string $slug ): \WP_Error {
		return new \WP_Error(
			'pys_mcp_custom_event_pixel_locked',
			sprintf( 'Per-event pixel selection is not available in PixelYourSite Free — the event fires to `%s`\'s configured pixel. Choosing specific pixels per event, or firing to multiple pixels, requires SuperPack / Pro. Remove `pixel_ids`/`pixel_id` from the `%s` patch.', $slug, $slug ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Bad-pixel error with the valid list.
	 *
	 * @param string $slug Platform slug.
	 * @param mixed  $pid  Offending value.
	 * @return \WP_Error
	 */
	private static function badPixel( string $slug, $pid ): \WP_Error {
		$valid = CustomEventPlatformMap::validPixelIds( $slug );

		return new \WP_Error(
			'pys_mcp_custom_event_bad_pixel',
			sprintf( 'Pixel `%s` is not configured for `%s`. Use `all` or one of: %s.', is_string( $pid ) ? $pid : gettype( $pid ), $slug, empty( $valid ) ? '(none configured)' : implode( ', ', $valid ) ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Generic invalid-field error.
	 *
	 * @param string $slug  Platform slug.
	 * @param string $field Field name.
	 * @return \WP_Error
	 */
	private static function badField( string $slug, string $field ): \WP_Error {
		return new \WP_Error( 'pys_mcp_custom_event_bad_value', sprintf( 'Invalid value for `%s.%s`.', $slug, $field ), array( 'status' => 400 ) );
	}

	/**
	 * Apply point-wise trigger ops onto $merged['triggers'].
	 *
	 * @param array $merged  Merged args (mutated).
	 * @param array $ops     `triggers` arg.
	 * @param array $pending Per-op pending summary (mutated).
	 * @return \WP_Error|null
	 */
	private static function applyTriggerOps( array &$merged, array $ops, array &$pending ) {
		$list  = ( isset( $merged[ 'triggers' ] ) && is_array( $merged[ 'triggers' ] ) ) ? array_values( $merged[ 'triggers' ] ) : array();
		$count = count( $list );

		$updates = array();
		$removes = array();
		$adds    = array();

		foreach ( $ops as $op ) {
			if ( !is_array( $op ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_trigger_op', 'Each trigger op must be an object.', array( 'status' => 400 ) );
			}
			$opName = isset( $op[ 'op' ] ) ? (string) $op[ 'op' ] : '';
			if ( !in_array( $opName, array( 'add', 'update', 'remove' ), true ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_trigger_op', 'Trigger op `op` must be add / update / remove.', array( 'status' => 400 ) );
			}

			if ( 'add' === $opName ) {
				$type = isset( $op[ 'type' ] ) ? (string) $op[ 'type' ] : '';
				if ( !in_array( $type, self::WRITABLE_TRIGGER_TYPES, true ) ) {
					return self::triggerTypeError( $type );
				}
				$built = self::buildTriggerArgs( $type, $op, null );
				if ( $built instanceof \WP_Error ) {
					return $built;
				}
				$adds[]    = $built;
				$pending[] = array( 'op' => 'add', 'type' => $type );
				continue;
			}

			if ( !isset( $op[ 'index' ] ) || !is_int( $op[ 'index' ] ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_trigger_index', sprintf( 'Trigger op `%s` requires an integer `index`.', $opName ), array( 'status' => 400 ) );
			}
			$idx = $op[ 'index' ];
			if ( $idx < 0 || $idx >= $count ) {
				return new \WP_Error( 'pys_mcp_custom_event_trigger_index', sprintf( 'Trigger index %d out of range (event has %d trigger(s): valid 0..%d).', $idx, $count, max( 0, $count - 1 ) ), array( 'status' => 409 ) );
			}
			$type = isset( $list[ $idx ][ 'trigger_type' ] ) ? (string) $list[ $idx ][ 'trigger_type' ] : '';

			if ( 'remove' === $opName ) {
				$removes[ $idx ] = true;
				$pending[]       = array( 'op' => 'remove', 'index' => $idx, 'type' => $type );
				continue;
			}

			// update (with optional retype).
			$targetType = isset( $op[ 'type' ] ) ? (string) $op[ 'type' ] : $type;
			if ( !in_array( $targetType, self::WRITABLE_TRIGGER_TYPES, true ) ) {
				return self::triggerTypeError( $targetType );
			}
			$pendingRow = array( 'op' => 'update', 'index' => $idx, 'type' => $type );
			if ( $targetType !== $type ) {
				if ( !array_key_exists( 'rules', $op ) && self::sameRuleShape( $type, $targetType ) ) {
					$carried = self::extractRules( $list[ $idx ], $type );
					if ( !empty( $carried ) ) {
						$op[ 'rules' ] = $carried;
					}
				}
				$built = self::buildTriggerArgs( $targetType, $op, null );
				$pendingRow[ 'from_type' ] = $type;
				$pendingRow[ 'type' ]      = $targetType;
			} else {
				$built = self::buildTriggerArgs( $type, $op, $list[ $idx ] );
			}
			if ( $built instanceof \WP_Error ) {
				return $built;
			}
			$updates[ $idx ] = $built;
			$pending[]       = $pendingRow;
		}

		foreach ( $updates as $idx => $d ) {
			$list[ $idx ] = $d;
		}
		foreach ( array_keys( $removes ) as $idx ) {
			unset( $list[ $idx ] );
		}
		foreach ( $adds as $d ) {
			$list[] = $d;
		}
		$list = array_values( $list );

		if ( count( $list ) > 1 ) {
			return new \WP_Error(
				'pys_mcp_custom_event_one_trigger',
				'PixelYourSite Free supports a SINGLE trigger per custom event — multiple triggers (combined with AND/OR trigger logic) require PixelYourSite Pro. Use op:update to change the existing trigger, or op:remove it before adding a different one.',
				array( 'status' => 409 )
			);
		}

		$merged[ 'triggers' ] = $list;

		return null;
	}

	/**
	 * Build/patch one trigger's admin-arg dict.
	 *
	 * @param string     $type     Trigger type.
	 * @param array      $op       User op.
	 * @param array|null $existing Existing dict (update) or null (add).
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function buildTriggerArgs( string $type, array $op, ?array $existing ) {
		if ( isset( $op[ 'params' ] ) && is_array( $op[ 'params' ] ) ) {
			$op = array_merge( $op[ 'params' ], $op );
			unset( $op[ 'params' ] );
		}

		$def = CustomEventTriggerMap::get( $type );
		$out = null !== $existing ? $existing : array();
		$out[ 'trigger_type' ] = $type;
		// Drop any carried-over rule keys from a different type on retype.
		if ( null === $existing ) {
			unset( $out[ 'page_visit_triggers' ], $out[ 'scroll_pos_triggers' ], $out[ 'delay' ], $out[ 'post_type_value' ] );
		}

		if ( array_key_exists( 'rules', $op ) ) {
			$rulesKey = $def[ 'rules_key' ] ?? null;
			if ( empty( $rulesKey ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_trigger_no_rules', sprintf( 'Trigger type `%s` takes no rules.', $type ), array( 'status' => 400 ) );
			}
			$norm = self::normalizeRules( $op[ 'rules' ], $type );
			if ( $norm instanceof \WP_Error ) {
				return $norm;
			}
			$out[ $rulesKey ] = $norm;
		}

		foreach ( self::TRIGGER_OP_PARAMS as $p => $ptype ) {
			if ( !array_key_exists( $p, $op ) ) {
				continue;
			}
			if ( !in_array( $p, (array) ( $def[ 'params' ] ?? array() ), true ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_trigger_bad_param', sprintf( 'Param `%s` is not valid for trigger type `%s`.', $p, $type ), array( 'status' => 400 ) );
			}
			$cv = self::coerceTriggerParam( $ptype, $op[ $p ] );
			if ( null === $cv ) {
				return new \WP_Error( 'pys_mcp_custom_event_trigger_bad_param', sprintf( 'Invalid value for trigger param `%s`.', $p ), array( 'status' => 400 ) );
			}
			$out[ $p ] = $cv;
		}

		// Rule-based types need at least one rule when ADDED.
		if ( null === $existing && in_array( $type, self::RULE_BASED_TYPES, true ) ) {
			$rulesKey = $def[ 'rules_key' ] ?? null;
			if ( null === $rulesKey || empty( $out[ $rulesKey ] ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_trigger_needs_rule', sprintf( 'Trigger type `%s` needs at least one rule to add (pass `rules`).', $type ), array( 'status' => 400 ) );
			}
		}

		if ( 'post_type' === $type && isset( $out[ 'post_type_value' ] ) && '' !== (string) $out[ 'post_type_value' ] ) {
			$validTypes = function_exists( 'get_post_types' ) ? array_map( 'strval', array_keys( get_post_types( array(), 'names' ) ) ) : array();
			if ( !empty( $validTypes ) && !in_array( (string) $out[ 'post_type_value' ], $validTypes, true ) ) {
				return new \WP_Error(
					'pys_mcp_custom_event_bad_post_type',
					sprintf( 'Invalid post_type_value `%s`. Use a registered post-type SLUG (see get_custom_event available_trigger_types post_type `values`, each with value+label). Valid: %s.', (string) $out[ 'post_type_value' ], implode( ', ', $validTypes ) ),
					array( 'status' => 409 )
				);
			}
		}

		if ( 'scroll_pos' === $type ) {
			$scrollKey = $def[ 'rules_key' ] ?? 'scroll_pos_triggers';
			if ( isset( $out[ $scrollKey ] ) && is_array( $out[ $scrollKey ] ) && count( $out[ $scrollKey ] ) > 1 ) {
				return new \WP_Error(
					'pys_mcp_custom_event_one_scroll_threshold',
					'PixelYourSite Free supports a SINGLE scroll threshold per Page Scroll trigger — multiple scroll percentages (e.g. 77% and 90%) require PixelYourSite Pro. Pass exactly one {value}.',
					array( 'status' => 409 )
				);
			}
		}

		return $out;
	}

	/**
	 * Normalise a user `rules` array into the admin {rule,value} shape.
	 *
	 * @param mixed  $rules Incoming rules.
	 * @param string $type  Trigger type.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private static function normalizeRules( $rules, string $type ) {
		if ( !is_array( $rules ) || empty( $rules ) ) {
			return new \WP_Error( 'pys_mcp_custom_event_bad_rules', sprintf( '`rules` for `%s` must be a non-empty array.', $type ), array( 'status' => 400 ) );
		}
		$allowed  = CustomEventTriggerMap::ruleValues( $type );
		$needRule = !empty( $allowed );
		$out      = array();

		foreach ( $rules as $r ) {
			if ( is_string( $r ) || is_int( $r ) ) {
				if ( $needRule ) {
					return new \WP_Error( 'pys_mcp_custom_event_bad_rules', sprintf( '`%s` rule items need `{rule, value}`. Valid rules: %s.', $type, implode( ', ', $allowed ) ), array( 'status' => 400 ) );
				}
				$out[] = array( 'rule' => null, 'value' => sanitize_text_field( (string) $r ) );
				continue;
			}
			if ( !is_array( $r ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_rules', 'Each rule item must be a value or a {rule, value} object.', array( 'status' => 400 ) );
			}

			if ( !$needRule ) {
				if ( !isset( $r[ 'value' ] ) || '' === (string) $r[ 'value' ] ) {
					return new \WP_Error( 'pys_mcp_custom_event_bad_rules', 'Each rule item needs a non-empty `value`.', array( 'status' => 400 ) );
				}
				$out[] = array( 'rule' => null, 'value' => sanitize_text_field( (string) $r[ 'value' ] ) );
				continue;
			}

			$rule = isset( $r[ 'rule' ] ) ? sanitize_text_field( (string) $r[ 'rule' ] ) : '';
			if ( '' === $rule || !in_array( $rule, $allowed, true ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_rule_value', sprintf( 'Invalid/missing rule for `%s`. Valid in Free: %s. (The URL-parameter rules `param_contains`/`param_match` are PixelYourSite Pro.)', $type, implode( ', ', $allowed ) ), array( 'status' => 409 ) );
			}
			if ( 'any' === $rule ) {
				$out[] = array( 'rule' => 'any', 'value' => '' );
				continue;
			}
			if ( !isset( $r[ 'value' ] ) || '' === (string) $r[ 'value' ] ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_rules', 'Each rule item needs a non-empty `value`.', array( 'status' => 400 ) );
			}
			$out[] = array( 'rule' => $rule, 'value' => sanitize_text_field( (string) $r[ 'value' ] ) );
		}

		return $out;
	}

	/**
	 * Whether two trigger types share the same rule shape (so rules carry over).
	 *
	 * @param string $a Type A.
	 * @param string $b Type B.
	 * @return bool
	 */
	private static function sameRuleShape( string $a, string $b ): bool {
		$ra = CustomEventTriggerMap::ruleValues( $a );
		$rb = CustomEventTriggerMap::ruleValues( $b );
		sort( $ra );
		sort( $rb );

		return $ra === $rb;
	}

	/**
	 * Pull the existing rules array out of a built trigger dict.
	 *
	 * @param array  $dict Existing trigger arg dict.
	 * @param string $type Its trigger type.
	 * @return array<int, array>
	 */
	private static function extractRules( array $dict, string $type ): array {
		$def = CustomEventTriggerMap::get( $type );
		$key = $def[ 'rules_key' ] ?? null;
		if ( null === $key || empty( $dict[ $key ] ) || !is_array( $dict[ $key ] ) ) {
			return array();
		}

		return $dict[ $key ];
	}

	/**
	 * Coerce a trigger param value by type.
	 *
	 * @param string $ptype int|string.
	 * @param mixed  $val   Incoming value.
	 * @return mixed|null
	 */
	private static function coerceTriggerParam( string $ptype, $val ) {
		switch ( $ptype ) {
			case 'int':
				return ( is_int( $val ) || ( is_string( $val ) && ctype_digit( $val ) ) ) ? max( 0, (int) $val ) : null;
			case 'string':
				return is_string( $val ) ? sanitize_text_field( $val ) : null;
		}

		return null;
	}

	/**
	 * Non-writable-trigger-type error.
	 *
	 * @param string $type Trigger type.
	 * @return \WP_Error
	 */
	private static function triggerTypeError( string $type ): \WP_Error {
		return new \WP_Error(
			'pys_mcp_custom_event_trigger_not_writable',
			sprintf( 'Trigger type `%s` is not editable in Free. Writable types: %s. Other trigger types (url_click, css_click, add_to_cart, purchase, form triggers, …) are PixelYourSite Pro.', '' === $type ? '(missing)' : $type, implode( ', ', self::WRITABLE_TRIGGER_TYPES ) ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Apply point-wise condition ops onto $merged['conditions']. Free keeps a
	 * SINGLE condition — the result is capped at one.
	 *
	 * @param array $merged  Merged args (mutated).
	 * @param array $ops     `conditions` arg.
	 * @param array $pending Per-op pending (mutated).
	 * @return \WP_Error|null
	 */
	private static function applyConditionOps( array &$merged, array $ops, array &$pending ) {
		$list  = ( isset( $merged[ 'conditions' ] ) && is_array( $merged[ 'conditions' ] ) ) ? array_values( $merged[ 'conditions' ] ) : array();
		$count = count( $list );

		$updates = array();
		$removes = array();
		$adds    = array();

		foreach ( $ops as $op ) {
			if ( !is_array( $op ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_condition_op', 'Each condition op must be an object.', array( 'status' => 400 ) );
			}
			$opName = isset( $op[ 'op' ] ) ? (string) $op[ 'op' ] : '';
			if ( !in_array( $opName, array( 'add', 'update', 'remove' ), true ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_condition_op', 'Condition op `op` must be add / update / remove.', array( 'status' => 400 ) );
			}

			if ( 'add' === $opName ) {
				$type = isset( $op[ 'type' ] ) ? (string) $op[ 'type' ] : '';
				if ( !CustomEventConditionMap::isWritable( $type ) ) {
					$suffix = CustomEventConditionMap::has( $type ) ? ' — it is a PixelYourSite Pro condition (not editable in Free).' : '.';
					return new \WP_Error( 'pys_mcp_custom_event_bad_condition_type', sprintf( 'Condition type `%s` is not writable in Free%s Free types: %s.', $type, $suffix, implode( ', ', CustomEventConditionMap::writableTypes() ) ), array( 'status' => 409 ) );
				}
				$built = self::buildConditionArgs( $type, $op, null );
				if ( $built instanceof \WP_Error ) {
					return $built;
				}
				$adds[]    = $built;
				$pending[] = array( 'op' => 'add', 'type' => $type );
				continue;
			}

			if ( !isset( $op[ 'index' ] ) || !is_int( $op[ 'index' ] ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_condition_index', sprintf( 'Condition op `%s` requires an integer `index`.', $opName ), array( 'status' => 400 ) );
			}
			$idx = $op[ 'index' ];
			if ( $idx < 0 || $idx >= $count ) {
				return new \WP_Error( 'pys_mcp_custom_event_condition_index', sprintf( 'Condition index %d out of range (event has %d condition(s): valid 0..%d).', $idx, $count, max( 0, $count - 1 ) ), array( 'status' => 409 ) );
			}
			$type = isset( $list[ $idx ][ 'condition_type' ] ) ? (string) $list[ $idx ][ 'condition_type' ] : '';

			if ( 'remove' === $opName ) {
				$removes[ $idx ] = true;
				$pending[]       = array( 'op' => 'remove', 'index' => $idx, 'type' => $type );
				continue;
			}

			$targetType = isset( $op[ 'type' ] ) ? (string) $op[ 'type' ] : $type;
			$pendingRow = array( 'op' => 'update', 'index' => $idx, 'type' => $type );
			if ( $targetType !== $type ) {
				if ( !CustomEventConditionMap::isWritable( $targetType ) ) {
					$suffix = CustomEventConditionMap::has( $targetType ) ? ' — it is a PixelYourSite Pro condition (not editable in Free).' : '.';
					return new \WP_Error( 'pys_mcp_custom_event_bad_condition_type', sprintf( 'Condition type `%s` is not writable in Free%s', $targetType, $suffix ), array( 'status' => 409 ) );
				}
				if ( CustomEventConditionMap::SHAPE_RULE_VALUE === CustomEventConditionMap::shape( $type )
				     && CustomEventConditionMap::SHAPE_RULE_VALUE === CustomEventConditionMap::shape( $targetType ) ) {
					$inner = $list[ $idx ][ $type ] ?? array();
					if ( !array_key_exists( 'rule', $op ) && isset( $inner[ 'condition_rule' ] ) ) {
						$op[ 'rule' ] = $inner[ 'condition_rule' ];
					}
					if ( !array_key_exists( 'value', $op ) && isset( $inner[ 'condition_value' ] ) ) {
						$op[ 'value' ] = $inner[ 'condition_value' ];
					}
				}
				$built = self::buildConditionArgs( $targetType, $op, null );
				$pendingRow[ 'from_type' ] = $type;
				$pendingRow[ 'type' ]      = $targetType;
			} else {
				$built = self::buildConditionArgs( $type, $op, $list[ $idx ] );
			}
			if ( $built instanceof \WP_Error ) {
				return $built;
			}
			$updates[ $idx ] = $built;
			$pending[]       = $pendingRow;
		}

		foreach ( $updates as $idx => $d ) {
			$list[ $idx ] = $d;
		}
		foreach ( array_keys( $removes ) as $idx ) {
			unset( $list[ $idx ] );
		}
		foreach ( $adds as $d ) {
			$list[] = $d;
		}
		$list = array_values( $list );

		if ( count( $list ) > 1 ) {
			return new \WP_Error(
				'pys_mcp_custom_event_one_condition',
				'PixelYourSite Free supports a SINGLE display condition per event. Remove the existing condition before adding another (or update the existing one instead of adding).',
				array( 'status' => 409 )
			);
		}

		$merged[ 'conditions' ] = $list;

		return null;
	}

	/**
	 * Build/patch one condition's admin-arg dict.
	 *
	 * @param string     $type     Condition type.
	 * @param array      $op       User op.
	 * @param array|null $existing Existing dict or null.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function buildConditionArgs( string $type, array $op, ?array $existing ) {
		$shape = CustomEventConditionMap::shape( $type );
		$out   = array( 'condition_type' => $type );

		if ( CustomEventConditionMap::SHAPE_DEVICE === $shape ) {
			$device = array_key_exists( 'device', $op ) ? (string) $op[ 'device' ] : ( $existing[ 'device' ] ?? '' );
			if ( !in_array( $device, CustomEventConditionMap::DEVICE_VALUES, true ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_device', sprintf( '`device` must be one of: %s.', implode( ', ', CustomEventConditionMap::DEVICE_VALUES ) ), array( 'status' => 409 ) );
			}
			$out[ 'device' ] = $device;

			return $out;
		}

		if ( CustomEventConditionMap::SHAPE_USER_ROLE === $shape ) {
			$roles = array_key_exists( 'user_role', $op ) ? $op[ 'user_role' ] : ( $existing[ 'user_role' ] ?? array() );
			if ( !is_array( $roles ) || empty( $roles ) ) {
				return new \WP_Error( 'pys_mcp_custom_event_bad_user_role', '`user_role` must be a non-empty array of role slugs (e.g. ["guest","administrator"]).', array( 'status' => 400 ) );
			}
			$valid = self::validRoles();
			$clean = array();
			foreach ( $roles as $role ) {
				$role = sanitize_text_field( (string) $role );
				if ( !in_array( $role, $valid, true ) ) {
					return new \WP_Error( 'pys_mcp_custom_event_bad_user_role', sprintf( 'Unknown role `%s`. Valid: %s.', $role, implode( ', ', $valid ) ), array( 'status' => 409 ) );
				}
				$clean[] = $role;
			}
			$out[ 'user_role' ] = array_values( array_unique( $clean ) );

			return $out;
		}

		// rule_value (url_filters)
		$allowed = CustomEventConditionMap::ruleValues( $type );
		$inner   = ( null !== $existing && isset( $existing[ $type ] ) && is_array( $existing[ $type ] ) ) ? $existing[ $type ] : array();
		$rule    = array_key_exists( 'rule', $op ) ? sanitize_text_field( (string) $op[ 'rule' ] ) : ( $inner[ 'condition_rule' ] ?? '' );
		$value   = array_key_exists( 'value', $op ) ? sanitize_text_field( (string) $op[ 'value' ] ) : ( $inner[ 'condition_value' ] ?? '' );

		if ( '' === $rule || !in_array( $rule, $allowed, true ) ) {
			return new \WP_Error( 'pys_mcp_custom_event_bad_condition_rule', sprintf( 'Invalid/missing `rule` for `%s`. Valid: %s.', $type, implode( ', ', $allowed ) ), array( 'status' => 409 ) );
		}
		if ( '' === $value ) {
			return new \WP_Error( 'pys_mcp_custom_event_condition_no_value', sprintf( '`%s` condition needs a non-empty `value`.', $type ), array( 'status' => 400 ) );
		}
		$out[ $type ] = array( 'condition_rule' => $rule, 'condition_value' => $value );

		return $out;
	}

	/**
	 * Valid user-role slugs (guest + the site's roles).
	 *
	 * @return array<int, string>
	 */
	private static function validRoles(): array {
		$roles = array( 'guest' );
		if ( function_exists( '\\PixelYourSite\\getAvailableUserRoles' ) ) {
			$roles = array_merge( $roles, array_keys( (array) \PixelYourSite\getAvailableUserRoles() ) );
		} elseif ( function_exists( 'wp_roles' ) ) {
			$roles = array_merge( $roles, array_keys( wp_roles()->get_names() ) );
		}

		return array_values( array_unique( $roles ) );
	}

	/**
	 * Prune each ENABLED platform's standard params to only the keys valid for its
	 * current event_type (Free's update() copies back every params key passed, so a
	 * type change would otherwise pile up stale params). Self-healing / idempotent.
	 *
	 * @param array $merged  Merged args (mutated).
	 * @param array $pending Pending-changes accumulator (mutated).
	 * @return array<int, string> Notes for dropped keys.
	 */
	private static function pruneStaleParams( array &$merged, array &$pending ): array {
		$notes = array();
		foreach ( CustomEventPlatformMap::slugs() as $slug ) {
			$def = CustomEventPlatformMap::get( $slug );
			if ( null === $def || null === $def[ 'params' ] ) {
				continue;
			}
			if ( !self::truthy( $merged[ $def[ 'enabled' ] ] ?? false ) ) {
				continue;
			}
			$pkey = $def[ 'params' ];
			if ( !isset( $merged[ $pkey ] ) || !is_array( $merged[ $pkey ] ) || empty( $merged[ $pkey ] ) ) {
				continue;
			}
			$et      = (string) ( $merged[ $def[ 'event_type' ] ] ?? '' );
			$valid   = CustomEventPlatformMap::eventParamNames( $slug, $et );
			$dropped = array();
			foreach ( array_keys( $merged[ $pkey ] ) as $k ) {
				if ( !in_array( (string) $k, $valid, true ) ) {
					unset( $merged[ $pkey ][ $k ] );
					$dropped[] = (string) $k;
				}
			}
			if ( !empty( $dropped ) ) {
				$pending[ $slug ][ 'params_pruned' ] = $dropped;
				$notes[] = sprintf( 'Dropped %s param(s) not used by `%s` event_type `%s`: %s (stale from a previous event type).', $slug, $slug, $et, implode( ', ', $dropped ) );
			}
		}

		return $notes;
	}

	/**
	 * Advisory notes (not requested changes) to relay to the user.
	 *
	 * @param bool  $isCreate Whether creating.
	 * @param array $merged   Merged args after patch.
	 * @return array<int, string>
	 */
	private static function advisoryNotes( bool $isCreate, array $merged ): array {
		$notes = array();

		$pys = function_exists( '\\PixelYourSite\\PYS' ) ? \PixelYourSite\PYS() : null;
		if ( null !== $pys && !self::truthy( $pys->getOption( 'custom_events_enabled' ) ) ) {
			$notes[] = 'The custom-events master feature (`custom_events_enabled`) is OFF — no custom event fires until it is enabled in PixelYourSite → Events.';
		}

		$triggers = ( isset( $merged[ 'triggers' ] ) && is_array( $merged[ 'triggers' ] ) ) ? $merged[ 'triggers' ] : array();
		if ( 0 === count( $triggers ) ) {
			$notes[] = 'This event has no triggers, so it will not fire until at least one trigger is added (pass `triggers` with an `add` op) — even with a platform enabled.';
		}

		$conditions = ( isset( $merged[ 'conditions' ] ) && is_array( $merged[ 'conditions' ] ) ) ? $merged[ 'conditions' ] : array();
		if ( !empty( $conditions ) && !self::truthy( $merged[ 'conditions_enabled' ] ?? false ) ) {
			$notes[] = 'This event has a display condition but `conditions_enabled` is OFF — the condition is ignored until you set `conditions_enabled: true`.';
		}

		$dup = self::ecommerceDuplicationNote( $merged );
		if ( null !== $dup ) {
			$notes[] = $dup;
		}

		if ( self::hasDynamicTokenParam( $merged ) ) {
			$notes[] = 'A param value looks like a PixelYourSite dynamic token (e.g. "[id]" / "[title]"). Dynamic parameters are PixelYourSite Pro — in Free this is stored and sent VERBATIM as the literal text, NOT substituted with the real value at fire time. Use a static value, or tell the user dynamic parameters require Pro.';
		}

		$notes[] = 'After saving, verify the event actually fires with your platform\'s testing tool (Meta Pixel Helper / GA4 DebugView / Google Tag Assistant / Microsoft UET Tag Helper / Reddit Pixel Helper / Pinterest Tag Helper) before relying on it for reporting.';

		return $notes;
	}

	/**
	 * Whether any platform param / custom-param value looks like a PYS dynamic
	 * token (exactly `[something]`). Dynamic parameters are Pro — in Free such a
	 * value is stored/sent verbatim, not substituted, so the preview warns.
	 *
	 * @param array $merged Merged args.
	 * @return bool
	 */
	private static function hasDynamicTokenParam( array $merged ): bool {
		$isToken = static function ( $v ) {
			return is_string( $v ) && (bool) preg_match( '/^\s*\[[^\]\[]+\]\s*$/', $v );
		};
		foreach ( CustomEventPlatformMap::slugs() as $slug ) {
			$def = CustomEventPlatformMap::get( $slug );
			if ( null === $def ) {
				continue;
			}
			if ( !empty( $def[ 'params' ] ) && isset( $merged[ $def[ 'params' ] ] ) && is_array( $merged[ $def[ 'params' ] ] ) ) {
				foreach ( $merged[ $def[ 'params' ] ] as $v ) {
					if ( $isToken( is_array( $v ) ? ( $v[ 'value' ] ?? '' ) : $v ) ) {
						return true;
					}
				}
			}
			if ( !empty( $def[ 'custom_params' ] ) && isset( $merged[ $def[ 'custom_params' ] ] ) && is_array( $merged[ $def[ 'custom_params' ] ] ) ) {
				foreach ( $merged[ $def[ 'custom_params' ] ] as $cp ) {
					if ( is_array( $cp ) && $isToken( $cp[ 'value' ] ?? '' ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Warn when an enabled platform uses a standard ecommerce event name PYS may
	 * already fire automatically (double-fire risk).
	 *
	 * @param array $merged Merged args.
	 * @return string|null
	 */
	private static function ecommerceDuplicationNote( array $merged ): ?string {
		$hits = array();
		foreach ( CustomEventPlatformMap::slugs() as $slug ) {
			$def = CustomEventPlatformMap::get( $slug );
			if ( null === $def || !self::truthy( $merged[ $def[ 'enabled' ] ] ?? false ) ) {
				continue;
			}
			$et = (string) ( $merged[ $def[ 'event_type' ] ] ?? '' );
			if ( '' !== $et && self::isStandardEcommerceEventType( $et ) ) {
				$hits[] = $slug . ':' . $et;
			}
		}
		if ( empty( $hits ) ) {
			return null;
		}

		return '⚠️ DUPLICATION RISK: an enabled platform uses a standard ecommerce event name (' . implode( ', ', $hits ) . '). PixelYourSite very likely ALREADY fires this ecommerce event automatically (WooCommerce / EDD), so this custom event would send it TWICE and inflate conversions. Confirm the event NAME with the user; valid reasons to keep it are a DIFFERENT (non-standard) name or conditions narrowing to a subset of users. Otherwise verify in the platform tester first.';
	}

	/**
	 * Whether an event_type value is a standard ecommerce funnel event.
	 *
	 * @param string $eventType Event type value.
	 * @return bool
	 */
	private static function isStandardEcommerceEventType( string $eventType ): bool {
		$norm = str_replace( array( '_', '-', ' ' ), '', strtolower( $eventType ) );
		$std  = array(
			'viewcontent', 'viewitem', 'viewitemlist', 'viewcategory',
			'addtocart', 'removefromcart', 'addtowishlist', 'viewcart',
			'initiatecheckout', 'begincheckout', 'checkout', 'addpaymentinfo', 'addshippinginfo',
			'purchase', 'completepayment', 'placeanorder',
		);

		return in_array( $norm, $std, true );
	}

	/**
	 * Loose truthiness for stored option values.
	 *
	 * @param mixed $v Value.
	 * @return bool
	 */
	private static function truthy( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_string( $v ) ) {
			return '1' === $v || 'true' === strtolower( $v );
		}

		return is_int( $v ) && 1 === $v;
	}
}
