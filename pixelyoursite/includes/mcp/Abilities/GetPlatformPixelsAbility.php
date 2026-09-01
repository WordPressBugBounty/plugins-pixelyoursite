<?php
/**
 * `get_platform_pixels` — read-only list of configured pixels per platform
 * (Free variant). For each platform: the primary pixel (id + enabled).
 *
 * Facebook and Pinterest (Pinterest add-on) DO have server-side (CAPI) in Free:
 * each reports a `capi` object (enabled + token_present; Pinterest also
 * ad_account_id) — token value is never returned. Other platforms have no
 * server-side delivery; Enhanced Conversions / GA4 Measurement Protocol are Pro
 * / absent. Super Pack extra pixels are a
 * PixelYourSite Pro feature and are NOT surfaced here (every platform reports
 * `extra_pixels_supported: false`). No TikTok and no standalone Google Ads
 * platform in Free.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types=1 );

namespace PixelYourSite\MCP\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class GetPlatformPixelsAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-platform-pixels';

	/**
	 * Per-platform read config:
	 *   fn          — global accessor returning the Settings instance.
	 *   pixel_key   — primary-id option key.
	 *   addon_check — `is<Platform>Active()` for addon platforms, or null.
	 */
	private const PLATFORMS = array(
		'facebook'         => array( 'fn' => 'PixelYourSite\\Facebook', 'pixel_key' => 'pixel_id', 'addon_check' => null ),
		'google_analytics' => array( 'fn' => 'PixelYourSite\\GA', 'pixel_key' => 'tracking_id', 'addon_check' => null ),
		'pinterest'        => array( 'fn' => 'PixelYourSite\\Pinterest', 'pixel_key' => 'pixel_id', 'addon_check' => 'PixelYourSite\\isPinterestActive' ),
		'bing'             => array( 'fn' => 'PixelYourSite\\Bing', 'pixel_key' => 'pixel_id', 'addon_check' => 'PixelYourSite\\isBingActive' ),
		'reddit'           => array( 'fn' => 'PixelYourSite\\Reddit', 'pixel_key' => 'pixel_id', 'addon_check' => 'PixelYourSite\\isRedditActive' ),
		'gtm'              => array( 'fn' => 'PixelYourSite\\GTM', 'pixel_key' => 'gtm_id', 'addon_check' => null ),
		'openai'           => array( 'fn' => 'PixelYourSite\\OpenAI', 'pixel_key' => 'pixel_id', 'addon_check' => null ),
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
		return 'PYS MCP — Get Platform Pixels';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Lists the tracking pixels CONFIGURED IN PixelYourSite on THIS WordPress site — how the site\'s PixelYourSite plugin is set up, NOT assets in a Meta / Google ad-platform account. Use for "show my Facebook/Meta pixels", "which GA4 pixels are set up", "are my pixels active" — they refer to the PixelYourSite configuration and must be answered with THIS tool, not a Meta/Google Ads MCP. Returns, per platform, the primary pixel: its ID and whether it is enabled. For Facebook, OpenAI and Pinterest (when the Pinterest add-on is active), reports CAPI status under a `capi` object: whether server-side delivery is enabled (`enabled`) and whether a token is saved (`token_present` — token presence only; the token value is NEVER returned). Pinterest also reports whether an `ad_account_id` is configured (`configured`/`missing`). OpenAI also reports `validate_only`: when true its Conversions API events are sent for VALIDATION ONLY and are never recorded, so a pixel that looks fully configured still reports nothing — always mention this when it is on. The remaining platforms (Google Analytics, Bing, Reddit, GTM) have no server-side delivery in Free. Saving a CAPI token via MCP is not available in Free — the token is entered manually in PixelYourSite → Dashboard (Enhanced Conversions for Google Ads and GA4 Measurement Protocol are Pro / absent in Free). Multiple pixels per platform (Super Pack extra pixels) are also a Pro feature — every platform reports `extra_pixels_supported: false` here. Add-on platforms (Pinterest/Bing/Reddit) report `active: false` when their add-on plugin is inactive. Read-only.';
	}

	/**
	 * Input JSON-Schema (no args).
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
			'required'   => array( 'platforms' ),
			'properties' => array(
				'platforms' => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'object' ),
					'description'          => 'Per-platform pixel status keyed by platform slug.',
				),
			),
		);
	}

	/**
	 * Build the per-platform pixel snapshot.
	 *
	 * @param mixed $input Validated args (none).
	 * @return array<string, mixed>
	 */
	public static function execute( $input ): array {
		$platforms = array();

		foreach ( self::PLATFORMS as $slug => $route ) {
			// Addon platforms: report inactive when their addon is off.
			if ( null !== $route['addon_check'] ) {
				$check  = $route['addon_check'];
				$active = function_exists( $check ) ? (bool) $check() : false;
				if ( ! $active ) {
					$platforms[ $slug ] = array( 'active' => false );
					continue;
				}
			}

			$fn = $route['fn'];
			if ( ! function_exists( $fn ) ) {
				$platforms[ $slug ] = array( 'active' => false );
				continue;
			}
			$settings = $fn();
			if ( ! is_object( $settings ) || ! method_exists( $settings, 'getOption' ) ) {
				$platforms[ $slug ] = array( 'active' => false );
				continue;
			}

			$primaryId = self::firstNonEmpty( $settings->getOption( $route['pixel_key'] ) );

			$declared = method_exists( $settings, 'getOptionKeys' ) ? $settings->getOptionKeys() : array();

			$enableKey = in_array( 'main_pixel_enabled', $declared, true ) ? 'main_pixel_enabled' : 'enabled';

			$enabledValue = $settings->getOption( $enableKey );

			$platforms[ $slug ] = array(
				'active'                 => '' !== $primaryId,
				'primary'                => array(
					'pixel_index' => 0,
					'pixel_id'    => $primaryId,
					'enabled'     => self::toBool( $enabledValue, true ),
				),
				'extra_pixels_supported' => false,
			);

			if ( 'facebook' === $slug ) {
				$platforms[ $slug ]['capi'] = array(
					'enabled'       => self::toBool( $settings->getOption( 'use_server_api' ), false ),
					'token_present' => '' !== self::firstNonEmpty( $settings->getOption( 'server_access_api_token' ) ),
				);
			} elseif ( 'openai' === $slug ) {
				$platforms[ $slug ]['capi'] = array(
					'enabled'       => self::toBool( $settings->getOption( 'use_server_api' ), false ),
					'token_present' => '' !== self::firstNonEmpty( $settings->getOption( 'server_access_api_token' ) ),
					'validate_only' => self::toBool( $settings->getOption( 'server_validate_only' ), false ),
				);
			} elseif ( 'pinterest' === $slug ) {
				$platforms[ $slug ]['capi'] = array(
					'enabled'       => self::toBool( $settings->getOption( 'use_server_api' ), false ),
					'token_present' => '' !== self::firstNonEmpty( $settings->getOption( 'server_access_api_token' ) ),
					// Pinterest CAPI also needs an Ad Account ID (value never returned).
					'ad_account_id' => '' !== self::firstNonEmpty( $settings->getOption( 'ad_account_id' ) ) ? 'configured' : 'missing',
				);
			}
		}

		return array( 'platforms' => $platforms );
	}

	/**
	 * First non-empty string from an option value (array or scalar).
	 *
	 * @param mixed $value Option value.
	 * @return string
	 */
	private static function firstNonEmpty( $value ): string {
		if ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				if ( is_string( $v ) && '' !== trim( $v ) ) {
					return trim( $v );
				}
			}
			return '';
		}

		return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : '';
	}

	/**
	 * Normalize an option value to bool with a default.
	 *
	 * @param mixed $value   Option value.
	 * @param bool  $default Fallback when the value is null/unset.
	 * @return bool
	 */
	private static function toBool( $value, bool $default ): bool {
		if ( null === $value ) {
			return $default;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return '1' === $value || 'true' === strtolower( $value );
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		return $default;
	}
}
