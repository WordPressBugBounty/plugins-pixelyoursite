<?php

namespace PixelYourSite;

use PYS_PRO_GLOBAL\FacebookAds\ETLDPlus1Resolver;
use PYS_PRO_GLOBAL\FacebookAds\ParamBuilder;
use PYS_PRO_GLOBAL\FacebookAds\PlainDataObject;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Feeds Meta's capi-param-builder the cookie domain we resolved ourselves.
 *
 * The library's own eTLD+1 fallback is naive, so it is never allowed to
 * guess: pys_cookie_domain() is the single source of truth for the library,
 * for our setcookie() calls and for the JS side alike, keeping the
 * subdomain_index the library mints consistent with the domain the cookie
 * actually lives on.
 */
class PysEtldResolver implements ETLDPlus1Resolver {

    /**
     * @param string $domain Host the library is asking about.
     * @return string Registrable domain, without a leading dot.
     */
    public function resolveETLDPlus1( $domain ) {
        $resolved = ltrim( (string) pys_cookie_domain(), '.' );

        // Host-only scoping: the library still needs *a* domain to derive
        // subdomain_index from, and the host itself is the honest answer.
        return '' !== $resolved ? $resolved : (string) $domain;
    }
}

/**
 * Our wrapper around Meta's capi-param-builder.
 *
 * The library produces `_fbp` and `_fbc`; everything on either side of that is
 * ours. It never reads the request — we hand it a PlainDataObject built from
 * our own resolvers — and it never writes a cookie: processRequest() returns
 * a list and the caller decides.
 *
 * It offered four things our PHP did not: millisecond creation time, a derived
 * subdomain_index, fbclid recovery from the referrer, and the 8-character
 * appendix. The first three now happen off-flag — functions-fb-cookies.php,
 * pys_cookie_subdomain_index_for_request(), pys_fbclid_from_referrer() — and
 * the appendix carries no site or integration identity (AppendixProvider packs
 * a format version, a language index, an is-new byte and the library's semver,
 * while partner_agent: "dvpixelyoursite" already goes out on every event). So
 * this stays vendored and off.
 *
 * What we deliberately do NOT take from it:
 *
 *   - getClientIpAddress(), getEventSourceUrl(), getReferrerUrl(). It appends
 *     the appendix to those too, so '203.0.113.10.AQEAAQMB' is not an IP.
 *     Ours also detect the real IP behind Cloudflare and Akamai, strip URL
 *     parameters, and honour the order-confirmation override.
 *   - getNormalizedAndHashedPII(). UserData::normalize() in the Business SDK
 *     already normalises and hashes every field, idempotently.
 *
 * Behind the `pys_use_capi_param_builder` filter, off by default.
 */
class ParamBuilderAdapter {

    /** @var ParamBuilderAdapter|null */
    private static $instance = null;

    /** @var ParamBuilder|null */
    private $builder = null;

    /** @var bool */
    private $ran = false;

    /**
     * Values repaired on the way in, keyed by cookie name.
     *
     * @var array<string, string>
     */
    private $repaired = array();

    /** @var bool Guards against writing the same cookies once per event. */
    private $flushed = false;

    /**
     * Whether to use the library at all.
     *
     * A filter rather than a stored option: no settings UI yet, and the
     * rollout plan is to enable it per site before it becomes a default. Add
     * to `plugins_loaded` or earlier.
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) apply_filters( 'pys_use_capi_param_builder', false );
    }

    /**
     * Memoised per request. The values must describe the visitor, so the object
     * is built once, in the visitor's own request, and reused.
     *
     * @return ParamBuilderAdapter
     */
    public static function for_request() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Run the library once, over a context we assembled.
     */
    private function run() {
        if ( $this->ran ) {
            return;
        }
        $this->ran = true;

        if ( ! self::is_enabled() ) {
            return;
        }

        $this->builder = new ParamBuilder( new PysEtldResolver() );
        $this->builder->processRequestFromContext( $this->build_context() );
    }

    /**
     * The request, as we see it rather than as $_SERVER sees it.
     *
     * Passing a PlainDataObject makes RequestContextAdaptor — and with it every
     * assumption the library makes about $_SERVER — unreachable. That's the
     * point: the library's IP handling knows only X-Forwarded-For and
     * REMOTE_ADDR, so left to itself it would discard our Cloudflare,
     * True-Client-IP and nginx real-IP support along with the pys_client_ip
     * filter.
     *
     * @return PlainDataObject
     */
    private function build_context() {
        $host = isset( $_SERVER['HTTP_HOST'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
            : (string) wp_parse_url( get_site_url(), PHP_URL_HOST );

        // The library never parses a query itself. It reads `fbclid` from
        // $_GET-style input and from the referrer through parse_str(), which
        // keeps the LAST of `?fbclid=A&fbclid=B` (our JS stores A) and turns
        // `?fbclid[0]=B` into an array it concatenates as "Array"
        // (capi-param-builder issue #45). So we resolve the click id with the
        // reader the main path uses — URL first, then the referrer — and hand
        // over that alone. The referrer itself is withheld: getReferrerUrl(),
        // its only other consumer, is not used.
        $fbclid = (string) pys_query_param_first(
            isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '',
            'fbclid'
        );

        if ( '' === $fbclid ) {
            $fbclid = pys_fbclid_from_referrer();
        }

        return new PlainDataObject(
            $host,
            '' !== $fbclid ? array( 'fbclid' => $fbclid ) : array(),
            $this->normalised_cookies(),
            null,
            // x_forwarded_for is deliberately null: the library would take the
            // first hop of it as the client, which is spoofable and ignores
            // every proxy header we actually trust.
            null,
            $this->client_ip(),
            rtrim( str_replace( '://', '', pys_get_request_protocol() ), ':/' ),
            isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null
        );
    }

    /**
     * Meta's cookies as they should be read, rather than as $_COOKIE presents them.
     *
     * Duplicate resolution and the seconds→milliseconds repair both live in
     * includes/functions-fb-cookies.php, off-flag, since neither needs the
     * library. ServerEventHelper::getFbp()/getFbc() applies the same rules
     * from the same place, so the two paths cannot drift. See
     * pys_fb_resolve_cookies() for the reasoning behind each rule.
     *
     * A value changed on the way in is remembered so flush_cookies() persists
     * it, rather than re-resolving it on every request forever.
     *
     * @return array
     */
    private function normalised_cookies() {
        $resolved = pys_fb_resolve_cookies(
            is_array( $_COOKIE ) ? $_COOKIE : array(),
            isset( $_SERVER['HTTP_COOKIE'] ) ? (string) $_SERVER['HTTP_COOKIE'] : ''
        );

        $this->repaired = $resolved['changed'];

        return $resolved['values'];
    }

    /**
     * The visitor's IP, from our resolver rather than the library's.
     *
     * Currently unconsumed: the library only uses it to build
     * getClientIpAddress(), which we do not take. Wired correctly anyway so
     * adopting that getter later needs no change here.
     *
     * @return string|null
     */
    private function client_ip() {
        // pys_client_ip_for_capi() already refuses anything non-routable and
        // returns the first trusted header that yields a routable address.
        $ip = pys_client_ip_for_capi();

        return '' !== $ip ? $ip : null;
    }

    /**
     * The `_fbp` for this event, or null while it cannot be persisted.
     *
     * `pys_cookie_domain_pending()` gates persistence, and for `_fbp` it is
     * also the right gate on production: the payload is a random number, a
     * browser identity we invent rather than derive. Minting one anyway would
     * send Meta an event under a browser ID stored nowhere, while the browser
     * writes its own `_fbp` moments later with a different random.
     *
     * Returning null lets the caller fall through to `$eventParams['_fbp']`,
     * the browser's value — authoritative here, since only the browser knows
     * the scope yet. `_fbc` answers the opposite way; see get_fbc().
     *
     * @return string|null
     */
    public function get_fbp() {
        // Consent first. With pys_meta_ldu_mode events reach Meta without
        // consent, while flush_cookies() writes nothing: the library has
        // already invented a value that exists in no browser, and handing it
        // out would send Meta an identity stored nowhere. The browser's real
        // cookie still arrives through ServerEventHelper::getFbp().
        if ( ! Consent()->checkConsent( 'facebook' ) ) {
            return null;
        }

        if ( pys_cookie_domain_pending() ) {
            // Not bounded — see pys_cookie_domain_pending(). Withheld, not
            // reported: only the caller knows whether the browser supplied an
            // `_fbp` of its own (fbevents.js writes one without any pys_cd),
            // so a loss is logged there, by ServerEventHelper, once every
            // fallback has had its turn. A report from here made every server
            // event on a pending site look like a loss when most were not.
            return null;
        }

        $this->run();

        return $this->builder ? $this->builder->getFbp() : null;
    }

    /**
     * The `_fbc` for this event, produced even when it cannot be persisted.
     *
     * Deliberately NOT gated on pys_cookie_domain_pending(), unlike get_fbp():
     * the gate governs writing, and `_fbc`'s payload is the `fbclid` read from
     * the URL or the referrer — derived, not invented, so no phantom identity.
     * Persistence stays deferred; only production is not.
     *
     * Withholding it would cost the landing request of every paid click, since
     * `track_cookie_for_subdomains` defaults to on and pending is therefore
     * true on a new visitor's first request — a bouncing visitor's click would
     * never be associated.
     *
     * The tradeoff: Meta may see two `_fbc` strings for one `fbclid`, ours and
     * our JS's, differing in creation time and possibly subdomain_index.
     * Whether attribution keys on the whole string or on the `fbclid` inside it
     * is undocumented. `main` did the same, with a 1970 timestamp on top.
     *
     * @return string|null
     */
    public function get_fbc() {
        // Same reason as get_fbp(): without consent nothing is persisted, and
        // the main path does not build an `_fbc` from an fbclid then either.
        if ( ! Consent()->checkConsent( 'facebook' ) ) {
            return null;
        }

        $this->run();

        return $this->builder ? $this->builder->getFbc() : null;
    }

    /**
     * The cookies the library wants written, before we override anything.
     *
     * @return array CookieSettings[]
     */
    public function get_cookies_to_set() {
        $this->run();

        return $this->builder ? $this->builder->getCookiesToSet() : array();
    }

    /**
     * Persist whatever the library decided needs writing.
     *
     * Consent-gated, and it overrides two of the library's own choices: the
     * domain comes from pys_cookie_domain() so PHP and the front end cannot
     * disagree, and the lifetime comes from pys_fb_cookie_lifetime() (90 days,
     * `pys_fb_cookie_lifetime_days` filter) rather than the library's own max_age.
     */
    public function flush_cookies() {
        if ( ! Consent()->checkConsent( 'facebook' ) ) {
            return;
        }

        // Headers gone, every PYS cookie switched off by the site, or the
        // browser hasn't told us the registrable domain yet and this site
        // wants one — any scope chosen now would be contradicted next
        // request; our script resolves it and writes the cookies itself.
        //
        // Checked before the write-once latch below on purpose: sharing one
        // latch would mean a deferred first call consumed the single write.
        if ( ! pys_may_write_cookies() ) {
            return;
        }

        // Reachable both from boot() on 'init' and from
        // mapEventToServerEvent() per queued event, so a page firing several
        // server events would otherwise emit the same Set-Cookie headers
        // several times over.
        if ( $this->flushed ) {
            return;
        }
        $this->flushed = true;

        $this->run();

        if ( ! $this->builder ) {
            return;
        }

        $max_age = pys_fb_cookie_lifetime();
        $written = array();

        foreach ( $this->builder->getCookiesToSet() as $cookie ) {
            // The library's own max_age is ignored for the same reason
            // the lifetime is: one lifetime answers for `_fbp` and `_fbc`
            // whichever writer produced them.
            setcookie( $cookie->name, $cookie->value, time() + $max_age, '/', pys_cookie_domain() );

            // Readable within this same request, so anything running later sees
            // the value instead of minting a second one.
            $_COOKIE[ $cookie->name ] = $cookie->value;
            $written[ $cookie->name ] = true;
        }

        // A value repaired on the way in (see normalised_cookies) is usually
        // persisted by the loop above. Not when the value already carried an
        // appendix, though — then the repair would be redone every request.
        // Persist those explicitly.
        foreach ( $this->repaired as $name => $value ) {
            if ( isset( $written[ $name ] ) ) {
                continue;
            }

            setcookie( $name, $value, time() + $max_age, '/', pys_cookie_domain() );
            $_COOKIE[ $name ] = $value;
        }
    }

    /**
     * Drop the trailing appendix from a value, for logs and the debug console.
     *
     * The token is Meta's, not ours, and printing it in our own diagnostics only
     * makes values harder to compare with what the browser holds.
     *
     * @param string $value
     * @return string
     */
    public static function strip_appendix( $value ) {
        $value = (string) $value;

        if ( pys_fb_has_appendix( $value ) ) {
            return substr( $value, 0, strrpos( $value, '.' ) );
        }

        return $value;
    }

    /**
     * Run early, so the cookies exist before the pixel script does.
     *
     * Meta asked for the param builder to be initialised first: when `_fbp` is
     * already present, fbevents.js reads it instead of minting its own, which is
     * how the appendix survives. Late enough for options and consent to be
     * readable, early enough that headers are not sent.
     *
     * On a fully page-cached site PHP does not run for the cached HTML at all,
     * so there fbevents.js will always be the one to mint `_fbp`. No integration
     * can change that.
     */
    public static function boot() {
        if ( ! self::is_enabled() ) {
            return;
        }

        if ( ! Facebook()->configured() ) {
            return;
        }

        self::for_request()->flush_cookies();
    }
}
