<?php

namespace ModernFileManager\Tests;

use ModernFileManager\Filesystem_Service;
use PHPUnit\Framework\TestCase;

class FilesystemServiceTest extends TestCase {
	private $root;
	private $siblingRoot;
	private $service;

	protected function setUp(): void {
		parent::setUp();

		$this->root = rtrim( MFM_TEST_ROOT, '/\\' );
		$this->siblingRoot = dirname( $this->root ) . '/' . basename( $this->root ) . '-backup';
		$this->rrmdir( $this->root );
		$this->rrmdir( $this->siblingRoot );
		mkdir( $this->root, 0755, true );
		mkdir( $this->root . '/wp-content/uploads', 0755, true );
		file_put_contents( $this->root . '/wp-content/uploads/sample.txt', 'hello' );
		mkdir( $this->root . '/safe-dir', 0755, true );
		file_put_contents( $this->root . '/wp-config.php', 'do not touch' );
		file_put_contents( $this->root . '/.env', 'SECRET=true' );
		mkdir( $this->siblingRoot, 0755, true );
		file_put_contents( $this->siblingRoot . '/secret.txt', 'outside root' );

		$this->service = new Filesystem_Service();
	}

	protected function tearDown(): void {
		$this->rrmdir( $this->root );
		$this->rrmdir( $this->siblingRoot );
		parent::tearDown();
	}

	public function test_it_lists_root_directories(): void {
		$result = $this->service->list_directory( '/' );

		$this->assertFalse( \is_wp_error( $result ) );
		$this->assertSame( '/', $result['path'] );
		$this->assertNotEmpty( $result['items'] );
	}

	public function test_it_blocks_denylisted_file_access(): void {
		$result = $this->service->list_directory( '/.env' );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertSame( 'forbidden', $result->get_error_code() );
	}

	public function test_it_lists_wp_config_file_when_not_denied(): void {
		$result = $this->service->list_directory( '/' );

		$this->assertFalse( \is_wp_error( $result ) );
		$paths = array_map(
			static function ( $item ) {
				return isset( $item['path'] ) ? $item['path'] : '';
			},
			$result['items']
		);
		$this->assertContains( '/wp-config.php', $paths );
	}

	public function test_it_rejects_traversal_outside_root(): void {
		$result = $this->service->list_directory( '/../../' );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertContains( $result->get_error_code(), array( 'not_found', 'out_of_scope' ) );
	}

	public function test_it_blocks_prefix_based_sibling_traversal(): void {
		$path = '/../' . basename( $this->siblingRoot ) . '/secret.txt';

		$result = $this->service->read_file( $path );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertSame( 'out_of_scope', $result->get_error_code() );
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

	public function test_save_file_blocks_invalid_php_syntax_and_preserves_original(): void {
		$targetPath = '/safe-dir/write-me.php';
		$file       = $this->root . $targetPath;
		file_put_contents( $file, "<?php\nfunction keep_ok() { return true; }\n" );
		$tempLintPath = '';

		$service = new Filesystem_Service(
			static function ( $binary, $temp_file ) use ( &$tempLintPath ) {
				$tempLintPath = (string) $temp_file;
				return array(
					'available' => true,
					'exit_code' => 255,
					'output'    => "PHP Parse error: unexpected token \"}\" in {$temp_file} on line 18\nParse error: unexpected token \"}\" in {$temp_file} on line 18\nErrors parsing {$temp_file}",
				);
			}
		);

		$result = $service->save_file( $targetPath, "<?php\nfunction broken( { \n" );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertSame( 'php_lint_failed', $result->get_error_code() );
		$this->assertSame( 422, $result->get_error_data()['status'] );
		$this->assertStringContainsString( 'Save blocked', $result->get_error_message() );
		$this->assertStringContainsString( $targetPath, $result->get_error_message() );
		$this->assertStringNotContainsString( $tempLintPath, $result->get_error_message() );
		$this->assertStringContainsString( 'keep_ok', (string) file_get_contents( $file ) );
	}

	public function test_save_file_blocks_when_lint_unavailable_and_preserves_original(): void {
		$targetPath = '/safe-dir/write-me.php';
		$file       = $this->root . $targetPath;
		file_put_contents( $file, "<?php\nfunction keep_ok() { return true; }\n" );

		$service = new Filesystem_Service(
			static function () {
				return array(
					'available' => false,
					'exit_code' => 1,
					'output'    => 'lint unavailable',
				);
			}
		);

		$result = $service->save_file( $targetPath, "<?php\nfunction maybe_ok() { return false; }\n" );

		$this->assertTrue( \is_wp_error( $result ) );
		$this->assertSame( 'php_lint_unavailable', $result->get_error_code() );
		$this->assertSame( 503, $result->get_error_data()['status'] );
		$this->assertStringContainsString( 'fatal-error check is unavailable', $result->get_error_message() );
		$this->assertStringContainsString( 'keep_ok', (string) file_get_contents( $file ) );
	}

	public function test_save_file_non_php_bypasses_lint_runner(): void {
		$targetPath = '/safe-dir/write-me.txt';
		$file       = $this->root . $targetPath;
		file_put_contents( $file, "before\n" );

		$service = new Filesystem_Service(
			static function () {
				throw new \RuntimeException( 'Lint runner should not be called for non-PHP files.' );
			}
		);

		$result = $service->save_file( $targetPath, "after\n" );

		$this->assertFalse( \is_wp_error( $result ) );
		$this->assertSame( "after\n", (string) file_get_contents( $file ) );
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
