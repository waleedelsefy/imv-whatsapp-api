<?php
/**
 * Plugin Name:       IMV WhatsApp API
 * Plugin URI:        https://imvagency.net/
 * Description:       A custom WordPress plugin to integrate WooCommerce with WhatsApp, providing custom API endpoints, order status notifications, and an advanced customer wallet system with OTP login.
 * Version:           6.3
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
 * This function handles the auto-login process. It runs on every page load
 * before most of WordPress and WooCommerce, ensuring the user is logged in
 * before any checks or redirects can happen.
 */
function imv_api_handle_autologin() {
    if ( is_user_logged_in() || ! isset( $_GET['imv_autologin'], $_GET['uid'] ) ) {
        return;
    }

    $user_id = absint( $_GET['uid'] );
    $token_from_url = sanitize_key( $_GET['imv_autologin'] );

    if ( ! $user_id || empty( $token_from_url ) ) {
        return;
    }

    // Manually include logger if the main class hasn't loaded yet.
    if ( ! class_exists('IMV_API_Logger') ) {
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-logger.php';
    }

    $stored_token = get_user_meta( $user_id, '_imv_autologin_token', true );
    $expires = get_user_meta( $user_id, '_imv_autologin_token_expires', true );

    if ( ! empty( $stored_token ) && hash_equals( $stored_token, $token_from_url ) && time() < $expires ) {
        // Invalidate the token first
        delete_user_meta( $user_id, '_imv_autologin_token' );
        delete_user_meta( $user_id, '_imv_autologin_token_expires' );

        // Log the user in
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );

        IMV_API_Logger::log("Auto-login successful for user #{$user_id}. Redirecting to clean URL.");

        // Redirect to a clean URL to remove the token from the address bar
        $redirect_url = remove_query_arg( array( 'imv_autologin', 'uid' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
add_action('wp_loaded', 'imv_api_handle_autologin');

/**
 * This function disables the login requirement on the order-pay page,
 * which respects the "guest checkout" setting even if the order is assigned to a user.
 */
function imv_api_allow_guest_payment_for_order( $is_required ) {
    if ( function_exists('is_checkout_pay_page') && is_checkout_pay_page() && isset( $_GET['key'] ) ) {
        return false; // Do not require login on the order-pay page if a key is present
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
    private $login_manager;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();

        $this->loader        = new IMV_API_Loader();
        $this->core          = new IMV_API_Core();
        $this->wallet        = new IMV_API_Wallet();
        $this->admin         = new IMV_API_Admin();
        $this->login_manager = new IMV_API_Login_Form_Manager();

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
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-otp-manager.php';
     //   require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-login-form-manager.php';
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

        // OTP Login Form Manager Hooks
      //  $this->loader->add_action( 'wp_enqueue_scripts', $this->login_manager, 'enqueue_scripts' );
       // $this->loader->add_action( 'woocommerce_login_form_start', $this->login_manager, 'render_otp_login_form' );
        //$this->loader->add_action( 'wp_ajax_nopriv_imv_request_otp', $this->login_manager, 'ajax_request_otp' );
        //$this->loader->add_action( 'wp_ajax_nopriv_imv_verify_otp_and_login', $this->login_manager, 'ajax_verify_otp_and_login' );
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
