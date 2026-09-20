<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PandamusRex_Email_Webhook_Rest_Controller extends \WP_REST_Controller {

    protected $namespace = 'pandamusrex/v1';
    protected $rest_base = 'email-webhook';

    public function register_routes() {
        // TODO - delete this test route before production
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_silence' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'post_email' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ]
        );
    }

    public function get_silence() {
        return 'Silence is golden.';
    }

    public function post_email( $request ) {
        $json = $request->get_body();
        $decoded_body = json_decode( $json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->debug( "Error decoding POST body as json" );
                wc_get_logger()->debug( $json );
                return new WP_REST_Response( [], 400 );
            }
        }

        $email_subject = sanitize_text_field( $decoded_body[ 'email_subject' ] );
        $email_body = sanitize_text_field( $decoded_body[ 'email_body' ] );
        $email_received = sanitize_text_field( $decoded_body[ 'email_received' ] );
        $email_sender = $decoded_body[ 'email_sender' ];
        $email_sender = str_replace( "<", "", $email_sender );
        $email_sender = str_replace( ">", "", $email_sender );

        $result = PandamusRex_Email_Webhook_Db::record_webhook(
            $email_subject,
            $email_received,
            $email_sender,
            $email_body
        );

        if ( is_wp_error( $result ) ) {
                wc_get_logger()->debug( "Error saving webhook data to database" );
                return new WP_REST_Response( [], 400 );
        }

        return new WP_REST_Response( true, 200 );
    }

    public function check_permission() {
        return current_user_can( 'edit_users' );
    }
}
