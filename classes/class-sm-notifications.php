<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Notifications API main class
 * 
 * @since 2.0.6
 */
class SM_Notifications {
	/* @todo public for debugging now, change to private/protected l8r */
	public $handlers = array();
	public $handlers_loaded = array();
	
	public function __construct() {
		// Load abstract class.
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/notifications/abstract-class-sm-notification-base.php' );
		
		// Run handlers loader
		add_action( 'init', array( &$this, 'load_handlers' ), 20 );
		add_action( 'sm_load_notification_handlers', array( &$this, 'load_default_handlers' ) );
		add_action( 'sm_insert_log', array( &$this, 'process_notifications' ), 20 );
	}
	
	public function process_notifications( $args ) {
		$enabled_handlers = $this->get_enabled_handlers();
		// if we can't find any enabled event handlers, bail.
		if ( empty( $enabled_handlers ) )
			return;
		
		// calculate if this type event is set in the rules
		$options = SM_Main::instance()->settings->get_options();
		
		// if ( ! empty( $notification_matched_rules ) ) {
			// cycle through enabled handlers and trigger them
			foreach ( $enabled_handlers as $enabled_handler ) {
				$enabled_handler->trigger( $args );
			}
		// }
	}

	public function get_object_types() {
		// TODO: It's need to be integration from the plugin
		$opts = apply_filters(
			'sm_notification_get_object_types',
			array(
				'Core',
				'Export',
				'Post',
				'Taxonomy',
				'User',
				'Options',
				'Attachment',
				'Plugin',
				'Widget',
				'Theme',
				'Menu',
				'Comments',
			)
		);
		
		return array_combine( $opts, $opts );
	}

	public function get_actions() {
		// TODO: It's need to be integration from the plugin
		$opts = apply_filters(
			'sm_notification_get_actions',
			array(
				'created',
				'deleted',
				'updated',
				'trashed',
				'untrashed',
				'spammed',
				'unspammed',
				'downloaded',
				'installed',
				'added',
				'activated',
				'deactivated',
				'accessed',
				'file_updated',
				'logged_in',
				'logged_out',
				'wrong_password',
			)
		);
		$ready = array();

		// make key => value pairs (where slug in key)
		foreach ( $opts as $opt ) {
			$ready[ $opt ] = ucwords( str_replace( '_', ' ', __( $opt, 'status-machine' ) ) );
		}

		return $ready;
	}
	
	/**
	 * Returns values for the dropdown in the settings page (the last dropdown in each conditions row)
	 * 
	 * @param string $row_key type
	 * @return array
	 */
	public function get_settings_dropdown_values( $row_key ) {
		$results = array();
		
		/**
		 * @todo allow this switch to be extensible by other plugins (see example)
		 */
		switch ( $row_key ) {
			case 'user':
				// cache all data in case we need the same data twice on the same/upcoming pageloads
				if ( false === ( $results = wp_cache_get( $cache_key = 'notifications-users', 'sm' ) ) ) {
					// get all users
					$all_users = get_users();
					$preped_users = array();
					
					// prepare users
					foreach ( $all_users as $user ) {
						$user_role = $user->roles;
							
						// if user has no role (shouldn't happen, but just in case)
						if ( empty( $user_role ) )
							continue;
						
						$user_role_obj = get_role( $user_role[0] );
						$user_role_name = isset( $user_role_obj->name ) ? $user_role_obj->name : $user_role[0];
							
						$preped_users[ $user->ID ] = apply_filters( 'sm_notifications_user_format', sprintf( '%s - %s (ID #%d)', $user->display_name, $user_role_name, $user->ID ), $user );
					}
					
					wp_cache_set( $cache_key, $results = $preped_users, 'sm' ); // no need for expiration time
				}
				break;
				
			case 'action-type':
				$results = $this->get_object_types();
				break;
				
			case 'action-value':
				$results = $this->get_actions();
				break;
				
			default:
				// @todo allow plugins to extend and handle custom field types 
				$results = apply_filters( 'sm_settings_dropdown_values', $results, $row_key );
				break;
		}
		
		return $results;
	}
	
	/**
	 * Returns a list of handlers, in a key-value format.
	 * Key holds the classname, value holds the name of the transport.
	 */
	public function get_handlers() {
		if ( empty( $this->handlers ) || ! did_action( 'sm_load_notification_handlers' ) )
			return array();
		
		$handlers = array();
		
		foreach ( $this->handlers as $handler ) {
			$handler_obj = $this->handlers_loaded[ $handler ];
			
			// if we got the name of the handler, use it. otherwise, use the classname.
			$handler_name = isset( $handler_obj->name ) ? $handler_obj->name : $handler;
			
			$handlers[ $handler_obj->id ] = $handler_name; 
		}
		
		return $handlers;
	}
	
	/**
	 * Returns a handler object
	 * 
	 * @param string $id
	 * @return SM_Notification_Base|bool
	 */
	public function get_handler_object( $id ) {
		return isset( $this->handlers_loaded[ $id ] ) ? $this->handlers_loaded[ $id ] : false;
	}
	
	/**
	 * Returns all available handlers
	 * @return array
	 */
	public function get_available_handlers() {
		$handlers = array();
		
		foreach ( $this->handlers_loaded as $handler_classname => $handler_obj ) {
			$handlers[ $handler_obj->id ] = $handler_obj;
		}
		
		return apply_filters( 'sm_available_handlers', $handlers );
	}
	
	/**
	 * Returns the active handlers that were activated through the settings page
	 * 
	 * @return array
	 */
	public function get_enabled_handlers() {
		$enabled = array();
		$options = SM_Main::instance()->settings->get_options();
		
		foreach ( $this->get_available_handlers() as $id => $handler_obj ) {
			// the handlers are always active... we only have one, and we check 
			// its value when it's time to actually notify.
			// if ( isset( $options['notification_handlers'][ $id ] ) && 1 == $options['notification_handlers'][ $id ] ) {
				$enabled[ $id ] = $handler_obj;
			// }
		}
		
		return $enabled;
	}

	/**
	 * Runs during sm_load_notification_handlers, 
	 * includes the necessary files to register default notification handlers.
	 */
	public function load_default_handlers() {
		$default_handlers = apply_filters( 'sm_default_addons', array(
			'api'			=> $this->get_default_handler_path( 'class-sm-notification-api.php' ),
		) );

		foreach ( $default_handlers as $filename )
			include_once $filename;
	}

	/**
	 * Returns path to notification handler file
	 * 
	 * @param string $filename
	 * @return string
	 */
	public function get_default_handler_path( $filename ) {
		return plugin_dir_path( STATUS_MACHINE__FILE__ ) . "notifications/$filename";
	}

	/**
	 * Fired before $this->init()
	 *
	 * @todo maybe check $classname's inheritance tree and signal if it's not a SM_Notification_Base
	 */
	public function load_handlers() {
		do_action( 'sm_load_notification_handlers' );

		foreach ( $this->handlers as $handler_classname ) {
			if ( class_exists( $handler_classname ) ) {
				$obj = new $handler_classname;
				
				// is this handler extending SM_Notification_Base?
				if ( ! is_a( $obj, 'SM_Notification_Base' ) )
					continue;
				
				$this->handlers_loaded[ $handler_classname ] = $obj;
			}
		}
	}

	/**
	 * Registers a handler class, which is then loaded in $this->load_handlers
	 * 
	 * @param string $classname The name of the class to create an instance for
	 * @return bool
	 */
	public function register_handler( $classname ) {
		if ( ! class_exists( $classname ) ) {
			trigger_error( __( 'The Status Machine notification handler you are trying to register does not exist.', 'status-machine' ) );
			return false;
		}

		$this->handlers[] = $classname;
		return true;
	}
}
