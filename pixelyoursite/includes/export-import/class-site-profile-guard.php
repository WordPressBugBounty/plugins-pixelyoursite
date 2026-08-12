<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Lightweight rate limiter and append-only audit log for the Site Profile API.
 *
 * Self-contained on purpose: it does NOT depend on the MCP module (which is
 * Pro-only today), so the same class ships in both editions and keeps working
 * whether or not MCP is present.
 *
 * All state lives in the plugin's own options table (Settings::storage_table()),
 * never in wp_options / transients — the rate counter carries its own window
 * timestamp so no transient TTL is needed.
 */
class SiteProfileGuard {

    /** @var string Row name holding the capped audit log. */
    const LOG_OPTION = 'pys_profile_audit_log';

    /** @var int How many audit entries to keep (newest first). */
    const LOG_MAX = 100;

    /** @var int Max requests per window, per user, per action. */
    const RATE_MAX = 30;

    /** @var int Rate-limit window in seconds. */
    const RATE_WINDOW = 60;

    /**
     * Enforce a per-user, per-action rate limit.
     *
     * @param string $action export|import|restore
     *
     * @return \WP_Error|null WP_Error when the limit is exceeded, null otherwise.
     */
    public static function checkRate( $action ) {

        $user_id = get_current_user_id();
        $key     = 'pys_profile_rl_' . $user_id . '_' . sanitize_key( (string) $action );
        $now     = time();

        $bucket = self::readRow( $key );

        // Only count hits inside the current window; an expired bucket resets.
        $count       = 0;
        $window_start = $now;
        if ( is_array( $bucket ) && isset( $bucket['ts'] ) && ( $now - (int) $bucket['ts'] ) < self::RATE_WINDOW ) {
            $count        = (int) $bucket['count'];
            $window_start = (int) $bucket['ts'];
        }

        if ( $count >= self::RATE_MAX ) {
            return new \WP_Error(
                'pys_profile_rate_limited',
                sprintf(
                    'Rate limit exceeded — max %d %s requests per %d seconds.',
                    self::RATE_MAX,
                    (string) $action,
                    self::RATE_WINDOW
                ),
                array( 'status' => 429 )
            );
        }

        self::writeRow( $key, array( 'count' => $count + 1, 'ts' => $window_start ) );

        return null;
    }

    /**
     * Append an entry to the audit log (capped, newest first).
     *
     * @param string $action export|import|restore
     * @param string $status ok|dry-run|blocked|error
     * @param string $note   Free-text detail (module count, backup id, reason...).
     *
     * @return void
     */
    public static function log( $action, $status, $note = '' ): void {

        $entry = array(
            'ts'     => gmdate( 'c' ),
            'user'   => get_current_user_id(),
            'login'  => self::currentLogin(),
            'ip'     => self::clientIp(),
            'action' => (string) $action,
            'status' => (string) $status,
            'note'   => (string) $note,
        );

        $log = self::readRow( self::LOG_OPTION );
        if ( ! is_array( $log ) ) {
            $log = array();
        }

        array_unshift( $log, $entry );
        $log = array_slice( $log, 0, self::LOG_MAX );

        self::writeRow( self::LOG_OPTION, $log );
    }

    /**
     * Read the most recent audit entries (newest first).
     *
     * @param int $limit
     *
     * @return array<int, array>
     */
    public static function getLog( $limit = 50 ): array {
        $log = self::readRow( self::LOG_OPTION );
        if ( ! is_array( $log ) ) {
            return array();
        }
        return array_slice( $log, 0, max( 0, (int) $limit ) );
    }

    /**
     * Read a value from the plugin's options table (safe unserialize, no objects).
     *
     * @param string $option_name
     *
     * @return mixed|null Decoded value, or null when the row is absent.
     */
    private static function readRow( $option_name ) {

        global $wpdb;
        $table = Settings::storage_table();

        $value = $wpdb->get_var(
            $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1", $option_name )
        );
        if ( $value === null ) {
            return null;
        }

        return is_serialized( $value )
            ? unserialize( (string) $value, array( 'allowed_classes' => false ) )
            : $value;
    }

    /**
     * Write a value into the plugin's options table (insert or update).
     *
     * @param string $option_name
     * @param mixed  $data
     *
     * @return void
     */
    private static function writeRow( $option_name, $data ): void {

        global $wpdb;
        $table = Settings::storage_table();
        $value = maybe_serialize( $data );

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE option_name = %s", $option_name )
        );

        if ( $exists ) {
            $wpdb->update( $table, array( 'option_value' => $value ), array( 'option_name' => $option_name ), array( '%s' ), array( '%s' ) );
        } else {
            $wpdb->insert( $table, array( 'option_name' => $option_name, 'option_value' => $value, 'migrated' => 1 ), array( '%s', '%s', '%d' ) );
        }
    }

    /**
     * Current user's login name, or empty.
     *
     * @return string
     */
    private static function currentLogin(): string {
        $user = wp_get_current_user();
        return ( $user && $user->exists() ) ? (string) $user->user_login : '';
    }

    /**
     * Best-effort client IP (not spoof-proof; for the audit trail only).
     *
     * @return string
     */
    private static function clientIp(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        return sanitize_text_field( $ip );
    }
}
