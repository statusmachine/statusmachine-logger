<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Contextual help texts
 *
 * Class SM_Help
 */
class SM_Help {

    public function __construct() {
        add_action( 'in_admin_header', array( $this, 'contextual_help' ) );
    }

    public function contextual_help() {
        $screen = get_current_screen();

        switch ( $screen->id ) {
            case 'activity-log_page_status-machine-settings':
                $screen->add_help_tab( array(
                    'title' => __( 'Overview', 'status-machine' ),
                    'id' => 'sm-overview',
                    'content' => '
                    <h3>' . __( 'Notifications', 'status-machine' ) . '</h3>
                    <p>' . __( 'This screen lets you control what will happen once a user on your site does something you define. For instance, let us assume that you have created a user on your site
                    for your content editor. Now, let\'s say that every time that user updates a post, you want to know about it. You can easily do it from this page.', 'status-machine' ) . '</p>',
                ) );
                break;
        }
    }
}
