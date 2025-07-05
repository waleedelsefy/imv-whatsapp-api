<?php
/**
 * Plugin Name:       IMV WhatsApp API
 * Plugin URI:        https://imvagency.net/
 * Description:       A custom WordPress plugin to integrate WooCommerce with WhatsApp, providing custom API endpoints, order status notifications, and an advanced customer wallet system.
 * Version:           7.0
 * Author:            waleed elsefy
 * Author URI:        https://imvagency.net/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       imv-api
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'IMV_API_PLUGIN_DIR' ) ) {
    define( 'IMV_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * This function allows guest payment for orders if the "guest checkout" option is enabled in WooCommerce.
 */
function imv_api_allow_guest_payment_for_order( $is_required ) {
    // If guest checkout is disabled site-wide, this won't have an effect.
    // This filter ensures that even if an order is assigned to a user,
    // the payment page doesn't force a login, respecting the guest checkout flow.
    if ( function_exists('is_checkout_pay_page') && is_checkout_pay_page() && isset( $_GET['key'] ) ) {
        return false;
    }
    return $is_required;
}
add_filter( 'woocommerce_login_form_required', 'imv_api_allow_guest_payment_for_order' );


final class IMV_WhatsApp_API_Main {
    private static $instance = null;
    private $loader;
    private $core;
    private $wallet;
    private $admin;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();

        $this->loader = new IMV_API_Loader();
        $this->core   = new IMV_API_Core();
        $this->wallet = new IMV_API_Wallet();
        $this->admin  = new IMV_API_Admin();

        $this->define_hooks();
        $this->loader->run();
    }

    private function load_dependencies() {
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-loader.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-core.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-handlers.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-wallet.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-admin.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-logger.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-helpers.php';
    }

    private function define_hooks() {
        // Core
        $this->loader->add_action( 'init', $this->core, 'load_textdomain' );
        $this->loader->add_action( 'rest_api_init', $this->core, 'register_api_endpoints' );
        $this->loader->add_action( 'init', $this->core, 'register_pending_assessment_order_status' );
        $this->loader->add_filter( 'wc_order_statuses', $this->core, 'add_pending_assessment_to_order_statuses' );
        $this->loader->add_action( 'woocommerce_order_status_changed', $this->core, 'send_direct_whatsapp_notification', 10, 4 );

        // Wallet
        $this->loader->add_action( 'user_register', $this->wallet, 'initialize_customer_wallet' );
        $this->loader->add_action( 'woocommerce_created_customer', $this->wallet, 'initialize_customer_wallet_on_api_create', 10, 1 );
        $this->loader->add_action( 'show_user_profile', $this->wallet, 'add_wallet_fields_to_user_profile' );
        $this->loader->add_action( 'edit_user_profile', $this->wallet, 'add_wallet_fields_to_user_profile' );
        $this->loader->add_action( 'personal_options_update', $this->wallet, 'save_wallet_fields_from_user_profile' );
        $this->loader->add_action( 'edit_user_profile_update', $this->wallet, 'save_wallet_fields_from_user_profile' );
        $this->loader->add_action( 'woocommerce_order_status_changed', $this->wallet, 'deduct_from_pending_on_order_completion', 15, 4 );
        $this->loader->add_action( 'woocommerce_order_status_completed', $this->wallet, 'add_funds_on_topup_order_completion', 10, 1 );
        $this->loader->add_action( 'woocommerce_subscription_payment_complete', $this->wallet, 'add_funds_from_subscription', 10, 1 );

        // Admin
        $this->loader->add_action( 'admin_menu', $this->admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_init', $this->admin, 'settings_init' );
    }
}

function imv_api_activate() {
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-activator.php';
    IMV_API_Activator::activate();
}
register_activation_hook( __FILE__, 'imv_api_activate' );

function imv_api_deactivate() {
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-deactivator.php';
    IMV_API_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'imv_api_deactivate' );

IMV_WhatsApp_API_Main::get_instance();
