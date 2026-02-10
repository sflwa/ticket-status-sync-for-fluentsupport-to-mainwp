<?php
/**
 * MainWP FluentSupport Admin
 *
 * @package MainWP\Extensions\FluentSupport
 */

namespace MainWP\Extensions\FluentSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MainWP_FluentSupport_Admin {

	public static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		MainWP_FluentSupport_DB::get_instance()->install();
		add_action( 'admin_init', array( $this, 'process_settings_save' ) );
		// Register the AJAX handler for the "Sync Now" button
		add_action( 'wp_ajax_mainwp_fluentsupport_fetch_tickets', array( $this, 'ajax_fetch_tickets' ) );
	}

	/**
	 * AJAX Handler to manually trigger sync and return updated table HTML.
	 */
	public function ajax_fetch_tickets() {
		check_ajax_referer( 'ticket-status-sync-for-fluentsupport-to-mainwp-nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) ) );
		}

		$url  = rtrim( get_option( 'mainwp_fluentsupport_site_url', '' ), '/' );
		$user = get_option( 'mainwp_fluentsupport_api_username', '' );
		$pass = get_option( 'mainwp_fluentsupport_api_password', '' );

		if ( empty( $url ) || empty( $user ) || empty( $pass ) ) {
			wp_send_json_error( array( 'message' => __( 'Configuration missing. Check settings.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) ) );
		}

		$sync_result = MainWP_FluentSupport_Utility::api_sync_tickets( $url, $user, $pass );

		if ( $sync_result['success'] ) {
			// Regenerate the table HTML for the UI update
			$db_results = MainWP_FluentSupport_Utility::api_get_tickets_from_db();
			$html = '';
			if ( ! empty( $db_results['tickets'] ) ) {
				foreach ( $db_results['tickets'] as $ticket ) {
					$html .= '<tr>
						<td>' . wp_kses_post( $ticket['client_site_name'] ) . '</td>
						<td><a href="' . esc_url( $ticket['ticket_url'] ) . '" target="_blank">' . esc_html( $ticket['title'] ) . '</a></td>
						<td>' . esc_html( $ticket['status'] ) . '</td>
						<td>' . esc_html( $ticket['updated_at'] ) . '</td>
					</tr>';
				}
			} else {
				$html = '<tr><td colspan="4">' . esc_html__( 'No tickets found.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) . '</td></tr>';
			}

			wp_send_json_success( array(
				'message' => sprintf( __( 'Successfully synced %d tickets.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ), $sync_result['synced'] ),
				'html'    => $html
			) );
		} else {
			wp_send_json_error( array( 'message' => $sync_result['error'] ) );
		}
	}

	public function process_settings_save() {
		if ( ! isset( $_POST['mainwp_fluentsupport_settings_save_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mainwp_fluentsupport_settings_save_nonce'] ) ), 'mainwp_fluentsupport_settings_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) );
		}
		update_option( 'mainwp_fluentsupport_site_url', sanitize_url( wp_unslash( $_POST['fluentsupport_site_url'] ) ), false );
		update_option( 'mainwp_fluentsupport_api_username', sanitize_text_field( wp_unslash( $_POST['fluentsupport_api_username'] ) ), false );
		update_option( 'mainwp_fluentsupport_api_password', sanitize_text_field( wp_unslash( $_POST['fluentsupport_api_password'] ) ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . ( isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'Extensions-Mainwp-FluentSupport' ) . '&tab=settings&message=settings_saved' ) );
		exit;
	}

	public function render_settings_tab() {
		$url  = get_option( 'mainwp_fluentsupport_site_url', '' );
		$user = get_option( 'mainwp_fluentsupport_api_username', '' );
		$pass = get_option( 'mainwp_fluentsupport_api_password', '' );
		?>
		<div class="mainwp-padd-cont" style="padding-top: 50px; padding-right: 20px;">
			<?php if ( isset( $_GET['message'] ) && 'settings_saved' === $_GET['message'] ) : ?>
				<div class="mainwp-notice mainwp-notice-green"><?php esc_html_e( 'Settings saved successfully!', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></div>
			<?php endif; ?>
			<h3><?php esc_html_e( 'Support Site Configuration', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></h3>
			<form method="post" action="">
				<?php wp_nonce_field( 'mainwp_fluentsupport_settings_save', 'mainwp_fluentsupport_settings_save_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="fluentsupport_site_url"><?php esc_html_e( 'Support Site URL', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label></th>
						<td><input type="url" id="fluentsupport_site_url" name="fluentsupport_site_url" class="regular-text" value="<?php echo esc_attr( $url ); ?>" required /></td>
					</tr>
					<tr>
						<th><label for="fluentsupport_api_username"><?php esc_html_e( 'API Username', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label></th>
						<td><input type="text" id="fluentsupport_api_username" name="fluentsupport_api_username" class="regular-text" value="<?php echo esc_attr( $user ); ?>" required /></td>
					</tr>
					<tr>
						<th><label for="fluentsupport_api_password"><?php esc_html_e( 'Application Password', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></label></th>
						<td><input type="password" id="fluentsupport_api_password" name="fluentsupport_api_password" class="regular-text" value="<?php echo esc_attr( $pass ); ?>" required /></td>
					</tr>
				</table>
				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	public function render_overview_tab() {
		$db_results = MainWP_FluentSupport_Utility::api_get_tickets_from_db();
		$html = '<tr><td colspan="4">' . esc_html__( 'No ticket updates stored.', 'ticket-status-sync-for-fluentsupport-to-mainwp' ) . '</td></tr>';
		if ( ! empty( $db_results['tickets'] ) ) {
			$html = '';
			foreach ( $db_results['tickets'] as $ticket ) {
				$html .= '<tr>
					<td>' . wp_kses_post( $ticket['client_site_name'] ) . '</td>
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
							<?php printf( esc_html__( 'Background Sync Status: Last updated %s ago', 'ticket-status-sync-for-fluentsupport-to-mainwp' ), esc_html( $time_diff ) ); ?>
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
