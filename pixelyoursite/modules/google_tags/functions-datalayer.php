<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Move every Google tag (gtag.js) snippet on the page onto the data layer PYS uses.
 *
 * PYS loads gtag.js with `&l=<name>`, so a second gtag.js snippet printed by a theme or
 * another plugin has to read the same layer: its loader gets the `&l=`, and its
 * `window.dataLayer = window.dataLayer || []` declaration and
 * `function gtag(){dataLayer.push(arguments);}` helper are renamed with it.
 *
 * Only the Google tag snippet is touched. A gtag helper kept inside a closure —
 * `(function(){function gtag(){dataLayer.push(arguments);}gtag('set', …);})();` as
 * GTM4WP prints for its developer ID — feeds the GTM container's data layer and keeps
 * its name. Renaming that push while its `var dataLayer` stayed produced a
 * ReferenceError that killed the rest of the block. Text outside `<script>` is never a
 * snippet and is left alone.
 *
 * @param string $html          The buffered page.
 * @param string $dataLayerName The layer PYS loads gtag.js with.
 * @return string The page, or the untouched page if a regex failed on it.
 */
function pys_rename_gtag_datalayer( $html, $dataLayerName ) {

    $out = preg_replace_callback(
        '/(<script\s+[^>]*src="https:\/\/www\.googletagmanager\.com\/gtag\/js\?id=[^"]+)/',
        function ( $matches ) use ( $dataLayerName ) {
            if ( strpos( $matches[0], '&l=dataLayer' ) !== false ) {
                return str_replace( '&l=dataLayer', '&l=' . $dataLayerName, $matches[0] );
            }
            return $matches[0] . '&l=' . $dataLayerName;
        },
        $html
    );

    if ( null === $out ) {
        return $html;
    }

    $out = preg_replace_callback(
        '/window\.dataLayer\s*=\s*window\.dataLayer\s*\|\|\s*\[\];|window\[\'dataLayer\'\]\s*=\s*window\[\'dataLayer\'\]\s*\|\|\s*\[\];/s',
        function ( $matches ) use ( $dataLayerName ) {
            return str_replace( 'dataLayer', $dataLayerName, $matches[0] );
        },
        $out
    );

    if ( null === $out ) {
        return $html;
    }

    $out = preg_replace_callback(
        '/<script\b[^>]*>.*?<\/script>/is',
        function ( $matches ) use ( $dataLayerName ) {
            return pys_rename_gtag_helper( $matches[0], $dataLayerName );
        },
        $out
    );

    return null === $out ? $html : $out;
}

/**
 * Rename the data layer inside the `gtag(...)` calls and helper of one `<script>` block.
 *
 * @param string $script        One `<script>…</script>` block.
 * @param string $dataLayerName The layer PYS loads gtag.js with.
 * @return string
 */
function pys_rename_gtag_helper( $script, $dataLayerName ) {

    // A gtag declared inside a closure is a local helper for another data layer
    // (GTM4WP's developer-ID stub), not the global gtag of a gtag.js snippet.
    if ( preg_match( '/\(\s*(?:function\s*\([^)]*\)|\([^)]*\)\s*=>)\s*\{\s*function\s+gtag\s*\(/', $script ) ) {
        return $script;
    }

    $out = preg_replace_callback(
        '/gtag\((.*?)\);/s',
        function ( $matches ) use ( $dataLayerName ) {
            return str_replace( 'dataLayer', $dataLayerName, $matches[0] );
        },
        $script
    );

    return null === $out ? $script : $out;
}
