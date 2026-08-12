<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * "API & CLI" documentation panel for the Site Profile page. Static reference
 * for the REST API (pys-profile/v1) and WP-CLI commands, so agencies can
 * discover and use the automation surface without leaving the admin.
 */

$rest_base = untrailingslashit( rest_url( 'pys-profile/v1' ) );

/**
 * Render a copyable code block using the plugin's copy component.
 *
 * @param string $code
 * @return void
 */
$copy = function ( $code ): void {
    ?>
    <div class="example-block pys-api-example">
        <pre class="copy_text"><?php echo esc_html( $code ); ?><div class="copy-icon" data-toggle="pys-popover" data-tippy-trigger="click" data-tippy-placement="bottom" data-popover_id="copied-popover"></div></pre>
    </div>
    <?php
};
?>

<div class="gap-24 mt-24 pys-api-docs">

    <!-- ===================== Overview ===================== -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2">
            <h4 class="secondary_heading_type2">Automation — REST API &amp; WP-CLI</h4>
        </div>
        <div class="card-body">
            <p class="mb-8">
                Everything on the <b>Export</b> and <b>Import</b> tabs is also available programmatically —
                same engine, so results are identical. Built for agencies replicating a configuration across
                many sites.
            </p>
            <p class="pys-api-text">
                Three operations: <b>export</b> a profile, <b>import</b> it (a safe dry-run by default), and
                <b>restore</b> the automatic pre-import backup. Imports are non-destructive until you explicitly
                apply them, and every apply snapshots the previous settings first.
            </p>
        </div>
    </div>

    <!-- ===================== Authentication ===================== -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2">
            <h4 class="secondary_heading_type2">Authentication</h4>
        </div>
        <div class="card-body">
            <p class="pys-api-text mb-16">
                Every endpoint requires the <code>manage_pys</code> capability. For cross-site automation, use
                <b>WordPress Application Passwords</b> (WP admin → <i>Users → Profile → Application Passwords</i>)
                and send them as HTTP Basic auth (<code>-u "user:app password"</code>). A browser session
                (cookie&nbsp;+&nbsp;<code>X-WP-Nonce</code>) also works.
            </p>
            <?php
            $message = "On a local / non-HTTPS site WordPress hides Application Passwords. Enable them with
                <code>define( 'WP_ENVIRONMENT_TYPE', 'local' );</code> in <code>wp-config.php</code>, or the
                filter <code>add_filter( 'wp_is_application_passwords_available', '__return_true' );</code>.";

            renderWarningMessage( $message );
            ?>
        </div>
    </div>

    <!-- ===================== REST API ===================== -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2">
            <h4 class="secondary_heading_type2">REST API</h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div class="pys-api-table-scroll">
                    <table class="widefat striped pys-api-table">
                        <thead>
                        <tr><th>Method</th><th>Endpoint</th><th>Purpose</th></tr>
                        </thead>
                        <tbody>
                        <tr><td><code>GET</code></td><td><code>/export</code></td><td>Return the site profile as JSON.</td></tr>
                        <tr><td><code>POST</code></td><td><code>/import</code></td><td>Validate + dry-run diff (default), or apply.</td></tr>
                        <tr><td><code>POST</code></td><td><code>/restore</code></td><td>Roll back to the last pre-import backup.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Base URL for this site:</div>
	                <?php $copy( $rest_base ); ?>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Parameters</div>
                    <div class="pys-api-table-scroll">
                        <table class="widefat striped pys-api-table">
                            <tbody>
                            <tr><td><code>/export</code> · <code>modules</code></td><td>CSV of module slugs (e.g. <code>facebook,ga</code>). Omit for all.</td></tr>
                            <tr><td><code>/export</code> · <code>include_tokens</code></td><td>Include credentials / API tokens. Default <code>false</code>.</td></tr>
                            <tr><td><code>/export</code> · <code>include_automatic_events</code></td><td>Include core Automatic Events. Default <code>true</code>.</td></tr>
                            <tr><td><code>/import</code> · <code>profile</code></td><td><b>Required.</b> The exported profile object.</td></tr>
                            <tr><td><code>/import</code> · <code>apply</code></td><td><code>false</code> = dry-run diff only (default); <code>true</code> writes.</td></tr>
                            <tr><td><code>/import</code> · <code>include_tokens</code></td><td>Auto-plan: import credentials. Default <code>false</code>.</td></tr>
                            <tr><td><code>/import</code> · <code>modules</code></td><td>Auto-plan: restrict to these slugs.</td></tr>
                            <tr><td><code>/import</code> · <code>remap</code></td><td>Array of <code>{from,to}</code> URL/domain replacements.</td></tr>
                            <tr><td><code>/import</code> · <code>plan</code></td><td>Explicit granular plan; overrides the auto-plan.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Export the current site (with credentials) to a file</div>
	                <?php $copy( 'curl -u "admin:APP_PASSWORD" \\' . "\n" . '  "' . $rest_base . '/export?include_tokens=true" \\' . "\n" . '  -o profile.json' ); ?>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Dry-run against a target (nothing is written)</div>
	                <?php $copy( 'curl -u "admin:APP_PASSWORD" -H "Content-Type: application/json" \\' . "\n" . '  --data \'{"profile": \'"$(cat profile.json)"\', "include_tokens": true}\' \\' . "\n" . '  "' . $rest_base . '/import"' ); ?>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Apply for real, then (if needed) roll back</div>
	                <?php $copy( 'curl -u "admin:APP_PASSWORD" -H "Content-Type: application/json" \\' . "\n" . '  --data \'{"profile": \'"$(cat profile.json)"\', "include_tokens": true, "apply": true}\' \\' . "\n" . '  "' . $rest_base . '/import"' . "\n\n" . 'curl -u "admin:APP_PASSWORD" -X POST "' . $rest_base . '/restore"' ); ?>
                </div>
            </div>
       </div>
    </div>

    <!-- ===================== WP-CLI ===================== -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2">
            <h4 class="secondary_heading_type2">WP-CLI</h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div class="pys-api-table-scroll">
                    <table class="widefat striped pys-api-table">
                        <thead>
                        <tr><th>Command</th><th>Purpose</th></tr>
                        </thead>
                        <tbody>
                        <tr><td><code>wp pys profile export [&lt;file&gt;]</code></td><td>Export to a file or STDOUT.</td></tr>
                        <tr><td><code>wp pys profile import &lt;file&gt;</code></td><td>Dry-run by default; <code>--apply</code> writes.</td></tr>
                        <tr><td><code>wp pys profile restore</code></td><td>Roll back the last import.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div>
                    <p class="pys-api-text">
                        Flags: <code>--modules=&lt;csv&gt;</code>, <code>--include-tokens</code>,
                        <code>--no-automatic-events</code> (export); <code>--apply</code>, <code>--include-tokens</code>,
                        <code>--modules=&lt;csv&gt;</code>, <code>--plan=&lt;file&gt;</code>,
                        <code>--remap-from=&lt;url&gt; --remap-to=&lt;url&gt;</code>, <code>--format=&lt;table|json&gt;</code>,
                        <code>--yes</code> (import). Run <code>wp help pys profile &lt;command&gt;</code> for the full list.
                    </p>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Export a full profile (with tokens)</div>
	                <?php $copy( 'wp pys profile export profile.json --include-tokens' ); ?>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Preview changes on the target (dry-run)</div>
	                <?php $copy( 'wp pys profile import profile.json' ); ?>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Apply everything, no prompt, then roll back if needed</div>
	                <?php $copy( 'wp pys profile import profile.json --apply --include-tokens --yes' . "\n" . 'wp pys profile restore --yes' ); ?>
                </div>

                <div>
                    <div class="pys-profile-subtitle">Apply with a staging → production domain swap</div>
	                <?php $copy( 'wp pys profile import profile.json --apply \\' . "\n" . '  --remap-from=https://staging.example --remap-to=https://example.com' ); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== Notes ===================== -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2">
            <h4 class="secondary_heading_type2">Good to know</h4>
        </div>
        <div class="card-body">
            <ul class="pys-api-notes">
                <li><b>Dry-run first.</b> <code>POST /import</code> and <code>wp ... import</code> only report a diff until you pass <code>apply</code> / <code>--apply</code>.</li>
                <li><b>Automatic backup.</b> Every apply snapshots the previous settings; <code>restore</code> rolls them back.</li>
                <li><b>Tokens are opt-in.</b> Credentials are excluded from exports unless you pass <code>include_tokens</code> / <code>--include-tokens</code>.</li>
                <li><b>What never travels.</b> License credentials, plugin access (<code>admin_permissions</code>), and the MCP read-only posture (<code>mcp_read_only_enabled</code>) are excluded from every profile — so a shared file can't hand over control of a site.</li>
                <li><b>Auto vs explicit.</b> Without a <code>plan</code>, import replicates each module's settings and its main pixel. Use an explicit <code>plan</code> for fine-grained per-pixel control.</li>
            </ul>
        </div>
    </div>

</div>
