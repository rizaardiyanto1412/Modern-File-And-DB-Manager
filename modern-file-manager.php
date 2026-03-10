<?php
/**
 * Plugin Name: Modern File & DB Manager
 * Description: Secure and modern file manager for WordPress admins.
 * Version: 1.0.0
 * Author: Camyt Group
 * Author URI: https://camytgroup.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: modern-file-manager
 * Domain Path: /languages
 *
 * @package ModernFileManager
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MFM_VERSION' ) ) {
	define( 'MFM_VERSION', '1.0.0' );
}

if ( ! defined( 'MFM_PLUGIN_FILE' ) ) {
	define( 'MFM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MFM_PLUGIN_DIR' ) ) {
	define( 'MFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MFM_PLUGIN_URL' ) ) {
	define( 'MFM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once MFM_PLUGIN_DIR . 'includes/class-plugin.php';
require_once MFM_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once MFM_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once MFM_PLUGIN_DIR . 'includes/class-filesystem-service.php';
require_once MFM_PLUGIN_DIR . 'includes/db/class-db-manager-service.php';
require_once MFM_PLUGIN_DIR . 'includes/db/class-db-admin-page.php';

register_activation_hook( __FILE__, array( 'ModernFileManager\\Plugin', 'activate' ) );

ModernFileManager\Plugin::instance()->init();
