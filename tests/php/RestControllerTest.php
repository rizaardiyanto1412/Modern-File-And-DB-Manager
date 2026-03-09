<?php

namespace ModernFileManager\Tests;

use ModernFileManager\Filesystem_Service;
use ModernFileManager\Rest_Controller;
use PHPUnit\Framework\TestCase;

class RestControllerTest extends TestCase {
	private $root;
	private $filesystem;
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->root = rtrim( MFM_TEST_ROOT, '/\\' );
		$this->rrmdir( $this->root );
		mkdir( $this->root, 0755, true );
		mkdir( $this->root . '/safe-dir', 0755, true );
		mkdir( $this->root . '/target-dir', 0755, true );
		file_put_contents( $this->root . '/safe-dir/keep.txt', 'x' );

		$GLOBALS['mfm_test_current_user_can'] = true;
		$GLOBALS['mfm_test_expected_nonce']   = 'valid-nonce';
		$GLOBALS['mfm_test_is_uploaded_file'] = true;
		$GLOBALS['mfm_test_force_move_uploaded_file'] = true;

		$this->filesystem = new Filesystem_Service();
		$this->controller = new Rest_Controller( $this->filesystem );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['mfm_test_current_user_can'],
			$GLOBALS['mfm_test_expected_nonce'],
			$GLOBALS['mfm_test_is_uploaded_file'],
			$GLOBALS['mfm_test_force_move_uploaded_file']
		);
		$this->rrmdir( $this->root );
		parent::tearDown();
	}

	public function test_permission_check_allows_valid_admin_nonce(): void {
		$request = new \WP_REST_Request( 'GET', '/modern-file-manager/v1/list' );
		$request->set_header( 'X-WP-Nonce', 'valid-nonce' );

		$result = $this->controller->permission_check( $request );

		$this->assertTrue( $result );
	}

	public function test_permission_check_rejects_missing_capability(): void {
		$GLOBALS['mfm_test_current_user_can'] = false;

		$request = new \WP_REST_Request( 'GET', '/modern-file-manager/v1/list' );
		$request->set_header( 'X-WP-Nonce', 'valid-nonce' );

		$result = $this->controller->permission_check( $request );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertSame( 'forbidden', $result->get_error_code() );
	}

	public function test_permission_check_rejects_invalid_nonce(): void {
		$request = new \WP_REST_Request( 'GET', '/modern-file-manager/v1/list' );
		$request->set_header( 'X-WP-Nonce', 'bad' );

		$result = $this->controller->permission_check( $request );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertSame( 'nonce_failed', $result->get_error_code() );
	}

	public function test_create_file_endpoint_success_payload_shape(): void {
		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/create-file' );
		$request->set_param( 'path', '/safe-dir' );
		$request->set_param( 'name', 'new.txt' );

		$response = $this->controller->create_file( $request );
		$data     = $response->get_data();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['ok'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertFileExists( $this->root . '/safe-dir/new.txt' );
	}

	public function test_create_file_endpoint_conflict_returns_standard_error_shape(): void {
		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/create-file' );
		$request->set_param( 'path', '/safe-dir' );
		$request->set_param( 'name', 'keep.txt' );

		$response = $this->controller->create_file( $request );
		$data     = $response->get_data();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 409, $response->get_status() );
		$this->assertFalse( $data['ok'] );
		$this->assertSame( 'conflict', $data['code'] );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertArrayHasKey( 'details', $data );
	}

	public function test_delete_endpoint_success(): void {
		file_put_contents( $this->root . '/safe-dir/delete-me.txt', 'x' );

		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/delete' );
		$request->set_param( 'paths', array( '/safe-dir/delete-me.txt' ) );

		$response = $this->controller->delete_paths( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['ok'] );
		$this->assertSame( array( '/safe-dir/delete-me.txt' ), $data['data']['deleted'] );
		$this->assertFileDoesNotExist( $this->root . '/safe-dir/delete-me.txt' );
	}

	public function test_delete_endpoint_invalid_payload_returns_error_shape(): void {
		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/delete' );
		$request->set_param( 'paths', 'not-an-array' );

		$response = $this->controller->delete_paths( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $data['ok'] );
		$this->assertSame( 'invalid_request', $data['code'] );
	}

	public function test_mkdir_endpoint_success(): void {
		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/mkdir' );
		$request->set_param( 'path', '/safe-dir' );
		$request->set_param( 'name', 'new-folder' );

		$response = $this->controller->create_directory( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['ok'] );
		$this->assertFileExists( $this->root . '/safe-dir/new-folder' );
	}

	public function test_rename_endpoint_success(): void {
		file_put_contents( $this->root . '/safe-dir/old-name.txt', 'x' );

		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/rename' );
		$request->set_param( 'path', '/safe-dir/old-name.txt' );
		$request->set_param( 'newName', 'new-name.txt' );

		$response = $this->controller->rename_path( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['ok'] );
		$this->assertFileExists( $this->root . '/safe-dir/new-name.txt' );
	}

	public function test_move_endpoint_success(): void {
		file_put_contents( $this->root . '/safe-dir/move-me.txt', 'x' );

		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/move' );
		$request->set_param( 'source', '/safe-dir/move-me.txt' );
		$request->set_param( 'destination', '/target-dir' );

		$response = $this->controller->move_path( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['ok'] );
		$this->assertFileExists( $this->root . '/target-dir/move-me.txt' );
		$this->assertFileDoesNotExist( $this->root . '/safe-dir/move-me.txt' );
	}

	public function test_copy_endpoint_success(): void {
		file_put_contents( $this->root . '/safe-dir/copy-me.txt', 'x' );

		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/copy' );
		$request->set_param( 'source', '/safe-dir/copy-me.txt' );
		$request->set_param( 'destination', '/target-dir' );

		$response = $this->controller->copy_path( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['ok'] );
		$this->assertFileExists( $this->root . '/target-dir/copy-me.txt' );
		$this->assertFileExists( $this->root . '/safe-dir/copy-me.txt' );
	}

	public function test_upload_endpoint_success(): void {
		$tmpFile = tempnam( sys_get_temp_dir(), 'mfm-up-' );
		file_put_contents( $tmpFile, 'upload-content' );

		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/upload' );
		$request->set_param( 'path', '/safe-dir' );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'uploaded.txt',
					'tmp_name' => $tmpFile,
					'error'    => UPLOAD_ERR_OK,
				),
			)
		);

		$response = $this->controller->upload_file( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['ok'] );
		$this->assertFileExists( $this->root . '/safe-dir/uploaded.txt' );
	}

	public function test_upload_endpoint_missing_file_returns_standard_error_shape(): void {
		$request = new \WP_REST_Request( 'POST', '/modern-file-manager/v1/upload' );
		$request->set_param( 'path', '/safe-dir' );
		$request->set_file_params( array() );

		$response = $this->controller->upload_file( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $data['ok'] );
		$this->assertSame( 'invalid_request', $data['code'] );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertArrayHasKey( 'details', $data );
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$entries = scandir( $dir );
		if ( false === $entries ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . '/' . $entry;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}
}
