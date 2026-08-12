<?php
/**
 * `get_credential_setup_instructions` — per-platform static read.
 * Returns step-by-step instructions for generating a server-side credential
 * on one platform per call. Static prose keyed on `platform`; no DB work.
 *
 * @package PixelYourSite\MCP\Abilities
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP\Abilities;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class CredentialSetupInstructionsAbility extends AbstractAbility {

	public const ID = 'pixelyoursite/get-credential-setup-instructions';

	/**
	 * Supported platforms — must match the inputSchema enum exactly.
	 * `bing` and `reddit` have a different credential model (pixel-only, no
	 * separate CAPI token in PYS addons) — their `instructions()` content
	 * documents this asymmetry.
	 */
	private const PLATFORMS = array(
		'facebook',
		'pinterest',
		'bing',
		'reddit',
		'gtm',
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
		return 'PYS MCP — Credential Setup Instructions';
	}

	/**
	 * Tool description shown to Claude.
	 *
	 * @return string
	 */
	public static function description(): string {
		return 'Returns step-by-step instructions for generating a server-side credential for one tracking platform. Static content, no DB calls, safe to call before any other tool. The response includes: what the credential is called on that platform, the exact `admin_ui_path` inside the platform\'s admin UI (quote it verbatim to the user — do not paraphrase), what to copy, whether the value can be retrieved later or is shown only once, and the common mistakes that cause silent CAPI failures. Call this when the user wants to set up server-side events / Conversions API for Facebook or Pinterest and get_tracking_audit shows that platform\'s `capi` status as `warning`. Free supports only Facebook and Pinterest CAPI (bing/reddit/gtm are id-only). GA4 Measurement Protocol, Google Ads Enhanced Conversions and TikTok are NOT in Free — do not offer their setup; say plainly they require Pro. Pass exactly one `platform` per call — do not loop across all platforms; ask the user which platform first.';
	}

	/**
	 * Input JSON-Schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'platform' ),
			'additionalProperties' => false,
			'properties'           => array(
				'platform' => array(
					'type'        => 'string',
					'enum'        => self::PLATFORMS,
					'description' => 'Tracking platform. Pick one. `facebook` = Meta Pixel + Conversions API token. `pinterest` = Pinterest Conversions API token + Ad Account ID (needs the Pinterest add-on). GA4 Measurement Protocol, Google Ads Enhanced Conversions and TikTok Events API are NOT available in PixelYourSite Free (Pro, or not a Free platform); do not offer their setup, and if asked say plainly they require Pro. `bing`, `reddit` and `gtm` are id-only in PYS — no separate server-side token (`gtm` is the Google Tag Manager container ID; see response `pitfall` for details).',
				),
			),
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
				'platform',
				'credential_name',
				'admin_ui_path',
				'what_to_copy',
				'retrievable_later',
				'pitfall',
			),
			'properties' => array(
				'platform'          => array( 'type' => 'string' ),
				'credential_name'   => array( 'type' => 'string' ),
				'admin_ui_path'     => array( 'type' => 'string' ),
				'what_to_copy'      => array( 'type' => 'string' ),
				'retrievable_later' => array( 'type' => 'boolean' ),
				'pitfall'           => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Returns setup instructions for the requested platform.
	 *
	 * @param mixed $input Validated args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( $input ) {
		$platform = is_array( $input ) && isset( $input[ 'platform' ] )
		            && is_string(
			            $input[ 'platform' ]
		            ) ? $input[ 'platform' ] : '';

		// Defensive — the adapter validates `enum`, but re-check here.
		if ( !in_array( $platform, self::PLATFORMS, true ) ) {
			return new \WP_Error(
				'pys_mcp_invalid_platform',
				sprintf( 'Unknown platform "%s". Supported: %s.', $platform, implode( ', ', self::PLATFORMS ) ),
				array( 'status' => 400 )
			);
		}

		$instructions = self::instructions( $platform );

		return array_merge( array( 'platform' => $platform ), $instructions );
	}

	/**
	 * Per-platform content. Single source of truth — English only.
	 *
	 * @param string $platform Platform key.
	 * @return array<string, mixed>
	 */
	private static function instructions( string $platform ): array {
		switch ( $platform ) {
			case 'facebook':
				return array(
					'credential_name'   => 'Conversions API access token (System User)',
					'admin_ui_path'     => 'Meta Business Manager → Business Settings → Users → System Users → Generate Token. Assign the token to your Pixel and select the `ads_management` permission.',
					'what_to_copy'      => 'The System User access token shown in the dialog. NOT a personal user token.',
					'retrievable_later' => false,
					'pitfall'           => 'Personal user tokens silently expire and break CAPI without any error. Always issue from a System User in Business Manager — System User tokens are long-lived and survive admin changes.',
				);

			case 'pinterest':
				return array(
					'credential_name'   => 'Conversions API access token + Ad Account ID',
					'admin_ui_path'     => 'Pinterest Business Hub → Conversions → Conversions API → Generate token. Also note the Ad Account ID (top-left of the dashboard, format `549…`).',
					'what_to_copy'      => 'The access token, and the Ad Account ID ONLY IF it is not already configured. Pinterest CAPI delivery needs both, but check current state first (get_tracking_audit / get_platform_pixels) — if ad_account_id is already set, do NOT ask the user for it again; just save the token.',
					'retrievable_later' => false,
					'pitfall'           => 'The Ad Account ID is required for delivery and is the most-missed piece — without it events deliver to the wrong account or fail silently. BUT it is often already configured: do not demand it blindly. `set_platform_credential` does NOT reject a token-only write (it writes whatever fields you pass), so when ad_account_id is already set, saving just the token is correct and sufficient.',
				);

			case 'bing':
				return array(
					'credential_name'   => 'UET Tag ID (no separate server-side token)',
					'admin_ui_path'     => 'Microsoft Advertising → Tools → Tracking → UET tags → [your tag]. Copy the numeric Tag ID. Enhanced Conversions is a separate per-event toggle in PYS → Bing settings, not a credential.',
					'what_to_copy'      => 'The UET Tag ID (numeric). NOT a token — PYS Bing addon does not use a separate CAPI token. If you also want server-side enrichment, enable the `Enhanced Conversions` checkbox in PYS settings (it hashes PII per event; no extra credential needed).',
					'retrievable_later' => true,
					'pitfall'           => 'There is NO CAPI token field for Bing in PYS. Looking for a Meta-CAPI-style token will dead-end. `set_platform_credential` for `bing` accepts only `pixel_id`; a `token` argument is silently ignored.',
				);

			case 'reddit':
				return array(
					'credential_name'   => 'Reddit Pixel ID (client-side only in PYS)',
					'admin_ui_path'     => 'Reddit Ads → Tools → Events Manager → Conversions tracking. Note the Pixel ID assigned to your pixel.',
					'what_to_copy'      => 'The Reddit Pixel ID only. PYS Reddit addon does NOT integrate Reddit Conversions API server-side at this version — events deliver client-side via the pixel.',
					'retrievable_later' => true,
					'pitfall'           => 'Reddit Conversions API (server-side) exists at the platform but PYS does not integrate it. Only client-side pixel events are delivered. If you need server-side, you must configure it outside PYS. `set_platform_credential` for `reddit` accepts only `pixel_id`; a `token` argument is silently ignored.',
				);

			case 'gtm':
				return array(
					'credential_name'   => 'Google Tag Manager container ID (GTM-XXXXXXX)',
					'admin_ui_path'     => 'tagmanager.google.com → select (or create) your Account → create a Web container for this site → the container ID `GTM-XXXXXXX` is shown at the top of the workspace and in Admin → Container Settings.',
					'what_to_copy'      => 'The container ID in the form `GTM-XXXXXXX` (letters/digits after the `GTM-` prefix). NOT a GA4 Measurement ID (`G-…`) and NOT a Google Ads ID (`AW-…`).',
					'retrievable_later' => true,
					'pitfall'           => 'GTM is a tag container, not a pixel or CAPI endpoint — it has no server-side token here. (Server-side GTM uses a separate container URL configured outside this tool.) Using GTM means your tags fire through the container, so avoid ALSO enabling the same platform\'s native pixel in PYS or events may double-fire. `set_platform_credential` for `gtm` accepts only `pixel_id` (the GTM-… container ID); a `token` argument is silently ignored.',
				);
		}

		// Unreachable (execute() guards unknown platforms); empty shape for safety.
		return array(
			'credential_name'   => '',
			'admin_ui_path'     => '',
			'what_to_copy'      => '',
			'retrievable_later' => false,
			'pitfall'           => '',
		);
	}
}
