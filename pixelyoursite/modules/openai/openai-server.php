<?php

namespace PixelYourSite;

use PixelYourSite\OpenAI\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once PYS_FREE_PATH . '/modules/openai/openai-server-async-task.php';

/**
 * OpenAI Ads Conversions API sender (Free edition).
 *
 * Endpoint contract:
 *   POST https://bzr.openai.com/v1/events?pid=<PIXEL-ID>
 *   Authorization: Bearer <conversions api key>
 *   { "integration_source": "...", "validate_only": bool, "events": [ { ... } ] }
 */
class OpenAIServer {

    /** Conversions API endpoint. The pixel id goes in `?pid=`, not the body. */
    const API_URL = 'https://bzr.openai.com/v1/events';

    /** Stable identifier for the sending integration, reported to OpenAI. */
    const INTEGRATION_SOURCE = 'pixelyoursite';

    /** How far in the past `timestamp_ms` may be — 7 days, per the docs. */
    const MAX_EVENT_AGE = 604800;

    /** How far in the future `timestamp_ms` may be — 10 minutes, per the docs. */
    const MAX_EVENT_FUTURE = 600;

    /** Delivered to at least one pixel. */
    const SEND_SENT = 'sent';

    /** Nothing to send: disabled, no token, no recipient. Not an error. */
    const SEND_SKIPPED = 'skipped';

    /** The endpoint refused this payload and would refuse it again. */
    const SEND_FAILED = 'failed';

    /**
     * Event names the Conversions API accepts.
     */
    const SUPPORTED_EVENTS = array(
        'appointment_scheduled',
        'checkout_started',
        'contents_viewed',
        'items_added',
        'lead_created',
        'order_created',
        'page_viewed',
        'registration_completed',
        'subscription_created',
        'trial_started',
        'custom',
    );

    private static $_instance;

    private $isEnabled;
    private $access_token;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Register the fallback paths the browser can use when the inline server
     * send is unavailable — blocked by consent, delayed, or a dynamic event.
     */
    public function init() {

        $this->isEnabled = OpenAI()->enabled() && OpenAI()->isServerApiEnabled();

        if ( ! $this->isEnabled ) {
            return;
        }

        add_action( 'wp_ajax_pys_openai_api_event', array( $this, 'catchAjaxEvent' ) );
        add_action( 'wp_ajax_nopriv_pys_openai_api_event', array( $this, 'catchAjaxEvent' ) );

        new OpenAIAsyncTask();
    }

    /**
     * Send through the async task, so the visitor's page is not held open.
     *
     * @param SingleEvent[] $events
     */
    public function sendEventsAsync( $events ) {

        $this->snapshotSourceUrlOnEvents( $events );

        $serverEvents = array();

        foreach ( $events as $event ) {

            if ( ! $this->isEventSupported( $event ) ) {
                continue;
            }

            $ids = $event->payload['pixelIds'] ?? array();

            if ( empty( $ids ) ) {
                continue;
            }

            $serverEvents[] = array(
                'pixelIds' => $ids,
                'event'    => $this->mapEventToServerEvent( $event ),
            );
        }

        if ( count( $serverEvents ) > 0 ) {
            do_action( 'pys_send_openai_server_event', $serverEvents );
        }
    }

    /**
     * Send inline, inside the current request.
     *
     * @param SingleEvent[] $events
     */
    public function sendEventsNow( $events ) {

        $this->snapshotSourceUrlOnEvents( $events );

        foreach ( $events as $event ) {

            if ( ! $this->isEventSupported( $event ) ) {
                continue;
            }

            $ids = $event->payload['pixelIds'] ?? array();

            if ( empty( $ids ) ) {
                continue; // no destination pixel
            }

            $this->sendEvent( $ids, $this->mapEventToServerEvent( $event ) );
        }
    }

    /**
     * Record the page each event happened on, before the request ends.
     *
     * @param SingleEvent[] $events
     */
    private function snapshotSourceUrlOnEvents( $events ) {

        if ( empty( $events ) ) {
            return;
        }

        $url = $this->currentPageUrl();

        if ( $url === '' ) {
            return;
        }

        foreach ( $events as $event ) {
            if ( $event instanceof SingleEvent && empty( $event->getPayloadValue( 'event_source_url' ) ) ) {
                $event->addPayload( array( 'event_source_url' => $url ) );
            }
        }
    }

    /**
     * Only the documented event names may go out. An unknown name would be
     * rejected by the endpoint anyway; dropping it here keeps the log readable
     * and costs nothing.
     *
     * @param SingleEvent $event
     * @return bool
     */
    public function isEventSupported( $event ) {

        if ( ! $event instanceof SingleEvent ) {
            return false;
        }

        $name = $event->getPayloadValue( 'name' );

        if ( ! is_string( $name ) || ! in_array( $name, self::SUPPORTED_EVENTS, true ) ) {
            return false;
        }

        if ( $name === 'custom' ) {
            $params = is_array( $event->params ) ? $event->params : array();

            return ! empty( $params['custom_event_name'] );
        }

        return true;
    }

    /**
     * Build the Conversions API event object.
     *
     * @param SingleEvent $event
     * @return \stdClass
     */
    public function mapEventToServerEvent( $event ) {

        $eventData = $event->getData();

        $eventData = EventsManager::filterEventParams( $eventData, $event->getCategory(), array(
            'event_id' => $event->getId(),
            'pixel'    => OpenAI()->getSlug(),
        ) );

        $wooOrder = $event->payload['woo_order'] ?? null;
        $eddOrder = $event->payload['edd_order'] ?? null;

        $serverEvent = new \stdClass;

        $event_id = (string) ( $event->payload['eventID'] ?? '' );

        if ( $event_id === '' ) {
            $event_id = EventIdGenerator::guidv4();
        }

        $serverEvent->id            = $event_id;
        $serverEvent->type          = (string) $eventData['name'];
        $serverEvent->timestamp_ms  = $this->currentTimestampMs();
        $serverEvent->action_source = 'web';
        $serverEvent->source_url    = $this->resolveSourceUrl( $event );

        $params = is_array( $eventData['params'] ) ? $eventData['params'] : array();

        if ( $serverEvent->type === 'custom' && ! empty( $params['custom_event_name'] ) ) {
            $serverEvent->custom_event_name = (string) $params['custom_event_name'];
        }

        $this->persistClickId();

        $click_id = Helpers\pys_openai_get_click_id();

        if ( $click_id !== '' ) {
            $serverEvent->oppref = $click_id;
        }

        $userData = $this->getUserData( $wooOrder, $eddOrder );

        if ( count( get_object_vars( $userData ) ) > 0 ) {
            $serverEvent->user = $userData;
        }

        $serverEvent->data = $this->normaliseData( $params['data'] ?? array( 'type' => 'contents' ) );

        return $serverEvent;
    }

    /**
     * Coerce the data object to the types the endpoint demands.
     *
     * @param array|object $data
     * @return \stdClass
     */
    private function normaliseData( $data ) {

        $data = (array) $data;

        if ( isset( $data['type'] ) ) {
            $data['type'] = (string) $data['type'];
        }

        if ( isset( $data['amount'] ) ) {
            $data['amount'] = (int) $data['amount'];
        }

        if ( isset( $data['currency'] ) ) {
            $data['currency'] = (string) $data['currency'];
        }

        if ( ! empty( $data['contents'] ) && is_array( $data['contents'] ) ) {

            $contents = array();

            foreach ( $data['contents'] as $item ) {

                $item = (array) $item;

                foreach ( array( 'id', 'name', 'content_type', 'currency' ) as $string_field ) {
                    if ( isset( $item[ $string_field ] ) ) {
                        $item[ $string_field ] = (string) $item[ $string_field ];
                    }
                }

                foreach ( array( 'quantity', 'amount' ) as $int_field ) {
                    if ( isset( $item[ $int_field ] ) ) {
                        $item[ $int_field ] = (int) $item[ $int_field ];
                    }
                }

                $contents[] = $item;
            }

            $data['contents'] = $contents;
        }

        return (object) $data;
    }

    /**
     * The event's `user` object — every field OpenAI accepts and nothing else.
     *
     * @param int|null $wooOrder
     * @param int|null $eddOrder
     * @return \stdClass
     */
    public function getUserData( $wooOrder = null, $eddOrder = null ) {

        $userData = new \stdClass;

        $ip = $this->getIpAddress();

        if ( $ip !== '' ) {
            $userData->ip_address = $ip;
        }

        $user_agent = $this->getHttpUserAgent();

        if ( $user_agent !== '' ) {
            $userData->user_agent = $user_agent;
        }

        if ( ! empty( $_COOKIE['__obref'] ) ) {

            $obref = sanitize_text_field( wp_unslash( $_COOKIE['__obref'] ) );

            if ( $obref !== '' ) {
                $userData->obref = $obref;
            }
        }

        if ( OpenAI()->getOption( 'advanced_matching_enabled' ) ) {

            $matching = OpenAI()->getAdvancedMatchingParams( $wooOrder, $eddOrder );

            foreach ( array( 'email_sha256', 'external_id_sha256', 'country', 'city', 'zip_code' ) as $field ) {
                if ( ! empty( $matching[ $field ] ) ) {
                    $userData->$field = $matching[ $field ];
                }
            }

        } elseif ( EventsManager::isTrackExternalId() ) {
            $external_id = OpenAI()->getExternalId( $wooOrder, $eddOrder );

            if ( $external_id !== '' ) {
                $userData->external_id_sha256 = hash( 'sha256', $external_id );
            }
        }

        return apply_filters( 'pys_openai_server_user_data', $userData, $wooOrder, $eddOrder );
    }

    /**
     * Persist OpenAI's click identifier so later events keep reporting it.
     */
    private function persistClickId() {

        if ( ! empty( $_COOKIE['__oppref'] ) || headers_sent() ) {
            return;
        }

        if ( ! Consent()->checkConsent( 'openai' ) ) {
            return;
        }

        $from_url = Helpers\pys_openai_get_click_id();

        if ( $from_url === '' ) {
            return;
        }

        setcookie( '__oppref', $from_url, 2147483647, '/', PYS()->general_domain );
        $_COOKIE['__oppref'] = $from_url;
    }

    /**
     * Send one mapped event to every recipient pixel.
     *
     * @param array     $pixel_Ids Recipient pixel ids, already resolved upstream.
     * @param \stdClass $event     One mapped Conversions API event.
     * @return array{status:string,message:string}
     */
    public function sendEvent( $pixel_Ids, $event ) {

        if ( ! $event ) {
            return $this->sendResult( self::SEND_SKIPPED, 'No event to send' );
        }

        if ( ! OpenAI()->enabled() || ! OpenAI()->isServerApiEnabled() ) {
            return $this->sendResult( self::SEND_SKIPPED, 'OpenAI pixel or its Conversions API is disabled' );
        }

        if ( ! $this->isTimestampSendable( $event->timestamp_ms ?? null ) ) {

            PYS()->getLog()->debug( 'OpenAI server event skipped - timestamp outside the accepted window' );

            return $this->sendResult( self::SEND_FAILED, 'Event timestamp is outside the window the endpoint accepts' );
        }

        if ( ! $this->access_token ) {
            $this->access_token = OpenAI()->getApiTokens();
        }

        $validate_only = (bool) OpenAI()->getOption( 'server_validate_only' );

        $sent           = false;
        $failed_message = '';

        foreach ( (array) $pixel_Ids as $pixel_Id ) {

            if ( empty( $pixel_Id ) || empty( $this->access_token[ $pixel_Id ] ) ) {
                continue;
            }

            $request = $this->buildRequest( $pixel_Id, $event, $validate_only );

            PYS()->getLog()->debug(
                'Send OpenAI server event ' . $event->type . ' to pixel ' . $pixel_Id,
                $this->logSafeRequest( $request )
            );

            $response = wp_remote_post( $request['url'], array(
                'headers'     => $request['headers'],
                'body'        => $request['body'],
                'timeout'     => pys_server_request_timeout(),
                'redirection' => 0,
                'blocking'    => true,
            ) );

            if ( is_wp_error( $response ) ) {

                PYS()->getLog()->error( 'Error send OpenAI server event: ' . $response->get_error_message() );

                $failed_message = sprintf( 'OpenAI pixel %s: %s', $pixel_Id, $response->get_error_message() );
                continue;
            }

            $status = (int) wp_remote_retrieve_response_code( $response );
            $raw    = (string) wp_remote_retrieve_body( $response );

            PYS()->getLog()->debug( 'Response from OpenAI server: ' . $status . ' ' . $raw );

            if ( $status < 200 || $status >= 300 ) {
                PYS()->getLog()->error(
                    'OpenAI rejected the request with HTTP ' . $status . ': ' . $raw,
                    $this->logSafeRequest( $request )
                );

                $failed_message = sprintf( 'OpenAI pixel %s: HTTP %d', $pixel_Id, $status );
                continue;
            }

            $body     = json_decode( $raw, true );
            $accepted = is_array( $body ) && isset( $body['accepted_events'] )
                ? (int) $body['accepted_events']
                : 1;

            if ( $accepted < 1 ) {

                PYS()->getLog()->error(
                    'OpenAI accepted no events: ' . $raw,
                    $this->logSafeRequest( $request )
                );

                $failed_message = sprintf( 'OpenAI pixel %s: accepted_events 0', $pixel_Id );
                continue;
            }

            $sent = true;
        }

        if ( $failed_message !== '' ) {
            return $this->sendResult( self::SEND_FAILED, $failed_message );
        }

        return $sent
            ? $this->sendResult( self::SEND_SENT )
            : $this->sendResult( self::SEND_SKIPPED, 'No pixel ID with a configured Conversions API key' );
    }

    /**
     * The browser's AJAX fallback, used when the REST route is unreachable.
     */
    public function catchAjaxEvent() {

        if ( ! OpenAI()->isServerApiEnabled() ) {
            wp_send_json_error( 'OpenAI Conversions API is disabled', 400 );
        }

        $event_name = isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';
        $ids        = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();

        if ( $event_name === '' || empty( $ids ) ) {
            wp_send_json_error( 'Missing mandatory parameters: event and ids', 400 );
        }

        $params   = isset( $_POST['data'] ) ? map_deep( wp_unslash( $_POST['data'] ), 'sanitize_text_field' ) : array();

        if ( isset( $_POST['event_id'] ) ) {
            $event_id = sanitize_text_field( wp_unslash( $_POST['event_id'] ) );
        } elseif ( isset( $_POST['eventID'] ) ) {
            $event_id = sanitize_text_field( wp_unslash( $_POST['eventID'] ) );
        } else {
            $event_id = '';
        }

        $single_event = $this->dataToSingleEvent(
            $event_name,
            is_array( $params ) ? $params : array(),
            $event_id,
            array_map( 'sanitize_text_field', $ids ),
            isset( $_POST['woo_order'] ) ? (int) $_POST['woo_order'] : 0,
            isset( $_POST['edd_order'] ) ? (int) $_POST['edd_order'] : 0,
            isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : ''
        );

        $this->sendEventsNow( array( $single_event ) );

        wp_send_json_success();
    }

    /**
     * Rebuild a SingleEvent from what the browser posted.
     *
     * @param string $event_name
     * @param array  $params
     * @param string $event_id
     * @param array  $ids
     * @param int    $woo_order
     * @param int    $edd_order
     * @param string $source_url
     * @return SingleEvent
     */
    public function dataToSingleEvent( $event_name, $params, $event_id, $ids, $woo_order = 0, $edd_order = 0, $source_url = '' ) {

        $single_event = new SingleEvent( '', '' );

        $payload = array(
            'name'      => $event_name,
            'eventID'   => $event_id,
            'woo_order' => $woo_order,
            'edd_order' => $edd_order,
            'pixelIds'  => $ids,
        );

        if ( $source_url !== '' && wp_http_validate_url( $source_url ) ) {
            $payload['event_source_url'] = $source_url;
        }

        $single_event->addParams( $params );
        $single_event->addPayload( $payload );

        return $single_event;
    }

    /**
     * Resolve `source_url`, which the endpoint requires for every web event.
     *
     * @param SingleEvent $event
     * @return string
     */
    private function resolveSourceUrl( $event ) {

        $from_browser = $event->getPayloadValue( 'event_source_url' );

        if ( is_string( $from_browser ) && $from_browser !== '' && wp_http_validate_url( $from_browser ) ) {
            return $this->stripSourceUrlParams( $from_browser );
        }

        $current = $this->currentPageUrl();

        if ( $current !== '' ) {
            return $this->stripSourceUrlParams( $current );
        }

        return home_url( '/' );
    }

    /**
     * Honour the plugin-wide "remove source URL parameters" setting.
     *
     * @param string $url
     * @return string
     */
    private function stripSourceUrlParams( $url ) {

        if ( ! PYS()->getOption( 'enable_remove_source_url_params' ) ) {
            return $url;
        }

        $parts = explode( '?', $url );

        return $parts[0] !== '' ? $parts[0] : $url;
    }

    /**
     * URL of the page this request is for.
     *
     * @return string
     */
    private function currentPageUrl() {

        if ( empty( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
            return '';
        }

        $url = ( is_ssl() ? 'https://' : 'http://' )
            . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
            . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

        return wp_http_validate_url( $url ) ? $url : '';
    }

    /**
     * Assemble the HTTP request for one recipient pixel.
     *
     * @param string    $pixel_Id
     * @param \stdClass $event
     * @param bool      $validate_only
     * @return array{url:string,headers:array,body:string}
     */
    public function buildRequest( $pixel_Id, $event, $validate_only = false ) {

        if ( ! $this->access_token ) {
            $this->access_token = OpenAI()->getApiTokens();
        }

        $body = array(
            'integration_source' => self::INTEGRATION_SOURCE,
            'validate_only'      => (bool) $validate_only,
            'events'             => array( $event ),
        );

        return array(
            'url'     => self::API_URL . '?pid=' . rawurlencode( $pixel_Id ),
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . ( $this->access_token[ $pixel_Id ] ?? '' ),
            ),
            'body'    => wp_json_encode( $body ),
        );
    }

    /**
     * The request as it goes on the wire, in a form that is safe to write to a
     * log the admin can download.
     *
     * @param array $request
     * @return array
     */
    private function logSafeRequest( $request ) {

        $body = json_decode( isset( $request['body'] ) ? $request['body'] : '', true );

        return array(
            'url'  => isset( $request['url'] ) ? $request['url'] : '',
            'body' => $body === null ? ( $request['body'] ?? '' ) : $body,
        );
    }

    /**
     * Current time as an integer millisecond timestamp.
     *
     * @return int
     */
    private function currentTimestampMs() {
        return (int) round( microtime( true ) * 1000 );
    }

    /**
     * The endpoint accepts `timestamp_ms` no older than 7 days and no more than
     * 10 minutes ahead.
     *
     * @param mixed $timestamp_ms
     * @return bool
     */
    public function isTimestampSendable( $timestamp_ms ) {

        if ( ! is_numeric( $timestamp_ms ) ) {
            return false;
        }

        $age = ( $this->currentTimestampMs() - (int) $timestamp_ms ) / 1000;

        return $age <= self::MAX_EVENT_AGE && $age >= -self::MAX_EVENT_FUTURE;
    }

    /**
     * The visitor's IP, or '' when there is none worth reporting.
     *
     * @return string
     */
    private function getIpAddress() {

        $ip = PYS()->get_user_ip();

        if ( ! is_string( $ip ) || $ip === '' ) {
            return '';
        }

        $valid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return $valid ? $ip : '';
    }

    /**
     * @return string
     */
    private function getHttpUserAgent() {

        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
    }

    /**
     * @param string $status One of the SEND_* constants.
     * @param string $message
     * @return array{status:string,message:string}
     */
    private function sendResult( $status, $message = '' ) {
        return array( 'status' => $status, 'message' => $message );
    }
}

/**
 * @return OpenAIServer
 */
function OpenAIServer() {
    return OpenAIServer::instance();
}

OpenAIServer();
