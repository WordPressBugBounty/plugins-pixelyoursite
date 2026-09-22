<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Headers that may carry the visitor's address, in order of how much we trust them.
 *
 * CF-Connecting-IPv6 comes first because Cloudflare's "Pseudo IPv4" setting
 * replaces CF-Connecting-IP with a synthetic class-E address (240.0.0.0/4) for
 * IPv6 visitors and moves the real one here.
 *
 * HTTP_CLIENT_IP is deliberately absent: non-standard, and unlike the others
 * not stripped by any infrastructure — anyone can send `Client-IP: 1.2.3.4`
 * and be believed. A site whose proxy genuinely only sets that header can
 * restore it through the `pys_client_ip` filter.
 *
 * CF-Connecting-IP and True-Client-IP are only trustworthy because the edge
 * strips and re-adds them; on a request that bypasses the edge they are as
 * forgeable as anything else. Use `pys_client_ip` to check against known
 * proxy ranges if that matters.
 */
const PYS_CLIENT_IP_HEADERS = array(
    'HTTP_CF_CONNECTING_IPV6',
    'HTTP_CF_CONNECTING_IP',
    'HTTP_TRUE_CLIENT_IP',
    'HTTP_X_REAL_IP',
    'HTTP_X_FORWARDED_FOR',
    'REMOTE_ADDR',
);

/**
 * Reduce one header value to a bare IP address, or '' if it is not one.
 *
 * Handles the four shapes proxies actually emit: `1.2.3.4`, `1.2.3.4:56789`,
 * `2606:4700::1111` and `[2606:4700::1111]:443`. A bare IPv6 literal must not
 * lose anything after its last colon, which is why the port is only stripped
 * when the value is bracketed or has exactly one colon.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $value
 * @return string Bare IP, or ''.
 */
function pys_normalize_ip( $value ) {
    $value = trim( (string) $value );

    if ( '' === $value ) {
        return '';
    }

    // [2606:4700::1111] or [2606:4700::1111]:443
    if ( '[' === $value[0] ) {
        $close = strpos( $value, ']' );
        if ( false === $close ) {
            return '';
        }
        $value = substr( $value, 1, $close - 1 );
    } elseif ( 1 === substr_count( $value, ':' ) ) {
        // Exactly one colon means host:port; more than one is an IPv6 literal.
        $value = substr( $value, 0, strrpos( $value, ':' ) );
    }

    return false !== filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
}

/**
 * True when an address is globally routable — not private, not reserved.
 *
 * This is what makes `10.0.0.5` and Cloudflare's synthetic `240.x` unusable as a
 * `client_ip_address`: Meta cannot match on an address that identifies nobody.
 *
 * Pure — no WordPress, no superglobals.
 *
 * @param string $ip
 * @return bool
 */
function pys_is_public_ip( $ip ) {
    return false !== filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

/**
 * The visitor's address, resolved from a request's headers.
 *
 * Trust order decides, nothing else: the first header in
 * PYS_CLIENT_IP_HEADERS yielding a globally routable address wins, and only
 * the first entry of a comma-separated value counts (in
 * `X-Forwarded-For: client, proxy1, proxy2` the client is first, and scanning
 * the chain would let a proxy's own address win).
 *
 * No IPv6-over-IPv4 preference across headers: where X-Real-IP is present
 * REMOTE_ADDR is the proxy, not the peer, so preferring a "hidden" IPv6 there
 * collapsed every IPv4 visitor of a Cloudflare-fronted site onto a handful of
 * egress addresses. Cloudflare's Pseudo IPv4, the problem such a preference
 * reaches for, is already handled by reading CF-Connecting-IPv6 first and
 * rejecting non-routable addresses.
 *
 * `$require_public` is why this function has two callers:
 *
 *   - true — anything sent to an ad platform as `client_ip_address`. A private
 *     address is worse than none: it cannot match, and it silently pollutes
 *     the parameter Meta weighs most.
 *   - false — local bookkeeping (`blocked_ips`, transient and rate-limit
 *     keys), where a LAN address is a perfectly good identifier and someone
 *     excluding their own office traffic may well have entered one.
 *
 * Pure — no WordPress, no superglobals. `$server` is normally `$_SERVER`.
 *
 * @param array $server         Request headers.
 * @param bool  $require_public Whether a non-routable address is unacceptable.
 * @return string Bare IP, or '' when nothing usable was found.
 */
function pys_resolve_client_ip( array $server, $require_public = false ) {
    $first_parseable = '';

    foreach ( PYS_CLIENT_IP_HEADERS as $header ) {
        if ( empty( $server[ $header ] ) ) {
            continue;
        }

        $first = explode( ',', (string) $server[ $header ] )[0];
        $ip    = pys_normalize_ip( $first );

        if ( '' === $ip ) {
            continue;
        }

        // Remembered for the lax mode, where a LAN address is a usable
        // identifier and the most trusted header still wins.
        if ( '' === $first_parseable ) {
            $first_parseable = $ip;
        }

        if ( pys_is_public_ip( $ip ) ) {
            return $ip;
        }
    }

    return $require_public ? '' : $first_parseable;
}

/**
 * The visitor's address for a `client_ip_address` parameter.
 *
 * Empty when the request carries nothing routable — callers must not send an
 * empty value, they must omit the parameter.
 *
 * @return string
 */
function pys_client_ip_for_capi() {
    $ip = pys_resolve_client_ip( $_SERVER, true );

    /**
     * Filters the resolved client IP address.
     *
     * The same filter as PYS()->get_user_ip() uses, so a site that overrides
     * IP detection overrides it everywhere. A filter is an explicit decision, so
     * a value returned from it is used as given — including a private one.
     *
     * @param string $ip     Resolved address, '' when nothing routable was found.
     * @param string $header Kept for backwards compatibility; now always 'capi'.
     */
    return (string) apply_filters( 'pys_client_ip', $ip, 'capi' );
}
