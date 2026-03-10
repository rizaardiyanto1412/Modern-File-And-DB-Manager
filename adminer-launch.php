<?php
/**
 * Direct Adminer launcher endpoint.
 *
 * @package ModernFileManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	$modern_file_manager_wp_load = dirname( __FILE__, 4 ) . '/wp-load.php';
	if ( file_exists( $modern_file_manager_wp_load ) ) {
		require_once $modern_file_manager_wp_load;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	http_response_code( 403 );
	exit;
}

if ( ! defined( 'MFM_PLUGIN_DIR' ) ) {
	define( 'MFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

require_once MFM_PLUGIN_DIR . 'includes/db/class-db-manager-service.php';

$modern_file_manager_db_service = new \ModernFileManager\DB_Manager_Service();

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access DB Manager.', 'modern-file-db-manager' ), 403 );
}

if ( ! $modern_file_manager_db_service->is_enabled() ) {
	wp_die( esc_html__( 'DB Manager is disabled.', 'modern-file-db-manager' ), 403 );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token verification is handled below for signed initial launches.
$modern_file_manager_request = wp_unslash( $_GET );
if ( ! is_array( $modern_file_manager_request ) ) {
	wp_die( esc_html__( 'Invalid DB Manager request.', 'modern-file-db-manager' ), 403 );
}

$modern_file_manager_has_signed_params = isset( $modern_file_manager_request['ts'] ) || isset( $modern_file_manager_request['token'] ) || isset( $modern_file_manager_request['sig'] );
$modern_file_manager_has_adminer_file  = isset( $modern_file_manager_request['file'] ) || isset( $modern_file_manager_request['script'] );
$modern_file_manager_has_adminer_state = isset( $modern_file_manager_request['username'] ) || isset( $modern_file_manager_request['db'] ) || isset( $modern_file_manager_request['server'] );

if ( $modern_file_manager_has_signed_params && ! $modern_file_manager_has_adminer_file && ! $modern_file_manager_has_adminer_state && ! $modern_file_manager_db_service->validate_launch_request( $modern_file_manager_request ) ) {
	wp_die( esc_html__( 'Invalid or expired DB Manager launch token.', 'modern-file-db-manager' ), 403 );
}

require_once MFM_PLUGIN_DIR . 'includes/db/adminer-loader.php';
exit;
