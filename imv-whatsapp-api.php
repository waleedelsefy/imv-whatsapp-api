<?php
/**
 * Plugin Name:       IMV WhatsApp API
 * Plugin URI:        https://imvagency.net/
 * Description:       A custom WordPress plugin to integrate WooCommerce with WhatsApp, providing custom API endpoints, order status notifications, and an advanced customer wallet system.
 * Version:           3.8
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
if ( ! defined( 'IMV_API_PLUGIN_URL' ) ) {
    define( 'IMV_API_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Manually include all necessary class files.
 * This approach is more robust for standard WordPress plugins as it doesn't
 * rely on Composer's autoloader, which might not be present in all environments.
 * This resolves the "Class not found" errors.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-activator.php';
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-deactivator.php';
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-loader.php';
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-core.php';
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-handlers.php';
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-wallet.php';
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-admin.php';
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-logger.php';
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-helpers.php';


/**
 * Register activation and deactivation hooks.
 * These methods are called when the plugin is activated or deactivated.
 * We use the original class names as strings since we are not using namespaces.
 */
register_activation_hook( __FILE__, array( 'IMV_API_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IMV_API_Deactivator', 'deactivate' ) );

/**
 * Begins execution of the plugin.
 *
 * This function instantiates the main loader and registers all plugin hooks
 * (actions and filters) with WordPress.
 */
function run_imv_whatsapp_api() {
    // Instantiate core classes.
    $core = new IMV_API_Core();
    $wallet = new IMV_API_Wallet();
    $admin = new IMV_API_Admin();
    $loader = new IMV_API_Loader();

    // Load the textdomain directly at the beginning.
    $core->load_textdomain();

    // Register core plugin functionalities.
    $loader->add_action( 'rest_api_init', $core, 'register_api_endpoints' );
    $loader->add_action( 'init', $core, 'register_pending_assessment_order_status' );
    $loader->add_filter( 'wc_order_statuses', $core, 'add_pending_assessment_to_order_statuses' );
    $loader->add_action( 'woocommerce_order_status_changed', $core, 'send_direct_whatsapp_notification', 10, 4 );

    // Register wallet functionalities.
    $loader->add_action( 'user_register', $wallet, 'initialize_customer_wallet' );
    $loader->add_action( 'woocommerce_created_customer', $wallet, 'initialize_customer_wallet_on_api_create', 10, 1 );
    $loader->add_action( 'show_user_profile', $wallet, 'add_wallet_fields_to_user_profile' );
    $loader->add_action( 'edit_user_profile', $wallet, 'add_wallet_fields_to_user_profile' );
    $loader->add_action( 'personal_options_update', $wallet, 'save_wallet_fields_from_user_profile' );
    $loader->add_action( 'edit_user_profile_update', $wallet, 'save_wallet_fields_from_user_profile' );
    $loader->add_action( 'woocommerce_order_status_changed', $wallet, 'deduct_from_pending_on_order_completion', 15, 4 );

    // Register admin settings functionalities.
    $loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
    $loader->add_action( 'admin_init', $admin, 'settings_init' );

    // Run the loader to execute all registered hooks.
    $loader->run();
}

/**
 * The main execution hook.
 *
 * By hooking into 'init', we ensure that WordPress, all plugins (including WooCommerce),
 * and the theme are fully loaded before our plugin's main logic runs. This is the
 * recommended, stable approach and prevents class loading and timing issues.
 */
add_action( 'init', 'run_imv_whatsapp_api' );
