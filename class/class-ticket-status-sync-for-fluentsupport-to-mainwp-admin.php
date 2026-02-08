<?php
/**
 * MainWP FluentSupport Admin
 *
 * Handles the administration interface and traditional settings saving.
 *
 * @package MainWP\Extensions\FluentSupport
 */

namespace MainWP\Extensions\FluentSupport;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MainWP_FluentSupport_Admin
 */
class MainWP_FluentSupport_Admin {

	/**
	 * Static instance of this class.
	 *
	 * @var MainWP_FluentSupport_Admin|null
	 */
	public static $instance = null;

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
	 */
	public function __construct() {
		// Ensure DB is ready.
		MainWP_FluentSupport_DB::get_instance()->install();

		// Hook into admin_init to process traditional form submission for settings.
		add_action( 'admin_init', array( $this, 'process_settings_save' ) );

		// Hook directly into admin_enqueue_scripts to ensure assets are loaded on extension pages.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles specifically for the extension pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function admin_enqueue_scripts( $hook ) {
		/**
		 * Check if we are on the extension page or the MainWP dashboard.
		 * Using stristr for case-insensitive matching to handle various slug formats.
		 */
		if ( stristr( $hook, 'ticket-status-sync-for-fluentsupport-to-mainwp' ) || 'toplevel_page_mainwp_tab' === $hook ) {
			$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
			$version    = '1.2.6';

			wp_enqueue_script(
				'ticket-status-sync-for-fluentsupport-to-mainwp-js',
				$plugin_url . 'js/ticket-status-sync-for-fluentsupport-to-mainwp.js',
				array( 'jquery' ),
				$version,
				true
			);

			wp_enqueue_style(
				'ticket-status-sync-for-fluentsupport-to-mainwp-css',
				$plugin_url . 'css/ticket-status-sync-for-fluentsupport-to-mainwp.css',
				array(),
				$version
			);

			wp_localize_script( 'ticket-status-sync-for-fluentsupport-to-mainwp-js', 'ticketStatusSync', array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'security' => wp_create_nonce( 'ticket-status-sync-for-fluentsupport-to-mainwp-nonce' ),
			) );
		}
	}

	/**
	 * Retrieves the stored Support Site URL.
	 */
	private function get_support_site_url() {
		return rtrim( get_option( 'mainwp_fluentsupport_site_url', '' ), '/' );
	}

	/**
	 * Retrieves the stored API Username.
	 */
	private function get_api_username() {
		return get_option( 'mainwp_fluentsupport_api_username', '' );
	}

	/**
	 * Retrieves the stored API Password.
	 */
	private function get_api_password() {
		return get_option( 'mainwp_fluentsupport_api_password', '' );
	}

	/**
	 * Processes the traditional POST request to save settings.
	 */
	public function process_settings_save() {
		// Verify Nonce immediately before touching any other form data.
		if ( ! isset( $_POST['mainwp_fluentsupport_settings_save_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mainwp_fluentsupport_settings_save_nonce'] ) ), 'mainwp_fluentsupport_settings_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) );
		}

		$site_url     = isset( $_POST['fluentsupport_site_url'] ) ? sanitize_url( wp_unslash( $_POST['fluentsupport_site_url'] ) ) : '';
		$api_username = isset( $_POST['fluentsupport_api_username'] ) ? sanitize_text_field( wp_unslash( $_POST['fluentsupport_api_username'] ) ) : '';
		$api_password = isset( $_POST['fluentsupport_api_password'] ) ? sanitize_text_field( wp_unslash( $_POST['fluentsupport_api_password'] ) ) : '';

		update_option( 'mainwp_fluentsupport_site_url', $site_url, false );
		update_option( 'mainwp_fluentsupport_api_username', $api_username, false );
		update_option( 'mainwp_fluentsupport_api_password', $api_password, false );

		$page         = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'Extensions-Mainwp-FluentSupport';
		$redirect_url = admin_url( 'admin.php?page=' . $page . '&tab=settings&message=settings_saved' );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Renders the Settings tab content.
	 */
	public function render_settings_tab() {
		$url  = $this->get_support_site_url();
		$user = $this->get_api_username();
		$pass = $this->get_api_password();

		?>
		<div class="mainwp-padd-cont" style="padding-top: 50px; padding-right: 20px;">
			<?php if ( isset( $_GET['message'] ) && 'settings_saved' === $_GET['message'] ) : ?>
				<div class="mainwp-notice mainwp-notice-green"><?php esc_html_e( 'Settings saved successfully!', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></div>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Support Site Configuration', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></h3>
			<p><?php esc_html_e( 'Enter the URL and credentials for the site hosting FluentSupport. This extension communicates directly via the WordPress REST API.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'mainwp_fluentsupport_settings_save', 'mainwp_fluentsupport_settings_save_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="fluentsupport_site_url"><?php esc_html_e( 'Support Site URL', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label></th>
						<td>
							<input type="url" id="fluentsupport_site_url" name="fluentsupport_site_url" class="regular-text" value="<?php echo esc_attr( $url ); ?>" placeholder="https://your-support-site.com" required />
							<p class="description"><?php esc_html_e( 'The base URL for the site hosting FluentSupport.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="fluentsupport_api_username"><?php esc_html_e( 'API Username', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label></th>
						<td>
							<input type="text" id="fluentsupport_api_username" name="fluentsupport_api_username" class="regular-text" value="<?php echo esc_attr( $user ); ?>" required />
							<p class="description"><?php esc_html_e( 'The WordPress username that generated the Application Password.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="fluentsupport_api_password"><?php esc_html_e( 'Application Password', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label></th>
						<td>
							<input type="password" id="fluentsupport_api_password" name="fluentsupport_api_password" class="regular-text" value="<?php echo esc_attr( $pass ); ?>" required />
							<p class="description"><?php esc_html_e( 'The Application Password (must have permissions to access FluentSupport).', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the Overview tab content.
	 */
	public function render_overview_tab() {
		$db_results = MainWP_FluentSupport_Utility::api_get_tickets_from_db();
		$html       = '<tr><td colspan="4">' . esc_html__( 'No ticket updates currently stored. Synchronization occurs automatically in the background.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) . '</td></tr>';

		if ( ! empty( $db_results['tickets'] ) ) {
			$html = '';
			foreach ( $db_results['tickets'] as $ticket ) {
				$html .= '<tr>
					<td>' . esc_html( $ticket['client_site_name'] ) . '</td>
					<td><a href="' . esc_url( $ticket['ticket_url'] ) . '" target="_blank">' . esc_html( $ticket['title'] ) . '</a></td>
					<td>' . esc_html( $ticket['status'] ) . '</td>
					<td>' . esc_html( $ticket['updated_at'] ) . '</td>
				</tr>';
			}
		}

		?>
		<div class="mainwp-padd-cont" style="padding-top: 50px; padding-right: 20px;">
			<div class="ui grid">
				<div class="sixteen wide column right aligned">
					<?php 
					$last_sync = get_option( 'mainwp_fluentsupport_last_sync', 0 );
					if ( $last_sync > 0 ) : 
						$time_diff = human_time_diff( $last_sync, current_time( 'timestamp' ) );
					?>
						<p style="color: #666; font-style: italic; margin-bottom: 20px;">
							<i class="history icon"></i> 
							<?php
							/* translators: %s: Human readable time difference (e.g., 5 minutes) */
							printf( esc_html__( 'Background Sync Status: Last updated %s ago', 'ticket-status-sync-for-fluentsupport-to-mainwp' ), esc_html( $time_diff ) );
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<table class="ui stackable table mainwp-favorites-table dataTable unstackable">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Client Site', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></th>
						<th><?php esc_html_e( 'Ticket Title', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></th>
						<th><?php esc_html_e( 'Last Update', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></th>
					</tr>
				</thead>
				<tbody id="fluentsupport-ticket-data"><?php echo wp_kses_post( $html ); ?></tbody>
			</table>
		</div>
		<?php
	}
}
