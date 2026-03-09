<?php
/**
 * Secure Adminer bootstrap.
 *
 * @package ModernFileManager
 */

namespace ModernFileManager;

defined( 'ABSPATH' ) || exit;

require_once MFM_PLUGIN_DIR . 'includes/db/adminer-bridge.php';
define( 'MODERN_FILE_MANAGER_DB_MANAGER_ADMINER_ALLOWED', true );

// phpcs:ignore WordPress.Security.NonceVerification.Missing -- sets internal bootstrap auth payload, not processing user-submitted form input.
$modern_file_manager_has_auth_post = isset( $_POST['auth'] ) && is_array( $_POST['auth'] );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check to prevent repeated auto-login bootstrap.
$modern_file_manager_username = isset( $_GET['username'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['username'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check to prevent repeated auto-login bootstrap.
$modern_file_manager_db_name = isset( $_GET['db'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['db'] ) ) : '';
if ( ! $modern_file_manager_has_auth_post && '' === $modern_file_manager_username && '' === $modern_file_manager_db_name ) {
	$_POST['auth'] = array(
		'driver'    => 'server',
		'server'    => defined( 'DB_HOST' ) ? (string) DB_HOST : '',
		'username'  => defined( 'DB_USER' ) ? (string) DB_USER : '',
		'password'  => defined( 'DB_PASSWORD' ) ? (string) DB_PASSWORD : '',
		'db'        => defined( 'DB_NAME' ) ? (string) DB_NAME : '',
		'permanent' => 0,
	);
}

// Adminer controls output buffering itself; prevent WP shutdown buffer flushing warnings for this response.
remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );

require_once MFM_PLUGIN_DIR . 'includes/vendor/adminer/adminer.php';
