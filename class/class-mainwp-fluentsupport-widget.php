<?php
/**
 * MainWP FluentSupport Widget
 *
 * This class handles the Widget process.
 *
 * @package MainWP/Extensions
 */

 namespace MainWP\Extensions\FluentSupport;

 /**
  * Class MainWP_FluentSupport_Widget
  *
  * @package MainWP/Extensions
  */
class MainWP_FluentSupport_Widget {

	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// construct.
	}


	/**
	 * Render Metabox
	 *
	 * Initiates the correct widget depending on which page the user lands on.
	 */
	public function render_metabox() {
        $this->render_widget_content();
	}

	/**
	 * Renders the widget content.
	 */
	public function render_widget_content() {
        
        // 1. Detect if we are on a single site dashboard and get the ID
        $current_site_id = isset( $_GET['dashboard'] ) ? intval( $_GET['dashboard'] ) : 0;
        
        // PULL SETTINGS DIRECTLY FROM DB (Only need URL for the dynamic link)
        $support_site_url = rtrim( get_option( 'mainwp_fluentsupport_site_url', '', false ), '/' );
        
        // 2. Configuration check
        if ( empty( $support_site_url ) ) {
            ?>
            <div class="mainwp-widget-header">
                <div class="ui grid">
                    <div class="twelve wide column">
                        <h2 class="ui header handle-drag">
                            <?php esc_html_e( 'FluentSupport Tickets Summary', 'mainwp-fluentsupport' ); ?>
                            <div class="sub header"><?php esc_html_e( 'Configuration Required', 'mainwp-fluentsupport' ); ?></div>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="ui hidden divider"></div>
            <div class="ui fluid message red">
                <div class="ui icon header">
                    <i class="exclamation triangle icon"></i>
                    <?php esc_html_e( 'Support Site URL is missing. Please configure your Support Site settings in the Settings tab to enable the sync feature and link the View All Tickets button.', 'mainwp-fluentsupport' ); ?>
                </div>
            </div>
            <div class="ui hidden divider"></div>
            <div class="ui divider" style="margin-left:-1em;margin-right:-1em;"></div>
            <div class="ui two columns grid">
                <div class="left aligned column">
                    <a href="admin.php?page=Extensions-Mainwp-FluentSupport&tab=settings" class="ui basic red button"><?php esc_html_e( 'Go to Settings', 'mainwp-fluentsupport' ); ?></a>
                </div>
            </div>
            <?php
            return; // EXIT if settings are missing
        }
        
        // Construct the dynamic ticket URL
        $support_site_tickets_url = rtrim( $support_site_url, '/' ) . '/wp-admin/admin.php?page=fluent-support#/tickets'; 
        
        // 3. Prepare filters for the utility functions
        $filters = array(
            'status' => array( 'new', 'active' ),
            'limit'  => $current_site_id > 0 ? 10 : 5, // Show more tickets for single site view
            'site_id' => $current_site_id, // Pass the site ID
        );
        
        $db_results = MainWP_FluentSupport_Utility::api_get_tickets_from_db( $filters );
        $tickets = $db_results['success'] && ! empty( $db_results['tickets'] ) ? $db_results['tickets'] : array();
        
        // Count all new/active tickets for the header.
        $all_active_count = MainWP_FluentSupport_Utility::get_ticket_count( array( 'new', 'active' ), $current_site_id );

        // 4. Determine the header title based on context
        if ( $current_site_id > 0 ) {
            // Fetch site name for a better title
            $site_info = MainWP_FluentSupport_Utility::get_websites( $current_site_id );
            $site_name = ! empty( $site_info ) ? $site_info[0]['name'] : 'Current Site';
            $title = sprintf( esc_html__( 'Tickets for %s', 'mainwp-fluentsupport' ), esc_html( $site_name ) );
            $subtitle = sprintf( esc_html__( 'Displaying %d active tickets (total active: %d).', 'mainwp-fluentsupport' ), count( $tickets ), $all_active_count );
        } else {
            $title = esc_html__( 'FluentSupport Tickets Summary', 'mainwp-fluentsupport' );
            $subtitle = sprintf( esc_html__( 'Displaying %d active tickets across all sites (total active: %d).', 'mainwp-fluentsupport' ), count( $tickets ), $all_active_count );
        }

        ?>
		<div class="mainwp-widget-header">
            <div class="ui grid">
                <div class="twelve wide column">
                    <h2 class="ui header handle-drag">
                        <?php echo $title; ?>
                        <div class="sub header"><?php echo $subtitle; ?></div>
                    </h2>
                </div>
                <div class="four wide column right aligned">
                    <?php 
                    // Add the Sync Button to the header
                    // This button uses the AJAX function already set up in mainwp-fluentsupport.js to trigger a sync.
                    $button_text = esc_html__( 'Sync Now', 'mainwp-fluentsupport' );
                    ?>
                    <button id="mainwp-fluentsupport-fetch-btn" class="ui button mini green" style="margin-top: 5px;">
                        <i class="sync alternate icon"></i>
                        <?php echo $button_text; ?>
                    </button>
                </div>
            </div>
		</div>
		<div class="ui hidden divider"></div>
        <?php if ( empty( $tickets ) ) : ?>
            <div class="ui fluid placeholder">
                <div class="ui icon header">
                    <i class="check circle outline icon"></i>
                    <?php esc_html_e( 'No new or active tickets found in local cache for this view. Click "Sync Now" above or "Fetch Latest Tickets" on the extension page to sync.', 'mainwp-fluentsupport' ); ?>
                </div>
            </div>
        <?php else : ?>
            <table class="ui stackable table mainwp-favorites-table dataTable unstackable">
                <thead>
                    <tr>
                        <?php if ( $current_site_id === 0 ) : // Only show the Client Site column on the main dashboard ?>
                            <th width="30%"><?php esc_html_e( 'Client Site', 'mainwp-fluentsupport' ); ?></th>
                            <th width="45%"><?php esc_html_e( 'Ticket Title', 'mainwp-fluentsupport' ); ?></th>
                            <th width="15%"><?php esc_html_e( 'Status', 'mainwp-fluentsupport' ); ?></th>
                            <th width="10%"><?php esc_html_e( 'Updated', 'mainwp-fluentsupport' ); ?></th>
                        <?php else : // On single site dashboard, hide the Client Site column ?>
                            <th width="60%"><?php esc_html_e( 'Ticket Title', 'mainwp-fluentsupport' ); ?></th>
                            <th width="20%"><?php esc_html_e( 'Status', 'mainwp-fluentsupport' ); ?></th>
                            <th width="20%"><?php esc_html_e( 'Updated', 'mainwp-fluentsupport' ); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tickets as $ticket ) : ?>
                    <tr>
                        <?php if ( $current_site_id === 0 ) : ?>
                            <td>
                                <?php echo $ticket['client_site_name']; // Site name is already escaped and linked to dashboard or URL ?>
                                <?php 
                                if ( $ticket['client_site_id'] > 0 ) :
                                    // Using the required 'mainwp-admin-nonce' key for site open action
                                    $nonce = wp_create_nonce( 'mainwp-admin-nonce' ); 
                                    $login_url = admin_url( 'admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $ticket['client_site_id'] . '&_opennonce=' . $nonce );
                                ?>
                                    <a href="<?php echo esc_url( $login_url ); ?>" target="_blank"><i class="sign in alternate icon"></i></a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td><a href="<?php echo esc_url( $ticket['ticket_url'] ); ?>" target="_blank"><?php echo esc_html( $ticket['title'] ); ?></a></td>
                        <td><?php echo esc_html( $ticket['status'] ); ?></td>
                        <td><?php echo esc_html( date( 'M j', strtotime( $ticket['updated_at'] ) ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
		<div class="ui hidden divider"></div>
		<div class="ui divider" style="margin-left:-1em;margin-right:-1em;"></div>
		<div class="ui two columns grid">
			<div class="left aligned column">
				<a href="<?php echo esc_url( $support_site_tickets_url ); ?>" class="ui basic green button" target="_blank"><?php esc_html_e( 'View All Tickets', 'mainwp-fluentsupport' ); ?></a>
			</div>
		</div>
		<?php
	}
}
