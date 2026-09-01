<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * OpenAI Ads REST endpoint for browser-originated server events.
 */
class OpenAI_REST_API {

    /**
     * Register REST API routes
     */
    public function register_routes() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_rest_routes() {
        register_rest_route( 'pys-openai/v1', '/event', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_openai_event' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => $this->get_event_args(),
        ) );
    }

    /**
     * Handle one browser-originated event.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_openai_event( $request ) {

        if ( ! OpenAI()->isServerApiEnabled() ) {
            return new \WP_Error( 'api_disabled', 'OpenAI Conversions API is disabled', array( 'status' => 400 ) );
        }

        $event      = $request->get_param( 'event' );
        $data       = $request->get_param( 'data' );
        $ids        = $request->get_param( 'ids' );
        $event_id   = $request->get_param( 'eventID' );
        $woo_order  = $request->get_param( 'woo_order' );
        $edd_order  = $request->get_param( 'edd_order' );
        $source_url = $request->get_param( 'event_source_url' );

        if ( empty( $event ) || empty( $ids ) ) {
            return new \WP_Error(
                'missing_parameters',
                'Missing mandatory parameters: event and ids',
                array( 'status' => 400 )
            );
        }

        if ( empty( $source_url ) ) {

            $referer = $request->get_header( 'referer' );

            if ( ! empty( $referer ) ) {
                $source_url = esc_url_raw( $referer );
            }
        }

        $single_event = OpenAIServer()->dataToSingleEvent(
            $event,
            is_array( $data ) ? $data : array(),
            $event_id,
            $ids,
            $woo_order,
            $edd_order,
            $source_url
        );

        OpenAIServer()->sendEventsNow( array( $single_event ) );

        return new \WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Check access permissions.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function check_permission( $request ) {

        $origin    = $request->get_header( 'origin' );
        $referer   = $request->get_header( 'referer' );
        $site      = wp_parse_url( home_url() );
        $site_host = isset( $site['host'] ) ? strtolower( $site['host'] ) : '';

        $is_same_origin = false;

        if ( ! empty( $origin ) ) {
            $origin_parts   = wp_parse_url( $origin );
            $is_same_origin = ! empty( $site_host )
                && isset( $origin_parts['host'] )
                && strtolower( $origin_parts['host'] ) === $site_host;
        } elseif ( ! empty( $referer ) ) {
            $referer_parts  = wp_parse_url( $referer );
            $is_same_origin = ! empty( $site_host )
                && isset( $referer_parts['host'] )
                && strtolower( $referer_parts['host'] ) === $site_host;
        }

        // Per-IP rate limit, per namespace, configured in Global Settings.
        if ( $is_same_origin ) {

            $ip    = PYS()->get_user_ip();
            $key   = 'pys_rl_' . md5( $ip . '_pys-openai/v1' );
            $count = (int) get_transient( $key );

            $limit = max( 1, (int) PYS()->getOption( 'pys_check_permission_rate_limit' ) );

            if ( $count >= $limit ) {
                return new \WP_Error( 'rate_limited', 'Too many requests', array( 'status' => 429 ) );
            }

            set_transient( $key, $count + 1, 60 );
        }

        return (bool) apply_filters( 'pys_rest_event_permission', $is_same_origin, $request );
    }

    /**
     * @return array
     */
    private function get_event_args() {

        return array(
            'event'            => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'data'             => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '{}',
                'sanitize_callback' => array( $this, 'sanitize_data' ),
            ),
            'ids'              => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '[]',
                'sanitize_callback' => array( $this, 'sanitize_ids' ),
            ),
            'eventID'          => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'woo_order'        => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '0',
                'sanitize_callback' => array( $this, 'sanitize_order_id' ),
            ),
            'edd_order'        => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '0',
                'sanitize_callback' => array( $this, 'sanitize_order_id' ),
            ),
            'order_key'        => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'event_slug'       => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'event_source_url' => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'esc_url_raw',
            ),
        );
    }

    /**
     * Recursively sanitise every scalar in the decoded payload.
     *
     * @param mixed $data
     * @return array
     */
    public function sanitize_data( $data ) {

        if ( is_string( $data ) ) {

            $decoded = json_decode( $data, true );

            if ( is_array( $decoded ) ) {
                return map_deep( $decoded, 'sanitize_text_field' );
            }
        }

        if ( is_array( $data ) ) {
            return map_deep( $data, 'sanitize_text_field' );
        }

        return array();
    }

    /**
     * @param mixed $ids
     * @return array
     */
    public function sanitize_ids( $ids ) {

        if ( is_string( $ids ) ) {
            $decoded = json_decode( $ids, true );
            $ids     = is_array( $decoded ) ? $decoded : array( $ids );
        }

        if ( ! is_array( $ids ) ) {
            return array();
        }

        return array_values( array_filter( array_map( 'sanitize_text_field', $ids ) ) );
    }

    /**
     * @param mixed $order_id
     * @return int
     */
    public function sanitize_order_id( $order_id ) {

        if ( empty( $order_id ) || $order_id === '0' || $order_id === 'null' || $order_id === 'undefined' ) {
            return 0;
        }

        return (int) $order_id;
    }

    /**
     * Hand the route URL to the browser.
     */
    public function enqueue_scripts() {

        if ( ! OpenAI()->isServerApiEnabled() ) {
            return;
        }

        wp_localize_script( 'jquery', 'pysOpenAIRest', array(
            'restApiUrl' => rest_url( 'pys-openai/v1/event' ),
            'debug'      => PYS()->getOption( 'debug_enabled' ),
        ) );
    }

    public function init() {
        $this->register_routes();
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }
}

/**
 * Accessor function for the OpenAI REST API
 *
 * @return OpenAI_REST_API
 */
function OpenAI_REST_API() {

    static $instance = null;

    if ( $instance === null ) {
        $instance = new OpenAI_REST_API();
        $instance->init();
    }

    return $instance;
}
