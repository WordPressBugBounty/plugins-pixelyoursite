<?php

namespace PixelYourSite;

use URL;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * True when the visitor's request reached the site over HTTPS.
 *
 * $_SERVER['HTTPS'] alone is unreliable behind an SSL-terminating proxy
 * (Cloudflare, load balancer, reverse proxy): PHP sees plain HTTP while the
 * browser is on HTTPS, so every URL we build comes out as http:// and the
 * browser blocks it as mixed content. Also check the forwarded-proto header
 * and is_ssl(), which covers FORCE_SSL setups and port 443.
 */
function pys_is_request_secure() {
    if ( isset( $_SERVER['HTTPS'] ) && strtolower( $_SERVER['HTTPS'] ) === 'on' ) {
        return true;
    }

    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
        // Proxy chains send a comma-separated list, e.g. "https, http".
        $forwarded = explode( ',', $_SERVER['HTTP_X_FORWARDED_PROTO'] );
        if ( strtolower( trim( $forwarded[0] ) ) === 'https' ) {
            return true;
        }
    }

    return is_ssl();
}

/**
 * Request scheme with "://" appended, ready to prepend to host + REQUEST_URI.
 */
function pys_get_request_protocol() {
    return pys_is_request_secure() ? 'https://' : 'http://';
}

/**
 * Strip the query string and fragment from a URL.
 * Used to keep referrer_url free of tracking/PII params before
 * storage or sending to Meta CAPI.
 */
function pys_strip_url_query_and_fragment( $url ) {
    if ( empty( $url ) ) {
        return '';
    }
    $clean = strtok( (string) $url, '?#' );
    return is_string( $clean ) ? $clean : '';
}

/**
 * Absolute URL of the page currently being requested.
 *
 * @param bool $with_query Keep the query string. Pass false for the
 *                         origin+path form the frontend JS writes into the
 *                         select_prod_list cookie (url.origin + url.pathname),
 *                         so PHP-side comparisons can match it.
 */
function pys_get_current_page_url( $with_query = true ) {
    $host = ! empty( $_SERVER['HTTP_HOST'] )
        ? $_SERVER['HTTP_HOST']
        : parse_url( get_site_url(), PHP_URL_HOST );
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $url = pys_get_request_protocol() . $host . $uri;

    return $with_query ? $url : pys_strip_url_query_and_fragment( $url );
}

/**
 * Read the session-entry referrer URL captured by the frontend JS.
 *
 * The frontend stores the visitor's first external referrer of the session
 * in the `pys_session_entry_referrer` session cookie. This helper is the
 * LAST-resort fallback for orphan server-only events that arrive without
 * a per-page payload (e.g. async loopback without snapshot, cron jobs).
 *
 * @return string Validated absolute URL, or '' when missing/invalid.
 */
/**
 * Is the current request one where nobody is waiting for a response?
 *
 * Queue processing runs on WP-Cron, so there we can afford to wait a long time
 * for a platform to answer. In a normal page or AJAX request a visitor is on the
 * other end and the timeout has to be a limit, not a hope.
 *
 * @return bool
 */
function pys_is_background_request() {
    if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
        return true;
    }
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
        return true;
    }
    // Set once a response has been handed to the client via
    // fastcgi_finish_request(): from that point the request is background work.
    if ( defined( 'PYS_RESPONSE_DETACHED' ) && PYS_RESPONSE_DETACHED ) {
        return true;
    }

    return PHP_SAPI === 'cli';
}

/**
 * Hand the response to the client and keep this PHP worker running.
 *
 * Call it once the body is fully written (e.g. on late `shutdown`). Without
 * fastcgi_finish_request()/litespeed_finish_request() nothing can be detached,
 * so only ignore_user_abort() is set and the visitor keeps waiting.
 *
 * @return bool True when the connection was released.
 */
function pys_detach_response() {
    ignore_user_abort( true );

    if ( function_exists( 'fastcgi_finish_request' ) ) {
        $finish = 'fastcgi_finish_request';
    } elseif ( function_exists( 'litespeed_finish_request' ) ) {
        $finish = 'litespeed_finish_request';
    } else {
        return false;
    }

    // Fixed number of levels, like wp_ob_end_flush_all(): a buffer callback
    // that re-opens a buffer must not trap us in an endless loop.
    $levels = ob_get_level();
    for ( $i = 0; $i < $levels; $i++ ) {
        @ob_end_flush();
    }
    flush();

    $finish();

    // Nobody waits any more, so outgoing requests may use the background
    // timeouts (see pys_is_background_request()).
    if ( ! defined( 'PYS_RESPONSE_DETACHED' ) ) {
        define( 'PYS_RESPONSE_DETACHED', true );
    }

    return true;
}

/**
 * Run $task after the REST response has reached the visitor.
 *
 * WP REST echoes the JSON in serve_request() and then die()s, so by `shutdown`
 * the body is already written; wp_ob_end_flush_all() runs at priority 1.
 *
 * This only improves the VISITOR's latency. The FPM worker stays busy for the
 * whole API call, so it does not reduce server load; the bot gate does that.
 *
 * Outside REST (or with the filter off) the task runs immediately.
 *
 * @param callable $task
 */
function pys_run_after_rest_response( $task ) {
    if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST
        || ! apply_filters( 'pys_rest_send_after_response', true ) ) {
        call_user_func( $task );
        return;
    }

    // The client may hang up once it has its answer; the task must survive it.
    ignore_user_abort( true );

    add_action( 'shutdown', function () use ( $task ) {
        pys_detach_response();
        // A slow platform API must not be cut off by max_execution_time.
        @set_time_limit( 60 );
        try {
            call_user_func( $task );
        } catch ( \Throwable $e ) {
            // The task reports its own failures; this only keeps shutdown quiet.
        }
    }, 1000 );
}

/**
 * Total transfer timeout, in seconds, for an outgoing server-side event request.
 *
 * Deliberately generous: these calls carry conversion data we do not want to
 * lose, and platform APIs are occasionally slow without being broken. The point
 * is to have an upper bound at all — several of these requests previously ran
 * with no limit whatsoever, so a hung connection could hold a checkout request
 * (or the whole queue batch) for as long as PHP allowed.
 *
 * Filter with pys_server_request_timeout to raise or lower it; the second
 * argument tells you whether this is a background (cron/CLI) request.
 *
 * @return int
 */
function pys_server_request_timeout() {
    $background = pys_is_background_request();
    $timeout    = $background ? 20 : 10;

    $timeout = (int) apply_filters( 'pys_server_request_timeout', $timeout, $background );

    return max( 1, $timeout );
}

/**
 * Connection-establishment timeout, in seconds, for the same requests.
 *
 * Kept well below the total timeout: failing to open a socket at all is a
 * different kind of problem from a slow response, and it is the case that hurts
 * most when DNS or routing is broken.
 *
 * @return int
 */
function pys_server_connect_timeout() {
    $background = pys_is_background_request();
    $timeout    = $background ? 10 : 5;

    $timeout = (int) apply_filters( 'pys_server_connect_timeout', $timeout, $background );

    return max( 1, $timeout );
}

/**
 * Validate a URL that we only ever pass along to an ads platform as a string.
 *
 * wp_http_validate_url() must NOT be used for this. It is meant for vetting the
 * target of an outgoing request, so whenever the host differs from home_url() it
 * calls gethostbyname() to check the address is not private. Our referrer URLs
 * are external by construction — the frontend only stores a session-entry
 * referrer when its host differs from the site's — so every validation turned
 * into a blocking DNS lookup on the critical path of building a server event.
 * gethostbyname() has no PHP-level timeout: with a slow or unreachable resolver
 * it blocks for whole seconds (5s per attempt by default), and hosts without a
 * local caching resolver pay it on every single call.
 *
 * We never fetch these URLs, so a syntax check is all that is required.
 *
 * @param string $url
 * @return bool
 */
function pys_is_valid_public_url( $url ) {
    if ( ! is_string( $url ) || $url === '' ) {
        return false;
    }

    $parts = wp_parse_url( $url );
    if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
        return false;
    }
    if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
        return false;
    }
    if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
        return false;
    }
    if ( strpbrk( $parts['host'], ':#?[]' ) !== false ) {
        return false;
    }

    return (bool) filter_var( $url, FILTER_VALIDATE_URL );
}

function pys_get_session_entry_referrer_url() {
    // Memoized: $_COOKIE cannot change mid-request, and this is called once per
    // server event built.
    static $cached = null;
    if ( $cached !== null ) {
        return $cached;
    }
    $cached = '';

    if ( ! empty( $_COOKIE['pys_session_entry_referrer'] ) ) {
        $url = esc_url_raw( wp_unslash( $_COOKIE['pys_session_entry_referrer'] ) );
        $url = pys_strip_url_query_and_fragment( $url );
        if ( $url && pys_is_valid_public_url( $url ) ) {
            $cached = $url;
        }
    }
    return $cached;
}

/**
 * Snapshot the per-page referrer for an event being created server-side.
 *
 * Reads `$_SERVER['HTTP_REFERER']` of the CURRENT synchronous request — this
 * is the URL of the page where the event actually happened, which is what
 * Meta's `referrer_url` expects. MUST be called synchronously, at the moment
 * the SingleEvent is created (Woo/EDD hooks, form handlers, template-driven
 * events). Inside an async loopback handler this value belongs to the
 * loopback request itself and is meaningless — never call from there.
 *
 * @return string Validated absolute URL, or '' when missing/invalid.
 */
function pys_snapshot_event_referrer_url() {
    // Memoized: $_SERVER['HTTP_REFERER'] is fixed for the request, and one
    // add-to-cart builds an event per pixel.
    static $cached = null;
    if ( $cached !== null ) {
        return $cached;
    }
    $cached = '';

    if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
        $url = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
        $url = pys_strip_url_query_and_fragment( $url );
        if ( $url && pys_is_valid_public_url( $url ) ) {
            $cached = $url;
        }
    }
    return $cached;
}

/**
 * Resolve the final, clean, validated referrer_url for a server event.
 *
 * Sources, in order:
 *   1. SingleEvent payload — per-page value snapshotted either from
 *      the browser REST/AJAX payload or from
 *      pys_snapshot_event_referrer_url() in a synchronous
 *      Woo/EDD hook at event creation time.
 *   2. pys_get_session_entry_referrer_url() — last-resort fallback
 *      for orphan events (async loopback without snapshot, cron).
 *
 * Returns '' when no source yields a valid URL or consent is missing.
 * $_SERVER['HTTP_REFERER'] is never consulted here.
 *
 * @param \PixelYourSite\SingleEvent $event
 * @param string                     $platform Pixel slug whose consent applies.
 * @return string
 */
/**
 * May a browser-originated event go to the platform's server API? The REST
 * and AJAX endpoints are public, so the visitor's consent is checked here as
 * well, not only in the browser. In restricted mode (Meta LDU, OpenAI opt-out)
 * the event goes with that mode.
 *
 * @param string $platform   Pixel slug.
 * @param string $event_name For the log.
 * @return bool
 */
function pys_server_event_has_consent( $platform, $event_name = '' ) {
    if ( Consent()->mayFire( $platform ) ) {
        return true;
    }

    PYS()->getLog()->debug( "Server event {$event_name} to {$platform} not sent: no consent" );

    return false;
}

function pys_resolve_event_referrer_url( $event, $platform = 'facebook' ) {
    if ( ! Consent()->checkConsent( $platform ) ) {
        return '';
    }

    $url = '';
    if ( $event && method_exists( $event, 'getPayloadValue' ) ) {
        $url = (string) $event->getPayloadValue( 'referrer_url' );
    }
    if ( $url === '' ) {
        $url = pys_get_session_entry_referrer_url();
    }
    if ( $url === '' ) {
        return '';
    }

    $url = esc_url_raw( $url );
    $url = pys_strip_url_query_and_fragment( $url );

    if ( $url && pys_is_valid_public_url( $url ) ) {
        return $url;
    }
    return '';
}

/**
 * Check if EDD Recurring plugin is active.
 * Uses static caching to prevent repeated checks.
 *
 * @return bool
 */
function isEddRecurringActive() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $cache = is_plugin_active( 'edd-recurring/edd-recurring.php' );
    return $cache;
}

/**
 * Check if EDD Software Licensing plugin is active.
 * Uses static caching to prevent repeated checks.
 *
 * @return bool
 */
function isEddSoftwareLicensingActive() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $cache = is_plugin_active( 'edd-software-licensing/edd-software-licenses.php' );
    return $cache;
}

/**
 * Check if WooCommerce Subscriptions plugin is active.
 * Uses static caching to prevent repeated checks.
 *
 * @return bool
 */
function isWooCommerceSubscriptionsActive() {
    static $cache = null;

    if ( $cache !== null ) {
        return $cache;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $cache = is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' );
    return $cache;
}
function isWcfActive() {
    return function_exists('wcf');
}

function isPinterestActive( $checkCompatibility = true ) {
	
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	
	$active = is_plugin_active( 'pixelyoursite-pinterest/pixelyoursite-pinterest.php' );
	
	if ( $checkCompatibility ) {
		return $active && ! isPinterestVersionIncompatible();
	} else {
		return $active;
	}
	
}

function getUserRoles() {
    $user = wp_get_current_user();

    if ( $user->ID !== 0 ) {
        $user_roles = implode( ',', $user->roles );
    } else {
        $user_roles = 'guest';
    }
    return $user_roles;
}

function isPinterestVersionIncompatible() {
    // get_plugin_data() opens the plugin file and parses its header on every call, and
    // this sits behind the is*Active() checks every pixel asks while building each
    // event. A plugin's version cannot change within a request.
    static $incompatible = null;
    if ( null !== $incompatible ) {
        return $incompatible;
    }
    
    if ( ! function_exists( 'get_plugin_data' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    
    $data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-pinterest/pixelyoursite-pinterest.php', false, false );
    
    $incompatible = ! version_compare( $data['Version'], PYS_FREE_PINTEREST_MIN_VERSION, '>=' );

    return $incompatible;
    
}

function isSuperPackActive( $checkCompatibility = true ) {
    
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    
    $active = is_plugin_active( 'pixelyoursite-super-pack/pixelyoursite-super-pack.php' );
    
    if ( $checkCompatibility ) {
        return $active && function_exists( 'PixelYourSite\SuperPack' ) && ! isSuperPackVersionIncompatible();
    } else {
        return $active;
    }
    
}

function isBingActive( $checkCompatibility = true ) {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $active = is_plugin_active( 'pixelyoursite-bing/pixelyoursite-bing.php' );

    if ( $checkCompatibility ) {
        return $active && ! isBingVersionIncompatible()
            && function_exists( 'PixelYourSite\Bing' )
            && Bing() instanceof Plugin; // false for dummy
    } else {
        return $active;
    }

}

function isBingVersionIncompatible() {
    // get_plugin_data() opens the plugin file and parses its header on every call, and
    // this sits behind the is*Active() checks every pixel asks while building each
    // event. A plugin's version cannot change within a request.
    static $incompatible = null;
    if ( null !== $incompatible ) {
        return $incompatible;
    }

    if ( ! function_exists( 'get_plugin_data' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-bing/pixelyoursite-bing.php', false, false );

    $incompatible = ! version_compare( $data['Version'], PYS_FREE_BING_MIN_VERSION, '>=' );

    return $incompatible;

}

/**
 * Check if Reddit plugin installed and activated.
 * @param bool $checkCompatibility
 * @return bool
 */
function isRedditActive( bool $checkCompatibility = true ): bool {

	if ( !function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	$active = is_plugin_active( 'pixelyoursite-reddit/pixelyoursite-reddit.php' );

	if ( $checkCompatibility ) {
		return $active && !isRedditVersionIncompatible()
		       && function_exists( 'PixelYourSite\Reddit' )
		       && Reddit() instanceof Plugin; // false for dummy
	} else {
		return $active;
	}
}

/**
 * Check if Reddit plugin version is compatible.
 * @return bool
 */
function isRedditVersionIncompatible(): bool {
	// get_plugin_data() opens the plugin file and parses its header on every call, and
	// this sits behind the is*Active() checks every pixel asks while building each
	// event. A plugin's version cannot change within a request.
	static $incompatible = null;
	if ( null !== $incompatible ) {
		return $incompatible;
	}

	if ( !function_exists( 'get_plugin_data' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	$data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-reddit/pixelyoursite-reddit.php', false, false );

	$incompatible = ! version_compare( $data[ 'Version' ], PYS_FREE_REDDIT_MIN_VERSION, '>=' );

	return $incompatible;
}

/**
 * Check if Pixel Cost of goods plugin installed and activated.
 * Uses static caching to prevent repeated checks.
 *
 * @return bool
 */
function isPixelCogActive() {
    static $cache = null;
    if ( $cache !== null ) {
        return $cache;
    }
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $active = is_plugin_active( 'pixel-cost-of-goods/pixel-cost-of-goods.php' );

    // Agency Mode: a HARD-blocked Cost of Goods behaves as if deactivated, so its
    // integration inside PixelYourSite (profit calc, toggles, columns) turns off.
    if ( $active ) {
        $block = get_option( 'pys_am_pixel-cost-of-goods_domain_block' );
        if ( is_array( $block ) && ! empty( $block['blocked'] ) && isset( $block['type'] ) && 'hard' === $block['type'] ) {
            $active = false;
        }
    }

    $cache = $active;
    return $cache;
}

/**
 * Check if WooCommerce native Cost of Goods (COGS) is available.
 * Requires WooCommerce 10.3 or later.
 * Uses static caching to prevent repeated reflection calls.
 *
 * @return bool
 */
function isWcCogAvailable() {
    static $cache = null;
    if ( $cache !== null ) {
        return $cache;
    }
    $cache = method_exists( 'WC_Order', 'get_cogs_total_value' );
    return $cache;
}

// ─────────────────────────────────────────────────────────────────────────────
// Global COG (Cost of Goods) helpers
// All COG logic for events and ConversionProfit goes through these functions.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check if the WooCommerce native COGS feature flag is enabled by the user.
 * Requires WooCommerce 8.3+ (FeaturesUtil) and COGS enabled in
 * WooCommerce → Settings → Advanced → Features.
 *
 * @return bool
 */
function isWcCogFeatureEnabled() {
    static $cache = null;
    if ( $cache !== null ) {
        return $cache;
    }
    if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        $cache = false;
        return $cache;
    }
    $cache = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' );
    return $cache;
}

/**
 * Return the active COG source selected in the global setting.
 * Anything other than the three known values falls back to 'auto'.
 *
 * @return string 'auto' | 'pixel_cog' | 'wc_cog'
 */
function pys_cog_get_source() {
    $source = PYS()->getOption( 'woo_cog_source' );
    return in_array( $source, array( 'auto', 'pixel_cog', 'wc_cog' ), true ) ? $source : 'auto';
}

/**
 * True when WooCommerce native COGS can be read: WC 10.3+ AND the feature flag is on.
 * WooCommerce guards its COGS methods and may emit "doing it wrong" while the feature
 * is off, so never call get_cogs_value() / get_cogs_total_value() unless this is true.
 *
 * @return bool
 */
function pys_cog_wc_is_ready() {
    return isWcCogAvailable() && isWcCogFeatureEnabled();
}

/**
 * Whether value='cog' options must be reset once the PixelYourSite Cost of Goods
 * add-on is deactivated: only when no source remains without it.
 * 'pixel_cog' always loses its source; 'wc_cog' and 'auto' keep WooCommerce COGS.
 *
 * @param string $source   'auto' | 'pixel_cog' | 'wc_cog'
 * @param bool   $wc_ready result of pys_cog_wc_is_ready()
 * @return bool
 */
function pys_cog_restore_needed_after_addon_deactivation( $source, $wc_ready ) {
    if ( $source === 'pixel_cog' ) {
        return true;
    }
    return ! $wc_ready;
}

/**
 * Check whether the currently selected COG source is fully available and ready.
 * - 'wc_cog'    → WooCommerce 10.3+ AND feature flag enabled in WC Settings.
 * - 'pixel_cog' → PixelYourSite Cost of Goods plugin active.
 * - 'auto'      → either of the two above.
 *
 * @return bool
 */
function pys_cog_source_is_available() {
    $source = pys_cog_get_source();
    if ( $source === 'wc_cog' ) {
        return pys_cog_wc_is_ready();
    }
    if ( $source === 'auto' ) {
        return isPixelCogActive() || pys_cog_wc_is_ready();
    }
    return isPixelCogActive();
}

/**
 * Clean a stored cost value the way the PixelYourSite Cost of Goods add-on does:
 * keep only digits, ',' and '.'.
 *
 * @param mixed $raw Meta / option value (may be '', false, null).
 * @return string '' = no cost stored; '0' is a found cost of 0.
 */
function pys_cog_clean_cost( $raw ) {
    if ( $raw === false || $raw === null || is_array( $raw ) || is_object( $raw ) ) {
        return '';
    }
    return preg_replace( "/[^,.0-9]/", '', (string) $raw );
}

/**
 * Cleaned cost as a float, with ',' read as the decimal point ('12,5' → 12.5). The add-on only
 * does the ',' → '.' swap on its own cleaned string; we do it before the float cast on purpose.
 *
 * @param mixed $raw
 * @return float|null null = no cost stored.
 */
function pys_cog_normalize_cost( $raw ) {
    $clean = pys_cog_clean_cost( $raw );
    return $clean === '' ? null : (float) str_replace( ',', '.', $clean );
}

/**
 * Normalise a stored cost type: only 'percent' is percent, everything else is a fixed amount.
 *
 * @param mixed $raw
 * @return string 'fix' | 'percent'
 */
function pys_cog_normalize_cost_type( $raw ) {
    return $raw === 'percent' ? 'percent' : 'fix';
}

/**
 * Category level of the PYS COG cascade for the resolver: highest value among the product's
 * categories. Exactly like the add-on, a category counts when its cleaned string is truthy
 * (PHP truthiness, before ',' → '.'): '' and '0' are not found, '0.0' / '0,00' / '00' are a
 * found 0. On a tie the 'percent' category wins (the add-on sorts [val, type] pairs).
 * Separate from get_product_cog_by_cat(), which skips non-numeric values such as '12,5' and
 * must keep behaving as it does for existing callers.
 *
 * @param int $product_id Id of the product line itself (not its parent), as the add-on does.
 * @return array{val: float, type: string}|null
 */
function pys_cog_resolve_category_cost( $product_id ) {
    $term_list = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );
    if ( empty( $term_list ) || is_wp_error( $term_list ) ) {
        return null;
    }

    $best = null;
    foreach ( $term_list as $term_id ) {
        $clean = pys_cog_clean_cost( get_term_meta( $term_id, '_pixel_cost_of_goods_cost_val', true ) );
        if ( ! $clean ) {
            continue;
        }
        $val  = (float) str_replace( ',', '.', $clean );
        $type = pys_cog_normalize_cost_type( get_term_meta( $term_id, '_pixel_cost_of_goods_cost_type', true ) );
        if ( $best === null || $val > $best['val'] || ( $val === $best['val'] && $type === 'percent' ) ) {
            $best = [ 'val' => $val, 'type' => $type ];
        }
    }
    return $best;
}

/**
 * Resolve the cost of one product for the selected COG source (pys_cog_get_source()).
 *
 * - 'pixel_cog': PYS cascade product → parent (variation) → category → global.
 * - 'wc_cog':    WooCommerce native COGS (only when ready).
 * - 'auto':      PYS product → PYS parent → PYS category → WooCommerce → PYS global.
 * PYS levels need the PYS COG add-on active; the WC level needs pys_cog_wc_is_ready().
 * WooCommerce stores a COGS of 0 as null on non-variations, so a found WC cost of 0 only occurs
 * on variations.
 *
 * @param \WC_Product $product
 * @return array{source: string, level: string, type: string, val: float}|null null = no cost.
 */
function pys_cog_resolve_product_cost( $product ) {
    $source  = pys_cog_get_source();
    $use_pys = $source !== 'wc_cog' && isPixelCogActive();
    $use_wc  = $source !== 'pixel_cog' && pys_cog_wc_is_ready();
    $is_var  = $product->is_type( 'variation' );
    $id      = $product->get_id();

    if ( $use_pys ) {
        // Level 1: product
        $val = pys_cog_normalize_cost( get_post_meta( $id, '_pixel_cost_of_goods_cost_val', true ) );
        if ( $val !== null ) {
            return [ 'source' => 'pixel_cog', 'level' => 'product', 'type' => pys_cog_normalize_cost_type( get_post_meta( $id, '_pixel_cost_of_goods_cost_type', true ) ), 'val' => $val ];
        }
        // Level 2: parent of a variation; the type is read from the variation's own id - mirrors the add-on on purpose
        if ( $is_var ) {
            $val = pys_cog_normalize_cost( get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_val', true ) );
            if ( $val !== null ) {
                return [ 'source' => 'pixel_cog', 'level' => 'parent', 'type' => pys_cog_normalize_cost_type( get_post_meta( $id, '_pixel_cost_of_goods_cost_type', true ) ), 'val' => $val ];
            }
        }
        // Level 3: category, looked up by the line's own product id (a variation has no terms, so it skips this level) - mirrors the PYS COG add-on on purpose
        $cat = pys_cog_resolve_category_cost( $id );
        if ( $cat !== null ) {
            return [ 'source' => 'pixel_cog', 'level' => 'category', 'type' => $cat['type'], 'val' => $cat['val'] ];
        }
    }

    if ( $use_wc ) {
        $has_cost = $product->get_cogs_value() !== null;
        if ( ! $has_cost && $is_var ) {
            $parent   = wc_get_product( $product->get_parent_id() );
            $has_cost = $parent && $parent->get_cogs_value() !== null;
        }
        if ( $has_cost ) {
            return [ 'source' => 'wc_cog', 'level' => 'wc', 'type' => 'fix', 'val' => (float) $product->get_cogs_total_value() ];
        }
    }

    if ( $use_pys ) {
        // Level 4: global option
        $val = pys_cog_normalize_cost( get_option( '_pixel_cost_of_goods_cost_val' ) );
        if ( $val !== null ) {
            return [ 'source' => 'pixel_cog', 'level' => 'global', 'type' => pys_cog_normalize_cost_type( get_option( '_pixel_cost_of_goods_cost_type' ) ), 'val' => $val ];
        }
    }

    return null;
}

/**
 * Whether a line is taxed when its profit is calculated (R4): a line resolved to the PYS COG
 * add-on follows the add-on's own option, a line resolved to WooCommerce COGS follows ours.
 *
 * @param string $source 'pixel_cog' | 'wc_cog' (the `source` of pys_cog_resolve_product_cost()).
 * @return bool true = with tax.
 */
function pys_cog_line_includes_tax( $source ) {
    if ( $source === 'pixel_cog' ) {
        return get_option( '_pixel_cog_tax_calculating' ) != 'no';
    }
    return (bool) PYS()->getOption( 'woo_cog_wc_include_tax' );
}

/**
 * Source whose tax rule applies to a line that has no cost: the add-on's when it is active and
 * allowed to answer, otherwise ours.
 *
 * @return string 'pixel_cog' | 'wc_cog'
 */
function pys_cog_default_tax_source() {
    return ( pys_cog_get_source() !== 'wc_cog' && isPixelCogActive() ) ? 'pixel_cog' : 'wc_cog';
}

/**
 * Cost of one line for a resolved cost: fixed = per unit × quantity, percent = share of $percent_base.
 *
 * @param array{type: string, val: float} $cost         From pys_cog_resolve_product_cost().
 * @param float|int                       $quantity
 * @param float                           $percent_base Line amount a percent cost is taken from.
 * @return float
 */
function pys_cog_cost_amount( $cost, $quantity, $percent_base ) {
    if ( $cost['type'] === 'percent' ) {
        return (float) $percent_base * $cost['val'] / 100;
    }
    return (float) $cost['val'] * (float) $quantity;
}

/**
 * Calculate profit (price − COG) for a single product line.
 * Works for the 'pixel_cog', 'wc_cog' and 'auto' sources.
 *
 * For 'pixel_cog': delegates to pys_woo_get_cog_product_value() (PYS COG cascade).
 * For 'wc_cog' and 'auto': the cost comes from pys_cog_resolve_product_cost(); amount is the
 * tax-adjusted price × qty (rule of the line's own source), cost is fixed × qty or a percent
 * of that amount.
 *
 * Without any cost the profit is the amount itself (cost 0), so callers never get an empty value.
 * Returns '' only when the selected source is not available.
 *
 * @param \WC_Product $product
 * @param int         $quantity
 * @param float|string $price   Unit price (before tax adjustments).
 * @return float|string Profit value, or '' if unavailable.
 */
function pys_cog_get_product_profit( $product, $quantity, $price ) {
    if ( ! pys_cog_source_is_available() ) {
        return '';
    }

    // pixel_cog: delegate to existing PYS COG cascade function
    if ( pys_cog_get_source() === 'pixel_cog' ) {
        return pys_woo_get_cog_product_value( $product, $quantity, $price );
    }

    $cost       = pys_cog_resolve_product_cost( $product );
    $price_args = [ 'qty' => $quantity, 'price' => $price ];
    if ( $cost === null ) {
        // no cost found: the value is the amount itself, like pys_woo_get_cog_product_value()
        if ( pys_cog_line_includes_tax( pys_cog_default_tax_source() ) ) {
            $amount = (float) wc_get_price_including_tax( $product, $price_args );
        } else {
            $amount = (float) wc_get_price_excluding_tax( $product, $price_args );
        }
        return formatPriceTrimZeros( $amount );
    }
    if ( pys_cog_line_includes_tax( $cost['source'] ) ) {
        $amount = (float) wc_get_price_including_tax( $product, $price_args );
    } else {
        $amount = (float) wc_get_price_excluding_tax( $product, $price_args );
    }
    return formatPriceTrimZeros( $amount - pys_cog_cost_amount( $cost, $quantity, $amount ) );
}

/**
 * The product lines of an order with what the profit math needs, resolved through
 * pys_cog_resolve_product_cost() (use for the 'wc_cog' and 'auto' sources).
 *
 * Per line: product (the variation when there is one, null when it was deleted), quantity,
 * total (after coupons), total_tax, subtotal (before coupons), subtotal_tax, cost (resolver
 * result or null), include_tax (the line's tax rule), revenue (total [+ total_tax]) and
 * cost_amount. Both are null when the line has no cost.
 *
 * cost_amount: fixed = per unit × quantity, percent = share of the line SUBTOTAL before coupons
 * (with its tax per the line's rule), as the PYS COG add-on does. A WooCommerce-resolved line
 * uses the cost WooCommerce stored on the order item (get_cogs_value(): what the item cost when
 * the order was placed) when the item carries one, otherwise the resolver value × quantity.
 *
 * @param \WC_Order $order
 * @return array[]
 */
function pys_cog_get_order_lines( $order ) {
    $lines = [];
    foreach ( $order->get_items() as $item ) {
        if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
            continue;
        }
        $product  = $item->get_product();
        $cost     = $product ? pys_cog_resolve_product_cost( $product ) : null;
        $quantity = $item->get_quantity();
        $line     = [
            'product'      => $product ? $product : null,
            'quantity'     => $quantity,
            'total'        => (float) $item->get_total(),
            'total_tax'    => (float) $item->get_total_tax(),
            'subtotal'     => (float) $item->get_subtotal(),
            'subtotal_tax' => (float) $item->get_subtotal_tax(),
            'cost'         => $cost,
            'include_tax'  => null,
            'revenue'      => null,
            'cost_amount'  => null,
        ];
        if ( $cost !== null ) {
            $include_tax         = pys_cog_line_includes_tax( $cost['source'] );
            $line['include_tax'] = $include_tax;
            $line['revenue']     = $line['total'] + ( $include_tax ? $line['total_tax'] : 0.0 );
            $stored              = $cost['source'] === 'wc_cog' ? (float) $item->get_cogs_value() : 0.0;
            if ( $stored > 0 ) {
                $line['cost_amount'] = $stored;
            } else {
                $base                = $line['subtotal'] + ( $include_tax ? $line['subtotal_tax'] : 0.0 );
                $line['cost_amount'] = pys_cog_cost_amount( $cost, $quantity, $base );
            }
        }
        $lines[] = $line;
    }
    return $lines;
}

/**
 * Calculate total profit for a WooCommerce order.
 * Works for the 'wc_cog', 'pixel_cog' and 'auto' sources.
 *
 * For 'wc_cog' and 'auto' (per line, see pys_cog_get_order_lines(); lines without a cost
 * contribute neither revenue nor cost, like the PYS COG add-on):
 *   profit = Σ (line total after coupons [+ line tax] − line cost) [+ shipping]
 *   Tax follows each line's own source (pys_cog_line_includes_tax()).
 *   Shipping: 'auto' with the PYS COG add-on active adds the add-on's shipping profit meta when
 *   its own shipping option is on; otherwise woo_cog_wc_include_shipping [+ the shipping tax
 *   when woo_cog_wc_include_tax is on as well]. 'auto' never reads the add-on's order profit meta.
 *
 * For 'pixel_cog':
 *   Uses pre-calculated '_pixel_cost_of_goods_order_profit' order meta (stored by COG plugin).
 *   Falls back to per-product cascade calculation if meta is absent.
 *
 * Returns 0.0 when profit cannot be determined.
 *
 * @param \WC_Order $order
 * @return float
 */
function pys_cog_get_order_profit( $order ) {
    if ( ! pys_cog_source_is_available() ) {
        return 0.0;
    }

    $source = pys_cog_get_source();
    if ( $source !== 'pixel_cog' ) {
        $profit = 0.0;
        foreach ( pys_cog_get_order_lines( $order ) as $line ) {
            if ( $line['cost'] === null ) {
                continue;
            }
            $profit += $line['revenue'] - $line['cost_amount'];
        }
        $shipping_mode = get_option( '_pixel_cost_of_goods_shipping_cost' );
        if ( $source === 'auto' && isPixelCogActive() ) {
            // the add-on's own shipping rule: its profit meta, only when its shipping option is on
            if ( $shipping_mode && $shipping_mode !== 'exclude' ) {
                $profit += (float) $order->get_meta( '_pixel_cost_of_goods_order_shipping_profit', true );
            }
        } elseif ( PYS()->getOption( 'woo_cog_wc_include_shipping' ) ) {
            $profit += (float) $order->get_shipping_total();
            if ( PYS()->getOption( 'woo_cog_wc_include_tax' ) ) {
                $profit += (float) $order->get_shipping_tax();
            }
        }
        return max( 0.0, round( $profit, 2 ) );
    }

    // pixel_cog: prefer pre-calculated meta stored by the COG plugin on order save
    $profit = $order->get_meta( '_pixel_cost_of_goods_order_profit', true );
    if ( $profit !== '' && $profit !== false && is_numeric( $profit ) ) {
        return round( (float) $profit, 2 );
    }

    $calculated = getAvailableProductCogOrder( $order );
    return is_numeric( $calculated ) ? round( (float) $calculated, 2 ) : 0.0;
}

/**
 * Resolve Cost of Goods for a single product with 4-level cascade fallback:
 *   1. Product meta  →  2. Parent variation meta  →  3. Category meta  →  4. Global option
 *
 * @param \WC_Product $product
 * @return array{type: string, val: string}
 */
function getAvailableProductCog( $product ) {
    $product_id   = $product->get_id();
    $cost_type    = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_type', true );
    $product_cost = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_val', true );

    // Level 2: parent variation
    if ( ! $product_cost && $product->is_type( 'variation' ) ) {
        $parent_id    = $product->get_parent_id();
        $cost_type    = get_post_meta( $parent_id, '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $parent_id, '_pixel_cost_of_goods_cost_val', true );
    }

    if ( $product_cost ) {
        return [ 'type' => $cost_type, 'val' => $product_cost ];
    }

    // Level 3: category — single query via get_product_cog_by_cat()
    $cat_cog = get_product_cog_by_cat( $product_id );
    if ( $cat_cog['val'] ) {
        return [ 'type' => $cat_cog['type'], 'val' => $cat_cog['val'] ];
    }

    // Level 4: global option
    return [
        'type' => get_option( '_pixel_cost_of_goods_cost_type' ),
        'val'  => get_option( '_pixel_cost_of_goods_cost_val' ),
    ];
}


function getAvailableProductCogOrder( $order ) {
    // ── 'wc_cog' and 'auto': every line through the resolver and its own tax rule ──
    // A line without a cost stays in the total with a zero cost: this is the event value.
    if ( pys_cog_get_source() !== 'pixel_cog' && pys_cog_source_is_available() ) {
        $default_source = pys_cog_default_tax_source();
        $order_total    = 0.0;
        $cost           = 0.0;
        foreach ( $order->get_items() as $item ) {
            if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
                continue;
            }
            $productObject = wc_get_product( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() );
            if ( ! $productObject ) {
                continue;
            }
            $line_cost    = pys_cog_resolve_product_cost( $productObject );
            $include_tax  = pys_cog_line_includes_tax( $line_cost !== null ? $line_cost['source'] : $default_source );
            // pys_round() like Pro's pys_cog_get_order_products_args(): the Purchase values cannot differ by cents
            $order_total += (float) pys_round( $item->get_total() );
            if ( $include_tax ) {
                $order_total += (float) pys_round( $item->get_total_tax() );
            }
            if ( $line_cost === null ) {
                continue;
            }
            $qlt  = $item->get_quantity();
            $base = 0.0;
            if ( $line_cost['type'] === 'percent' ) {
                // a percent cost is a share of the line subtotal, like the COG plugin: discounted, before coupons
                $price = pys_woo_line_unit_price( pys_round( $item->get_subtotal() ), pys_round( $item->get_subtotal_tax() ), $qlt, $include_tax );
                if ( $price === false ) {
                    $price_args = [ 'qty' => 1, 'price' => $productObject->get_price() ];
                    $price      = $include_tax
                        ? wc_get_price_including_tax( $productObject, $price_args )
                        : wc_get_price_excluding_tax( $productObject, $price_args );
                }
                $base = $price * $qlt;
            }
            $cost += pys_cog_cost_amount( $line_cost, $qlt, $base );
        }
        return $order_total - $cost;
    }

    // ── PYS COG plugin path ───────────────────────────────────────────────────
    // For pixel_cog: tax inclusion controlled by the PYS COG plugin's own setting
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating' ) == 'no';
    $order_total  = 0.0;
    $cost         = 0.0;
    $custom_total = 0.0;
    $cat_isset    = 0;
    $notice       = '';
    // Read global fallback values once before the loop
    $global_cost  = get_option( '_pixel_cost_of_goods_cost_val' );
    $global_type  = get_option( '_pixel_cost_of_goods_cost_type' );

    foreach ( $order->get_items() as $item_id => $item ) {
        $product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
        $product = wc_get_product($product_id);
        if(!$product) continue;

        // what the customer paid for the line, as in Pro and in the COG plugin
        $order_total += (float) $item->get_total();
        if ( ! $isWithoutTax ) {
            $order_total += (float) $item->get_total_tax();
        }

        $product_id    = $product->get_id();

        $cost_type    = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_val', true );

        if ( ! $product_cost && $product->is_type( 'variation' ) ) {
            $cost_type    = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        $qlt   = $item['quantity'];
        // a percent cost is a share of the line subtotal, like the COG plugin: discounted, before coupons
        $price = pys_woo_line_unit_price( $item->get_subtotal(), $item->get_subtotal_tax(), $qlt, ! $isWithoutTax );
        if ( $price === false ) {
            $price_args = [ 'qty' => 1, 'price' => $product->get_price() ];
            $price      = $isWithoutTax
                ? wc_get_price_excluding_tax( $product, $price_args )
                : wc_get_price_including_tax( $product, $price_args );
        }

        if ( $product_cost ) {
            $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
            $custom_total += $price * $qlt;
        } else {
            // Single query for both val + type via the combined category helper
            $cat_cog      = get_product_cog_by_cat( $product_id );
            $product_cost = $cat_cog['val'];
            $cost_type    = $cat_cog['type'];
            if ( $product_cost ) {
                $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
                $custom_total += $price * $qlt;
                $notice        = 'Category Cost of Goods was used for some products.';
                $cat_isset     = 1;
            } elseif ( $global_cost ) {
                $cost         += ( $global_type == 'percent' ) ? (float) $price * ( (float) $global_cost / 100 ) * $qlt : (float) $global_cost * $qlt;
                $custom_total += $price * $qlt;
                $notice        = $cat_isset ? 'Global and Category Cost of Goods was used for some products.' : 'Global Cost of Goods was used for some products.';
            } else {
                $notice = "Some products don't have Cost of Goods.";
            }
        }
    }

    return $order_total - $cost;
}
function getProductCogOrder( $args ) {
    if ( ! isPixelCogActive() ) {
        return [ 'cost' => 0, 'profit' => 0 ];
    }

    $order_total  = 0.0;
    $cost         = 0.0;
    $custom_total = 0.0;
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating' ) == 'no';
    // Read global fallback values once before the loop
    $global_cost  = get_option( '_pixel_cost_of_goods_cost_val' );
    $global_type  = get_option( '_pixel_cost_of_goods_cost_type' );

    foreach ( $args['products'] as $productData ) {
        $order_total += (float) $productData['total'];
        if ( ! $isWithoutTax ) {
            $order_total += (float) $productData['total_tax'];
        }

        $product_id    = $productData['product_id'];
        $productObject = wc_get_product( $product_id );
        if ( ! $productObject ) continue;

        $cost_type    = get_post_meta( $productObject->get_id(), '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $productObject->get_id(), '_pixel_cost_of_goods_cost_val', true );

        if ( ! $product_cost && $productObject->is_type( 'variation' ) ) {
            $cost_type    = get_post_meta( $productObject->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $productObject->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        $qlt   = $productData['quantity'];
        // a percent cost is a share of the line subtotal, like the COG plugin: discounted, before coupons
        $price = pys_woo_line_unit_price( $productData['subtotal'] ?? null, $productData['subtotal_tax'] ?? 0, $qlt, ! $isWithoutTax );
        if ( $price === false ) {
            $price_args = [ 'qty' => 1, 'price' => $productObject->get_price() ];
            $price      = $isWithoutTax
                ? wc_get_price_excluding_tax( $productObject, $price_args )
                : wc_get_price_including_tax( $productObject, $price_args );
        }

        if ( $product_cost ) {
            $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
            $custom_total += $price * $qlt;
        } else {
            // Single query for both val + type via the combined category helper
            $cat_cog      = get_product_cog_by_cat( $product_id );
            $product_cost = $cat_cog['val'];
            $cost_type    = $cat_cog['type'];
            if ( $product_cost ) {
                $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
                $custom_total += $price * $qlt;
            } elseif ( $global_cost ) {
                $cost         += ( $global_type == 'percent' ) ? (float) $price * ( (float) $global_cost / 100 ) * $qlt : (float) $global_cost * $qlt;
                $custom_total += $price * $qlt;
            }
        }
    }

    return [ 'cost' => $cost, 'profit' => $custom_total - $cost ];
}

/**
 * Check if every product in the order has a defined Cost of Goods.
 *
 * Uses the global woo_cog_source option (via pys_cog_get_source()):
 * - 'wc_cog':    WooCommerce native COGS (10.3+), through pys_cog_resolve_product_cost().
 * - 'auto':      the resolver's cascade (PYS levels, then WooCommerce).
 * - 'pixel_cog': PYS COG plugin. Cascades: product → parent → category → global.
 *
 * Returns false if any single product lacks a cost value.
 *
 * @param \WC_Order $order
 * @return bool
 */
function poasAllProductsHaveCost( $order ) {
    $source = pys_cog_get_source();

    // wc_cog / auto: every product line has to resolve to a cost; a deleted product does not
    if ( $source !== 'pixel_cog' ) {
        if ( ! pys_cog_source_is_available() ) {
            return false;
        }
        foreach ( pys_cog_get_order_lines( $order ) as $line ) {
            if ( $line['product'] === null || $line['cost'] === null ) {
                return false;
            }
        }
        return true;
    }

    // PYS COG plugin: cascade through product → parent → category → global
    if ( ! isPixelCogActive() ) {
        return false;
    }

    $global_cost = get_option( '_pixel_cost_of_goods_cost_val' );

    foreach ( $order->get_items() as $item ) {
        if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
            continue;
        }

        $variation_id = $item->get_variation_id();
        $product_id   = $variation_id ? $variation_id : $item->get_product_id();

        // Level 1: product meta
        $product_cost = get_post_meta( $product_id, '_pixel_cost_of_goods_cost_val', true );
        if ( $product_cost !== '' && is_numeric( $product_cost ) ) {
            continue;
        }

        // Level 2: parent product meta (if variation)
        if ( $variation_id ) {
            $parent_id    = $item->get_product_id();
            $product_cost = get_post_meta( $parent_id, '_pixel_cost_of_goods_cost_val', true );
            if ( $product_cost !== '' && is_numeric( $product_cost ) ) {
                continue;
            }
        }

        // Level 3: category cost
        $cat_cost = get_product_cost_by_cat( $product_id );
        if ( $cat_cost !== '' && $cat_cost !== false && is_numeric( $cat_cost ) ) {
            continue;
        }

        // Level 4: global cost
        if ( $global_cost !== '' && $global_cost !== false && is_numeric( $global_cost ) ) {
            continue;
        }

        // No cost found at any level
        return false;
    }

    return true;
}

/**
 * Net revenue of an order for Meta: the order profit, when every product has a cost.
 * Same gate and number as the Pro ProfitConversion event (poasAllProductsHaveCost() + pys_cog_get_order_profit()).
 *
 * @param \WC_Order $order
 * @return float|null null = omit it (not an order, source unavailable, a product without cost, profit zero or negative).
 */
function pys_cog_get_order_net_revenue( $order ) {
    if ( ! ( $order instanceof \WC_Order ) || ! pys_cog_source_is_available() ) {
        return null;
    }
    if ( ! poasAllProductsHaveCost( $order ) ) {
        return null;
    }
    $profit = pys_cog_get_order_profit( $order );
    if ( $profit <= 0.0 ) {
        return null;
    }
    return round( $profit, 2 );
}

/**
 * Whether a Meta Purchase event describes the whole order, which is what Meta's
 * net_revenue (the whole order's profit) needs. False when the event's quantity
 * differs from the order's (an event narrowed to some products). Free has no
 * deposit / partial-payment handling and no per-pixel product filter, so this is
 * the only whole-order signal it needs.
 *
 * The order side is the sum of quantities over the order's product lines whose
 * product still exists (the lines getAvailableProductCogOrder() prices). The
 * event side, in order of preference: the event's own product list ($products,
 * set on events PHP built); the num_items param (browser-relayed events have no
 * product list; the "num_items" parameter can be switched off, and then it is
 * stripped before it gets here); the sum over contents[].quantity. Unknown = false.
 *
 * @param \WC_Order  $order
 * @param array      $params   Event params.
 * @param array|null $products The event's args['products'], when it has them.
 * @return bool
 */
function pys_meta_event_covers_whole_order( $order, $params, $products = null ) {
    if ( ! ( $order instanceof \WC_Order ) ) {
        return false;
    }

    $event_qty = null;
    if ( is_array( $products ) ) {
        $event_qty = 0.0;
        foreach ( $products as $product ) {
            $event_qty += isset( $product['quantity'] ) ? (float) $product['quantity'] : 0.0;
        }
    } elseif ( isset( $params['num_items'] ) && is_numeric( $params['num_items'] ) ) {
        $event_qty = (float) $params['num_items'];
    } elseif ( ! empty( $params['contents'] ) && is_array( $params['contents'] ) ) {
        $event_qty = 0.0;
        foreach ( $params['contents'] as $content ) {
            $event_qty += isset( $content['quantity'] ) ? (float) $content['quantity'] : 0.0;
        }
    }
    if ( $event_qty === null ) {
        return false;
    }

    $order_qty = 0.0;
    foreach ( $order->get_items() as $item ) {
        if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
            continue;
        }
        if ( ! wc_get_product( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() ) ) {
            continue;
        }
        $order_qty += (float) $item->get_quantity();
    }
    return $order_qty > 0 && abs( $event_qty - $order_qty ) < 0.0001;
}

function getAvailableProductCogCart() {
    if ( ! isWooCartAvailable() ) return '';

    $shipping        = WC()->cart->get_shipping_total();
    $cart_total_base = WC()->cart->get_total( 'edit' ) - $shipping;

    // ── 'wc_cog' and 'auto': every line through the resolver and its own tax rule ──
    if ( pys_cog_get_source() !== 'pixel_cog' && pys_cog_source_is_available() ) {
        $default_source = pys_cog_default_tax_source();
        // goods without any tax; each line then adds back its own tax when its rule says so
        $cart_total = $cart_total_base - WC()->cart->get_total_tax();
        if ( pys_cog_line_includes_tax( $default_source ) ) {
            $cart_total += (float) WC()->cart->get_fee_tax(); // fees are not product lines: default rule
        }
        $cost       = 0.0;
        foreach ( WC()->cart->cart_contents as $item ) {
            $product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] )
                ? $item['variation_id']
                : $item['product_id'];
            $product     = wc_get_product( $product_id );
            $line_cost   = $product ? pys_cog_resolve_product_cost( $product ) : null;
            $include_tax = pys_cog_line_includes_tax( $line_cost !== null ? $line_cost['source'] : $default_source );
            if ( $include_tax ) {
                $cart_total += (float) ( $item['line_tax'] ?? 0 );
            }
            if ( $line_cost === null ) {
                continue;
            }
            $qlt  = $item['quantity'];
            $base = 0.0;
            if ( $line_cost['type'] === 'percent' ) {
                $price = pys_woo_line_unit_price( $item['line_subtotal'] ?? null, $item['line_subtotal_tax'] ?? 0, $qlt, $include_tax );
                if ( $price === false ) {
                    $price_args = [ 'qty' => 1, 'price' => $product->get_price() ];
                    $price      = $include_tax
                        ? wc_get_price_including_tax( $product, $price_args )
                        : wc_get_price_excluding_tax( $product, $price_args );
                }
                $base = $price * $qlt;
            }
            $cost += pys_cog_cost_amount( $line_cost, $qlt, $base );
        }
        return $cart_total - $cost;
    }

    // ── PYS COG plugin path ───────────────────────────────────────────────────
    // For pixel_cog: tax inclusion controlled by the PYS COG plugin's own setting
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating' ) == 'no';
    $cart_total   = $cart_total_base;
    if ( $isWithoutTax ) {
        $cart_total -= WC()->cart->get_total_tax();
    } else {
        $cart_total -= WC()->cart->get_shipping_tax();
    }

    // Read global fallback values once before the loop
    $global_cost  = get_option( '_pixel_cost_of_goods_cost_val' );
    $global_type  = get_option( '_pixel_cost_of_goods_cost_type' );

    $cost         = 0.0;
    $custom_total = 0.0;
    $cat_isset    = 0;
    $notice       = '';

    foreach ( WC()->cart->cart_contents as $cart_item_key => $item ) {
        $product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] )
            ? $item['variation_id']
            : $item['product_id'];

        $product = wc_get_product( $product_id );
        if ( ! $product ) continue;

        $cost_type    = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_val', true );

        if ( ! $product_cost && $product->is_type( 'variation' ) ) {
            $cost_type    = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        $qlt   = $item['quantity'];
        // a percent cost is a share of what the customer pays for the line, which discount plugins
        // have already set_price()'d: the same base the COG plugin uses (the line subtotal)
        $price = pys_woo_line_unit_price( $item['line_subtotal'] ?? null, $item['line_subtotal_tax'] ?? 0, $qlt, ! $isWithoutTax );
        if ( $price === false ) {
            $price_args = [ 'qty' => 1, 'price' => $product->get_price() ];
            $price      = $isWithoutTax
                ? wc_get_price_excluding_tax( $product, $price_args )
                : wc_get_price_including_tax( $product, $price_args );
        }

        if ( $product_cost ) {
            $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
            $custom_total += $price * $qlt;
        } else {
            // Single query for both val + type via the combined category helper
            $cat_cog      = get_product_cog_by_cat( $product_id );
            $product_cost = $cat_cog['val'];
            $cost_type    = $cat_cog['type'];
            if ( $product_cost ) {
                $cost         += ( $cost_type == 'percent' ) ? $price * ( (float) $product_cost / 100 ) * $qlt : (float) $product_cost * $qlt;
                $custom_total += $price * $qlt;
                $notice        = 'Category Cost of Goods was used for some products.';
                $cat_isset     = 1;
            } elseif ( $global_cost ) {
                $cost         += ( $global_type == 'percent' ) ? (float) $price * ( (float) $global_cost / 100 ) * $qlt : (float) $global_cost * $qlt;
                $custom_total += $price * $qlt;
                $notice        = $cat_isset ? 'Global and Category Cost of Goods was used for some products.' : 'Global Cost of Goods was used for some products.';
            } else {
                $notice = "Some products don't have Cost of Goods.";
            }
        }
    }

    return $cart_total - $cost;
}

/**
 * get_product_cog_by_cat.
 *
 * Fetches COG value AND type for a product from its WooCommerce categories in a single
 * wp_get_post_terms() call. Results are cached per product_id for the duration of the request.
 * Selects the category with the numerically highest cost value (same behaviour as the
 * original asort/end approach, but only for categories that have a numeric value set).
 *
 * @param int $product_id
 * @return array{val: string, type: string}
 */
function get_product_cog_by_cat( $product_id ) {
    static $cache = [];
    if ( isset( $cache[ $product_id ] ) ) {
        return $cache[ $product_id ];
    }

    $empty     = [ 'val' => '', 'type' => '' ];
    $term_list = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );

    if ( empty( $term_list ) || is_wp_error( $term_list ) ) {
        $cache[ $product_id ] = $empty;
        return $empty;
    }

    $candidates = [];
    foreach ( $term_list as $term_id ) {
        $val = get_term_meta( $term_id, '_pixel_cost_of_goods_cost_val', true );
        if ( $val !== '' && is_numeric( $val ) ) {
            $candidates[ $term_id ] = [
                'val'  => $val,
                'type' => get_term_meta( $term_id, '_pixel_cost_of_goods_cost_type', true ),
            ];
        }
    }

    if ( empty( $candidates ) ) {
        $cache[ $product_id ] = $empty;
        return $empty;
    }

    // Use the category with the highest numeric cost value
    uasort( $candidates, function ( $a, $b ) {
        return (float) $a['val'] <=> (float) $b['val'];
    } );

    $max                  = end( $candidates );
    $cache[ $product_id ] = $max;
    return $max;
}

/**
 * get_product_type_by_cat.
 *
 * @deprecated Use get_product_cog_by_cat() which returns both val and type in one query.
 * @version 1.0.0
 * @since   1.0.0
 */
function get_product_type_by_cat( $product_id ) {
    return get_product_cog_by_cat( $product_id )['type'];
}

/**
 * get_product_cost_by_cat.
 *
 * @deprecated Use get_product_cog_by_cat() which returns both val and type in one query.
 * @version 1.0.0
 * @since   1.0.0
 */
function get_product_cost_by_cat( $product_id ) {
    return get_product_cog_by_cat( $product_id )['val'];
}

/**
 * Check if WooCommerce plugin installed and activated.
 *
 * @return bool
 */
function isWooCommerceActive() {
    return class_exists( 'woocommerce' );
}

/**
 * Check if WooCommerce cart is initialized and safe to use.
 *
 * WC()->cart stays null on requests where WooCommerce does not load the cart
 * (admin, cron, REST, or when a third-party plugin disables cart/session
 * loading on the front-end), so every cart access must be guarded.
 *
 * @return bool
 */
function isWooCartAvailable() {
    return isWooCommerceActive() && function_exists( 'WC' ) && WC() !== null && WC()->cart !== null;
}

/**
 * Check if Easy Digital Downloads plugin installed and activated.
 *
 * @return bool
 */
function isEddActive() {
    return function_exists( 'EDD' );
}

/**
 * Check if Product Catalog Feed Pro plugin installed and activated.
 *
 * @return bool
 */
function isProductCatalogFeedProActive() {
	return class_exists( 'wpwoof_product_catalog' );
}

/**
 * Check if EDD Products Feed Pro plugin installed and activated.
 *
 * @return bool
 */
function isEddProductsFeedProActive() {
	return class_exists( 'Wpeddpcf_Product_Catalog' );
}

function isBoostActive() {

	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	return is_plugin_active( 'boost/boost.php' );

}

/**
 * Check if Smart OpenGraph plugin installed and activated.
 *
 * @return bool
 */
function isSmartOpenGraphActive() {
    
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    
    return is_plugin_active( 'smart-opengraph/catalog-plugin.php' );
    
}

/**
 * Check if WPML plugin installed and activated.
 *
 * @return bool
 */
function isWPMLActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' );
}
function isPhotoCartActive() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    return is_plugin_active( 'sunshine-photo-cart/sunshine-photo-cart.php' );
}
/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 * Non-scalar values are ignored.
 *
 * @param string|array $var
 *
 * @return string|array
 */
function deepSanitizeTextField( $var ) {

    if ( is_array( $var ) ) {
        return array_map( 'deepSanitizeTextField', $var );
    } else {
        return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
    }

}

function getAvailableUserRoles() {

	$wp_roles   = new \WP_Roles();
	$user_roles = array();

	foreach ( $wp_roles->get_names() as $slug => $name ) {
		$user_roles[ $slug ] = $name;
	}

	return $user_roles;

}

function isDisabledForCurrentRole() {

	$user = wp_get_current_user();
	$disabled_for = PYS()->getOption( 'do_not_track_user_roles' );

	foreach ( (array) $user->roles as $role ) {

		if ( in_array( $role, $disabled_for ) ) {

			add_action( 'wp_head', function() {
				echo "<script type='application/javascript' id='pys-config-warning-user-role'>console.warn('PixelYourSite is disabled for current user role.');</script>\r\n";
			} );

			return true;

		}

	}

	return false;

}
function pys_round( $val, $precision = 2, $mode = PHP_ROUND_HALF_UP )  {
    if ( ! is_numeric( $val ) ) {
        $val = floatval( $val );
    }
    return round( $val, $precision, $mode );
}

function getObjectTerms($taxonomy, $post_id) {
    $terms = get_the_terms($post_id, $taxonomy);
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    // Build a map of term_id => term object for quick parent lookups
    $terms_by_id = [];
    foreach ($terms as $term) {
        $terms_by_id[$term->term_id] = $term;
    }

    // Helper function to calculate the "depth" of a term in the hierarchy
    // (how many parent levels it has)
    $get_depth = function($term) use ($terms_by_id) {
        $depth = 0;
        while ($term->parent && isset($terms_by_id[$term->parent])) {
            $term = $terms_by_id[$term->parent];
            $depth++;
        }
        return $depth;
    };

    // Sort terms by their depth so that parent categories come before child categories
    usort($terms, function($a, $b) use ($get_depth) {
        return $get_depth($a) <=> $get_depth($b);
    });

    // Return an array of decoded term names
    $results = [];
    foreach ($terms as $term) {
        $results[] = html_entity_decode($term->name);
    }

    return $results;
}
/**
 * @param string $taxonomy Taxonomy name
 *
 * @return array Array of object term names and id
 */
function getObjectTermsWithId( $taxonomy, $post_id ) {

    $terms   = get_the_terms( $post_id, $taxonomy );
    $results = array();

    if ( is_wp_error( $terms ) || empty ( $terms ) ) {
        return array();
    }

    // decode special chars
    foreach ( $terms as $term ) {
        $results[] = [
            'name' => html_entity_decode( $term->name ),
            'id'   => $term->term_id
        ];
    }

    return $results;

}
/**
 * Sanitize event name. Only letters, numbers and underscores allowed.
 *
 * @param string $name
 *
 * @return string
 */
function sanitizeKey( $name ) {

	$name = str_replace( ' ', '_', $name );
	$name = preg_replace( '/[^0-9a-zA-z_]/', '', $name );

	return $name;

}

function getCommonEventParams() {

	return array(
		'domain'     => substr( get_home_url( null, '', 'http' ), 7 ),
		'user_roles' => getUserRoles(),
		'plugin'     => 'PixelYourSite',
	);

}

function sanitizeParams( $params ) {

	$sanitized = array();

	foreach ( $params as $key => $value ) {

		// skip empty (but not zero)
        if ( ! isset( $value )  ||
            (is_string($value) && $value == "") ||
            (is_array($value) && count($value) == 0)
        ) {
            continue;
        }

		$key = sanitizeKey( $key );

		if ( is_array( $value ) ) {
			$sanitized[ $key ] = sanitizeParams( $value );
		} elseif ( is_bool( $value ) ) {
			$sanitized[ $key ] = (bool) $value;
		} elseif (is_numeric($value)) {
            $sanitized[ $key ] = $value;
        } else {
            // Do NOT html_entity_decode() here: reversing HTML encoding would
            // re-introduce XSS-capable characters (<, >, ", ') that may have
            // been previously encoded by upstream sanitizers/templating.
            $sanitized[ $key ] = sanitize_text_field( stripslashes( $value ) );
		}

	}

	return $sanitized;

}
function formatPriceTrimZeros($number, $decimals = 2) {
    $formatted = number_format($number, $decimals, '.', '');
    // Remove extra zeros on the right and a period if necessary
    return rtrim(rtrim($formatted, '0'), '.');
}
/**
 * Checks if specified event enabled at least for one configured pixel
 *
 * @param string $eventName
 *
 * @return bool
 */
function isEventEnabled( $eventName ) {

	foreach ( PYS()->getRegisteredPixels() as $pixel ) {
		/** @var Pixel|Settings $pixel */

		if ( $pixel->configured() && $pixel->getOption( $eventName ) ) {
			return true;
		}

	}

	return false;

}

function startsWith( $haystack, $needle ) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos( $haystack, $needle, - strlen( $haystack ) ) !== false;
}

function endsWith( $haystack, $needle ) {
    // search forward starting from end minus needle length characters
    return $needle === "" || ( ( $temp = strlen( $haystack ) - strlen( $needle ) ) >= 0 && strpos( $haystack, $needle,
                $temp ) !== false );
}

function getCurrentPageUrl($removeQuery = false) {
    if(!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
        return '';
    }
    if($removeQuery && isset($_SERVER['QUERY_STRING']) && isset($_SERVER['HTTP_HOST'])){
        return $_SERVER['HTTP_HOST'] . str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']);
    }
    return  $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ;
}

function removeProtocolFromUrl( $url ) {

    if ( extension_loaded( 'mbstring' ) && class_exists( 'URL\Normalizer' ) ) {

        $un = new URL\Normalizer();
        $un->setUrl( $url );
        $url = $un->normalize();
        
    }
    
    // remove fragment component
    $url_parts = parse_url( $url );
    if ( isset( $url_parts['fragment'] ) ) {
        $url = preg_replace( '/#' . $url_parts['fragment'] . '$/', '', $url );
    }
    
    // remove scheme and www and current host if any
    $url = str_replace( array( 'http://', 'https://', 'http://www.', 'https://www.', 'www.' ), '', $url );
    $url = trim( $url );
    $url = ltrim( $url, '/' );
    //$url = rtrim( $url, '/' );
    
    return $url;
    
}

/**
 * Compare single URL or array of URLs with base URL. If base URL is not set, current page URL will be used.
 *
 * @param string|array $url
 * @param string       $base
 * @param string       $rule
 *
 * @return bool
 */
function compareURLs( $url, $base = '', $rule = 'match' ) {

    // use current page url if not set
    if ( empty( $base ) ) {
        $base = getCurrentPageUrl();
    }

    // Normalize base ONCE here; _compareURLsInternal receives it already clean
    $base = removeProtocolFromUrl( $base );

    return _compareURLsInternal( $url, $base, $rule );

}

/**
 * Internal URL comparison helper. Expects $base already normalized via removeProtocolFromUrl().
 * Avoids double normalization of $base during recursive array iteration.
 *
 * @param string|array $url
 * @param string       $base  Already processed by removeProtocolFromUrl()
 * @param string       $rule
 *
 * @return bool
 */
function _compareURLsInternal( $url, $base, $rule ) {

    if ( is_string( $url ) ) {

        if ( empty( $url ) || '*' === $url ) {
            return true;
        }

        $url = rtrim( $url, '*' );  // for backward capability

        // Remember whether the user entered a path (leading slash) BEFORE
        // normalization strips it, so "contains" can anchor to the path and
        // not match inside the domain name (e.g. "/cart" vs "turenorthcart.com").
        $url_had_leading_slash = ( isset( $url[0] ) && $url[0] === '/' );

        $url = removeProtocolFromUrl( $url );

        if ( $rule == 'match' ) {
            return rtrim( $base, '/' ) === rtrim( $url, '/' );
        }

        if ( $rule == 'contains' ) {

            // If the user entered a path, anchor the match to the path so it
            // cannot accidentally match inside the domain name.
            // e.g. "/cart" must match "site.com/cart" but not "turenorthcart.com".
            $needle = $url_had_leading_slash ? '/' . $url : $url;

            if ( $base == $needle ) {
                return true;
            }

            if ( empty( $base ) || empty( $needle ) ) {
                return false;
            }

            if ( strpos( $base, $needle ) !== false ) {
                return true;
            }

            return false;

        }

        return false;

    } else {

        // $base is already normalized — iterate without re-normalizing
        foreach ( $url as $single_url ) {

            if ( _compareURLsInternal( $single_url['value'], $base, $single_url['rule'] ) ) {
                return true;
            }

        }

        return false;

    }

}

/**
 * Currency symbols
 *
 * @return array
 * */

function getPysCurrencySymbols() {
    return array(
        'AED' => '&#x62f;.&#x625;',
        'AFN' => '&#x60b;',
        'ALL' => 'L',
        'AMD' => 'AMD',
        'ANG' => '&fnof;',
        'AOA' => 'Kz',
        'ARS' => '&#36;',
        'AUD' => '&#36;',
        'AWG' => 'Afl.',
        'AZN' => 'AZN',
        'BAM' => 'KM',
        'BBD' => '&#36;',
        'BDT' => '&#2547;&nbsp;',
        'BGN' => '&#1083;&#1074;.',
        'BHD' => '.&#x62f;.&#x628;',
        'BIF' => 'Fr',
        'BMD' => '&#36;',
        'BND' => '&#36;',
        'BOB' => 'Bs.',
        'BRL' => '&#82;&#36;',
        'BSD' => '&#36;',
        'BTC' => '&#3647;',
        'BTN' => 'Nu.',
        'BWP' => 'P',
        'BYR' => 'Br',
        'BYN' => 'Br',
        'BZD' => '&#36;',
        'CAD' => '&#36;',
        'CDF' => 'Fr',
        'CHF' => '&#67;&#72;&#70;',
        'CLP' => '&#36;',
        'CNY' => '&yen;',
        'COP' => '&#36;',
        'CRC' => '&#x20a1;',
        'CUC' => '&#36;',
        'CUP' => '&#36;',
        'CVE' => '&#36;',
        'CZK' => '&#75;&#269;',
        'DJF' => 'Fr',
        'DKK' => 'DKK',
        'DOP' => 'RD&#36;',
        'DZD' => '&#x62f;.&#x62c;',
        'EGP' => 'EGP',
        'ERN' => 'Nfk',
        'ETB' => 'Br',
        'EUR' => '&euro;',
        'FJD' => '&#36;',
        'FKP' => '&pound;',
        'GBP' => '&pound;',
        'GEL' => '&#x20be;',
        'GGP' => '&pound;',
        'GHS' => '&#x20b5;',
        'GIP' => '&pound;',
        'GMD' => 'D',
        'GNF' => 'Fr',
        'GTQ' => 'Q',
        'GYD' => '&#36;',
        'HKD' => '&#36;',
        'HNL' => 'L',
        'HRK' => 'kn',
        'HTG' => 'G',
        'HUF' => '&#70;&#116;',
        'IDR' => 'Rp',
        'ILS' => '&#8362;',
        'IMP' => '&pound;',
        'INR' => '&#8377;',
        'IQD' => '&#x639;.&#x62f;',
        'IRR' => '&#xfdfc;',
        'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
        'ISK' => 'kr.',
        'JEP' => '&pound;',
        'JMD' => '&#36;',
        'JOD' => '&#x62f;.&#x627;',
        'JPY' => '&yen;',
        'KES' => 'KSh',
        'KGS' => '&#x441;&#x43e;&#x43c;',
        'KHR' => '&#x17db;',
        'KMF' => 'Fr',
        'KPW' => '&#x20a9;',
        'KRW' => '&#8361;',
        'KWD' => '&#x62f;.&#x643;',
        'KYD' => '&#36;',
        'KZT' => 'KZT',
        'LAK' => '&#8365;',
        'LBP' => '&#x644;.&#x644;',
        'LKR' => '&#xdbb;&#xdd4;',
        'LRD' => '&#36;',
        'LSL' => 'L',
        'LYD' => '&#x644;.&#x62f;',
        'MAD' => '&#x62f;.&#x645;.',
        'MDL' => 'MDL',
        'MGA' => 'Ar',
        'MKD' => '&#x434;&#x435;&#x43d;',
        'MMK' => 'Ks',
        'MNT' => '&#x20ae;',
        'MOP' => 'P',
        'MRU' => 'UM',
        'MUR' => '&#x20a8;',
        'MVR' => '.&#x783;',
        'MWK' => 'MK',
        'MXN' => '&#36;',
        'MYR' => '&#82;&#77;',
        'MZN' => 'MT',
        'NAD' => 'N&#36;',
        'NGN' => '&#8358;',
        'NIO' => 'C&#36;',
        'NOK' => '&#107;&#114;',
        'NPR' => '&#8360;',
        'NZD' => '&#36;',
        'OMR' => '&#x631;.&#x639;.',
        'PAB' => 'B/.',
        'PEN' => 'S/',
        'PGK' => 'K',
        'PHP' => '&#8369;',
        'PKR' => '&#8360;',
        'PLN' => '&#122;&#322;',
        'PRB' => '&#x440;.',
        'PYG' => '&#8370;',
        'QAR' => '&#x631;.&#x642;',
        'RMB' => '&yen;',
        'RON' => 'lei',
        'RSD' => '&#x434;&#x438;&#x43d;.',
        'RUB' => '&#8381;',
        'RWF' => 'Fr',
        'SAR' => '&#x631;.&#x633;',
        'SBD' => '&#36;',
        'SCR' => '&#x20a8;',
        'SDG' => '&#x62c;.&#x633;.',
        'SEK' => '&#107;&#114;',
        'SGD' => '&#36;',
        'SHP' => '&pound;',
        'SLL' => 'Le',
        'SOS' => 'Sh',
        'SRD' => '&#36;',
        'SSP' => '&pound;',
        'STN' => 'Db',
        'SYP' => '&#x644;.&#x633;',
        'SZL' => 'L',
        'THB' => '&#3647;',
        'TJS' => '&#x405;&#x41c;',
        'TMT' => 'm',
        'TND' => '&#x62f;.&#x62a;',
        'TOP' => 'T&#36;',
        'TRY' => '&#8378;',
        'TTD' => '&#36;',
        'TWD' => '&#78;&#84;&#36;',
        'TZS' => 'Sh',
        'UAH' => '&#8372;',
        'UGX' => 'UGX',
        'USD' => '&#36;',
        'UYU' => '&#36;',
        'UZS' => 'UZS',
        'VEF' => 'Bs F',
        'VES' => 'Bs.S',
        'VND' => '&#8363;',
        'VUV' => 'Vt',
        'WST' => 'T',
        'XAF' => 'CFA',
        'XCD' => '&#36;',
        'XOF' => 'CFA',
        'XPF' => 'Fr',
        'YER' => '&#xfdfc;',
        'ZAR' => '&#82;',
        'ZMW' => 'ZK',
    );
}

function getStandardParams( $post_id = null ) {
    global $post;
    $cpt = get_post_type();
    $params = array(
        'page_title' => "",
        'post_type' => $cpt,
        'post_id' => "",
        'plugin' => "PixelYourSite"
    );

    if(PYS()->getOption("enable_user_role_param")) {
        $params['user_role'] = getUserRoles();
    }

    if(PYS()->getOption("enable_event_url_param")) {
        $params['event_url'] = getCurrentPageUrl(true);
    }

    // If a specific post_id is provided (e.g., during AJAX requests where
    // WordPress conditional tags are unavailable), resolve page context directly.
    if ( ! empty( $post_id ) && (int) $post_id > 0 ) {
        $queried_post = get_post( (int) $post_id );
        if ( $queried_post instanceof \WP_Post ) {
            $params['page_title'] = $queried_post->post_title;
            $params['post_type']  = $queried_post->post_type;
            $params['post_id']    = $queried_post->ID;
        }
    } elseif(is_singular( 'post' )) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;

    } elseif( is_singular( 'page' ) || is_home()) {
        $params['post_type']    = 'page';
        $params['post_id']      = is_home() ? null : $post->ID;
        $params['page_title']   = is_home() == true ? get_bloginfo( 'name' ) : $post->post_title;

    } elseif (isWooCommerceActive() && is_shop()) {
        $page_id = (int) wc_get_page_id( 'shop' );
        $params['post_type'] = 'page';
        $params['post_id']   = $page_id;
        $params['page_title'] = get_the_title( $page_id );

    } elseif ( is_category() ) {
        $cat  = get_query_var( 'cat' );
        $term = get_category( $cat );
        $params['post_type']    = 'category';
        $params['post_id']      = $cat;
        $params['page_title'] = $term->name;

    } elseif ( is_tag() ) {
        $slug = get_query_var( 'tag' );
        $term = get_term_by( 'slug', $slug, 'post_tag' );
        $params['post_type']    = 'tag';
        if($term) {
            $params['post_id']      = $term->term_id;
            $params['page_title']   = $term->name;
        }


    } elseif (is_tax()) {
        $term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
        $params['post_type'] = get_query_var( 'taxonomy' );
        if ( $term ) {
            $params['post_id']      = $term->term_id;
            $params['page_title'] = $term->name;
        }

    }elseif(is_archive()){
        $params['page_title'] = get_the_archive_title();
        $params['post_type'] = 'archive';
    } elseif ((isWooCommerceActive() && $cpt == 'product') ||
        (isEddActive() && $cpt == 'download') ) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;

    } else if ($post instanceof \WP_Post) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;
    }

    if(!PYS()->getOption("enable_post_type_param")) {
        unset($params['post_type']);
    }
    if(!PYS()->getOption("enable_post_id_param")) {
        unset($params['post_id']);
    }


    return $params;
}
function getWPMLProductId($product_id, $tag) {
    $tagOption = "woo_wpml_unified_id";
    $tagLanguageOption = "woo_wpml_language";

    if (isWPMLActive() && $tag->getOption($tagOption)) {
        $wpml_product_id = !empty($tag->getOption($tagLanguageOption))
            ? apply_filters('wpml_object_id', $product_id, 'product', false, $tag->getOption($tagLanguageOption))
            : apply_filters('wpml_original_element_id', NULL, $product_id);
        if ($wpml_product_id) {
            return $wpml_product_id;
        }
    }
    return $product_id;
}
function getTrafficSource () {
    $referrer = "";
    $source = "";
    try {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        }

        $direct = empty($referrer);
        $internal = $direct ? false : (substr($referrer, 0, strlen(site_url())) === site_url());
        $external = !$direct && !$internal;
        $cookie = sanitize_text_field(!isset($_COOKIE['pysTrafficSource']) ? null : $_COOKIE['pysTrafficSource']);
        $session = sanitize_text_field(!isset($_SESSION['TrafficSource']) ? null : $_SESSION['TrafficSource']);
        if (!$external) {
            $source = $cookie || $session ? $cookie ?? $session : 'direct';
        } else {
            $source = ($cookie && $cookie === $referrer) || ($session && $session === $referrer) ? $cookie ?? $session : $referrer;
        }

        if ($source !== 'direct') {

            $parse = parse_url($source);
            if(isset($parse['host'])) {
                return $parse['host'];// leave only domain (Issue #70)
            } elseif ($source == $cookie || $source == $session){
                return $source;
            } else {
                return defined( 'REST_REQUEST' ) && REST_REQUEST ? 'REST API' : "direct";
            }
        } else {
            return defined( 'REST_REQUEST' ) && REST_REQUEST ? 'REST API' : $source;
        }
    } catch (\Exception $e) {
        return "direct";
    }
}

function filterEmails($value) {
    return validateEmail($value) ? "undefined" : $value;
}

function validateEmail($email){
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function getUtms ($seed_undefined = false) {
    $utm = array();

    $utmTerms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    foreach ($utmTerms as $utmTerm) {
        if(isset($_GET[$utmTerm])) {
            $utm[$utmTerm] = sanitize_text_field(filterEmails($_GET[$utmTerm]));
        } elseif (isset($_COOKIE["pys_".$utmTerm])) {
            $utm[$utmTerm] = sanitize_text_field(filterEmails( $_COOKIE["pys_".$utmTerm]));
        } elseif(isset($_SESSION['TrafficUtms']) && isset($_SESSION['TrafficUtms'][$utmTerm])){
            $utm[$utmTerm] = sanitize_text_field(filterEmails( $_SESSION['TrafficUtms'][$utmTerm]));
        } else {
            if($seed_undefined){
                $utm[$utmTerm] = "undefined";
            }
        }
    }

    return $utm;
}

function getUtmsId ($seed_undefined = false) {
    $utm = array();

    $utmTerms = ['fbadid', 'gadid', 'padid', 'bingid'];
    foreach ($utmTerms as $utmTerm) {
        if(isset($_GET[$utmTerm])) {
            $utm[$utmTerm] = sanitize_text_field(filterEmails($_GET[$utmTerm]));
        } elseif (isset($_COOKIE["pys_".$utmTerm])) {
            $utm[$utmTerm] = sanitize_text_field(filterEmails( $_COOKIE["pys_".$utmTerm]));
        } elseif(isset($_SESSION['TrafficUtmsId']) &&  isset($_SESSION['TrafficUtmsId'][$utmTerm])){
            $utm[$utmTerm] = sanitize_text_field(filterEmails( $_SESSION['TrafficUtmsId'][$utmTerm]));
        } else {
            if($seed_undefined){
                $utm[$utmTerm] = "undefined";
            }
        }
    }

    return $utm;
}
function getBrowserTime(){
    $dateTime = array();
    $date = new \DateTime();

    $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    $months = array('January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December');
    $hours = array('00-01', '01-02', '02-03', '03-04', '04-05', '05-06', '06-07', '07-08',
        '08-09', '09-10', '10-11', '11-12', '12-13', '13-14', '14-15', '15-16', '16-17',
        '17-18', '18-19', '19-20', '20-21', '21-22', '22-23', '23-24');

    $dateTime[] = $hours[$date->format('G')];
    $dateTime[] = $days[$date->format('w')];
    $dateTime[] = $months[$date->format('n') - 1];

    $dateTimeString = implode("|", $dateTime);
    return $dateTimeString;
}

/**
 * Pixel-option keys that carry visitor PII (email, phone, name, address) into the
 * localized pysOptions object, and therefore into the page HTML. When
 * 'fetch_user_data_via_rest' is enabled these are emptied before the options are
 * printed and delivered per-visitor by the pys/v1/dynamic-options endpoint
 * instead, so a shared HTML cache cannot serve one visitor's identity to another.
 *
 * Keyed by pixel slug. Add-ons that ship their own user data register through the
 * filter rather than being hard-coded here. Slugs whose pixel is not registered or
 * not configured are simply skipped, so listing tiktok here costs nothing and keeps
 * this map identical to the Pro one.
 *
 * @return array<string, string[]>
 */
function getDynamicUserDataKeys() {
    return apply_filters( 'pys_dynamic_user_data_keys', array(
        'facebook'  => array( 'advancedMatching' ),
        'tiktok'    => array( 'advanced_matching' ),
        'pinterest' => array( 'advancedMatching' ),
        'reddit'    => array( 'advanced_matching' ),
        'openai'    => array( 'advanced_matching' ),
    ) );
}

/**
 * Purchase confirmation pages are exempt from the PII strip described above.
 *
 * Their advanced matching data comes from the order, which is resolved from the
 * current request URL (order key / payment key) and guarded by the strict 'pii'
 * access check. The REST endpoint never receives that URL, so it cannot rebuild
 * the order context — and passing the order key into a second request would just
 * spread it across more access logs. These pages are also excluded from HTML
 * caching by every cache plugin, so leaving the data inline is safe.
 *
 * @return bool
 */
function isPurchaseConfirmationPage() {
    if ( isWooCommerceActive() && PYS()->woo_is_order_received_page() ) {
        return true;
    }

    if ( isEddActive() && function_exists( 'edd_is_success_page' ) && edd_is_success_page() ) {
        return true;
    }

    return false;
}

/**
 * Builds the src of a <noscript> tracking pixel, escaped for an HTML attribute.
 *
 * Every value is URL-encoded here, so callers pass them raw. A null or false
 * value drops the parameter, as add_query_arg() does.
 *
 * @param string $base Pixel endpoint.
 * @param array  $args Query parameters.
 * @return string
 */
function pys_noscript_pixel_src( $base, $args ) {

	foreach ( $args as $key => $value ) {
		if ( $value === null || $value === false ) {
			unset( $args[ $key ] );
			continue;
		}
		$args[ $key ] = rawurlencode( is_array( $value ) ? wp_json_encode( $value ) : (string) $value );
	}

	$src = add_query_arg( $args, $base );
	$src = str_replace( array( '[', ']' ), array( '%5B', '%5D' ), $src ); // pass markup validation

	return esc_url( $src );

}

/**
 * Whether the current code path is building the HTML that goes into the page.
 *
 * Page HTML can be stored in a shared full-page cache and replayed to every
 * other visitor, so anything that identifies the current visitor has to stay
 * out of it. Server-to-server paths -- CAPI senders, AJAX handlers, the
 * dynamic-options REST endpoint -- produce a response for one request and are
 * never inside this context.
 *
 * @param bool|null $set Raise or lower the context. Null only reads it.
 * @return bool The context that was in effect before the call, so a caller can
 *              restore it instead of assuming it was off.
 */
function pys_html_render_context( $set = null ) {

	static $active = false;

	$previous = $active;

	if ( $set !== null ) {
		$active = (bool) $set;
	}

	return $previous;

}

/**
 * Whether the pys_advanced_form_data cookie may be read on this code path.
 *
 * The cookie holds the visitor's own email, phone and name. Reading it is right
 * for anything that answers a single visitor, and wrong while page HTML is
 * being built: a full-page cache would store that identity and hand it to
 * everybody else. The browser puts the same values back into the pixels from
 * its own copy of the cookie, so nothing is lost by leaving them out here.
 *
 * Purchase confirmation pages are the exception. They are excluded from caching
 * by every cache plugin, and their identity comes from the order key in the
 * request URL rather than from this cookie.
 */
function pys_can_read_visitor_cookie_data() {

	if ( pys_advanced_form_data_cookie_disabled() ) {
		return false;
	}

	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}

	if ( ! pys_html_render_context() ) {
		return true;
	}

	if ( isPurchaseConfirmationPage() ) {
		return true;
	}

	return (bool) apply_filters( 'pys_render_visitor_pii_in_html', false );

}

/**
 * True when the pys_advanced_form_data cookie must not be written or read.
 *
 * The admin toggle is checked first; the two developer filters shipped before
 * that toggle existed, so sites relying on either keep working unchanged.
 */
function pys_advanced_form_data_cookie_disabled() {

	if ( ! PYS()->getOption( 'enable_advanced_form_data_cookie', true ) ) {
		return true;
	}

	return (bool) apply_filters( 'pys_disable_advanced_form_data_cookie', false )
	       || (bool) apply_filters( 'pys_disable_advance_data_cookie', false );

}

/**
 * Lifetime of the pys_advanced_form_data cookie, in days.
 *
 * Attribution windows are measured in days and weeks. This cookie used to be
 * written with a 2038 expiry, which kept a visitor's email on the device far
 * longer than anything could use it. Sites with a long sales cycle can raise
 * it through the filter.
 */
function pys_advanced_form_data_cookie_days() {

	$days = (int) apply_filters( 'pys_advanced_form_data_cookie_days', 30 );

	return $days > 0 ? $days : 30;

}

/**
 * Write the pys_advanced_form_data cookie with the transport flags it needs.
 *
 * HttpOnly is deliberately absent: the browser-side pixel code has to read this
 * value to hand it to Meta's and TikTok's pixels, which hash it themselves.
 * Secure is derived from pys_is_request_secure() rather than is_ssl() so that
 * sites behind an SSL-terminating proxy still get the flag.
 *
 * @param array $userData
 */
function pys_set_advanced_form_data_cookie( $userData ) {

	// Until the browser reports the domain it accepts, the scope below would be
	// host-only while the front end writes the same name on the site domain.
	// Both copies then coexist, and PHP and js-cookie alike read the older one,
	// so fresh values get written but are never read back.
	// The same gate holds the write back when the site has switched every PYS
	// cookie off, or the headers are gone.
	if ( ! pys_may_write_cookies() ) {
		return;
	}

	$domain = pys_cookie_domain();
	$secure = pys_is_request_secure();

	setcookie( 'pys_advanced_form_data', wp_json_encode( $userData ), array(
		'expires'  => time() + pys_advanced_form_data_cookie_days() * DAY_IN_SECONDS,
		'path'     => '/',
		'domain'   => $domain,
		'secure'   => $secure,
		'samesite' => 'Lax',
	) );

	// Retire a host-only copy an earlier request left behind, for the same
	// reason. No 'domain' key is what makes this target the host-only cookie.
	if ( '' !== $domain ) {
		setcookie( 'pys_advanced_form_data', '', array(
			'expires'  => time() - YEAR_IN_SECONDS,
			'path'     => '/',
			'secure'   => $secure,
			'samesite' => 'Lax',
		) );
	}

}

/**
 * Suggested privacy-policy wording for the data this plugin keeps on the
 * visitor's device, so site owners can declare the advanced matching cookie
 * instead of having to discover it.
 */
function pys_add_privacy_policy_content() {

	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = '<p class="privacy-policy-tutorial">'
	           . __( 'Suggested text for sites using PixelYourSite advanced matching:', 'pys' )
	           . '</p><p>'
	           . sprintf(
		/* translators: 1: cookie name, 2: number of days the cookie is kept */
		           __( 'When you enter your email address, phone number or name into a form on this site, we store those values in a cookie named %1$s on your browser, for up to %2$d days. We use them to recognise you when you return, so that conversions can be attributed correctly by our advertising providers. The cookie is set on this site\'s own domain. You can remove it at any time by clearing your browser cookies.', 'pys' ),
		           '<code>pys_advanced_form_data</code>',
		           pys_advanced_form_data_cookie_days()
	           )
	           . '</p>';

	wp_add_privacy_policy_content( 'PixelYourSite', $content );

}

/**
 * Get persistence user data
 * @param $em
 * @param $fn
 * @param $ln
 * @param $tel
 * @return array
 */
function get_persistence_user_data( $em, $fn, $ln, $tel ) {

	if ( pys_can_read_visitor_cookie_data() ) {
		if ( isset( $_COOKIE[ "pys_advanced_form_data" ] ) ) {
			$userData = json_decode( stripslashes( $_COOKIE[ "pys_advanced_form_data" ] ), true );
			$data_persistence = PYS()->getOption( 'data_persistency' );

			if ( isset( $userData[ "email" ] ) && $userData[ "email" ] != "" && ( $data_persistence == 'keep_data' || empty( $em ) ) ) {
				$em = $userData[ "email" ];
			}
			if ( isset( $userData[ "phone" ] ) && $userData[ "phone" ] != "" && ( $data_persistence == 'keep_data' || empty( $tel ) ) ) {
				$tel = $userData[ "phone" ];
			}
			if ( isset( $userData[ "first_name" ] ) && $userData[ "first_name" ] != "" && ( $data_persistence == 'keep_data' || empty( $fn ) ) ) {
				$fn = $userData[ "first_name" ];
			}
			if ( isset( $userData[ "last_name" ] ) && $userData[ "last_name" ] != "" && ( $data_persistence == 'keep_data' || empty( $ln ) ) ) {
				$ln = $userData[ "last_name" ];
			}
		}
	}

	return array(
		'em'  => $em,
		'fn'  => $fn,
		'ln'  => $ln,
		'tel' => $tel
	);
}

function getAllMetaEventParamName(){
    $metaEventParamName = array(
        'event_url'=>'Event URL',
        'landing_page'=>'Landing Page URL',
        'post_id'=>'Post ID',
        'post_title'=>'Post Title',
        'post_type'=>'Post Type',
        'page_title' => 'Page Title',
        'content_name'=>'Content Name',
        'content_type'=>'Content Type',
        'categories'=>'Categories',
        'category_name'=>'Category Name',
        'tags'=>'Tags',
        'user_role'=>'User Role',
        'plugin'=>'Plugin',
    );

    return $metaEventParamName;
}


function get_aw_feed_country_codes() {
    $country = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo - Brazzaville',
        'CD' => 'Congo - Kinshasa',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GR' => 'Greece',
        'GD' => 'Grenada',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar (Burma)',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'KP' => 'North Korea',
        'MK' => 'North Macedonia',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe'
    ];

    $result = array_map(
        function($name, $code) {
            return $name . " ($code)";
        },
        $country,
        array_keys($country)
    );
    return array_combine(array_keys($country), $result);
}

function get_aw_feed_language_codes() {
    $language = [
        'AB' => 'Abkhaz',
        'AA' => 'Afar',
        'AF' => 'Afrikaans',
        'AK' => 'Akan',
        'SQ' => 'Albanian',
        'AM' => 'Amharic',
        'AR' => 'Arabic',
        'AN' => 'Aragonese',
        'HY' => 'Armenian',
        'AS' => 'Assamese',
        'AV' => 'Avaric',
        'AE' => 'Avestan',
        'AY' => 'Aymara',
        'AZ' => 'Azerbaijani',
        'BM' => 'Bambara',
        'BA' => 'Bashkir',
        'EU' => 'Basque',
        'BE' => 'Belarusian',
        'BN' => 'Bengali',
        'BH' => 'Bihari',
        'BI' => 'Bislama',
        'BS' => 'Bosnian',
        'BR' => 'Breton',
        'BG' => 'Bulgarian',
        'MY' => 'Burmese',
        'CA' => 'Catalan',
        'CH' => 'Chamorro',
        'CE' => 'Chechen',
        'NY' => 'Chichewa',
        'ZH' => 'Chinese',
        'CV' => 'Chuvash',
        'KW' => 'Cornish',
        'CO' => 'Corsican',
        'CR' => 'Cree',
        'HR' => 'Croatian',
        'CS' => 'Czech',
        'DA' => 'Danish',
        'DV' => 'Divehi',
        'NL' => 'Dutch',
        'DZ' => 'Dzongkha',
        'EN' => 'English',
        'EO' => 'Esperanto',
        'ET' => 'Estonian',
        'EE' => 'Ewe',
        'FO' => 'Faroese',
        'FJ' => 'Fijian',
        'FI' => 'Finnish',
        'FR' => 'French',
        'FF' => 'Fula',
        'GL' => 'Galician',
        'KA' => 'Georgian',
        'DE' => 'German',
        'EL' => 'Greek',
        'GN' => 'Guarani',
        'GU' => 'Gujarati',
        'HT' => 'Haitian',
        'HA' => 'Hausa',
        'HE' => 'Hebrew',
        'HZ' => 'Herero',
        'HI' => 'Hindi',
        'HO' => 'Hiri Motu',
        'HU' => 'Hungarian',
        'IA' => 'Interlingua',
        'ID' => 'Indonesian',
        'IE' => 'Interlingue',
        'GA' => 'Irish',
        'IG' => 'Igbo',
        'IK' => 'Inupiaq',
        'IO' => 'Ido',
        'IS' => 'Icelandic',
        'IT' => 'Italian',
        'IU' => 'Inuktitut',
        'JA' => 'Japanese',
        'JV' => 'Javanese',
        'KL' => 'Kalaallisut',
        'KN' => 'Kannada',
        'KR' => 'Kanuri',
        'KS' => 'Kashmiri',
        'KK' => 'Kazakh',
        'KM' => 'Khmer',
        'KI' => 'Kikuyu',
        'RW' => 'Kinyarwanda',
        'KY' => 'Kyrgyz',
        'KV' => 'Komi',
        'KG' => 'Kongo',
        'KO' => 'Korean',
        'KU' => 'Kurdish',
        'KJ' => 'Kwanyama',
        'LA' => 'Latin',
        'LB' => 'Luxembourgish',
        'LG' => 'Luganda',
        'LI' => 'Limburgish',
        'LN' => 'Lingala',
        'LO' => 'Lao',
        'LT' => 'Lithuanian',
        'LU' => 'Luba-Katanga',
        'LV' => 'Latvian',
        'GV' => 'Manx',
        'MK' => 'Macedonian',
        'MG' => 'Malagasy',
        'MS' => 'Malay',
        'ML' => 'Malayalam',
        'MT' => 'Maltese',
        'MI' => 'Maori',
        'MR' => 'Marathi',
        'MH' => 'Marshallese',
        'MN' => 'Mongolian',
        'NA' => 'Nauru',
        'NV' => 'Navajo',
        'ND' => 'North Ndebele',
        'NE' => 'Nepali',
        'NG' => 'Ndonga',
        'NB' => 'Norwegian Bokmål',
        'NN' => 'Norwegian Nynorsk',
        'NO' => 'Norwegian',
        'II' => 'Nuosu',
        'NR' => 'South Ndebele',
        'OC' => 'Occitan',
        'OJ' => 'Ojibwe',
        'CU' => 'Old Church Slavonic',
        'OM' => 'Oromo',
        'OR' => 'Oriya',
        'OS' => 'Ossetian',
        'PA' => 'Punjabi',
        'PI' => 'Pali',
        'FA' => 'Persian',
        'PL' => 'Polish',
        'PS' => 'Pashto',
        'PT' => 'Portuguese',
        'QU' => 'Quechua',
        'RM' => 'Romansh',
        'RN' => 'Kirundi',
        'RO' => 'Romanian',
        'RU' => 'Russian',
        'SA' => 'Sanskrit',
        'SC' => 'Sardinian',
        'SD' => 'Sindhi',
        'SE' => 'Northern Sami',
        'SM' => 'Samoan',
        'SG' => 'Sango',
        'SR' => 'Serbian',
        'GD' => 'Scottish Gaelic',
        'SN' => 'Shona',
        'SI' => 'Sinhala',
        'SK' => 'Slovak',
        'SL' => 'Slovenian',
        'SO' => 'Somali',
        'ST' => 'Southern Sotho',
        'ES' => 'Spanish',
        'SU' => 'Sundanese',
        'SW' => 'Swahili',
        'SS' => 'Swati',
        'SV' => 'Swedish',
        'TA' => 'Tamil',
        'TE' => 'Telugu',
        'TG' => 'Tajik',
        'TH' => 'Thai',
        'TI' => 'Tigrinya',
        'BO' => 'Tibetan',
        'TK' => 'Turkmen',
        'TL' => 'Tagalog',
        'TN' => 'Tswana',
        'TO' => 'Tongan',
        'TR' => 'Turkish',
        'TS' => 'Tsonga',
        'TT' => 'Tatar',
        'TW' => 'Twi',
        'TY' => 'Tahitian',
        'UG' => 'Uyghur',
        'UK' => 'Ukrainian',
        'UR' => 'Urdu',
        'UZ' => 'Uzbek',
        'VE' => 'Venda',
        'VI' => 'Vietnamese',
        'VO' => 'Volapük',
        'WA' => 'Walloon',
        'CY' => 'Welsh',
        'WO' => 'Wolof',
        'XH' => 'Xhosa',
        'YI' => 'Yiddish',
        'YO' => 'Yoruba',
        'ZA' => 'Zhuang',
        'ZU' => 'Zulu'
    ];

    $result = array_map(
        function($name, $code) {
            return $name . " ($code)";
        },
        $language,
        array_keys($language)
    );
    return array_combine(array_keys($language), $result);
}

function pys_get_option( $option, $default = false ) {
    global $wpdb;

    $table = $wpdb->prefix . 'pys_options';

    // We are trying to get from our table
    $value = $wpdb->get_var( $wpdb->prepare(
        "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1",
        $option
    ) );

    if ( $value !== null ) {
        return maybe_unserialize( $value );
    }

    // If it is not in your table, try wp_options
    $value = get_option( $option, $default );

    return $value;
}
function pys_update_option( $option, $value ) {
    global $wpdb;

    $table = $wpdb->prefix . 'pys_options';

    $data = [
        'option_name'  => $option,
        'option_value' => maybe_serialize( $value ),
    ];
    $formats = [ '%s', '%s' ];

    // Update or insert
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE option_name = %s",
        $option
    ) );

    if ( $existing ) {
        $wpdb->update( $table, $data, [ 'option_name' => $option ], $formats, [ '%s' ] );
    } else {
        $wpdb->insert( $table, $data, $formats );
    }

    return true;
}

/**
 * Per-site secret that keys the visitor id.
 *
 * Standalone option on purpose: the site-profile exporter walks the PYS
 * options array only, so this never travels with a profile. Deleting the
 * option re-keys every cookie-less visitor; cookie holders keep their value.
 *
 * @return string 64 hex chars
 */
function pys_pbid_secret() {
    static $secret = null;

    if ( is_string( $secret ) ) {
        return $secret;
    }

    $stored = get_option( 'pys_pbid_secret', '' );

    if ( is_string( $stored ) && strlen( $stored ) === 64 ) {
        $secret = $stored;
        return $secret;
    }

    // First use. WordPress' add_option() upserts, so two racing first requests
    // would each keep their own secret for the duration of the request and hand
    // out ids that never recur. INSERT IGNORE on the unique option_name lets
    // exactly one of them win, with no lock to time out or be unsupported; the
    // value is then re-read straight from the table, past this request's
    // option cache, so the loser adopts the winner's secret.
    global $wpdb;

    $wpdb->query( $wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
        'pys_pbid_secret',
        bin2hex( random_bytes( 32 ) ),
        'yes'
    ) );

    // The row was written behind the options API's back.
    wp_cache_delete( 'pys_pbid_secret', 'options' );
    wp_cache_delete( 'alloptions', 'options' );
    wp_cache_delete( 'notoptions', 'options' );

    $stored = $wpdb->get_var( $wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
        'pys_pbid_secret'
    ) );

    if ( ! is_string( $stored ) || strlen( $stored ) !== 64 ) {
        // A malformed row was already there, which INSERT IGNORE leaves alone.
        $stored = bin2hex( random_bytes( 32 ) );
        update_option( 'pys_pbid_secret', $stored, 'yes' );
    }

    $secret = $stored;
    return $secret;
}

/**
 * Deterministic browser id for a visitor who carries no cookie.
 *
 * The same request signals give the same id, so page render, the
 * dynamic-options endpoint, pys_get_pbid and server events agree on the very
 * first page view, with no storage and no rotation. Keyed with the site secret
 * so it cannot be derived from public data and differs per site.
 *
 * Two people who share an address AND an identical browser build and language
 * share an id; that is the price of an id that exists before any cookie does.
 *
 * @param array|null $server Request environment; defaults to $_SERVER.
 * @return string 64 hex chars
 */
function pys_visitor_key( $server = null ) {
    if ( null === $server ) {
        $server = $_SERVER;
    }

    $parts = array(
        'ip'       => pys_resolve_client_ip( $server, false ),
        'ua'       => isset( $server['HTTP_USER_AGENT'] ) ? (string) $server['HTTP_USER_AGENT'] : '',
        'language' => isset( $server['HTTP_ACCEPT_LANGUAGE'] ) ? (string) $server['HTTP_ACCEPT_LANGUAGE'] : '',
    );

    /**
     * Filters the request signals that make up the visitor id.
     *
     * @param array $parts  Ordered name => value map; values are joined with '|'.
     * @param array $server Request environment the parts were read from.
     */
    $parts = apply_filters( 'pys_pbid_fingerprint_parts', $parts, $server );

    return hash_hmac( 'sha256', implode( '|', array_map( 'strval', $parts ) ), pys_pbid_secret() );
}


/**
 * An external id in the one shape both channels send identically.
 *
 * fbevents.js hands every `ud[...]` value to its identity plugin, which passes a
 * 32- or 64-hex value through lowercased and SHA-256s anything else. Meta also
 * lists external_id as "hashing recommended", and Pinterest's API spec types the
 * field as hashes outright. So a hash is the only value the browser Pixel and the
 * server send as the same string -- a raw account id would reach Meta as "7" from
 * the server and as sha256("7") from the Pixel.
 *
 * Every browser id PYS has ever issued is already 64 hex characters (the keyed
 * visitor key, and the random mint before it), so this leaves those untouched and
 * only hashes the values that are not hashes yet.
 *
 * @param string $value Id in any shape.
 * @return string Hash, or '' when there was nothing to name.
 */
function pys_hash_external_id( $value ) {
    $value = trim( (string) $value );

    if ( '' === $value ) {
        return '';
    }

    if ( preg_match( '/^[A-Fa-f0-9]{32}$|^[A-Fa-f0-9]{64}$/', $value ) ) {
        return strtolower( $value );
    }

    return hash( 'sha256', strtolower( $value ) );
}

/**
 * Every id that names the current visitor, browser id first.
 *
 * Meta's Conversions API types `external_id` as "string or list<string>" and
 * states "You can send one or more external IDs for a given event"; Pinterest's
 * API spec types it as a list outright. Sending the whole set instead of a
 * single value lets a logged-in visitor's browser id and account id be read as
 * one person, and keeps the id the pixel already sent in the set after login
 * swapped the id the server resolves.
 *
 * The browser id stays first so the single value the pixel puts in fbq('init')
 * is always a member of the set the server sends -- the two channels must not
 * disagree about who this is.
 *
 * @param int|null $user_id Account to name; defaults to the current user, 0 skips it.
 * @return string[] Unique non-empty ids, or none when external ids are switched off.
 */
function pys_get_external_ids( $user_id = null ) {
    if ( ! EventsManager::isTrackExternalId() ) {
        return array();
    }

    $ids = array();

    // The id the pixel is using on this page right now.
    if ( ! empty( $_COOKIE['pbid'] ) ) {
        $ids[] = sanitize_text_field( wp_unslash( $_COOKIE['pbid'] ) );
    }

    // Whatever this request resolved: the stored id of a logged-in visitor, or
    // the deterministic key minted before any cookie exists.
    $ids[] = PYS()->get_pbid();

    if ( null === $user_id ) {
        $user_id = get_current_user_id();
    }

    $user_id = (int) $user_id;

    if ( $user_id > 0 ) {
        /**
         * Filters the account id before it is hashed.
         *
         * The plain WordPress user id by default, so the hash lands on the same
         * value as one computed from that id elsewhere -- a CRM or offline
         * conversion upload. Namespace it here when one pixel serves several
         * sites, where account 5 is a different person on each of them.
         * Whatever is returned is hashed unless it already is a hash.
         *
         * @param string $value   Id before hashing.
         * @param int    $user_id WordPress user the value was built from.
         */
        $ids[] = pys_hash_external_id(
            apply_filters( 'pys_external_id_user_value', (string) $user_id, $user_id )
        );
    }

    // Every other id this account has been seen under, after the live ones.
    if ( $user_id > 0 ) {
        foreach ( pys_get_user_stored_external_ids( $user_id ) as $stored ) {
            $ids[] = $stored;
        }
    }

    $ids = array_filter( array_map( 'strval', $ids ), 'strlen' );
    $ids = array_values( array_unique( $ids ) );

    /**
     * Filters the whole set of external ids for one event.
     *
     * @param string[] $ids     Ordered, unique, browser id first.
     * @param int      $user_id WordPress user the set was built for, 0 when anonymous.
     */
    return apply_filters( 'pys_external_ids', $ids, $user_id );
}

/**
 * The ids stored on an account in `pys_external_ids`, hashed, newest first.
 *
 * @param int $user_id
 * @return string[] Hashed ids; empty for a guest or a corrupt list.
 */
function pys_get_user_stored_external_ids( $user_id ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return array();
    }

    $list = get_user_meta( $user_id, 'pys_external_ids', true );

    if ( ! is_array( $list ) ) {
        return array();
    }

    $out = array();

    foreach ( $list as $raw ) {
        if ( is_scalar( $raw ) ) {
            $hashed = pys_hash_external_id( $raw );

            if ( '' !== $hashed ) {
                $out[] = $hashed;
            }
        }
    }

    return $out;
}

/**
 * Remember an id the customer was seen under, on their account.
 *
 * Raw ids, distinct, newest first, at most 20 (the oldest
 * drop off). No visitor-key check here: the browser cookie is minted from the
 * visitor key, so the browser's own id often equals it. The CALLER decides
 * whether the id is the browser's (login: the pbid cookie; checkout: the order
 * id only when it equals the request's pbid cookie) and never passes the
 * server's no-cookie fallback key.
 *
 * @param int    $user_id
 * @param string $id Raw id, as the browser cookie or the order holds it.
 * @return void
 */
function pys_append_user_external_id( $user_id, $id ) {
    $user_id = (int) $user_id;
    $id      = is_scalar( $id ) ? trim( (string) $id ) : '';

    if ( $user_id <= 0 || '' === $id || strlen( $id ) > 191 ) {
        return;
    }

    $list = get_user_meta( $user_id, 'pys_external_ids', true );
    $list = is_array( $list ) ? array_values( array_filter( $list, 'is_string' ) ) : array();

    if ( isset( $list[0] ) && $list[0] === $id ) {
        return;
    }

    $list = array_values( array_diff( $list, array( $id ) ) );
    array_unshift( $list, $id );

    update_user_meta( $user_id, 'pys_external_ids', array_slice( $list, 0, 20 ) );
}

/**
 * External ids for an order, independent of the request that reports it.
 *
 * An order is reported from the thank-you page, but also from a webhook, a cron
 * retry or wp-admin, where the current request belongs to nobody in particular
 * -- so the ids come from the order itself: the browser id persisted when the
 * order was placed, plus the account that owns it. pys_get_external_ids() must
 * not be used here, it would name whoever is making the current request.
 *
 * @param string   $stored_id Browser id saved on the order; may be empty.
 * @param int|null $user_id   Account that owns the order; 0 for a guest.
 * @return string[] Unique non-empty ids, or none when external ids are switched off.
 */
function pys_get_order_external_ids( $stored_id, $user_id = 0 ) {
    if ( ! EventsManager::isTrackExternalId() ) {
        return array();
    }

    $ids = array( $stored_id );

    $user_id = (int) $user_id;

    if ( $user_id > 0 ) {
        // The account's canonical browser id, as stored (raw, like the order's).
        $canonical = get_user_meta( $user_id, 'external_id', true );
        $ids[]     = is_scalar( $canonical ) ? (string) $canonical : '';

        /** This filter is documented in pys_get_external_ids(). */
        $ids[] = pys_hash_external_id(
            apply_filters( 'pys_external_id_user_value', (string) $user_id, $user_id )
        );

        // Then every other id the customer has been seen under, hashed.
        foreach ( pys_get_user_stored_external_ids( $user_id ) as $stored ) {
            $ids[] = $stored;
        }
    }

    $ids = array_filter( array_map( 'strval', $ids ), 'strlen' );
    $ids = array_values( array_unique( $ids ) );
    $ids = array_slice( $ids, 0, 20 );

    /** This filter is documented in pys_get_external_ids(). */
    return apply_filters( 'pys_external_ids', $ids, $user_id );
}

/**
 * Hosts this site is served on: the home_url() and site_url() hosts plus the
 * language domains/subdomains of Polylang and WPML.
 *
 * @return string[] Lowercased, unique, non-empty.
 */
function pys_site_hosts() {
    $hosts = array();

    foreach ( array( home_url(), site_url() ) as $url ) {
        $host = wp_parse_url( (string) $url, PHP_URL_HOST );
        if ( is_string( $host ) ) {
            $hosts[] = $host;
        }
    }

    // Polylang: "a different domain per language" and subdomain modes.
    try {
        $polylang = isset( $GLOBALS['polylang'] ) ? $GLOBALS['polylang'] : null;
        if ( is_object( $polylang )
            && isset( $polylang->links_model )
            && is_object( $polylang->links_model )
            && method_exists( $polylang->links_model, 'get_hosts' )
        ) {
            $hosts = array_merge( $hosts, array_values( (array) $polylang->links_model->get_hosts() ) );
        }
    } catch ( \Throwable $e ) {
        // Language hosts are optional; fall back to the site hosts.
    }

    // WPML: "a different domain per language" (negotiation type 2).
    try {
        if ( defined( 'ICL_SITEPRESS_VERSION' )
            && 2 === (int) apply_filters( 'wpml_setting', 0, 'language_negotiation_type' )
        ) {
            $domains = apply_filters( 'wpml_setting', array(), 'language_domains' );
            foreach ( is_array( $domains ) ? $domains : array() as $domain ) {
                if ( ! is_string( $domain ) || '' === trim( $domain ) ) {
                    continue;
                }
                $domain = trim( $domain );
                if ( false === strpos( $domain, '://' ) ) {
                    $domain = 'http://' . $domain;
                }
                $host = wp_parse_url( $domain, PHP_URL_HOST );
                if ( is_string( $host ) ) {
                    $hosts[] = $host;
                }
            }
        }
    } catch ( \Throwable $e ) {
        // Language hosts are optional; fall back to the site hosts.
    }

    /**
     * Filters the hosts PixelYourSite treats as this site (REST same-origin
     * check, MCP page scanner). Lets sites that serve pages on extra domains
     * (TranslatePress, domain mapping) add them.
     *
     * @param string[] $hosts Hosts without scheme or port, e.g. `www.example.se`.
     */
    $hosts = apply_filters( 'pys_site_hosts', $hosts );

    $clean = array();
    foreach ( is_array( $hosts ) ? $hosts : array() as $host ) {
        if ( ! is_string( $host ) ) {
            continue;
        }
        $host = strtolower( trim( $host ) );
        if ( '' !== $host ) {
            $clean[] = $host;
        }
    }

    return array_values( array_unique( $clean ) );
}

/**
 * Whether a REST request comes from a page of this site: the Origin host (or,
 * when Origin is absent, the Referer host) is one of pys_site_hosts().
 *
 * @param \WP_REST_Request $request
 * @return bool
 */
function pys_rest_is_same_origin( $request ) {
    $origin  = $request->get_header( 'origin' );
    $referer = $request->get_header( 'referer' );

    if ( ! empty( $origin ) ) {
        $source = $origin;
    } elseif ( ! empty( $referer ) ) {
        $source = $referer;
    } else {
        return false;
    }

    $host = wp_parse_url( $source, PHP_URL_HOST );
    if ( ! is_string( $host ) || '' === $host ) {
        return false;
    }

    return in_array( strtolower( $host ), pys_site_hosts(), true );
}
