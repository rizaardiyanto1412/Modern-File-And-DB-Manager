<?php
/**
 * DB manager service.
 *
 * @package ModernFileManager
 */

namespace ModernFileManager;

defined( 'ABSPATH' ) || exit;

/**
 * DB manager service class.
 */
class DB_Manager_Service {
	const OPTION_ENABLED = 'mfm_db_manager_enabled';
	const OPTION_READ_ONLY = 'mfm_db_read_only';
	const OPTION_LAUNCH_TTL = 'mfm_db_launch_ttl_seconds';

	/**
	 * Check whether DB manager is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$enabled = get_option( self::OPTION_ENABLED, true );
		return (bool) $enabled;
	}

	/**
	 * Check whether DB manager should be read-only.
	 *
	 * @return bool
	 */
	public function is_read_only() {
		$read_only = get_option( self::OPTION_READ_ONLY, false );
		return (bool) $read_only;
	}

	/**
	 * Get launch token time-to-live.
	 *
	 * @return int
	 */
	public function get_launch_ttl() {
		$ttl = (int) get_option( self::OPTION_LAUNCH_TTL, 300 );
		if ( $ttl < 60 ) {
			return 60;
		}
		if ( $ttl > 3600 ) {
			return 3600;
		}
		return $ttl;
	}

	/**
	 * Build signed launch URL.
	 *
	 * @return string
	 */
	public function generate_launch_url() {
		$user_id   = (int) get_current_user_id();
		$timestamp = time();
		$action    = $this->build_nonce_action( $user_id, $timestamp );
		$nonce     = wp_create_nonce( $action );
		$sig       = $this->build_signature( $user_id, $timestamp, $nonce );
		$base_url  = defined( 'MFM_PLUGIN_URL' ) ? MFM_PLUGIN_URL . 'adminer-launch.php' : admin_url( 'adminer-launch.php' );

		$url = add_query_arg(
			array(
				'ts'     => $timestamp,
				'token'  => $nonce,
				'sig'    => $sig,
			),
			$base_url
		);

		return (string) $url;
	}

	/**
	 * Validate launch request.
	 *
	 * @param array $request Request array.
	 * @return bool
	 */
	public function validate_launch_request( array $request ) {
		$user_id = (int) get_current_user_id();
		if ( $user_id < 1 ) {
			$this->log_event( 'launch denied: no user' );
			return false;
		}

		$timestamp = isset( $request['ts'] ) ? (int) $request['ts'] : 0;
		$token     = isset( $request['token'] ) ? sanitize_text_field( (string) $request['token'] ) : '';
		$sig       = isset( $request['sig'] ) ? sanitize_text_field( (string) $request['sig'] ) : '';

		if ( $timestamp < 1 || '' === $token || '' === $sig ) {
			$this->log_event( 'launch denied: malformed params' );
			return false;
		}

		if ( abs( time() - $timestamp ) > $this->get_launch_ttl() ) {
			$this->log_event( 'launch denied: token expired' );
			return false;
		}

		$expected_sig = $this->build_signature( $user_id, $timestamp, $token );
		if ( ! hash_equals( $expected_sig, $sig ) ) {
			$this->log_event( 'launch denied: invalid signature' );
			return false;
		}

		$action = $this->build_nonce_action( $user_id, $timestamp );
		if ( ! wp_verify_nonce( $token, $action ) ) {
			$this->log_event( 'launch denied: invalid nonce' );
			return false;
		}

		$this->log_event( 'launch accepted' );
		return true;
	}

	/**
	 * Build DB info for UI.
	 *
	 * @return array
	 */
	public function get_db_info() {
		$host = defined( 'DB_HOST' ) ? (string) DB_HOST : '';
		$name = defined( 'DB_NAME' ) ? (string) DB_NAME : '';
		$user = defined( 'DB_USER' ) ? (string) DB_USER : '';

		return array(
			'host' => $this->mask_host( $host ),
			'name' => $name,
			'user' => $this->mask_user( $user ),
		);
	}

	/**
	 * Get environment indicator.
	 *
	 * @return string
	 */
	public function detect_environment() {
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return (string) wp_get_environment_type();
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';
		if ( false !== strpos( $host, 'localhost' ) || false !== strpos( $host, '.test' ) || false !== strpos( $host, '.local' ) || false !== strpos( $host, 'dev.' ) ) {
			return 'local';
		}
		if ( false !== strpos( $host, 'staging' ) || false !== strpos( $host, 'stage.' ) ) {
			return 'staging';
		}
		return 'production';
	}

	/**
	 * Sanitize enabled option.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_enabled( $value ) {
		return (bool) $value;
	}

	/**
	 * Sanitize read only option.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_read_only( $value ) {
		return (bool) $value;
	}

	/**
	 * Sanitize launch ttl option.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_launch_ttl( $value ) {
		$ttl = (int) $value;
		if ( $ttl < 60 ) {
			$ttl = 60;
		}
		if ( $ttl > 3600 ) {
			$ttl = 3600;
		}
		return $ttl;
	}

	/**
	 * Build nonce action.
	 *
	 * @param int $user_id User ID.
	 * @param int $timestamp Token timestamp.
	 * @return string
	 */
	private function build_nonce_action( $user_id, $timestamp ) {
		return 'mfm_db_launch|' . (int) $user_id . '|' . (int) $timestamp;
	}

	/**
	 * Build HMAC signature.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $timestamp Token timestamp.
	 * @param string $token Nonce token.
	 * @return string
	 */
	private function build_signature( $user_id, $timestamp, $token ) {
		$payload = (int) $user_id . '|' . (int) $timestamp . '|' . (string) $token;
		$key     = wp_salt( 'auth' );
		return hash_hmac( 'sha256', $payload, $key );
	}

	/**
	 * Mask host for UI.
	 *
	 * @param string $host Raw host.
	 * @return string
	 */
	private function mask_host( $host ) {
		$host = trim( (string) $host );
		if ( '' === $host ) {
			return '';
		}
		$parts = explode( ':', $host );
		if ( count( $parts ) > 1 ) {
			return $parts[0] . ':****';
		}
		return $host;
	}

	/**
	 * Mask user for UI.
	 *
	 * @param string $user Raw DB user.
	 * @return string
	 */
	private function mask_user( $user ) {
		$user = trim( (string) $user );
		if ( strlen( $user ) < 3 ) {
			return '***';
		}
		return substr( $user, 0, 2 ) . str_repeat( '*', max( 1, strlen( $user ) - 2 ) );
	}

	/**
	 * Log DB manager events while debugging.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function log_event( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only runs in debug mode for security auditing.
			error_log( '[MFM DB] ' . sanitize_text_field( (string) $message ) );
		}
	}
}
