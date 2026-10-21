<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Move every Google tag (gtag.js) snippet on the page onto the data layer PYS uses.
 *
 * PYS loads gtag.js with `&l=<name>`, so a second gtag.js snippet printed by a theme,
 * Site Kit or a hand-pasted Google snippet has to read the same layer: its loader gets
 * the `&l=`, and inside its inline block every `dataLayer` reference is renamed — the
 * `window.dataLayer = window.dataLayer || []` declaration and the
 * `function gtag(){dataLayer.push(arguments);}` helper.
 *
 * Only the Google tag snippet is touched, and it is recognised by its shape, as Google
 * documents it: a `<script>` block that declares the `gtag` helper at the top level of
 * the block. A helper declared inside any nested block is somebody else's and keeps its
 * name — GTM4WP wraps its developer-ID stub in a closure
 * (`(function(){function gtag(){dataLayer.push(arguments);}…})();`) and its consent
 * default in `if (typeof gtag == "undefined") { function gtag(){…} }`; both feed the GTM
 * container's data layer. Renaming the push in those blocks produced a ReferenceError
 * (task 44247592) and a consent default landing in the wrong layer. Nothing outside a
 * recognised block is rewritten: a `gtag('event', …)` call elsewhere goes through the
 * global helper and needs no rename.
 *
 * A recognised block that does not declare the layer itself gets a
 * `window.<name> = window.<name> || [];` line prepended, so the renamed push is safe
 * even when the snippet sits above `wp_head` and PYS's own declaration.
 *
 * @param string $html          The buffered page.
 * @param string $dataLayerName The layer PYS loads gtag.js with.
 * @return string The page, or the untouched page if a regex failed on it.
 */
function pys_rename_gtag_datalayer( $html, $dataLayerName ) {

    $out = preg_replace_callback(
        '/(<script\s+[^>]*src="https:\/\/www\.googletagmanager\.com\/gtag\/js\?id=[^"]+)/',
        function ( $matches ) use ( $dataLayerName ) {
            if ( preg_match( '/[?&]l=/', $matches[0] ) ) {
                // Only the default-named layer is re-homed. A loader pointed at another
                // layer belongs to a snippet whose helper we do not rewrite either.
                $r = preg_replace_callback(
                    '/([?&]l=)dataLayer(?=&|$)/',
                    function ( $m ) use ( $dataLayerName ) {
                        return $m[1] . $dataLayerName;
                    },
                    $matches[0]
                );
                return null === $r ? $matches[0] : $r;
            }
            return $matches[0] . '&l=' . $dataLayerName;
        },
        $html
    );

    if ( null === $out ) {
        return $html;
    }

    if ( 'dataLayer' === $dataLayerName ) {
        return $out;
    }

    $out = preg_replace_callback(
        '/(<script\b[^>]*>)(.*?)(<\/script>)/is',
        function ( $matches ) use ( $dataLayerName ) {
            return $matches[1] . pys_rename_gtag_snippet( $matches[2], $dataLayerName ) . $matches[3];
        },
        $out
    );

    return null === $out ? $html : $out;
}

/**
 * Rename the data layer inside one inline block, if that block is a Google tag snippet.
 *
 * @param string $body          The JavaScript between `<script …>` and `</script>`.
 * @param string $dataLayerName The layer PYS loads gtag.js with.
 * @return string The body, renamed, or as it was when it is not a Google tag snippet.
 */
function pys_rename_gtag_snippet( $body, $dataLayerName ) {

    $helper = '/function\s+gtag\s*\(\s*\)\s*\{\s*(?:window\s*\.\s*)?dataLayer\s*\.\s*push\s*\(\s*arguments\s*\)/';

    if ( ! preg_match_all( $helper, $body, $found, PREG_OFFSET_CAPTURE ) ) {
        return $body;
    }

    $top_level = false;
    foreach ( $found[0] as $match ) {
        if ( pys_gtag_helper_is_top_level( substr( $body, 0, $match[1] ) ) ) {
            $top_level = true;
            break;
        }
    }

    if ( ! $top_level ) {
        return $body;
    }

    $renamed = preg_replace_callback(
        '/(?<![\w$])dataLayer(?![\w$])/',
        function () use ( $dataLayerName ) {
            return $dataLayerName;
        },
        $body
    );

    if ( null === $renamed ) {
        return $body;
    }

    $name = preg_quote( $dataLayerName, '/' );
    $declared = preg_match(
        '/(?<![\w$])(?:window\s*\.\s*' . $name . '|window\s*\[\s*[\'"]' . $name . '[\'"]\s*\]|(?:var|let|const)\s+' . $name . ')\s*=/',
        $renamed
    );

    if ( ! $declared ) {
        $renamed = "\nwindow." . $dataLayerName . ' = window.' . $dataLayerName . ' || [];' . $renamed;
    }

    return $renamed;
}

/**
 * Whether the code before a `function gtag(){…}` declaration leaves it at the top
 * level of its block: no `{` or `(` opened and not closed again. Strings and comments
 * are dropped first so a brace inside them does not count.
 *
 * @param string $before The block's code up to the helper.
 * @return bool
 */
function pys_gtag_helper_is_top_level( $before ) {

    // `\x5c` is a backslash; spelled this way to keep PHP's own quoting out of the pattern.
    $code = preg_replace(
        '/\/\/[^\n]*|\/\*.*?\*\/|"(?:\x5c.|[^"\x5c\n])*"|\'(?:\x5c.|[^\'\x5c\n])*\'|`(?:\x5c.|[^`\x5c])*`/s',
        '',
        $before
    );

    if ( null === $code ) {
        return false;
    }

    return substr_count( $code, '{' ) === substr_count( $code, '}' )
        && substr_count( $code, '(' ) === substr_count( $code, ')' );
}
