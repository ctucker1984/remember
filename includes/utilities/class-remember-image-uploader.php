<?php
/**
 * Image uploader utility class
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/**
 * Image uploader utility class.
 *
 * Profile photos and location logos are stored as files under uploads/remember/
 * (not Media Library attachments). Ticket logos stay in the Media Library.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Image_Uploader {

	const SUBDIR_PHOTOS    = 'remember/photos';
	const SUBDIR_LOCATIONS = 'remember/locations';

	/**
	 * Active upload subdir while the upload_dir filter is attached.
	 *
	 * @var string
	 */
	private static $upload_subdir = '';

	/**
	 * Upload and resize an image to a square format.
	 *
	 * @param array  $file       $_FILES array for the image.
	 * @param int    $max_size   Maximum dimension (width/height) in pixels.
	 * @param string $subdir     Subdirectory under uploads (must start with remember/).
	 * @return array|WP_Error Array with 'url' and 'path', or WP_Error on failure.
	 */
	public static function upload_square_image( $file, $max_size = 600, $subdir = self::SUBDIR_PHOTOS ) {
		// Validate file
		if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded.', 'remember' ) );
		}

		// Validate image type
		$image_info = @getimagesize( $file['tmp_name'] );
		if ( false === $image_info ) {
			return new WP_Error( 'invalid_image', __( 'Invalid image file.', 'remember' ) );
		}

		$allowed_types = array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF );
		if ( ! in_array( $image_info[2], $allowed_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Only JPEG, PNG, and GIF images are allowed.', 'remember' ) );
		}

		$subdir = self::normalize_subdir( $subdir );
		self::ensure_upload_subdir( $subdir );

		self::$upload_subdir = $subdir;
		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		$uploaded_file = wp_handle_upload(
			$file,
			array( 'test_form' => false )
		);
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		self::$upload_subdir = '';

		if ( isset( $uploaded_file['error'] ) ) {
			return new WP_Error( 'upload_error', $uploaded_file['error'] );
		}

		$file_path = $uploaded_file['file'];
		$original_width = $image_info[0];
		$original_height = $image_info[1];

		// Resize and crop image
		$image_editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $image_editor ) ) {
			return $image_editor;
		}

		// Calculate crop dimensions (square, centered)
		$crop_size = min( $original_width, $original_height );
		$crop_x = ( $original_width - $crop_size ) / 2;
		$crop_y = ( $original_height - $crop_size ) / 2;

		// Crop to square first (centered)
		$cropped = $image_editor->crop( $crop_x, $crop_y, $crop_size, $crop_size );
		if ( is_wp_error( $cropped ) ) {
			return $cropped;
		}

		// Resize to max_size if needed
		if ( $crop_size > $max_size ) {
			$resized = $image_editor->resize( $max_size, $max_size, true );
			if ( is_wp_error( $resized ) ) {
				return $resized;
			}
		}

		// Save the image
		$saved = $image_editor->save( $file_path );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		// Update file path and URL if filename changed
		$upload_dir = wp_upload_dir( null, false );
		if ( isset( $saved['path'] ) ) {
			$file_path = $saved['path'];
			$uploaded_file['url'] = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $saved['path'] );
		} else {
			// Fallback: construct URL from file path
			$uploaded_file['url'] = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
		}

		return array(
			'url'  => $uploaded_file['url'],
			'path' => $file_path,
		);
	}

	/**
	 * Point WordPress uploads at our remember/ subfolder for one handle_upload call.
	 *
	 * @param array $dirs upload_dir array.
	 * @return array
	 */
	public static function filter_upload_dir( $dirs ) {
		if ( ! is_array( $dirs ) || empty( self::$upload_subdir ) ) {
			return $dirs;
		}
		$relative = '/' . self::$upload_subdir;
		$dirs['subdir'] = $relative;
		$dirs['path']   = untrailingslashit( $dirs['basedir'] ) . $relative;
		$dirs['url']    = untrailingslashit( $dirs['baseurl'] ) . $relative;
		return $dirs;
	}

	/**
	 * Delete an uploaded image file.
	 *
	 * @param string $file_url File URL to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_image( $file_url ) {
		$upload_dir = wp_upload_dir( null, false );
		if ( empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) ) {
			return false;
		}
		$file_path = wp_normalize_path( str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], (string) $file_url ) );
		$basedir   = wp_normalize_path( $upload_dir['basedir'] );
		if ( 0 !== strpos( $file_path, $basedir ) || ! is_file( $file_path ) ) {
			return false;
		}

		return unlink( $file_path );
	}

	/**
	 * Keep uploads under remember/, with safe path segments only.
	 *
	 * @param string $subdir Requested subdir.
	 * @return string
	 */
	private static function normalize_subdir( $subdir ) {
		$subdir = strtolower( str_replace( '\\', '/', (string) $subdir ) );
		$subdir = trim( $subdir, '/' );
		$parts  = explode( '/', $subdir );
		$clean  = array();
		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part || '..' === $part ) {
				continue;
			}
			if ( ! preg_match( '/^[a-z0-9_-]+$/', $part ) ) {
				continue;
			}
			$clean[] = $part;
		}
		if ( empty( $clean ) || 'remember' !== $clean[0] ) {
			return self::SUBDIR_PHOTOS;
		}
		return implode( '/', $clean );
	}

	/**
	 * Create the target folder and drop index.php files to discourage listing.
	 *
	 * @param string $subdir Normalized subdir.
	 * @return void
	 */
	private static function ensure_upload_subdir( $subdir ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}
		$walk = untrailingslashit( $uploads['basedir'] );
		foreach ( explode( '/', $subdir ) as $part ) {
			$walk .= '/' . $part;
			if ( ! wp_mkdir_p( $walk ) ) {
				continue;
			}
			$index = $walk . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
	}
}
