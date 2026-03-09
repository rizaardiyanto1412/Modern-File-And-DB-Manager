<?php
/**
 * Uninstall Modern File Manager.
 *
 * @package ModernFileManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mfm_version' );
delete_option( 'mfm_db_manager_enabled' );
delete_option( 'mfm_db_read_only' );
delete_option( 'mfm_db_launch_ttl_seconds' );
