<?php

namespace PixelYourSite;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Consent {

	private static $_instance;
	private string $consentKey    = "pys_consent";
	private bool   $consentLoaded = false;
	private bool   $consentPlugin = false;

	private array $consentData = array(
		'facebook'   => true,
		'ga'         => true,
		'bing'       => true,
		'pinterest'  => true,
		'gtm'        => true,
		'reddit'     => true,
		'openai'     => true,
	);

	public static function instance(): Consent {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		$this->checkConsentPlugin();
	}

	/**
	 * Consent is resolved on first use, never at construction time.
	 *
	 * @return void
	 */
	private function loadConsent() {

		if ( $this->consentLoaded ) {
			return;
		}

		$has_cookie = false;

		if ( isset( $_COOKIE[ $this->consentKey ] ) && !empty( $_COOKIE[ $this->consentKey ] ) ) {

			$consent = json_decode( base64_decode( sanitize_text_field( $_COOKIE[ $this->consentKey ] ) ), true );

			if ( !empty( $consent ) ) {
				$this->consentData = $consent;
				$has_cookie        = true;
			} else {
				$this->disableAllPixels();
			}

		} else {
			$this->disableAllPixels();
		}

		$this->consentLoaded = (bool) did_action( 'plugins_loaded' );

		if ( apply_filters( 'pys_disable_by_gdpr', false ) ) {
			$this->disableAllPixels();

			return;
		}

		$this->applyGdprFilter( array( 'facebook' ), 'pys_disable_facebook_by_gdpr', $has_cookie );
		$this->applyGdprFilter( array( 'ga', 'gtm' ), 'pys_disable_analytics_by_gdpr', $has_cookie );
		$this->applyGdprFilter( array( 'pinterest' ), 'pys_disable_pinterest_by_gdpr', $has_cookie );
		$this->applyGdprFilter( array( 'bing' ), 'pys_disable_bing_by_gdpr', $has_cookie );
		$this->applyGdprFilter( array( 'reddit' ), 'pys_disable_reddit_by_gdpr', $has_cookie );
		$this->applyGdprFilter( array( 'openai' ), 'pys_disable_openai_by_gdpr', $has_cookie );
	}

	/**
	 * A consent plugin speaks through one of two channels, and only one of them
	 * is present on any given site.
	 *
	 * @param string[] $slugs      Pixels the filter governs.
	 * @param string   $filter     Filter name.
	 * @param bool     $has_cookie Whether the visitor's choice was in the cookie.
	 * @return void
	 */
	private function applyGdprFilter( array $slugs, string $filter, bool $has_cookie ): void {

		if ( has_filter( $filter ) ) {
			$disabled = (bool) apply_filters( $filter, false );

			foreach ( $slugs as $slug ) {
				$this->consentData[ $slug ] = !$disabled;
			}

			return;
		}

		if ( $has_cookie ) {
			return; // the cookie already carries the answer
		}

		foreach ( $slugs as $slug ) {
			$this->consentData[ $slug ] = true;
		}
	}

	private function checkConsentPlugin(): void {
		$this->consentPlugin = isCookiebotPluginActivated() || isCookieNoticePluginActivated() || isRealCookieBannerPluginActivated() || isConsentMagicPluginActivated() || isCookieLawInfoPluginActivated();
	}

	private function disableAllPixels(): void {
		foreach ( $this->consentData as &$pixel ) {
			$pixel = false;
		}

		unset( $pixel );
	}

	/**
	 * A refusal does not always mean silence.
	 *
	 * @param string $pixel Pixel slug.
	 * @return bool
	 */
	public function firesInRestrictedMode( $pixel ): bool {

		switch ( $pixel ) {

			case 'facebook':
				$restricted = (bool) apply_filters( 'pys_meta_ldu_mode', false );
				break;

			case 'reddit':
				$restricted = (bool) apply_filters( 'pys_reddit_ldu_mode', false );
				break;

			case 'ga':
			case 'gtm':
			case 'google_ads':
				$restricted = has_filter( 'cm_google_consent_mode' )
					|| has_filter( 'pys_analytics_storage_mode' )
					|| has_filter( 'pys_ad_storage_mode' )
					|| has_filter( 'pys_ad_user_data_mode' )
					|| has_filter( 'pys_ad_personalization_mode' );
				break;

			case 'bing':
				$restricted = has_filter( 'cm_bing_consent_mode' )
					|| has_filter( 'pys_bing_ad_storage_mode' );
				break;

			default:
				$restricted = false;
		}

		return (bool) apply_filters( 'pys_pixel_restricted_mode', (bool) $restricted, $pixel );
	}

	/**
	 * May this pixel report anything at all right now?
	 *
	 * @param string $pixel Pixel slug.
	 * @return bool
	 */
	public function mayFire( $pixel ): bool {
		return $this->checkConsent( $pixel ) || $this->firesInRestrictedMode( $pixel );
	}

	public function checkConsent( $pixel ): bool {

		if ( $this->consentPlugin ) {
			$this->loadConsent();
		}

		$consented = $this->consentData[ $pixel ] ?? false;

        return (bool) apply_filters( 'pys_check_consent_by_gdpr', $consented, $pixel );
	}
}

/**
 * @return Consent
 */
function Consent(): Consent {
	return Consent::instance();
}

Consent();