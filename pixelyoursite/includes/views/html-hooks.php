<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<div class="cards-wrapper cards-wrapper-style2 gap-24 hooks-wrapper">
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_by_gdpr - Disable send all pixels events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Disable send all pixels events, can by used for custom gdpr</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_disable_by_gdpr',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>

                </div>
            </div>
        </div>
    </div>
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_{pixel}_by_gdpr - Disable send pixel events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>{pixel} - facebook, ga, gtm, pinterest, bing, reddit, openai</p>
                    <p>The Google pixels share one name: pys_disable_analytics_by_gdpr stops GA and GTM everywhere, while pys_disable_ga_by_gdpr and pys_disable_gtm_by_gdpr reach only the no-script tags.</p>
                    <p>Disable some pixel events, can by used for custom gdpr</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_disable_facebook_by_gdpr',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_gdpr_ajax_enabled - Update gdpr pixel status</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Load latest gdpr pixel status before load web pixel. Can by used when server use page caching</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_gdpr_ajax_enabled',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_check_consent_by_gdpr - Consent status for GDPR</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Allows developers to programmatically override consent status for GDPR compliance. Receives the current consent value, allowing you to customize logic to determine whether consent should be enabled or disabled. Useful for integrating with third-party consent management solutions or custom privacy workflows.</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_check_consent_by_gdpr',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_event_data - Edit or add custom data to event</h4>
            </div>
            <div class="pro-badge-block">
                <?php renderProBadge(); ?>
                <?php cardCollapseSettings(); ?>
            </div>
        </div>
        <div class="card-body">
            <div class="pro-feature-container">
                <div class="flex-column-24gap">
                    <div class="double-line-height">
                        <p>Param: array $data, string $slug ,any $context</p>
                    </div>
                    <div class="example-block">
                        <label>Example:</label>
                        <pre class="copy_text">
    add_filter('pys_event_data',function ($data,$slug,$context) {
        if(get_current_user_id() == 0 ) {
            $data['params']['total'] = 0;
        }
        return $data;
    },10,3);<div class="copy-icon" data-toggle="pys-popover"
                 data-tippy-trigger="click" data-tippy-placement="bottom"
                 data-popover_id="copied-popover"></div></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_currencies_list - Add new currency in list, for custom events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: array $currencies</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_currencies_list',function ($currencies) {
    $currencies['PTH'] = 'Test';
    return $currencies;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_{edd or woo}_checkout_order_id - Use custom order id for purchase event</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>pys_edd_checkout_order_id - Edd plugin<br>pys_woo_checkout_order_id - WooCommerce plugin</p>
                    <p>Can by user for custom checkout page</p>
                    <p>Param: int $order_id</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_woo_checkout_order_id',function ($order_id) {
    if(isset($_GET['custom_order_param_with_id'])) {
        return $_GET['custom_order_param_with_id'];
    }
    return $order_id;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_woo_legacy_gateway_compat - Purchase event for gateways that drop the order key</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>The WooCommerce Purchase event needs to know the order is really the visitor's own. It normally does, because
                        the thank-you page carries the order key, the buyer is logged in, or the order was placed in the same session.</p>
                    <p>A few payment gateways return the buyer without the order key <b>and</b> with a cross-site POST, which drops the
                        session cookie — for those, enable this filter. It only covers orders created within the last hour that were
                        actually paid, and it never exposes customer personal data (email, phone, name, address).</p>
                    <p>Leave it off unless your Purchase event stopped firing after a gateway redirect.</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter( 'pys_woo_legacy_gateway_compat', '__return_true' );

// optional: widen the window (seconds, default 1 hour)
add_filter( 'pys_woo_legacy_gateway_compat_window', function () {
    return 2 * HOUR_IN_SECONDS;
} );<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_woo_request_can_access_order - Decide who may see an order's data</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Final say over whether the current request may read a WooCommerce order for tracking purposes. Use it to
                        support an unusual gateway, or to tighten access further.</p>
                    <p>Params: bool $allowed, int $order_id, string $context ('default' for tracking payloads, 'pii' for
                        personal data such as Advanced Matching)</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter( 'pys_woo_request_can_access_order', function ( $allowed, $order_id, $context ) {
    // your gateway returns ?my_gateway_ref=&lt;order id&gt;&amp;my_gateway_token=&lt;token&gt;
    if ( ! $allowed && 'pii' !== $context && isset( $_GET['my_gateway_token'] ) ) {
        return my_gateway_token_is_valid( $order_id, $_GET['my_gateway_token'] );
    }
    return $allowed;
}, 10, 3 );<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_validate_pixel_event - Disable some events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>You can disable some events depend on your logic</p>
                    <p>Param: bool $isActive, \PixelYourSite\PYSEvent $event, \PixelYourSite\Settings $pixel</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_validate_pixel_event',function ($isActive,$event,$pixel) {
    if($pixel->getSlug() == "facebook"
    && $event->getId() == "woo_purchase"
    && get_current_user_id() == 0
    ) {
        return false;
    }
    return $isActive;
},10,3);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_server_event_filter - Disable Facebook server events</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_disable_server_event_filter',function ($status) {
    if(get_current_user_id() == 0 ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_before_send_fb_server_event - Add custom data to  Facebook server event</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: FacebookAds\Object\ServerSide\Event $event,string $pixel_Id, string $eventId</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_before_send_fb_server_event',function ($event,$pixel_Id,$eventId) {
    if(get_current_user_id() == 0 ) {
        $event->setActionSource("not_registered");
    }
    return $event;
},10,3);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_pixel_disabled - Disable Pixel</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $isActive,string $pixelSlug</p>
                    <p>Return: Array</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_pixel_disabled',function ($isActive,$pixelSlug) {
    if(get_current_user_id() == 0 && $pixelSlug == 'facebook') {
        return ['all']; // Disable all pixels
    }
    return $isActive;
},11,2);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_pixel_disabled',function ($isActive,$pixelSlug) {
    if(get_current_user_id() == 0 && $pixelSlug == 'facebook') {
        return ['1123450378576095', '1300447800692613']; // Disables pixels that are in the array
    }
    return $isActive;
},11,2);<div class="copy-icon" data-toggle="pys-popover"
             data-tippy-trigger="click" data-tippy-placement="bottom"
             data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_{pixel}_ids - Add custom Pixel id</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p> {pixel} - facebook, ga, gtm, pinterest, bing, openai</p>
                    <p>Param: array $ids</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_facebook_ids',function ($ids) {
    if(get_current_user_id() == 0) {
        $ids[]='CUSTOM_PIXEL_ID';
    }
    return $ids;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_fb_advanced_matching - Add or edit facebook advanced matching params</h4>
            </div>
            <div class="pro-badge-block">
                <?php renderProBadge(); ?>
                <?php cardCollapseSettings(); ?>
            </div>
        </div>
        <div class="card-body">
            <div class="pro-feature-container">
                <div class="flex-column-24gap">
                    <div class="double-line-height">
                        <p>Param: array $params</p>
                    </div>
                    <div class="example-block">
                        <label>Example:</label>
                        <pre class="copy_text">
    add_filter('pys_fb_advanced_matching',function ($params) {
        if(get_current_user_id() == 0) {
            $params['fn'] = "not_registered";
            $params['ln'] = "not_registered";
        }
        return $params;
    });<div class="copy-icon" data-toggle="pys-popover"
            data-tippy-trigger="click" data-tippy-placement="bottom"
            data-popover_id="copied-popover"></div></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_fb_server_user_data - Add or edit facebook server user data</h4>
            </div>
            <div class="pro-badge-block">
                <?php renderProBadge(); ?>
                <?php cardCollapseSettings(); ?>
            </div>
        </div>
        <div class="card-body">
            <div class="pro-feature-container">
                <div class="flex-column-24gap">
                    <div class="double-line-height">
                        <p>Param: \PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\UserData $userData</p>
                    </div>
                    <div class="example-block">
                        <label>Example:</label>
                        <pre class="copy_text">
    add_filter('pys_fb_server_user_data',function ($userData) {
        if(get_current_user_id() == 0) {
            $userData->setFirstName("undefined");
            $userData->setLastName("undefined");
            $userData->setEmail("undefined");
        }
        return $userData;
    });<div class="copy-icon" data-toggle="pys-popover"
            data-tippy-trigger="click" data-tippy-placement="bottom"
            data-popover_id="copied-popover"></div></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>




    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_openai_advanced_matching - Add or edit OpenAI advanced matching params</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Used by the JavaScript Pixel and the Conversions API alike, so a change here reaches both channels.</p>
                    <p>OpenAI accepts only these keys: email_sha256, external_id_sha256, country, city, zip_code. There are no phone or name fields, and one unknown key makes the whole Conversions API request fail.</p>
                    <p>Param: array $params</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_openai_advanced_matching',function ($params) {
    if(get_current_user_id() == 0) {
        unset($params['email_sha256']);
    }
    return $params;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_openai_server_user_data - Add or edit the OpenAI Conversions API user object</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Server events only. Fields: obref, email_sha256, external_id_sha256, country, city, zip_code, ip_address, user_agent.</p>
                    <p>Hashes must be lowercase 64-character hex. Geo, IP and user agent travel raw; raw emails, raw external ids and phone numbers in any form are rejected by OpenAI.</p>
                    <p>Params: object $userData, int|null $wooOrder, int|null $eddOrder</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_openai_server_user_data',function ($userData, $wooOrder, $eddOrder) {
    if(get_current_user_id() == 0) {
        unset($userData->email_sha256);
    }
    return $userData;
}, 10, 3);<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_openai_currency_exponent - How many minor units one major unit has</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>OpenAI takes every amount as an integer number of minor units, so 10.00 USD travels as 1000. The exponent comes from the currency itself (ISO 4217): 0 for JPY, KRW and the other zero-decimal currencies, 3 for BHD, KWD and the rest of the three-decimal ones, 2 for everything else.</p>
                    <p>Deliberately not taken from the shop's price-decimals setting, which is a display preference. Use this filter only for a currency the list does not cover - returning the wrong exponent multiplies or divides every reported value by ten.</p>
                    <p>Params: int $exponent, string $currency</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_openai_currency_exponent',function ($exponent, $currency) {
    if($currency == 'XYZ') {
        return 0;
    }
    return $exponent;
}, 10, 2);<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_disable_all_cookie</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>disable all PYS cookies</p>
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_disable_all_cookie',function ($status) {
    $user = wp_get_current_user();
    $roles = ( array ) $user->roles;
    if(in_array('administrator', $roles) ) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="double-line-height">
                    <p>there are also filters to disable certain groups of cookies that work on the same principle</p>
                    <p><code>pys_disabled_start_session_cookie</code> - disable start_session & session_limit cookie</p>
                    <p><code>pys_disable_first_visit_cookie</code> - disable pys_first_visit cookie</p>
                    <p><code>pys_disable_landing_page_cookie</code> - disable pys_landing_page & last_pys_landing_page cookies</p>
                    <p><code>pys_disable_trafficsource_cookie</code> - disable pysTrafficSource & last_pysTrafficSource cookies</p>
                    <p><code>pys_disable_utmTerms_cookie</code> - disable ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content' ,'utm_term'] with prefix <code>pys_</code> and <code>last_pys_</code> cookies</p>
                    <p><code>pys_disable_utmId_cookie</code> - disable ['fbadid', 'gadid', 'padid', 'bingid'] with prefix <code>pys_</code> and <code>last_pys_</code> cookies</p>
                    <p><code>pys_disable_advance_data_cookie</code> - disable pys_advanced_data cookies</p>
                    <p><code>pys_disable_externalID_by_gdpr</code> - disable pbid(external_id) cookie</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_{mode name}_mode - Fire pixel with Google consent mode</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p> {mode name} - analytics_storage, ad_storage, ad_user_data, ad_personalization</p>
                    <p>Param: bool $mod</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_analytics_storage_mode',function ($mode) {
    if(get_current_user_id() == 0) {
        return true;
    }
    return $mode;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="double-line-height">
                    <p>Fire the pixel with consent mode "analytics_storage": "granted"</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_bing_ad_storage_mode - Fire the Bing with consent mode</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $mode</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_bing_ad_storage_mode',function ($mode) {
    if(get_current_user_id() == 0) {
        return true;
    }
    return $mode;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
                <div class="double-line-height">
                    <p>Fire the Bing with consent mode "ad_storage": "granted"</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_url_passthrough_mode - The filter turn ON/OFF the url_passthrough option</h4>
            </div>
            <div class="pro-badge-block">
                <?php renderProBadge(); ?>
                <?php cardCollapseSettings(); ?>
            </div>
        </div>
        <div class="card-body">
            <div class="pro-feature-container">
                <div class="flex-column-24gap">
                    <div class="double-line-height">
                        <p>Param: bool $status</p>
                    </div>
                    <div class="example-block">
                        <label>Example:</label>
                        <pre class="copy_text">
        add_filter('pys_url_passthrough_mode',function ($status) {
            if(get_current_user_id() == 0) {
                return true;
            }
        return $status;
    });<div class="copy-icon" data-toggle="pys-popover"
            data-tippy-trigger="click" data-tippy-placement="bottom"
            data-popover_id="copied-popover"></div></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_meta_ldu_mode - The filter turn ON/OFF Meta Limited Data Use option</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_meta_ldu_mode',function ($status) {
if(get_current_user_id() == 0) {
    return true;
}
return $status;
});<div class="copy-icon" data-toggle="pys-popover"
    data-tippy-trigger="click" data-tippy-placement="bottom"
    data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_send_meta_id - The filter allow/disallow sending the fb_login_id parameter from Social connect plugin</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_send_meta_id',function ($status) {
    if(get_current_user_id() == 1) {
        return false;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_reddit_ldu_mode - The filter turn ON/OFF Reddit Limited Data Use option</h4>
            </div>
			<?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_reddit_ldu_mode',function ($status) {
    if(get_current_user_id() == 0) {
        return true;
    }
    return $status;
});<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>
</div>

