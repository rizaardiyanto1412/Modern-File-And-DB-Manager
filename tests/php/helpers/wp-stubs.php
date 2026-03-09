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

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return isset( $GLOBALS['mfm_test_current_user_can'] ) ? (bool) $GLOBALS['mfm_test_current_user_can'] : true;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		$expected = isset( $GLOBALS['mfm_test_expected_nonce'] ) ? (string) $GLOBALS['mfm_test_expected_nonce'] : 'valid-nonce';
		return (string) $nonce === $expected;
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {
	}
}
