<?php
/**
 * Plugin Name:       IMV WhatsApp API
 * Plugin URI:        https://imvagency.net/
 * Description:       A custom plugin to integrate WooCommerce with WhatsApp by providing custom API endpoints and sending direct order status notifications via your chatbot system's API, now with a customer wallet and custom roles.
 * Version:           3.4
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
 * The code that runs during plugin activation.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-activator.php';

/**
 * The code that runs during plugin deactivation.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-deactivator.php';

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing hooks.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-loader.php';

/**
 * All core plugin functionalities like API routes, custom order statuses.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-core.php';

/**
 * All API handler functions.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-handlers.php';

/**
 * All wallet related functionalities.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-wallet.php';

/**
 * All admin settings page functionalities.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-admin.php';

/**
 * The logging system.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-logger.php';

/**
 * Helper functions.
 */
require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-helpers.php';


/**
 * Register activation and deactivation hooks.
 */
register_activation_hook( __FILE__, array( 'IMV_API_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IMV_API_Deactivator', 'deactivate' ) );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file means
 * that all of the plugin's functionality is registered and ready for use.
 */
function run_imv_whatsapp_api() {
    $loader = new IMV_API_Loader();

    // Core functionalities
    $core = new IMV_API_Core();
    $loader->add_action( 'plugins_loaded', $core, 'load_textdomain' );
    $loader->add_action( 'rest_api_init', $core, 'register_api_endpoints' );
    $loader->add_action( 'init', $core, 'register_pending_assessment_order_status' );
    $loader->add_filter( 'wc_order_statuses', $core, 'add_pending_assessment_to_order_statuses' );
    $loader->add_action( 'woocommerce_order_status_changed', $core, 'send_direct_whatsapp_notification', 10, 4 );

    // API Handlers
    $handlers = new IMV_API_Handlers();
    // Handlers are called directly from the core's register_api_endpoints method,
    // so no need to add them to the loader here.

    // Wallet functionalities
    $wallet = new IMV_API_Wallet();
    $loader->add_action( 'user_register', $wallet, 'initialize_customer_wallet' );
    $loader->add_action( 'woocommerce_created_customer', $wallet, 'initialize_customer_wallet_on_api_create', 10, 1 );
    $loader->add_action( 'show_user_profile', $wallet, 'add_wallet_fields_to_user_profile' );
    $loader->add_action( 'edit_user_profile', $wallet, 'add_wallet_fields_to_user_profile' );
    $loader->add_action( 'personal_options_update', $wallet, 'save_wallet_fields_from_user_profile' );
    $loader->add_action( 'edit_user_profile_update', $wallet, 'save_wallet_fields_from_user_profile' );
    $loader->add_action( 'woocommerce_order_status_changed', $wallet, 'deduct_from_pending_on_order_completion', 15, 4 ); // Run after default notifications

    // Admin settings
    $admin = new IMV_API_Admin();
    $loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
    $loader->add_action( 'admin_init', $admin, 'settings_init' );

    $loader->run();
}
run_imv_whatsapp_api();