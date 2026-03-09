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
	 * Constructor.
	 */
	public function __construct() {
		$raw_root       = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$real_root      = realpath( $raw_root );
		$this->root     = false !== $real_root ? wp_normalize_path( $real_root ) : $raw_root;
		$this->denylist = array(
			'/wp-config.php',
			'/.htaccess',
			'/.git',
			'/.env',
		);
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
			return new WP_Error( 'invalid_path', __( 'Target path is not a directory.', 'modern-file-manager' ), array( 'status' => 400 ) );
		}

		$entries = @scandir( $resolved );
		if ( false === $entries ) {
			return new WP_Error( 'io_error', __( 'Unable to read this directory.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
				'writable'  => is_writable( $entry_abs ),
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
			return new WP_Error( 'conflict', __( 'A file or folder with this name already exists.', 'modern-file-manager' ), array( 'status' => 409 ) );
		}

		if ( ! @mkdir( $target_abs, 0755 ) ) {
			return new WP_Error( 'io_error', __( 'Unable to create folder.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'conflict', __( 'A file or folder with this name already exists.', 'modern-file-manager' ), array( 'status' => 409 ) );
		}

		$created = @file_put_contents( $target_abs, '' );
		if ( false === $created ) {
			return new WP_Error( 'io_error', __( 'Unable to create file.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'conflict', __( 'Destination already exists.', 'modern-file-manager' ), array( 'status' => 409 ) );
		}

		if ( ! @rename( $source_abs, $dest_abs ) ) {
			return new WP_Error( 'io_error', __( 'Unable to rename.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'invalid_path', __( 'Destination must be a directory.', 'modern-file-manager' ), array( 'status' => 400 ) );
		}

		$target_abs = wp_normalize_path( $dest_abs . '/' . basename( $source_abs ) );
		$valid      = $this->validate_target_path( $target_abs );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( file_exists( $target_abs ) ) {
			return new WP_Error( 'conflict', __( 'Destination already contains an item with the same name.', 'modern-file-manager' ), array( 'status' => 409 ) );
		}

		if ( ! @rename( $source_abs, $target_abs ) ) {
			return new WP_Error( 'io_error', __( 'Unable to move item.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'invalid_path', __( 'Destination must be a directory.', 'modern-file-manager' ), array( 'status' => 400 ) );
		}

		$target_abs = wp_normalize_path( $dest_abs . '/' . basename( $source_abs ) );
		$valid      = $this->validate_target_path( $target_abs );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( file_exists( $target_abs ) ) {
			return new WP_Error( 'conflict', __( 'Destination already contains an item with the same name.', 'modern-file-manager' ), array( 'status' => 409 ) );
		}

		$result = is_dir( $source_abs )
			? $this->copy_directory_recursive( $source_abs, $target_abs )
			: @copy( $source_abs, $target_abs );

		if ( ! $result ) {
			return new WP_Error( 'io_error', __( 'Unable to copy item.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
				return new WP_Error( 'forbidden', __( 'Deleting the sandbox root is not allowed.', 'modern-file-manager' ), array( 'status' => 403 ) );
			}

			$ok = is_dir( $target_abs ) ? $this->delete_directory_recursive( $target_abs ) : @unlink( $target_abs );
			if ( ! $ok ) {
				return new WP_Error( 'io_error', __( 'Unable to delete item.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'invalid_path', __( 'Upload destination must be a directory.', 'modern-file-manager' ), array( 'status' => 400 ) );
		}

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'io_error', __( 'Upload failed.', 'modern-file-manager' ), array( 'status' => 400 ) );
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
			return new WP_Error( 'conflict', __( 'Destination already contains an item with the same name.', 'modern-file-manager' ), array( 'status' => 409 ) );
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'forbidden', __( 'Invalid uploaded file.', 'modern-file-manager' ), array( 'status' => 403 ) );
		}

		if ( ! @move_uploaded_file( $tmp_name, $target_abs ) ) {
			return new WP_Error( 'io_error', __( 'Unable to store uploaded file.', 'modern-file-manager' ), array( 'status' => 500 ) );
		}

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
			return new WP_Error( 'invalid_path', __( 'Download target must be a file.', 'modern-file-manager' ), array( 'status' => 400 ) );
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
			return new WP_Error( 'invalid_path', __( 'Editor target must be a file.', 'modern-file-manager' ), array( 'status' => 400 ) );
		}
		if ( ! is_readable( $resolved ) ) {
			return new WP_Error( 'forbidden', __( 'File is not readable.', 'modern-file-manager' ), array( 'status' => 403 ) );
		}

		$size = @filesize( $resolved );
		if ( false !== $size && $size > 1024 * 1024 * 2 ) {
			return new WP_Error( 'file_too_large', __( 'File exceeds 2 MB editor limit.', 'modern-file-manager' ), array( 'status' => 413 ) );
		}

		$content = @file_get_contents( $resolved );
		if ( false === $content ) {
			return new WP_Error( 'io_error', __( 'Unable to read file.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'invalid_path', __( 'Editor target must be a file.', 'modern-file-manager' ), array( 'status' => 400 ) );
		}
		if ( ! is_writable( $resolved ) ) {
			return new WP_Error( 'forbidden', __( 'File is not writable.', 'modern-file-manager' ), array( 'status' => 403 ) );
		}

		$written = @file_put_contents( $resolved, (string) $content, LOCK_EX );
		if ( false === $written ) {
			return new WP_Error( 'io_error', __( 'Unable to save file.', 'modern-file-manager' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'forbidden', __( 'Access to this path is blocked.', 'modern-file-manager' ), array( 'status' => 403 ) );
		}

		$candidate = wp_normalize_path( $this->root . ( '/' === $relative_path ? '' : $relative_path ) );
		$resolved  = realpath( $candidate );
		if ( false === $resolved ) {
			return new WP_Error( 'not_found', __( 'Path not found.', 'modern-file-manager' ), array( 'status' => 404 ) );
		}

		$resolved = wp_normalize_path( $resolved );
		if ( ! $this->is_within_root( $resolved ) ) {
			return new WP_Error( 'out_of_scope', __( 'Path is outside the allowed sandbox.', 'modern-file-manager' ), array( 'status' => 403 ) );
		}

		if ( $this->is_denied_path( $this->to_relative_path( $resolved ) ) ) {
			return new WP_Error( 'forbidden', __( 'Access to this path is blocked.', 'modern-file-manager' ), array( 'status' => 403 ) );
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
			return new WP_Error( 'out_of_scope', __( 'Path is outside the allowed sandbox.', 'modern-file-manager' ), array( 'status' => 403 ) );
		}

		if ( $this->is_denied_path( $this->to_relative_path( $target_abs ) ) ) {
			return new WP_Error( 'forbidden', __( 'This path is blocked by policy.', 'modern-file-manager' ), array( 'status' => 403 ) );
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
		return 0 === strpos( $absolute, $this->root );
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
			return new WP_Error( 'invalid_name', __( 'Please provide a valid file or folder name.', 'modern-file-manager' ), array( 'status' => 400 ) );
		}

		if ( preg_match( '#[/\\\\]#', $name ) ) {
			return new WP_Error( 'invalid_name', __( 'Invalid characters in file or folder name.', 'modern-file-manager' ), array( 'status' => 400 ) );
		}

		return $name;
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @param string $source Source directory.
	 * @param string $destination Destination directory.
	 * @return bool
	 */
	private function copy_directory_recursive( $source, $destination ) {
		if ( ! @mkdir( $destination, 0755 ) ) {
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
			$ok   = is_dir( $path ) ? $this->delete_directory_recursive( $path ) : @unlink( $path );
			if ( ! $ok ) {
				return false;
			}
		}

		return @rmdir( $directory );
	}
}
