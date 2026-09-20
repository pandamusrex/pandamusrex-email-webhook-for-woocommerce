<?php

/**
 * Plugin Name: PandamusRex Email Webhook for WooCommerce
 * Version: 1.3.0
 * Plugin URI: https://github.com/pandamusrex/pandamusrex-email-webhook-for-woocommerce
 * Description: Assign payment receipt emails and complete orders with a webhook that connects your email to WooCommerce.
 * Author: PandamusRex
 * Author URI: https://www.github.com/pandamusrex/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.4
 * Tested up to: 6.8
 *
 * Text Domain: pandamusrex-email-webhook-for-woocommerce
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author PandamusRex
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once( plugin_dir_path(__FILE__) . 'includes/webhook-db.php' );
register_activation_hook( __FILE__, [ 'PandamusRex_Email_Webhook_Db', 'create_tables' ] );

require_once( plugin_dir_path(__FILE__) . 'includes/webhook-history-db.php' );
register_activation_hook( __FILE__, [ 'PandamusRex_Email_Webhook_History_Db', 'create_tables' ] );

require_once( plugin_dir_path(__FILE__) . 'includes/admin.php' );

class PandamusRex_Email_Webhook_for_WooCommerce {
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
        add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
    }

    public function rest_api_init() {
        require_once( plugin_dir_path(__FILE__) . 'includes/rest-controller.php' );
        $rest_controller = new PandamusRex_Email_Webhook_Rest_Controller();
        $rest_controller->register_routes();
    }

    public function admin_enqueue_scripts() {
        wp_enqueue_style(
            'pandamusrex-admin-notification-styles',
            plugin_dir_url( __FILE__ ) . 'css/styles.css'
        );
    }
}

PandamusRex_Email_Webhook_for_WooCommerce::get_instance();
