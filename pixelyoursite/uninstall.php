<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes what the purchase report created: its cache table, its state,
 * its schedules on every site of a network, and the users' "Hide for 30 days". Settings and order data stay,
 * as before. PixelYourSite Pro uses the same table and state: while Pro is
 * installed they are left to it.
 *
 * @package PixelYourSite
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( file_exists( WP_PLUGIN_DIR . '/pixelyoursite-pro/pixelyoursite-pro.php' ) ) {
	return;
}

/**
 * Remove the purchase report data of the current site.
 *
 * @return void
 */
function pys_free_purchase_report_uninstall_site() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pys_purchase_report" );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'pys_options' ) ) ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'pys_options', array( 'option_name' => 'pys_purchase_report_state' ), array( '%s' ) );
	}
	delete_option( 'pys_purchase_report_state' );
	delete_transient( 'pys_purchase_report_widget' );
	delete_transient( 'pys_purchase_report_install_failed' );

	wp_clear_scheduled_hook( 'pys_purchase_report_fill' );
	wp_clear_scheduled_hook( 'pys_purchase_report_refresh' );
	wp_clear_scheduled_hook( 'pys_purchase_report_summary' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $site_id ) {
		switch_to_blog( $site_id );
		pys_free_purchase_report_uninstall_site();
		restore_current_blog();
	}
} else {
	pys_free_purchase_report_uninstall_site();
}

// "Hide for 30 days" of the widget; user meta is shared by every site of a network.
delete_metadata( 'user', 0, 'pys_purchase_report_pro_hidden_until', '', true );
