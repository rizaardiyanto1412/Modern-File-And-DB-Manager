<?php
/**
 * Uninstall Modern File Manager.
 *
 * @package ModernFileManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mfm_version' );
