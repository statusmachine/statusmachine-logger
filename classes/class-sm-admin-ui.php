<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SM_Admin_Ui {

	/**
	 * @var SM_Status_Machine_List_Table
	 */
	protected $_list_table = null;
	
	protected $_screens = array();

	public function create_admin_menu() {
		$menu_capability = current_user_can( 'view_sm_status_machine' ) ? 'view_sm_status_machine' : 'edit_pages';
		
		$this->_screens['main'] = add_menu_page( __( 'Status Machine', 'status-machine' ), __( 'Status Machine', 'status-machine' ), $menu_capability, 'status_machine_page', array( &$this, 'status_machine_page_func' ), '', '2.1' );
		
		// Just make sure we are create instance.
		add_action( 'load-' . $this->_screens['main'], array( &$this, 'get_list_table' ) );
	}

	public function status_machine_page_func() {
		$this->get_list_table()->prepare_items();
		?>
		<div class="wrap">
			<h1 class="sm-page-title"><?php _e( 'Status Machine', 'status-machine' ); ?></h1>

			<form id="activity-filter" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php $this->get_list_table()->display(); ?>
			</form>
		</div>
		
		<?php // TODO: move to a separate file. ?>
		<style>
			.sm-pt {
				color: #ffffff;
				padding: 1px 4px;
				margin: 0 5px;
				font-size: 1em;
				border-radius: 3px;
				background: #808080;
				font-family: inherit;
			}
			.toplevel_page_activity_log_page .manage-column {
				width: auto;
			}
			.toplevel_page_activity_log_page .column-description {
				width: 20%;
			}
			#adminmenu #toplevel_page_activity_log_page div.wp-menu-image:before {
				content: "\f321";
			}
			@media (max-width: 767px) {
				.toplevel_page_activity_log_page .manage-column {
					width: auto;
				}
				.toplevel_page_activity_log_page .column-date,
				.toplevel_page_activity_log_page .column-author {
					display: table-cell;
					width: auto;
				}
				.toplevel_page_activity_log_page .column-ip,
				.toplevel_page_activity_log_page .column-description,
				.toplevel_page_activity_log_page .column-label {
					display: none;
				}
				.toplevel_page_activity_log_page .column-author .avatar {
					display: none;
				}
			}
		</style>
		<?php
	}
	
	public function admin_header() {
		// TODO: move to a separate file.
		?><style>
			#adminmenu #toplevel_page_status_machine_page div.wp-menu-image::before {
				content: none !important;
			}
			#adminmenu #toplevel_page_status_machine_page div.wp-menu-image {
				background: url(<?php echo plugins_url('../assets/images/sm-sprite.png', __FILE__) ?>) no-repeat 3px -32px !important;
			}
		#adminmenu #toplevel_page_status_machine_page:hover .wp-menu-image {
			background-position: 3px 2px !important;
		}
		#adminmenu #toplevel_page_status_machine_page.wp-has-current-submenu .wp-menu-image {
			background-position: 3px -66px !important;
		}
		</style>
	<?php
	}
	
	public function ajax_sm_install_elementor_set_admin_notice_viewed() {
		update_user_meta( get_current_user_id(), '_sm_elementor_install_notice', 'true' );
	}

	public function admin_notices() {
		if ( ! current_user_can( 'install_plugins' ) || $this->_is_elementor_installed() )
			return;
		

		if ( 'true' === get_user_meta( get_current_user_id(), '_sm_elementor_install_notice', true ) )
			return;
		
		if ( ! in_array( get_current_screen()->id, array( 'toplevel_page_status_machine_page', 'dashboard', 'plugins', 'plugins-network' ) ) ) {
			return;
		}

		add_action( 'admin_footer', array( &$this, 'print_js' ) );

		$install_url = self_admin_url( 'plugin-install.php?tab=search&s=elementor' );
		?>
		<style>
			.notice.sm-notice {
				border-left-color: #9b0a46 !important;
				padding: 20px;
			}
			.rtl .notice.sm-notice {
				border-right-color: #9b0a46 !important;
			}
			.notice.sm-notice .sm-notice-inner {
				display: table;
				width: 100%;
			}
			.notice.sm-notice .sm-notice-inner .sm-notice-icon,
			.notice.sm-notice .sm-notice-inner .sm-notice-content,
			.notice.sm-notice .sm-notice-inner .sm-install-now {
				display: table-cell;
				vertical-align: middle;
			}
			.notice.sm-notice .sm-notice-icon {
				color: #9b0a46;
				font-size: 50px;
				width: 50px;
			}
			.notice.sm-notice .sm-notice-content {
				padding: 0 20px;
			}
			.notice.sm-notice p {
				padding: 0;
				margin: 0;
			}
			.notice.sm-notice h3 {
				margin: 0 0 5px;
			}
			.notice.sm-notice .sm-install-now {
				text-align: center;
			}
			.notice.sm-notice .sm-install-now .sm-install-button {
				background-color: #9b0a46;
				color: #fff;
				border-color: #7c1337;
				box-shadow: 0 1px 0 #7c1337;
				padding: 5px 30px;
				height: auto;
				line-height: 20px;
				text-transform: capitalize;
			}
			.notice.sm-notice .sm-install-now .sm-install-button i {
				padding-right: 5px;
			}
			.rtl .notice.sm-notice .sm-install-now .sm-install-button i {
				padding-right: 0;
				padding-left: 5px;
			}
			.notice.sm-notice .sm-install-now .sm-install-button:hover {
				background-color: #a0124a;
			}
			.notice.sm-notice .sm-install-now .sm-install-button:active {
				box-shadow: inset 0 1px 0 #7c1337;
				transform: translateY(1px);
			}
			@media (max-width: 767px) {
				.notice.sm-notice {
					padding: 10px;
				}
				.notice.sm-notice .sm-notice-inner {
					display: block;
				}
				.notice.sm-notice .sm-notice-inner .sm-notice-content {
					display: block;
					padding: 0;
				}
				.notice.sm-notice .sm-notice-inner .sm-notice-icon,
				.notice.sm-notice .sm-notice-inner .sm-install-now {
					display: none;
				}
			}
		</style>
		<?php
	}

	public function print_js() {
		?>
		<script>jQuery( function( $ ) {
				$( 'div.notice.sm-install-elementor' ).on( 'click', 'button.notice-dismiss', function( event ) {
					event.preventDefault();

					$.post( ajaxurl, {
						action: 'sm_install_elementor_set_admin_notice_viewed'
					} );
				} );
			} );</script>
		<?php
	}
	
	public function __construct() {
		add_action( 'admin_menu', array( &$this, 'create_admin_menu' ), 20 );
		add_action( 'admin_head', array( &$this, 'admin_header' ) );
		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
		add_action( 'wp_ajax_sm_install_elementor_set_admin_notice_viewed', array( &$this, 'ajax_sm_install_elementor_set_admin_notice_viewed' ) );
	}
	
	private function _is_elementor_installed() {
		$file_path = 'elementor/elementor.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $file_path ] );
	}

	/**
	 * @return SM_Status_Machine_List_Table
	 */
	public function get_list_table() {
		if ( is_null( $this->_list_table ) )
			$this->_list_table = new SM_Status_Machine_List_Table( array( 'screen' => $this->_screens['main'] ) );
		
		return $this->_list_table;
	}
}
