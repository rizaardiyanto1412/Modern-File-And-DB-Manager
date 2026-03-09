<?php
/**
 * Adminer integration bridge.
 *
 * @package ModernFileManager
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'adminer_object' ) ) {
	/**
	 * Return custom Adminer object.
	 *
	 * @return \Adminer\Adminer
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Adminer requires this global function name.
	function adminer_object() {
		return new class() extends \Adminer\Adminer {
			/**
			 * DB credentials from WP config.
			 *
			 * @return array
			 */
			public function credentials() {
				$host = defined( 'DB_HOST' ) ? DB_HOST : '';
				$user = defined( 'DB_USER' ) ? DB_USER : '';
				$pass = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
				return array( $host, $user, $pass );
			}

			/**
			 * Restrict to current WordPress DB.
			 *
			 * @return string
			 */
			public function database() {
				return defined( 'DB_NAME' ) ? DB_NAME : '';
			}

			/**
			 * App name.
			 *
			 * @return string
			 */
			public function name() {
				return 'Adminer';
			}

			/**
			 * Optional read-only mode.
			 *
			 * @return bool
			 */
			public function readonly() {
				return (bool) get_option( \ModernFileManager\DB_Manager_Service::OPTION_READ_ONLY, false );
			}
		};
	}
}
