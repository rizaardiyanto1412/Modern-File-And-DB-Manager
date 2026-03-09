<?php
/**
 * Admin page for file manager app.
 *
 * @package ModernFileManager
 */

namespace ModernFileManager;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page class.
 */
class Admin_Page {
	/**
	 * Hook suffix for plugin page.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			esc_html__( 'Modern File Manager', 'modern-file-manager' ),
			esc_html__( 'File Manager', 'modern-file-manager' ),
			'manage_options',
			'modern-file-manager',
			array( $this, 'render_page' ),
			'dashicons-portfolio',
			59
		);
	}

	/**
	 * Render admin app root.
	 *
	 * @return void
	 */
	public function render_page() {
		echo '<div class="wrap mfm-page-wrap">';
		echo '<h1 class="mfm-title">' . esc_html__( 'Modern File Manager', 'modern-file-manager' ) . '</h1>';
		echo '<div id="mfm-app" aria-live="polite"></div>';
		echo '</div>';
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'mfm-admin-style',
			MFM_PLUGIN_URL . 'assets/css/admin-app.css',
			array(),
			MFM_VERSION
		);

		wp_enqueue_script(
			'mfm-codemirror-bundle',
			MFM_PLUGIN_URL . 'assets/js/codemirror-bundle.js',
			array(),
			MFM_VERSION,
			true
		);

		wp_enqueue_script(
			'mfm-admin-script',
			MFM_PLUGIN_URL . 'assets/js/admin-app.js',
			array( 'wp-element', 'wp-i18n', 'mfm-codemirror-bundle' ),
			MFM_VERSION,
			true
		);

		wp_set_script_translations( 'mfm-admin-script', 'modern-file-manager', MFM_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			'mfm-admin-script',
			'mfmConfig',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'modern-file-manager/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'initialPath'  => '/',
				'pluginTitle'  => esc_html__( 'Modern File Manager', 'modern-file-manager' ),
				'capability'   => 'manage_options',
				'maxUploadMib' => (int) wp_max_upload_size() / ( 1024 * 1024 ),
			)
		);
	}
}
