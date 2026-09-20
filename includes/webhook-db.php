<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PandamusRex_Email_Webhook_Db {
    public static function getTableName() {
        global $wpdb;
        return $wpdb->prefix . 'pandamusrex_email_wbhks';
    }

    public static function create_tables() {
        global $wpdb;
        $table_name = PandamusRex_Email_Webhook_Db::getTableName();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            email_subject VARCHAR(255) NOT NULL,
            email_received DATETIME NOT NULL,
            email_sender VARCHAR(255) NOT NULL,
            email_body LONGTEXT,
            order_id BIGINT(20),
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); // Include dbDelta()
        dbDelta( $sql );
    }

    public static function get_all_notifications() {
        global $wpdb;

        $sql = 'SELECT * FROM %i ORDER BY email_received DESC';
        $vars = [ self::getTableName() ];
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $vars ), ARRAY_A );

        return $results;
    }

    public static function get_notification_by_id( $id ) {
        global $wpdb;

        $sql = 'SELECT * FROM %i WHERE id = %d';
        $vars = [ self::getTableName(), $id ];
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $vars ), ARRAY_A );

        if ( is_array( $results ) and ( count( $results ) > 0 ) ) {
            return $results[0];
        }

        return new WP_Error( 'pandamusrex-email-webhook', $wpdb->last_error );
    }

    public static function record_webhook( $email_subject, $email_received, $email_sender, $email_body ) {
        global $wpdb;

        $data = [
            'email_subject' => $email_subject,
            'email_received' => $email_received,
            'email_sender' => $email_sender,
            'email_body' => $email_body,
            'order_id' => 0 // needs assignment
        ];

        $result = $wpdb->insert(
            self::getTableName(),
            $data,
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%d'
            ]
        );
        if ( false === $result ) {
            return new WP_Error( 'pandamusrex-email-webhook', $wpdb->last_error );
        }

        $data[ 'id' ] = $wpdb->insert_id;

        $result = PandamusRex_Email_Webhook_History_Db::add_history_for_webhook(
            $data[ 'id' ],
            0,
            __( 'Email added to database', 'pandamusrex-email-webhook' )
        );

        if ( false === $result ) {
            return new WP_Error( 'pandamusrex-email-webhook', $wpdb->last_error );
        }

        return $data;
    }

    public static function update_webhook_order_id( $webhook_id, $order_id ) {
        global $wpdb;

        $data = [
            'order_id' => $order_id
        ];

        $result = $wpdb->update(
            self::getTableName(),
            $data, // data
            [
                'id' => $webhook_id // where
            ],
            [
                '%d' // data format
            ],
            [
                '%d' // where format
            ]
        );

        if ( false === $result ) {
            return new WP_Error( 'pandamusrex-email-webhook', $wpdb->last_error );
        }

        $note = sprintf(
            __( 'Updated order ID to %d', 'pandamusrex-email-webhook' ),
            $order_id
        );

        return PandamusRex_Email_Webhook_History_Db::add_history_for_webhook(
            $webhook_id, // webhook id
            0,           // user id
            $note        // note
        );
    }

    public static function delete_webhook( $webhook_id ) {
        global $wpdb;

        $result = $wpdb->delete(
            self::getTableName(),
            [
                'id' => $webhook_id
            ],
            [
                '%d'
            ]
        );

        if ( false === $result ) {
            return new WP_Error( 'pandamusrex-email-webhook', $wpdb->last_error );
        }

        return true;
    }
}