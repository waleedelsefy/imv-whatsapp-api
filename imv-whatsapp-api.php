<?php
/**
 * Plugin Name:       IMV WhatsApp API
 * Plugin URI:        https://imvagency.net/
 * Description:       A custom WordPress plugin to integrate WooCommerce with WhatsApp, providing custom API endpoints, order status notifications, and an advanced customer wallet system with OTP login.
 * Version:           5.5
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

final class IMV_WhatsApp_API_Main {
    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }

    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'admin_notice_missing_woocommerce' ) );
            return;
        }
        $this->load_dependencies();
        $loader = new IMV_API_Loader();
        $core = new IMV_API_Core();
        $wallet = new IMV_API_Wallet();
        $admin = new IMV_API_Admin();
        $login_manager = new IMV_API_Login_Form_Manager();
        $this->define_hooks( $loader, $core, $wallet, $admin, $login_manager );
        $loader->run();
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
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-login-form-manager.php';
    }

    private function define_hooks( $loader, $core, $wallet, $admin, $login_manager ) {
        // Core
        $loader->add_action( 'init', $core, 'load_textdomain' );
        $loader->add_action( 'rest_api_init', $core, 'register_api_endpoints' );
        $loader->add_action( 'init', $core, 'register_pending_assessment_order_status' );
        $loader->add_filter( 'wc_order_statuses', $core, 'add_pending_assessment_to_order_statuses' );
        $loader->add_action( 'woocommerce_order_status_changed', $core, 'send_direct_whatsapp_notification', 10, 4 );

        // Wallet
        $loader->add_action( 'user_register', $wallet, 'initialize_customer_wallet' );
        $loader->add_action( 'woocommerce_created_customer', $wallet, 'initialize_customer_wallet_on_api_create', 10, 1 );
        $loader->add_action( 'show_user_profile', $wallet, 'add_wallet_fields_to_user_profile' );
        $loader->add_action( 'edit_user_profile', $wallet, 'add_wallet_fields_to_user_profile' );
        $loader->add_action( 'personal_options_update', $wallet, 'save_wallet_fields_from_user_profile' );
        $loader->add_action( 'edit_user_profile_update', $wallet, 'save_wallet_fields_from_user_profile' );
        $loader->add_action( 'woocommerce_order_status_changed', $wallet, 'deduct_from_pending_on_order_completion', 15, 4 );
        $loader->add_action( 'woocommerce_order_status_completed', $wallet, 'add_funds_on_topup_order_completion', 10, 1 );
        $loader->add_action( 'woocommerce_subscription_payment_complete', $wallet, 'add_funds_from_subscription', 10, 1 );

        // Admin
        $loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
        $loader->add_action( 'admin_init', $admin, 'settings_init' );

        // OTP & Auto-Login Manager Hooks
        $loader->add_action( 'wp_enqueue_scripts', $login_manager, 'enqueue_scripts' );
        $loader->add_action( 'woocommerce_login_form_start', $login_manager, 'render_otp_login_form' );
        $loader->add_action( 'wp_ajax_nopriv_imv_request_otp', $login_manager, 'ajax_request_otp' );
        $loader->add_action( 'wp_ajax_nopriv_imv_verify_otp_and_login', $login_manager, 'ajax_verify_otp_and_login' );

        // ** UPDATED HOOK **: Use 'parse_request' to ensure auto-login runs before any page content is processed or redirects happen.
        $loader->add_action( 'parse_request', $login_manager, 'handle_autologin_token_verification' );
    }

    public function admin_notice_missing_woocommerce() {
        echo '<div class="error"><p><strong>' . esc_html__( 'IMV WhatsApp API:', 'imv-api' ) . '</strong> ' . esc_html__( 'WooCommerce is not active.', 'imv-api' ) . '</p></div>';
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
