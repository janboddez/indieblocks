<?php
/**
 * Helper functions.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Returns this plugin's options.
 *
 * Roughly equal to `get_option( 'indieblocks_settings' )`.
 *
 * @return array Current plugin settings.
 */
function get_options() {
	return Plugin::get_instance()
		->get_options_handler()
		->get_options();
}

/**
 * Registers this plugin's _active_ permalinks, then flushes the rewrite cache.
 */
function flush_permalinks() {
	Post_Types::register_post_types();
	Post_Types::custom_permalinks();
	Post_Types::create_date_archives();
	Feeds::create_post_feed();

	flush_rewrite_rules();
}

/**
 * Writes to WordPress' debug log.
 *
 * @param mixed $item Thing to log.
 */
function debug_log( $item ) {
	error_log( is_string( $item ) ? $item : print_r( $item, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
}

/**
 * Wrapper around PHP's built-in `mb_detect_encoding()`.
 *
 * @param  string $text The string being inspected.
 * @return string       Detected encoding.
 */
function detect_encoding( $text ) {
	$encoding = mb_detect_encoding( $text );

	if ( 'ASCII' === $encoding ) {
		$encoding = 'UTF-8';
	}

	return $encoding;
}

/**
 * Converts HTML entities in a text string.
 *
 * @param  string $text The string being converted.
 * @return string       Entity-encoded string.
 */
function convert_encoding( $text ) {
	$text = mb_convert_encoding( $text, 'UTF-8', detect_encoding( $content ) );
	$text = mb_encode_numericentity( htmlentities( $text, ENT_QUOTES, 'UTF-8' ), array( 0x80, 0x10FFFF, 0, ~0 ), 'UTF-8' );

	return $text;
}

/**
 * Wrapper around `wp_remote_get()`.
 *
 * @param  string $url          URL to fetch.
 * @param  bool   $json         Whether to accept (only) JSON.
 * @return WP_Response|WP_Error Response.
 */
function remote_get( $url, $json = false ) {
	$args = array(
		'timeout'             => 11,
		'limit_response_size' => 1048576,
		'user-agent'          => get_user_agent(),
	);

	if ( $json ) {
		$args['headers'] = array( 'Accept' => 'application/json' );
	}

	$args = apply_filters( 'indieblocks_fetch_args', $args, $url );

	return wp_remote_get(
		esc_url_raw( $url ),
		$args
	);
}

/**
 * Wrapper around `wp_remote_post()`.
 *
 * @param  string $url          URL to fetch.
 * @param  bool   $json         Whether to accept (only) JSON.
 * @param  array  $args         Arguments for `wp_remote_post()`.
 * @return WP_Response|WP_Error Response.
 */
function remote_post( $url, $json = false, $args = array() ) {
	$args = array_merge(
		array(
			'timeout'             => 11,
			'limit_response_size' => 1048576,
			'user-agent'          => get_user_agent(),
		),
		$args
	);

	if ( $json ) {
		$args['headers'] = array( 'Accept' => 'application/json' );
	}

	$args = apply_filters( 'indieblocks_fetch_args', $args, $url );

	return wp_remote_post(
		esc_url_raw( $url ),
		$args
	);
}

/**
 * Returns a user agent.
 *
 * @param  string $url The URL we're looking to fetch.
 * @return string      User agent.
 */
function get_user_agent( $url = '' ) {
	$wp_version = get_bloginfo( 'version' );
	$user_agent = 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) . '; IndieBlocks';

	// Allow developers to override this user agent.
	return apply_filters( 'indieblocks_fetch_user_agent', $user_agent, $url );
}

/**
 * Whether webmentions are enabled.
 *
 * @param  int|\WP_Post $post Post ID or post object. Defaults to the global `$post`.
 * @return bool               Whether webmentions are open.
 */
function webmentions_open( $post = null ) {
	$post = get_post( $post );

	if ( null === $post ) {
		return false;
	}

	if ( ! in_array( $post->post_type, Webmention::get_supported_post_types(), true ) ) {
		// Unsupported post type.
		return false;
	}

	return apply_filters( 'indieblocks_webmentions_open', comments_open( $post ), $post );
}

/**
 * Parses post content for microformats (and then some).
 *
 * @param \WP_Post $post Post object.
 */
function post_content_parser( $post ) {
	$content = $post->post_content;

	if ( ! preg_match( '~ class=("|\')([^"\']*?)e-content([^"\']*?)("|\')~', $content ) ) {
		$content = '<div class="e-content">' . $content . '</div>';
	}

	$content = '<div class="h-entry">' . $content . '</div>';

	$parser = new Parser();
	$parser->parse( $content );

	return $parser;
}

/**
 * Returns a post's implied kind.
 *
 * @param  int|\WP_Post $post   Post ID or post object.
 * @param  array        $accept Accepted values.
 * @return string               The implied (per the post's microformats) post kind.
 */
function get_kind( $post, $accept = array() ) {
	$post = get_post( $post );
	$kind = get_post_meta( $post->ID, '_indieblocks_kind', true );

	if ( '' === $kind ) {
		$parser = post_content_parser( $post );
		$kind   = $parser->get_type();
	}

	if ( ! empty( $accept ) ) {
		$kind = in_array( $kind, $accept, true ) ? $kind : '';
	}

	if ( '' !== $kind ) {
		// Store for future use.
		update_post_meta( $post->ID, '_indieblocks_kind', $kind );
	}

	return $kind;
}

/**
 * Returns a post's first repost, like, or bookmark URL.
 *
 * @param  int|\WP_Post $post Post ID or post object.
 * @return string             URL, or an empty string.
 */
function get_linked_url( $post ) {
	$post       = get_post( $post );
	$linked_url = get_post_meta( $post->ID, '_indieblocks_linked_url', true );

	if ( '' === $linked_url ) {
		// We should really only ever do this for supported kinds.
		$parser     = post_content_parser( $post );
		$linked_url = $parser->get_link_url();

		if ( '' !== $linked_url ) {
			// Store for future use.
			update_post_meta( $post->ID, '_indieblocks_linked_url', esc_url_raw( $linked_url ) );
		}
	}

	return $linked_url;
}

/**
 * Stores a remote image locally.
 *
 * @param  string $url      Image URL.
 * @param  string $filename File name.
 * @param  string $dir      Target directory, relative to the uploads directory.
 * @param  string $width    Target width.
 * @param  string $height   Target height.
 * @return string|null      Local image URL, or nothing on failure.
 */
function store_image( $url, $filename, $dir, $width = 150, $height = 150 ) {
	$upload_dir = wp_upload_dir();
	$dir        = trailingslashit( $upload_dir['basedir'] ) . trim( $dir, '/' );

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir ); // Recursive directory creation. Permissions are taken from the nearest parent folder.
	}

	$file_path = trailingslashit( $dir ) . sanitize_file_name( $filename );

	if ( file_exists( $file_path ) && ( time() - filectime( $file_path ) ) < MONTH_IN_SECONDS ) {
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
			debug_log( '[IndieBlocks] Could not download the image at ' . esc_url_raw( $url ) . '.' );
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
			debug_log( '[IndieBlocks] Could not save image file: ' . $file_path . '.' );
			return null;
		}

		if ( ! function_exists( 'wp_crop_image' ) ) {
			// Load WordPress' image functions.
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! file_is_valid_image( $file_path ) || ! file_is_displayable_image( $file_path ) ) {
			// Somehow not a valid image. Delete it.
			unlink( $file_path );

			debug_log( '[IndieBlocks] Invalid image file: ' . esc_url_raw( $url ) . '.' );
			return null;
		}

		// Try to scale down and crop it.
		$image = wp_get_image_editor( $file_path );

		if ( ! is_wp_error( $image ) ) {
			$image->resize( $width, $height, true );
			$result = $image->save( $file_path );

			if ( $file_path !== $result['path'] ) {
				// The image editor's `save()` method has altered the file path (like, added an extension that wasn't there).
				wp_delete_file( $file_path ); // Delete "old" image.
				$file_path = $result['path'];
			}
		} else {
			debug_log( '[IndieBlocks] Could not resize ' . $file_path . ': ' . $image->get_error_message() . '.' );
		}

		// And return the local URL.
		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
	}
}

/**
 * Queries for a post's "facepile" comments.
 *
 * @param  int $post_id Post ID.
 * @return array        Facepile comments.
 */
function get_facepile_comments( $post_id ) {
	$facepile_comments = wp_cache_get( "indieblocks:facepile-comments:$post_id" );

	if ( false !== $facepile_comments ) {
		return $facepile_comments;
	}

	// When the "facepile" setting's enabled, we _remove_ the very comments
	// we now want to fetch, so we have to temporarily disable that
	// behavior.
	remove_action( 'pre_get_comments', array( \IndieBlocks\Webmention::class, 'comment_query' ) );

	// Placeholder.
	$facepile_comments           = new \stdClass();
	$facepile_comments->comments = array();

	// IndieBlocks' webmentions use custom fields to set them apart.
	$args = array(
		'post_id'    => $post_id,
		'fields'     => 'ids',
		'status'     => 'approve',
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'AND',
			array(
				'key'     => 'indieblocks_webmention_kind',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'indieblocks_webmention_kind',
				'compare' => 'IN',
				'value'   => apply_filters( 'indieblocks_facepile_kinds', array( 'bookmark', 'like', 'repost' ), $post_id ),
			),
		),
	);
	if ( 0 !== get_current_user_id() ) {
		$args['include_unapproved'] = array( get_current_user_id() );
	}
	$indieblocks_comments = new \WP_Comment_Query( $args );

	// The Webmention plugin's mentions use "proper" comment types.
	$args = array(
		'post_id'  => $post_id,
		'fields'   => 'ids',
		'status'   => 'approve',
		'type__in' => apply_filters( 'indieblocks_facepile_kinds', array( 'bookmark', 'like', 'repost' ), $post_id ),
	);
	if ( 0 !== get_current_user_id() ) {
		$args['include_unapproved'] = array( get_current_user_id() );
	}
	$webmention_comments = new \WP_Comment_Query( $args );

	$comment_ids = array_unique( array_merge( $indieblocks_comments->comments, $webmention_comments->comments ) );

	if ( ! empty( $comment_ids ) ) {
		// Grab 'em all.
		$facepile_comments = new \WP_Comment_Query(
			array(
				'comment__in' => $comment_ids,
				'post_id'     => $post_id,
				'order_by'    => 'comment_date',
				'order'       => 'ASC',
			)
		);
	}

	$options = get_options();
	if ( ! empty( $options['webmention_facepile'] ) ) {
		// Restore the filter disabled above, but only if it was active before!
		add_action( 'pre_get_comments', array( \IndieBlocks\Webmention::class, 'comment_query' ) );
	}

	// Allow filtering the resulting comments.
	$facepile_comments = apply_filters( 'indieblocks_facepile_comments', $facepile_comments->comments, $post_id );

	// Cache for the duration of the request (and then some)?
	wp_cache_set( "indieblocks:facepile-comments:$post_id", $facepile_comments, '', 10 );

	return $facepile_comments;
}

/**
 * Get post or comment meta.
 *
 * @param  \WP_Post|\WP_Comment $obj      Post or comment.
 * @param  string               $meta_key The meta key.
 * @return string                         The meta value (or an empty) string.
 */
function get_meta( $obj, $meta_key ) {
	if ( $obj instanceof \WP_Post ) {
		return get_post_meta( $obj->ID, $meta_key, true );
	}

	if ( $obj instanceof \WP_Comment ) {
		return get_comment_meta( $obj->comment_ID, $meta_key, true );
	}

	return '';
}

/**
 * Update post or comment meta.
 *
 * @param  \WP_Post|\WP_Comment $obj        Post or comment.
 * @param  string               $meta_key   The meta key.
 * @param  mixed                $meta_value The value.
 */
function add_meta( $obj, $meta_key, $meta_value ) {
	if ( $obj instanceof \WP_Post ) {
		add_post_meta( $obj->ID, $meta_key, $meta_value );
	}

	if ( $obj instanceof \WP_Comment ) {
		add_comment_meta( $obj->comment_ID, $meta_key, $meta_value );
	}
}

/**
 * Update post or comment meta.
 *
 * @param  \WP_Post|\WP_Comment $obj        Post or comment.
 * @param  string               $meta_key   The meta key.
 * @param  mixed                $meta_value The value.
 */
function update_meta( $obj, $meta_key, $meta_value ) {
	if ( $obj instanceof \WP_Post ) {
		update_post_meta( $obj->ID, $meta_key, $meta_value );
	}

	if ( $obj instanceof \WP_Comment ) {
		update_comment_meta( $obj->comment_ID, $meta_key, $meta_value );
	}
}

/**
 * Delete post or comment meta.
 *
 * @param  \WP_Post|\WP_Comment $obj      Post or comment.
 * @param  string               $meta_key The meta key.
 */
function delete_meta( $obj, $meta_key ) {
	if ( $obj instanceof \WP_Post ) {
		delete_post_meta( $obj->ID, $meta_key );
	}

	if ( $obj instanceof \WP_Comment ) {
		delete_comment_meta( $obj->comment_ID, $meta_key );
	}
}
