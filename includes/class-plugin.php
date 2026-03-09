<?php
/**
 * Main plugin orchestration.
 *
 * @package ModernFileManager
 */

namespace ModernFileManager;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin class.
 */
class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin page service.
	 *
	 * @var Admin_Page
	 */
	private $admin_page;

	/**
	 * REST controller service.
	 *
	 * @var Rest_Controller
	 */
	private $rest_controller;

	/**
	 * Get singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( 'mfm_version', MFM_VERSION, false );
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$filesystem            = new Filesystem_Service();
		$this->admin_page      = new Admin_Page();
		$this->rest_controller = new Rest_Controller( $filesystem );

		$this->admin_page->init();
		$this->rest_controller->init();
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'modern-file-manager', false, dirname( plugin_basename( MFM_PLUGIN_FILE ) ) . '/languages' );
	}
}
