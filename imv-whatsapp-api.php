<?php
/**
 * Plugin Name:       IMV WhatsApp API
 * Plugin URI:        https://imvagency.net/
 * Description:       A custom WordPress plugin to integrate WooCommerce with WhatsApp, providing custom API endpoints, order status notifications, and an advanced customer wallet system.
 * Version:           4.0
 * Author:            waleed elsefy
 * Author URI:        https://imvagency.net/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       imv-api
 * Domain Path:       /languages
 */

// Security Check: Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
if ( ! defined( 'IMV_API_PLUGIN_DIR' ) ) {
    define( 'IMV_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Activation hook.
 * Runs only when the plugin is activated.
 */
function imv_api_activate() {
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-activator.php';
    IMV_API_Activator::activate();
}
register_activation_hook( __FILE__, 'imv_api_activate' );

/**
 * Deactivation hook.
 * Runs only when the plugin is deactivated.
 */
function imv_api_deactivate() {
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-deactivator.php';
    IMV_API_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'imv_api_deactivate' );

/**
 * The core function that runs the plugin.
 *
 * This function includes all necessary files and initializes the plugin's functionalities.
 * It's hooked into 'plugins_loaded' to ensure all dependencies (like WooCommerce) are available.
 */
function imv_api_run() {

    // Check if WooCommerce is active. If not, show an admin notice and stop loading the plugin.
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p><strong>' . esc_html__( 'IMV WhatsApp API:', 'imv-api' ) . '</strong> ' . esc_html__( 'WooCommerce is not active. This plugin requires WooCommerce to function.', 'imv-api' ) . '</p></div>';
        });
        return;
    }

    /**
     * Load all plugin classes now that it's safe to do so.
     */
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-loader.php';
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-core.php';
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-handlers.php';
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-wallet.php';
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-admin.php';
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-logger.php';
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-helpers.php';

    // Instantiate the main classes.
    $loader = new IMV_API_Loader();
    $core = new IMV_API_Core();
    $wallet = new IMV_API_Wallet();
    $admin = new IMV_API_Admin();

    // Register all hooks with the loader.

    // Core functionalities
    $loader->add_action( 'init', $core, 'load_textdomain' ); // Load textdomain on init
    $loader->add_action( 'rest_api_init', $core, 'register_api_endpoints' );
    $loader->add_action( 'init', $core, 'register_pending_assessment_order_status' );
    $loader->add_filter( 'wc_order_statuses', $core, 'add_pending_assessment_to_order_statuses' );
    $loader->add_action( 'woocommerce_order_status_changed', $core, 'send_direct_whatsapp_notification', 10, 4 );

    // Wallet functionalities
    $loader->add_action( 'user_register', $wallet, 'initialize_customer_wallet' );
    $loader->add_action( 'woocommerce_created_customer', $wallet, 'initialize_customer_wallet_on_api_create', 10, 1 );
    $loader->add_action( 'show_user_profile', $wallet, 'add_wallet_fields_to_user_profile' );
    $loader->add_action( 'edit_user_profile', $wallet, 'add_wallet_fields_to_user_profile' );
    $loader->add_action( 'personal_options_update', $wallet, 'save_wallet_fields_from_user_profile' );
    $loader->add_action( 'edit_user_profile_update', $wallet, 'save_wallet_fields_from_user_profile' );
    $loader->add_action( 'woocommerce_order_status_changed', $wallet, 'deduct_from_pending_on_order_completion', 15, 4 );

    // Admin settings functionalities
    $loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
    $loader->add_action( 'admin_init', $admin, 'settings_init' );

    // Execute all registered hooks.
    $loader->run();
}

/**
 * Hook the main plugin function to 'plugins_loaded'.
 * This ensures that all plugins are loaded before our plugin attempts to run.
 */
add_action( 'plugins_loaded', 'imv_api_run' );
