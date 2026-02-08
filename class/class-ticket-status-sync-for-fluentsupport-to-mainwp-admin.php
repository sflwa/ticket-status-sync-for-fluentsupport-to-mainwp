<?php
/**
 * MainWP FluentSupport Admin
 *
 * Handles the administration interface and settings processing for the extension.
 *
 * @package MainWP\Extensions\FluentSupport
 */

namespace MainWP\Extensions\FluentSupport;

/**
 * Class MainWP_FluentSupport_Admin
 *
 * Provides the settings forms and handlers for the FluentSupport integration.
 */
class MainWP_FluentSupport_Admin {

	/**
	 * Static instance of this class.
	 *
	 * @var MainWP_FluentSupport_Admin|null
	 */
	public static $instance = null;

	/**
	 * Current version of the admin component.
	 *
	 * @var string
	 */
	private $version = '1.2.6';

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return MainWP_FluentSupport_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * MainWP_FluentSupport_Admin constructor.
	 *
	 * Hooks into WordPress admin_init and initializes sub-components.
	 */
	public function __construct() {
		// Initialize the DB class to ensure table exists.
		MainWP_FluentSupport_DB::get_instance()->install();

		// Initialize the Widget class.
		MainWP_FluentSupport_Widget::get_instance();

		// AJAX handler for manual ticket fetching.
		add_action( 'wp_ajax_mainwp_fluentsupport_fetch_tickets', array( $this, 'ajax_fetch_tickets' ) );

		// Hook into admin_init to process traditional form submission for settings.
		add_action( 'admin_init', array( $this, 'process_settings_save' ) );
	}

	/**
	 * Retrieves the stored Support Site URL.
	 *
	 * @return string
	 */
	private function get_support_site_url() {
		return rtrim( get_option( 'mainwp_fluentsupport_site_url', '' ), '/' );
	}

	/**
	 * Retrieves the stored API Username.
	 *
	 * @return string
	 */
	private function get_api_username() {
		return get_option( 'mainwp_fluentsupport_api_username', '' );
	}

	/**
	 * Retrieves the stored API Password (Application Password).
	 *
	 * @return string
	 */
	private function get_api_password() {
		return get_option( 'mainwp_fluentsupport_api_password', '' );
	}

	/**
	 * Processes the traditional POST request to save settings.
	 * Hooked to admin_init.
	 */
	public function process_settings_save() {
		// Check if our specific form was submitted.
		if ( ! isset( $_POST['mainwp_fluentsupport_settings_save_nonce'] ) ) {
			return;
		}

		// Verify Nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mainwp_fluentsupport_settings_save_nonce'] ) ), 'mainwp_fluentsupport_settings_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) );
		}

		// Verify Permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) );
		}

		// Sanitize and Save.
		$site_url     = isset( $_POST['fluentsupport_site_url'] ) ? sanitize_url( wp_unslash( $_POST['fluentsupport_site_url'] ) ) : '';
		$api_username = isset( $_POST['fluentsupport_api_username'] ) ? sanitize_text_field( wp_unslash( $_POST['fluentsupport_api_username'] ) ) : '';
		$api_password = isset( $_POST['fluentsupport_api_password'] ) ? sanitize_text_field( wp_unslash( $_POST['fluentsupport_api_password'] ) ) : '';

		update_option( 'mainwp_fluentsupport_site_url', $site_url, false );
		update_option( 'mainwp_fluentsupport_api_username', $api_username, false );
		update_option( 'mainwp_fluentsupport_api_password', $api_password, false );

		// Dynamic redirect to the current extension page to avoid hardcoded slug issues.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'Extensions-Mainwp-FluentSupport';
		$redirect_url = admin_url( 'admin.php?page=' . $page . '&tab=settings&message=settings_saved' );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Renders the Settings tab content.
	 */
	public function render_settings_tab() {
		$current_site_url     = $this->get_support_site_url();
		$current_api_username = $this->get_api_username();
		$current_api_password = $this->get_api_password();

		// Show success message if redirect parameter is present.
		if ( isset( $_GET['message'] ) && 'settings_saved' === $_GET['message'] ) {
			echo '<div class="mainwp-notice mainwp-notice-green">' . esc_html__( 'Settings saved successfully!', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) . '</div>';
		}
		?>
		<div class="mainwp-padd-cont">
			<h3><?php esc_html_e( 'Support Site Configuration', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></h3>
			<p><?php esc_html_e( 'Configure the connection to your FluentSupport site. These settings are saved via traditional form submission.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'mainwp_fluentsupport_settings_save', 'mainwp_fluentsupport_settings_save_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="fluentsupport_site_url"><?php esc_html_e( 'Support Site URL', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label>
						</th>
						<td>
							<input 
								type="url"
								id="fluentsupport_site_url" 
								name="fluentsupport_site_url" 
								class="regular-text"
								value="<?php echo esc_attr( $current_site_url ); ?>"
								placeholder="https://your-support-site.com"
								required
							/>
							<p class="description"><?php esc_html_e( 'The base URL for the site hosting FluentSupport.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fluentsupport_api_username"><?php esc_html_e( 'API Username', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label>
						</th>
						<td>
							<input 
								type="text"
								id="fluentsupport_api_username" 
								name="fluentsupport_api_username" 
								class="regular-text"
								value="<?php echo esc_attr( $current_api_username ); ?>"
								required
							/>
							<p class="description"><?php esc_html_e( 'The WordPress username that generated the Application Password.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fluentsupport_api_password"><?php esc_html_e( 'Application Password', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label>
						</th>
						<td>
							<input 
								type="password"
								id="fluentsupport_api_password" 
								name="fluentsupport_api_password" 
								class="regular-text"
								value="<?php echo esc_attr( $current_api_password ); ?>"
								required
							/>
							<p class="description"><?php esc_html_e( 'The Application Password (must have permissions to access FluentSupport).', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></p>
						</td>
					</tr>
				</table>

				<div id="mainwp-fluentsupport-message" style="display:none; margin: 20px 0;"></div>

				<p class="submit">
					<button type="submit" name="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the Overview tab content.
	 */
	public function render_overview_tab() {
		$support_site_url = $this->get_support_site_url();
		
		if ( empty( $support_site_url ) ) {
			echo '<div class="mainwp-notice mainwp-notice-red">' . esc_html__( 'Please go to the Settings tab and configure your Support Site URL and REST API credentials.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) . '</div>';
			return;
		}

		$db_results   = MainWP_FluentSupport_Utility::api_get_tickets_from_db();
		$initial_html = '<tr><td colspan="4">' . esc_html__( 'No tickets found in local database. Click "Fetch Latest Tickets" to sync.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) . '</td></tr>';

		if ( $db_results['success'] && ! empty( $db_results['tickets'] ) ) {
			$initial_html = '';
			foreach ( $db_results['tickets'] as $ticket ) {
				$initial_html .= '
                    <tr>
                        <td>' . $ticket['client_site_name'] . '</td>
                        <td><a href="' . esc_url( $ticket['ticket_url'] ) . '" target="_blank">' . esc_html( $ticket['title'] ) . '</a></td>
                        <td>' . esc_html( $ticket['status'] ) . '</td>
                        <td>' . esc_html( $ticket['updated_at'] ) . '</td>
                    </tr>';
			}
		}

		?>
		<div class="mainwp-padd-cont">
			<div id="mainwp-fluentsupport-message" style="display:none; margin-bottom: 20px;"></div>
			
			<p><button id="mainwp-fluentsupport-fetch-btn" class="button button-primary"><?php esc_html_e( 'Fetch Latest Tickets', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></button></p>
			
			<table class="ui stackable table mainwp-favorites-table dataTable unstackable" id="mainwp-fluentsupport-tickets-table">
				<thead>
					<tr>
						<th width="15%"><?php esc_html_e( 'Client Site', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></th>
						<th width="50%"><?php esc_html_e( 'Ticket Title', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></th>
						<th width="15%"><?php esc_html_e( 'Status', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></th>
						<th width="20%"><?php esc_html_e( 'Last Update', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></th>
					</tr>
				</thead>
				<tbody id="fluentsupport-ticket-data">
					<?php echo wp_kses_post( $initial_html ); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX Handler: Kicks off the sync process and returns data from the local DB.
	 *
	 * @return void
	 */
	public function ajax_fetch_tickets() {
		check_ajax_referer( 'ticket-status-sync-for-fluentsupport-to-mainwp-nonce', 'security' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) ) );
		}
		
		$support_site_url = $this->get_support_site_url();
		$api_username     = $this->get_api_username();
		$api_password     = $this->get_api_password();

		if ( empty( $support_site_url ) || empty( $api_username ) || empty( $api_password ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'API connection details are missing. Check Settings.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) ) );
		}

		// Run the synchronization process.
		$sync_result = MainWP_FluentSupport_Utility::api_sync_tickets( $support_site_url, $api_username, $api_password );
		
		// Retrieve data from DB.
		$db_results = MainWP_FluentSupport_Utility::api_get_tickets_from_db();
		
		$html_output = '';
		if ( $db_results['success'] && ! empty( $db_results['tickets'] ) ) {
			foreach ( $db_results['tickets'] as $ticket ) {
				$html_output .= '
                    <tr>
                        <td>' . $ticket['client_site_name'] . '</td>
                        <td><a href="' . esc_url( $ticket['ticket_url'] ) . '" target="_blank">' . esc_html( $ticket['title'] ) . '</a></td>
                        <td>' . esc_html( $ticket['status'] ) . '</td>
                        <td>' . esc_html( $ticket['updated_at'] ) . '</td>
                    </tr>';
			}
		} else {
			$html_output = '<tr><td colspan="4">' . esc_html__( 'No tickets found in local database.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) . '</td></tr>';
		}

		if ( $sync_result['success'] ) {
			$message = sprintf( esc_html__( 'Sync complete! %d tickets processed.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ), $sync_result['synced'] );
			wp_send_json_success( array( 'html' => $html_output, 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'html' => $html_output, 'message' => $sync_result['error'] ?? esc_html__( 'Sync failed.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) ) );
		}
	}
}
