<?php
/**
 * Handles all custom REST API requests for customers, products, and orders.
 */
class IMV_API_Handlers {

    /**
     * Handles the request to search for products.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */

    public static function handle_product_search_request( $request ) {
        IMV_API_Logger::log('====== New /search-products Request ======');
        $search_query = sanitize_text_field( $request->get_param('query') );
        IMV_API_Logger::log('Search Query: ' . $search_query);

        if ( empty($search_query) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Search query is required.' ), 400 );
        }

        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => 10,
            's'              => $search_query,
        );
        $products_query = new WP_Query($args);

        $found_products = array();
        if ( $products_query->have_posts() ) {
            while ( $products_query->have_posts() ) {
                $products_query->the_post();
                $product = wc_get_product( get_the_ID() );
                $found_products[] = array(
                    'id'           => $product->get_id(),
                    'name'         => $product->get_name(),
                    'sku'          => $product->get_sku() ? $product->get_sku() : 'N/A',
                    'price'        => $product->get_price(),
                    'stock_status' => $product->get_stock_status(),
                );
            }
        }
        wp_reset_postdata();

        if (empty($found_products)) {
            return new WP_REST_Response( array('status' => 'not_found', 'products' => []), 404 );
        }

        return new WP_REST_Response( array('status' => 'success', 'products' => $found_products), 200 );
    }

    /**
     * Handles the request to check for a customer by phone number.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_customer_check_request( $request ) {
        IMV_API_Logger::log( '====== New /check-customer Request ======' );
        $phone_number = sanitize_text_field( $request->get_param('phone') );
        if ( empty( $phone_number ) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number is required.' ), 400 );
        }
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone_number, 'number' => 1, 'count_total' => false ) );
        if ( ! empty( $users ) ) {
            $customer = $users[0];
            $customer_id = $customer->ID;
            $customer_name = $customer->first_name . ' ' . $customer->last_name;
            $orders = wc_get_orders( array( 'customer_id' => $customer_id, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
            $last_order_status = !empty($orders) ? wc_get_order_status_name( $orders[0]->get_status() ) : 'لا توجد طلبات سابقة';
            $response_data = array( 'status' => 'found', 'data' => array( 'customer_name' => trim($customer_name), 'last_order_status' => $last_order_status ) );
            return new WP_REST_Response( $response_data, 200 );
        } else {
            return new WP_REST_Response( array('status' => 'not_found'), 404 );
        }
    }

    /**
     * Handles the request to create a new customer.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_customer_create_request( $request ) {
        IMV_API_Logger::log( '====== New /create-customer Request ======' );
        $phone = trim( $request->get_param('phone') ?? '' );
        $name = trim( $request->get_param('name') ?? '' );
        $address = trim( $request->get_param('address') ?? '' );
        $latitude = trim( $request->get_param('latitude') ?? '' );
        $longitude = trim( $request->get_param('longitude') ?? '' );
        $is_location_provided = !empty($latitude) && !empty($longitude);
        if ( empty($phone) || empty($name) || (empty($address) && !$is_location_provided) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone, name, and (address or location coordinates) are required.' ), 400 );
        }
        $cleaned_phone = IMV_API_Helpers::clean_phone_number($phone);
        $dummy_email = $cleaned_phone . '@' . wp_parse_url( get_site_url(), PHP_URL_HOST );
        if ( username_exists( $cleaned_phone ) || email_exists( $dummy_email ) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Customer already exists.' ), 409 );
        }
        $customer_id = wc_create_new_customer( $dummy_email, $cleaned_phone );
        if ( is_wp_error( $customer_id ) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => $customer_id->get_error_message() ), 500 );
        }
        $name_parts = explode( ' ', $name, 2 );
        $first_name = $name_parts[0];
        $last_name = isset( $name_parts[1] ) ? $name_parts[1] : '';
        update_user_meta( $customer_id, 'first_name', $first_name );
        update_user_meta( $customer_id, 'last_name', $last_name );
        update_user_meta( $customer_id, 'display_name', $name );
        update_user_meta( $customer_id, 'billing_phone', $cleaned_phone );
        $country_code = IMV_API_Helpers::get_country_from_phone($cleaned_phone);
        if ($country_code) {
            update_user_meta($customer_id, 'billing_country', $country_code);
        }
        if ($is_location_provided) {
            update_user_meta( $customer_id, 'billing_address_1', __( 'Location from Map', 'imv-api' ) );
            update_user_meta( $customer_id, 'billing_latitude', sanitize_text_field($latitude) );
            update_user_meta( $customer_id, 'billing_longitude', sanitize_text_field($longitude) );
        } else {
            update_user_meta( $customer_id, 'billing_address_1', sanitize_text_field($address) );
        }
        $response_data = array( 'status' => 'created', 'data' => array( 'customer_id' => $customer_id, 'customer_name' => $name, 'message' => 'New customer created successfully.' ) );
        return new WP_REST_Response( $response_data, 201 );
    }

    /**
     * Handles the request to create a new order (laundry pickup or standard).
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_order_create_request( $request ) {
        IMV_API_Logger::log( '====== New /create-order Request ======' );
        $phone = sanitize_text_field( $request->get_param('phone') );
        $products = $request->get_param('products');
        $estimated_cost = floatval( $request->get_param('estimated_cost') );
        if ( empty($phone) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number is required.' ), 400 );
        }
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone, 'number' => 1 ) );
        if ( empty($users) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Customer not found. Please create the customer first.' ), 404 );
        }
        $customer = $users[0];
        $customer_id = $customer->ID;
        try {
            $order = wc_create_order(array('customer_id' => $customer_id));
            if ( empty($products) || !is_array($products) ) {
                $order->set_status('wc-pending-assessment');
                $order->add_order_note( __( 'Order created via WhatsApp for laundry pickup. Price to be determined after assessment.', 'imv-api' ) );
                if ( $estimated_cost > 0 ) {
                    $current_available = get_user_meta( $customer_id, 'imv_wallet_balance', true );
                    if ( floatval($current_available) < $estimated_cost ) {
                        $order->delete(true);
                        return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Insufficient wallet balance for estimated cost.' ), 400 );
                    }
                    $new_available = floatval($current_available) - $estimated_cost;
                    $current_pending = get_user_meta( $customer_id, 'imv_pending_balance', true );
                    $new_pending = floatval($current_pending) + $estimated_cost;
                    update_user_meta( $customer_id, 'imv_wallet_balance', $new_available );
                    update_user_meta( $customer_id, 'imv_pending_balance', $new_pending );
                    $order->update_meta_data( '_imv_estimated_pickup_cost', $estimated_cost );
                    IMV_API_Logger::log( "User #{$customer_id} held {$estimated_cost} for order #{$order->get_id()}. New available: {$new_available}, New pending: {$new_pending}" );
                }
            } else {
                foreach ( $products as $product_data ) {
                    $product_id = wc_get_product_id_by_sku( sanitize_text_field($product_data['sku']) );
                    if ( $product_id && absint($product_data['quantity']) > 0 ) {
                        $order->add_product( wc_get_product($product_id), absint($product_data['quantity']) );
                    }
                }
            }
            $address = array(
                'first_name' => $customer->first_name, 'last_name' => $customer->last_name,
                'address_1' => get_user_meta($customer_id, 'billing_address_1', true),
                'phone' => $phone, 'email' => $customer->user_email,
                'country' => get_user_meta($customer_id, 'billing_country', true),
            );
            $order->set_address( $address, 'billing' );
            $order->calculate_totals();
            $order_id = $order->save();
            return new WP_REST_Response( array( 'status' => 'success', 'data' => array( 'order_id' => $order_id, 'order_total' => $order->get_total(), 'currency' => get_woocommerce_currency_symbol(), 'message' => 'Order created successfully.' ) ), 201 );
        } catch ( Exception $e ) {
            IMV_API_Logger::log( 'Error creating order: ' . $e->getMessage() );
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'An internal error occurred.' ), 500 );
        }
    }

    /**
     * UPDATED: Handles the request to top up a wallet.
     * Creates a pending order, sends a payment link via WhatsApp, and returns the link in the response.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_wallet_topup_request( $request ) {
        IMV_API_Logger::log( '====== New /topup-wallet Request ======' );
        $phone = sanitize_text_field( $request->get_param('phone') );
        $sku = sanitize_text_field( $request->get_param('sku') );

        if ( empty($phone) || empty($sku) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number and product SKU are required.' ), 400 );
        }

        // 1. Find the customer
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone, 'number' => 1 ) );
        if ( empty($users) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Customer not found.' ), 404 );
        }
        $customer = $users[0];
        $customer_id = $customer->ID;

        // 2. Find the product by SKU
        $product_id = wc_get_product_id_by_sku( $sku );
        if ( ! $product_id ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Product with the specified SKU not found.' ), 404 );
        }
        $product = wc_get_product( $product_id );

        // 3. Verify it's a valid top-up product
        if ( ! has_term( 'wallet-top-up', 'product_cat', $product_id ) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'The specified product is not a valid wallet top-up product.' ), 400 );
        }

        try {
            // 4. Create a new order with 'pending' status
            $order = wc_create_order( array( 'customer_id' => $customer_id ) );
            $order->add_product( $product, 1 );
            $address = array(
                'first_name' => $customer->first_name,
                'last_name'  => $customer->last_name,
                'phone'      => $phone,
                'email'      => $customer->user_email,
            );
            $order->set_address( $address, 'billing' );
            $order->calculate_totals();
            $order->update_status( 'pending', __( 'Awaiting payment for wallet top-up via API.', 'imv-api' ) );

            // 5. Generate a payment link for the order
            $payment_link = $order->get_checkout_payment_url();
            $order_id = $order->get_id();

            // 6. Create a clean, bilingual message and send it via WhatsApp
            $customer_name = $customer->first_name;
            // Get the raw price without HTML tags
            $clean_price = wp_strip_all_tags( wc_price( $order->get_total(), array('currency' => $order->get_currency()) ) );

            $message_body = sprintf(
                "مرحباً %s،\nلإتمام شحن محفظتك بقيمة %s، يرجى استخدام الرابط التالي:\n%s\n\n---\n\nHello %s,\nTo complete your wallet top-up of %s, please use the following link:\n%s",
                $customer_name,
                $clean_price,
                $payment_link,
                $customer_name,
                $clean_price,
                $payment_link
            );

            // Send the message with URL preview disabled for a cleaner look
            IMV_API_Helpers::send_whatsapp_message( $phone, $message_body, false );

            $response_data = array(
                'status'  => 'success',
                'data'    => array(
                    'order_id'      => $order_id,
                    'payment_link'  => $payment_link,
                    'message'       => 'Wallet top-up order created and a payment link has been sent to the customer.',
                    'customer_id'   => $customer_id,
                )
            );
            return new WP_REST_Response( $response_data, 201 );

        } catch ( Exception $e ) {
            IMV_API_Logger::log( 'Error creating top-up order via API: ' . $e->getMessage() );
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'An internal error occurred while creating the top-up order.' ), 500 );
        }
    }
}