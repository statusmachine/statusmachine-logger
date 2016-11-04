<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SM_Maintenance {

	public static function activate( $network_wide ) {
		global $wpdb;

		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		if ( function_exists( 'is_multisite') && is_multisite() && $network_wide ) {
			$old_blog_id = $wpdb->blogid;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::_create_tables();
				self::_activate_wp_cron();
			}

			switch_to_blog( $old_blog_id );
		} else {
			self::_create_tables();
			self::_activate_wp_cron();
		}

	}

	public static function deactivate( $network_wide ) {
		global $wpdb;

		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		if ( function_exists( 'is_multisite') && is_multisite() && $network_deactivating ) {
			$old_blog_id = $wpdb->blogid;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs};" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::_deactivate_wp_cron();
			}

			switch_to_blog( $old_blog_id );
		} else {
			self::_deactivate_wp_cron();
		}
	}

	public static function uninstall( $network_deactivating ) {
		global $wpdb;

		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		if ( function_exists( 'is_multisite') && is_multisite() && $network_deactivating ) {
			$old_blog_id = $wpdb->blogid;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs};" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::_remove_tables();
				self::_deactivate_wp_cron();
			}

			switch_to_blog( $old_blog_id );
		} else {
			self::_remove_tables();
			self::_deactivate_wp_cron();
		}
	}

	public static function mu_new_blog_installer( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		global $wpdb;

		if ( is_plugin_active_for_network( STATUS_MACHINE_BASE ) ) {
			$old_blog_id = $wpdb->blogid;
			switch_to_blog( $blog_id );
			self::_create_tables();
			self::_activate_wp_cron();
			switch_to_blog( $old_blog_id );
		}
	}

	public static function mu_delete_blog( $blog_id, $drop ) {
		global $wpdb;

		$old_blog_id = $wpdb->blogid;
		switch_to_blog( $blog_id );
		self::_remove_tables();
		self::_deactivate_wp_cron();
		switch_to_blog( $old_blog_id );
	}

	protected static function _create_tables() {
		global $wpdb;

		$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}status_machine` (
					  `histid` int(11) NOT NULL AUTO_INCREMENT,
					  `user_caps` varchar(70) NOT NULL DEFAULT 'guest',
					  `action` varchar(255) NOT NULL,
					  `object_type` varchar(255) NOT NULL,
					  `object_subtype` varchar(255) NOT NULL DEFAULT '',
					  `object_name` varchar(255) NOT NULL,
					  `object_id` int(11) NOT NULL DEFAULT '0',
					  `user_id` int(11) NOT NULL DEFAULT '0',
					  `hist_ip` varchar(55) NOT NULL DEFAULT '127.0.0.1',
					  `hist_time` int(11) NOT NULL DEFAULT '0',
					  PRIMARY KEY (`histid`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role instanceof WP_Role && ! $admin_role->has_cap( 'view_all_status_machine' ) )
			$admin_role->add_cap( 'view_all_status_machine' );
		
		update_option( 'activity_log_db_version', '1.0' );
	}

	protected static function _remove_tables() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}status_machine`;" );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role && $admin_role->has_cap( 'view_all_status_machine' ) )
			$admin_role->remove_cap( 'view_all_status_machine' );

		delete_option( 'activity_log_db_version' );
	}

	protected static function _activate_wp_cron() {
		// Since there can be different themes on different sites of a network, 
		// we need to schedule the wp_cron for each one of these sites. 
		// Otherwise it might not be trigger for a particular theme.
		// It's done outside of the 
		// If this doesn't work, make sure DISABLE_WP_CRON is not true
		if (! wp_next_scheduled ( 'sm_twicedaily_event' )) {
			wp_schedule_event(time(), 'twicedaily', 'sm_twicedaily_event');
		}
	}

	protected static function _deactivate_wp_cron() {
		wp_clear_scheduled_hook('sm_twicedaily_event');
	}
}

function check_for_theme_modifications() {
	/*
	if is multisite
		check what is the current theme of the current site that is calling this event (is it possible?)
		for this site's active theme and child_theme, send the notification to Status Machine about what changes were made in the filesystem for that theme (child_theme)
	else (not multisite)
		for the active theme and child_theme, send the notification to Status Machine about what changes were made in the filesystem for that theme (child_theme)
	*/
}

register_activation_hook( STATUS_MACHINE_BASE, array( 'SM_Maintenance', 'activate' ) );
register_deactivation_hook( STATUS_MACHINE_BASE, array( 'SM_Maintenance', 'deactivate' ) );
register_uninstall_hook( STATUS_MACHINE_BASE, array( 'SM_Maintenance', 'uninstall' ) );

// MU installer for new blog.
add_action( 'wpmu_new_blog', array( 'SM_Maintenance', 'mu_new_blog_installer' ), 10, 6 );
// MU Uninstall for delete blog.
add_action( 'delete_blog', array( 'SM_Maintenance', 'mu_delete_blog' ), 10, 2 );
// Check 
add_action( 'sm_twicedaily_event', 'check_for_theme_modifications');
