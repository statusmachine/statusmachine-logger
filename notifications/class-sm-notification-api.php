<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SM_Notification_API extends SM_Notification_Base {
	
	/**
	 * Store options in a class locally
	 */
	protected $options = array();
	
	public function __construct() {
		parent::__construct();
		
		$this->id = 'api';
		$this->name = __( 'API', 'status-machine' );
		$this->description = __( 'Synchronize your WordPress site with Status Machine.', 'status-machine' );
	}
	
	public function init() {
		$this->options = array_merge( array(), $this->get_handler_options() );
	}
	
	public function trigger( $args ) {
		$api_token = isset( $this->options['api_token'] ) ? $this->options['api_token'] : '';

		// if no from email or to email provided, quit.
		if ( ! $api_token )
		    return;

		$body = $this->prep_notification_body( $args );
		$site_url = home_url();

		if ($api_token) {
			wp_remote_post( "https://my.statusmachine.com/api/v1/wp_notify?token=".$api_token,
			// DEV: wp_remote_post( "http://0.0.0.0:9393/api/v1/wp_notify?token=".$api_token,
				array(
					'body' => array(
						'site_url' => $site_url,
						'action_details' => $body
					)
				)
			);
		}
	}

	public function settings_fields() {
		$this->add_settings_field_helper( 'api_token', __( 'API token', 'status-machine' ), array( 'SM_Settings_Fields', 'text_field' ), __( "Find it in your site's preferences in Status Machine", 'status-machine' ) );
	}
	
	public function validate_options( $input ) {
		$output = array();

		// api token
		if ( ! empty( $input['api_token'] ) ) {
		    $output['api_token'] = $input['api_token'];
		}

		return $output;
	}
}

// Register this handler, creates an instance of this class when necessary.
sm_register_notification_handler( 'SM_Notification_API' );
