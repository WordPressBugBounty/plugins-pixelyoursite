<?php
/**
 * Declarative registry of custom-event CONDITION types (Free variant): label +
 * value shape (rule_value / device / user_role). Mirrors CustomEvent::update()'s
 * conditions parser AND the full admin condition-type dropdown
 * (ConditionalEvent::$conditional_type_array).
 *
 * Free WRITABLE conditions via MCP: url_filters, device, user_role (see
 * WRITABLE_TYPES). url_parameters, landing_page and source are PixelYourSite Pro
 * conditions — kept here (mirroring the full admin dropdown) so the tool NEVER
 * reports a real condition type as "does not exist", an existing condition of
 * that type can still be read/labelled, and available_condition_types shows it
 * as `editable:false` (Pro). `isWritable()` returns false for those three.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class CustomEventConditionMap {

	public const SHAPE_RULE_VALUE = 'rule_value';
	public const SHAPE_DEVICE     = 'device';
	public const SHAPE_USER_ROLE  = 'user_role';

	/** Condition types Free can CREATE/EDIT via MCP. */
	public const WRITABLE_TYPES = array( 'url_filters', 'device', 'user_role' );

	private const MAP = array(
		'url_filters'    => array( 'label' => 'URL filters', 'shape' => self::SHAPE_RULE_VALUE ),
		'device'         => array( 'label' => 'Device', 'shape' => self::SHAPE_DEVICE ),
		'user_role'      => array( 'label' => 'User role', 'shape' => self::SHAPE_USER_ROLE ),
		'url_parameters' => array( 'label' => 'URL parameters', 'shape' => self::SHAPE_RULE_VALUE ),
		'landing_page'   => array( 'label' => 'Landing page', 'shape' => self::SHAPE_RULE_VALUE ),
		'source'         => array( 'label' => 'Source (traffic source / referrer)', 'shape' => self::SHAPE_RULE_VALUE ),
	);

	private const RULE_VALUES = array(
		'url_filters'    => array( 'contains', 'match' ),
		'url_parameters' => array( 'contains', 'match' ),
		'landing_page'   => array( 'contains', 'match' ),
		'source'         => array( 'contains', 'match' ),
	);

	public const DEVICE_VALUES = array( 'Desktop', 'Mobile' );

	/**
	 * Allowed condition_rule values for a rule_value type ([] otherwise).
	 *
	 * @param string $type Condition type slug.
	 * @return array<int, string>
	 */
	public static function ruleValues( string $type ): array {
		return self::RULE_VALUES[ $type ] ?? array();
	}

	/**
	 * All known condition type slugs (readable — Free + Pro, mirrors the admin
	 * dropdown). Use writableTypes() for the Free-editable subset.
	 *
	 * @return array<int, string>
	 */
	public static function types(): array {
		return array_keys( self::MAP );
	}

	/**
	 * Condition types Free can create/edit via MCP.
	 *
	 * @return array<int, string>
	 */
	public static function writableTypes(): array {
		return self::WRITABLE_TYPES;
	}

	/**
	 * Whether a type is known to the map (Free OR Pro).
	 *
	 * @param string $type Condition type slug.
	 * @return bool
	 */
	public static function has( string $type ): bool {
		return isset( self::MAP[ $type ] );
	}

	/**
	 * Whether a condition type is editable via Free MCP (only the 3 Free types).
	 *
	 * @param string $type Condition type slug.
	 * @return bool
	 */
	public static function isWritable( string $type ): bool {
		return in_array( $type, self::WRITABLE_TYPES, true );
	}

	/**
	 * Admin-facing label (falls back to the raw slug).
	 *
	 * @param string $type Condition type slug.
	 * @return string
	 */
	public static function label( string $type ): string {
		return self::MAP[ $type ][ 'label' ] ?? $type;
	}

	/**
	 * Arg shape for a type (rule_value / device / user_role).
	 *
	 * @param string $type Condition type slug.
	 * @return string
	 */
	public static function shape( string $type ): string {
		return self::MAP[ $type ][ 'shape' ] ?? self::SHAPE_RULE_VALUE;
	}
}
