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
	 * DB manager admin page service.
	 *
	 * @var DB_Admin_Page
	 */
	private $db_admin_page;

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
		$filesystem            = new Filesystem_Service();
		$db_manager            = new DB_Manager_Service();
		$this->admin_page      = new Admin_Page();
		$this->rest_controller = new Rest_Controller( $filesystem );
		$this->db_admin_page   = new DB_Admin_Page( $db_manager );

		$this->admin_page->init();
		$this->rest_controller->init();
		$this->db_admin_page->init();
	}
}
