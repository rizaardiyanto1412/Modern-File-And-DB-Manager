<?php
/**
 * DB manager admin page and launcher.
 *
 * @package ModernFileManager
 */

namespace ModernFileManager;

defined( 'ABSPATH' ) || exit;

/**
 * DB manager admin page.
 */
class DB_Admin_Page {
	/**
	 * Menu hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * DB manager service.
	 *
	 * @var DB_Manager_Service
	 */
	private $db_service;

	/**
	 * Constructor.
	 *
	 * @param DB_Manager_Service $db_service DB service.
	 */
	public function __construct( DB_Manager_Service $db_service ) {
		$this->db_service = $db_service;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_mfm_db_manager_launch', array( $this, 'handle_db_launch' ) );
	}

	/**
	 * Register DB manager submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_submenu_page(
			'modern-file-db-manager',
			esc_html__( 'DB Manager', 'modern-file-db-manager' ),
			esc_html__( 'DB Manager', 'modern-file-db-manager' ),
			'manage_options',
			'modern-file-manager-db',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render DB manager page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access DB Manager.', 'modern-file-db-manager' ) );
		}

		$db_info      = $this->db_service->get_db_info();
		$environment  = $this->db_service->detect_environment();
		$launch_url   = $this->db_service->generate_launch_url();
		$is_enabled = $this->db_service->is_enabled();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'DB Manager', 'modern-file-db-manager' ); ?></h1>
			<div style="max-width:980px;background:#fff;border:1px solid #d0d7e2;border-radius:10px;padding:16px 18px;margin:14px 0;">
				<p><strong><?php echo esc_html__( 'Warning:', 'modern-file-db-manager' ); ?></strong> <?php echo esc_html__( 'Database operations can permanently change or delete data. Use carefully.', 'modern-file-db-manager' ); ?></p>
				<p>
					<?php echo esc_html__( 'Environment:', 'modern-file-db-manager' ); ?> <strong><?php echo esc_html( $environment ); ?></strong>
					&nbsp;|&nbsp;
					<?php echo esc_html__( 'Database:', 'modern-file-db-manager' ); ?> <strong><?php echo esc_html( $db_info['name'] ); ?></strong>
					&nbsp;|&nbsp;
					<?php echo esc_html__( 'Host:', 'modern-file-db-manager' ); ?> <strong><?php echo esc_html( $db_info['host'] ); ?></strong>
					&nbsp;|&nbsp;
					<?php echo esc_html__( 'User:', 'modern-file-db-manager' ); ?> <strong><?php echo esc_html( $db_info['user'] ); ?></strong>
				</p>

				<?php if ( $is_enabled ) : ?>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( $launch_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open Adminer', 'modern-file-db-manager' ); ?></a>
					</p>
					<p class="description"><?php echo esc_html__( 'Adminer opens in a new tab.', 'modern-file-db-manager' ); ?></p>
				<?php else : ?>
					<p><em><?php echo esc_html__( 'DB Manager is currently disabled.', 'modern-file-db-manager' ); ?></em></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle DB manager launch endpoint.
	 *
	 * @return void
	 */
	public function handle_db_launch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->db_service->log_event( 'launch blocked: insufficient capability' );
			wp_die( esc_html__( 'You do not have permission to access DB Manager.', 'modern-file-db-manager' ), 403 );
		}

		if ( ! $this->db_service->is_enabled() ) {
			$this->db_service->log_event( 'launch blocked: module disabled' );
			wp_die( esc_html__( 'DB Manager is disabled.', 'modern-file-db-manager' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token verification is handled by DB_Manager_Service::validate_launch_request() when signed params are present.
		$request = wp_unslash( $_GET );
		if ( ! is_array( $request ) ) {
			wp_die( esc_html__( 'Invalid DB Manager request.', 'modern-file-db-manager' ), 403 );
		}

		$has_signed_params = isset( $request['ts'] ) || isset( $request['token'] ) || isset( $request['sig'] );
		$has_adminer_file  = isset( $request['file'] ) || isset( $request['script'] );
		$has_adminer_state = isset( $request['username'] ) || isset( $request['db'] ) || isset( $request['server'] );

		// Validate signature only on the initial launch handoff.
		if ( $has_signed_params && ! $has_adminer_file && ! $has_adminer_state && ! $this->db_service->validate_launch_request( $request ) ) {
			wp_die( esc_html__( 'Invalid or expired DB Manager launch token.', 'modern-file-db-manager' ), 403 );
		}

		require_once MFM_PLUGIN_DIR . 'includes/db/adminer-loader.php';
		exit;
	}
}
