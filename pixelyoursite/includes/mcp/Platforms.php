<?php
/**
 * Resolves which PYS Settings modules are currently live, so the event-config
 * writers can fan a toggle / content-id change out to every active module. The
 * writer then filters each key per-module via `getOption()`, so we return all
 * live modules rather than hardcoding per-key lists.
 *
 * Free variant: no TikTok and no standalone Google Ads module (Free has neither
 * a `TikTok()` nor an `Ads()` accessor). Two kinds of module:
 *  - **Pixel platforms** (facebook, google_analytics, pinterest, bing, reddit,
 *    openai)
 *    — active = a non-empty main pixel / ID. Bing/Reddit/Pinterest are add-on
 *    plugins, so we gate on `function_exists()` for their accessor.
 *  - **Google tag modules** (gatags, gtm) — no own pixel ID; they hold the
 *    unified Google content-id config. gatags active = GA configured;
 *    gtm active = `GTM()->enabled()`.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Platforms {

	/**
	 * Pixel-platform slug => [ accessor function, main-id option key ].
	 * "Active" when the main-id option holds a non-empty value.
	 */
	private const PIXEL_MAP = array(
		'facebook'         => array( 'PixelYourSite\\Facebook', 'pixel_id' ),
		'google_analytics' => array( 'PixelYourSite\\GA', 'tracking_id' ),
		'pinterest'        => array( 'PixelYourSite\\Pinterest', 'pixel_id' ),
		'bing'             => array( 'PixelYourSite\\Bing', 'pixel_id' ),
		'reddit'           => array( 'PixelYourSite\\Reddit', 'pixel_id' ),
		'openai'           => array( 'PixelYourSite\\OpenAI', 'pixel_id' ),
	);

	/**
	 * Slugs of every active module — pixel platforms with a main id, plus the
	 * Google tag modules (gatags / gtm) when they are enabled.
	 *
	 * @return array<int, string>
	 */
	public static function active(): array {
		return array_keys( self::activeSettings() );
	}

	/**
	 * Settings instances for every active module, keyed by slug. Only modules
	 * whose accessor exists AND expose getOption / updateOptions are included.
	 *
	 * @return array<string, object>
	 */
	public static function activeSettings(): array {
		$out = array();

		// Pixel platforms — active when main id is non-empty.
		foreach ( self::PIXEL_MAP as $slug => $spec ) {
			$inst = self::usableInstance( $spec[ 0 ] );
			if ( null !== $inst && self::hasMainId( $inst, $spec[ 1 ] ) ) {
				$out[ $slug ] = $inst;
			}
		}

		// gatags — unified Google tag. Active when GA has a main id. Holds the
		// Google-side content_id config.
		$gatags = self::usableInstance( 'PixelYourSite\\GATags' );
		if ( null !== $gatags && isset( $out[ 'google_analytics' ] ) ) {
			$out[ 'gatags' ] = $gatags;
		}

		// gtm — Google Tag Manager. Active per its own enabled() check.
		$gtm = self::usableInstance( 'PixelYourSite\\GTM' );
		if ( null !== $gtm && method_exists( $gtm, 'enabled' ) && $gtm->enabled() ) {
			$out[ 'gtm' ] = $gtm;
		}

		return $out;
	}

	/**
	 * Return the module Settings instance if its accessor exists and the object
	 * is usable (getOption / updateOptions), else null.
	 *
	 * @param string $fn Global accessor function name.
	 * @return object|null
	 */
	private static function usableInstance( string $fn ) {
		if ( !function_exists( $fn ) ) {
			return null;
		}
		$inst = $fn();
		if ( is_object( $inst )
		     && method_exists( $inst, 'getOption' )
		     && method_exists( $inst, 'updateOptions' ) ) {
			return $inst;
		}

		return null;
	}

	/**
	 * Does the module's main-id option hold at least one non-empty value?
	 *
	 * @param object $inst Module Settings instance.
	 * @param string $key  Main-id option key.
	 * @return bool
	 */
	private static function hasMainId( $inst, string $key ): bool {
		$value = $inst->getOption( $key );
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
}