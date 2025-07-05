<?php
/**
 * Handles OTP generation, sending, and verification for passwordless login.
 */
class IMV_API_OTP_Manager {

    /**
     * Handles the API request to generate and send an OTP to the user's WhatsApp.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public static function handle_otp_request( $request ) {
        IMV_API_Logger::log('====== New /request-otp Request ======');
        $phone = sanitize_text_field( $request->get_param('phone') );

        if ( empty($phone) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number is required.' ), 400 );
        }

        // Find user by phone. If not found, a new user will be created on OTP verification.
        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone, 'number' => 1 ) );
        $user_exists = ! empty($users);
        $user_id = $user_exists ? $users[0]->ID : 0;
        $customer_name = $user_exists ? $users[0]->first_name : __( 'Customer', 'imv-api' );

        // Generate a 6-digit OTP
        $otp_code = rand(100000, 999999);
        // OTP is valid for 10 minutes
        $otp_expires = time() + (10 * 60);

        // Store OTP and its expiration. If user doesn't exist, we store it in a temporary option.
        if ( $user_id ) {
            update_user_meta( $user_id, '_imv_otp_code', $otp_code );
            update_user_meta( $user_id, '_imv_otp_expires', $otp_expires );
        } else {
            // For new users, store OTP temporarily in the options table against their phone number
            set_transient( 'imv_otp_' . $phone, array('code' => $otp_code, 'expires' => $otp_expires), 10 * 60 );
        }

        IMV_API_Logger::log("Generated OTP {$otp_code} for phone {$phone}.");

        // Send OTP message via WhatsApp
        $message_body = sprintf(
            "رمز التحقق الخاص بك هو: %s\nهذا الرمز صالح لمدة 10 دقائق.\n\n---\n\nYour verification code is: %s\nThis code is valid for 10 minutes.",
            $otp_code,
            $otp_code
        );

        $sent = IMV_API_Helpers::send_whatsapp_message( $phone, $message_body, false );

        if ( $sent ) {
            return new WP_REST_Response( array( 'status' => 'success', 'message' => 'OTP has been sent successfully.' ), 200 );
        } else {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Failed to send OTP via WhatsApp.' ), 500 );
        }
    }

    /**
     * Handles the API request to verify the OTP and log the user in.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public static function handle_otp_verification( $request ) {
        IMV_API_Logger::log('====== New /verify-otp Request ======');
        $phone = sanitize_text_field( $request->get_param('phone') );
        $otp_submitted = sanitize_text_field( $request->get_param('otp') );

        if ( empty($phone) || empty($otp_submitted) ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Phone number and OTP are required.' ), 400 );
        }

        $users = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone, 'number' => 1 ) );
        $user_id = !empty($users) ? $users[0]->ID : 0;

        $stored_otp = '';
        $otp_expires = 0;

        if ( $user_id ) {
            $stored_otp = get_user_meta( $user_id, '_imv_otp_code', true );
            $otp_expires = get_user_meta( $user_id, '_imv_otp_expires', true );
        } else {
            $transient_data = get_transient( 'imv_otp_' . $phone );
            if ( $transient_data ) {
                $stored_otp = $transient_data['code'];
                $otp_expires = $transient_data['expires'];
            }
        }

        if ( empty($stored_otp) || time() > $otp_expires ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'OTP is invalid or has expired.' ), 401 );
        }

        if ( (string) $stored_otp !== (string) $otp_submitted ) {
            return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Incorrect OTP.' ), 401 );
        }

        // OTP is correct, clear it
        if ( $user_id ) {
            delete_user_meta( $user_id, '_imv_otp_code' );
            delete_user_meta( $user_id, '_imv_otp_expires' );
        } else {
            delete_transient( 'imv_otp_' . $phone );
            // Since user doesn't exist, create a new one
            $user_id = IMV_API_Handlers::handle_customer_create_request( $request );
            if( is_wp_error( $user_id ) ) {
                return new WP_REST_Response( array( 'status' => 'error', 'message' => 'Could not create new user.' ), 500 );
            }
        }

        IMV_API_Logger::log("OTP verified for phone {$phone}. User ID: {$user_id}.");

        // Here you can implement a session token if needed for subsequent API calls.
        // For now, we return a success message.
        return new WP_REST_Response( array(
            'status' => 'success',
            'message' => 'OTP verified successfully.',
            'data' => array(
                'customer_id' => $user_id,
            )
        ), 200 );
    }
}
