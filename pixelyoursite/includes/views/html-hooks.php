<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<div class="cards-wrapper cards-wrapper-style2 gap-24 hooks-wrapper">

<style>
    /* Tokens from assets/scss/variables/_variables.scss. Scoped under #pys:
       the admin stylesheet styles headings, links and inputs at that
       specificity, and these have to win against it. */
    #pys #pys-hooks-toolbar {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
        padding: 2px 0 6px;
    }
    #pys #pys-hooks-search {
        width: 100%;
        max-width: 560px;
        padding: 11px 12px;
        border: 1px solid #BAC7D5;
        border-radius: 3px;
        color: #252A31;
        line-height: 125%;
    }
    #pys #pys-hooks-search::placeholder { color: #697D95; }
    #pys #pys-hooks-search:focus {
        outline: none;
        border-color: #0172CB;
        box-shadow: 0 0 0 1px #0172CB;
    }
    #pys #pys-hooks-count { color: #5D5D5D; font-size: 14px; }
    #pys #pys-hooks-toolbar nav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    /* White chips with the input border: the page background is #F1F2F8 and
       the selection grey #E8EDF1 disappears against it. */
    #pys #pys-hooks-toolbar nav a {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 12px;
        border: 1px solid #BAC7D5;
        border-radius: 3px;
        background: #fff;
        color: #00527C;
        font-size: 14px;
        text-decoration: none;
        line-height: 125%;
    }
    #pys #pys-hooks-toolbar nav a:hover {
        background: #E8F4FD;
        border-color: #0172CB;
    }
    #pys #pys-hooks-toolbar nav a:focus-visible {
        outline: 2px solid #0172CB;
        outline-offset: 1px;
    }
    #pys #pys-hooks-toolbar nav a .pys-hooks-chip-count { color: #697D95; }
    #pys #pys-hooks-toolbar nav a.pys-hooks-chip-empty {
        opacity: .45;
        pointer-events: none;
    }
    #pys .pys-hooks-section-title {
        width: 100%;
        margin: 20px 0 0;
        scroll-margin-top: 80px;
        color: #252A31;
        font-size: 18px;
        font-weight: 600;
        line-height: 134%;
    }
    #pys #pys-hooks-no-results { color: #5D5D5D; }
    #pys #pys-hooks-no-results a { color: #005BD3; }
</style>

    <div id="pys-hooks-toolbar" data-pys-hooks-keep="1">
        <input type="text" id="pys-hooks-search" placeholder="Search hooks and filters&hellip;"
               autocomplete="off" aria-label="Search hooks and filters">
        <span id="pys-hooks-count"></span>
        <nav aria-label="Hook sections">
            <a href="#pys-hooks-consent">Consent (GDPR)</a>
            <a href="#pys-hooks-privacy">Privacy modes</a>
            <a href="#pys-hooks-cookies">Cookies &amp; identifiers</a>
            <a href="#pys-hooks-events">Pixels &amp; event firing</a>
            <a href="#pys-hooks-userdata">Server-side user data &amp; matching</a>
            <a href="#pys-hooks-shops">WooCommerce &amp; EDD</a>
        </nav>
    </div>

    <p id="pys-hooks-no-results" style="display:none;">Nothing matches this search. <a href="#" id="pys-hooks-clear">Clear search</a></p>

    <h3 class="pys-hooks-section-title" id="pys-hooks-consent">Consent (GDPR)</h3>

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

    <h3 class="pys-hooks-section-title" id="pys-hooks-privacy">Privacy modes</h3>

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

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_openai_consent_mode - The filter turn ON/OFF the OpenAI restricted mode (opt_out)</h4>
            </div>
			<?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $status</p>
                    <p>When it returns true, every OpenAI event is sent with <span class="event-parameter-list">opt_out: true</span> &mdash; in OpenAI's words, the event is opted out of future user-level personalization. The conversion is still measured, so this is the counterpart of Meta Limited Data Use, not a way to stop tracking: to stop it, do not give the pixel consent at all.</p>
                    <p>Both copies of the event follow this filter &mdash; the browser Pixel and the Conversions API &mdash; so a deduplicated pair cannot disagree.</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_openai_consent_mode',function ($status) {
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

    <h3 class="pys-hooks-section-title" id="pys-hooks-cookies">Cookies &amp; identifiers</h3>

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
                <h4 class="secondary_heading_type2">pys_cookie_domain_override - Set the tracking-cookie domain manually</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: string $domain, string $host</p>
                    <p>PYS normally learns the cookie domain from the visitor's browser: the front-end script reports which domain the browser actually accepted, and server-side <span class="event-parameter-list">_fbp</span> waits for that answer. Return a domain here to skip the handshake &mdash; for sites where the script cannot run (aggressive page caching, a consent tool blocking it) or for unusual multi-domain setups.</p>
                    <p>The value must cover the current host (e.g. <span class="event-parameter-list">.example.com</span> for <span class="event-parameter-list">shop.example.com</span>), otherwise it is ignored. Return an empty string to let PYS decide. Register the filter no later than <span class="event-parameter-list">init</span> priority 20 &mdash; <span class="event-parameter-list">plugins_loaded</span> is the safe place.</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter( 'pys_cookie_domain_override', function ( $domain, $host ) {
    return '.example.com';
}, 10, 2 );<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_fb_cookie_lifetime_days - Lifetime of the Meta _fbp and _fbc cookies</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: int $days</p>
                    <p>90 days by default &mdash; the same lifetime Meta's own pixel gives <span class="event-parameter-list">_fbp</span>. This is deliberately separate from the &laquo;First Visit&raquo; cookie duration setting, which controls the attribution cookies (landing page, traffic source, UTMs) and not the advertising identifiers.</p>
                    <p>The value is clamped between 1 and 400 days: browsers refuse to keep a cookie longer than 400 days, so a larger number would only pretend.</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter( 'pys_fb_cookie_lifetime_days', function ( $days ) {
    return 30;
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
                <h4 class="secondary_heading_type2">pys_{platform}_cookie_lifetime_days - Lifetime of the click-id cookies</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>{platform} - openai, or pinterest (needs the PixelYourSite Pinterest add-on); the TikTok variant exists in PixelYourSite PRO</p>
                    <p>Param: int $days</p>
                    <p>PYS stores each platform's ad click id in a first-party cookie (<span class="event-parameter-list">pys_ttclid</span>, <span class="event-parameter-list">pys_epik</span>, <span class="event-parameter-list">__oppref</span>) so a purchase sent later &mdash; from the events queue, a payment webhook, an admin order-status change &mdash; still carries the click that brought the buyer.</p>
                    <p>Defaults: TikTok 90 days (TikTok asks integrators to store the click id for 28 days or more), Pinterest 365 days (matching Pinterest's own <span class="event-parameter-list">_epik</span> cookie), OpenAI 90 days. Values are clamped between 1 and 400 days.</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter( 'pys_pinterest_cookie_lifetime_days', function ( $days ) {
    return 30;
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
                <h4 class="secondary_heading_type2">pys_use_capi_param_builder - Enable Meta's Conversions API Parameter Builder library</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Param: bool $enabled</p>
                    <p>PYS bundles Meta's <span class="event-parameter-list">capi-param-builder</span> library, switched off by default: everything it adds &mdash; millisecond creation time, the computed subdomain index, click-id recovery from the referrer &mdash; is already done by PYS itself, and PYS keeps its own IP detection and browser-verified cookie domain, which are stronger than the library's.</p>
                    <p>Turn it on to let the library produce the <span class="event-parameter-list">_fbp</span> / <span class="event-parameter-list">_fbc</span> values instead. It still runs behind the PYS consent checks and the cookie-domain handshake.</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter( 'pys_use_capi_param_builder', '__return_true' );<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-style3 hook-card">
        <div class="card-header card-header-style2 disable-card-wrap d-flex justify-content-between align-items-center">
            <div class="disable-card align-items-center">
                <h4 class="secondary_heading_type2">pys_external_id_user_value - Change the account id sent as an external ID</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Params: string $value, int $user_id</p>
                    <p>When a visitor is logged in, their WordPress user ID travels alongside the browser ID as a second external ID, so the same person is recognised across devices and browsers. This filter receives the ID <em>before</em> it is hashed; whatever you return is SHA-256 hashed unless it already is a hash, which is the only format the browser Pixel and the Conversions API send identically.</p>
                    <p>Namespace it here if one Pixel serves several sites, where account 5 is a different person on each of them. Whatever you return must stay the same over time &mdash; changing it later starts a new identity and discards the matching built up so far.</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_external_id_user_value',function ($value, $user_id) {
    return 'shop-' . $user_id;
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
                <h4 class="secondary_heading_type2">pys_external_ids - Change the whole set of external IDs for an event</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Params: array $ids, int $user_id</p>
                    <p>Meta's Conversions API takes <span class="event-parameter-list">external_id</span> as one value or a list of them, and Pinterest's API takes a list; this is that list. It holds the browser ID first, then the ID stored for a logged-in visitor, then the hashed account ID. Return an empty array to send none.</p>
                    <p>Keep the browser ID in the set. The Pixel sends a single value, and matching only holds together while that value is one of the ones the server sends. Anything you add here is sent as you return it, so hash it yourself &mdash; the values already in the list are hashes.</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_external_ids',function ($ids, $user_id) {
    $crm = $user_id ? get_user_meta($user_id, 'crm_contact_id', true) : '';
    if ($crm) {
        $ids[] = hash('sha256', strtolower(trim($crm)));
    }
    return array_values(array_unique(array_filter($ids)));
}, 10, 2);<div class="copy-icon" data-toggle="pys-popover"
        data-tippy-trigger="click" data-tippy-placement="bottom"
        data-popover_id="copied-popover"></div></pre>
                </div>
            </div>
        </div>
    </div>

    <h3 class="pys-hooks-section-title" id="pys-hooks-events">Pixels &amp; event firing</h3>

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
                    <p> {pixel} - facebook, ga, gtm, openai</p>
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

    <h3 class="pys-hooks-section-title" id="pys-hooks-userdata">Server-side user data &amp; matching</h3>

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
                <h4 class="secondary_heading_type2">pys_openai_advanced_matching - Add or edit OpenAI advanced matching params</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>Used by the JavaScript Pixel and the Conversions API alike, so a change here reaches both channels.</p>
                    <p>OpenAI accepts only these keys: email_sha256, phone_number_sha256, external_id_sha256, first_name_sha256, last_name_sha256, country, city, region, postal_code. Anything else is dropped before it is sent.</p>
                    <p>One value per key here — that is what the browser Pixel takes. The Conversions API accepts several per field; to reach those, filter pys_openai_matching_values instead.</p>
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
                <h4 class="secondary_heading_type2">pys_openai_matching_values - Edit the OpenAI matching values as lists</h4>
            </div>
            <?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <div class="flex-column-24gap">
                <div class="double-line-height">
                    <p>The same identity as pys_openai_advanced_matching, but before it is reduced to one value per field: each key holds an ORDERED list. The Conversions API takes up to three emails and two phone numbers, and uses them in the order given — the browser Pixel takes only the first of each.</p>
                    <p>Keys are the same nine; values are arrays of already-normalised, already-hashed strings. Filter this to add a value the plugin cannot know about, or to reorder them; filter pys_openai_advanced_matching to change what the browser sends.</p>
                    <p>Params: array $values, int|null $wooOrder, int|null $eddOrder</p>
                </div>
                <div class="example-block">
                    <label>Example:</label>
                    <pre class="copy_text">
add_filter('pys_openai_matching_values',function ($values) {
    // A second address for the same customer, tried after the first.
    $values['email_sha256'][] = hash('sha256', 'billing@example.com');
    return $values;
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

    <h3 class="pys-hooks-section-title" id="pys-hooks-shops">WooCommerce &amp; EDD</h3>

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

</div>
<script>
(function () {
    var input = document.getElementById('pys-hooks-search');
    if (!input) { return; }
    var wrapper  = input.closest('.hooks-wrapper');
    var cards    = Array.prototype.slice.call(wrapper.querySelectorAll('.hook-card'))
        .filter(function (c) { return !c.hasAttribute('data-pys-hooks-keep'); });
    var headings = Array.prototype.slice.call(wrapper.querySelectorAll('.pys-hooks-section-title'));
    var chips    = Array.prototype.slice.call(document.querySelectorAll('#pys-hooks-toolbar nav a'));
    var empty    = document.getElementById('pys-hooks-no-results');
    var counter  = document.getElementById('pys-hooks-count');
    var clear    = document.getElementById('pys-hooks-clear');

    function sectionCards(heading) {
        var el = heading.nextElementSibling, list = [];
        while (el && !el.classList.contains('pys-hooks-section-title')) {
            if (el.classList.contains('hook-card')) { list.push(el); }
            el = el.nextElementSibling;
        }
        return list;
    }

    // Per-section counts on the chips, computed from the page itself so the
    // Free and PRO copies never drift.
    chips.forEach(function (chip) {
        var id = chip.getAttribute('href').slice(1);
        var heading = document.getElementById(id);
        if (!heading) { return; }
        var n = document.createElement('span');
        n.className = 'pys-hooks-chip-count';
        n.textContent = sectionCards(heading).length;
        chip.appendChild(n);
    });

    function apply() {
        var q = input.value.toLowerCase().trim();
        var shown = 0;

        cards.forEach(function (card) {
            var hit = !q || card.textContent.toLowerCase().indexOf(q) !== -1;
            card.style.display = hit ? '' : 'none';
            if (hit) { shown++; }
        });

        headings.forEach(function (heading) {
            var visible = sectionCards(heading).some(function (c) {
                return c.style.display !== 'none';
            });
            heading.style.display = visible ? '' : 'none';
            var chip = document.querySelector('#pys-hooks-toolbar nav a[href="#' + heading.id + '"]');
            if (chip) { chip.classList.toggle('pys-hooks-chip-empty', !visible); }
        });

        counter.textContent = q
            ? 'Showing ' + shown + ' of ' + cards.length
            : cards.length + ' hooks and filters';
        if (empty) { empty.style.display = shown ? 'none' : ''; }
    }

    input.addEventListener('input', apply);
    if (clear) {
        clear.addEventListener('click', function (e) {
            e.preventDefault();
            input.value = '';
            apply();
            input.focus();
        });
    }
    apply();
})();
</script>
