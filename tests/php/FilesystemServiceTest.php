<?php

namespace ModernFileManager\Tests;

use ModernFileManager\Filesystem_Service;
use PHPUnit\Framework\TestCase;

class FilesystemServiceTest extends TestCase {
	private $root;
	private $service;

	protected function setUp(): void {
		parent::setUp();

		$this->root = rtrim( MFM_TEST_ROOT, '/\\' );
		$this->rrmdir( $this->root );
		mkdir( $this->root, 0755, true );
		mkdir( $this->root . '/wp-content/uploads', 0755, true );
		file_put_contents( $this->root . '/wp-content/uploads/sample.txt', 'hello' );
		mkdir( $this->root . '/safe-dir', 0755, true );
		file_put_contents( $this->root . '/wp-config.php', 'do not touch' );
		file_put_contents( $this->root . '/.env', 'SECRET=true' );

		$this->service = new Filesystem_Service();
	}

	protected function tearDown(): void {
		$this->rrmdir( $this->root );
		parent::tearDown();
	}

	public function test_it_lists_root_directories(): void {
		$result = $this->service->list_directory( '/' );

		$this->assertFalse( \is_wp_error( $result ) );
		$this->assertSame( '/', $result['path'] );
		$this->assertNotEmpty( $result['items'] );
	}

	public function test_it_blocks_denylisted_file_access(): void {
		$result = $this->service->list_directory( '/wp-config.php' );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertSame( 'forbidden', $result->get_error_code() );
	}

	public function test_it_rejects_traversal_outside_root(): void {
		$result = $this->service->list_directory( '/../../' );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertContains( $result->get_error_code(), array( 'not_found', 'out_of_scope' ) );
	}

	public function test_it_creates_renames_moves_and_deletes_file(): void {
		$created = $this->service->create_file( '/safe-dir', 'draft.txt' );
		$this->assertFalse( \is_wp_error( $created ) );
		$this->assertFileExists( $this->root . '/safe-dir/draft.txt' );

		$renamed = $this->service->rename_path( '/safe-dir/draft.txt', 'renamed.txt' );
		$this->assertFalse( \is_wp_error( $renamed ) );
		$this->assertFileExists( $this->root . '/safe-dir/renamed.txt' );

		$moved = $this->service->move_path( '/safe-dir/renamed.txt', '/wp-content/uploads' );
		$this->assertFalse( \is_wp_error( $moved ) );
		$this->assertFileExists( $this->root . '/wp-content/uploads/renamed.txt' );

		$deleted = $this->service->delete_paths( array( '/wp-content/uploads/renamed.txt' ) );
		$this->assertFalse( \is_wp_error( $deleted ) );
		$this->assertFileDoesNotExist( $this->root . '/wp-content/uploads/renamed.txt' );
	}

	public function test_it_prevents_deleting_root(): void {
		$result = $this->service->delete_paths( array( '/' ) );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertSame( 'forbidden', $result->get_error_code() );
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
