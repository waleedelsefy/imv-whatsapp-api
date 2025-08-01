<?php
/**
 * Handles all wallet-related functionalities.
 * This version is updated to support both simple product top-ups and WooCommerce Subscriptions.
 */
class IMV_API_Wallet {

    /**
     * Initialize default wallet balances for new customers.
     * This ensures every customer has a wallet balance set to 0.
     *
     * @param int $user_id The ID of the newly registered user.
     */
    public function initialize_customer_wallet( $user_id ) {
        if ( ! metadata_exists( 'user', $user_id, 'imv_wallet_balance' ) ) {
            update_user_meta( $user_id, 'imv_wallet_balance', 0 );
        }
        if ( ! metadata_exists( 'user', $user_id, 'imv_pending_balance' ) ) {
            update_user_meta( $user_id, 'imv_pending_balance', 0 );
        }
        IMV_API_Logger::log("Initialized wallet for new user #{$user_id}.");
    }

    /**
     * When a new customer is created via API, ensure their wallet is initialized.
     *
     * @param int $customer_id The ID of the newly created WooCommerce customer.
     */
    public function initialize_customer_wallet_on_api_create( $customer_id ) {
        $this->initialize_customer_wallet( $customer_id );
    }

    /**
     * Add custom fields to the user profile page for wallet balance.
     *
     * @param WP_User $user The user object.
     */
    public function add_wallet_fields_to_user_profile( $user ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        $wallet_balance = get_user_meta( $user->ID, 'imv_wallet_balance', true );
        $pending_balance = get_user_meta( $user->ID, 'imv_pending_balance', true );
        $wallet_balance = is_numeric( $wallet_balance ) ? floatval($wallet_balance) : 0;
        $pending_balance = is_numeric( $pending_balance ) ? floatval($pending_balance) : 0;
        ?>
        <h2><?php _e( 'IMV Wallet Balance', 'imv-api' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="imv_wallet_balance"><?php _e( 'Available Balance', 'imv-api' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" name="imv_wallet_balance" id="imv_wallet_balance" value="<?php echo esc_attr( $wallet_balance ); ?>" class="regular-text" /><br />
                    <span class="description"><?php _e( 'This is the customer\'s available balance in the wallet.', 'imv-api' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="imv_pending_balance"><?php _e( 'Pending Assessment Balance', 'imv-api' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" name="imv_pending_balance" id="imv_pending_balance" value="<?php echo esc_attr( $pending_balance ); ?>" class="regular-text" /><br />
                    <span class="description"><?php _e( 'This balance is held for pending assessments (e.g., laundry pickups).', 'imv-api' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="imv_add_to_wallet"><?php _e( 'Add/Deduct Available Balance', 'imv-api' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" name="imv_add_to_wallet" id="imv_add_to_wallet" value="" class="regular-text" placeholder="<?php esc_attr_e('e.g. 50 or -25', 'imv-api'); ?>" /><br />
                    <span class="description"><?php _e( 'Enter an amount to add (positive) or deduct (negative) from the available balance. This will update the Available Balance above.', 'imv-api' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="imv_add_to_pending"><?php _e( 'Add/Deduct Pending Balance', 'imv-api' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" name="imv_add_to_pending" id="imv_add_to_pending" value="" class="regular-text" placeholder="<?php esc_attr_e('e.g. 50 or -25', 'imv-api'); ?>" /><br />
                    <span class="description"><?php _e( 'Enter an amount to add (positive) or deduct (negative) from the pending balance. This will update the Pending Assessment Balance above.', 'imv-api' ); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save custom fields from the user profile page.
     *
     * @param int $user_id The user ID.
     */
    public function save_wallet_fields_from_user_profile( $user_id ) {
        if ( ! current_user_can( 'manage_woocommerce', $user_id ) ) {
            return;
        }
        if ( isset( $_POST['imv_add_to_wallet'] ) && is_numeric( $_POST['imv_add_to_wallet'] ) ) {
            $amount_to_change = floatval( $_POST['imv_add_to_wallet'] );
            $current_balance = get_user_meta( $user_id, 'imv_wallet_balance', true );
            $new_balance = floatval( $current_balance ) + $amount_to_change;
            update_user_meta( $user_id, 'imv_wallet_balance', $new_balance );
            IMV_API_Logger::log( "Admin updated user #{$user_id} wallet: changed by {$amount_to_change}, new available balance: {$new_balance}" );
        } elseif ( isset( $_POST['imv_wallet_balance'] ) && is_numeric( $_POST['imv_wallet_balance'] ) ) {
            update_user_meta( $user_id, 'imv_wallet_balance', floatval( $_POST['imv_wallet_balance'] ) );
        }
        if ( isset( $_POST['imv_add_to_pending'] ) && is_numeric( $_POST['imv_add_to_pending'] ) ) {
            $amount_to_change_pending = floatval( $_POST['imv_add_to_pending'] );
            $current_pending_balance = get_user_meta( $user_id, 'imv_pending_balance', true );
            $new_pending_balance = floatval( $current_pending_balance ) + $amount_to_change_pending;
            update_user_meta( $user_id, 'imv_pending_balance', $new_pending_balance );
            IMV_API_Logger::log( "Admin updated user #{$user_id} pending balance: changed by {$amount_to_change_pending}, new pending balance: {$new_pending_balance}" );
        } elseif ( isset( $_POST['imv_pending_balance'] ) && is_numeric( $_POST['imv_pending_balance'] ) ) {
            update_user_meta( $user_id, 'imv_pending_balance', floatval( $_POST['imv_pending_balance'] ) );
        }
    }

    /**
     * Handles the API request to check customer wallet balance.
     */
    public static function handle_check_wallet_request( $request ) {
        IMV_API_Logger::log( '====== New /check-wallet Request ======' );
        $phone_number = sanitize_text_field( $request->get_param('phone') );
        if ( empty( $phone_number ) ) { return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number is required.' ), 400 ); }
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone_number, 'number' => 1, 'count_total' => false ) );
        if ( empty( $users ) ) { return new WP_REST_Response( array( 'status' => 'not_found', 'message' => 'Customer not found.' ), 404 ); }
        $customer_id = $users[0]->ID;
        $available_balance = get_user_meta( $customer_id, 'imv_wallet_balance', true );
        $pending_balance = get_user_meta( $customer_id, 'imv_pending_balance', true );
        $available_balance = is_numeric($available_balance) ? floatval($available_balance) : 0;
        $pending_balance = is_numeric($pending_balance) ? floatval($pending_balance) : 0;
        IMV_API_Logger::log( "Wallet check for user #{$customer_id}. Available: {$available_balance}, Pending: {$pending_balance}" );
        return new WP_REST_Response( array( 'status' => 'success', 'data' => array( 'customer_id' => $customer_id, 'available_balance' => $available_balance, 'pending_balance' => $pending_balance, 'currency' => get_woocommerce_currency_symbol() ) ), 200 );
    }

    /**
     * Handles the API request to update a customer's wallet balance.
     */
    public static function handle_update_wallet_request( $request ) {
        IMV_API_Logger::log( '====== New /update-wallet Request ======' );
        $phone_number = sanitize_text_field( $request->get_param('phone') );
        $amount = floatval( $request->get_param('amount') );
        if ( empty( $phone_number ) || ! is_numeric( $amount ) ) { return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number and a valid amount are required.' ), 400 ); }
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone_number, 'number' => 1, 'count_total' => false ) );
        if ( empty( $users ) ) { return new WP_REST_Response( array( 'status' => 'not_found', 'message' => 'Customer not found.' ), 404 ); }
        $customer_id = $users[0]->ID;
        $current_balance = get_user_meta( $customer_id, 'imv_wallet_balance', true );
        $current_balance = is_numeric($current_balance) ? floatval($current_balance) : 0;
        $new_balance = $current_balance + $amount;
        update_user_meta( $customer_id, 'imv_wallet_balance', $new_balance );
        IMV_API_Logger::log( "User #{$customer_id} wallet updated: changed by {$amount}, new available balance: {$new_balance}" );
        return new WP_REST_Response( array( 'status' => 'success', 'data' => array( 'customer_id' => $customer_id, 'old_balance' => $current_balance, 'new_balance' => $new_balance, 'currency' => get_woocommerce_currency_symbol(), 'message' => 'Wallet balance updated successfully.' ) ), 200 );
    }

    /**
     * Handles the API request to hold funds.
     */
    public static function handle_hold_balance_request( $request ) {
        IMV_API_Logger::log( '====== New /hold-balance Request ======' );
        $phone_number = sanitize_text_field( $request->get_param('phone') );
        $amount = floatval( $request->get_param('amount') );
        if ( empty( $phone_number ) || ! is_numeric( $amount ) || $amount <= 0 ) { return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number and a positive amount are required.' ), 400 ); }
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone_number, 'number' => 1, 'count_total' => false ) );
        if ( empty( $users ) ) { return new WP_REST_Response( array( 'status' => 'not_found', 'message' => 'Customer not found.' ), 404 ); }
        $customer_id = $users[0]->ID;
        $current_available = get_user_meta( $customer_id, 'imv_wallet_balance', true );
        $current_pending = get_user_meta( $customer_id, 'imv_pending_balance', true );
        $current_available = is_numeric($current_available) ? floatval($current_available) : 0;
        $current_pending = is_numeric($current_pending) ? floatval($current_pending) : 0;
        if ( $current_available < $amount ) { return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Insufficient available balance to hold funds.' ), 400 ); }
        $new_available = $current_available - $amount;
        $new_pending = $current_pending + $amount;
        update_user_meta( $customer_id, 'imv_wallet_balance', $new_available );
        update_user_meta( $customer_id, 'imv_pending_balance', $new_pending );
        IMV_API_Logger::log( "User #{$customer_id} held funds: {$amount}. New available: {$new_available}, New pending: {$new_pending}" );
        return new WP_REST_Response( array( 'status' => 'success', 'data' => array( 'customer_id' => $customer_id, 'amount_held' => $amount, 'new_available_balance' => $new_available, 'new_pending_balance' => $new_pending, 'currency' => get_woocommerce_currency_symbol(), 'message' => 'Funds successfully held for assessment.' ) ), 200 );
    }

    /**
     * Handles the API request to release held funds.
     */
    public static function handle_release_balance_request( $request ) {
        IMV_API_Logger::log( '====== New /release-balance Request ======' );
        $phone_number = sanitize_text_field( $request->get_param('phone') );
        $amount = floatval( $request->get_param('amount') );
        if ( empty( $phone_number ) || ! is_numeric( $amount ) || $amount <= 0 ) { return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number and a positive amount are required.' ), 400 ); }
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone_number, 'number' => 1, 'count_total' => false ) );
        if ( empty( $users ) ) { return new WP_REST_Response( array( 'status' => 'not_found', 'message' => 'Customer not found.' ), 404 ); }
        $customer_id = $users[0]->ID;
        $current_available = get_user_meta( $customer_id, 'imv_wallet_balance', true );
        $current_pending = get_user_meta( $customer_id, 'imv_pending_balance', true );
        $current_available = is_numeric($current_available) ? floatval($current_available) : 0;
        $current_pending = is_numeric($current_pending) ? floatval($current_pending) : 0;
        if ( $current_pending < $amount ) { return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Insufficient pending balance to release funds.' ), 400 ); }
        $new_available = $current_available + $amount;
        $new_pending = $current_pending - $amount;
        update_user_meta( $customer_id, 'imv_wallet_balance', $new_available );
        update_user_meta( $customer_id, 'imv_pending_balance', $new_pending );
        IMV_API_Logger::log( "User #{$customer_id} released funds: {$amount}. New available: {$new_available}, New pending: {$new_pending}" );
        return new WP_REST_Response( array( 'status' => 'success', 'data' => array( 'customer_id' => $customer_id, 'amount_released' => $amount, 'new_available_balance' => $new_available, 'new_pending_balance' => $new_pending, 'currency' => get_woocommerce_currency_symbol(), 'message' => 'Funds successfully released from pending.' ) ), 200 );
    }

    /**
     * Handles the API request to deduct from pending balance.
     */
    public static function handle_deduct_pending_balance_request( $request ) {
        IMV_API_Logger::log( '====== New /deduct-pending Request ======' );
        $phone_number = sanitize_text_field( $request->get_param('phone') );
        $amount = floatval( $request->get_param('amount') );
        if ( empty( $phone_number ) || ! is_numeric( $amount ) || $amount <= 0 ) { return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number and a positive amount are required.' ), 400 ); }
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone_number, 'number' => 1, 'count_total' => false ) );
        if ( empty( $users ) ) { return new WP_REST_Response( array( 'status' => 'not_found', 'message' => 'Customer not found.' ), 404 ); }
        $customer_id = $users[0]->ID;
        $current_pending = get_user_meta( $customer_id, 'imv_pending_balance', true );
        $current_pending = is_numeric($current_pending) ? floatval($current_pending) : 0;
        if ( $current_pending < $amount ) { return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Insufficient pending balance to deduct this amount.' ), 400 ); }
        $new_pending = $current_pending - $amount;
        update_user_meta( $customer_id, 'imv_pending_balance', $new_pending );
        IMV_API_Logger::log( "User #{$customer_id} deducted from pending: {$amount}. New pending balance: {$new_pending}" );
        return new WP_REST_Response( array( 'status' => 'success', 'data' => array( 'customer_id' => $customer_id, 'amount_deducted' => $amount, 'new_pending_balance' => $new_pending, 'currency' => get_woocommerce_currency_symbol(), 'message' => 'Amount successfully deducted from pending balance.' ) ), 200 );
    }

    /**
     * Deduct final order amount from customer's pending balance when a pending-assessment order is processed/completed.
     */
    public function deduct_from_pending_on_order_completion( $order_id, $old_status, $new_status, $order ) {
        if ( $old_status === 'pending-assessment' && in_array( $new_status, array( 'processing', 'completed' ) ) ) {
            $customer_id = $order->get_customer_id();
            $order_total = floatval( $order->get_total() );
            $estimated_pickup_cost = floatval( $order->get_meta( '_imv_estimated_pickup_cost', true ) );
            if ( ! $customer_id ) { IMV_API_Logger::log("Order #{$order_id}: Cannot deduct from wallet, no customer ID found."); return; }
            $customer_pending_balance = get_user_meta( $customer_id, 'imv_pending_balance', true );
            $customer_pending_balance = is_numeric($customer_pending_balance) ? floatval($customer_pending_balance) : 0;
            if ( $order_total <= 0 ) {
                IMV_API_Logger::log("Order #{$order_id}: Order total is zero or negative. Skipping wallet deduction.");
                if ($estimated_pickup_cost > 0 && $customer_pending_balance >= $estimated_pickup_cost) {
                    $new_pending_balance = $customer_pending_balance - $estimated_pickup_cost;
                    $current_available_balance = get_user_meta( $customer_id, 'imv_wallet_balance', true );
                    $current_available_balance = is_numeric($current_available_balance) ? floatval($current_available_balance) : 0;
                    $new_available_balance = $current_available_balance + $estimated_pickup_cost;
                    update_user_meta( $customer_id, 'imv_pending_balance', $new_pending_balance );
                    update_user_meta( $customer_id, 'imv_wallet_balance', $new_available_balance );
                    IMV_API_Logger::log("Order #{$order_id}: Zero total, released estimated cost {$estimated_pickup_cost} from pending to available for user #{$customer_id}. New pending: {$new_pending_balance}, New available: {$new_available_balance}");
                }
                return;
            }
            $amount_to_deduct_from_pending = 0;
            $amount_to_release_to_available = 0;
            $amount_to_deduct_from_available = 0;
            if ($estimated_pickup_cost > 0) {
                if ($order_total <= $estimated_pickup_cost) {
                    $amount_to_deduct_from_pending = $order_total;
                    $amount_to_release_to_available = $estimated_pickup_cost - $order_total;
                } else {
                    $amount_to_deduct_from_pending = $estimated_pickup_cost;
                    $amount_to_deduct_from_available = $order_total - $estimated_pickup_cost;
                }
            } else {
                $amount_to_deduct_from_pending = min($order_total, $customer_pending_balance);
                $amount_to_deduct_from_available = $order_total - $amount_to_deduct_from_pending;
            }
            $new_pending_balance = $customer_pending_balance - $amount_to_deduct_from_pending;
            update_user_meta( $customer_id, 'imv_pending_balance', $new_pending_balance );
            IMV_API_Logger::log("Order #{$order_id}: Deducted {$amount_to_deduct_from_pending} from pending balance for user #{$customer_id}. New pending: {$new_pending_balance}");
            $current_available_balance = get_user_meta( $customer_id, 'imv_wallet_balance', true );
            $current_available_balance = is_numeric($current_available_balance) ? floatval($current_available_balance) : 0;
            if ($amount_to_release_to_available > 0) {
                $new_available_balance = $current_available_balance + $amount_to_release_to_available;
                update_user_meta( $customer_id, 'imv_wallet_balance', $new_available_balance );
                IMV_API_Logger::log("Order #{$order_id}: Released excess {$amount_to_release_to_available} from pending to available for user #{$customer_id}. New available: {$new_available_balance}");
            }
            if ($amount_to_deduct_from_available > 0) {
                if ($current_available_balance >= $amount_to_deduct_from_available) {
                    $new_available_balance = $current_available_balance - $amount_to_deduct_from_available;
                    update_user_meta( $customer_id, 'imv_wallet_balance', $new_available_balance );
                    IMV_API_Logger::log("Order #{$order_id}: Covered remaining {$amount_to_deduct_from_available} from available balance for user #{$customer_id}. New available: {$new_available_balance}");
                } else {
                    IMV_API_Logger::log("Order #{$order_id}: Customer #{$customer_id} has insufficient available wallet balance to cover remaining {$amount_to_deduct_from_available}. Manual intervention required.");
                }
            }
        }
    }

    /**
     * Add funds to wallet when a simple top-up product order is completed.
     *
     * @param int $order_id The ID of the completed order.
     */
    public function add_funds_on_topup_order_completion( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_meta('_wallet_funds_added') ) {
            return; // Exit if order not found or funds already added
        }

        $customer_id = $order->get_customer_id();
        if ( ! $customer_id ) { return; }

        $items = $order->get_items();
        $total_topup_amount = 0;

        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            // Check if the product is a top-up product and NOT a subscription
            if ( has_term( 'wallet-top-up', 'product_cat', $product_id ) && ( ! function_exists('wcs_is_subscription') || ! wcs_is_subscription( $product_id ) ) ) {
                $topup_value = get_post_meta( $product_id, '_wallet_topup_value', true );
                if ( ! empty( $topup_value ) && is_numeric( $topup_value ) ) {
                    $total_topup_amount += floatval( $topup_value ) * $item->get_quantity();
                } else {
                    $total_topup_amount += $item->get_total();
                }
            }
        }

        if ( $total_topup_amount > 0 ) {
            $current_balance = get_user_meta( $customer_id, 'imv_wallet_balance', true );
            $new_balance = floatval( $current_balance ) + $total_topup_amount;
            update_user_meta( $customer_id, 'imv_wallet_balance', $new_balance );
            $order->add_order_note( sprintf( __( 'Added %s to customer wallet. New balance: %s', 'imv-api' ), wc_price( $total_topup_amount ), wc_price( $new_balance ) ) );
            $order->update_meta_data( '_wallet_funds_added', true ); // Mark order to prevent double-adding
            $order->save();
            IMV_API_Logger::log( "Order #{$order_id}: Added {$total_topup_amount} to wallet for user #{$customer_id}. New balance: {$new_balance}" );
        }
    }

    /**
     * Add funds to wallet when a subscription payment is completed.
     *
     * @param WC_Subscription|int $subscription The subscription object or ID for which payment was completed.
     */
    public function add_funds_from_subscription( $subscription ) {
        if ( is_numeric( $subscription ) ) {
            $subscription = wcs_get_subscription( $subscription );
        }

        if ( ! $subscription ) { return; }

        $customer_id = $subscription->get_customer_id();
        if ( ! $customer_id ) {
            IMV_API_Logger::log( "Subscription #{$subscription->get_id()}: No customer ID found. Cannot add funds." );
            return;
        }

        $items = $subscription->get_items();
        $total_topup_amount = 0;

        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            if ( has_term( 'wallet-top-up', 'product_cat', $product_id ) ) {
                $topup_value = get_post_meta( $product_id, '_wallet_topup_value', true );
                if ( ! empty( $topup_value ) && is_numeric( $topup_value ) ) {
                    $total_topup_amount += floatval( $topup_value ) * $item->get_quantity();
                } else {
                    $total_topup_amount += $item->get_total();
                }
            }
        }

        if ( $total_topup_amount > 0 ) {
            $current_balance = get_user_meta( $customer_id, 'imv_wallet_balance', true );
            $new_balance = floatval( $current_balance ) + $total_topup_amount;
            update_user_meta( $customer_id, 'imv_wallet_balance', $new_balance );
            $subscription->add_order_note( sprintf( __( 'Added %s to customer wallet from subscription renewal. New balance: %s', 'imv-api' ), wc_price( $total_topup_amount ), wc_price( $new_balance ) ) );
            IMV_API_Logger::log( "Subscription #{$subscription->get_id()}: Added {$total_topup_amount} to wallet for user #{$customer_id}. New balance: {$new_balance}" );
        }
    }
}
