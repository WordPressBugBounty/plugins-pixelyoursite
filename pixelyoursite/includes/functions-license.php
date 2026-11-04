<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/* -------------------------------------------------------------------------- *
 *  Agency Mode domain-block handling.
 *
 *  When the PYS Agency Mode license server marks this domain as blocked, its
 *  activate_license / check_license responses carry a `site_blocked` status
 *  and the `pys_eam_blocked` / `pys_eam_message` / `pys_eam_blocked_at` fields.
 *  The block state is stored per slug (each add-on independently) so background
 *  pings and updates can be stopped for the affected products.
 * -------------------------------------------------------------------------- */

/**
 * Option name that stores the block state for a given plugin slug.
 *
 * @param string $slug
 * @return string
 */
function pys_domain_block_option_name( $slug ) {
	return 'pys_' . $slug . '_domain_block';
}

/**
 * Returns the raw block data for a slug (empty array when none).
 *
 * @param string $slug
 * @return array
 */
function get_domain_block_data( $slug ) {
	return pys_domain_block_state( $slug );
}

/**
 * Per-request store for one slug's block state.
 *
 * pys_get_option() runs an uncached $wpdb->get_var() on every call, and when no
 * block is stored it falls through to get_option() as well. The state is read
 * far more often than that can afford: once per registered pixel, plus every
 * isSuperPackActive() call in the event pipeline, which runs in loops. One
 * front-end request was measured issuing more than 1600 of these queries.
 * Memoising leaves one query per slug per request.
 *
 * The state is written in exactly two places, set_domain_block() and
 * clear_domain_block(), and both pass the new value back here, so the memo
 * cannot serve a stale block to the request that changed it.
 *
 * @param string     $slug
 * @param array|null $fresh Value just written, stored instead of reading.
 * @return array
 */
function pys_domain_block_state( $slug, $fresh = null ) {
	static $cache = array();

	if ( is_array( $fresh ) ) {
		$cache[ $slug ] = $fresh;

		return $fresh;
	}

	if ( ! array_key_exists( $slug, $cache ) ) {
		$data           = pys_get_option( pys_domain_block_option_name( $slug ) );
		$cache[ $slug ] = is_array( $data ) ? $data : array();
	}

	return $cache[ $slug ];
}

/**
 * Whether the current domain is blocked for the given slug.
 *
 * @param string $slug
 * @return bool
 */
function is_domain_blocked( $slug ) {
	$data = get_domain_block_data( $slug );

	return ! empty( $data['blocked'] );
}

/**
 * Builds the "buy your own license" link embedded in the default block messages.
 *
 * @param string $utm_source UTM source (differs per block level).
 * @return string HTML anchor.
 */
function pys_pricing_link( $utm_source ) {
	$url = add_query_arg(
		array(
			'utm_source'   => $utm_source,
			'utm_medium'   => 'plugin_admin',
			'utm_campaign' => 'agency_domain_block',
		),
		'https://pixelyoursite.com/'
	);

	return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">pixelyoursite.com</a>';
}

/**
 * Sanitizes a block message for display, allowing only a single link. Block
 * messages (default and custom agency-typed) may contain an <a> tag, so they
 * must be rendered with this instead of esc_html().
 *
 * @param string $message
 * @return string
 */
function pys_block_message_kses( $message ) {
	return wp_kses(
		(string) $message,
		array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
	);
}

/**
 * Default (Soft) block message used when the server did not provide one.
 * Contains a link — render with pys_block_message_kses(), not esc_html().
 *
 * @return string
 */
function pys_default_block_message() {
	return sprintf(
		/* translators: %s: link to the PixelYourSite pricing page. */
		__( 'This domain has been blocked by the license owner. This license can no longer be activated or reactivated here, and license and update checks will keep failing until the block is lifted. Tracking is still running for now. Contact the license owner for assistance, or purchase your own license directly at %s to keep everything running independently.', 'pys' ),
		pys_pricing_link( 'soft_block_notice' )
	);
}

/**
 * Persists the block state for a slug.
 *
 * @param string $slug
 * @param string $message    Reason text from the server.
 * @param string $blocked_at When the server blocked the domain.
 * @param string $type       Block level: 'soft' (default) or 'hard'.
 * @return void
 */
function set_domain_block( $slug, $message, $blocked_at, $type = 'soft' ) {
	$message = is_string( $message ) ? trim( $message ) : '';
	$type    = ( 'hard' === $type ) ? 'hard' : 'soft';

	$state = array(
		'blocked'    => true,
		'message'    => $message,
		'blocked_at' => (string) $blocked_at,
		'type'       => $type,
		'detected'   => time(),
	);

	pys_update_option( pys_domain_block_option_name( $slug ), $state );
	pys_domain_block_state( $slug, $state );
}

/**
 * Removes the block state for a slug (used for auto-recovery once unblocked).
 *
 * @param string $slug
 * @return void
 */
function clear_domain_block( $slug ) {
	if ( is_domain_blocked( $slug ) ) {
		// No pys_delete_option exists; storing an empty array clears the block
		// (is_domain_blocked() / is_hard_blocked() then read false).
		pys_update_option( pys_domain_block_option_name( $slug ), array() );
		pys_domain_block_state( $slug, array() );
	}
}

/**
 * Whether the server marked this slug's block as HARD.
 *
 * Informational only in the free plugin, and deliberately not used to switch
 * anything off. A block here behaves as Soft whatever level the server sent:
 * tracking keeps running, for the core plugin and for a blocked add-on alike,
 * the notices carry the Soft wording (see get_domain_block_message()), and the
 * only thing a block stops is the update and license traffic for the plugin it
 * belongs to, which runs off is_domain_blocked(). The Hard lockdown lives in the
 * Pro plugin.
 *
 * The level is still stored, so the Pro plugin and the store agree on what was
 * set, and so this stays readable if the rule ever changes.
 *
 * @param string $slug
 * @return bool
 */
function is_hard_blocked( $slug ) {
	$data = get_domain_block_data( $slug );

	return ! empty( $data['blocked'] ) && isset( $data['type'] ) && 'hard' === $data['type'];
}

/**
 * Returns the block reason shown for a slug.
 *
 * Always the Soft text, whatever level the server sent and whatever message it
 * stored. Nothing in the free plugin stops tracking on a block, for the core
 * plugin or for a blocked add-on (see is_hard_blocked()), so the Hard wording
 * ("tracking has stopped") would tell the site owner something that is not true
 * here.
 *
 * @param string $slug Kept for call-site symmetry with the Pro plugin.
 * @return string
 */
function get_domain_block_message( $slug ) {
	return pys_default_block_message();
}

/**
 * Reads a field from an API response, tolerating both object and array shapes.
 *
 * @param object|array $license_data
 * @param string       $key
 * @return mixed|null
 */
function pys_response_value( $license_data, $key ) {
	if ( is_object( $license_data ) && isset( $license_data->$key ) ) {
		return $license_data->$key;
	}

	if ( is_array( $license_data ) && isset( $license_data[ $key ] ) ) {
		return $license_data[ $key ];
	}

	return null;
}

/**
 * Detects an Agency Mode block in a license API response.
 *
 * @param object|array $license_data
 * @return bool
 */
function pys_response_is_blocked( $license_data ) {
	if ( empty( $license_data ) || is_wp_error( $license_data ) ) {
		return false;
	}

	$flag = pys_response_value( $license_data, 'pys_eam_blocked' );
	if ( ! empty( $flag ) ) {
		return true;
	}

	if ( 'site_blocked' === pys_response_value( $license_data, 'license' ) ) {
		return true;
	}

	if ( 'site_blocked' === pys_response_value( $license_data, 'error' ) ) {
		return true;
	}

	return false;
}

/**
 * The block reason to persist for a license API response.
 *
 * The server's own text is ignored on purpose: see get_domain_block_message().
 * The level itself is still read and stored, it just does not switch anything
 * off here.
 *
 * @param object|array $license_data Kept for call-site symmetry with the Pro plugin.
 * @return string
 */
function pys_response_block_message( $license_data ) {
	return pys_default_block_message();
}

/**
 * Extracts the block timestamp from a license API response.
 *
 * @param object|array $license_data
 * @return string
 */
function pys_response_blocked_at( $license_data ) {
	$blocked_at = pys_response_value( $license_data, 'pys_eam_blocked_at' );

	return is_string( $blocked_at ) ? $blocked_at : '';
}

/**
 * Extracts the block level ('soft'|'hard') from a license API response.
 *
 * Deliberately degrades to 'soft' on anything missing or unrecognized (older
 * agency add-on, malformed response, network hiccup) — a parsing issue must
 * never silently escalate to Hard and kill a customer's tracking.
 *
 * @param object|array $license_data
 * @return string
 */
function pys_response_block_type( $license_data ) {
	return 'hard' === pys_response_value( $license_data, 'pys_eam_block_type' ) ? 'hard' : 'soft';
}

/**
 * Records the block, queues the reason notice and flags the license status as
 * `site_blocked` for a plugin. Shared by the check and activation flows.
 *
 * @param Plugin|Settings $plugin
 * @param object|array    $license_data
 * @param string|null     $license_key  Key to persist; defaults to the stored one.
 * @return void
 */
function pys_apply_domain_block( $plugin, $license_data, $license_key = null ) {
	$slug    = $plugin->getSlug();
	$message = pys_response_block_message( $license_data );

	if ( null === $license_key ) {
		$license_key = $plugin->getOption( 'license_key' );
	}

	set_domain_block( $slug, $message, pys_response_blocked_at( $license_data ), pys_response_block_type( $license_data ) );

	set_transient( "pys_{$slug}_license_notice", array(
		'class' => 'danger',
		'msg'   => pys_block_message_kses( $message ),
	), 60 * 5 );

	$plugin->updateOptions( array(
		'license_key'     => $license_key,
		'license_status'  => 'site_blocked',
		'license_expires' => $plugin->getOption( 'license_expires' ),
	) );
}

/**
 * Returns the list of currently blocked plugins (core + add-ons) as
 * `[ ['name' => ..., 'message' => ...], ... ]`. Skips the `head_footer` slug.
 * Shared by the block UI renderers below.
 *
 * @return array
 */
function pys_get_blocked_plugins() {
	$blocked = array();
	$plugins = array_merge( array( PYS() ), array_values( PYS()->getRegisteredPlugins() ) );

	foreach ( $plugins as $plugin ) {
		if ( empty( $plugin ) ) {
			continue;
		}

		$slug = $plugin->getSlug();
		if ( 'head_footer' === $slug || ! is_domain_blocked( $slug ) ) {
			continue;
		}

		$blocked[] = array(
			'name'    => $plugin->getPluginName(),
			'message' => get_domain_block_message( $slug ),
		);
	}

	return $blocked;
}

/**
 * Groups the currently blocked plugins by identical message so a single
 * consolidated notice is shown per distinct block, rather than one per plugin.
 *
 * @return array List of [ 'message' => ..., 'names' => [ ... ] ].
 */
function pys_group_blocked_plugins() {
	$groups = array();

	foreach ( pys_get_blocked_plugins() as $blocked ) {
		// By message: a differing one keeps its own notice. Hashed so the array
		// key stays short and safe whatever the message contains.
		$key = md5( $blocked['message'] );

		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array(
				'message' => $blocked['message'],
				'names'   => array(),
			);
		}

		$groups[ $key ]['names'][] = $blocked['name'];
	}

	return array_values( $groups );
}

/**
 * Brand-prefixed heading for a consolidated block notice.
 *
 * One heading for both levels, for the reason given in
 * get_domain_block_message(): a Hard block does not stop tracking here.
 *
 * @return string Escaped heading text.
 */
function pys_block_group_heading() {
	return esc_html__( 'PixelYourSite: This site has been blocked', 'pys' );
}

/**
 * "The following plugins are affected: A, B, C" line for a consolidated notice.
 *
 * @param string[] $names Affected plugin display names.
 * @return string Escaped, ready-to-print line.
 */
function pys_block_affected_line( $names ) {
	return sprintf(
		/* translators: %s: comma-separated list of affected plugin names. */
		esc_html__( 'The following plugins are affected: %s', 'pys' ),
		esc_html( implode( ', ', $names ) )
	);
}

/**
 * Shows a non-dismissible error notice on every wp-admin page (including the
 * Dashboard and PixelYourSite's own pages): one consolidated notice per distinct
 * (type, message) block, listing every affected plugin. Registered on
 * `admin_notices` in PYS::__construct().
 *
 * @return void
 */
function pys_render_domain_block_admin_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	foreach ( pys_group_blocked_plugins() as $group ) {
		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong><br>%2$s<br>%3$s</p></div>',
			pys_block_group_heading(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			pys_block_message_kses( $group['message'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses.
			pys_block_affected_line( $group['names'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		);
	}
}

/**
 * Renders the block banner inside PixelYourSite's own admin pages, where the
 * default admin_notices area sits outside the plugin's custom layout. Called
 * from the PYS page wrappers, right under the page title. One consolidated
 * notice per distinct (type, message) block.
 *
 * @return void
 */
function pys_render_domain_block_banner() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	foreach ( pys_group_blocked_plugins() as $group ) {
		printf(
			'<div class="alert alert-danger" role="alert" style="margin-bottom:12px;"><strong>%1$s</strong><br>%2$s<br>%3$s</div>',
			pys_block_group_heading(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			pys_block_message_kses( $group['message'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses.
			pys_block_affected_line( $group['names'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		);
	}
}

/**
 * @param Plugin|Settings $plugin
 */
function renderLicenseControls( $plugin, $license_status ) {

	$slug = $plugin->getSlug();
	$input_name = "pys[{$slug}][license_action]";

	?>
	<?php if ( is_domain_blocked( $slug ) ) : ?>
        <div class="alert alert-danger" style="margin-bottom: 12px" role="alert">
            <div>
                <h4 style="margin-bottom: 6px;">
					<?php esc_html_e( 'This site has been blocked', 'pys' ); ?>
				</h4>
                <p><?php echo pys_block_message_kses( get_domain_block_message( $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses. ?></p>
            </div>
        </div>
	<?php endif; ?>
    <div class="license-info mb-24">
        <h4 class="font-medium"><?php esc_html_e( 'Your license key', 'pys' ); ?>:</h4>
		<?php if ( $license_status == 'Activated' || $license_status == 'Expired' || $license_status == 'valid' ) : ?>
            <p class="text-green fw-500"><?php esc_html_e( 'Activated', 'pys' ); ?></p>
		<?php elseif ( $license_status === 'site_blocked' ) : ?>
            <p class="text-red fw-500"><?php esc_html_e( 'License blocked', 'pys' ); ?></p>
		<?php else : ?>
            <p class="text-red fw-500"><?php esc_html_e( 'Deactivated', 'pys' ); ?></p>
		<?php endif; ?>

        <h4 class="font-medium"><?php esc_html_e( 'Plugin version', 'pys' ); ?>:</h4>

        <div>
            <span class="fw-500"><?php echo esc_html( $plugin->getPluginVersion() ); ?></span>
        </div>

        <h4 class="font-medium"><?php esc_html_e( 'License name', 'pys' ); ?>:</h4>

        <div>
            <span class="fw-500"><?php echo esc_html( PYS_FREE_LICENSE_NAME ); ?></span>
        </div>
    </div>

    <div>
        <h4 class="font-semibold mb-4">
			<?php esc_html_e( 'Your license key', 'pys' ); ?>:
        </h4>
        <div>
            <div class="d-flex align-items-center">
				<?php $plugin->render_password_input( 'license_key', 'Enter your license key', false, false, false ); ?>
				<?php if ( $license_status == 'valid' || $license_status == 'expired' ) : ?>
                    <button class="btn btn-block btn-sm btn-success" name="<?php echo esc_attr( $input_name ); ?>"
                            value="reactivate">Reactivate License
                    </button>
				<?php else: ?>
                    <button class="btn btn-block btn-sm btn-primary" name="<?php echo esc_attr( $input_name ); ?>"
                            value="activate">Activate License
                    </button>
				<?php endif; ?>
            </div>
			<?php if ( $license_status != 'valid' && $license_status != 'expired' ) : ?>
                <p class="license-description mt-4 lh-162">
					<?php esc_html_e(
						'You have your license key in the order confirmation email, and you can get it from ',
						'pys'
					);
					?>
                    <a href="<?php echo esc_url( 'https://www.pixelyoursite.com/my-account' ); ?>"
                       rel="nofollow"
                       class="link link-small"
                       target="_blank">
						<?php esc_html_e( 'your account', 'pys' );
						?></a>
					<?php esc_html_e(
						' on our website. We also sent you an email containing your login data.',
						'pys'
					);
					?>
                </p>
			<?php endif; ?>
        </div>
    </div>

    <div class="mt-24">
		<?php render_info_message( sprintf( __( 'If your don\'t have key, you can take it from %s', 'pys' ), '<a href="https://www.pixelyoursite.com/my-account" rel="nofollow" target="_blank">' . __( 'here', 'pys' ) . '</a>' ) ); ?>
    </div>
	<?php

    $license_key = $plugin->getOption( 'license_key' );
    $license_expires = $plugin->getOption( 'license_expires', null );
    $license_expires_soon = false;
    $license_expired = false;

	if( $license_expires ) {

		$now = time();

		if( $now >= $license_expires ) {
			$license_expired = true;
		} elseif ( $now >= ( $license_expires - 30 * DAY_IN_SECONDS ) ) {
			$license_expires_soon = true;
		}

	}

	if ( $notice = get_transient( "pys_{$slug}_license_notice" ) ) :
		?>
            <div class="alert alert-<?php echo esc_attr( $notice['class'] ); ?>" role="alert">
                <?php echo $notice['msg']; ?>
            </div>
		<?php

		delete_transient(  "pys_{$slug}_license_notice" );

	endif;

	if ( $notice = get_transient( "pys_{$slug}_license_notice_403" ) ) :
		?>
            <div class="alert alert-<?php echo esc_attr( $notice['class'] ); ?>" role="alert">
                <?php echo $notice['msg']; ?>
            </div>
		<?php

		delete_transient(  "pys_{$slug}_license_notice_403" );

	endif;

	if ( $license_expires_soon ) :
		?>
            <div class="alert alert-warning">
                <p>Your license key <strong>expires
                        on <?php echo date( get_option( 'date_format' ), $license_expires ); ?></strong>. Make sure
                    you keep everything updated and in order.</p>
                <p>If you renewed your license but you still see this message, click on the "Reactivate License" button.</p>
                <p><a href="https://www.pixelyoursite.com/checkout-2/?edd_license_key=<?php echo esc_attr(
                    $license_key ); ?>&utm_campaign=admin&utm_source=licenses&utm_medium=renew" target="_blank"><strong>Click here to renew your license</strong></a></p>
            </div>
		<?php
	endif;

	if ( $license_expired ) :
		?>
            <div class="alert alert-danger">
                <p><strong>Your license key is expired</strong>, so you no longer get any updates. Don't miss our
                    latest improvements and make sure that everything works smoothly.</p>
                <p>If you renewed your license but you still see this message, click on the "Reactivate License" button.</p>
                <p><a href="https://www.pixelyoursite.com/checkout-2/?edd_license_key=<?php echo esc_attr(
                    $license_key ); ?>&utm_campaign=admin&utm_source=licenses&utm_medium=renew" target="_blank"><strong>Click here to renew your license now</strong></a></p>
            </div>
		<?php
	endif;

}

function checkLicense()
{
    $plugins = PYS()->getRegisteredPlugins();
    // Stop pinging the server for the core plugin while its domain is blocked.
    if(!is_domain_blocked(PYS()->getSlug()) && (!get_option(PYS()->getSlug().'_last_check_license') || get_option(PYS()->getSlug().'_last_check_license')['time'] == '' || get_option(PYS()->getSlug().'_last_check_license')['time'] < time()) && PYS()->getOption('license_key') && !empty(PYS()->getOption('license_key')))
    {
        $license_data = singleCheckLicense(PYS()->getOption('license_key'), PYS());
        if(!empty($license_data)) {
            set_data_license(PYS(), $license_data);
        }
    }


    foreach ($plugins as $plugin)
    {
        if ( $plugin->getSlug() == 'head_footer' ) { continue; }
        // Skip blocked add-ons: no more background license pings for them.
        if ( is_domain_blocked( $plugin->getSlug() ) ) { continue; }
        if((!get_option($plugin->getSlug().'_last_check_license') || get_option($plugin->getSlug().'_last_check_license')['time'] == '' || get_option($plugin->getSlug().'_last_check_license')['time'] < time()) && $plugin->getOption('license_key') && !empty($plugin->getOption('license_key')))
        {
            $license_data_single = singleCheckLicense($plugin->getOption('license_key'), $plugin);
            if(!empty($license_data_single)) {
                set_data_license($plugin, $license_data_single);
            }
        }
    }

}
function set_data_license($plugin, $license_data)
{
    $license_status = $plugin->getOption( 'license_status' );
    $license_expires = $plugin->getOption( 'license_expires' );
    $license_key = $plugin->getOption( 'license_key' );
    $slug = $plugin->getSlug();
    $admin_notice = array();

    // Server reported the domain as blocked: store the block, notify, and stop.
    if ( ! is_wp_error( $license_data ) && pys_response_is_blocked( $license_data ) ) {
        pys_apply_domain_block( $plugin, $license_data );
        return;
    }

    if ( is_wp_error( $license_data ) ) {

        $admin_notice = array(
            'class' => 'danger',
            'msg'   => 'Something went wrong during license update request. [' . $license_data->get_error_message() . ']'
        );

    } else {

        // Valid, non-blocked response: auto-recover if the domain was unblocked.
        clear_domain_block( $slug );

        /**
         * Overwrite empty license status only on successful activation.
         * For existing status overwrite with any value except error.
         */
        if ( empty( $license_status ) && $license_data->license == 'valid' ) {
            $license_status = 'valid';
        } elseif ( ! empty( $license_status ) ) {
            $license_status = $license_data->license;
        }

        if ( $license_data->success ) {

            switch ( $license_data->license ) {
                case
                'valid':
                    $admin_notice = array(
                        'class' => 'success',
                        'msg'   => 'Your license is working fine. Good job!'
                    );
                    break;

                case 'deactivated':
                    $admin_notice = array(
                        'class' => 'success',
                        'msg'   => 'Your license was successfully deactivated for this site.'
                    );
                    break;
                case 'expired':                 // license has expired
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'Your License has expired. <a href="https://www.pixelyoursite.com/checkout-2?edd_license_key=' . urlencode( $license_key ) . '&utm_campaign=admin&utm_source=licenses&utm_medium=renew" target="_blank">Renew it now.</a>'
                    );
                    break;

                case 'inactive':                // license is not active
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'This license is not active. Activate it now.'
                    );
                    break;

                case 'disabled':                // license key disabled
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'License key disabled.'
                    );
                    break;

                case 'license_not_activable':   // trying to activate bundle license
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'If you have a bundle package, please use each individual license for your products.'
                    );
                    break;

                case 'revoked':                 // license key revoked
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'This license was revoked.'
                    );
                    break;
            }

            $license_expires = $license_data->expires === 'lifetime' ? strtotime('2099-12-31 23:59:59') : strtotime($license_data->expires);;

        } else {

            switch ( $license_data->license ) {
                case 'invalid':                 // key do not exist
                case 'missing':
                case 'key_mismatch':
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => "License keys don't match. Make sure you're using the correct license."
                    );
                    break;

                case 'license_not_activable':   // trying to activate bundle license
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'If you have a bundle package, please use each individual license for your products.'
                    );
                    break;

                case 'revoked':                 // license key revoked
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'This license was revoked.'
                    );
                    break;

                case 'no_activations_left':     // no activations left
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'No activations left. Log in to your account to extent your license.'
                    );
                    break;

                case 'invalid_item_id':
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'Invalid item ID.'
                    );
                    break;

                case 'item_name_mismatch':      // item names don't match
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => "Item names don't match."
                    );
                    break;



                case 'site_inactive':
                    $admin_notice = array(
                        'class' => 'danger',
                        'msg'   => 'The license is not active for this site. Activate it now.'
                    );
                    break;

            }

            // add error code
            $admin_notice['msg'] .= " [error: $license_data->license]";

        }

    }

    if ( ! empty( $admin_notice ) ) {
        set_transient( "pys_{$slug}_license_notice", $admin_notice, 60 * 5 );
    }

    $plugin->updateOptions(
        array (
            'license_key'     => $license_key,
            'license_status'  => $license_status,
            'license_expires' => $license_expires
        )
    );
}
function singleCheckLicense( $license_key, $plugin)
{
    update_option($plugin->getSlug().'_last_check_license', array('name'=>$plugin->getPluginName(), 'time'=>time()));
    $api_params = array(
        'edd_action' => 'check_license',
        'license'    => $license_key,
        'item_name'  => urlencode( $plugin->getPluginName() ),
        'url'        => home_url()
    );

    $response = wp_remote_post( 'https://www.pixelyoursite.com', array(
        'timeout'   => 30,
        'sslverify' => (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true ),
        'body'      => $api_params,
        'headers'   => array(
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            'Accept'     => 'application/json',
        ),
    ) );

    if ( is_wp_error( $response ) ) {
        return $response;
    }
	$status_code = wp_remote_retrieve_response_code($response);
    if($status_code == 403 || $status_code == 415) {
        $ip_list = get_all_server_ips();

        $admin_notice = array(
            'class' => 'danger',
            'msg'   => sprintf(
                __( 'The request may have been blocked by our firewall. Please try again later. If the problem persists, contact our support and provide the following IP addresses: %s', 'pys' ),
                $ip_list
            )
        );

	    if ( ! empty( $admin_notice ) ) {
		    set_transient( "pys_{$plugin->getSlug()}_license_notice_403", $admin_notice, 60 * 5 );
	    }
    }

    // $license_data->license will be either "valid" or "invalid"
    return json_decode( wp_remote_retrieve_body( $response ) );
}


/**
 * @param Plugin|Settings $plugin
 */
function updateLicense( $plugin ) {

	$slug = $plugin->getSlug();
    $license_key_old = $plugin->getOption( 'license_key' );
	// nothing to do...
	if( ! isset( $_POST['pys'][ $slug ]['license_action'] ) ) {
		return;
	}
    $last_check_license = time();
	$license_action = $_POST['pys'][ $slug ]['license_action'];
	if(isset( $_POST['pys'][ $slug ]['license_key'] )) {
        $license_key    =  sanitize_text_field($_POST['pys'][ $slug ]['license_key']);
    } else {
        $license_key    =   '';
    }


	// activate/deactivate license
	if ( $license_action == 'activate' ) {
		$license_data = licenseActivate( $license_key, $plugin );
	} else if ( $license_action == 'reactivate' ) {
        if($license_key_old)
        {
            $license_key = $license_key_old;
        }
        $license_data = licenseActivate($license_key, $plugin);
    }
    else {
        if($license_key_old)
        {
            $license_key = $license_key_old;
        }
		$license_data = licenseDeactivate( $license_key, $plugin );
	}
    if(!empty($license_data)) {
        update_option($plugin->getSlug() . '_last_check_license', array('name' => $plugin->getPluginName(), 'time' => $last_check_license));
        $license_status = $plugin->getOption('license_status');
        $license_expires = $plugin->getOption('license_expires');

        $admin_notice = array();

        // Server reported the domain as blocked: refuse activation and stop.
        if ( ! is_wp_error( $license_data ) && pys_response_is_blocked( $license_data ) ) {
            pys_apply_domain_block( $plugin, $license_data, $license_key );
            return;
        }

        if (is_wp_error($license_data)) {

            $admin_notice = array(
                'class' => 'danger',
                'msg' => 'Something went wrong during license update request. [' . $license_data->get_error_message() . ']'
            );

        } else {

            // Valid, non-blocked response: auto-recover if the domain was unblocked.
            clear_domain_block( $slug );

            /**
             * Overwrite empty license status only on successful activation.
             * For existing status overwrite with any value except error.
             */
            if (empty($license_status) && $license_data->license == 'valid') {
                $license_status = 'valid';
            } elseif (!empty($license_status)) {
                $license_status = $license_data->license;
            }

            if ($license_data->success) {

                switch ($license_data->license) {
                    case
                    'valid':
                        $admin_notice = array(
                            'class' => 'success',
                            'msg' => 'Your license is working fine. Good job!'
                        );
                        break;

                    case 'deactivated':
                        $admin_notice = array(
                            'class' => 'success',
                            'msg' => 'Your license was successfully deactivated for this site.'
                        );
                        break;
                }

                $license_expires = $license_data->expires === 'lifetime' ? strtotime('2099-12-31 23:59:59') : strtotime($license_data->expires);;

            } else {

                switch ($license_data->license) {
                    case 'invalid':                 // key do not exist
                    case 'missing':
                    case 'key_mismatch':
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => "License keys don't match. Make sure you're using the correct license."
                        );
                        break;

                    case 'license_not_activable':   // trying to activate bundle license
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => 'If you have a bundle package, please use each individual license for your products.'
                        );
                        break;

                    case 'revoked':                 // license key revoked
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => 'This license was revoked.'
                        );
                        break;

                    case 'no_activations_left':     // no activations left
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => 'No activations left. Log in to your account to extent your license.'
                        );
                        break;

                    case 'invalid_item_id':
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => 'Invalid item ID.'
                        );
                        break;

                    case 'item_name_mismatch':      // item names don't match
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => "Item names don't match."
                        );
                        break;

                    case 'expired':                 // license has expired
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => 'Your License has expired. <a href="https://www.pixelyoursite.com/checkout-2?edd_license_key=' . urlencode($license_key) . '&utm_campaign=admin&utm_source=licenses&utm_medium=renew" target="_blank">Renew it now.</a>'
                        );
                        break;

                    case 'inactive':                // license is not active
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => 'This license is not active. Activate it now.'
                        );
                        break;

                    case 'disabled':                // license key disabled
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => 'License key disabled.'
                        );
                        break;

                    case 'site_inactive':
                        $admin_notice = array(
                            'class' => 'danger',
                            'msg' => 'The license is not active for this site. Activate it now.'
                        );
                        break;

                }

                // add error code
                $admin_notice['msg'] .= " [error: $license_data->license]";

            }

        }

        if (!empty($admin_notice)) {
            set_transient("pys_{$slug}_license_notice", $admin_notice, 60 * 5);
        }

        $plugin->updateOptions(
            array(
                'license_key' => $license_key,
                'license_status' => $license_status,
                'license_expires' => $license_expires
            )
        );
    }

}

/**
 * @param string          $license_key
 * @param Plugin|Settings $plugin
 *
 * @return array|mixed|object|\WP_Error
 */
function licenseActivate( $license_key, $plugin ) {

	$api_params = array(
		'edd_action' => 'activate_license',
		'license'    => $license_key,
		'item_name'  => urlencode( $plugin->getPluginName() ),
		'url'        => home_url()
	);

	$response = wp_remote_post( 'https://www.pixelyoursite.com', array(
		'timeout'   => 120,
		'sslverify' => (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true ),
		'body'      => $api_params,
		'headers'   => array(
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			'Accept'     => 'application/json',
		),
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code($response);
	if($status_code == 403 || $status_code == 415) {
        $ip_list = get_all_server_ips();

        $admin_notice = array(
            'class' => 'danger',
            'msg'   => sprintf(
                __( 'The request may have been blocked by our firewall. Please try again later. If the problem persists, contact our support and provide the following IP addresses: %s', 'pys' ),
                $ip_list
            )
        );

		if ( ! empty( $admin_notice ) ) {
			set_transient( "pys_{$plugin->getSlug()}_license_notice_403", $admin_notice, 60 * 5 );
		}
	}
	// $license_data->license will be either "valid" or "invalid"
	return json_decode( wp_remote_retrieve_body( $response ) );

}

/**
 * @param string          $license_key
 * @param Plugin|Settings $plugin
 *
 * @return array|mixed|object|\WP_Error
 */
function licenseDeactivate( $license_key, $plugin ) {

	$api_params = array(
		'edd_action' => 'deactivate_license',
		'license'    => $license_key,
		'item_name'  => urlencode( $plugin->getPluginName() ),
		'url'        => home_url()
	);

	$response = wp_remote_post( 'https://www.pixelyoursite.com', array(
		'timeout'   => 120,
		'sslverify' => (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true ),
		'body'      => $api_params,
		'headers'   => array(
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			'Accept'     => 'application/json',
		),
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$status_code = wp_remote_retrieve_response_code($response);
	if($status_code == 403 || $status_code == 415) {
        $ip_list = get_all_server_ips();

        $admin_notice = array(
            'class' => 'danger',
            'msg'   => sprintf(
                __( 'The request may have been blocked by our firewall. Please try again later. If the problem persists, contact our support and provide the following IP addresses: %s', 'pys' ),
                $ip_list
            )
        );

		if ( ! empty( $admin_notice ) ) {
			set_transient( "pys_{$plugin->getSlug()}_license_notice_403", $admin_notice, 60 * 5 );
		}
	}
	// $license_data->license will be either "deactivated" or "failed"
	return json_decode( wp_remote_retrieve_body( $response ) );

}

function get_all_server_ips(): string {
    $ips = [];

    // 1. Get external/public IP using an external service
    $external_ip = @file_get_contents('https://api64.ipify.org?format=json');
    if ($external_ip) {
        $decoded = json_decode($external_ip, true);
        if (!empty($decoded['ip'])) {
            $ips[] = $decoded['ip'];
        }
    }

    // 2. Get IP from the server's hostname
    $hostname = gethostname();
    if ($hostname) {
        $hostname_ip = gethostbyname($hostname);
        if (filter_var($hostname_ip, FILTER_VALIDATE_IP)) {
            $ips[] = $hostname_ip;
        }

        // 3. Get all IPs associated with the hostname
        $multiple_ips = gethostbynamel($hostname);
        if (is_array($multiple_ips)) {
            foreach ($multiple_ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = $ip;
                }
            }
        }
    }

    // 4. Get IP resolved from the server name (e.g., domain)
    if (!empty($_SERVER['SERVER_NAME'])) {
        $server_name_ip = gethostbyname($_SERVER['SERVER_NAME']);
        if (filter_var($server_name_ip, FILTER_VALIDATE_IP)) {
            $ips[] = $server_name_ip;
        }
    }

    // 5. Get the IP address of the interface the server is running on
    if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP)) {
        $ips[] = $_SERVER['SERVER_ADDR'];
    }

    // Remove duplicates and return as a comma-separated string
    $unique_ips = array_unique($ips);
    return implode(', ', $unique_ips);
}
