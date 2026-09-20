<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PandamusRex_Payment_Notifications_Admin {
    private static $instance;

    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}

    public function __wakeup() {}

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
    }

    public function admin_menu(){
        add_menu_page( 
            __( 'Payment Notifications', 'pandamusrex-email-webhook' ),
            __( 'Payment Notifications', 'pandamusrex-email-webhook' ),
            'manage_options',
            'pandamusrex_pmt_notif_page',
            [ $this, 'page_router' ],
            'dashicons-pets',
            59 // below first separator
        );
    }

    public function page_router() {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            wp_admin_notice(
                __( 'PandamusRex Email Webhook for WooCommerce requires the WooCommerce plugin to be active.', 'pandamusrex-memberships' ),
                [ 'type' => 'error' ]
            );
            return;
        }

        // TODO handle POSTs
        // POST action can be do_delete or do_update
        if ( isset( $_POST['action'] ) ) {
            $post_action = $_POST['action'];

            if ( $post_action == 'do_delete' ) {
                $this->echo_do_delete();
                return;
            }

            if ( $post_action == 'do_update' ) {
                $this->echo_do_update();
                return;
            }

            wp_admin_notice(
                __( 'Invalid POST action', 'pandamusrex-email-webhook' ),
                [ 'type' => 'error' ]
            );
            return;
        }

        // Handle our GETs
        if ( isset( $_GET[ 'action' ] ) ) {
            $notification_id = 0;
            if ( isset( $_GET[ 'notification_id' ] ) ) {
                $notification_id = $_GET[ 'notification_id' ];
                $notification_id = intval( $notification_id );
            }
            if ( $_GET[ 'action' ] == 'edit' ) {
                $this->echo_pmt_notif_edit_page( $notification_id );
                return;
            }

            if ( $_GET[ 'action' ] == 'delete' ) {
                $this->echo_pmt_notif_delete_page( $notification_id );
                return;
            }
        }

        // Otherwise, show the big list view
        $this->echo_pmt_notif_page();
    }

    public function echo_do_delete() {
        wp_admin_notice(
            __( 'Delete not yet implemented', 'pandamusrex-email-webhook' ),
                [ 'type' => 'error' ]
        );
    }

    public function echo_do_update() {
        // Check id (field is id in form)
        // Check notification_nonce (notification-{id}) nonce
        // Get order assignment (order_id in form)
        // Get mark_order_complete

        // Get id from POST
        if ( ! isset( $_POST['id'] ) ) {
            wp_admin_notice(
                __( 'Invalid POST request - do_update action - no id in POST data', 'pandamusrex-email-webhook' ),
                [ 'type' => 'error' ]
            );
            return;
        }

        $notification_id = sanitize_text_field( $_POST['id'] );

        // Check nonce in POST (notification-$id)
        if ( ! wp_verify_nonce( $_POST['notification_nonce'], 'notification-' . $notification_id ) ) {
            wp_admin_notice(
                __( 'Invalid POST request - do_update action - bad nonce in POST data', 'pandamusrex-email-webhook' ),
                [ 'type' => 'error' ]
            );
            return;
        }

        $required_fields = [ 'order_id' ];
        foreach ( $required_fields as $required_field ) {
            if (! isset( $_POST[$required_field] ) ) {
                wp_admin_notice(
                    __( 'Invalid POST request - do_update action - incomplete POST data', 'pandamusrex-email-webhook' ),
                    [ 'type' => 'error' ]
                );
                return;
            }
        }

        // Get the original notification
        $notification = PandamusRex_Email_Webhook_Db::get_notification_by_id( $notification_id );
        if ( is_wp_error( $notification ) ) {
            wp_admin_notice(
                $notification->get_error_message(),
                [ 'type' => 'error' ]
            );
            return;
        }

        // Get the form data
        $order_id = intval( sanitize_text_field( $_POST['order_id'] ) );
        $mark_order_complete = isset( $_POST['mark_order_complete'] );

        // Process it all
        // Is this a request to clear the assigned order for this email?
        if ( $order_id == 0 ) {
            // Had the email been assigned to an order?
            if ( $notification[ 'order_id' ] != 0 ) {
                $result = PandamusRex_Email_Webhook_Db::update_webhook_order_id( $notification_id, 0 );
                if (is_wp_error( $result ) ) {
                    wp_admin_notice(
                        __( 'Unable to update order', 'pandamusrex-email-webhook' ),
                        [ 'type' => 'error' ]
                    );
                } else {
                    wp_admin_notice(
                        __( 'Removed order assignment', 'pandamusrex-email-webhook' ),
                        [ 'type' => 'success' ]
                    );
                }
            } else {
                wp_admin_notice(
                    __( 'No changes detected', 'pandamusrex-email-webhook' ),
                    [ 'type' => 'success' ]
                );
            }
        } else {
            // Does this represent a change to the assigned order for this email?
            if ( $notification[ 'order_id' ] != $order_id ) {
                $result = PandamusRex_Email_Webhook_Db::update_webhook_order_id( $notification_id, $order_id );
                if (is_wp_error( $result ) ) {
                    wp_admin_notice(
                        __( 'Unable to update order', 'pandamusrex-email-webhook' ),
                        [ 'type' => 'error' ]
                    );
                } else {
                    if ( $mark_order_complete ) {
                        $order = new WC_Order($order_id); 
                        $order->update_status('completed');
                        wp_admin_notice(
                            __( 'Updated order assignment and updated order status to complete', 'pandamusrex-email-webhook' ),
                            [ 'type' => 'success' ]
                        );
                    } else {
                        wp_admin_notice(
                            __( 'Updated order assignment', 'pandamusrex-email-webhook' ),
                            [ 'type' => 'success' ]
                        );
                    }
                }
            } else {
                wp_admin_notice(
                    __( 'No changes detected', 'pandamusrex-email-webhook' ),
                    [ 'type' => 'success' ]
                );
            }
        }

        $this->echo_pmt_notif_edit_page( $notification_id );
    }

    // Big list view
    public function echo_pmt_notif_page() {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">';
        esc_html_e( 'Payment Notifications', 'pandamusrex-email-webhook' );
        echo '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<p>&nbsp;</p>';

        echo '<table class="wp-list-table widefat fixed striped table-view-list">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col" class="manage-column">' . esc_html__( 'Sender', 'pandamusrex-payment-notifications' ) . '</th>';
        echo '<th scope="col" class="manage-column">' . esc_html__( 'Subject', 'pandamusrex-payment-notifications' ) . '</th>';
        echo '<th scope="col" class="manage-column">' . esc_html__( 'Assigned to Order', 'pandamusrex-payment-notifications' ) . '</th>';
        echo '<th scope="col" class="manage-column">' . esc_html__( 'Received', 'pandamusrex-payment-notifications' ) . '</th>';
        echo '</tr>';
        echo '</thead>';

        $notifications = PandamusRex_Email_Webhook_Db::get_all_notifications();
        if ( empty( $notifications ) ) {
            echo '<tr class="no-items">';
            echo '<td class="colspanchange" colspan="4">';
            esc_html_e( 'No payment notifications found.', 'pandamusrex-payment-notifications' );
            echo '</td>';
            echo '</tr>';
        } else {
            foreach ( $notifications as $notification ) {
                echo '<tr>';
                echo '<td>';
                echo esc_html( $notification[ 'email_sender' ] );
                echo '<div class="row-actions">';
                echo '<span class="id">';
                echo esc_html__( 'ID:', 'pandamusrex-payment-notifications' );
                echo ' ';
                echo esc_html( $notification[ 'id' ] );
                echo ' | ';
                echo '</span>';
                echo '<span class="edit">';
                $edit_url = "?page=pandamusrex_pmt_notif_page&action=edit&notification_id=" . $notification[ 'id' ];
                echo '<a href="' . $edit_url . '">';
                echo esc_html__( 'Edit', 'pandamusrex-payment-notifications' );
                echo '</a>';
                echo ' | ';
                echo '<span class="delete">';
                $delete_url = "?page=pandamusrex_pmt_notif_page&action=delete&notification_id=" . $notification[ 'id' ];
                echo '<a href="' . $delete_url . '">';
                echo esc_html__( 'Delete', 'pandamusrex-payment-notifications' );
                echo '</a>';
                echo '</span>';
                echo '</div>';
                echo '</td>';
                echo '<td>';
                echo esc_html( $notification[ 'email_subject' ] );
                echo '</td>';
                echo '<td>';
                $order_id = $notification[ 'order_id' ];
                if ( $order_id == 0 ) {
                    echo '-';
                } else {
                    echo esc_html( $order_id );
                }
                echo '</td>';
                echo '<td>';
                $email_received = $notification[ 'email_received' ];
                echo esc_html( $email_received );
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '<tfoot>';
        echo '<tr>';
        echo '<th scope="col" class="manage-column">' . esc_html__( 'Sender', 'pandamusrex-payment-notifications' ) . '</th>';
        echo '<th scope="col" class="manage-column">' . esc_html__( 'Subject', 'pandamusrex-payment-notifications' ) . '</th>';
        echo '<th scope="col" class="manage-column">' . esc_html__( 'Assigned to Order', 'pandamusrex-payment-notifications' ) . '</th>';
        echo '<th scope="col" class="manage-column">' . esc_html__( 'Received', 'pandamusrex-payment-notifications' ) . '</th>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';

        echo '</div>';
    }

    public function echo_order_selector( $preselect_order_id = 0 ) {
        echo '<select name="order_id" id="order_id">';
        echo '<option value="0">';
        echo esc_html( 'None', 'pandamusrex-payment-notifications' );
        echo '</option>';
        $args = array(
            'limit'      => -1,
        );
        $loop_orders = wc_get_orders( $args );
        foreach ( $loop_orders as $loop_order ) {
            if ( is_a( $loop_order, 'WC_Order_Refund' ) ) {
                continue; // WC_Order_Refund doesn't implement get_customer_id();
            }
            $loop_customer_id = $loop_order->get_customer_id();
            if ( $loop_customer_id ) {
                $loop_order_id = $loop_order->get_id();
                $loop_order_date = $loop_order->get_date_created();
                $formatted_loop_order_date = $loop_order_date->date( 'd/m/Y' );
                $loop_customer = new WC_Customer( $loop_customer_id );
                $loop_customer_name = $loop_customer->get_first_name() . " " . $loop_customer->get_last_name();
                $loop_customer_email = $loop_customer->get_email();
                $selected = ( $loop_order_id == $preselect_order_id ) ? 'selected' : '';
                echo '<option value="' . esc_attr( $loop_order_id ) . '" ' . $selected . '>';
                echo esc_html( '#' . $loop_order_id . ' - ' . $loop_customer_name . ' - ' . $loop_customer_email . ' - ' . $formatted_loop_order_date );
                echo '</option>';
            }
        }
        echo '</select>';
    }

    // Single view
    public function echo_pmt_notif_edit_page( $notification_id ) {
        if ( $notification_id < 1 ) {
            wp_admin_notice(
                __( 'Invalid request - bad notification ID', 'pandamusrex-email-webhook' ),
                [ 'type' => 'error' ]
            );
            return;
        }

        $notification = PandamusRex_Email_Webhook_Db::get_notification_by_id( $notification_id );
        if ( is_wp_error( $notification ) ) {
            wp_admin_notice(
                $notification->get_error_message(),
                [ 'type' => 'error' ]
            );
            return;
        }

        $notification_history_items = PandamusRex_Email_Webhook_History_Db::get_history_for_webhook( $notification_id );
        if ( is_wp_error( $notification_history_items ) ) {
            wp_admin_notice(
                $notification_history_items->get_error_message(),
                [ 'type' => 'error' ]
            );
            return;
        }

        echo '<div class="wrap">';
            echo '<h1 class="wp-heading-inline">';
            esc_html_e( 'Edit Payment Notification', 'pandamusrex-email-webhook' );
            echo '</h1>';
            echo '<hr class="wp-header-end">';

            echo '<form method="post">';
                echo '<input type="hidden" name="action" id="action" value="do_update" />';
                $nonce =  wp_create_nonce( 'notification-' . $notification_id );
                echo '<input type="hidden" name="notification_nonce" id="notification_nonce" value="' . esc_attr( $nonce ) . '" />';
                echo '<input name="id" type="hidden" id="id" value="' . esc_attr( $notification_id ) . '">';

                echo '<div id="poststuff">';
                    echo '<div id="post-body" class="metabox-holder columns-2">';
                        echo '<div id="post-body-content">';
                            echo '<h2 class="pandamusrex-notification-subject-single">';
                            echo esc_html( $notification[ 'email_subject' ] );
                            echo '</h2>';
                            echo '<p>';
                                echo '<b>';
                                esc_html_e( 'Received:', 'pandamusrex-email-webhook' );
                                echo '</b>';
                                echo ' ';
                                echo esc_html( $notification[ 'email_received' ] );
                            echo '</p>';
                            echo '<p>';
                                echo '<b>';
                                esc_html_e( 'From:', 'pandamusrex-email-webhook' );
                                echo '</b>';
                                echo ' ';
                                echo esc_html( $notification[ 'email_sender' ] );
                            echo '</p>';
                            echo '<div id="postassignedorder" class="postbox">';
                                echo '<div class="postbox-header">';
                                    echo '<h2 class="hndle">';
                                    esc_html_e( 'Assign to Order', 'pandamusrex-email-webhook' );
                                    echo '</h2>';
                                echo '</div>';
                                echo '<div class="inside">';
                                    // Order Selector
                                    $this->echo_order_selector( $notification[ 'order_id' ] );
                                    echo '<br/>';
                                    echo '<br/>';
                                    // Mark Order Paid Checkbox
                                    echo '<input type="checkbox" id="mark_order_complete" name="mark_order_complete" value="1" checked />';
                                    echo '<label for="mark_order_complete">';
                                        esc_html_e( 'Set Order Status to Completed (Paid)', 'pandamusrex-email-webhook' );
                                    echo '</label>';
                                echo '</div>';
                            echo '</div>';
                            echo '<div id="postemailcontent" class="postbox">';
                                echo '<div class="postbox-header">';
                                    echo '<h2 class="hndle">';
                                    esc_html_e( 'Email Body', 'pandamusrex-email-webhook' );
                                    echo '</h2>';
                                echo '</div>';
                                echo '<div class="inside">';
                                    echo wp_kses_post( nl2br( $notification[ 'email_body' ] ) );
                                echo '</div>';
                            echo '</div>';
                        echo '</div>';
                        echo '<div id="postbox-container-1" class="postbox-container">';
                            echo '<div id="side-sortables" class="meta-box-sortables ui-sortable">';
                                echo '<div id="submitdiv" class="postbox">';
                                    echo '<div class="postbox-header">';
                                        echo '<h2 class="hndle ui-sortable-handle">';
                                            esc_html_e( 'Actions', 'pandamusrex-email-webhook' );
                                        echo '</h2>';
                                    echo '</div>';
                                    echo '<div class="inside">';
                                        echo '<div class="submitbox" id="submitpost">';
                                            echo '<div id="minor-publishing">';
                                            echo '</div>';
                                            echo '<div id="major-publishing-actions">';
                                                echo '<div id="delete-action">';
                                                    echo '<a class="submitdelete deletion" href="#">Move to Trash</a>';
                                                echo '</div>';
                                                echo '<div id="publishing-action">';
                                                    echo '<input type="submit" name="publish" id="publish" class="button button-primary button-large" value="Save Changes">';
                                                echo '</div>';
                                                echo '<div class="clear">';
                                                echo '</div>';
                                            echo '</div>';
                                        echo '</div>';
                                    echo '</div>'; // .inside
                                echo '</div>'; // #submitdiv
                            echo '</div>';
                        echo '</div>'; // #postbox-container-1
                        echo '<div id="postbox-container-2" class="postbox-container">';
                            echo '<div id="postcustom" class="postbox">';
                                echo '<div class="postbox-header">';
                                    echo '<h2 class="hndle">';
                                    esc_html_e( 'History', 'pandamusrex-email-webhook' );
                                    echo '</h2>';
                                echo '</div>';
                                echo '<div class="inside">';
                                    if ( empty( $notification_history_items ) ) {
                                        esc_html_e( 'No history available.', 'pandamusrex-email-webhook' );
                                    } else {
                                        echo '<table>';
                                            echo '<tbody>';
                                            foreach ( $notification_history_items as $item ) {
                                                echo '<tr>';
                                                echo '<td>';
                                                echo esc_html( $item[ 'note_created'] );
                                                echo '</td>';
                                                echo '<td>';
                                                echo esc_html( $item[ 'note'] );
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        echo '</tbody>';
                                        echo '</table>';
                                    }
                                echo '</div>'; // .inside
                            echo '</div>'; // #postcustom .postbox
                        echo '</div>'; // #postbox-container-2
                    echo '</div>'; // post-body
                echo '</div>'; // #poststuff
            echo '</form>';
        echo '</div>'; // #wrap
    }

    public function echo_pmt_notif_delete_page( $notification_id ) {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">';
        esc_html_e( 'Delete Payment Notification', 'pandamusrex-email-webhook' );
        echo '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<p>&nbsp;</p>';
        echo '</div>';
    }

}

PandamusRex_Payment_Notifications_Admin::get_instance();