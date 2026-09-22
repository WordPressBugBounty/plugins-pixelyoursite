<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Name of the cookie our front-end script uses to hand PHP the registrable
 * domain it computed with tldjs. See pys_cookie_domain() for why we ask the
 * browser instead of working it out here.
 */
const PYS_REPORTED_DOMAIN_COOKIE = 'pys_cd';

/**
 * Reasons pys_cookie_domain_pending_reason() can give. See it for what they
 * mean and for why nothing is allowed to branch on them.
 */
const PYS_COOKIE_DOMAIN_PENDING_OPTIONS = 'options-not-loaded';
const PYS_COOKIE_DOMAIN_PENDING_BROWSER = 'no-browser-report';

/**
 * Normalise a host or domain into a bare, comparable form.
 *
 * Strips a scheme, a leading dot, IPv6 brackets and a trailing port, then
 * lowercases. Returns '' for anything unusable.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $value Host, domain or URL authority.
 * @return string Bare lowercase host, or '' .
 */
function pys_cookie_domain_normalize_host( $value ) {
    if ( ! is_string( $value ) ) {
        return '';
    }

    $value = trim( $value );
    if ( '' === $value ) {
        return '';
    }

    // Drop a scheme if one was passed in.
    $scheme_pos = strpos( $value, '://' );
    if ( false !== $scheme_pos ) {
        $value = substr( $value, $scheme_pos + 3 );
    }

    // Keep only the authority.
    $value = strtok( $value, '/' );
    if ( false === $value ) {
        return '';
    }

    // A bracketed IPv6 literal, with or without a port: [::1] / [::1]:8080 .
    if ( isset( $value[0] ) && '[' === $value[0] ) {
        $close = strpos( $value, ']' );
        if ( false === $close ) {
            return '';
        }
        return strtolower( substr( $value, 1, $close - 1 ) );
    }

    // A trailing :port — but exactly one colon, otherwise this is a bare IPv6
    // literal such as 2606:4700::1111 and the colons are part of the address.
    if ( 1 === substr_count( $value, ':' ) ) {
        $value = substr( $value, 0, strrpos( $value, ':' ) );
    }

    return strtolower( trim( $value, '.' ) );
}

/**
 * True when $candidate is a domain that legitimately covers $host.
 *
 * "Covers" means the candidate is $host itself or one of its parent domains,
 * and carries at least two labels so a bare TLD can never be returned.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $candidate Domain to test.
 * @param string $host      Host the cookie will be set from.
 * @return bool
 */
function pys_cookie_domain_covers_host( $candidate, $host ) {
    $candidate = pys_cookie_domain_normalize_host( $candidate );
    $host      = pys_cookie_domain_normalize_host( $host );

    if ( '' === $candidate || '' === $host ) {
        return false;
    }

    // An IP address is never a cookie domain.
    if ( false !== filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
        return false;
    }

    // At minimum "example.com" — refuse a single label such as "uk".
    if ( substr_count( $candidate, '.' ) < 1 ) {
        return false;
    }

    if ( $candidate === $host ) {
        return true;
    }

    // Parent domain: host must end with ".candidate".
    $suffix = '.' . $candidate;
    return substr( $host, - strlen( $suffix ) ) === $suffix;
}

/**
 * Decide the cookie domain from explicit inputs.
 *
 * The goal is parity with dist/scripts/public.js, not "compute the eTLD+1 in
 * PHP": the front end resolves the registrable domain through a real Public
 * Suffix List when track_cookie_for_subdomains is on, and writes host-only
 * otherwise. A parent domain built from the last two labels yields a public
 * suffix on hosts like shop.example.co.uk, which browsers reject.
 *
 * Deliberately not sources: the subdomain_index inside an existing _fbp (older
 * versions hardcoded 1, so it would reintroduce that bug), and WordPress's
 * COOKIE_DOMAIN (it scopes auth cookies, is non-empty on every multisite by
 * default, and on a domain-mapped subsite names a domain that does not even
 * cover the subsite host).
 *
 * The floor is '' (host-only): never rejected, so an unknown domain costs
 * cross-subdomain sharing but never the cookie itself.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $host             Current host.
 * @param bool   $track_subdomains Value of the track_cookie_for_subdomains option.
 * @param string $reported         Registrable domain the browser verified, if any.
 * @param string $configured       Domain from the pys_cookie_domain_override
 *                                 filter; stands in until the browser answers.
 * @return string Domain to pass to setcookie(), '' for host-only.
 */
function pys_cookie_domain_resolve( $host, $track_subdomains, $reported = '', $configured = '' ) {
    $host = pys_cookie_domain_normalize_host( $host );
    if ( '' === $host ) {
        return '';
    }

    // Meaningless for an IP address or single-label host ("localhost");
    // browsers drop both, and nothing below — filter included — can override it.
    if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
        return '';
    }
    if ( false === strpos( $host, '.' ) ) {
        return '';
    }

    // Parity with public.js: option off means the browser writes host-only
    // cookies, so PHP does too — this also discards a stale pys_cd.
    if ( ! $track_subdomains ) {
        return '';
    }

    // The tldjs answer, reported via cookie and therefore visitor-supplied:
    // validated (must cover this host) rather than trusted. Worst case a
    // forged value scopes only that visitor's own cookie differently.
    //
    // Accepted gap: a forged public suffix of this host ("co.uk" on
    // shop.example.co.uk) passes the structural check and the browser then
    // rejects the write — closing it needs a PSL in PHP, the duplication this
    // design avoids.
    if ( pys_cookie_domain_covers_host( $reported, $host ) ) {
        return '.' . pys_cookie_domain_normalize_host( $reported );
    }

    // No browser answer yet (first page load, or front end never runs). A
    // filter may stand in, under the same coverage check.
    //
    // Ranked below both the browser's answer and the option on purpose: a
    // scope the front end won't follow is worse than none, since a cookie
    // written at one scope can't be refreshed or deleted at another.
    if ( pys_cookie_domain_covers_host( $configured, $host ) ) {
        return '.' . pys_cookie_domain_normalize_host( $configured );
    }

    return '';
}

/**
 * The track_cookie_for_subdomains option, or null while it cannot be read.
 *
 * `null` matters: it is falsy, so a caller treating it as bool concludes
 * "host-only" — a decision, not an absence of one. Options are readable only
 * once locateOptions() has run (PYS::init(), 'init' priority 9);
 * `did_action( 'init' )` is already 1 by priority 0-8 so it can't detect that
 * window, Settings::optionsLocated() can.
 *
 * @return bool|null True/false once the option is readable, null before that.
 */
function pys_cookie_domain_track_subdomains() {
    $pys = PYS();

    // method_exists guards a version skew between the two plugins: this file
    // ships identically in both, and whichever loads first defines it.
    if ( ! $pys || ! method_exists( $pys, 'optionsLocated' ) || ! $pys->optionsLocated() ) {
        return null;
    }

    return (bool) $pys->getOption( 'track_cookie_for_subdomains' );
}

/**
 * The cookie domain for this request.
 *
 * Single source of truth: every setcookie() in the plugin must go through this,
 * deletes included. A cookie written at one scope and expired at another cannot
 * be removed — that is how PHP ended up unable to delete the pbid cookie the
 * front end had written.
 *
 * @return string Domain to pass to setcookie(), '' for host-only.
 */
function pys_cookie_domain() {
    static $cached = null;

    if ( null !== $cached ) {
        return $cached;
    }

    // Never memoise an answer derived from an unreadable option: the host-only
    // fallback would then get cached even on sites where the option is on.
    $track_subdomains = pys_cookie_domain_track_subdomains();
    $may_cache        = null !== $track_subdomains;

    $host = pys_cookie_domain_request_host();

    /**
     * Filters the cookie domain for sites where the browser cannot report it.
     *
     * Return a domain covering the current host to use while no verified
     * answer exists yet. Once the browser reports its accepted domain, that
     * answer wins; a value that doesn't cover the host is ignored.
     *
     * Does not switch subdomain tracking on — with the option off, the front
     * end writes host-only cookies and PHP matches it regardless of this filter.
     *
     * Register no later than 'init' priority 20: the resolved value is
     * memoised on first use. 'plugins_loaded' is the safe place.
     *
     * @param string $configured Domain to use, '' to let PYS decide.
     * @param string $host       Current host.
     */
    $configured = (string) apply_filters( 'pys_cookie_domain_override', '', $host );

    $reported = isset( $_COOKIE[ PYS_REPORTED_DOMAIN_COOKIE ] )
        ? sanitize_text_field( wp_unslash( $_COOKIE[ PYS_REPORTED_DOMAIN_COOKIE ] ) )
        : '';

    // An unreadable option resolves to host-only, the floor that is never
    // rejected. pys_cookie_domain_pending() reports the same state as "do not
    // write yet", so the floor is used for reading and not acted on for writing.
    $resolved = pys_cookie_domain_resolve(
        $host,
        (bool) $track_subdomains,
        $reported,
        $configured
    );

    if ( $may_cache ) {
        $cached = $resolved;
    }

    return $resolved;
}

/**
 * The `subdomain_index` Meta expects inside an `_fbp` / `_fbc` value.
 *
 * Meta defines it as the dot count of the scope the cookie lives on — `com` =
 * 0, `example.com` = 1, `www.example.com` = 2 — which is the Domain attribute
 * when we set one, else the host. Hardcoding `1` (as our old PHP and JS did)
 * is right only on a two-label domain; `shop.example.co.uk` wants 2.
 *
 * Nothing parses the index back out on our side, and Meta's docs don't say
 * whether their ingestion does — cheap correctness, not a measurable fix.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $domain Domain attribute we will set, '' for host-only.
 * @param string $host   Current host, used when the cookie is host-only.
 * @return int
 */
function pys_cookie_subdomain_index( $domain, $host ) {
    $scope = is_string( $domain ) && '' !== trim( $domain ) ? $domain : $host;
    $scope = pys_cookie_domain_normalize_host( $scope );

    return '' === $scope ? 0 : substr_count( $scope, '.' );
}

/**
 * The `subdomain_index` for a cookie set during this request.
 *
 * @return int
 */
function pys_cookie_subdomain_index_for_request() {
    return pys_cookie_subdomain_index( pys_cookie_domain(), pys_cookie_domain_request_host() );
}

/**
 * The host this request arrived on.
 *
 * Centralised because the resolver, the subdomain index and the diagnostics
 * must all see the same host within one request, or they can disagree.
 *
 * @return string Raw host, unnormalised — callers normalise as they need.
 */
function pys_cookie_domain_request_host() {
    return isset( $_SERVER['HTTP_HOST'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
        : (string) wp_parse_url( get_site_url(), PHP_URL_HOST );
}

/**
 * Whether an empty cookie domain means "host-only" or "we do not know yet".
 *
 * pys_cookie_domain() collapses both into '', which is fine for reading but
 * not for writing — the two states need opposite actions. Host-only is correct
 * when subdomain tracking is off or the host cannot carry a Domain attribute
 * (IP literal, single-label host); it is wrong on the first consented page
 * load of a site that wants subdomain tracking, where writing host-only stakes
 * a claim the next request contradicts. A third state, the option unreadable
 * (`null` before locateOptions()), must not be read as "off": the option
 * defaults to on, so that guess is wrong on a default install.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string    $host             Current host.
 * @param bool|null $track_subdomains The track_cookie_for_subdomains option,
 *                                    null while it cannot be read.
 * @param string    $resolved         What pys_cookie_domain_resolve() returned.
 * @return bool True when a write should be deferred to the front end.
 */
function pys_cookie_domain_is_pending( $host, $track_subdomains, $resolved ) {
    return '' !== pys_cookie_domain_pending_reason( $host, $track_subdomains, $resolved );
}

/**
 * Why a write is being deferred, or '' when it is not.
 *
 * Same decision as pys_cookie_domain_is_pending() plus which state applies:
 *
 *   PYS_COOKIE_DOMAIN_PENDING_OPTIONS
 *     Option not readable yet ('init' priorities 0-8). A diagnostic seeing
 *     this ran too early; it lasts a few priorities of one request.
 *
 *   PYS_COOKIE_DOMAIN_PENDING_BROWSER
 *     No browser has said which domain it accepts. Normally one request, but
 *     permanent on a site whose front end never runs.
 *
 * For reporting only, never for deciding: no server-side evidence separates
 * "not answered yet" from "never will" — no `pys_cd` looks the same in both.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string    $host             Current host.
 * @param bool|null $track_subdomains The track_cookie_for_subdomains option,
 *                                    null while it cannot be read.
 * @param string    $resolved         What pys_cookie_domain_resolve() returned.
 * @return string One of the PYS_COOKIE_DOMAIN_PENDING_* constants, or ''.
 */
function pys_cookie_domain_pending_reason( $host, $track_subdomains, $resolved ) {
    // We have an answer.
    if ( is_string( $resolved ) && '' !== trim( $resolved ) ) {
        return '';
    }

    // Host-only is the answer, not the absence of one — but only once the site
    // has actually said so. A null option has said nothing yet.
    if ( null !== $track_subdomains && ! $track_subdomains ) {
        return '';
    }

    $host = pys_cookie_domain_normalize_host( $host );

    if ( '' === $host ) {
        return '';
    }
    if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
        return '';
    }
    if ( false === strpos( $host, '.' ) ) {
        return '';
    }

    return null === $track_subdomains
        ? PYS_COOKIE_DOMAIN_PENDING_OPTIONS
        : PYS_COOKIE_DOMAIN_PENDING_BROWSER;
}

/**
 * Whether PHP should defer writing Meta's cookies to the front end this request.
 *
 * True on a site that wants subdomain tracking, on a host that can carry a
 * Domain attribute, before the browser has reported which domain that is —
 * normally one request, since our script writes `pys_cd` on the same page
 * load. Also true before options are readable ('init' priorities 0-8).
 *
 * Deliberately not bounded when the front end never answers: falling back to
 * host-only would mint an `_fbp` sent to Meta and stored nowhere, and no value
 * beats an invented one. Escape hatches are the `pys_cookie_domain_override`
 * filter and turning off `track_cookie_for_subdomains`. The silence is
 * reported by pys_cookie_domain_diagnostics() and logged at the point of loss
 * by pys_cookie_domain_log_deferral().
 *
 * @return bool
 */
function pys_cookie_domain_pending() {
    return '' !== pys_cookie_domain_pending_reason_for_request();
}

/**
 * pys_cookie_domain_pending(), but saying which pending state we are in.
 *
 * @return string One of the PYS_COOKIE_DOMAIN_PENDING_* constants, or ''.
 */
function pys_cookie_domain_pending_reason_for_request() {
    return pys_cookie_domain_pending_reason(
        pys_cookie_domain_request_host(),
        pys_cookie_domain_track_subdomains(),
        pys_cookie_domain()
    );
}

/**
 * Note in the debug log that a value was withheld for want of a cookie domain.
 *
 * Written here because the visitor's own request is the only context with
 * real evidence — an admin screen only sees the admin's own browser.
 *
 * Once per subject per request: `$subject` is what we did not produce
 * (`_fbp`), so several callers deciding the same thing log one line, not
 * several. Silent unless logging is switched on.
 *
 * @param string $subject Name of the value that was withheld.
 * @param string $note    Optional extra cause, e.g. "headers already sent".
 * @return void
 */
function pys_cookie_domain_log_deferral( $subject, $note = '' ) {
    static $logged = array();

    if ( isset( $logged[ $subject ] ) ) {
        return;
    }
    $logged[ $subject ] = true;

    $reason = pys_cookie_domain_pending_reason_for_request();

    // Nothing was deferred on our account. A caller may still have had its own
    // reason (headers_sent), and that is its message to write, not ours.
    if ( '' === $reason && '' === $note ) {
        return;
    }

    $pys = PYS();
    if ( ! $pys || ! method_exists( $pys, 'getLog' ) || ! $pys->getLog() ) {
        return;
    }

    $causes = array();
    if ( '' !== $note ) {
        $causes[] = $note;
    }
    if ( PYS_COOKIE_DOMAIN_PENDING_BROWSER === $reason ) {
        $causes[] = 'the cookie domain is unknown — our front-end script has not'
            . ' reported it (pys_cd). If it never does, this is permanent: set the'
            . ' pys_cookie_domain_override filter, or turn off'
            . ' track_cookie_for_subdomains to make host-only cookies correct';
    } elseif ( PYS_COOKIE_DOMAIN_PENDING_OPTIONS === $reason ) {
        $causes[] = 'the options were not loaded yet (init priority < 9)';
    }

    $pys->getLog()->debug(
        'Server-side ' . $subject . ' withheld: ' . implode( '; ', $causes )
    );
}

/**
 * What PYS will do with tracking cookies on this request, for the system report.
 *
 * Turns a silent permanent deferral ("server events have no _fbp and I
 * cannot see why") into one findable row.
 *
 * Limitation: run from wp-admin, `pys_cd` here is the *admin's own*
 * browser's cookie — present is strong evidence the handshake works for
 * visitors, absent is ambiguous (this browser may simply never have loaded
 * the front end).
 *
 * The caller supplies the warning glyph; `text` is escaped because
 * html-report.php echoes values raw.
 *
 * @return array label => array( 'text' => string, 'warn' => bool )
 */
function pys_cookie_domain_diagnostics() {
    $host     = pys_cookie_domain_request_host();
    $track    = pys_cookie_domain_track_subdomains();
    $resolved = pys_cookie_domain();
    $reason   = pys_cookie_domain_pending_reason( $host, $track, $resolved );

    $rows = array();

    $rows['Request host'] = array( 'text' => '' !== $host ? $host : 'unknown', 'warn' => false );

    if ( null === $track ) {
        $subdomains = 'Unknown — options not loaded yet, so this report ran too early';
    } elseif ( $track ) {
        $subdomains = 'On — cookies are scoped to the registrable domain';
    } else {
        $subdomains = 'Off — cookies are host-only, which needs no browser handshake';
    }
    $rows['Subdomain cookie tracking'] = array( 'text' => $subdomains, 'warn' => false );

    // Deliberately reports presence and acceptance rather than echoing the
    // cookie: the value is visitor-supplied, and what a diagnostic needs to know
    // is whether it was *usable*, which is what the resolver already decided.
    $reported = isset( $_COOKIE[ PYS_REPORTED_DOMAIN_COOKIE ] )
        ? sanitize_text_field( wp_unslash( $_COOKIE[ PYS_REPORTED_DOMAIN_COOKIE ] ) )
        : '';

    $covers  = pys_cookie_domain_covers_host( $reported, $host );
    $settled = '' !== trim( (string) $resolved );

    if ( '' === $reported ) {
        $handshake = 'No pys_cd from this browser. That is normal in wp-admin if'
            . ' you have not loaded the front end here; it is a problem if it also'
            . " never arrives for visitors — see the last row of this section.";
    } elseif ( $covers && $settled ) {
        $handshake = 'Reported and in use';
    } elseif ( $covers ) {
        // Structurally fine, but not the scope in force. Said plainly rather
        // than glossed as "accepted", because the row below reads host-only and
        // a report whose rows contradict each other is worse than no report.
        $handshake = false === $track
            ? 'Reported, but not in use: subdomain cookie tracking is off, so'
                . ' host-only is the correct scope and the value is ignored'
            : 'Reported, but this request had already resolved to host-only. One'
                . ' request keeps one scope; the next request will use it.';
    } else {
        $handshake = 'pys_cd present but rejected: it does not cover this host.'
            . ' Usually a stale value from a previous domain — it is ignored, and'
            . ' our script overwrites it on the next front-end page load.';
    }
    $rows['Browser handshake'] = array( 'text' => $handshake, 'warn' => false );

    $override = (string) apply_filters( 'pys_cookie_domain_override', '', $host );
    if ( '' === trim( $override ) ) {
        $filter = 'Not set';
    } elseif ( pys_cookie_domain_covers_host( $override, $host ) ) {
        $filter = 'Set to ' . pys_cookie_domain_normalize_host( $override )
            . ' — used while no browser has reported';
    } else {
        $filter = 'Set, but ignored: the value does not cover this host, so the'
            . ' browser would reject a cookie written with it';
    }
    $rows['pys_cookie_domain_override filter'] = array( 'text' => $filter, 'warn' => false );

    $rows['Cookie domain in use'] = array(
        'text' => '' !== trim( (string) $resolved ) ? (string) $resolved : 'host-only',
        'warn' => false,
    );

    if ( PYS_COOKIE_DOMAIN_PENDING_BROWSER === $reason ) {
        $fbp  = 'Disabled. PHP will not mint an _fbp until it knows the cookie'
            . ' domain, because a value it cannot store is a browser identity that'
            . ' exists nowhere. If no browser ever reports pys_cd — a consent tool'
            . ' blocking our script, a JavaScript error before it runs, or the'
            . ' pys_disable_all_cookie filter — this never resolves on its own.'
            . ' Fix it by setting the pys_cookie_domain_override filter to your'
            . ' registrable domain, or by turning off track_cookie_for_subdomains,'
            . ' which makes host-only cookies the correct answer. Values the'
            . ' browser sends us are unaffected, and so is _fbc.';
        $warn = true;
    } elseif ( PYS_COOKIE_DOMAIN_PENDING_OPTIONS === $reason ) {
        $fbp  = 'Unknown — options not loaded yet, so this report ran too early';
        $warn = false;
    } else {
        $fbp  = 'Available';
        $warn = false;
    }
    $rows['Server-side _fbp'] = array( 'text' => $fbp, 'warn' => $warn );

    return $rows;
}
