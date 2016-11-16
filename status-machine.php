<?php
/*
Plugin Name: Status Machine
Plugin URI: http://wordpress.org/plugins/status-machine/
Description: Synchronizes the changes you've made in WordPress with your Status Machine account, so that you can later pinpoint what changes caused some effects on your site.
Author: Julien Desrosiers, Yakir Sitbon, Maor Chasen, Ariel Klikstein
Author URI: https://www.statusmachine.com/
Version: 2.3.6
Text Domain: status-machine
License: GPLv2 or later

FORK NOTICE:
This plugin is a fork of the Activity Log plugin (https://fr.wordpress.org/plugins/aryo-activity-log/),
and is intended to meet the requirements of Status Machine regarding the
synchronization with Status Machine's API for its users.

LICENSE:
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.


*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'STATUS_MACHINE__FILE__', __FILE__ );
define( 'STATUS_MACHINE_BASE', plugin_basename( STATUS_MACHINE__FILE__ ) );

include( 'classes/class-sm-maintenance.php' );
include( 'classes/class-sm-status-machine-list-table.php' );
include( 'classes/class-sm-admin-ui.php' );
include( 'classes/class-sm-settings.php' );
include( 'classes/class-sm-api.php' );
include( 'classes/class-sm-hooks.php' );
include( 'classes/class-sm-notifications.php' );
include( 'classes/class-sm-help.php' );

// Integrations
include( 'classes/class-sm-integration-woocommerce.php' );

// Probably we should put this in a separate file
final class SM_Main {

	/**
	 * @var SM_Main The one true SM_Main
	 * @since 2.0.5
	 */
	private static $_instance = null;

	/**
	 * @var SM_Admin_Ui
	 * @since 1.0.0
	 */
	public $ui;

	/**
	 * @var SM_Hooks
	 * @since 1.0.1
	 */
	public $hooks;

	/**
	 * @var SM_Settings
	 * @since 1.0.0
	 */
	public $settings;

	/**
	 * @var SM_API
	 * @since 2.0.5
	 */
	public $api;

	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'status-machine' );
	}

	/**
	 * Construct
	 */
	protected function __construct() {
		global $wpdb;
		
		$this->ui            = new SM_Admin_Ui();
		$this->hooks         = new SM_Hooks();
		$this->settings      = new SM_Settings();
		$this->api           = new SM_API();
		$this->notifications = new SM_Notifications();
		$this->help          = new SM_Help();

		// set up our DB name
		$wpdb->status_machine = $wpdb->prefix . 'status_machine';
		
		add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 2.0.7
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'status-machine' ), '2.0.7' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 2.0.7
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'status-machine' ), '2.0.7' );
	}

	/**
	 * @return SM_Main
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new SM_Main();
		return self::$_instance;
	}
}

SM_Main::instance();
