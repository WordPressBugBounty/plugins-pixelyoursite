<?php

namespace PixelYourSite;

use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\Event;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( __NAMESPACE__ . '\PysServerEvent', false ) ) {

	/**
	 * Server event with the top-level `referrer_url` parameter, which the
	 * vendored SDK's Event has no field for. Old queued events are plain
	 * Event objects and simply lack it.
	 */
	class PysServerEvent extends Event {

		/** @var string */
		private $referrer_url = '';

		public function setReferrerUrl( $referrer_url ) {
			$this->referrer_url = (string) $referrer_url;
			return $this;
		}

		public function getReferrerUrl() {
			return $this->referrer_url;
		}

		public function normalize() {
			$payload = parent::normalize();
			if ( '' !== $this->referrer_url ) {
				$payload['referrer_url'] = $this->referrer_url;
			}

			// UserData::dedup() returns array_keys(), and PHP turns the numeric
			// string key of a raw account id into an int -- which would leave as
			// a JSON number while Meta types external_id as list<string> and the
			// browser pixel sends the decimal string. Put the strings back, so
			// both channels name the person identically.
			if ( isset( $payload['user_data']['external_id'] ) && is_array( $payload['user_data']['external_id'] ) ) {
				$payload['user_data']['external_id'] = array_map( 'strval', $payload['user_data']['external_id'] );
			}

			return $payload;
		}
	}
}
