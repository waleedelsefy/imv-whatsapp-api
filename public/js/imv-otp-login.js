jQuery(document).ready(function($) {
    'use strict';

    const wrapper = $('#imv-otp-login-wrapper');
    if (!wrapper.length) {
        return;
    }

    const loginForm = wrapper.closest('form.woocommerce-form-login');
    const messageDiv = $('#imv-otp-message');
    const phoneStep = $('#imv-phone-step');
    const otpStep = $('#imv-otp-step');
    const requestBtn = $('#imv-request-otp-btn');
    const verifyBtn = $('#imv-verify-otp-btn');

    // Hide the original WooCommerce login form elements
    loginForm.find('.woocommerce-form-row, .woocommerce-form-login__rememberme, .woocommerce-LostPassword, button[name=\"login\"]').not(wrapper.find('*').addBack()).hide();

    function showMessage(message, isError = false) {
        messageDiv.html(message).removeClass('woocommerce-error woocommerce-message').addClass(isError ? 'woocommerce-error' : 'woocommerce-message').slideDown();
    }

    function hideMessage() {
        messageDiv.slideUp();
    }

    // Step 1: Request OTP
    requestBtn.on('click', function(e) {
        e.preventDefault();
        const phone = $('#imv_phone').val();

        if (!phone.trim()) {
            showMessage(imv_otp_ajax.strings.enter_phone, true);
            return;
        }

        requestBtn.prop('disabled', true).text(imv_otp_ajax.strings.sending);
        hideMessage();

        $.ajax({
            url: imv_otp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'imv_request_otp',
                phone: phone,
                nonce: imv_otp_ajax.nonce
            },
            success: function(response) {
                if (response.status === 'success') {
                    showMessage(response.message);
                    phoneStep.slideUp();
                    otpStep.slideDown();
                    $('#imv_phone_for_verify').val(phone);
                    $('#imv_otp').focus();
                } else {
                    showMessage(response.message, true);
                }
            },
            error: function() {
                showMessage(imv_otp_ajax.strings.error_occurred, true);
            },
            complete: function() {
                requestBtn.prop('disabled', false).text(imv_otp_ajax.strings.send_otp);
            }
        });
    });

    // Step 2: Verify OTP and Login
    verifyBtn.on('click', function(e) {
        e.preventDefault();
        const otp = $('#imv_otp').val();
        const phone = $('#imv_phone_for_verify').val();

        if (!otp.trim()) {
            showMessage(imv_otp_ajax.strings.enter_otp, true);
            return;
        }

        verifyBtn.prop('disabled', true).text(imv_otp_ajax.strings.verifying);
        hideMessage();

        $.ajax({
            url: imv_otp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'imv_verify_otp_and_login',
                phone: phone,
                otp: otp,
                nonce: imv_otp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message);
                    // Redirect after a short delay
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    showMessage(response.data.message, true);
                    verifyBtn.prop('disabled', false).text(imv_otp_ajax.strings.login);
                }
            },
            error: function() {
                showMessage(imv_otp_ajax.strings.error_occurred, true);
                verifyBtn.prop('disabled', false).text(imv_otp_ajax.strings.login);
            }
        });
    });

    // Handle "Enter" key press
    $('#imv_phone').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            requestBtn.click();
        }
    });
    $('#imv_otp').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            verifyBtn.click();
        }
    });

    // Change phone number link
    $('#imv-change-phone-btn').on('click', function(e){
        e.preventDefault();
        hideMessage();
        otpStep.slideUp();
        phoneStep.slideDown();
        $('#imv_otp').val('');
        $('#imv_phone').focus();
    });
});
