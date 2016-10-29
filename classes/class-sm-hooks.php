<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SM_Hooks {
	
	public function __construct() {
		// Load abstract class.
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/abstract-class-sm-hook-base.php' );
		
		// TODO: Maybe I will use with glob() function for this.
		// Load all our hooks.
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-user.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-attachment.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-menu.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-options.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-plugins.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-posts.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-taxonomy.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-theme.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-widgets.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-core.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-export.php' );
		include( plugin_dir_path( STATUS_MACHINE__FILE__ ) . '/hooks/class-sm-hook-comments.php' );
		
		new AAL_Hook_User();
		new AAL_Hook_Attachment();
		new AAL_Hook_Menu();
		new AAL_Hook_Options();
		new AAL_Hook_Plugins();
		new AAL_Hook_Posts();
		new AAL_Hook_Taxonomy();
		new AAL_Hook_Theme();
		new AAL_Hook_Widgets();
		new AAL_Hook_Core();
		new AAL_Hook_Export();
		new AAL_Hook_Comments();
	}
}
