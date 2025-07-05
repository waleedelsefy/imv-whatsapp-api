<?php
/**
 * Plugin Name:       IMV WhatsApp API
 * Plugin URI:        https://imvagency.net/
 * Description:       A custom WordPress plugin to integrate WooCommerce with WhatsApp, providing custom API endpoints, order status notifications, and an advanced customer wallet system.
 * Version:           4.1
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
 * The main plugin class.
 *
 * This class handles the loading of all dependencies, and the registration
 * of all hooks, actions, and filters. It is implemented as a singleton to
 * prevent multiple instances and ensure a stable loading sequence.
 */
final class IMV_WhatsApp_API_Main {

    /**
     * The single instance of the class.
     * @var IMV_WhatsApp_API_Main|null
     */
    private static $instance = null;

    /**
     * Main Instance.
     *
     * Ensures only one instance of the main class is loaded or can be loaded.
     * @return IMV_WhatsApp_API_Main - Main instance.
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     * This is private to prevent direct creation of the object.
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Initialize the plugin.
     *
     * Loads all necessary files and registers hooks. This method is called
     * on the 'plugins_loaded' hook to ensure all dependencies are available.
     */
    public function init() {
        // Check for WooCommerce dependency.
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'admin_notice_missing_woocommerce' ) );
            return;
        }

        // Load all plugin classes.
        $this->load_dependencies();

        // Instantiate the main classes.
        $loader = new IMV_API_Loader();
        $core = new IMV_API_Core();
        $wallet = new IMV_API_Wallet();
        $admin = new IMV_API_Admin();

        // Register all hooks with the loader.
        $this->define_hooks( $loader, $core, $wallet, $admin );

        // Execute all registered hooks.
        $loader->run();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-loader.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-core.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-handlers.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-wallet.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-admin.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-logger.php';
        require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-helpers.php';
    }

    /**
     * Register all of the hooks related to the plugin functionality.
     *
     * @param IMV_API_Loader $loader   The class that registers actions and filters.
     * @param IMV_API_Core   $core     The class that handles core functionalities.
     * @param IMV_API_Wallet $wallet   The class that handles wallet functionalities.
     * @param IMV_API_Admin  $admin    The class that handles admin settings.
     */
    private function define_hooks( $loader, $core, $wallet, $admin ) {
        // Core functionalities
        $loader->add_action( 'init', $core, 'load_textdomain' );
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
    }

    /**
     * Admin notice for missing WooCommerce.
     */
    public function admin_notice_missing_woocommerce() {
        echo '<div class="error"><p><strong>' . esc_html__( 'IMV WhatsApp API:', 'imv-api' ) . '</strong> ' . esc_html__( 'WooCommerce is not active. This plugin requires WooCommerce to function.', 'imv-api' ) . '</p></div>';
    }
}

/**
 * Activation hook.
 */
function imv_api_activate() {
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-activator.php';
    IMV_API_Activator::activate();
}
register_activation_hook( __FILE__, 'imv_api_activate' );

/**
 * Deactivation hook.
 */
function imv_api_deactivate() {
    require_once IMV_API_PLUGIN_DIR . 'includes/class-imv-api-deactivator.php';
    IMV_API_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'imv_api_deactivate' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
IMV_WhatsApp_API_Main::get_instance();
