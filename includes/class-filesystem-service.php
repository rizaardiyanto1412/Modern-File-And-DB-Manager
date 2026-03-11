<?php
/**
 * Filesystem service with sandbox enforcement.
 *
 * @package ModernFileManager
 */

namespace ModernFileManager;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Filesystem service.
 */
class Filesystem_Service {
	/**
	 * Sandbox root path.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Blocked relative paths.
	 *
	 * @var string[]
	 */
	private $denylist;

	/**
	 * Optional PHP lint runner callback for testing.
	 *
	 * Signature: function(string $binary, string $temp_file): array{available:bool,exit_code:int,output:string}
	 *
	 * @var callable|null
	 */
	private $php_lint_runner;

	/**
	 * Constructor.
	 */
	public function __construct( $php_lint_runner = null ) {
		$raw_root       = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$real_root      = realpath( $raw_root );
		$this->root     = false !== $real_root ? wp_normalize_path( $real_root ) : $raw_root;
		$this->denylist = array(
			'/.git',
			'/.env',
		);
		$this->php_lint_runner = is_callable( $php_lint_runner ) ? $php_lint_runner : null;
	}

	/**
	 * Get root metadata.
	 *
	 * @return array
	 */
	public function get_root_meta() {
		return array(
			'root' => '/',
			'path' => '/',
		);
	}

	/**
	 * Get path listing.
	 *
	 * @param string $relative_path Relative path.
	 * @return array|WP_Error
	 */
	public function list_directory( $relative_path ) {
		$resolved = $this->resolve_existing_path( $relative_path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		if ( ! is_dir( $resolved ) ) {
			return new WP_Error( 'invalid_path', __( 'Target path is not a directory.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}

		$entries = @scandir( $resolved );
		if ( false === $entries ) {
			return new WP_Error( 'io_error', __( 'Unable to read this directory.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}

		$items = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$entry_abs = wp_normalize_path( $resolved . '/' . $entry );
			$entry_rel = $this->to_relative_path( $entry_abs );
			if ( $this->is_denied_path( $entry_rel ) ) {
				continue;
			}

				$items[] = array(
				'name'      => $entry,
				'path'      => $entry_rel,
				'type'      => is_dir( $entry_abs ) ? 'dir' : 'file',
				'size'      => is_file( $entry_abs ) ? (int) @filesize( $entry_abs ) : 0,
				'modified'  => (int) @filemtime( $entry_abs ),
				'readable'  => is_readable( $entry_abs ),
					'writable'  => wp_is_writable( $entry_abs ),
				'extension' => is_file( $entry_abs ) ? strtolower( (string) pathinfo( $entry_abs, PATHINFO_EXTENSION ) ) : '',
			);
		}

		usort(
			$items,
			function ( $a, $b ) {
				if ( $a['type'] !== $b['type'] ) {
					return ( 'dir' === $a['type'] ) ? -1 : 1;
				}
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		return array(
			'path'  => $this->to_relative_path( $resolved ),
			'items' => $items,
		);
	}

	/**
	 * Create directory.
	 *
	 * @param string $parent Parent path.
	 * @param string $name Directory name.
	 * @return array|WP_Error
	 */
	public function create_directory( $parent, $name ) {
		$parent_abs = $this->resolve_existing_path( $parent );
		if ( is_wp_error( $parent_abs ) ) {
			return $parent_abs;
		}

		$name = $this->sanitize_new_name( $name );
		if ( is_wp_error( $name ) ) {
			return $name;
		}

		$target_abs = wp_normalize_path( $parent_abs . '/' . $name );
		$valid      = $this->validate_target_path( $target_abs );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( file_exists( $target_abs ) ) {
			return new WP_Error( 'conflict', __( 'A file or folder with this name already exists.', 'modern-file-db-manager' ), array( 'status' => 409 ) );
		}

		if ( ! wp_mkdir_p( $target_abs ) ) {
			return new WP_Error( 'io_error', __( 'Unable to create folder.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}

		return array( 'path' => $this->to_relative_path( $target_abs ) );
	}

	/**
	 * Create an empty file.
	 *
	 * @param string $parent Parent path.
	 * @param string $name Filename.
	 * @return array|WP_Error
	 */
	public function create_file( $parent, $name ) {
		$parent_abs = $this->resolve_existing_path( $parent );
		if ( is_wp_error( $parent_abs ) ) {
			return $parent_abs;
		}

		$name = $this->sanitize_new_name( $name );
		if ( is_wp_error( $name ) ) {
			return $name;
		}

		$target_abs = wp_normalize_path( $parent_abs . '/' . $name );
		$valid      = $this->validate_target_path( $target_abs );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( file_exists( $target_abs ) ) {
			return new WP_Error( 'conflict', __( 'A file or folder with this name already exists.', 'modern-file-db-manager' ), array( 'status' => 409 ) );
		}

		$created = @file_put_contents( $target_abs, '' );
		if ( false === $created ) {
			return new WP_Error( 'io_error', __( 'Unable to create file.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}

		return array( 'path' => $this->to_relative_path( $target_abs ) );
	}

	/**
	 * Rename path.
	 *
	 * @param string $path Path to rename.
	 * @param string $new_name New name.
	 * @return array|WP_Error
	 */
	public function rename_path( $path, $new_name ) {
		$source_abs = $this->resolve_existing_path( $path );
		if ( is_wp_error( $source_abs ) ) {
			return $source_abs;
		}

		$new_name = $this->sanitize_new_name( $new_name );
		if ( is_wp_error( $new_name ) ) {
			return $new_name;
		}

		$dest_abs = wp_normalize_path( dirname( $source_abs ) . '/' . $new_name );
		$valid    = $this->validate_target_path( $dest_abs );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( file_exists( $dest_abs ) ) {
			return new WP_Error( 'conflict', __( 'Destination already exists.', 'modern-file-db-manager' ), array( 'status' => 409 ) );
		}

		if ( ! $this->move_path_with_filesystem( $source_abs, $dest_abs ) ) {
			return new WP_Error( 'io_error', __( 'Unable to rename.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}

		return array( 'path' => $this->to_relative_path( $dest_abs ) );
	}

	/**
	 * Move path.
	 *
	 * @param string $source Source path.
	 * @param string $destination_folder Destination folder path.
	 * @return array|WP_Error
	 */
	public function move_path( $source, $destination_folder ) {
		$source_abs = $this->resolve_existing_path( $source );
		$dest_abs   = $this->resolve_existing_path( $destination_folder );
		if ( is_wp_error( $source_abs ) ) {
			return $source_abs;
		}
		if ( is_wp_error( $dest_abs ) ) {
			return $dest_abs;
		}
		if ( ! is_dir( $dest_abs ) ) {
			return new WP_Error( 'invalid_path', __( 'Destination must be a directory.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}

		$target_abs = wp_normalize_path( $dest_abs . '/' . basename( $source_abs ) );
		$valid      = $this->validate_target_path( $target_abs );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( file_exists( $target_abs ) ) {
			return new WP_Error( 'conflict', __( 'Destination already contains an item with the same name.', 'modern-file-db-manager' ), array( 'status' => 409 ) );
		}

		if ( ! $this->move_path_with_filesystem( $source_abs, $target_abs ) ) {
			return new WP_Error( 'io_error', __( 'Unable to move item.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}

		return array( 'path' => $this->to_relative_path( $target_abs ) );
	}

	/**
	 * Copy path.
	 *
	 * @param string $source Source path.
	 * @param string $destination_folder Destination directory.
	 * @return array|WP_Error
	 */
	public function copy_path( $source, $destination_folder ) {
		$source_abs = $this->resolve_existing_path( $source );
		$dest_abs   = $this->resolve_existing_path( $destination_folder );
		if ( is_wp_error( $source_abs ) ) {
			return $source_abs;
		}
		if ( is_wp_error( $dest_abs ) ) {
			return $dest_abs;
		}
		if ( ! is_dir( $dest_abs ) ) {
			return new WP_Error( 'invalid_path', __( 'Destination must be a directory.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}

		$target_abs = wp_normalize_path( $dest_abs . '/' . basename( $source_abs ) );
		$valid      = $this->validate_target_path( $target_abs );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( file_exists( $target_abs ) ) {
			return new WP_Error( 'conflict', __( 'Destination already contains an item with the same name.', 'modern-file-db-manager' ), array( 'status' => 409 ) );
		}

		$result = is_dir( $source_abs )
			? $this->copy_directory_recursive( $source_abs, $target_abs )
			: @copy( $source_abs, $target_abs );

		if ( ! $result ) {
			return new WP_Error( 'io_error', __( 'Unable to copy item.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}

		return array( 'path' => $this->to_relative_path( $target_abs ) );
	}

	/**
	 * Delete one or multiple paths.
	 *
	 * @param string[] $paths Paths to delete.
	 * @return array|WP_Error
	 */
	public function delete_paths( array $paths ) {
		$deleted = array();
		foreach ( $paths as $path ) {
			$target_abs = $this->resolve_existing_path( $path );
			if ( is_wp_error( $target_abs ) ) {
				return $target_abs;
			}
			if ( $this->to_relative_path( $target_abs ) === '/' ) {
				return new WP_Error( 'forbidden', __( 'Deleting the sandbox root is not allowed.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
			}

			$ok = is_dir( $target_abs ) ? $this->delete_directory_recursive( $target_abs ) : wp_delete_file( $target_abs );
			if ( ! $ok ) {
				return new WP_Error( 'io_error', __( 'Unable to delete item.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
			}

			$deleted[] = $path;
		}

		return array( 'deleted' => $deleted );
	}

	/**
	 * Handle uploaded file.
	 *
	 * @param string $destination_folder Destination folder.
	 * @param array  $file Uploaded file array.
	 * @return array|WP_Error
	 */
	public function upload_file( $destination_folder, array $file ) {
		$dest_abs = $this->resolve_existing_path( $destination_folder );
		if ( is_wp_error( $dest_abs ) ) {
			return $dest_abs;
		}
		if ( ! is_dir( $dest_abs ) ) {
			return new WP_Error( 'invalid_path', __( 'Upload destination must be a directory.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'io_error', __( 'Upload failed.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}

		$filename = $this->sanitize_new_name( (string) $file['name'] );
		if ( is_wp_error( $filename ) ) {
			return $filename;
		}

		$target_abs = wp_normalize_path( $dest_abs . '/' . $filename );
		$valid      = $this->validate_target_path( $target_abs );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( file_exists( $target_abs ) ) {
			return new WP_Error( 'conflict', __( 'Destination already contains an item with the same name.', 'modern-file-db-manager' ), array( 'status' => 409 ) );
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'forbidden', __( 'Invalid uploaded file.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
		}

		if ( ! @copy( $tmp_name, $target_abs ) ) {
			return new WP_Error( 'io_error', __( 'Unable to store uploaded file.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}
		wp_delete_file( $tmp_name );

		return array( 'path' => $this->to_relative_path( $target_abs ) );
	}

	/**
	 * Resolve path for download.
	 *
	 * @param string $path Relative path.
	 * @return string|WP_Error
	 */
	public function resolve_downloadable_file( $path ) {
		$resolved = $this->resolve_existing_path( $path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( ! is_file( $resolved ) ) {
			return new WP_Error( 'invalid_path', __( 'Download target must be a file.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}

		return $resolved;
	}

	/**
	 * Read text file content.
	 *
	 * @param string $path Relative path.
	 * @return array|WP_Error
	 */
	public function read_file( $path ) {
		$resolved = $this->resolve_existing_path( $path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( ! is_file( $resolved ) ) {
			return new WP_Error( 'invalid_path', __( 'Editor target must be a file.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}
		if ( ! is_readable( $resolved ) ) {
			return new WP_Error( 'forbidden', __( 'File is not readable.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
		}

		$size = @filesize( $resolved );
		if ( false !== $size && $size > 1024 * 1024 * 2 ) {
			return new WP_Error( 'file_too_large', __( 'File exceeds 2 MB editor limit.', 'modern-file-db-manager' ), array( 'status' => 413 ) );
		}

		$content = @file_get_contents( $resolved );
		if ( false === $content ) {
			return new WP_Error( 'io_error', __( 'Unable to read file.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}

		return array(
			'path'    => $this->to_relative_path( $resolved ),
			'content' => (string) $content,
		);
	}

	/**
	 * Save text file content.
	 *
	 * @param string $path Relative path.
	 * @param string $content File content.
	 * @return array|WP_Error
	 */
	public function save_file( $path, $content ) {
		$resolved = $this->resolve_existing_path( $path );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( ! is_file( $resolved ) ) {
			return new WP_Error( 'invalid_path', __( 'Editor target must be a file.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}
		if ( ! wp_is_writable( $resolved ) ) {
			return new WP_Error( 'forbidden', __( 'File is not writable.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
		}

		if ( $this->should_run_php_lint_check( $resolved ) ) {
			$lint_result = $this->validate_php_syntax_before_save( (string) $content, $this->to_relative_path( $resolved ) );
			if ( is_wp_error( $lint_result ) ) {
				return $lint_result;
			}
		}

		$written = @file_put_contents( $resolved, (string) $content, LOCK_EX );
		if ( false === $written ) {
			return new WP_Error( 'io_error', __( 'Unable to save file.', 'modern-file-db-manager' ), array( 'status' => 500 ) );
		}

		return array(
			'path'    => $this->to_relative_path( $resolved ),
			'bytes'   => (int) $written,
			'updated' => (int) @filemtime( $resolved ),
		);
	}

	/**
	 * Normalize and sanitize path string.
	 *
	 * @param mixed $path Path.
	 * @return string
	 */
	public function sanitize_relative_path( $path ) {
		if ( ! is_string( $path ) ) {
			return '/';
		}

		$path = rawurldecode( $path );
		$path = wp_normalize_path( trim( $path ) );
		$path = preg_replace( '#/+#', '/', $path );
		$path = ltrim( (string) $path, '/' );

		return '' === $path ? '/' : '/' . $path;
	}

	/**
	 * Resolve an existing path.
	 *
	 * @param string $relative_path Relative path.
	 * @return string|WP_Error
	 */
	private function resolve_existing_path( $relative_path ) {
		$relative_path = $this->sanitize_relative_path( $relative_path );

		if ( $this->is_denied_path( $relative_path ) ) {
			return new WP_Error( 'forbidden', __( 'Access to this path is blocked.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
		}

		$candidate = wp_normalize_path( $this->root . ( '/' === $relative_path ? '' : $relative_path ) );
		$resolved  = realpath( $candidate );
		if ( false === $resolved ) {
			return new WP_Error( 'not_found', __( 'Path not found.', 'modern-file-db-manager' ), array( 'status' => 404 ) );
		}

		$resolved = wp_normalize_path( $resolved );
		if ( ! $this->is_within_root( $resolved ) ) {
			return new WP_Error( 'out_of_scope', __( 'Path is outside the allowed sandbox.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
		}

		if ( $this->is_denied_path( $this->to_relative_path( $resolved ) ) ) {
			return new WP_Error( 'forbidden', __( 'Access to this path is blocked.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
		}

		return $resolved;
	}

	/**
	 * Validate a target path that may not exist yet.
	 *
	 * @param string $target_abs Absolute target path.
	 * @return true|WP_Error
	 */
	private function validate_target_path( $target_abs ) {
		$target_abs = wp_normalize_path( $target_abs );

		$parent = wp_normalize_path( dirname( $target_abs ) );
		if ( ! $this->is_within_root( $parent ) ) {
			return new WP_Error( 'out_of_scope', __( 'Path is outside the allowed sandbox.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
		}

		if ( $this->is_denied_path( $this->to_relative_path( $target_abs ) ) ) {
			return new WP_Error( 'forbidden', __( 'This path is blocked by policy.', 'modern-file-db-manager' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Convert absolute path to root-relative path.
	 *
	 * @param string $absolute_path Absolute path.
	 * @return string
	 */
	private function to_relative_path( $absolute_path ) {
		$absolute_path = wp_normalize_path( $absolute_path );
		if ( $absolute_path === $this->root ) {
			return '/';
		}

		$relative = str_replace( $this->root, '', $absolute_path );
		$relative = ltrim( $relative, '/' );
		return '/' . $relative;
	}

	/**
	 * Is path inside root.
	 *
	 * @param string $absolute Absolute path.
	 * @return bool
	 */
	private function is_within_root( $absolute ) {
		$absolute = wp_normalize_path( $absolute );
		if ( $absolute === $this->root ) {
			return true;
		}

		return 0 === strpos( $absolute, $this->root . '/' );
	}

	/**
	 * Check denylist.
	 *
	 * @param string $relative_path Relative path.
	 * @return bool
	 */
	private function is_denied_path( $relative_path ) {
		$relative_path = $this->sanitize_relative_path( $relative_path );
		foreach ( $this->denylist as $blocked ) {
			if ( $relative_path === $blocked || 0 === strpos( $relative_path, $blocked . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize new file or directory name.
	 *
	 * @param string $name Item name.
	 * @return string|WP_Error
	 */
	private function sanitize_new_name( $name ) {
		$name = sanitize_file_name( $name );
		$name = trim( $name );

		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'Please provide a valid file or folder name.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}

		if ( preg_match( '#[/\\\\]#', $name ) ) {
			return new WP_Error( 'invalid_name', __( 'Invalid characters in file or folder name.', 'modern-file-db-manager' ), array( 'status' => 400 ) );
		}

		return $name;
	}

	/**
	 * Check whether a file path should be lint-validated as PHP.
	 *
	 * @param string $absolute_path Absolute file path.
	 * @return bool
	 */
	private function should_run_php_lint_check( $absolute_path ) {
		$extension = strtolower( (string) pathinfo( $absolute_path, PATHINFO_EXTENSION ) );
		return in_array( $extension, array( 'php', 'phtml', 'php5', 'php7', 'php8' ), true );
	}

	/**
	 * Validate PHP syntax before saving.
	 *
	 * @param string $content Candidate PHP content.
	 * @param string $display_path User-facing edited path.
	 * @return true|WP_Error
	 */
	private function validate_php_syntax_before_save( $content, $display_path ) {
		$temp_file = @tempnam( sys_get_temp_dir(), 'mfm-lint-' );
		if ( false === $temp_file ) {
			return new WP_Error(
				'php_lint_unavailable',
				__( 'PHP fatal-error check is unavailable on this server, so save was blocked.', 'modern-file-db-manager' ),
				array( 'status' => 503 )
			);
		}

		$temp_file = wp_normalize_path( $temp_file );
		$written   = @file_put_contents( $temp_file, (string) $content, LOCK_EX );
		if ( false === $written ) {
			wp_delete_file( $temp_file );
			return new WP_Error(
				'php_lint_unavailable',
				__( 'PHP fatal-error check is unavailable on this server, so save was blocked.', 'modern-file-db-manager' ),
				array( 'status' => 503 )
			);
		}

		try {
			$binary     = $this->get_php_lint_binary();
			$lint_run   = $this->execute_php_lint( $binary, $temp_file );
			$available  = ! empty( $lint_run['available'] );
			$exit_code  = isset( $lint_run['exit_code'] ) ? (int) $lint_run['exit_code'] : 1;
			$raw_output = isset( $lint_run['output'] ) ? (string) $lint_run['output'] : '';

			if ( ! $available ) {
				return new WP_Error(
					'php_lint_unavailable',
					__( 'PHP fatal-error check is unavailable on this server, so save was blocked.', 'modern-file-db-manager' ),
					array( 'status' => 503 )
				);
			}

			if ( 0 !== $exit_code ) {
				$message = __( 'Save blocked: this change would cause a PHP fatal error (syntax error).', 'modern-file-db-manager' );
				$details = $this->format_php_lint_error_details( $raw_output, $temp_file, $display_path );
				if ( '' !== $details ) {
					$message .= "\n\n" . $details;
				}

				return new WP_Error(
					'php_lint_failed',
					$message,
					array( 'status' => 422 )
				);
			}
		} finally {
			wp_delete_file( $temp_file );
		}

		return true;
	}

	/**
	 * Determine which PHP binary to use for lint execution.
	 *
	 * @return string
	 */
	private function get_php_lint_binary() {
		if ( defined( 'PHP_BINARY' ) && is_string( PHP_BINARY ) && '' !== PHP_BINARY ) {
			return PHP_BINARY;
		}

		return 'php';
	}

	/**
	 * Execute php -l and return normalized output.
	 *
	 * @param string $binary PHP binary.
	 * @param string $temp_file Temp file path.
	 * @return array{available:bool,exit_code:int,output:string}
	 */
	private function execute_php_lint( $binary, $temp_file ) {
		if ( is_callable( $this->php_lint_runner ) ) {
			$result    = call_user_func( $this->php_lint_runner, (string) $binary, (string) $temp_file );
			$available = is_array( $result ) && isset( $result['available'] ) ? (bool) $result['available'] : false;
			$exit_code = is_array( $result ) && isset( $result['exit_code'] ) ? (int) $result['exit_code'] : 1;
			$output    = is_array( $result ) && isset( $result['output'] ) ? (string) $result['output'] : '';
			return array(
				'available' => $available,
				'exit_code' => $exit_code,
				'output'    => $output,
			);
		}

		if ( ! function_exists( 'exec' ) ) {
			return array(
				'available' => false,
				'exit_code' => 1,
				'output'    => 'exec unavailable',
			);
		}

		$command = escapeshellarg( (string) $binary ) . ' -l ' . escapeshellarg( (string) $temp_file ) . ' 2>&1';
		$output  = array();
		$status  = null;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Needed for fail-closed lint preflight.
		$exec_ok = @exec( $command, $output, $status );
		if ( null === $status ) {
			return array(
				'available' => false,
				'exit_code' => 1,
				'output'    => is_string( $exec_ok ) ? $exec_ok : '',
			);
		}

		return array(
			'available' => true,
			'exit_code' => (int) $status,
			'output'    => implode( "\n", array_map( 'strval', $output ) ),
		);
	}

	/**
	 * Format lint output for users (hide temp path and noisy duplicated lines).
	 *
	 * @param string $raw_output Raw lint output.
	 * @param string $temp_file Temp lint file path.
	 * @param string $display_path User-visible edited file path.
	 * @return string
	 */
	private function format_php_lint_error_details( $raw_output, $temp_file, $display_path ) {
		$normalized = trim( preg_replace( "/\r\n|\r/", "\n", (string) $raw_output ) );
		if ( '' === $normalized ) {
			return '';
		}

		$lines = array_map( 'trim', explode( "\n", $normalized ) );
		$lines = array_values(
			array_filter(
				array_unique( $lines ),
				static function ( $line ) {
					if ( '' === $line ) {
						return false;
					}
					return 0 !== stripos( $line, 'Errors parsing ' );
				}
			)
		);

		if ( empty( $lines ) ) {
			return '';
		}

		foreach ( $lines as &$line ) {
			if ( 0 === stripos( $line, 'PHP ' ) ) {
				$line = substr( $line, 4 );
			}
			$line = str_replace( (string) $temp_file, (string) $display_path, $line );
		}
		unset( $line );

		$preferred = '';
		foreach ( $lines as $line ) {
			if ( false !== stripos( $line, 'parse error:' ) || false !== stripos( $line, 'fatal error:' ) ) {
				$preferred = $line;
				break;
			}
		}

		if ( '' !== $preferred ) {
			return $preferred;
		}

		return $lines[0];
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @param string $source Source directory.
	 * @param string $destination Destination directory.
	 * @return bool
	 */
	private function copy_directory_recursive( $source, $destination ) {
		if ( ! wp_mkdir_p( $destination ) ) {
			return false;
		}

		$iterator = @scandir( $source );
		if ( false === $iterator ) {
			return false;
		}

		foreach ( $iterator as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$src_path = wp_normalize_path( $source . '/' . $item );
			$dst_path = wp_normalize_path( $destination . '/' . $item );

			if ( is_dir( $src_path ) ) {
				if ( ! $this->copy_directory_recursive( $src_path, $dst_path ) ) {
					return false;
				}
				continue;
			}

			if ( ! @copy( $src_path, $dst_path ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $directory Directory path.
	 * @return bool
	 */
	private function delete_directory_recursive( $directory ) {
		$iterator = @scandir( $directory );
		if ( false === $iterator ) {
			return false;
		}

		foreach ( $iterator as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = wp_normalize_path( $directory . '/' . $item );
			$ok   = is_dir( $path ) ? $this->delete_directory_recursive( $path ) : wp_delete_file( $path );
			if ( ! $ok ) {
				return false;
			}
		}

		$wp_filesystem = $this->get_wp_filesystem();
		if ( $wp_filesystem && method_exists( $wp_filesystem, 'rmdir' ) ) {
			return (bool) $wp_filesystem->rmdir( $directory, false );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Fallback for direct filesystem environments.
		return rmdir( $directory );
	}

	/**
	 * Move path using WP_Filesystem, with fallback.
	 *
	 * @param string $source_abs Source absolute path.
	 * @param string $destination_abs Destination absolute path.
	 * @return bool
	 */
	private function move_path_with_filesystem( $source_abs, $destination_abs ) {
		$wp_filesystem = $this->get_wp_filesystem();
		if ( $wp_filesystem && method_exists( $wp_filesystem, 'move' ) ) {
			return (bool) $wp_filesystem->move( $source_abs, $destination_abs, false );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Fallback for direct filesystem environments.
		return rename( $source_abs, $destination_abs );
	}

	/**
	 * Get initialized WP_Filesystem instance when possible.
	 *
	 * @return object|null
	 */
	private function get_wp_filesystem() {
		global $wp_filesystem;

		if ( is_object( $wp_filesystem ) ) {
			return $wp_filesystem;
		}

		$wp_filesystem_file = ABSPATH . 'wp-admin/includes/file.php';
		if ( ! file_exists( $wp_filesystem_file ) ) {
			return null;
		}

		require_once $wp_filesystem_file;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			return null;
		}

		WP_Filesystem();
		return is_object( $wp_filesystem ) ? $wp_filesystem : null;
	}
}
