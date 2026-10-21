<?php
namespace PixelYourSite;

use PixelYourSite;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\Event;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\UserData;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\CustomData;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\Content;
defined('ABSPATH') or die('Direct access not allowed');


class ServerEventHelper {
    private static $fbp;
    private static $fbc;
    /**
     * @param SingleEvent $event
     * @return Event | null
     */
    public static function mapEventToServerEvent($event) {

        $eventData = $event->getData();
        $eventData = EventsManager::filterEventParams($eventData,$event->getCategory(),[
            'event_id'=>$event->getId(),
            'pixel'=>Facebook()->getSlug()
        ]);

        $eventName = $eventData['name'];
        $eventParams = $eventData['params'];
        $eventId = $event->payload['eventID'];
        // Cast at the boundary: a non-numeric or hostile payload value must not
        // travel any further as a truthy "order id".
        $wooOrder = isset($event->payload['woo_order']) && is_numeric($event->payload['woo_order'])
            ? (int) $event->payload['woo_order']
            : null;
        $eddOrder = isset($event->payload['edd_order']) ? $event->payload['edd_order'] : null;

        if(!$eventId) return null;

        // Loaded once: the order supplies the customer's IP and user agent.
        // Guarded like getFbStatFromOrder(): $wooOrder can arrive from the
        // client payload on a site that has no WooCommerce at all.
        $order = null;
        if ( $wooOrder && function_exists( 'wc_get_order' ) && PixelYourSite\isWooCommerceActive() ) {
            $order = wc_get_order( $wooOrder ) ?: null;
        }
        // An EDD order is not loaded here, but its event is order-bound all the
        // same: a gateway webhook carries no browser to mint a browser id for.
        $order_bound = $order || !empty( $eddOrder );

        // An EDD order supplies the same IP and user agent through an adapter
        // built from its stored snapshot.
        $signalOrder = $order;
        if ( ! $signalOrder && $eddOrder && PixelYourSite\isEddActive() ) {
            $signalOrder = pys_edd_order_signal_source( $eddOrder );
        }

        // The request this runs in is not always the customer's: an admin
        // changing an order's status, a gateway webhook, cron. IP and user
        // agent therefore come from the order when it has them, and the
        // request's own cookies are read only when the request is a visitor's.
        // See includes/functions-event-signals.php.
        $signals = pys_event_client_signals( $signalOrder, self::getIpAddress(), self::getHttpUserAgent(), pys_is_admin_page_request() );

        $user_data = ServerEventHelper::getUserData($wooOrder,$eddOrder)
            ->setClientUserAgent( $signals['user_agent'] );

        // Set only when we have a routable address. An empty client_ip_address
        // is not a neutral value: it occupies the field Meta weighs most with
        // something that cannot match.
        if ( '' !== $signals['ip'] ) {
            $user_data->setClientIpAddress( $signals['ip'] );
        }

		$fbp_withheld      = false;
		$fbp_withheld_note = '';

		if ( ! $signals['request_cookies'] ) {
			// A wp-admin request: its cookies are the administrator's, and a
			// cookie minted here would land in the administrator's browser.
		} elseif ( ParamBuilderAdapter::is_enabled() ) {
			// Meta's param builder already minted and persisted these on 'init',
			// in the correct format: a millisecond creation time, a derived
			// subdomain_index, an fbclid recovered from the referrer when the
			// URL had none, and the appendix that lets Meta recognise the
			// integration. Nothing to do here but read the result.
			ParamBuilderAdapter::for_request()->flush_cookies();
		} elseif ( Consent()->checkConsent( 'facebook' ) ) {
			$withheld = self::mintBrowserCookies( $eventParams, $order_bound );

			if ( null !== $withheld ) {
				$fbp_withheld      = true;
				$fbp_withheld_note = $withheld;
			}
		}

        $fbp = '';
        $fbc = '';

        if ($wooOrder) {
            $fbp = ServerEventHelper::getFbStatFromOrder('fbp', $wooOrder);
            // A stored value Meta's library would not keep is not sent.
            if ( ! pys_fbp_is_valid( $fbp ) ) {
                $fbp = null;
            }
            $fbc = ServerEventHelper::getFbStatFromOrder('fbc', $wooOrder);
        } elseif ( $eddOrder ) {
            $fbp = ServerEventHelper::getFbStatFromEddOrder('fbp', $eddOrder);
            if ( ! pys_fbp_is_valid( $fbp ) ) {
                $fbp = null;
            }
            $fbc = ServerEventHelper::getFbStatFromEddOrder('fbc', $eddOrder);
        }

// Checking that the values are not empty and setting alternative values if they are missing.
// Order-sourced values keep top priority: they were captured when the order was
// placed and describe that visitor, which a value read now may no longer do.
// The request's values stand in only when the request is that visitor's: in
// wp-admin they are the administrator's, and an order placed without a browser
// is better sent without a browser id than with the wrong one.
        if ( $signals['request_cookies'] ) {
            // Not for an order-bound event or on cron, for the reason `_fbp` is
            // not minted there: the adapter is a per-request singleton, so a
            // queue batch would share one invented id between unrelated orders.
            if ( empty( $fbp ) && ParamBuilderAdapter::is_enabled() && ! $order_bound && ! wp_doing_cron() ) {
                $fbp = ParamBuilderAdapter::for_request()->get_fbp() ?? '';
                // The adapter withholds `_fbp` while the cookie domain is pending
                // and says nothing about it: whether that is a loss depends on the
                // fallbacks below, so the note is taken here and logged at the end.
                if ( '' === $fbp && pys_cookie_domain_pending() ) {
                    $fbp_withheld = true;
                }
            }
            if ( empty( $fbc ) && ParamBuilderAdapter::is_enabled() ) {
                $fbc = ParamBuilderAdapter::for_request()->get_fbc() ?? '';
            }
            if(empty($fbp)) {
                $fbp = self::getFbp() ?? ( isset( $eventParams['_fbp'] ) && pys_fbp_is_valid( $eventParams['_fbp'] ) ? $eventParams['_fbp'] : '' );
            }
            if (empty($fbc)) {
                $fbc = self::getFbc() ?? $eventParams['_fbc'] ?? '';
            }
        }

        // Only now is "withheld" a loss: nothing above could supply an `_fbp`
        // either, so this event reaches Meta without one. Logged because a
        // site whose front end never reports the domain would otherwise lose
        // server-side `_fbp` silently and permanently.
        if ( ! empty( $fbp_withheld ) && empty( $fbp ) ) {
            pys_cookie_domain_log_deferral( '_fbp', $fbp_withheld_note );
        }

        // Whatever supplied the value — the order snapshot, a cookie, the payload
        // — it is tagged as unchanged rather than new. A value we minted above
        // already carries its own token and is returned untouched.
        if(!empty($fbp)) { $user_data->setFbp( pys_fb_append_appendix( $fbp, false ) ); }
        if(!empty($fbc)) { $user_data->setFbc( pys_fb_append_appendix( $fbc, false ) ); }

        $customData = self::paramsToCustomData($eventParams);
        $uri = self::getRequestUri(PYS()->getOption('enable_remove_source_url_params'));

        // set custom uri use in ajax request
        if(isset($_POST['url'])) {
            if(PYS()->getOption('enable_remove_source_url_params')) {
                $list = explode("?",$_POST['url']);
                if(is_array($list) && count($list) > 0) {
                    $uri = $list[0];
                } else {
                    $uri = $_POST['url'];
                }
            } else {
                $uri = $_POST['url'];
            }
        }

        // Highest priority: the real page URL captured by the browser and
        // passed through the REST/AJAX payload (or derived from the REST
        // request Referer header). Meta requires event_source_url to be the
        // page where the event happened, not the REST endpoint.
        if ( method_exists( $event, 'getPayloadValue' ) ) {
            $payload_source_url = $event->getPayloadValue( 'event_source_url' );
            if ( ! empty( $payload_source_url ) ) {
                if ( PYS()->getOption( 'enable_remove_source_url_params' ) ) {
                    $payload_source_url = explode( '?', $payload_source_url )[0];
                }
                $uri = $payload_source_url;
            }
        }

        // A top-level server event parameter, not a custom_data property.
        $referrer_url = pys_resolve_event_referrer_url( $event );
        self::requireServerSideClasses();

        $event = (new PysServerEvent())
            ->setReferrerUrl($referrer_url)
            ->setEventName($eventName)
            ->setEventTime(time())
            ->setEventId($eventId)
            ->setEventSourceUrl($uri)
            ->setActionSource("website")
            ->setCustomData($customData)
            ->setUserData($user_data);

		if ( Facebook()->getLDUMode() ) {
			$event
			->setDataProcessingOptions( [ 'LDU' ] )
			->setDataProcessingOptionsCountry( 0 )
			->setDataProcessingOptionsState( 0 );
		}

        return $event;
    }

    /**
     * @param $key
     * @param $wooOrder
     * @return string|null
     */
    private static function getFbStatFromOrder($key,$wooOrder) {

        // $wooOrder arrives from the client payload (REST or admin-ajax), so it
        // can name an order on a site that has no WooCommerce at all. Mirrors the
        // guard getUserData() already applies a few methods below.
        // function_exists() is checked FIRST and is the load-bearing test: it is
        // the only one that cannot itself be undefined, and it is what actually
        // prevents the fatal.
        if ( ! $wooOrder
             || ! function_exists( 'wc_get_order' )
             || ! PixelYourSite\isWooCommerceActive() ) {
            return null;
        }

        $order = wc_get_order( $wooOrder );
        if($order) {
            $fbCookie = $order->get_meta('pys_fb_cookie',true);
            if($fbCookie){
                if(!empty($fbCookie[$key])) {
                    return $fbCookie[$key];
                }
            }
        }
        return null;
    }

    /**
     * Same snapshot as getFbStatFromOrder(), read from an EDD payment.
     *
     * @param string $key fbp|fbc
     * @param $eddOrder
     * @return string|null
     */
    private static function getFbStatFromEddOrder( $key, $eddOrder ) {

        // $eddOrder arrives from the client payload too. function_exists() is
        // checked FIRST for the reason getFbStatFromOrder() gives.
        if ( ! $eddOrder
             || ! function_exists( 'edd_get_payment_meta' )
             || ! PixelYourSite\isEddActive() ) {
            return null;
        }

        $fbCookie = edd_get_payment_meta( $eddOrder, 'pys_fb_cookie', true );
        if ( is_array( $fbCookie ) && ! empty( $fbCookie[ $key ] ) ) {
            return $fbCookie[ $key ];
        }

        return null;
    }

    /**
     * The client IP address to send to Meta.
     *
     * Was its own header scan, disagreeing with PYS()->get_user_ip() in this
     * same plugin, and both put the forgeable HTTP_CLIENT_IP first.
     * pys_client_ip_for_capi() is the single strict resolver and never returns
     * a non-routable address; the old 127.0.0.1 fallback is gone because
     * loopback cannot match anything.
     *
     * @return string Empty when nothing routable was found; the caller omits the
     *                parameter rather than sending an empty one.
     */
    private static function getIpAddress() {
        return pys_client_ip_for_capi();
    }

    private static function isValidIpAddress($ip_address) {
        return filter_var($ip_address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
            | FILTER_FLAG_IPV6
            | FILTER_FLAG_NO_PRIV_RANGE
            | FILTER_FLAG_NO_RES_RANGE);
    }

    private static function getHttpUserAgent() {
        $user_agent = null;

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        }

        return $user_agent;
    }

    private static function getRequestUri($removeQuery = false) {
        $request_uri = null;

        if (!empty($_SERVER['REQUEST_URI'])) {
            $start = pys_get_request_protocol();
            $host = $_SERVER['HTTP_HOST'] ?? parse_url(get_site_url(), PHP_URL_HOST);
            $request_uri = $start . $host . $_SERVER['REQUEST_URI'];
        }
        if($removeQuery && isset($_SERVER['QUERY_STRING'])) {
            $request_uri = str_replace("?".$_SERVER['QUERY_STRING'],"",$request_uri);
        }


        return $request_uri;
    }
    /**
     * A named query parameter's value, or false when it has none.
     *
     * `?fbclid` with no `=` must not become a click id: boolean true used to
     * stringify into one ("1" in PHP, "true" in our JS twin), get written to a
     * cookie that lives until 2038, and then block a real fbclid from ever
     * being recorded, since the caller only mints while `! self::getFbc()`.
     * A valueless match does not end the scan either, so `?fbclid&fbclid=REAL`
     * still finds the real one. Its JS twin in dist/scripts/public.js must
     * agree; tools/tests/url-parameter.* assert both.
     *
     * @param string $sParam Parameter name.
     * @return string|false Decoded value, or false if absent or valueless.
     */
    static function getUrlParameter($sParam) {
        // Absent on WP-CLI and on some cron paths, where reading it raw was an
        // undefined-index notice.
        $sPageURL = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
        $sURLVariables = explode('&', $sPageURL);

        foreach ($sURLVariables as $sURLVariable) {
            $sParameterName = explode('=', $sURLVariable);

            if ($sParameterName[0] === $sParam) {
                if ( ! isset( $sParameterName[1] ) ) {
                    continue;
                }
                return urldecode($sParameterName[1]);
            }
        }

        return false;
    }

    public static function setFbp($fbp) {
        self::$fbp = $fbp;
    }

    public static function setFbc($fbc) {
        self::$fbc = $fbc;
    }
	/**
	 * The `_fbp` this request knows about.
	 *
	 * Read through pys_fb_cookie(), which hands `_fbp` over exactly as $_COOKIE
	 * holds it (PHP keeps the first of two copies) — no oldest-duplicate pick,
	 * no repair of a seconds timestamp, as in Meta's own library. What decides
	 * is pys_fbp_is_valid(), on the RAW value: an invalid cookie counts as
	 * absent, so the caller mints and overwrites it.
	 *
	 * Single choke point on purpose, so "must a value be produced" and "what
	 * goes into the event" cannot disagree. The static set by setFbp() still
	 * wins only when the request carries no usable cookie.
	 */
    public static function getFbp() {
        $fbp = null;
        $resolved = pys_fb_cookie( '_fbp' );

        if ( '' !== $resolved && pys_fbp_is_valid( $resolved ) ) {
            $fbp = sanitize_text_field( $resolved );
        }
        elseif (!empty(self::$fbp)){
            $fbp = self::$fbp;
        }
        return $fbp;
    }

	/**
	 * The `_fbc` this request knows about. See getFbp().
	 * Unlike `_fbp`, a value minted in this request wins over the cookie: it is
	 * only minted when the request carries a newer click than the stored one.
	 */
    public static function getFbc() {
        $fbc = null;
        $resolved = pys_fb_cookie( '_fbc' );

        if (!empty(self::$fbc)){
            $fbc = self::$fbc;
        }
        elseif ('' !== $resolved) {
            $fbc = $resolved;
        }
        return $fbc;
    }

	/**
	 * Produce `_fbp` / `_fbc` for this visitor where they are missing and, when
	 * cookies may be written, persist them. Consent is the caller's to check.
	 *
	 * Shared by the server event and by writeFirstRequestCookies(), so the
	 * two cannot disagree on format, refresh rules, domain or lifetime.
	 *
	 * @param array $eventParams The event's params: a valid `_fbp` there counts as present.
	 * @param bool  $order_bound An order event: there is no browser to mint `_fbp` for.
	 * @return string|null Why `_fbp` was withheld ('' when no reason is known), null when it was not.
	 */
	public static function mintBrowserCookies( $eventParams = array(), $order_bound = false ) {
		$withheld = null;

		// Meta's format is `version.subdomainIndex.creationTime.payload`,
		// creationTime in MILLISECONDS; $fb_prefix supplies both.
		//
		// Writing needs headers unsent, cookies not switched off by the
		// site AND the domain settled: a host-only write while pending
		// stakes a claim the next request contradicts.
		// Whether a value may be PRODUCED is answered per cookie below.
		$can_write = pys_may_write_cookies();

		$fb_prefix = 'fb.' . pys_cookie_subdomain_index_for_request() . '.'
			. (int) round( microtime( true ) * 1000 ) . '.';

		// `_fbp` needs both answers to agree: its payload is random, so a
		// value sent but not stored is a browser id that exists nowhere.
		// Withholding is returned, and the server event logs it once the
		// order's stored value has had its turn: a webhook or cron request
		// has no cookies but usually does have the order, and an `_fbp`
		// that reaches Meta from there was not withheld from anyone.
		//
		// Never minted for an order-bound event or on cron: there is no
		// browser, and the static would hand one invented id to every
		// order of a batch. That skip is not a withheld loss either.
		//
		// A payload `_fbp` counts only when Meta's library would keep it
		// (pys_fbp_is_valid()); an invalid cookie or payload is as good as
		// none, and the new value overwrites the cookie once.
		if ( !$order_bound && !wp_doing_cron() && !self::getFbp() && !( isset( $eventParams[ '_fbp' ] ) && pys_fbp_is_valid( $eventParams[ '_fbp' ] ) ) ) {
			if ( $can_write ) {
				self::setFbp( pys_fb_append_appendix( $fb_prefix . mt_rand( 0, 2147483647 ), true ) );
				setcookie( "_fbp", self::getFbp(), time() + pys_fb_cookie_lifetime(), '/', pys_cookie_domain() );
				// Readable within this request: the order snapshot taken
				// later must not store the stale value.
				$_COOKIE['_fbp'] = self::getFbp();
			} else {
				$withheld = headers_sent() ? 'the response headers were already sent' : '';
			}
		}

		// `_fbc` is produced even when it cannot be stored: its payload is
		// the request's `fbclid`, not an invented id, so there is no phantom
		// identity. The domain is pending on the landing request of every
		// paid click, and withholding there loses the click on a bounce.
		// The cost: Meta may see two `_fbc` strings for one click, ours and
		// our JS's, differing in creation time.
		//
		// The referrer is consulted only when the URL carries no fbclid:
		// server events POST to /wp-json/..., whose Referer is the landing
		// page.
		$fbclid = self::getUrlParameter( 'fbclid' );

		if ( ! $fbclid ) {
			$fbclid = pys_fbclid_from_referrer();
		}

		// A new click replaces a stored one; the same click id does not,
		// because re-minting would move creation_time. The URL is read on
		// every event now, not only when `_fbc` is missing — that is how a
		// second ad click is noticed at all.
		if ( pys_fbc_needs_refresh( self::getFbc(), (string) $fbclid ) ) {
			self::setFbc( pys_fb_append_appendix( $fb_prefix . $fbclid, true ) );
			if ( $can_write ) {
				setcookie( "_fbc", self::$fbc, time() + pys_fb_cookie_lifetime(), '/', pys_cookie_domain() );
				// Readable within this request: the order snapshot taken
				// later must not store the stale click.
				$_COOKIE['_fbc'] = self::$fbc;
			} else {
				// The value above still reaches Meta; what is lost is the
				// cookie, and with it every reader of document.cookie on
				// this page load (a form's hidden field, for one).
				pys_cookie_domain_log_deferral( '_fbc', headers_sent() ? 'the response headers were already sent' : '' );
			}
		}

		return $withheld;
	}

	/**
	 * Write `_fbp` / `_fbc` on 'init', before any output, so a new visitor's
	 * first HTML response already carries them.
	 *
	 * Opt-in (Meta setting first_request_cookies). Without it a first visit
	 * gets these cookies only from public.js, after Facebook.loadPixel() has
	 * waited for identity, which is too late for anything that reads
	 * document.cookie as the page loads — a form filling hidden fields on DOM
	 * ready, for one. Off by default: a full-page cache that stores the
	 * response with its Set-Cookie would hand one `_fbp` to every visitor.
	 *
	 * Front-end page requests only; a value is written only when missing, so a
	 * returning visitor gets no Set-Cookie. Param builder sites are skipped:
	 * ParamBuilderAdapter::boot() has written them already.
	 */
	public static function writeFirstRequestCookies() {
		if ( ! Facebook()->getOption( 'first_request_cookies' )
			|| ! Facebook()->enabled()
			|| ! Facebook()->configured()
			|| ParamBuilderAdapter::is_enabled()
			|| ! self::isFrontEndPageRequest()
			|| ! pys_may_write_cookies()
			|| ! Consent()->checkConsent( 'facebook' )
		) {
			return;
		}

		self::mintBrowserCookies();
	}

	/**
	 * A visitor's page request: GET or HEAD, not wp-admin, admin-ajax, cron or
	 * REST. REST_REQUEST is not defined yet on 'init', so the path is checked.
	 *
	 * @return bool
	 */
	private static function isFrontEndPageRequest() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '';

		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || isset( $_GET['rest_route'] ) ) {
			return false;
		}

		$path = isset( $_SERVER['REQUEST_URI'] )
			? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
			: '';

		return false === strpos( trailingslashit( $path ), '/' . rest_get_url_prefix() . '/' );
	}

    private static function getUserData($wooOrder = null,$eddOrder = null) {
        $userData = new UserData();

        /**
         * Add purchase WooCommerce Advanced Matching params
         */
        if ( PixelYourSite\isWooCommerceActive() && isEventEnabled( 'woo_purchase_enabled' ) &&
            ($wooOrder || ( PYS()->woo_is_order_received_page() && wooIsRequestContainOrderId() ))
        ) {
            if(wooIsRequestContainOrderId()) {
                $order_id = wooGetOrderIdFromRequest();
            } else {
                $order_id = $wooOrder;
            }

            $order = wc_get_order( $order_id );

            if ( $order ) {

                if ( PixelYourSite\isWooCommerceVersionGte( '3.0.0' ) ) {

					$user_firstname = $order->get_billing_first_name();
					$user_lastname = $order->get_billing_last_name();
					$user_phone = $order->get_billing_phone();
					$user_email = $order->get_billing_email();

                    if($order->get_billing_postcode()) {
                        $userData->setZipCode($order->get_billing_postcode());
                    }
                    if($order->get_billing_country()) {
                        $userData->setCountryCode(strtolower($order->get_billing_country()));
                    }
                    if($order->get_billing_city()) {
                        $userData->setCity($order->get_billing_city());
                    }
                    if($order->get_billing_state()) {
                        $userData->setState($order->get_billing_state());
                    }
                    $external_ids = PixelYourSite\pys_get_order_external_ids($order->get_meta( 'external_id' ), $order->get_user_id());
                    if (!empty($external_ids)) {
                        $userData->setExternalIds($external_ids);
                    }
                } else {
					$user_firstname = $order->billing_first_name;
					$user_lastname = $order->billing_last_name;
					$user_phone = $order->billing_phone;
					$user_email = $order->billing_email;

                    if($order->billing_postcode) {
                        $userData->setZipCode($order->billing_postcode);
                    }
                    $userData->setCountryCode(strtolower($order->billing_country));
                    $userData->setCity($order->billing_city);
                    $userData->setState($order->billing_state);
                    $external_ids = PixelYourSite\pys_get_order_external_ids(get_post_meta( $order_id, 'external_id', true ), $order->get_user_id());
                    if (!empty($external_ids)) {
                        $userData->setExternalIds($external_ids);
                    }
                }

				$user_persistence_data = get_persistence_user_data( $user_email, $user_firstname, $user_lastname, $user_phone );
				if ( !empty( $user_persistence_data[ 'fn' ] ) ) $userData->setFirstName( $user_persistence_data[ 'fn' ] );
				if ( !empty( $user_persistence_data[ 'ln' ] ) ) $userData->setLastName( $user_persistence_data[ 'ln' ] );
				if ( !empty( $user_persistence_data[ 'em' ] ) ) $userData->setEmail( $user_persistence_data[ 'em' ] );
				if ( !empty( $user_persistence_data[ 'tel' ] ) ) $userData->setPhone( $user_persistence_data[ 'tel' ] );

				$user_id = $order->get_user_id();
				if ( $user_id && apply_filters( 'pys_send_meta_id', true ) ) {
					$login_id = get_user_meta( $user_id, '_socplug_social_id_Facebook', true );
					if ( !empty( $login_id ) ) {
						$userData->setFbLoginId( $login_id );
					}
				}

            } else {
                return ServerEventHelper::getRegularUserData();
            }

        } else {

            if(PixelYourSite\isEddActive() && isEventEnabled( 'edd_purchase_enabled' ) &&
                ($eddOrder ||  edd_is_success_page()) ) {

                if($eddOrder)
                    $payment_id = $eddOrder;
                else {
                    $payment_key = getEddPaymentKey();
                    $payment_id = (int) edd_get_purchase_id_by_key( $payment_key );
                }
                $user_info = edd_get_payment_meta_user_info($payment_id);
                $email = edd_get_payment_user_email($payment_id);
				$user_firstname = $user_lastname = $user_email = '';

				if ( isset($user_info[ 'first_name' ]) && $user_info[ 'first_name' ] ) {
					$user_firstname = $user_info[ 'first_name' ];
				}
				if (isset($user_info[ 'last_name' ]) &&  $user_info[ 'last_name' ] ) {
					$user_lastname = $user_info[ 'last_name' ];
				}
				if ( $email ) {
					$user_email = $email;
				}

				$user_persistence_data = get_persistence_user_data( $user_email, $user_firstname, $user_lastname, '' );
				if ( !empty( $user_persistence_data[ 'fn' ] ) ) $userData->setFirstName( $user_persistence_data[ 'fn' ] );
				if ( !empty( $user_persistence_data[ 'ln' ] ) ) $userData->setLastName( $user_persistence_data[ 'ln' ] );
				if ( !empty( $user_persistence_data[ 'em' ] ) ) $userData->setEmail( $user_persistence_data[ 'em' ] );
				if ( !empty( $user_persistence_data[ 'tel' ] ) ) $userData->setPhone( $user_persistence_data[ 'tel' ] );

                // Browser id saved on the payment, plus the account that owns it
                $external_ids = PixelYourSite\pys_get_order_external_ids(
                    edd_get_payment_meta( $payment_id, 'external_id', true ),
                    isset( $user_info[ 'id' ] ) ? $user_info[ 'id' ] : 0
                );
                if ( !empty( $external_ids ) ) {
                    $userData->setExternalIds( $external_ids );
                }

				if ( $user_info[ 'id' ] && apply_filters( 'pys_send_meta_id', true ) ) {
					$login_id = get_user_meta( $user_info[ 'id' ], '_socplug_social_id_Facebook', true );
					if ( !empty( $login_id ) ) {
						$userData->setFbLoginId( $login_id );
					}
				}

			} else {
                return ServerEventHelper::getRegularUserData();
            }
        }

        return $userData;
    }

    private static function getRegularUserData() {
        $user = wp_get_current_user();
        $userData = new UserData();
        if ( $user->ID ) {
            // get user regular data
			$user_firstname = $user->get( 'user_firstname' );
			$user_lastname = $user->get( 'user_lastname' );
			$user_phone = $user->get( 'billing_phone' );

            /**
             * Add common WooCommerce Advanced Matching params
             */
            if ( PixelYourSite\isWooCommerceActive() ) {
                // if first name is not set in regular wp user meta
				if ( empty( $user_firstname ) ) {
					$user_firstname = $user->get( 'billing_first_name' );
				}

                // if last name is not set in regular wp user meta
				if ( empty( $user_lastname ) ) {
					$user_lastname = $user->get( 'billing_last_name' );
				}

                if($user->get('billing_phone'))
                    $userData->setPhone($user->get('billing_phone'));
                if($user->get('billing_city'))
                    $userData->setCity($user->get('billing_city'));
                if($user->get('billing_state'))
                    $userData->setState($user->get('billing_state'));
                if($user->get('shipping_country'))
                    $userData->setCountryCode(strtolower($user->get('shipping_country')));
                if($user->get('billing_postcode')) {
                    $userData->setZipCode($user->get('billing_postcode'));
                }
            }
			$user_persistence_data = get_persistence_user_data( $user->get( 'user_email' ), $user_firstname, $user_lastname, $user_phone );
            $external_ids = PixelYourSite\pys_get_external_ids();
            if (!empty($external_ids)) {
                $userData->setExternalIds($external_ids);
            }

			$login_id = get_user_meta( $user->ID, '_socplug_social_id_Facebook', true );
			if ( !empty( $login_id ) && apply_filters( 'pys_send_meta_id', true ) ) {
				$userData->setFbLoginId( $login_id );
			}
        } else {
			$user_persistence_data = get_persistence_user_data( '', '', '', '' );
            $external_ids = PixelYourSite\pys_get_external_ids();
            if (!empty($external_ids)) {
                $userData->setExternalIds($external_ids);
            }
        }

		if ( !empty( $user_persistence_data[ 'fn' ] ) ) $userData->setFirstName( $user_persistence_data[ 'fn' ] );
		if ( !empty( $user_persistence_data[ 'ln' ] ) ) $userData->setLastName( $user_persistence_data[ 'ln' ] );
		if ( !empty( $user_persistence_data[ 'em' ] ) ) $userData->setEmail( $user_persistence_data[ 'em' ] );
		if ( !empty( $user_persistence_data[ 'tel' ] ) ) $userData->setPhone( $user_persistence_data[ 'tel' ] );

        return $userData;
    }

    static function paramsToCustomData($data) {

        if(isset($data['contents']) && is_array($data['contents'])) {
            $contents = array();
            foreach ($data['contents'] as $c) {
                $contents[] = new Content([
                    'product_id' => $c['id'],
                    'quantity'  => $c['quantity']
                ]);
            }
            $data['contents'] = $contents;
        } else {
            $data['contents'] = array();
        }

        $customData = new CustomData($data);
        $customProperties = array();


        if(isset($data['category_name'])) {
            $customData->setContentCategory($data['category_name']);
        }

        $custom_values = ['event_action','download_type','download_name','download_url','target_url','text','trigger','traffic_source','plugin','user_role','event_url','page_title',"post_type",'post_id','categories','tags','video_type',
            'video_id','video_title','event_trigger','link_type','tag_text',"URL",
            'form_id','form_class','form_submit_label','transactions_count','average_order',
            'shipping_cost','tax','total','shipping','coupon_used','post_category','landing_page'];


        $adding_custom_field = array();

        $eventsCustom = EventsCustom()->getEvents();
        foreach ($eventsCustom as $event)
        {
            $fbCustomEvents = $event->getFacebookCustomParams();

            foreach ($fbCustomEvents as $paramKey => $params)
            {
                if(!in_array($params['name'], $custom_values))
                {
                    $adding_custom_field[] = $params['name'];
                }
            }
        }
        $result_custom_values = array_merge($custom_values, $adding_custom_field);
        foreach ($result_custom_values as $val) {
            if(isset($data[$val])){
                $customProperties[$val] = $data[$val];
            }
        }

        $customData->setCustomProperties($customProperties);
        return $customData;
    }

    /**
     * Explicitly require Facebook ServerSide SDK class files.
     *
     * PHP's unserialize() may produce __PHP_Incomplete_Class objects when the
     * Composer autoloader hasn't resolved these classes yet (background HTTP
     * requests, cron context, OPCache timing issues, plugin conflicts, etc.).
     * Calling this method before any unserialize() that involves these classes
     * eliminates the race condition.
     */
    public static function requireServerSideClasses() {
        $base  = PYS_FREE_PATH . '/vendor_prefix/facebook/php-business-sdk/src/FacebookAds/Object/ServerSide/';
        $files = [ 'Event.php', 'UserData.php', 'CustomData.php', 'Content.php' ];
        foreach ( $files as $file ) {
            $path = $base . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
        // After Event.php: it extends it, and queued events unserialize into it.
        require_once __DIR__ . '/PysServerEvent.php';
    }

}