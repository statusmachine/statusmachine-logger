<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SM_Settings {
	private $hook;
	public $slug = 'status-machine-settings';
	protected $options;
	
	public function __construct() {
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_menu', array( &$this, 'action_admin_menu' ), 30 );
		add_action( 'admin_init', array( &$this, 'register_settings' ) );
		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
		add_action( 'admin_footer', array( &$this, 'admin_footer' ) );
		add_filter( 'plugin_action_links_' . STATUS_MACHINE_BASE, array( &$this, 'plugin_action_links' ) );

		add_action( 'wp_ajax_sm_reset_items', array( &$this, 'ajax_sm_reset_items' ) );
		add_action( 'wp_ajax_sm_get_properties', array( &$this, 'ajax_sm_get_properties' ) );
	}
	
	public function init() {
		$this->options = $this->get_options();
	}
	
	public function plugin_action_links( $links ) {
		$settings_link = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://github.com/statusmachine/statusmachine-logger', __( 'GitHub', 'status-machine' ) );
		array_unshift( $links, $settings_link );
		
		$settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=status-machine-settings' ), __( 'Settings', 'status-machine' ) );
		array_unshift( $links, $settings_link );
		
		return $links;
	}

	/**
	 * Register the settings page
	 *
	 * @since 1.0
	 */
	public function action_admin_menu() {
		$this->hook = add_submenu_page(
			'status_machine_page',
			__( 'Status Machine Settings', 'status-machine' ), 	// <title> tag
			__( 'Settings', 'status-machine' ), 			// menu label
			'manage_options', 								// required cap to view this page
			$this->slug, 			// page slug
			array( &$this, 'display_settings_page' )			// callback
		);

		// register scripts & styles, specific for the settings page
		add_action( "admin_print_scripts-{$this->hook}", array( &$this, 'scripts_n_styles' ) );
		// this callback will initialize the settings for SM
		// add_action( "load-$this->hook", array( $this, 'register_settings' ) );
	}

	/**
	 * Register scripts & styles
	 *
	 * @since 1.0
	 */
	public function scripts_n_styles() {
		wp_enqueue_script( 'sm-settings', plugins_url( 'assets/js/settings.js', STATUS_MACHINE__FILE__ ), array( 'jquery' ) );
		wp_enqueue_style( 'sm-settings', plugins_url( 'assets/css/settings.css', STATUS_MACHINE__FILE__ ) );
	}

	public function register_settings() {
		// If no options exist, create them.
		if ( ! get_option( $this->slug ) ) {
			update_option( $this->slug, apply_filters( 'sm_default_options', array(
				'logs_lifespan' => '30',
			) ) );
		}

		register_setting( 'sm-options', $this->slug, array( $this, 'validate_options' ) );
		$section = $this->get_setup_section();

		switch ( $section ) {
			case 'general':
				// First, we register a section. This is necessary since all future options must belong to a
				add_settings_section(
					'general_settings_section',			// ID used to identify this section and with which to register options
					__( 'Display Options', 'status-machine' ),	// Title to be displayed on the administration page
					array( 'SM_Settings_Fields', 'general_settings_section_header' ),	// Callback used to render the description of the section
					$this->slug		// Page on which to add this section of options
				);

				add_settings_field(
					'logs_lifespan',
					__( 'Keep logs for', 'status-machine' ),
					array( 'SM_Settings_Fields', 'number_field' ),
					$this->slug,
					'general_settings_section',
					array(
						'id'      => 'logs_lifespan',
						'page'    => $this->slug,
						'classes' => array( 'small-text' ),
						'type'    => 'number',
						'sub_desc'    => __( 'days.', 'status-machine' ),
						'desc'    => __( 'Maximum number of days to keep activity log. Leave blank to keep activity log forever (not recommended).', 'status-machine' ),
					)
				);

				if ( apply_filters( 'sm_allow_option_erase_logs', true ) ) {
					add_settings_field(
						'raw_delete_log_activities',
						__( 'Delete Log Activities', 'status-machine' ),
						array( 'SM_Settings_Fields', 'raw_html' ),
						$this->slug,
						'general_settings_section',
						array(
							'html' => sprintf( __( '<a href="%s" id="%s">Reset Database</a>', 'status-machine' ), add_query_arg( array(
								'action' => 'sm_reset_items',
								'_nonce' => wp_create_nonce( 'sm_reset_items' ),
							), admin_url( 'admin-ajax.php' ) ), 'sm-delete-log-activities' ),
							'desc' => __( 'Warning: Clicking this will delete all activities from the database.', 'status-machine' ),
						)
					);
				}
				break;

			case 'api':

				$notification_handlers = SM_Main::instance()->notifications->get_available_handlers();
				$enabled_notification_handlers = SM_Main::instance()->settings->get_option( 'notification_handlers' );

				// Loop through custom notification handlers
				foreach ( $notification_handlers as $handler_id => $handler_obj  ) {
					if ( ! is_object( $handler_obj ) )
						continue;

					add_settings_section(
						"notification_$handler_id",
						$handler_obj->name,
						array( $handler_obj, '_settings_section_callback' ),
						$this->slug
					);

					$handler_obj->settings_fields();
				}
				break;
		}
	}

	/**
	 * Returns the current section within SM's setting pages
	 *
	 * @return string
	 */
	public function get_setup_section() {
		if ( isset( $_REQUEST['sm_section'] ) )
			return strtolower( $_REQUEST['sm_section'] );

		return 'api';
	}

	/**
	 * Prints section tabs within the settings area
	 */
	private function menu_print_tabs() {
		$current_section = $this->get_setup_section();
		$sections = array(
			'api' => __( 'API', 'status-machine' ),
		);

		$sections = apply_filters( 'sm_setup_sections', $sections );

		foreach ( $sections as $section_key => $section_caption ) {
			$active = $current_section === $section_key ? 'nav-tab-active' : '';
			$url = add_query_arg( 'sm_section', $section_key );
			echo '<a class="nav-tab ' . $active . '" href="' . esc_url( $url ) . '">' . esc_html( $section_caption ) . '</a>';
		}
	}
	
	public function validate_options( $input ) {
		$options = $this->options; // CTX,L1504
		
		// @todo some data validation/sanitization should go here
		$output = apply_filters( 'sm_validate_options', $input, $options );

		// merge with current settings
		$output = array_merge( $options, $output );
		
		return $output;
	}

	public function display_settings_page() {
		?>
		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap">

			<h1 class="sm-page-title"><?php _e( 'Status Machine Settings', 'status-machine' ); ?></h1>
			<?php settings_errors(); ?>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( 'sm-options' );
				do_settings_sections( $this->slug );
				submit_button();
				?>
			</form>
			
		</div><!-- /.wrap -->
		<?php
	}
	
	public function admin_notices() {
		switch ( filter_input( INPUT_GET, 'message' ) ) {
			case 'data_erased':
				printf( '<div class="updated"><p>%s</p></div>', __( 'All activities have been successfully deleted.', 'status-machine' ) );
				break;
		}
	}
	
	public function admin_footer() {
		// TODO: move to a separate file.
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( '#sm-delete-log-activities' ).on( 'click', function( e ) {
					if ( ! confirm( '<?php echo __( 'Are you sure you want to do this action?', 'status-machine' ); ?>' ) ) {
						e.preventDefault();
					}
				} );
			} );
		</script>
		<?php
	}
	
	public function ajax_sm_reset_items() {
		if ( ! check_ajax_referer( 'sm_reset_items', '_nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'status-machine' ) );
		}
		
		SM_Main::instance()->api->erase_all_items();
		
		wp_redirect( add_query_arg( array(
				'page' => 'status-machine-settings',
				'message' => 'data_erased',
		), admin_url( 'admin.php' ) ) );
		die();
	}

	public function ajax_sm_get_properties() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
			
		$action_category = isset( $_REQUEST['action_category'] ) ? $_REQUEST['action_category'] : false;
		
		$options = SM_Main::instance()->notifications->get_settings_dropdown_values( $action_category );

		if ( ! empty( $options ) ) {
			wp_send_json_success( $options );
		}

		wp_send_json_error();
	}

	public function get_option( $key = '' ) {
		$settings = $this->get_options();
		return ! empty( $settings[ $key ] ) ? $settings[ $key ] : false;
	}
	
	/**
	 * Returns all options
	 * 
	 * @since 2.0.7
	 * @return array
	 */
	public function get_options() {
		// Allow other plugins to get SM's options.
		if ( isset( $this->options ) && is_array( $this->options ) && ! empty( $this->options ) )
			return $this->options;
		
		return apply_filters( 'sm_options', get_option( $this->slug, array() ) );
	}
	
	public function slug() {
		return $this->slug;
	}
}

// TODO: Need rewrite this class to useful tool.
final class SM_Settings_Fields {

	public static function general_settings_section_header() {
		?>
		<p><?php _e( 'These are some basic settings for Status Machine.', 'status-machine' ); ?></p>
		<?php
	}

	public static function email_notifications_section_header() {
		?>
		<p><?php _e( 'Serve yourself with custom-tailored notifications. First, define your conditions. Then, choose how the notifications will be sent.', 'status-machine' ); ?></p>
		<?php
	}
	
	public static function raw_html( $args ) {
		if ( empty( $args['html'] ) )
			return;
		
		echo $args['html'];
		if ( ! empty( $args['desc'] ) ) : ?>
			<p class="description"><?php echo $args['desc']; ?></p>
		<?php endif;
	}
	
	public static function text_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );
		
		$args = wp_parse_args( $args, array(
			'classes' => array(),
		) );
		if ( empty( $args['id'] ) || empty( $args['page'] ) )
			return;
		
		?>
		<input type="text" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="<?php echo implode( ' ', $args['classes'] ); ?>" />
		<?php if ( ! empty( $desc ) ) : ?>
		<p class="description"><?php echo $desc; ?></p>
		<?php endif;
	}

	public static function textarea_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );

		$args = wp_parse_args( $args, array(
			'classes' => array(),
			'rows'    => 5,
			'cols'    => 50,
		) );

		if ( empty( $args['id'] ) || empty( $args['page'] ) )
			return;

		?>
		<textarea id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $name ); ?>" class="<?php echo implode( ' ', $args['classes'] ); ?>" rows="<?php echo absint( $args['rows'] ); ?>" cols="<?php echo absint( $args['cols'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>

		<?php if ( ! empty( $desc ) ) : ?>
			<p class="description"><?php echo $desc; ?></p>
		<?php endif;
	}
	
	public static function number_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );
		
		$args = wp_parse_args( $args, array(
			'classes' => array(),
			'min' => '1',
			'step' => '1',
			'desc' => '',
		) );
		if ( empty( $args['id'] ) || empty( $args['page'] ) )
			return;

		?>
		<input type="number" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="<?php echo implode( ' ', $args['classes'] ); ?>" min="<?php echo $args['min']; ?>" step="<?php echo $args['step']; ?>" />
		<?php if ( ! empty( $args['sub_desc'] ) ) echo $args['sub_desc']; ?>
		<?php if ( ! empty( $args['desc'] ) ) : ?>
			<p class="description"><?php echo $args['desc']; ?></p>
		<?php endif;
	}

	public static function select_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );

		if ( empty( $options ) || empty( $id ) || empty( $page ) )
			return;
		
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php printf( '%s[%s]', esc_attr( $page ), esc_attr( $id ) ); ?>">
			<?php foreach ( $options as $name => $label ) : ?>
			<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $name, (string) $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $desc ) ) : ?>
		<p class="description"><?php echo $desc; ?></p>
		<?php endif; ?>
		<?php
	}
	
	public static function yesno_field( $args ) {
		self::_set_name_and_value( $args );
		extract( $args, EXTR_SKIP );
		
		?>
		<label class="tix-yes-no description"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value, true ); ?>> <?php _e( 'Yes', 'status-machine' ); ?></label>
		<label class="tix-yes-no description"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="0" <?php checked( $value, false ); ?>> <?php _e( 'No', 'status-machine' ); ?></label>

		<?php if ( isset( $args['description'] ) ) : ?>
		<p class="description"><?php echo $args['description']; ?></p>
		<?php endif; ?>
		<?php
	}

	public static function email_notification_buffer_field( $args ) {
		$args = wp_parse_args( $args, array(
			'classes' => array(),
		) );
		if ( empty( $args['id'] ) || empty( $args['page'] ) )
			return;

		// available action categories
		$keys = array(
			'user' 			=> __( 'User', 'status-machine' ),
			'action-type' 	=> __( 'Action Type', 'status-machine' ),
			'action-value'  => __( 'Action Performed', 'status-machine' ),
		);
		// available condition types
		$conditions = array(
			'equals' => __( 'equals to', 'status-machine' ),
			'not_equals' => __( 'not equals to', 'status-machine' ),
		);

		$common_name = sprintf( '%s[%s]', esc_attr( $args['page'] ), esc_attr( $args['id'] ) );

		// get all rows
		$rows = SM_Main::instance()->settings->get_option( $args['id'] );
		// if empty, reset to one element with the key of 1
		$rows = empty( $rows ) ? array( array( 'key' => 1 ) ) : $rows;
		?>
		<p class="description"><?php _e( 'A notification will be sent upon a successful match with the following conditions:', 'status-machine' ); ?></p>
		<div class="sm-notifier-settings">
			<ul>
			<?php foreach ( $rows as $rid => $row ) :
				$row_key 		= $row['key']; 
				$row_condition 	= isset( $row['condition'] ) ? $row['condition'] : '';
				$row_value 		= isset( $row['value'] ) ? $row['value'] : '';
				?>
				<li data-id="<?php echo $rid; ?>">
					<select name="<?php echo $common_name; ?>[<?php echo $rid; ?>][key]" class="sm-category">
						<?php foreach ( $keys as $k => $v ) : ?>
						<option value="<?php echo $k; ?>" <?php selected( $row_key, $k ); ?>><?php echo $v; ?></option>
						<?php endforeach; ?>
					</select>
					<select name="<?php echo $common_name; ?>[<?php echo $rid; ?>][condition]" class="sm-condition">
						<?php foreach ( $conditions as $k => $v ) : ?>
						<option value="<?php echo $k; ?>" <?php selected( $row_condition, $k ); ?>><?php echo $v; ?></option>
						<?php endforeach; ?>
					</select>
					<?php $value_options = SM_Main::instance()->notifications->get_settings_dropdown_values( $row_key ); ?>
					<select name="<?php echo $common_name; ?>[<?php echo $rid; ?>][value]" class="sm-value">
						<?php foreach ( $value_options as $option_key => $option_value ) : ?>
						<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, $row_value ); ?>><?php echo esc_html( $option_value ); ?></option>
						<?php endforeach; ?>
					</select>
					<a href="#" class="sm-new-rule button"><small>+</small> and</a>
					<a href="#" class="sm-delete-rule button">&times;</a>
				</li>
			<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
	
	private static function _set_name_and_value( &$args ) {
		if ( ! isset( $args['name'] ) ) {
			$args['name'] = sprintf( '%s[%s]', esc_attr( $args['page'] ), esc_attr( $args['id'] ) );
		}
		
		if ( ! isset( $args['value'] ) ) {
			$args['value'] = SM_Main::instance()->settings->get_option( $args['id'] );
		}
	}
}
