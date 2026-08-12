<?php
/**
 * Declarative registry of custom-event TRIGGER types (Free variant). One MAP row
 * per type (label, rules_key, params) mirroring CustomEvent::update()'s parser.
 *
 * Free WRITABLE triggers via MCP: page_visit, home_page, scroll_pos, post_type
 * (see WRITABLE_TYPES). Every OTHER type in the admin trigger-type dropdown
 * (add_to_cart, purchase, number_page_visit, url_click, css_click, css_mouseover,
 * video_view, email_link, copy_element, video_speed, form_field) + form-plugin
 * triggers is PixelYourSite Pro for editing — kept here (mirrors the full admin
 * `renderTriggerTypeInput` list) so the tool NEVER reports a real trigger type as
 * "does not exist", an existing trigger of that type can still be read/labelled,
 * and available_trigger_types shows it as `editable:false` (Pro). `isEditable()`
 * returns false for all of them.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class CustomEventTriggerMap {

	/** Trigger types Free can CREATE/EDIT via MCP. */
	public const WRITABLE_TYPES = array( 'page_visit', 'home_page', 'scroll_pos', 'post_type' );

	private const MAP = array(
		'page_visit'        => array( 'label' => 'Page visit', 'rules_key' => 'page_visit_triggers', 'params' => array( 'delay' ) ),
		'home_page'         => array( 'label' => 'Home page', 'rules_key' => null, 'params' => array( 'delay' ) ),
		'scroll_pos'        => array( 'label' => 'Page Scroll', 'rules_key' => 'scroll_pos_triggers', 'params' => array() ),
		'post_type'         => array( 'label' => 'Post type', 'rules_key' => null, 'params' => array( 'delay', 'post_type_value' ) ),
		// Read-only in Free (Pro to edit) — kept for labelling existing triggers.
		'add_to_cart'       => array( 'label' => 'WooCommerce add to cart', 'rules_key' => null, 'params' => array( 'track_value_and_currency' ) ),
		'purchase'          => array( 'label' => 'WooCommerce purchase', 'rules_key' => null, 'params' => array( 'purchase_transaction_only', 'track_transaction_ID', 'track_value_and_currency' ) ),
		'number_page_visit' => array( 'label' => 'Number of Page Visits', 'rules_key' => 'number_page_visit_triggers', 'params' => array( 'number_visit', 'conditional_number_visit' ) ),
		'url_click'         => array( 'label' => 'Click on HTML link', 'rules_key' => 'url_click_triggers', 'params' => array() ),
		'css_click'         => array( 'label' => 'Click on CSS selector', 'rules_key' => 'css_click_triggers', 'params' => array( 'click_count', 'click_time_limit' ) ),
		'css_mouseover'     => array( 'label' => 'Mouse over CSS selector', 'rules_key' => 'css_mouseover_triggers', 'params' => array() ),
		'video_view'        => array( 'label' => 'Embedded Video View', 'rules_key' => null, 'params' => array( 'video_view_urls', 'video_view_play_trigger', 'video_view_disable_watch_video' ) ),
		'email_link'        => array( 'label' => 'Email Link', 'rules_key' => 'email_link_triggers', 'params' => array( 'email_link_disable_email_event' ) ),
		'copy_element'      => array( 'label' => 'Copy element (text copy)', 'rules_key' => 'copy_element_triggers', 'params' => array() ),
		'video_speed'       => array( 'label' => 'Video speed increase', 'rules_key' => null, 'params' => array( 'video_speed_urls', 'video_speed_rate', 'video_speed_triggers' ) ),
		'form_field'        => array( 'label' => 'Filling out a form field', 'rules_key' => null, 'params' => array() ),
	);

	private const RULE_VALUES = array(
		'page_visit'        => array( 'contains', 'match' ),
		'number_page_visit' => array( 'any', 'contains', 'match', 'param_contains', 'param_match' ),
		'url_click'         => array( 'contains', 'match' ),
		'email_link'        => array( 'any', 'match', 'contains' ),
	);

	private const PARAM_SPECS = array(
		'delay'                          => array( 'label' => 'Delay before firing', 'unit' => 'seconds' ),
		'post_type_value'                => array( 'label' => 'Post type' ),
		'click_count'                    => array( 'label' => 'Number of clicks required', 'unit' => 'clicks' ),
		'click_time_limit'               => array( 'label' => 'Time limit between clicks', 'unit' => 'milliseconds', 'note' => '0 = no limit' ),
		'number_visit'                   => array( 'label' => 'Number of page visits', 'unit' => 'visits' ),
		'conditional_number_visit'       => array( 'label' => 'Visit-count comparison', 'enum' => array( 'equal', 'equal_or_larger', 'equal_or_less', 'larger', 'less' ) ),
		'track_value_and_currency'       => array( 'label' => 'Track value & currency' ),
		'purchase_transaction_only'      => array( 'label' => 'Purchase transaction only' ),
		'track_transaction_ID'           => array( 'label' => 'Track transaction ID' ),
		'email_link_disable_email_event' => array( 'label' => 'Disable the email_link event' ),
	);

	private const URL_WILDCARD_TYPES = array( 'page_visit', 'number_page_visit' );

	/**
	 * Allowed `rule` values for a trigger type ([] = value-only / no rule).
	 *
	 * @param string $type Trigger type slug.
	 * @return array<int, string>
	 */
	public static function ruleValues( string $type ): array {
		return self::RULE_VALUES[ $type ] ?? array();
	}

	/**
	 * Metadata for a trigger param, or [] if none.
	 *
	 * @param string $name Param name.
	 * @return array<string, mixed>
	 */
	public static function paramSpec( string $name ): array {
		return self::PARAM_SPECS[ $name ] ?? array();
	}

	/**
	 * All known trigger type slugs (readable).
	 *
	 * @return array<int, string>
	 */
	public static function types(): array {
		return array_keys( self::MAP );
	}

	/**
	 * Whether a type is known to the map.
	 *
	 * @param string $type Trigger type slug.
	 * @return bool
	 */
	public static function has( string $type ): bool {
		return isset( self::MAP[ $type ] );
	}

	/**
	 * Full definition for a type, or null.
	 *
	 * @param string $type Trigger type slug.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $type ): ?array {
		return self::MAP[ $type ] ?? null;
	}

	/**
	 * Admin-facing label (falls back to the slug / form-plugin name).
	 *
	 * @param string $type Trigger type slug.
	 * @return string
	 */
	public static function label( string $type ): string {
		if ( isset( self::MAP[ $type ][ 'label' ] ) ) {
			return self::MAP[ $type ][ 'label' ];
		}

		return self::isFormPlugin( $type ) ? self::formPluginName( $type ) : $type;
	}

	/**
	 * Whether a trigger type is editable via Free MCP (only the 4 Free triggers).
	 *
	 * @param string $type Trigger type slug.
	 * @return bool
	 */
	public static function isEditable( string $type ): bool {
		return in_array( $type, self::WRITABLE_TYPES, true );
	}

	/**
	 * Whether `*` as a rule value matches all pages for this trigger type.
	 *
	 * @param string $type Trigger type slug.
	 * @return bool
	 */
	public static function supportsUrlWildcard( string $type ): bool {
		return in_array( $type, self::URL_WILDCARD_TYPES, true );
	}

	/**
	 * Param names relevant to a type — read abilities surface only these.
	 *
	 * @param string $type Trigger type slug.
	 * @return array<int, string>
	 */
	public static function relevantParams( string $type ): array {
		return self::MAP[ $type ][ 'params' ] ?? array();
	}

	/**
	 * Active form-plugin trigger providers, keyed by slug (for labelling existing
	 * form triggers; editing them is Pro).
	 *
	 * @return array<string, object>
	 */
	public static function formPlugins(): array {
		$out = array();
		if ( !function_exists( 'apply_filters' ) ) {
			return $out;
		}
		foreach ( (array) apply_filters( 'pys_form_event_factory', array() ) as $plugin ) {
			if ( is_object( $plugin ) && method_exists( $plugin, 'getSlug' ) ) {
				$out[ (string) $plugin->getSlug() ] = $plugin;
			}
		}

		return $out;
	}

	/**
	 * Whether a type slug is an active form-plugin trigger.
	 *
	 * @param string $type Trigger type slug.
	 * @return bool
	 */
	public static function isFormPlugin( string $type ): bool {
		return array_key_exists( $type, self::formPlugins() );
	}

	/**
	 * Display name for a form-plugin trigger.
	 *
	 * @param string $type Trigger type slug.
	 * @return string
	 */
	public static function formPluginName( string $type ): string {
		$plugin = self::formPlugins()[ $type ] ?? null;

		return ( $plugin && method_exists( $plugin, 'getName' ) ) ? (string) $plugin->getName() : $type;
	}
}
