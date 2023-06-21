<?php
/**
 * All things "link preview" cards.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * An attempt to add microformats to block themes.
 */
class Preview_Cards {
	/**
	 * Registers action callbacks.
	 */
	public static function register() {
		add_filter( 'publish_indieblocks_note', array( __CLASS__, 'schedule' ), 20, 2 );
		add_filter( 'publish_indieblocks_like', array( __CLASS__, 'schedule' ), 20, 2 );

		add_action( 'indieblocks_preview_card', array( __CLASS__, 'add_meta' ) );
	}

	/**
	 * Schedules the "generation" of link preview cards.
	 *
	 * @param  int      $post_id Post ID.
	 * @param  \WP_Post $post    Post objact.
	 */
	public static function schedule( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
			return;
		}

		// We could try and parse Open Graph metadata immediately, but let's
		// schedule it into the very near future instead, to speed up publishing
		// a bit.
		wp_schedule_single_event( time() + wp_rand( 0, 300 ), 'indieblocks_preview_card', array( $post->ID ) );
	}

	/**
	 * Saves preview card metadata as custom fields.
	 *
	 * @param int $post_id  Post ID.
	 */
	public static function add_meta( $post_id ) {
		$post = get_post( $post_id );

		if ( empty( $post->post_content ) ) {
			return;
		}

		$parser     = post_content_parser( $post );
		$linked_url = $parser->get_link_url( false );

		$parser = new Parser( $linked_url );
		$parser->parse();

		$name = $parser->get_name();
		if ( '' !== $name ) {
			update_post_meta( $post_id, '_indieblocks_og_title', $name );
		} else {
				delete_post_meta( $post_id, '_indieblocks_og_title' );
		}

		$image = $parser->get_image();
		if ( '' !== $image ) {
			$thumb = static::create_thumbnail( $image, $post );
			if ( '' !== $thumb ) {
				update_post_meta( $post_id, '_indieblocks_og_image', $thumb );
			} else {
				// @todo: Delete image?
				delete_post_meta( $post_id, '_indieblocks_og_image' );
			}
		}
	}

	/**
	 * Create a link preview thumbnail and return its local URL.
	 *
	 * @todo: There's a _lot_ of overlap here with the webmention avatar code.
	 *
	 * @param  string   $url  Image URL.
	 * @param  \WP_Post $post Post objact.
	 */
	public static function create_thumbnail( $url, $post ) {
		// Get the WordPress upload dir.
		$upload_dir = wp_upload_dir();
		$card_dir   = trailingslashit( $upload_dir['basedir'] ) . 'indieblocks-cards';

		if ( ! empty( $upload_dir['subdir'] ) ) {
			// Add month and year, to be able to keep track of things.
			$card_dir .= '/' . trim( $upload_dir['subdir'], '/' );
		}

		if ( ! is_dir( $card_dir ) ) {
			// This'll create, e.g., `wp-content/uploads/indieblocks-cards/` or `wp-content/uploads/indieblocks-cards/2023/06/`.
			wp_mkdir_p( $card_dir ); // Recursive directory creation. Permissions are taken from, normally, the `uploads` folder.
		}

		$slug      = $post->post_name;
		$ext       = pathinfo( $url, PATHINFO_EXTENSION );
		$filename  = $slug . ( ! empty( $ext ) ? '.' . $ext : '' );
		$file_path = trailingslashit( $card_dir ) . $filename;

		if ( file_exists( $file_path ) && ( time() - filectime( $file_path ) ) < MONTH_IN_SECONDS ) { // @todo: Reevaluate in the context of using subdirs.
			// File exists and is under a month old.
			return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
		} else {
			// Attempt to download the image.
			$response = remote_get(
				esc_url_raw( $url ),
				false,
				array( 'headers' => array( 'Accept' => 'image/*' ) )
			);

			$body = wp_remote_retrieve_body( $response );

			if ( empty( $body ) ) {
				error_log( '[IndieBlocks/Preview_Cards] Could not download the image at ' . esc_url_raw( $url ) . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return null;
			}

			// Now store it locally.
			global $wp_filesystem;

			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			// Write image data.
			if ( ! $wp_filesystem->put_contents( $file_path, $body, 0644 ) ) {
				error_log( '[IndieBlocks/Preview_Cards] Could not save image file: ' . $file_path . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return null;
			}

			if ( ! function_exists( 'wp_crop_image' ) ) {
				// Load WordPress' image functions.
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			if ( ! file_is_valid_image( $file_path ) || ! file_is_displayable_image( $file_path ) ) {
				// Somehow not a valid image. Delete it.
				unlink( $file_path );

				error_log( '[IndieBlocks/Preview_Cards] Invalid image file: ' . esc_url_raw( $url ) . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return null;
			}

			// Try to scale down and crop it.
			$image = wp_get_image_editor( $file_path );

			if ( ! is_wp_error( $image ) ) {
				$image->resize( 150, 150, true );
				$result = $image->save( $file_path );

				if ( $file_path !== $result['path'] ) {
					// The image editor's `save()` method has altered the file path (like, added an extension that wasn't there).
					unlink( $file_path ); // Delete "old" image.
					$file_path = $result['path'];
				};
			} else {
				error_log( '[IndieBlocks/Preview_Cards] Could not reisize ' . $file_path . ': ' . $image->get_error_message() . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			// And return the local URL.
			return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
		}
	}
}
