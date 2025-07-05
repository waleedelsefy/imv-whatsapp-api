<?php
/**
 * Contains various helper functions used throughout the plugin.
 */
class IMV_API_Helpers {

    /**
     * Removes all non-numeric characters from a phone number string.
     * @param string $phone_number The raw phone number.
     * @return string The cleaned phone number.
     */
    public static function clean_phone_number($phone_number) {
        return preg_replace('/[^0-9]/', '', $phone_number);
    }

    /**
     * Formats a phone number to be compliant with Meta's API.
     * @param string $phone_number The raw phone number.
     * @return string The formatted phone number, or an empty string if invalid.
     */
    public static function format_phone_number_for_meta($phone_number) {
        $cleaned_number = self::clean_phone_number($phone_number);
        if (substr($cleaned_number, 0, 2) === '20' && strlen($cleaned_number) === 12) {
            return $cleaned_number;
        }
        if (strlen($cleaned_number) === 11 && substr($cleaned_number, 0, 1) === '0') {
            return '20' . substr($cleaned_number, 1);
        }
        if (strlen($cleaned_number) === 10) {
            return '20' . $cleaned_number;
        }
        return '';
    }

    /**
     * Detects the country from a cleaned phone number based on its prefix.
     * @param string $cleaned_phone_number The phone number with only digits.
     * @return string|null The two-letter ISO country code, or null if not found.
     */
    public static function get_country_from_phone($cleaned_phone_number) {
        $country_codes = array( '20' => 'EG', '966' => 'SA', '965' => 'KW', '971' => 'AE', '974' => 'QA', '973' => 'BH', '968' => 'OM' );
        $prefix3 = substr($cleaned_phone_number, 0, 3);
        if (isset($country_codes[$prefix3])) { return $country_codes[$prefix3]; }
        $prefix2 = substr($cleaned_phone_number, 0, 2);
        if (isset($country_codes[$prefix2])) { return $country_codes[$prefix2]; }
        return null;
    }

    /**
     * NEW: Central function to send a WhatsApp message.
     *
     * @param string $recipient_phone The raw phone number of the recipient.
     * @param string $message_body The text of the message to be sent.
     * @return bool True on success, false on failure.
     */
    public static function send_whatsapp_message( $recipient_phone, $message_body ) {
        $api_url = get_option('imv_api_notification_url');
        $api_token = get_option('imv_api_token');

        if ( empty($api_url) || empty($api_token) || empty($message_body) ) {
            IMV_API_Logger::log("WhatsApp send failed: Missing API URL, Token, or message body.");
            return false;
        }

        $formatted_phone = self::format_phone_number_for_meta($recipient_phone);
        if (empty($formatted_phone)) {
            IMV_API_Logger::log("WhatsApp send failed: Could not format a valid phone number from '{$recipient_phone}'.");
            return false;
        }

        $messageObject = array( "to" => $formatted_phone, "type" => "text", "text" => array( "preview_url" => true, "body" => $message_body ) );
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
            IMV_API_Logger::log("WhatsApp API call failed. Error: " . $response->get_error_message());
            return false;
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            IMV_API_Logger::log("WhatsApp API call sent to {$formatted_phone}. Response code: {$response_code}.");
            return $response_code >= 200 && $response_code < 300;
        }
    }
}
