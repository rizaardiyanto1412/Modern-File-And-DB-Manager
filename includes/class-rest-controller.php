<?php
/**
 * REST controller.
 *
 * @package ModernFileManager
 */

namespace ModernFileManager;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller class.
 */
class Rest_Controller {
	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'modern-file-manager/v1';

	/**
	 * Filesystem service.
	 *
	 * @var Filesystem_Service
	 */
	private $filesystem;

	/**
	 * Constructor.
	 *
	 * @param Filesystem_Service $filesystem Filesystem service.
	 */
	public function __construct( Filesystem_Service $filesystem ) {
		$this->filesystem = $filesystem;
	}

	/**
	 * Init.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/list',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'list_items' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/mkdir',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'create_directory' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/create-file',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'create_file' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/rename',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'rename_path' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/move',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'move_path' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/copy',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'copy_path' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/delete',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'delete_paths' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/upload',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'upload_file' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/download',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'download_file' ),
			)
		);
	}

	/**
	 * Permission gate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_check( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'Insufficient permissions.', 'modern-file-manager' ), array( 'status' => 403 ) );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'nonce_failed', __( 'Invalid security nonce.', 'modern-file-manager' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * GET /list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_items( WP_REST_Request $request ) {
		$path   = $this->filesystem->sanitize_relative_path( $request->get_param( 'path' ) );
		$result = $this->filesystem->list_directory( $path );

		return $this->respond( $result, array( 'path' => $path ) );
	}

	/**
	 * POST /mkdir.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_directory( WP_REST_Request $request ) {
		$path   = $this->filesystem->sanitize_relative_path( $request->get_param( 'path' ) );
		$name   = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$result = $this->filesystem->create_directory( $path, $name );

		return $this->respond( $result, array( 'path' => $path ) );
	}

	/**
	 * POST /create-file.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_file( WP_REST_Request $request ) {
		$path   = $this->filesystem->sanitize_relative_path( $request->get_param( 'path' ) );
		$name   = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$result = $this->filesystem->create_file( $path, $name );

		return $this->respond( $result, array( 'path' => $path ) );
	}

	/**
	 * POST /rename.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rename_path( WP_REST_Request $request ) {
		$path    = $this->filesystem->sanitize_relative_path( $request->get_param( 'path' ) );
		$newname = sanitize_text_field( (string) $request->get_param( 'newName' ) );
		$result  = $this->filesystem->rename_path( $path, $newname );

		return $this->respond( $result, array( 'path' => $path ) );
	}

	/**
	 * POST /move.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function move_path( WP_REST_Request $request ) {
		$source      = $this->filesystem->sanitize_relative_path( $request->get_param( 'source' ) );
		$destination = $this->filesystem->sanitize_relative_path( $request->get_param( 'destination' ) );
		$result      = $this->filesystem->move_path( $source, $destination );

		return $this->respond( $result, array( 'source' => $source, 'destination' => $destination ) );
	}

	/**
	 * POST /copy.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function copy_path( WP_REST_Request $request ) {
		$source      = $this->filesystem->sanitize_relative_path( $request->get_param( 'source' ) );
		$destination = $this->filesystem->sanitize_relative_path( $request->get_param( 'destination' ) );
		$result      = $this->filesystem->copy_path( $source, $destination );

		return $this->respond( $result, array( 'source' => $source, 'destination' => $destination ) );
	}

	/**
	 * POST /delete.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_paths( WP_REST_Request $request ) {
		$raw_paths = $request->get_param( 'paths' );
		if ( ! is_array( $raw_paths ) ) {
			return $this->error_response( new WP_Error( 'invalid_request', __( 'paths must be an array.', 'modern-file-manager' ), array( 'status' => 400 ) ) );
		}

		$paths = array_map( array( $this->filesystem, 'sanitize_relative_path' ), $raw_paths );
		$paths = array_values( array_filter( $paths ) );
		if ( empty( $paths ) ) {
			return $this->error_response( new WP_Error( 'invalid_request', __( 'No valid paths provided.', 'modern-file-manager' ), array( 'status' => 400 ) ) );
		}

		$result = $this->filesystem->delete_paths( $paths );

		return $this->respond( $result, array( 'count' => count( $paths ) ) );
	}

	/**
	 * POST /upload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_file( WP_REST_Request $request ) {
		$path  = $this->filesystem->sanitize_relative_path( $request->get_param( 'path' ) );
		$files = $request->get_file_params();
		if ( ! isset( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return $this->error_response( new WP_Error( 'invalid_request', __( 'Missing file upload payload.', 'modern-file-manager' ), array( 'status' => 400 ) ) );
		}

		$result = $this->filesystem->upload_file( $path, $files['file'] );

		return $this->respond( $result, array( 'path' => $path ) );
	}

	/**
	 * GET /download.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function download_file( WP_REST_Request $request ) {
		$path     = $this->filesystem->sanitize_relative_path( $request->get_param( 'path' ) );
		$resolved = $this->filesystem->resolve_downloadable_file( $path );
		if ( is_wp_error( $resolved ) ) {
			return $this->error_response( $resolved );
		}

		$filename = basename( $resolved );
		$filename = str_replace( '"', '', $filename );
		nocache_headers();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) filesize( $resolved ) );
		readfile( $resolved );
		exit;
	}

	/**
	 * Build standardized response.
	 *
	 * @param array|WP_Error $result Result.
	 * @param array          $meta Meta data.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond( $result, array $meta = array() ) {
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return new WP_REST_Response(
			array(
				'ok'   => true,
				'data' => $result,
				'meta' => $meta,
			)
		);
	}

	/**
	 * Build standardized error response.
	 *
	 * @param WP_Error $error Error object.
	 * @return WP_REST_Response
	 */
	private function error_response( WP_Error $error ) {
		$status_data = $error->get_error_data();
		$status      = is_array( $status_data ) && isset( $status_data['status'] ) ? (int) $status_data['status'] : 400;

		return new WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
				'details' => is_array( $status_data ) ? $status_data : array(),
			),
			$status
		);
	}
}
