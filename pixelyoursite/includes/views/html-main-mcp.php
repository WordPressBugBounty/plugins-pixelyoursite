<?php
/**
 * PYS MCP settings tab — rendered inside html-wrapper-single-page.php via
 * the `pys_admin_pixelyoursite_mcp` action. `$viewModel` is provided by
 * `\PixelYourSite\MCP\AdminPage::renderPageContent()`.
 *
 * @var array $viewModel
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $viewModel ) || ! is_array( $viewModel ) ) {
	return;
}

$tokenState      = (string) ( $viewModel['token_state'] ?? '' );
$tokenActive     = 'active' === $tokenState;
$tokens          = is_array( $viewModel['tokens'] ?? null ) ? $viewModel['tokens'] : array();
$tokenMax        = (int) ( $viewModel['token_max'] ?? 25 );
$isPro           = (bool) ( $viewModel['is_pro'] ?? false );
$endpointUrl     = (string) ( $viewModel['endpoint_url'] ?? '' );
$recentActivity  = is_array( $viewModel['recent_activity'] ?? null ) ? $viewModel['recent_activity'] : array();
$infraChecks     = is_array( $viewModel['infra_checks'] ?? null ) ? $viewModel['infra_checks'] : array();

// Compact "time ago" string for header timestamps.
$humanTime = static function ( $ts ) {
	if ( null === $ts ) {
		return '—';
	}
	$delta = time() - (int) $ts;
	if ( $delta < 0 ) {
		return date_i18n( 'Y-m-d H:i:s', (int) $ts );
	}
	return human_time_diff( (int) $ts, time() ) . ' ago';
};

$statusIcon = static function ( $ok ) {
	if ( $ok ) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 12 10 17 19 7"></polyline></svg>';
	}
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="6" y1="18" x2="18" y2="6"></line></svg>';
};
?>

<div class="cards-wrapper cards-wrapper-style2 gap-24 setting-wrapper pys-mcp-admin">
    <!-- ============================================================= WHAT IS MCP -->
    <div class="card card-style6 card-static pys-mcp-info-card">
        <div class="card-header card-header-style2">
            <h4 class="secondary_heading_type2"><?php esc_html_e( 'What is MCP?', 'pys' ); ?></h4>
        </div>
        <div class="card-body">
            <p class="text-gray mb-24"><?php esc_html_e( 'MCP (Model Context Protocol) lets AI assistants connect directly to this site and manage PixelYourSite settings through conversation. No copy-pasting, no manual navigation. Works with Claude (claude.ai and Claude Desktop), Cursor, Windsurf, VS Code with GitHub Copilot, Claude Code, and any other MCP-compatible client.', 'pys' ); ?></p>

            <h4 class="secondary_heading mb-8"><?php esc_html_e( 'What your AI assistant can do with access', 'pys' ); ?></h4>
            <ul class="mb-24">
                <li><?php esc_html_e( 'Read: audit tracking health, read pixel and event configuration, check Facebook and Pinterest CAPI status', 'pys' ); ?></li>
                <li><?php esc_html_e( 'Write: toggle WooCommerce and EDD events, content ID settings, automatic events, create and manage custom events', 'pys' ); ?></li>
                <li><?php esc_html_e( 'Write operations always require your explicit confirmation before proceeding', 'pys' ); ?></li>
            </ul>

            <h4 class="secondary_heading mb-8"><?php esc_html_e( 'How access works', 'pys' ); ?></h4>
            <p class="text-gray mb-24"><?php esc_html_e( 'PYS MCP uses the WordPress Abilities API to expose tools to AI assistants. Each token you generate grants an AI assistant access through a secure endpoint. Tokens can be set to read-only if you want to allow auditing without allowing changes.', 'pys' ); ?></p>
            <p class="text-gray"><?php esc_html_e( 'This server exposes only PixelYourSite tools. The WordPress Abilities API is a shared system. Other plugins may register their own abilities, but PYS\'s MCP endpoint is scoped to PYS tools only by design.', 'pys' ); ?></p>
        </div>
    </div>
    <!-- ============================================================= TOKENS -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Authentication tokens', 'pys');?></h4>
			<?php if ( !empty( $tokens ) ) : ?>
                <button type="button" class="btn-small btn-gray btn-small-icon secondary_heading loadable" id="pys-mcp-revoke-all-btn"><i class="icon-delete"></i><?php _e( 'Revoke all', 'pys' ); ?></button>
			<?php endif; ?>
        </div>
        <div class="card-body">
            <p class="text-gray mb-24">
				<?php _e( 'Create a separate token per client / agent (Claude Desktop, a teammate, an automation). Each is revocable on its own; writes are attributed to the token in the activity log. Only the SHA-256 hash is stored — the raw token is shown once, at creation.', 'pys' ); ?>
            </p>

            <!-- New-token reveal (shown once, right after creation) -->
            <div id="pys-mcp-token-reveal" style="display:none;">
                <div class="alert alert-warning mb-16">
                    <p><strong><?php _e( 'Copy this token now.', 'pys' ); ?></strong> <?php _e( 'It will not be shown again. We only store its SHA-256 hash on the server.', 'pys' ); ?></p>
                </div>
                <div class="example-block mb-16">
                    <pre class="copy_text" id="pys-mcp-token-reveal-value"><div class="copy-icon" data-toggle="pys-popover"
                             data-tippy-trigger="click" data-tippy-placement="bottom"
                             data-popover_id="copied-popover"></div></pre>
                </div>
                <button type="button" class="btn btn-primary btn-primary-type2 mb-24" id="pys-mcp-dismiss-reveal-btn"><?php _e( 'I saved it — refresh list', 'pys' ); ?></button>
            </div>

            <!-- Existing tokens -->
            <div id="pys-mcp-token-list" class="mb-24">
				<?php if ( empty( $tokens ) ) : ?>
                    <div class="pys-mcp-empty">
                        <span class="pys-mcp-empty__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="4.5"></circle><path d="M10.7 12.3 19 4"></path><path d="m16 7 3 3"></path><path d="m14 9 2 2"></path></svg>
                        </span>
                        <p class="pys-mcp-empty__title"><?php _e( 'No tokens yet', 'pys' ); ?></p>
                        <p class="pys-mcp-empty__text"><?php _e( 'Create your first token below to connect Claude Desktop (or any MCP client) to this site. Give each client its own token so you can revoke them one at a time.', 'pys' ); ?></p>
                    </div>
				<?php else : ?>
                    <table class="pys-mcp-activity mb-24">
                        <thead>
                        <tr>
                            <th><?php _e( 'Label', 'pys' ); ?></th>
                            <th><?php _e( 'Created', 'pys' ); ?></th>
                            <th><?php _e( 'Last used', 'pys' ); ?></th>
                            <th><?php _e( 'Owner', 'pys' ); ?></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
						<?php foreach ( $tokens as $tok ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( (string) ( $tok['label'] ?? '' ) ); ?></strong></td>
                                <td class="pys-mcp-activity__when"><?php echo esc_html( $humanTime( $tok['created_at'] ?? null ) ); ?></td>
                                <td class="pys-mcp-activity__when"><?php echo esc_html( $humanTime( $tok['last_used_at'] ?? null ) ); ?></td>
                                <td class="pys-mcp-activity__ip"><?php echo esc_html( (string) ( $tok['owner_name'] ?? '' ) ); ?></td>
                                <td>
                                    <button type="button" class="btn-small btn-gray secondary_heading loadable pys-mcp-revoke-one"
                                            data-id="<?php echo esc_attr( (string) ( $tok['id'] ?? '' ) ); ?>"
                                            data-label="<?php echo esc_attr( (string) ( $tok['label'] ?? '' ) ); ?>"><?php _e( 'Revoke', 'pys' ); ?></button>
                                </td>
                            </tr>
						<?php endforeach; ?>
                        </tbody>
                    </table>
				<?php endif; ?>
            </div>

            <!-- Create token -->
            <div class="d-flex align-items-center" style="gap: 12px; flex-wrap: wrap;">
                <input type="text" class="input-standard" id="pys-mcp-token-label" style="width: auto; min-width: 300px;"
                       maxlength="60" placeholder="<?php esc_attr_e( 'Token label (e.g. Claude Desktop)', 'pys' ); ?>"/>
                <button type="button" class="btn btn-primary loadable" id="pys-mcp-generate-btn"><?php _e( 'Create token', 'pys' ); ?></button>
                <span class="text-gray"><?php echo esc_html( sprintf( /* translators: 1: current count, 2: max */ __( '%1$d of %2$d tokens', 'pys' ), count( $tokens ), $tokenMax ) ); ?></span>
            </div>

            <div class="pys-mcp-flash alert mt-24" style="display:none;"></div>
        </div>
    </div>

    <!-- ============================================================= SERVER URL -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2">
            <h4 class="secondary_heading_type2"><?php esc_html_e( 'MCP Server URL', 'pys' ); ?></h4>
        </div>
        <div class="card-body">
            <p class="text-gray mb-16"><?php esc_html_e( 'The endpoint your MCP client connects to. Paste this URL — together with a token above — into any MCP client that accepts a server URL. The setup steps below already include it.', 'pys' ); ?></p>
            <p class="text-gray mb-16"><?php esc_html_e( 'This site needs to be reachable on the public internet for cloud-based clients like claude.ai or Perplexity to connect — a local-only or firewalled site will not work with those. Production sites should also use HTTPS.', 'pys' ); ?></p>
            <div class="example-block">
                <pre class="copy_text"><?php echo esc_html( $endpointUrl ); ?><div class="copy-icon" data-toggle="pys-popover"
                        data-tippy-trigger="click" data-tippy-placement="bottom"
                        data-popover_id="copied-popover"></div></pre>
            </div>
        </div>
    </div>
    <!-- ====================================================== CONNECT YOUR AI CLIENT -->
    <div class="card card-style6">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h4 class="secondary_heading_type2"><?php esc_html_e( 'Connect your AI client', 'pys' ); ?></h4>
            </div>
        </div>

        <div class="card-body" style="display: block;">
            <div class="gap-24">
                <!-- Claude (claude.ai / Desktop app / Cowork / mobile) via custom connector -->
                <div class="card card-style6">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <h4 class="secondary_heading_type2"><?php _e( 'Claude (claude.ai, Desktop app, Cowork, mobile)', 'pys' ); ?></h4>
			            <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body">
                        <p class="text-gray mb-16"><?php esc_html_e( 'One setup covers claude.ai, the Claude Desktop app, Claude Cowork, and the Claude mobile apps — connectors run through Anthropic\'s cloud, not your device.', 'pys' ); ?></p>
                        <p class="text-gray mb-16"><?php esc_html_e( 'Note: fixed-token authentication via request headers is currently in beta on Claude\'s side. It works reliably, but the setup screen may change.', 'pys' ); ?></p>
                        <ol class="mb-16">
                            <li><?php esc_html_e( 'Settings → Connectors → "+" → Add custom connector', 'pys' ); ?></li>
                            <li><?php esc_html_e( 'Type: Remote', 'pys' ); ?></li>
                            <li><?php esc_html_e( 'Name: pixelyoursite (or anything you like)', 'pys' ); ?></li>
                            <li><?php esc_html_e( 'Paste the MCP Server URL from above', 'pys' ); ?></li>
                            <li><?php esc_html_e( 'Advanced settings → Headers helper → add a header named Authorization with value:', 'pys' ); ?></li>
                        </ol>

                        <div class="example-block mb-16">
                <pre class="copy_text pys-mcp-token-snippet">Bearer YOUR_TOKEN_HERE<div class="copy-icon" data-toggle="pys-popover"
                                                                                                       data-tippy-trigger="click" data-tippy-placement="bottom"
                                                                                                       data-popover_id="copied-popover"></div></pre>
                        </div>
                        <ol start="6" class="mb-16">
                            <li><?php esc_html_e( 'Click Add', 'pys' ); ?></li>
                            <li><?php esc_html_e( 'In any chat: "+" → Connectors → toggle it on', 'pys' ); ?></li>
                        </ol>
                        <p class="text-gray"><?php esc_html_e( 'Team/Enterprise accounts: an Owner adds it once under Organization settings → Connectors → Add → Custom → Web. Members then connect from Customize → Connectors.', 'pys' ); ?></p>
                    </div>
                </div>

                <!-- Claude Desktop config file (alternative) -->
                <div class="card card-style6">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <h4 class="secondary_heading_type2"><?php _e( 'Claude Desktop (config file — alternative)', 'pys' ); ?></h4>
			            <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body">
                        <p class="mb-16">
				            <?php esc_html_e( 'Use this only if the custom connector above doesn\'t fit your setup. Add this to your claude_desktop_config.json, then fully restart Claude Desktop from the system tray. If you just created a token above, it is already filled into the snippet below — otherwise replace YOUR_TOKEN_HERE with your token.', 'pys' ); ?>
                        </p>
                        <ul class="mb-16">
                            <li><strong><?php _e( 'Windows:', 'pys' ); ?></strong> <code>%APPDATA%\Claude\claude_desktop_config.json</code></li>
                            <li><strong><?php _e( 'macOS:', 'pys' ); ?></strong> <code>~/Library/Application Support/Claude/claude_desktop_config.json</code></li>
                            <li><strong><?php _e( 'Linux:', 'pys' ); ?></strong> <code>~/.config/Claude/claude_desktop_config.json</code></li>
                        </ul>
			            <?php
			            $endpointForJson = $endpointUrl;
			            $isHttp          = 0 === strpos( $endpointForJson, 'http://' );

			            $sharedArgs  = "        \"-y\", \"mcp-remote\",\n";
			            $sharedArgs .= "        \"" . $endpointForJson . "\",\n";
			            if ( $isHttp ) {
				            $sharedArgs .= "        \"--allow-http\",\n";
			            }
			            $sharedArgs .= "        \"--header\", \"Authorization: Bearer YOUR_TOKEN_HERE\"\n";

			            $snippetWin = "  \"mcpServers\": {\n";
			            $snippetWin .= "    \"pys-free\": {\n";
			            $snippetWin .= "      \"command\": \"cmd\",\n";
			            $snippetWin .= "      \"args\": [\n";
			            $snippetWin .= "        \"/c\", \"npx\",\n";
			            $snippetWin .= $sharedArgs;
			            $snippetWin .= "      ]\n";
			            $snippetWin .= "    }\n";
			            $snippetWin .= "  }\n";

			            $snippetUnix = "  \"mcpServers\": {\n";
			            $snippetUnix .= "    \"pys-free\": {\n";
			            $snippetUnix .= "      \"command\": \"npx\",\n";
			            $snippetUnix .= "      \"args\": [\n";
			            $snippetUnix .= $sharedArgs;
			            $snippetUnix .= "      ]\n";
			            $snippetUnix .= "    }\n";
			            $snippetUnix .= "  }\n";
			            ?>

                        <h5 class="mb-8"><?php _e( 'Windows', 'pys' ); ?></h5>
                        <div class="example-block mb-24">
                <pre class="copy_text pys-mcp-token-snippet"><?php echo esc_html( $snippetWin ); ?>
                    <div class="copy-icon" data-toggle="pys-popover"
                         data-tippy-trigger="click" data-tippy-placement="bottom"
                         data-popover_id="copied-popover"></div></pre>
                        </div>

                        <h5 class="mb-8"><?php _e( 'macOS / Linux', 'pys' ); ?></h5>
                        <div class="example-block mb-24">
                <pre class="copy_text pys-mcp-token-snippet"><?php echo esc_html( $snippetUnix ); ?>
                    <div class="copy-icon" data-toggle="pys-popover"
                         data-tippy-trigger="click" data-tippy-placement="bottom"
                         data-popover_id="copied-popover"></div></pre>
                        </div>

			            <?php if ( $isHttp ) : ?>
                            <p class="mb-16">
                                <code>--allow-http</code> <?php esc_html_e( 'is required because the endpoint is plain HTTP (local dev environment). Production sites should be HTTPS — drop the flag once a real cert is in place.', 'pys' ); ?>
                            </p>
			            <?php endif; ?>
                        <p>
				            <?php esc_html_e( 'If npx isn\'t found, install', 'pys' ); ?> <a href="https://nodejs.org/en" target="_blank" rel="noopener">Node.js</a> <?php esc_html_e( '(it ships with npx). On macOS/Linux a GUI-launched Claude Desktop may not inherit your shell PATH — use the full path to npx (find it with which npx) if you hit a spawn npx ENOENT error.', 'pys' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Claude Code -->
                <div class="card card-style6">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <h4 class="secondary_heading_type2"><?php _e( 'Claude Code', 'pys' ); ?></h4>
			            <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-8"><?php esc_html_e( 'Run this in your terminal:', 'pys' ); ?></h5>
			            <?php
			            $claudeCodeCmd = 'claude mcp add --transport http pys-free ' . $endpointUrl . ' --header "Authorization: Bearer YOUR_TOKEN_HERE"';
			            ?>
                        <div class="example-block">
                <pre class="copy_text pys-mcp-token-snippet"><?php echo esc_html( $claudeCodeCmd ); ?><div class="copy-icon" data-toggle="pys-popover"
                                                                                                           data-tippy-trigger="click" data-tippy-placement="bottom"
                                                                                                           data-popover_id="copied-popover"></div></pre>
                        </div>
                    </div>
                </div>

                <!-- Cursor -->
                <div class="card card-style6">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <h4 class="secondary_heading_type2"><?php _e( 'Cursor', 'pys' ); ?></h4>
			            <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-8"><?php esc_html_e( 'Settings → Tools and Integrations → New MCP Server → transport type HTTP → paste the URL below, then add a header named Authorization with the value below:', 'pys' ); ?></h5>
                        <div class="example-block mb-16">
                <pre class="copy_text"><?php echo esc_html( $endpointUrl ); ?><div class="copy-icon" data-toggle="pys-popover"
                                                                                   data-tippy-trigger="click" data-tippy-placement="bottom"
                                                                                   data-popover_id="copied-popover"></div></pre>
                        </div>
                        <div class="example-block">
                <pre class="copy_text pys-mcp-token-snippet">Bearer YOUR_TOKEN_HERE<div class="copy-icon" data-toggle="pys-popover"
                                                                                                       data-tippy-trigger="click" data-tippy-placement="bottom"
                                                                                                       data-popover_id="copied-popover"></div></pre>
                        </div>
                    </div>
                </div>

                <!-- Windsurf -->
                <div class="card card-style6">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <h4 class="secondary_heading_type2"><?php _e( 'Windsurf', 'pys' ); ?></h4>
			            <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-8"><?php esc_html_e( 'Cascade panel → hammer/wrench icon → View raw config → add this server entry:', 'pys' ); ?></h5>
			            <?php
			            $windsurfJson  = "{\n";
			            $windsurfJson .= "  \"mcpServers\": {\n";
			            $windsurfJson .= "    \"pixelyoursite\": {\n";
			            $windsurfJson .= "      \"serverUrl\": \"" . $endpointUrl . "\",\n";
			            $windsurfJson .= "      \"headers\": {\n";
			            $windsurfJson .= "        \"Authorization\": \"Bearer YOUR_TOKEN_HERE\"\n";
			            $windsurfJson .= "      }\n";
			            $windsurfJson .= "    }\n";
			            $windsurfJson .= "  }\n";
			            $windsurfJson .= "}";
			            ?>
                        <div class="example-block">
                <pre class="copy_text pys-mcp-token-snippet"><?php echo esc_html( $windsurfJson ); ?><div class="copy-icon" data-toggle="pys-popover"
                                                                                                          data-tippy-trigger="click" data-tippy-placement="bottom"
                                                                                                          data-popover_id="copied-popover"></div></pre>
                        </div>
                    </div>
                </div>

                <!-- VS Code + GitHub Copilot -->
                <div class="card card-style6">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <h4 class="secondary_heading_type2"><?php _e( 'VS Code + GitHub Copilot', 'pys' ); ?></h4>
			            <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-8"><?php esc_html_e( 'Command Palette → MCP: Add Server → HTTP → paste the URL below, then add a header named Authorization with the value below:', 'pys' ); ?></h5>
                        <div class="example-block mb-16">
                <pre class="copy_text"><?php echo esc_html( $endpointUrl ); ?><div class="copy-icon" data-toggle="pys-popover"
                                                                                   data-tippy-trigger="click" data-tippy-placement="bottom"
                                                                                   data-popover_id="copied-popover"></div></pre>
                        </div>
                        <div class="example-block">
                <pre class="copy_text pys-mcp-token-snippet">Bearer YOUR_TOKEN_HERE<div class="copy-icon" data-toggle="pys-popover"
                                                                                                       data-tippy-trigger="click" data-tippy-placement="bottom"
                                                                                                       data-popover_id="copied-popover"></div></pre>
                        </div>
                    </div>
                </div>

                <!-- Not yet supported -->
                <div class="card card-style6">
                    <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                        <h4 class="secondary_heading_type2"><?php esc_html_e( 'Not currently supported', 'pys' ); ?></h4>
	                    <?php cardCollapseSettings(); ?>
                    </div>
                    <div class="card-body">
                        <p class="text-gray mb-8"><strong>ChatGPT:</strong> <?php esc_html_e( 'Developer Mode connectors only support sign-in (OAuth) or no authentication — there\'s no field for a fixed token, so this can\'t connect today.', 'pys' ); ?></p>
                        <p class="text-gray"><strong><?php esc_html_e( 'Gemini app (gemini.google.com):', 'pys' ); ?></strong> <?php esc_html_e( 'custom connections can be added by URL, but the setup is built around sign-in credentials rather than a fixed token, and access is limited to certain accounts and countries. Not recommended yet — Gemini CLI (a separate developer tool) does work with a header, if you need Google\'s model specifically.', 'pys' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= READ-ONLY -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Write protection', 'pys');?></h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div>
                    <div class="d-flex align-items-center">
		                <?php \PixelYourSite\PYS()->render_switcher_input( \PixelYourSite\MCP\Capabilities::OPTION_READ_ONLY_ENABLED ); ?>
                        <h4 class="switcher-label secondary_heading ml-12 mb-0"><?php _e( 'Enable read-only mode', 'pys' ); ?></h4>
                    </div>
                    <p class="text-gray mt-4">
                        <?php _e( "Enable read-only mode to allow auditing without allowing changes: every <code>set_*</code> tool returns <code>Read-only mode is enabled.</code> instead of writing, while read tools continue to work normally.<br>This toggle applies to <strong>every connected client at once</strong> — it is not per-token, so turning it on blocks writes for all your AI assistants together, not just one.", "pys" ); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================= TEST CONNECTION + INSTRUCTIONS -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Connection test', 'pys');?></h4>
            <button type="button" class="btn btn-primary btn-primary-type2 loadable" id="pys-mcp-test-btn"><?php esc_html_e( "Run checks", "pys" ); ?></button>
        </div>
        <div class="card-body">
            <ul id="pys-mcp-check-list" class="pys-mcp-checks">
		        <?php foreach ( $infraChecks as $check ) :
			        $checkOk = ! empty( $check['ok'] );
			        ?>
                    <li class="pys-mcp-check pys-mcp-check--<?php echo $checkOk ? 'ok' : 'fail'; ?>">
                        <span class="pys-mcp-check__icon" aria-hidden="true">
                            <?php echo $statusIcon( $checkOk ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG ?>
                        </span>
                        <span class="pys-mcp-check__text">
                            <span class="pys-mcp-check__label"><?php echo esc_html( (string) $check['label'] ); ?></span>
                            <span class="pys-mcp-check__detail"><?php echo esc_html( (string) $check['detail'] ); ?></span>
                        </span>
                    </li>
		        <?php endforeach; ?>
            </ul>

            <div class="pys-mcp-flash alert mt-24" style="display:none;"></div>
        </div>
    </div>

    <!-- ============================================================ TERMINAL CURL -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Manual end-to-end check (curl)', 'pys');?></h4>
        </div>
        <div class="card-body">
            <div class="mb-24">
                <?php _e( "Run this from a terminal to confirm the endpoint accepts your Bearer token. Expected output: <code>200 OK</code> with a <code>Mcp-Session-Id</code> header.", "pys" ); ?>
            </div>

            <?php
                $curl  = 'curl -i -X POST "' . $endpointUrl . '" \\' . "\n";
                $curl .= '  -H "Authorization: Bearer YOUR_TOKEN_HERE" \\' . "\n";
                $curl .= '  -H "Accept: application/json, text/event-stream" \\' . "\n";
                $curl .= '  -H "Content-Type: application/json" \\' . "\n";
                $curl .= '  -d \'{"jsonrpc":"2.0","id":0,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"curl","version":"0.1"}}}\'';
                ?>

            <div class="example-block">
                <pre class="copy_text pys-mcp-token-snippet"><?php echo esc_html( $curl ); ?>
                    <div class="copy-icon" data-toggle="pys-popover"
                        data-tippy-trigger="click" data-tippy-placement="bottom"
                        data-popover_id="copied-popover"></div></pre>
            </div>
        </div>
    </div>

    <!-- =========================================================== TROUBLESHOOTING -->
    <div class="card card-style6">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e( 'Troubleshooting', 'pys' ); ?></h4>
			<?php cardCollapseSettings(); ?>
        </div>
        <div class="card-body">
            <table class="pys-mcp-activity">
                <thead>
                <tr>
                    <th><?php _e( 'What you see', 'pys' ); ?></th>
                    <th><?php _e( 'What to do', 'pys' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><?php _e( '"Couldn\'t reach the MCP server"', 'pys' ); ?></td>
                    <td><?php _e( 'Confirm your site is publicly reachable and the header/token was saved correctly.', 'pys' ); ?></td>
                </tr>
                <tr>
                    <td><?php _e( '401 / token rejected', 'pys' ); ?></td>
                    <td><?php _e( 'Generate a new token above and re-enter it in your client.', 'pys' ); ?></td>
                </tr>
                <tr>
                    <td><code>spawn npx ENOENT</code></td>
                    <td><?php _e( 'Node.js isn\'t installed or isn\'t on your PATH. Install Node.js or use the full path to npx (find it with which npx).', 'pys' ); ?></td>
                </tr>
                <tr>
                    <td><?php _e( 'No response at all', 'pys' ); ?></td>
                    <td><?php _e( 'Confirm wp-json is reachable from outside your network — a firewall or local-only setup will block remote clients.', 'pys' ); ?></td>
                </tr>
                <tr>
                    <td><?php _e( 'REST route check fails above', 'pys' ); ?></td>
                    <td><?php _e( 'A security plugin may be blocking the REST API. Temporarily disable REST API restrictions and re-run the check.', 'pys' ); ?></td>
                </tr>
                <tr>
                    <td><?php _e( 'Assistant mentions a rate limit or "loop detected" error', 'pys' ); ?></td>
                    <td><?php _e( 'The server is protecting itself from too many rapid or repeated calls. Wait a moment and ask again — no action needed on your end.', 'pys' ); ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- =========================================================== RECENT ACTIVITY -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Recent activity', 'pys');?></h4>
            <div class="d-flex align-items-center" style="gap: 8px;">
                <a class="btn-small btn-gray secondary_heading" href="<?php echo esc_url( (string) ( $viewModel['log_page_url'] ?? '' ) ); ?>"><?php _e( 'View full log', 'pys' ); ?></a>
                <button type="button" class="btn-small btn-gray btn-small-icon secondary_heading loadable" id="pys-mcp-clear-log-btn"><i class="icon-delete"></i><?php esc_html_e( "Clear log", "pys" ); ?></button>
            </div>
        </div>
        <div class="card-body">
            <div id="pys-mcp-activity-wrap">
		        <?php if ( empty( $recentActivity ) ) : ?>
                    <p><?php esc_html_e( "No write-tool calls recorded yet.", "pys" ); ?></p>
		        <?php else : ?>
                    <table class="pys-mcp-activity">
                        <thead>
                        <tr>
                            <th class="pys-mcp-activity__status-col"></th>
                            <th><?php esc_html_e( "When", "pys" ); ?></th>
                            <th><?php esc_html_e( "Tool", "pys" ); ?></th>
                            <th><?php esc_html_e( "Token", "pys" ); ?></th>
                            <th><?php esc_html_e( "Note", "pys" ); ?></th>
                            <th><?php esc_html_e( "IP", "pys" ); ?></th>
                        </tr>
                        </thead>
                        <tbody>
				        <?php
				        foreach ( $recentActivity as $entry ) :
					        $status   = (string) ( $entry['result_status'] ?? '' );
					        $statusOk = 'success' === $status;
					        ?>
                            <tr>
                                <td class="pys-mcp-activity__status-cell">
                                    <span class="pys-mcp-status pys-mcp-status--<?php echo $statusOk ? 'ok' : 'fail'; ?>"
                                          title="<?php echo esc_attr( $status ); ?>"
                                          aria-label="<?php echo esc_attr( $status ); ?>">
									    <?php echo $statusIcon( $statusOk ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG ?>
                                    </span>
                                </td>
                                <td class="pys-mcp-activity__when">
							        <?php echo esc_html( isset( $entry['ts'] ) ? date_i18n( 'Y-m-d H:i:s', (int) $entry['ts'] ) : '—' ); ?>
                                </td>
                                <td><code><?php echo esc_html( (string) ( $entry['tool'] ?? '' ) ); ?></code></td>
                                <td><?php echo esc_html( (string) ( $entry['token'] ?? '' ) ); ?></td>
                                <td class="pys-mcp-activity__note"><?php echo esc_html( (string) ( $entry['mcp_note'] ?? '' ) ); ?></td>
                                <td class="pys-mcp-activity__ip"><?php echo esc_html( (string) ( $entry['actor_ip'] ?? '' ) ); ?></td>
                            </tr>
				        <?php endforeach; ?>
                        </tbody>
                    </table>
		        <?php endif; ?>
            </div>

            <div class="pys-mcp-flash alert mt-24" style="display:none;"></div>
        </div>
    </div>
</div>


<script>
(function ($) {
	'use strict';

	// AdminPage::renderPageContent() passes these via the view-model array.
	let PYS_MCP = {
		ajaxUrl:       <?php echo wp_json_encode( $viewModel['ajax_url'] ); ?>,
		nonce:         <?php echo wp_json_encode( $viewModel['nonce'] ); ?>,
		actions: {
			generate:  <?php echo wp_json_encode( $viewModel['ajax_generate'] ); ?>,
			revoke:    <?php echo wp_json_encode( $viewModel['ajax_revoke'] ); ?>,
			test:      <?php echo wp_json_encode( $viewModel['ajax_test_conn'] ); ?>,
			clearLog:  <?php echo wp_json_encode( $viewModel['ajax_clear_log'] ); ?>
		}
	};

	function flash($scope, type, message) {
		let $flash = ($scope && $scope.length)
			? $scope.closest('.card').find('.pys-mcp-flash').first()
			: $();
		if (!$flash.length) {
			$flash = $('.pys-mcp-flash').first();
		}
		$flash.removeClass('alert-success alert-danger alert-warning').addClass('alert-' + type)
			.text(message).show();
		// auto-hide success after 3s
		if (type === 'success') {
			setTimeout(function () { $flash.fadeOut(); }, 3000);
		}
	}

	function ajax(action, data) {
		return $.post(PYS_MCP.ajaxUrl, $.extend({
			action: action,
			nonce: PYS_MCP.nonce
		}, data || {}));
	}

	function statusIcon(ok) {
		if (ok) {
			return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 12 10 17 19 7"></polyline></svg>';
		}
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="6" y1="18" x2="18" y2="6"></line></svg>';
	}

	function lockBtn($btn) {
		$btn.prop('disabled', true).addClass('is-loading');
	}
	function unlockBtn($btn) {
		$btn.prop('disabled', false).removeClass('is-loading');
	}

	function pysConfirm(opts, onConfirm) {
		if (typeof $.confirm === 'function') {
			$.confirm({
				title: opts.title,
				content: '<p>' + opts.content + '</p>',
				type: 'pys',
				typeAnimated: true,
				autoClose: 'cancelAction|10000',
				buttons: {
					yesAction: {
						text: opts.yesText || 'Yes',
						btnClass: opts.yesBtnClass || 'btn-pys btn-pys-red',
						action: onConfirm
					},
					cancelAction: { text: opts.noText || 'Cancel' }
				}
			});
			return;
		}
		if (window.confirm(opts.title + '\n\n' + opts.content)) {
			onConfirm();
		}
	}

	// ---------- Inject a fresh token into the connection snippets ----------
	var TOKEN_PLACEHOLDER = 'YOUR_TOKEN_HERE';

	function injectTokenIntoSnippets(token) {
		if (!token) {
			return;
		}
		$('.pys-mcp-token-snippet').each(function () {
			let $pre = $(this);

			if (typeof $pre.attr('data-token-template') === 'undefined') {
				let original = '';
				$pre.contents().each(function () {
					if (this.nodeType === 3) {
						original += this.nodeValue;
					}
				});
				$pre.attr('data-token-template', original);
			}
			let filled = ($pre.attr('data-token-template') || '').split(TOKEN_PLACEHOLDER).join(token);
			// Swap only the text node(s); keep the trailing copy-icon element intact.
			$pre.contents().filter(function () {
				return this.nodeType === 3;
			}).remove();
			$pre.prepend(document.createTextNode(filled));
		});
	}

	// ---------- Create token ----------
	function handleGenerate(e) {
		let $btn = $(e.currentTarget);
		let label = ($('#pys-mcp-token-label').val() || '').trim();
		if (!label) {
			flash($btn, 'warning', 'Enter a label first (e.g. the client or person this token is for).');
			$('#pys-mcp-token-label').focus();
			return;
		}
		lockBtn($btn);
		ajax(PYS_MCP.actions.generate, { label: label })
			.done(function (res) {
				if (!res || !res.success) {
					flash($btn, 'danger', (res && res.data && res.data.message) || 'Failed to create token.');
					return;
				}
				$('#pys-mcp-token-reveal-value').contents().filter(function () {
					return this.nodeType === 3; // strip any prior token text node
				}).remove();
				$('#pys-mcp-token-reveal-value').prepend(res.data.token);
				$('#pys-mcp-token-reveal').show();
				injectTokenIntoSnippets(res.data.token);
				$('#pys-mcp-token-label').val('');
				flash($btn, 'success', 'Token "' + (res.data.label || '') + '" created — copy it now.');
			})
			.fail(function () {
				flash($btn, 'danger', 'Network error during create.');
			})
			.always(function () {
				unlockBtn($btn);
			});
	}

	function handleRevokeOne(e) {
		let $btn = $(e.currentTarget);
		let id = $btn.data('id');
		let label = $btn.data('label') || 'this token';
		pysConfirm({
			title:   'Revoke "' + label + '"?',
			content: 'The MCP client using this token will lose access immediately. Other tokens are unaffected.',
			yesText: 'Yes, revoke'
		}, function () {
			lockBtn($btn);
			ajax(PYS_MCP.actions.revoke, { id: id })
				.done(function (res) {
					if (!res || !res.success) {
						flash($btn, 'danger', 'Failed to revoke token.');
						return;
					}
					flash($btn, 'success', 'Token revoked. Reloading…');
					setTimeout(function () { window.location.reload(); }, 500);
				})
				.fail(function () {
					flash($btn, 'danger', 'Network error during revoke.');
				})
				.always(function () {
					unlockBtn($btn);
				});
		});
	}

	function handleRevokeAll(e) {
		let $btn = $(e.currentTarget);
		pysConfirm({
			title:   'Revoke ALL tokens?',
			content: 'Every MCP client will lose access immediately. You will need to create and re-paste new tokens. This cannot be undone.',
			yesText: 'Yes, revoke all'
		}, function () {
			lockBtn($btn);
			ajax(PYS_MCP.actions.revoke, { all: 1 })
				.done(function (res) {
					if (!res || !res.success) {
						flash($btn, 'danger', 'Failed to revoke tokens.');
						return;
					}
					flash($btn, 'success', 'Revoked ' + (res.data.removed || 0) + ' tokens. Reloading…');
					setTimeout(function () { window.location.reload(); }, 500);
				})
				.fail(function () {
					flash($btn, 'danger', 'Network error during revoke.');
				})
				.always(function () {
					unlockBtn($btn);
				});
		});
	}

	// ---------- Test connection ----------
	function handleTestConnection(e) {
		let $btn = $(e.currentTarget);
		lockBtn($btn);
		ajax(PYS_MCP.actions.test)
			.done(function (res) {
				if (!res || !res.success) {
					flash($btn, 'danger', 'Test connection failed.');
					return;
				}
				let $list = $('#pys-mcp-check-list').empty();
				(res.data.checks || []).forEach(function (c) {
					let ok = !!c.ok;
					let $icon = $('<span>', {
						'class': 'pys-mcp-check__icon',
						'aria-hidden': 'true'
					}).html(statusIcon(ok));

					let $label = $('<span>', { 'class': 'pys-mcp-check__label' }).text(c.label),
                        $detail = $('<span>', { 'class': 'pys-mcp-check__detail' }).text(c.detail),
					    $text = $('<span>', { 'class': 'pys-mcp-check__text' }).append($label, $detail);

					$list.append(
						$('<li>', { 'class': 'pys-mcp-check pys-mcp-check--' + (ok ? 'ok' : 'fail') })
							.append($icon, $text)
					);
				});

				let allOk = (res.data.checks || []).every(function (c) { return !!c.ok; });
				flash($btn, allOk ? 'success' : 'warning', allOk ? 'All checks passed.' : 'Some checks failed — see list above.');
			})
			.fail(function () {
				flash($btn, 'danger', 'Network error.');
			})
			.always(function () {
				unlockBtn($btn);
			});
	}

	// ---------- Clear log ----------
	function handleClearLog(e) {
		e.preventDefault();
		let $btn = $(e.currentTarget);
		pysConfirm({
			title:   'Clear the entire MCP activity log?',
			content: 'All recorded write-tool calls will be deleted. This cannot be undone.',
			yesText: 'Yes, clear log'
		}, function () {
			lockBtn($btn);
			ajax(PYS_MCP.actions.clearLog)
				.done(function (res) {
					if (!res || !res.success) {
						flash($btn, 'danger', 'Failed to clear log.');
						return;
					}
					$('#pys-mcp-activity-wrap').html('<p>' + <?php echo wp_json_encode( __( "No write-tool calls recorded yet.", "pys" ) ); ?> + '</p>');
					flash($btn, 'success', 'Deleted ' + (res.data.deleted || 0) + ' entries.');
				})
				.fail(function () {
					flash($btn, 'danger', 'Network error during clear log.');
				})
				.always(function () {
					unlockBtn($btn);
				});
		});
	}

	// ---------- Copy to clipboard ----------
	function handleCopyToken(e) {
		let $btn = $(e.currentTarget);
		let token = $('#pys-mcp-token-reveal-value').text();
		if (!token) return;
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(token).then(function () {
				flash($btn, 'success', 'Token copied to clipboard.');
			}, function () {
				flash($btn, 'warning', 'Clipboard API blocked — select the token manually.');
			});
		} else {
			flash($btn, 'warning', 'Clipboard API unavailable — select the token manually.');
		}
	}

	$(function () {
		$(document)
			.on('click', '#pys-mcp-generate-btn', handleGenerate)
			.on('click', '.pys-mcp-revoke-one', handleRevokeOne)
			.on('click', '#pys-mcp-revoke-all-btn', handleRevokeAll)
			.on('click', '#pys-mcp-test-btn', handleTestConnection)
			.on('click', '#pys-mcp-clear-log-btn', handleClearLog)
			.on('click', '#pys-mcp-copy-token-btn', handleCopyToken)
			.on('click', '#pys-mcp-dismiss-reveal-btn', function () {
				window.location.reload();
			});
	});
})(jQuery);
</script>