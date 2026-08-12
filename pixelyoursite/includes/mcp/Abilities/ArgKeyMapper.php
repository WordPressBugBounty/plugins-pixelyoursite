<?php
/**
 * Maps public MCP tool argument names to the real PYS option keys — the
 * single source of truth for the naming drift (`poas_enabled` →
 * `woo_poas_enabled`, `returning_customer` → `woo_ReturningCustomer_enabled`,
 * etc.). Keyed by tool name; per-platform fan-out is the caller's job.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class ArgKeyMapper {

	/**
	 * `mcp_tool_name` => ( `arg_name` => `real_pys_option_key` ).
	 * Args not listed here are treated as unknown and silently dropped.
	 */
	private const MAP = array(

		'set_woo_event_config'         => array(
			// Lifecycle toggles. `new_customer` is the FirstTimeBuyer event —
			// NOT `woo_new_customer_enabled` (the Google new-customer parameter).
			'new_customer'                  => 'woo_FirstTimeBuyer_enabled',
			'returning_customer'            => 'woo_ReturningCustomer_enabled', // CamelCase
			'frequent_shopper'              => 'woo_frequent_shopper_enabled',
			'vip_client'                    => 'woo_vip_client_enabled',
			'big_whale'                     => 'woo_big_whale_enabled',
			// Thresholds.
			'frequent_shopper_transactions' => 'woo_frequent_shopper_transactions',
			'vip_client_transactions'       => 'woo_vip_client_transactions',
			'vip_client_average_value'      => 'woo_vip_client_average_value',
			'big_whale_ltv'                 => 'woo_big_whale_ltv',
			// Subscriptions / POAS.
			'track_subscriptions'           => 'woo_track_subscriptions',
			'poas_enabled'                  => 'woo_poas_enabled',
			'purchase'                      => 'woo_purchase_enabled',
			'view_content'                  => 'woo_view_content_enabled',
			'view_category'                 => 'woo_view_category_enabled',
			'add_to_cart'                   => 'woo_add_to_cart_enabled',
			'initiate_checkout'             => 'woo_initiate_checkout_enabled',
			'view_cart'                     => 'woo_view_cart_enabled',
			'view_item_list'                => 'woo_view_item_list_enabled',
			'remove_from_cart'              => 'woo_remove_from_cart_enabled',
			'checkout_steps'                => 'woo_checkout_steps_enabled',
			'value_mode'                    => 'woo_event_value',
			'tax_option'                    => 'woo_tax_option',
			'shipping_option'               => 'woo_shipping_option',
			'fees_option'                   => 'woo_fees_option',
			'order_id_prefix'               => 'woo_order_id_prefix',
			// Content ID (same key name in each platform module).
			'content_id_source'             => 'woo_content_id',
			'content_id_prefix'             => 'woo_content_id_prefix',
			'content_id_suffix'             => 'woo_content_id_suffix',
			'content_id_custom_field_name'  => 'woo_content_id_custom_field_name',
			'content_id_logic'              => 'woo_content_id_logic',
			'variable_as_simple'            => 'woo_variable_as_simple',
			'wpml_unified_id'               => 'woo_wpml_unified_id',
		),

		'set_edd_event_config'         => array(
			'frequent_shopper'              => 'edd_frequent_shopper_enabled',
			'vip_client'                    => 'edd_vip_client_enabled',
			'big_whale'                     => 'edd_big_whale_enabled',
			'frequent_shopper_transactions' => 'edd_frequent_shopper_transactions',
			'vip_client_transactions'       => 'edd_vip_client_transactions',
			'vip_client_average_value'      => 'edd_vip_client_average_value',
			'big_whale_ltv'                 => 'edd_big_whale_ltv',
			'track_subscriptions'           => 'edd_track_subscriptions',
			'track_licenses'                => 'edd_track_licenses',
			'purchase'                      => 'edd_purchase_enabled',
			'view_content'                  => 'edd_view_content_enabled',
			'view_category'                 => 'edd_view_category_enabled',
			'add_to_cart'                   => 'edd_add_to_cart_enabled',
			'initiate_checkout'             => 'edd_initiate_checkout_enabled',
			'remove_from_cart'              => 'edd_remove_from_cart_enabled',
			'value_mode'                    => 'edd_event_value',
			'tax_option'                    => 'edd_tax_option',
			'order_id_prefix'               => 'edd_order_id_prefix',
			'content_id_source'             => 'edd_content_id',
			'content_id_prefix'             => 'edd_content_id_prefix',
			'content_id_suffix'             => 'edd_content_id_suffix',
		),

		// Phone uses `_tel_` (not `_ph_`); read side uses the same keys.
		'set_advanced_matching_fields' => array(
			'first_name_field_names' => 'advance_matching_fn_names',
			'last_name_field_names'  => 'advance_matching_ln_names',
			'phone_field_names'      => 'advance_matching_tel_names',
			'email_field_names'      => 'advance_matching_em_names',
			'url_first_name_params'  => 'advance_matching_url_fn_names',
			'url_last_name_params'   => 'advance_matching_url_ln_names',
			'url_phone_params'       => 'advance_matching_url_tel_names',
			'url_email_params'       => 'advance_matching_url_em_names',
		),
	);

	/**
	 * Resolve an MCP arg name to the real PYS option key, or null if the
	 * arg is not whitelisted for that tool.
	 *
	 * @param string $mcpToolName Public MCP tool name.
	 * @param string $arg         Public arg name.
	 * @return string|null Real PYS option key, or null if not whitelisted.
	 */
	public static function resolve( string $mcpToolName, string $arg ): ?string {
		return self::MAP[ $mcpToolName ][ $arg ] ?? null;
	}

	/**
	 * Whitelisted args for a tool, in insertion order.
	 *
	 * @param string $mcpToolName Public MCP tool name.
	 * @return array<int, string> Arg names.
	 */
	public static function knownArgs( string $mcpToolName ): array {
		return array_keys( self::MAP[ $mcpToolName ] ?? array() );
	}

	/**
	 * Filter an input array to whitelisted args and map them to real PYS
	 * option keys. Unknown args are dropped. Returns `[ pys_key => value ]`.
	 *
	 * @param string $mcpToolName Public MCP tool name.
	 * @param array  $args        Raw input args.
	 * @return array<string, mixed> Real-key => value map.
	 */
	public static function filterAndMap( string $mcpToolName, array $args ): array {
		$out = array();
		foreach ( $args as $arg => $value ) {
			$key = self::resolve( $mcpToolName, (string) $arg );
			if ( null === $key ) {
				continue;
			}
			$out[ $key ] = $value;
		}

		return $out;
	}
}