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
     * Formats a phone number to be compliant with Meta's API, assuming Egyptian context primarily.
     * @param string $phone_number The raw phone number.
     * @return string The formatted phone number, or an empty string if invalid.
     */
    public static function format_phone_number_for_meta($phone_number) {
        $cleaned_number = self::clean_phone_number($phone_number);

        // Assumes Egyptian numbers start with 20 and are 12 digits, or start with 0 and are 11 digits.
        // Or 10 digits (without leading zero or country code)
        if (substr($cleaned_number, 0, 2) === '20' && strlen($cleaned_number) === 12) {
            return $cleaned_number;
        }
        if (strlen($cleaned_number) === 11 && substr($cleaned_number, 0, 1) === '0') {
            return '20' . substr($cleaned_number, 1);
        }
        if (strlen($cleaned_number) === 10) {
            return '20' . $cleaned_number;
        }
        // For other countries, you might need more sophisticated internationalization logic
        // This helper only handles specific Egyptian formats for Meta API.
        return ''; // Return empty if format is unknown or not supported
    }

    /**
     * Detects the country from a cleaned phone number based on its prefix.
     * @param string $cleaned_phone_number The phone number with only digits.
     * @return string|null The two-letter ISO country code, or null if not found.
     */
    public static function get_country_from_phone($cleaned_phone_number) {
        $country_codes = array(
            '20'  => 'EG', // Egypt
            '966' => 'SA', // Saudi Arabia
            '965' => 'KW', // Kuwait
            '971' => 'AE', // United Arab Emirates
            '974' => 'QA', // Qatar
            '973' => 'BH', // Bahrain
            '968' => 'OM'  // Oman
        );

        // Check for 3-digit codes first to avoid conflicts (e.g., 966 vs 96)
        $prefix3 = substr($cleaned_phone_number, 0, 3);
        if (isset($country_codes[$prefix3])) {
            return $country_codes[$prefix3];
        }

        // Check for 2-digit codes
        $prefix2 = substr($cleaned_phone_number, 0, 2);
        if (isset($country_codes[$prefix2])) {
            return $country_codes[$prefix2];
        }

        return null; // Return null if no country is found
    }
}