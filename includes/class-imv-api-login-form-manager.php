<?php
/**
 * Manages the replacement of the default WooCommerce login form with an OTP-based system.
 */
class IMV_API_Login_Form_Manager {

    /**
     * A flag to ensure the form is rendered only once per page load.
     * @var bool
     */
    private static $form_rendered = false;

    /**
     * Enqueues the necessary scripts and styles for the OTP login form.
     */
    public function enqueue_scripts() {
        if ( function_exists('is_account_page') && is_account_page() && ! is_user_logged_in() ) {
            wp_enqueue_style( 'imv-otp-login-css', plugin_dir_url( __DIR__ ) . 'public/css/imv-otp-login.css', array(), '1.1.0' );
            wp_enqueue_script( 'imv-otp-login-js', plugin_dir_url( __DIR__ ) . 'public/js/imv-otp-login.js', array('jquery'), '1.1.0', true );
            wp_localize_script('imv-otp-login-js', 'imv_otp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('imv_otp_nonce'),
                'strings'  => array(
                    'enter_phone'    => esc_html__('Please enter your phone number.', 'imv-api'),
                    'sending'        => esc_html__('Sending...', 'imv-api'),
                    'send_otp'       => esc_html__('Send OTP', 'imv-api'),
                    'error_occurred' => esc_html__('An error occurred. Please try again.', 'imv-api'),
                    'enter_otp'      => esc_html__('Please enter the OTP code.', 'imv-api'),
                    'verifying'      => esc_html__('Verifying...', 'imv-api'),
                    'login'          => esc_html__('Login', 'imv-api'),
                )
            ));
        }
    }

    /**
     * Renders the custom OTP login form structure.
     * Uses a static flag to prevent rendering more than once.
     */
    public function render_otp_login_form() {
        if ( is_user_logged_in() || self::$form_rendered ) {
            return;
        }
        self::$form_rendered = true; // Set the flag to true after first render
        ?>
        <div id="imv-otp-login-wrapper">
            <div id="imv-phone-step">
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="imv_phone"><?php esc_html_e('Phone Number', 'imv-api'); ?>&nbsp;<span class="required">*</span></label>
                    <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="imv_phone" id="imv_phone" autocomplete="tel" placeholder="<?php esc_html_e('e.g., 201000843339', 'imv-api'); ?>" />
                </p>
                <p class="form-row">
                    <button type="button" class="woocommerce-button button" id="imv-request-otp-btn"><?php esc_html_e('Send OTP', 'imv-api'); ?></button>
                </p>
            </div>
            <div id="imv-otp-step" style="display:none;">
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="imv_otp"><?php esc_html_e('OTP Code', 'imv-api'); ?>&nbsp;<span class="required">*</span></label>
                    <input type="text" inputmode="numeric" pattern="[0-9]*" class="woocommerce-Input woocommerce-Input--text input-text" name="imv_otp" id="imv_otp" autocomplete="one-time-code" />
                </p>
                <p class="form-row">
                    <input type="hidden" id="imv_phone_for_verify" name="imv_phone_for_verify" />
                    <button type="button" class="woocommerce-button button" name="login" id="imv-verify-otp-btn"><?php esc_html_e('Login', 'imv-api'); ?></button>
                </p>
                <p><a href="#" id="imv-change-phone-btn" class="imv-change-phone-link"><?php esc_html_e('Change phone number?', 'imv-api'); ?></a></p>
            </div>
            <div id="imv-otp-message" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * AJAX handler for requesting an OTP.
     */
    public function ajax_request_otp() {
        check_ajax_referer('imv_otp_nonce', 'nonce');
        $request = new WP_REST_Request('POST');
        $request->set_param('phone', sanitize_text_field($_POST['phone']));
        $response = IMV_API_OTP_Manager::handle_otp_request($request);
        wp_send_json($response->get_data(), $response->get_status());
    }

    /**
     * AJAX handler for verifying OTP and logging the user in.
     */
    public function ajax_verify_otp_and_login() {
        check_ajax_referer('imv_otp_nonce', 'nonce');
        $phone = sanitize_text_field($_POST['phone']);
        $otp = sanitize_text_field($_POST['otp']);
        $request = new WP_REST_Request('POST');
        $request->set_body_params(array('phone' => $phone, 'otp' => $otp));
        $verification_response = IMV_API_OTP_Manager::handle_otp_verification($request);
        $verification_data = $verification_response->get_data();

        if ( $verification_response->get_status() !== 200 ) {
            wp_send_json_error(array('message' => $verification_data['message']));
            return;
        }
        $user_id = $verification_data['data']['customer_id'];
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        wp_send_json_success(array(
            'message' => __('Login successful! Redirecting...', 'imv-api'),
            'redirect' => wc_get_page_permalink('myaccount')
        ));
    }
}
