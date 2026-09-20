<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PandamusRex_Email_Webhook_History_Db {
    public static function getTableName() {
        global $wpdb;
        return $wpdb->prefix . 'pandamusrex_email_wbhks_hst';
    }

    public static function create_tables() {
        global $wpdb;
        $table_name = PandamusRex_Email_Webhook_History_Db::getTableName();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            webhook_id BIGINT(20) NOT NULL,
            note_created DATETIME NOT NULL,
            user_id BIGINT(20),
            note VARCHAR(255),
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); // Include dbDelta()
        dbDelta( $sql );
    }

    public static function get_history_for_webhook( $webhook_id ) {
        global $wpdb;

        $sql = 'SELECT * FROM %i WHERE id = %d ORDER BY note_created DESC, id DESC';
        $vars = [
            self::getTableName(),
            $webhook_id
        ];
        return $wpdb->get_results( $wpdb->prepare( $sql, $vars ), ARRAY_A );
    }

    public static function add_history_for_webhook( $webhook_id, $user_id, $note ) {
        global $wpdb;

        $wp_tz = wp_timezone_string();
        $note_created_dt = new DateTime( "now", new DateTimeZone( $wp_tz ) );
        $note_created = $note_created_dt->format( "Y-m-d h:i:s" );

        $data = [
            'webhook_id' => $webhook_id,
            'note_created' => $note_created,
            'user_id' => $user_id,
            'note' => $note
        ];

        $result = $wpdb->insert(
            self::getTableName(),
            $data,
            [
                '%d',
                '%s',
                '%d',
                '%s'
            ]
        );
        if ( false === $result ) {
            return new WP_Error( 'pandamusrex-email-webhook', $wpdb->last_error );
        }

        $data[ 'id' ] = $wpdb->insert_id;
        return $data;
    }
}