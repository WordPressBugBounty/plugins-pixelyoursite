<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * How Meta's `_fbp` and `_fbc` should be read, as opposed to how PHP presents them.
 *
 * Three rules, none of which needs the vendored library, so they live here
 * off-flag and serve both readers (ParamBuilderAdapter::normalised_cookies()
 * and ServerEventHelper::getFbp()/getFbc()):
 *
 *   1. Duplicate resolution for `_fbc` — `$_COOKIE` holds one value per name,
 *      the raw `Cookie` header can carry the same name at two scopes. `_fbp`
 *      is not resolved: it is read as `$_COOKIE` presents it (PHP keeps the
 *      first copy), the way Meta's library reads it.
 *   2. The seconds→milliseconds repair of `_fbc`, for values our own PHP minted
 *      with time() and which therefore claim a creation date in January 1970.
 *   3. Recovering an `fbclid` from the referrer when the URL has none.
 *
 * Deliberately not here: minting a value. Everything below only chooses
 * between or rescales values the visitor already sent, so none of it needs a
 * `$can_write` gate — see ParamBuilderAdapter::get_fbp() for why minting does.
 */

/**
 * Every value for every name in a raw Cookie header, duplicates included.
 *
 * $_COOKIE cannot express this: it keeps one value per name. Values are
 * rawurldecode()d rather than urldecode()d so a literal '+' survives, which
 * matters for base64-ish payloads — the same choice the library makes.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $header Raw HTTP_COOKIE.
 * @return array<string, string[]>
 */
function pys_fb_parse_cookie_header( $header ) {
    $out = array();

    foreach ( explode( ';', (string) $header ) as $pair ) {
        $eq = strpos( $pair, '=' );
        if ( false === $eq ) {
            continue;
        }
        $name = trim( substr( $pair, 0, $eq ) );
        if ( '' === $name ) {
            continue;
        }
        $out[ $name ][] = rawurldecode( trim( substr( $pair, $eq + 1 ) ) );
    }

    return $out;
}

/**
 * The creation time encoded in an fbp/fbc value, normalised to milliseconds.
 *
 * Format is `fb.<subdomain_index>.<creation_time>.<payload>[.<appendix>]`.
 * A 10-digit time is seconds — that is our own old bug — and a 13-digit one
 * is already milliseconds.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $value
 * @return int|null Milliseconds, or null when the value is not parseable.
 */
function pys_fb_value_creation_time( $value ) {
    $parts = explode( '.', (string) $value );

    if ( count( $parts ) < 4 || 'fb' !== $parts[0] || ! ctype_digit( $parts[2] ) ) {
        return null;
    }

    $ts = $parts[2];

    return strlen( $ts ) <= 10 ? (int) $ts * 1000 : (int) $ts;
}

/**
 * How long `_fbp` and `_fbc` live, in days, unless a site says otherwise.
 *
 * 90 days is what fbevents.js writes and what Meta's own library asks for.
 */
const PYS_FB_COOKIE_LIFETIME_DAYS = 90;

/**
 * Clamp a filtered cookie lifetime to something a browser will actually keep.
 *
 * Chrome caps any cookie at 400 days and silently shortens longer ones, so a
 * larger number is not a longer cookie, only a less honest one.
 *
 * Not Meta-specific despite living here: OpenAI's `__oppref` is clamped by the
 * same rule, through pys_openai_cookie_lifetime().
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param mixed $filtered Days, from a filter or a default.
 * @param int   $default  Days to fall back to when the filtered value is not one.
 * @return int Days.
 */
function pys_cookie_lifetime_days( $filtered, $default = PYS_FB_COOKIE_LIFETIME_DAYS ) {
    $days = (int) $filtered;

    if ( $days < 1 ) {
        return (int) $default;
    }

    return $days > 400 ? 400 : $days;
}

/**
 * How long to keep `_fbp` and `_fbc`, in seconds.
 *
 * Deliberately NOT the `cookie_duration` option. That setting is the "First
 * Visit" attribution window — landing page, traffic source, UTMs, the Woo and
 * EDD reports — it defaults to **7 days**, and our front end used to write
 * Meta's cookies with it. A 7-day `_fbp` is a new browser identity every week,
 * while the pixel's own `_fbp` lasts 90; PHP meanwhile wrote January 2038, so
 * the two writers disagreed by twelve years.
 *
 * `pys_fb_cookie_lifetime_days` is the way to change it.
 *
 * @return int Seconds.
 */
function pys_fb_cookie_lifetime() {
    $days = pys_cookie_lifetime_days(
        apply_filters( 'pys_fb_cookie_lifetime_days', PYS_FB_COOKIE_LIFETIME_DAYS )
    );

    return $days * DAY_IN_SECONDS;
}

/**
 * The click id inside an `_fbc` value.
 *
 * Segment 4 of `fb.<subdomain_index>.<creation_time>.<fbclid>[.<appendix>]`,
 * indexed from the front rather than taken from the end, so an appendix can
 * never be mistaken for the click id.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $value
 * @return string The fbclid, or '' when the value is not an `_fbc`.
 */
function pys_fbc_click_id( $value ) {
    $parts = explode( '.', (string) $value );

    if ( count( $parts ) < 4 || 'fb' !== $parts[0] ) {
        return '';
    }

    return (string) $parts[3];
}

/**
 * Whether `_fbc` has to be minted for this request.
 *
 * Meta attributes the last click, so a returning visitor landing from a new ad
 * has to be reported under the new id. Minting only when nothing was stored
 * meant every event after that landing credited the previous ad.
 *
 * The same click id arriving again is deliberately not a refresh: re-minting
 * would move creation_time and tell Meta the click just happened.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $stored The `_fbc` this request knows about, '' when none.
 * @param string $fbclid The click id this request carries, '' when none.
 * @return bool
 */
function pys_fbc_needs_refresh( $stored, $fbclid ) {
    $fbclid = (string) $fbclid;

    if ( '' === $fbclid ) {
        return false;
    }

    return pys_fbc_click_id( $stored ) !== $fbclid;
}

/**
 * Meta's SDK language tokens, from the library's SUPPORTED_LANGUAGES_TOKEN.
 *
 * One per SDK, PHP first. Repeated here rather than read from the vendored copy
 * because a value has to be recognised on every request, while the library is
 * only loaded on some.
 */
const PYS_FB_LANGUAGE_TOKENS = array( 'AQ', 'Ag', 'Aw', 'BA', 'BQ', 'Bg' );

/**
 * Whether a trailing segment is one of Meta's appendix tokens.
 *
 * Twin of `ParamBuilder::validateAppendix()` as of 1.3.2, which stopped
 * accepting any eight characters: the V2 token is six base64url-encoded bytes
 * whose first is the format version and whose second is the SDK's language
 * index. The two-character form is the legacy language token.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $suffix
 * @return bool
 */
function pys_fb_valid_appendix( $suffix ) {
    $suffix = (string) $suffix;
    $length = strlen( $suffix );

    if ( 2 === $length ) {
        return in_array( $suffix, PYS_FB_LANGUAGE_TOKENS, true );
    }

    if ( 8 !== $length || ! preg_match( '/^[A-Za-z0-9_-]+$/D', $suffix ) ) {
        return false;
    }

    $decoded = base64_decode( strtr( $suffix, '-_', '+/' ), true );

    if ( false === $decoded || 6 !== strlen( $decoded ) ) {
        return false;
    }

    $language = ord( $decoded[1] );

    return 1 === ord( $decoded[0] )
        && $language >= 1
        && $language <= count( PYS_FB_LANGUAGE_TOKENS );
}

/**
 * Whether an `_fbp` is one Meta's own library would keep.
 *
 * Mirrors ParamBuilder 1.3.2 preProcess(), and is deliberately as loose as it
 * is: empty is missing; fewer than four or more than five dot-separated parts
 * is missing; exactly four is accepted with nothing else checked (no `fb`
 * prefix, no digits, a trailing dot included); exactly five is accepted only
 * when the fifth is a valid appendix. Anything stricter would replace cookies
 * the library keeps, and Meta would see a different identity than it would
 * from its own SDK.
 *
 * Takes the RAW value: running it after sanitize_text_field() would change the
 * number of parts for a value with a `%XX` in it.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param mixed $value
 * @return bool
 */
function pys_fbp_is_valid( $value ) {
    $value = (string) $value;

    if ( '' === $value ) {
        return false;
    }

    $parts = explode( '.', $value );
    $count = count( $parts );

    if ( 4 === $count ) {
        return true;
    }

    if ( 5 === $count ) {
        return pys_fb_valid_appendix( $parts[4] );
    }

    return false;
}

/**
 * Whether an `_fbp` / `_fbc` already carries an appendix.
 *
 * Segments are counted from the front — four is a bare value, a fifth is the
 * token — and only then validated. Guessing from the last segment's length and
 * charset alone, as strip_appendix() used to, mistakes an eight-character
 * `fbclid` for a token.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $value
 * @return bool
 */
function pys_fb_has_appendix( $value ) {
    $parts = explode( '.', (string) $value );

    return count( $parts ) >= 5 && pys_fb_valid_appendix( end( $parts ) );
}

/**
 * Whether this site wants Meta's appendix on `_fbp` and `_fbc`.
 *
 * Off by default. Meta has not said whether the token is weighted at all, and
 * `partner_agent: "dvpixelyoursite"` already identifies the integration on
 * every event, so it ships switched off and one filter away.
 *
 * @return bool
 */
function pys_fb_appendix_enabled() {
    return (bool) apply_filters( 'pys_fb_append_param_appendix', false );
}

/**
 * The appendix token, from the vendored library.
 *
 * Generated rather than hand-rolled: it encodes the library's own semver, so a
 * copy of it would go stale at the next update. `class_exists()` loads the
 * class through the autoloader — which is what defines the constants read on
 * the next line — and covers a build with `vendor_prefix` stripped.
 *
 * @param bool $is_new Whether the value was minted on this request.
 * @return string Empty when the library is unavailable.
 */
function pys_fb_appendix( $is_new ) {
    static $cache = array();

    $key = $is_new ? 'new' : 'unchanged';

    if ( isset( $cache[ $key ] ) ) {
        return $cache[ $key ];
    }

    if ( ! class_exists( '\\PYS_PRO_GLOBAL\\FacebookAds\\AppendixProvider' ) ) {
        $cache[ $key ] = '';

        return $cache[ $key ];
    }

    $type = $is_new
        ? \PYS_PRO_GLOBAL\FacebookAds\APPENDIX_NET_NEW
        : \PYS_PRO_GLOBAL\FacebookAds\APPENDIX_NO_CHANGE;

    $cache[ $key ] = (string) \PYS_PRO_GLOBAL\FacebookAds\AppendixProvider::getAppendix( $type );

    return $cache[ $key ];
}

/**
 * The value with Meta's appendix on it, when the site asked for one.
 *
 * Only `_fbp` and `_fbc` ever get it. The library also appends it to
 * `client_ip_address`, `event_source_url` and `referrer_url`, where it makes
 * the value invalid for anything but Meta's own ingestion —
 * `203.0.113.10.AQECAQMC` is not an IP address.
 *
 * Idempotent: a value that already carries one is returned untouched, so the
 * library's own output and a second pass over our own cost nothing.
 *
 * @param string $value
 * @param bool   $is_new Whether we minted this value on this request.
 * @return string
 */
function pys_fb_append_appendix( $value, $is_new ) {
    $value = (string) $value;

    if ( '' === $value || ! pys_fb_appendix_enabled() || pys_fb_has_appendix( $value ) ) {
        return $value;
    }

    $appendix = pys_fb_appendix( $is_new );

    return '' === $appendix ? $value : $value . '.' . $appendix;
}

/**
 * Of several values, the one with the earliest creation time.
 *
 * Unparseable values lose to parseable ones; if nothing parses, the first is
 * returned so the caller still gets something deterministic.
 *
 * No longer used for `_fbp`: pys_fb_resolve_cookies() hands `_fbp` through
 * untouched, as Meta's library reads it. Kept as the counterpart of
 * pys_fb_pick_newest_value(). Never apply it to `_fbc` — it reports
 * conversions against the wrong ad.
 *
 * On a tie the first value in the input wins — RFC 6265 §5.4 has user agents
 * send equal-path cookies in creation order, so in practice that's already
 * the older one.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string[] $values
 * @return string
 */
function pys_fb_pick_oldest_value( array $values ) {
    return pys_fb_pick_value( $values, true );
}

/**
 * Of several values for `_fbc`, the one that describes the click to report.
 *
 * `_fbc` is a click, and Meta attributes on last click — the library's own
 * shouldUpdateFbc() replaces the cookie whenever the payload differs, for the
 * same reason.
 *
 * Taking the oldest here caused a real misattribution: a visitor who clicked
 * ad A then later ad B would keep re-selecting A on every pageview without an
 * fbclid, crediting the conversion to A forever.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string[] $values
 * @return string
 */
function pys_fb_pick_newest_value( array $values ) {
    return pys_fb_pick_value( $values, false );
}

/**
 * Pure — no WordPress, no superglobals.
 *
 * @param string[] $values
 * @param bool     $oldest Whether the earliest creation time wins.
 * @return string
 */
function pys_fb_pick_value( array $values, $oldest ) {
    $best      = '';
    $best_time = null;

    foreach ( $values as $value ) {
        $value = (string) $value;
        if ( '' === $value ) {
            continue;
        }
        if ( '' === $best ) {
            $best = $value;
        }

        $time = pys_fb_value_creation_time( $value );
        if ( null === $time ) {
            continue;
        }

        $wins = null === $best_time
            || ( $oldest ? $time < $best_time : $time > $best_time );

        if ( $wins ) {
            $best_time = $time;
            $best      = $value;
        }
    }

    return $best;
}

/**
 * Rewrite a seconds-based creation time as milliseconds, leaving all else be.
 *
 * Applied to `_fbc` only. An `_fbp` with a seconds timestamp is kept as it is,
 * the way Meta's library keeps it.
 *
 * The random part is never re-minted: replacing it would lose the visitor
 * entirely — a far worse outcome than a wrong timestamp.
 *
 * Read-side only — see pys_fb_resolve_cookies() for why callers don't persist
 * the result.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $value
 * @return string Repaired value, or the input unchanged.
 */
function pys_fb_repair_value_timestamp( $value ) {
    $value = (string) $value;
    $parts = explode( '.', $value );

    if ( count( $parts ) < 4 || 'fb' !== $parts[0] || ! ctype_digit( $parts[2] ) ) {
        return $value;
    }

    if ( strlen( $parts[2] ) > 10 ) {
        return $value; // already milliseconds
    }

    $parts[2] = (string) ( (int) $parts[2] * 1000 );

    return implode( '.', $parts );
}

/**
 * Meta's cookies as they should be read, rather than as $_COOKIE presents them.
 *
 * Two repairs for `_fbc`, both invisible through $_COOKIE.
 *
 * Duplicates: the same name can exist at two scopes and $_COOKIE keeps only
 * one copy (PHP keeps the FIRST), so the raw header is parsed instead. Neither
 * copy is deleted. `_fbc` keeps the newest (Meta attributes the last click).
 *
 * Seconds where Meta's format says milliseconds: our own minting used time().
 * `* 1000` asserts the same wall-clock moment rather than inventing one.
 *
 * `_fbp` is deliberately not touched: it is returned exactly as $_COOKIE
 * holds it and never reported in `changed`, so it is judged the way Meta's
 * library judges it (see pys_fbp_is_valid()), not by an identity heuristic of
 * ours.
 *
 * Read-side only — the repair is deterministic, so re-deriving it every
 * request is free, and a write would need a scope it may not have. `changed`
 * is information for a caller that can write, not an instruction.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param array  $cookies    $_COOKIE, or an equivalent name => value map.
 * @param string $raw_header Raw HTTP_COOKIE, '' when unavailable.
 * @return array{values: array, changed: array<string, string>}
 */
function pys_fb_resolve_cookies( array $cookies, $raw_header ) {
    $values  = $cookies;
    $changed = array();
    $all     = pys_fb_parse_cookie_header( $raw_header );

    foreach ( array( '_fbc' ) as $name ) {
        $candidates = ! empty( $all[ $name ] ) ? $all[ $name ] : array();

        // $_COOKIE may hold a value the raw header did not survive, and vice
        // versa; consider both.
        if ( isset( $cookies[ $name ] ) && '' !== $cookies[ $name ] ) {
            $candidates[] = (string) $cookies[ $name ];
        }
        if ( ! $candidates ) {
            continue;
        }

        // `_fbc` is a click, and Meta attributes on last click.
        $chosen = pys_fb_pick_newest_value( $candidates );

        $repaired = pys_fb_repair_value_timestamp( $chosen );

        if ( '' === $repaired ) {
            continue;
        }

        $values[ $name ] = $repaired;

        // Only reported as changed if we actually differ from what came in.
        if ( ! isset( $cookies[ $name ] ) || $cookies[ $name ] !== $repaired ) {
            $changed[ $name ] = $repaired;
        }
    }

    return array(
        'values'  => $values,
        'changed' => $changed,
    );
}

/**
 * The value of one Meta cookie for this request, resolved and repaired.
 *
 * Thin request-scoped wrapper over pys_fb_resolve_cookies(). Deliberately NOT
 * memoised: ParamBuilderAdapter::flush_cookies() updates $_COOKIE after a
 * write so later code in the same request sees the stored value, and a memo
 * would hide that. The work is a handful of explode() calls over one header.
 *
 * @param string $name '_fbp' or '_fbc'.
 * @return string The resolved value, or '' when the request carries none.
 */
function pys_fb_cookie( $name ) {
    $resolved = pys_fb_resolve_cookies(
        is_array( $_COOKIE ) ? $_COOKIE : array(),
        isset( $_SERVER['HTTP_COOKIE'] ) ? (string) $_SERVER['HTTP_COOKIE'] : ''
    );

    return isset( $resolved['values'][ $name ] ) ? (string) $resolved['values'][ $name ] : '';
}

/**
 * The first value of a named parameter in a raw query string.
 *
 * The rule every click-id reader must share: the FIRST `name=value` wins, a
 * bare `name` without `=` is skipped, `name[...]` is a different name, and the
 * value is urldecode()d. ServerEventHelper::getUrlParameter() and
 * getUrlParameter() in public.js read the landing URL this way; anything else
 * reading an `fbclid` (the referrer, the param builder) must agree with them,
 * or Browser and CAPI carry different click ids for one event.
 *
 * Not parse_str(): it keeps the LAST of `?fbclid=A&fbclid=B` and turns
 * `?fbclid[0]=B` into an array (capi-param-builder issue #45).
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $query Raw query string, without the leading '?'.
 * @param string $name  Parameter name.
 * @return string|null The decoded value ('' when empty), or null when absent.
 */
function pys_query_param_first( $query, $name ) {
    foreach ( explode( '&', (string) $query ) as $pair ) {
        $parts = explode( '=', $pair );

        if ( $parts[0] === $name && isset( $parts[1] ) ) {
            return urldecode( $parts[1] );
        }
    }

    return null;
}

/**
 * An `fbclid` carried in the query string of a URL.
 *
 * Reimplements `ParamBuilder::getNewFbcPayloadFromQuery()` in the ten lines it
 * actually is. It earns its place on the REST request that dispatches server
 * events: that URL is `/wp-json/...` and carries no `fbclid`, while its
 * `Referer` is the ad click's landing URL.
 *
 * Read with pys_query_param_first(), the rule the landing page used to write
 * `_fbc`. With parse_str() `?fbclid=A&fbclid=B` gave B here while the page had
 * stored A, and the REST event re-minted `_fbc` under B. Not trimmed for the
 * same reason: the landing page does not trim either.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $url Absolute or relative URL. '' and malformed input are safe.
 * @return string The fbclid, or '' when there is none.
 */
function pys_fbclid_from_url( $url ) {
    if ( ! is_string( $url ) || '' === $url ) {
        return '';
    }

    $query = \parse_url( $url, PHP_URL_QUERY );

    if ( ! is_string( $query ) || '' === $query ) {
        return '';
    }

    return (string) pys_query_param_first( $query, 'fbclid' );
}

/**
 * An `fbclid` carried in this request's referrer.
 *
 * The thin request-scoped wrapper over pys_fbclid_from_url(). Callers must keep
 * this inside whatever bound already governs producing an `_fbc`: it widens
 * where a click id can be *found*, never when one may be produced.
 *
 * @return string The fbclid, or '' when there is none.
 */
function pys_fbclid_from_referrer() {
    return empty( $_SERVER['HTTP_REFERER'] )
        ? ''
        : pys_fbclid_from_url( (string) $_SERVER['HTTP_REFERER'] );
}
