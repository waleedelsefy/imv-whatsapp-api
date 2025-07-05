<?php
/**
 * Core functionalities of the plugin.
 * This includes REST API endpoint registration, custom order status, and WhatsApp notifications.
 */
namespace Imv\WhatsAppApi;

class Core {

    public function __construct() {
        // No explicit require_once calls for WooCommerce functions here.
        // WooCommerce core functions are generally available by the time
        // plugin hooks like 'rest_api_init' or 'plugins_loaded' fire,
        // assuming WooCommerce is active.
    }

    /**
     * Load plugin textdomain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'imv-api', false, dirname( plugin_basename( IMV_API_PLUGIN_DIR . 'imv-whatsapp-api.php' ) ) . '/languages' );
    }

    /**
     * Action hook to register all of our custom REST API endpoints.
     */
    public function register_api_endpoints() {
        // Endpoint for checking if a customer exists.
        register_rest_route( 'imv-api/v1', '/check-customer', array(
            'methods'             => 'POST',
            'callback'            => array( Handlers::class, 'handle_customer_check_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );

        // Endpoint for creating a new customer.
        register_rest_route( 'imv-api/v1', '/create-customer', array(
            'methods'             => 'POST',
            'callback'            => array( Handlers::class, 'handle_customer_create_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );

        // Endpoint for creating a new order.
        register_rest_route( 'imv-api/v1', '/create-order', array(
            'methods'             => 'POST',
            'callback'            => array( Handlers::class, 'handle_order_create_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );

        // Endpoint for searching products.
        register_rest_route( 'imv-api/v1', '/search-products', array(
            'methods'             => 'POST',
            'callback'            => array( Handlers::class, 'handle_product_search_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );

        // Endpoint for checking customer wallet balance.
        register_rest_route( 'imv-api/v1', '/check-wallet', array(
            'methods'             => 'POST',
            'callback'            => array( Wallet::class, 'handle_check_wallet_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );

        // Endpoint for updating customer wallet balance.
        register_rest_route( 'imv-api/v1', '/update-wallet', array(
            'methods'             => 'POST',
            'callback'            => array( Wallet::class, 'handle_update_wallet_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );

        // Endpoint for holding funds (moving from available to pending).
        register_rest_route( 'imv-api/v1', '/hold-balance', array(
            'methods'             => 'POST',
            'callback'            => array( Wallet::class, 'handle_hold_balance_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );

        // Endpoint for releasing held funds (moving from pending back to available).
        register_rest_route( 'imv-api/v1', '/release-balance', array(
            'methods'             => 'POST',
            'callback'            => array( Wallet::class, 'handle_release_balance_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );

        // Endpoint for deducting from pending balance.
        register_rest_route( 'imv-api/v1', '/deduct-pending', array(
            'methods'             => 'POST',
            'callback'            => array( Wallet::class, 'handle_deduct_pending_balance_request' ),
            'permission_callback' => '__return_true' // WARNING: For production, implement proper authentication.
        ) );
    }

    /**
     * Register a new "Pending Assessment" order status for laundry pickups.
     */
    public function register_pending_assessment_order_status() {
        register_post_status( 'wc-pending-assessment', array(
            'label'                     => __( 'Pending Assessment', 'imv-api' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Pending Assessment <span class="count">(%s)</span>', 'Pending Assessment <span class="count">(%s)</span>', 'imv-api' )
        ) );
    }

    /**
     * Add the new status to the list of WooCommerce order statuses in the admin panel.
     *
     * @param array $order_statuses
     * @return array
     */
    public function add_pending_assessment_to_order_statuses( $order_statuses ) {
        $new_order_statuses = array();
        foreach ( $order_statuses as $key => $status ) {
            $new_order_statuses[ $key ] = $status;
            if ( 'wc-on-hold' === $key ) {
                $new_order_statuses['wc-pending-assessment'] = __( 'Pending Assessment', 'imv-api' );
            }
        }
        return $new_order_statuses;
    }

    /**
     * Constructs and sends a direct WhatsApp message via the user's chatbot API.
     *
     * @param int      $order_id   The order ID.
     * @param string   $old_status The old order status.
     * @param string   $new_status The new order status.
     * @param WC_Order $order      The order object.
     */
    public function send_direct_whatsapp_notification( $order_id, $old_status, $new_status, $order ) {
        Logger::log("Order #{$order_id}: Status changed from '{$old_status}' to '{$new_status}'. Preparing WhatsApp notification.");

        $api_url = get_option('imv_api_notification_url');
        $api_token = get_option('imv_api_token');

        if ( empty($api_url) || empty($api_token) ) {
            Logger::log("API URL or Token is not set. Skipping WhatsApp notification.");
            return;
        }

        $customer_name = $order->get_billing_first_name();
        $message_body = "";

        switch ($new_status) {
            case 'processing':
                $message_body = sprintf(
                    __( 'Hello %s, your order #%d is now being processed and we will notify you when it is shipped.', 'imv-api' ),
                    $customer_name,
                    $order_id
                );
                break;
            case 'on-hold':
                $message_body = sprintf(
                    __( 'Hello %s, your order #%d is currently on hold.', 'imv-api' ),
                    $customer_name,
                    $order_id
                );
                break;
            case 'pending-assessment': // Notification for our custom status
                $message_body = sprintf(
                    __( 'Hello %s, your pickup request #%d has been confirmed. Our representative is on the way.', 'imv-api' ),
                    $customer_name,
                    $order_id
                );
                break;
            case 'completed':
                $message_body = sprintf(
                    __( 'Your order #%d has been successfully delivered! Thank you for trusting us, %s.', 'imv-api' ),
                    $order_id,
                    $customer_name
                );
                break;
        }

        if ( empty($message_body) ) {
            Logger::log("No message template for status '{$new_status}'. Skipping.");
            return;
        }

        $raw_phone = $order->get_billing_phone();
        $formatted_phone = Helpers::format_phone_number_for_meta($raw_phone);

        if (empty($formatted_phone)) {
            Logger::log("Could not format a valid phone number from '{$raw_phone}' for Order #{$order_id}. Skipping.");
            return;
        }

        $messageObject = array(
            "to" => $formatted_phone,
            "type" => "text",
            "text" => array( "preview_url" => false, "body" => $message_body )
        );

        $request_body = array( "messageObject" => $messageObject );
        $full_api_url = rtrim($api_url, '/') . '/api/v1/send-message?token=' . $api_token;

        $response = wp_remote_post($full_api_url, array(
            'method'      => 'POST',
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => json_encode($request_body),
            'data_format' => 'body',
            'timeout'     => 20,
        ));

        if ( is_wp_error($response) ) {
            Logger::log("API call failed for Order #{$order_id}. Error: " . $response->get_error_message());
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            Logger::log("API call for Order #{$order_id} sent. Response code: {$response_code}. Body: " . $response_body);
        }
    }
}
