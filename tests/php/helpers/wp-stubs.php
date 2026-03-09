<?php
/**
 * Lightweight WordPress test stubs for standalone PHPUnit runs.
 */

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $params = array();
		private $headers = array();
		private $files = array();

		public function __construct( $method = 'GET', $route = '' ) {
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_param( $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}

		public function set_header( $key, $value ) {
			$this->headers[ strtolower( (string) $key ) ] = $value;
		}

		public function get_header( $key ) {
			$key = strtolower( (string) $key );
			return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : null;
		}

		public function set_file_params( array $files ) {
			$this->files = $files;
		}

		public function get_file_params() {
			return $this->files;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private $status;

		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = (int) $status;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status() {
			return $this->status;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );
		return $path;
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		$filename = preg_replace( '/[^A-Za-z0-9\._-]/', '-', (string) $filename );
		$filename = trim( $filename, '.-' );
		return $filename;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return isset( $GLOBALS['mfm_test_options'][ $name ] ) ? $GLOBALS['mfm_test_options'][ $name ] : $default;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, $url ) {
		$separator = strpos( (string) $url, '?' ) === false ? '?' : '&';
		return (string) $url . $separator . http_build_query( $args );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return isset( $GLOBALS['mfm_test_current_user_id'] ) ? (int) $GLOBALS['mfm_test_current_user_id'] : 1;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'nonce-' . md5( (string) $action );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return isset( $GLOBALS['mfm_test_current_user_can'] ) ? (bool) $GLOBALS['mfm_test_current_user_can'] : true;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		if ( isset( $GLOBALS['mfm_test_expected_nonce'] ) ) {
			return (string) $nonce === (string) $GLOBALS['mfm_test_expected_nonce'];
		}
		return (string) $nonce === wp_create_nonce( $action );
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		return @unlink( (string) $file );
	}
}

if ( ! function_exists( 'wp_is_writable' ) ) {
	function wp_is_writable( $path ) {
		return is_writable( (string) $path );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		$target = (string) $target;
		if ( is_dir( $target ) ) {
			return true;
		}
		return @mkdir( $target, 0755, true );
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'mfm-test-salt-' . (string) $scheme;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type() {
		return isset( $GLOBALS['mfm_test_environment_type'] ) ? (string) $GLOBALS['mfm_test_environment_type'] : 'production';
	}
}
