<?php
/**
 * Core functionalities of the plugin.
 */
class IMV_API_Core {

    public function __construct() { }

    public function load_textdomain() {
        load_plugin_textdomain( 'imv-api', false, dirname( plugin_basename( IMV_API_PLUGIN_DIR . 'imv-whatsapp-api.php' ) ) . '/languages' );
    }

    public function register_api_endpoints() {
        // --- Customer Endpoints ---
        register_rest_route( 'imv-api/v1', '/check-customer', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Handlers', 'handle_customer_check_request' ), 'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'imv-api/v1', '/create-customer', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Handlers', 'handle_customer_create_request' ), 'permission_callback' => '__return_true'
        ) );

        // --- Product & Order Endpoints ---
        register_rest_route( 'imv-api/v1', '/search-products', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Handlers', 'handle_product_search_request' ), 'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'imv-api/v1', '/create-order', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Handlers', 'handle_order_create_request' ), 'permission_callback' => '__return_true'
        ) );

        // --- Wallet Endpoints ---
        register_rest_route( 'imv-api/v1', '/check-wallet', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Wallet', 'handle_check_wallet_request' ), 'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'imv-api/v1', '/update-wallet', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Wallet', 'handle_update_wallet_request' ), 'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'imv-api/v1', '/topup-wallet', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Handlers', 'handle_wallet_topup_request' ), 'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'imv-api/v1', '/hold-balance', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Wallet', 'handle_hold_balance_request' ), 'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'imv-api/v1', '/release-balance', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Wallet', 'handle_release_balance_request' ), 'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'imv-api/v1', '/deduct-pending', array(
            'methods' => 'POST', 'callback' => array( 'IMV_API_Wallet', 'handle_deduct_pending_balance_request' ), 'permission_callback' => '__return_true'
        ) );
    }

    public function register_pending_assessment_order_status() {
        register_post_status( 'wc-pending-assessment', array(
            'label' => __( 'Pending Assessment', 'imv-api' ), 'public' => true, 'exclude_from_search' => false,
            'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
            'label_count' => _n_noop( 'Pending Assessment <span class="count">(%s)</span>', 'Pending Assessment <span class="count">(%s)</span>', 'imv-api' )
        ) );
    }

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

    public function send_direct_whatsapp_notification( $order_id, $old_status, $new_status, $order ) {
        IMV_API_Logger::log("Order #{$order_id}: Status changed from '{$old_status}' to '{$new_status}'. Preparing WhatsApp notification.");
        $customer_name = $order->get_billing_first_name();
        $message_body = "";
        switch ($new_status) {
            case 'processing':
                $message_body = sprintf( __( 'Hello %s, your order #%d is now being processed.', 'imv-api' ), $customer_name, $order_id );
                break;
            case 'on-hold':
                $message_body = sprintf( __( 'Hello %s, your order #%d is on hold.', 'imv-api' ), $customer_name, $order_id );
                break;
            case 'pending-assessment':
                $message_body = sprintf( __( 'Hello %s, your pickup request #%d has been confirmed.', 'imv-api' ), $customer_name, $order_id );
                break;
            case 'completed':
                $message_body = sprintf( __( 'Your order #%d has been delivered! Thank you, %s.', 'imv-api' ), $order_id, $customer_name );
                break;
        }
        if ( ! empty($message_body) ) {
            IMV_API_Helpers::send_whatsapp_message( $order->get_billing_phone(), $message_body );
        }
    }
}
