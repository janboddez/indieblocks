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

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_field' ) );
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

		// We could try and parse Open Graph metadata immediately, but let's
		// schedule it into the near future instead, to speed up publishing a
		// bit.
		wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'indieblocks_preview_card', array( $post->ID ) );
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
		$linked_url = $parser->get_link_url( false ); // Get the first link, regardless of microformats.

		if ( empty( $linked_url ) ) {
			delete_post_meta( $post_id, '_indieblocks_link_preview' );
			return;
		}

		$parser = new Parser( $linked_url );
		$parser->parse();

		$name = $parser->get_name( false ); // Also consider non-mf2 titles, e.g., for notes.
		if ( '' === $name ) {
			delete_post_meta( $post_id, '_indieblocks_link_preview' );
			return;
		}

		$thumbnail = '';
		$image     = $parser->get_image();
		if ( '' !== $image ) {
			$thumbnail = static::create_thumbnail( $image, $post );
		}

		$meta = array_filter( // Remove empty values.
			array(
				'title'     => $name,
				'url'       => $linked_url,
				'thumbnail' => $thumbnail,
			)
		);
		update_post_meta( $post->ID, '_indieblocks_link_preview', $meta );
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

	/**
	 * Registers a custom REST API endpoint for reading (but not writing) our
	 * location data.
	 */
	public static function register_rest_field() {
		foreach ( array( 'post', 'indieblocks_note', 'indieblocks_like' ) as $post_type ) {
			register_rest_field(
				$post_type,
				'indieblocks_link_preview',
				array(
					'get_callback'    => array( __CLASS__, 'get_meta' ),
					'update_callback' => null,
				)
			);
		}
	}

	/**
	 * Returns link preview metadata.
	 *
	 * @param  array $params WP REST API request.
	 * @return mixed         Response.
	 */
	public static function get_meta( $params ) {
		$post_id = $params['id'];

		if ( empty( $post_id ) || ! is_int( $post_id ) ) {
			return new \WP_Error( 'invalid_id', 'Invalid post ID.', array( 'status' => 400 ) );
		}

		return get_post_meta( (int) $post_id, '_indieblocks_link_preview', true ); // Either an empty string, or an associated array (which gets translated into a JSON object).
	}
}
