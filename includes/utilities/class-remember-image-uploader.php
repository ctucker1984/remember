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
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Image_Uploader {

	/**
	 * Upload and resize an image to a square format.
	 *
	 * @param array  $file       $_FILES array for the image.
	 * @param int    $max_size   Maximum dimension (width/height) in pixels.
	 * @param string $subdir     Subdirectory in uploads (optional).
	 * @return array|WP_Error Array with 'url' and 'path', or WP_Error on failure.
	 */
	public static function upload_square_image( $file, $max_size = 600, $subdir = 'remember' ) {
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

		// Use WordPress upload handler
		$upload_overrides = array( 'test_form' => false );
		$uploaded_file = wp_handle_upload( $file, $upload_overrides );

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
		$upload_dir = wp_upload_dir();
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
	 * Delete an uploaded image file.
	 *
	 * @param string $file_url File URL to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_image( $file_url ) {
		$upload_dir = wp_upload_dir();
		$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $file_url );
		
		if ( file_exists( $file_path ) ) {
			return unlink( $file_path );
		}
		
		return false;
	}
}
