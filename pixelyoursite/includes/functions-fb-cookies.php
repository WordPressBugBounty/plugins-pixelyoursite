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
 *   1. Duplicate resolution — `$_COOKIE` holds one value per name, the raw
 *      `Cookie` header can carry the same name at two scopes.
 *   2. The seconds→milliseconds repair, for values our own PHP minted with
 *      time() and which therefore claim a creation date in January 1970.
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
 * Of several values for `_fbp`, the one that identifies the visitor.
 *
 * The earliest creation time is the older identity, the one Meta has been
 * seeing. Unparseable values lose to parseable ones; if nothing parses, the
 * first is returned so the caller still gets something deterministic.
 *
 * **Only for `_fbp`.** Applying it to `_fbc` reports conversions against the
 * wrong ad — see pys_fb_pick_newest_value().
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
 * The opposite rule to `_fbp`, and the difference is not cosmetic. `_fbp` is
 * a browser identity, where the oldest value is the visitor Meta already
 * knows. `_fbc` is a click, and Meta attributes on last click — the library's
 * own shouldUpdateFbc() replaces the cookie whenever the payload differs, for
 * the same reason.
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
 * The random part is never re-minted: that is the browser ID, and replacing
 * it would lose the visitor entirely — a far worse outcome than a wrong
 * timestamp.
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
 * Two repairs, both invisible through $_COOKIE.
 *
 * Duplicates: the same name can exist at two scopes and $_COOKIE keeps only
 * the last, so the raw header is parsed instead. Neither copy is deleted — the
 * discarded one might be the original identity. `_fbp` keeps the oldest
 * (longest-lived browser id), `_fbc` the newest (Meta attributes the last
 * click).
 *
 * Seconds where Meta's format says milliseconds: our own minting used time().
 * `* 1000` asserts the same wall-clock moment rather than inventing one.
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

    foreach ( array( '_fbp', '_fbc' ) as $name ) {
        $candidates = ! empty( $all[ $name ] ) ? $all[ $name ] : array();

        // $_COOKIE may hold a value the raw header did not survive, and vice
        // versa; consider both.
        if ( isset( $cookies[ $name ] ) && '' !== $cookies[ $name ] ) {
            $candidates[] = (string) $cookies[ $name ];
        }
        if ( ! $candidates ) {
            continue;
        }

        // Opposite rules on purpose: `_fbp` is an identity, where the oldest
        // value is the visitor Meta already knows; `_fbc` is a click, and
        // Meta attributes on last click.
        $chosen = '_fbc' === $name
            ? pys_fb_pick_newest_value( $candidates )
            : pys_fb_pick_oldest_value( $candidates );

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
 * An `fbclid` carried in the query string of a URL.
 *
 * Reimplements `ParamBuilder::getNewFbcPayloadFromQuery()` in the ten lines it
 * actually is. It earns its place on the REST request that dispatches server
 * events: that URL is `/wp-json/...` and carries no `fbclid`, while its
 * `Referer` is the ad click's landing URL.
 *
 * `parse_str()` can yield an array for `?fbclid[]=x`, which the library does
 * not guard against; anything but a non-empty string is refused here.
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

    $params = array();
    \parse_str( $query, $params );

    if ( ! isset( $params['fbclid'] ) || ! is_string( $params['fbclid'] ) ) {
        return '';
    }

    return trim( $params['fbclid'] );
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
