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
	 * @param  \WP_Post $post    Post object.
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

			if ( ! empty( $thumb ) ) {
				update_post_meta( $post_id, '_indieblocks_og_image', $thumb );
			} else {
				// @todo: Delete image?
				delete_post_meta( $post_id, '_indieblocks_og_image' );
			}
		}
	}

	/**
	 * Creates a link preview thumbnail and returns its local URL.
	 *
	 * @param  string $url Image URL.
	 * @return string      Local thumbnail URL.
	 */
	protected static function create_thumbnail( $url ) {
		$dir = 'indieblocks-cards';

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['subdir'] ) ) {
			// Add month and year, to be able to keep track of things.
			$dir .= '/' . trim( $upload_dir['subdir'], '/' );
		}

		$hash     = hash( 'sha256', esc_url_raw( $url ) );
		$ext      = pathinfo( $url, PATHINFO_EXTENSION );
		$filename = $hash . ( ! empty( $ext ) ? '.' . $ext : '' );

		return store_image( $url, $filename, $dir );
	}
}
