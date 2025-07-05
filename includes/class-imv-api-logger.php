<?php
namespace Imv\WhatsAppApi;

/**
 * Handles the plugin's logging system.
 */
class IMV_API_Logger {

    /**
     * Writes a message to the plugin's debug log file.
     * Logging is enabled/disabled via plugin settings.
     *
     * @param mixed $message The message to log. Can be a string, array, or object.
     */
    public static function log( $message ) {
        if ( get_option( 'imv_api_logging_enabled' ) !== '1' ) {
            return;
        }
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/imv-api-logs';
        if ( ! is_dir( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }
        $log_file = $log_dir . '/debug.log';
        $timestamp = date( 'Y-m-d H:i:s' );
        $log_entry = sprintf( "[%s] %s\n", $timestamp, is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message );
        file_put_contents( $log_file, $log_entry, FILE_APPEND );
    }
}