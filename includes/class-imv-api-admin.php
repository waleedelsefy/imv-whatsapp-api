<?php
/**
 * Handles the admin settings page and user list modifications for the plugin.
 */
class IMV_API_Admin {

	/**
	 * Adds the admin menu page for the plugin settings.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'IMV WhatsApp API Settings', 'imv-api' ),
			__( 'IMV WhatsApp API', 'imv-api' ),
			'manage_options',
			'imv-whatsapp-api',
			array( $this, 'settings_page_html' )
		);
	}

	/**
	 * Initializes the settings for the admin page.
	 */
	public function settings_init() {
		register_setting( 'imv_api_settings_group', 'imv_api_logging_enabled' );
		register_setting( 'imv_api_settings_group', 'imv_api_notification_url' );
		register_setting( 'imv_api_settings_group', 'imv_api_token' );

		add_settings_section( 'imv_api_logging_section', __( 'Logging Settings', 'imv-api' ), null, 'imv-whatsapp-api' );
		add_settings_field( 'imv_api_logging_enabled_field', __( 'Enable API Logging', 'imv-api' ), array( $this, 'logging_checkbox_html' ), 'imv-whatsapp-api', 'imv_api_logging_section' );

		add_settings_section( 'imv_api_notification_section', __( 'Order Notification API Settings', 'imv-api' ), null, 'imv-whatsapp-api' );
		add_settings_field( 'imv_api_notification_url_field', __( 'Chatbot API URL', 'imv-api' ), array( $this, 'notification_url_field_html' ), 'imv-whatsapp-api', 'imv_api_notification_section' );
		add_settings_field( 'imv_api_token_field', __( 'API Token', 'imv-api' ), array( $this, 'token_field_html' ), 'imv-whatsapp-api', 'imv_api_notification_section' );
	}

	public function logging_checkbox_html() {
		$is_checked = get_option( 'imv_api_logging_enabled' );
		echo '<input type="checkbox" name="imv_api_logging_enabled" value="1" ' . checked( 1, $is_checked, false ) . ' />';
		echo '<p class="description">' . __( 'When checked, all API requests will be saved to a log file.', 'imv-api' ) . '</p>';
	}

	public function notification_url_field_html() {
		$api_url = get_option('imv_api_notification_url');
		echo '<input type="url" name="imv_api_notification_url" value="' . esc_attr($api_url) . '" class="regular-text" placeholder="https://wa.imv.plus">';
		echo '<p class="description">' . __( 'Enter the base URL of your chatbot system (e.g., https://wa.imv.plus).', 'imv-api' ) . '</p>';
	}

	public function token_field_html() {
		$api_token = get_option('imv_api_token');
		echo '<input type="text" name="imv_api_token" value="' . esc_attr($api_token) . '" class="regular-text" placeholder="Your_API_Key_Here">';
		echo '<p class="description">' . __( 'Enter the API Key provided by your chatbot system for authentication.', 'imv-api' ) . '</p>';
	}

	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
				<?php
				settings_fields( 'imv_api_settings_group' );
				do_settings_sections( 'imv-whatsapp-api' );
				submit_button( __( 'Save Settings', 'imv-api' ) );
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * NEW: Adds the "Wallet Balance" column to the users list table.
	 *
	 * @param array $columns The existing columns.
	 * @return array The modified columns.
	 */
	public function add_wallet_balance_column( $columns ) {
		$columns['imv_wallet_balance'] = __( 'Wallet Balance', 'imv-api' );
		return $columns;
	}

	/**
	 * NEW: Displays the content for the custom "Wallet Balance" column.
	 *
	 * @param string $value       The value to display. Default empty.
	 * @param string $column_name The name of the column.
	 * @param int    $user_id     The ID of the current user.
	 * @return string The content to display in the column.
	 */
	public function display_wallet_balance_column( $value, $column_name, $user_id ) {
		if ( 'imv_wallet_balance' === $column_name ) {
			$balance = get_user_meta( $user_id, 'imv_wallet_balance', true );
			$balance = is_numeric( $balance ) ? floatval( $balance ) : 0;
			return wc_price( $balance );
		}
		return $value;
	}

	/**
	 * NEW: Makes the "Wallet Balance" column sortable.
	 *
	 * @param array $columns The existing sortable columns.
	 * @return array The modified sortable columns.
	 */
	public function make_wallet_column_sortable( $columns ) {
		$columns['imv_wallet_balance'] = 'imv_wallet_balance';
		return $columns;
	}

	/**
	 * NEW: Handles the sorting logic for the "Wallet Balance" column.
	 *
	 * @param WP_User_Query $query The WP_User_Query instance.
	 */
	public function sort_users_by_wallet_balance( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->get('orderby');
		if ( 'imv_wallet_balance' === $orderby ) {
			$query->set('meta_key', 'imv_wallet_balance');
			$query->set('orderby', 'meta_value_num');
		}
	}
}
