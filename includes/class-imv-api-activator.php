<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class IMV_API_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Ensure WooCommerce is active for capabilities.
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Add the 'Collector' role with specific capabilities.
        // We grant 'manage_woocommerce' so they can access customer profiles and wallet fields.
        add_role(
            'imv_collector',
            __( 'Collector', 'imv-api' ),
            array(
                'read'                   => true,
                'edit_posts'             => false,
                'delete_posts'           => false,
                'publish_posts'          => false,
                'upload_files'           => true,
                'edit_products'          => true, // Allow editing products if needed
                'manage_woocommerce'     => true, // Crucial for managing wallet and orders
                'view_admin_dashboard'   => true, // Allow access to admin dashboard
            )
        );

        // Ensure default wallet balances are set for existing users who might not have them.
        $users = get_users();
        foreach ( $users as $user ) {
            if ( ! metadata_exists( 'user', $user->ID, 'imv_wallet_balance' ) ) {
                update_user_meta( $user->ID, 'imv_wallet_balance', 0 );
            }
            if ( ! metadata_exists( 'user', $user->ID, 'imv_pending_balance' ) ) {
                update_user_meta( $user->ID, 'imv_pending_balance', 0 );
            }
        }
    }
}