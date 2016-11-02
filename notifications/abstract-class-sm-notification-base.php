<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Base class, handles notifications
 * 
 * Class SM_Notification_Base
 */
abstract class SM_Notification_Base {
	/**
	 * The following variables have to be defined for each payment method.
	 */
	public $id = '';
	public $name = '';
	public $description = '';
	
	public $sm_options;
	
	public function __construct() {
		$this->sm_options = SM_Main::instance()->settings->get_options();
		
		add_action( 'init', array( &$this, 'init' ), 30 );
		add_action( 'sm_validate_options', array( &$this, '_validate_options' ), 10, 2 );
	}

	private function settings_field_name_attr( $name ) {
		return esc_attr( "notification_handler_options_{$this->id}[{$name}]" );
	}
	
	public function init() {}
	
	/**
	 * Registers the settings for this individual extension
	 */
	public function settings_fields() {}
	
	/**
	 * Exectutes when notification is due
	 */
	public function trigger( $args ) {}
	
	public function _settings_section_callback() {
		echo '<p>' . $this->description . '</p>';
	}
	
	public function _settings_enabled_field_callback( $args = array() ) {
		SM_Settings_Fields::yesno_field( $args );
	}
	
	public function add_settings_field_helper( $option_name, $title, $callback, $description = '', $default_value = '' ) {
		$settings_page_slug = SM_Main::instance()->settings->slug();
		$handler_options = isset( $this->sm_options["handler_options_{$this->id}"] )
			? $this->sm_options["handler_options_{$this->id}"] : array();
		
		add_settings_field( 
			"notification_handler_{$this->id}_{$option_name}", 
			$title, 
			$callback, 
			$settings_page_slug, 
			"notification_{$this->id}",
			array(
				'name' 		=> $this->settings_field_name_attr( $option_name ),
				'value' 	=> isset( $handler_options[ $option_name ] ) ? $handler_options[ $option_name ] : $default_value,
				'desc' 		=> $description,
				'id'      	=> $option_name,
				'page'    	=> $settings_page_slug,
			) 
		);
	}
	
	public function _validate_options( $form_data, $sm_options ) {
		$post_key 	= "notification_handler_options_{$this->id}";
		$option_key = "handler_options_{$this->id}";
	
		if ( ! isset( $_POST[ $post_key ] ) )
			return $form_data;
	
		$input = $_POST[ $post_key ];
		$output = ( method_exists( $this, 'validate_options' ) ) ? $this->validate_options( $input ) : array();
		$form_data[ $option_key ] = $output;
	
		return $form_data;
	}
	
	public function get_handler_options() {
		$handler_options = array();
		$option_key = "handler_options_{$this->id}";
		
		if ( isset( $this->sm_options[ $option_key ] ) ) {
			$handler_options = (array) $this->sm_options[ $option_key ];
		}
		
		return $handler_options;
	}

	// Returns an associative Array
	public function prep_notification_body( $args ) {
		$details_to_provide = array(
			'user_id'     => __( 'User', 'status-machine' ),
			'object_type' => __( 'Object Type', 'status-machine' ),
			'object_name' => __( 'Object Name', 'status-machine' ),
			'action'      => __( 'Action Type', 'status-machine' ),
			'hist_ip'     => __( 'IP Address', 'status-machine' ),
		);
		$message = array();

		foreach ( $details_to_provide as $detail_key => $detail_title ) {
			$message[$detail_key] = $args[ $detail_key ];
		}

		return $message;
	}
}

function sm_register_notification_handler( $classname = '' ) {
	return SM_Main::instance()->notifications->register_handler( $classname );
}
