<?php

namespace PixelYourSite;

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );

/**
 * OpenAI Ads async task handler.
 */
class OpenAIAsyncTask extends \WP_Async_Task {

    protected $action = 'pys_send_openai_server_event';

    /**
     * @param array $data The arguments passed to do_action().
     * @return array
     */
    protected function prepare_data( $data ) {

        try {

            if ( empty( $data ) ) {
                return array();
            }

            $events = isset( $data[0] ) && is_array( $data[0] ) ? $data[0] : $data;

            if ( ! empty( $this->_body_data['data'] ) ) {

                $previous = json_decode( $this->_body_data['data'], true );

                if ( is_array( $previous ) ) {
                    $events = array_merge( $previous, $events );
                }
            }

            return array( 'data' => wp_json_encode( $events ) );

        } catch ( \Exception $e ) {
            error_log( $e );
        }

        return array();
    }

    /**
     * Runs in the loopback request.
     */
    protected function run_action() {

        try {

            $events = isset( $_POST['data'] ) ? json_decode( wp_unslash( $_POST['data'] ), true ) : null;

            if ( ! is_array( $events ) || empty( $events ) ) {
                return;
            }

            foreach ( $events as $event ) {

                if ( empty( $event['event'] ) || empty( $event['pixelIds'] ) ) {
                    continue;
                }

                OpenAIServer()->sendEvent( $event['pixelIds'], self::toObject( $event['event'] ) );
            }

        } catch ( \Exception $e ) {
            error_log( $e );
        }
    }

    /**
     * Restore the object shape json_decode() flattened.
     *
     * @param array $event
     * @return \stdClass
     */
    private static function toObject( $event ) {

        $event = (array) $event;

        if ( isset( $event['data'] ) && is_array( $event['data'] ) ) {
            $event['data'] = (object) $event['data'];
        }

        if ( isset( $event['user'] ) && is_array( $event['user'] ) ) {
            $event['user'] = (object) $event['user'];
        }

        return (object) $event;
    }
}
