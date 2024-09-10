<?php
/**
 * Helper functions.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

use IndieBlocks\Webmention\Webmention;

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
	return mb_encode_numericentity( $text, array( 0x80, 0x10FFFF, 0, ~0 ), detect_encoding( $text ) );
}

/**
 * Wrapper around `wp_safe_remote_get()`.
 *
 * @param  string $url            URL to fetch.
 * @param  bool   $json           Whether to accept (only) JSON.
 * @return \WP_Response|\WP_Error Response.
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

	return wp_safe_remote_get(
		esc_url_raw( $url ),
		$args
	);
}

/**
 * Wrapper around `wp_safe_remote_post()`.
 *
 * @param  string $url            URL to fetch.
 * @param  bool   $json           Whether to accept (only) JSON.
 * @param  array  $args           Arguments for `wp_safe_remote_post()`.
 * @return \WP_Response|\WP_Error Response.
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

	return wp_safe_remote_post(
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
	if ( 0 === strpos( $url, home_url() ) ) {
		// Not a remote URL.
		return $url;
	}

	$upload_dir = wp_upload_dir();
	$dir        = trailingslashit( $upload_dir['basedir'] ) . trim( $dir, '/' );

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir ); // Recursive directory creation. Permissions are taken from the nearest parent folder.
	}

	// Where we'll eventually store the image.
	$file_path = trailingslashit( $dir ) . sanitize_file_name( $filename );

	if ( file_exists( $file_path ) && ( time() - filectime( $file_path ) ) < MONTH_IN_SECONDS ) {
		// File exists and is under a month old. We're done here.
		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
	}

	// Not all image URLs end in a file extension. (Gravatar's URLs come to
	// mind.) We used to store them like that (without extension), but, e.g.,
	// the S3 Uploads plugin doesn't play 100% nice with such images, and we now
	// try to give 'em an extension after all. Either way, look for an existing
	// file, but allow an(y) additional extension.
	foreach ( glob( "$file_path.*" ) as $match ) {
		$file_path = $match;

		if ( ( time() - filectime( $file_path ) ) < MONTH_IN_SECONDS ) {
			// So, _this_ file exists and is under a month old. Let's return it.
			return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
		}

		break; // Stop after the first match.
	}

	// To be able to move files around.
	global $wp_filesystem;

	if ( ! function_exists( 'download_url' ) || empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	// OK, so either the file doesn't exist or is over a month old. Attempt to
	// download the image.
	$temp_file = download_url( esc_url_raw( $url ) );

	if ( is_wp_error( $temp_file ) ) {
		debug_log( '[IndieBlocks] Could not download the image at ' . esc_url_raw( $url ) . '.' );
		return null;
	}

	if ( '' === pathinfo( $file_path, PATHINFO_EXTENSION ) && function_exists( 'mime_content_type' ) ) {
		// Some filesystem drivers--looking at you, S3 Uploads--take issue with
		// extensionless files.
		$mime = mime_content_type( $temp_file );

		if ( is_string( $mime ) ) {
			$mimes = new Mimey\MimeTypes(); // A MIME type to file extension map, essentially.
			$ext   = $mimes->getExtension( $mime );
		}
	}

	if ( ! empty( $ext ) ) {
		if ( '' === pathinfo( $temp_file, PATHINFO_EXTENSION ) ) {
			// If our temp file is missing an extension, too, rename it before
			// attempting to run any image resizing functions on it.
			if ( $wp_filesystem->move( $temp_file, "$temp_file.$ext" ) ) {
				// Successfully renamed the file.
				$temp_file .= ".$ext";
			} elseif ( $wp_filesystem->put_contents( "$temp_file.$ext", $wp_filesystem->get_contents( $temp_file ), 0644 ) ) {
				// This here mainly because, once again,  plugins like S3
				// Uploads, or rather, the AWS SDK for PHP, doesn't always play
				// nice with `WP_Filesystem::move()`.
				wp_delete_file( $temp_file ); // Delete the original.
				$temp_file .= ".$ext"; // Our new file path from here on out.
			}
		}

		// Tack our newly discovered extension onto our target file name, too.
		$file_path .= ".$ext";
	}

	if ( ! function_exists( 'wp_crop_image' ) ) {
		// Load WordPress' image functions.
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	if ( ! file_is_valid_image( $temp_file ) || ! file_is_displayable_image( $temp_file ) ) {
		debug_log( '[IndieBlocks] Invalid image file: ' . esc_url_raw( $url ) . '.' );

		// Delete temp file and return.
		wp_delete_file( $temp_file );

		return null;
	}

	// Move the altered file to its final destination.
	if ( ! $wp_filesystem->move( $temp_file, $file_path ) ) {
		// If `WP_Filesystem::move()` failed, do it this way.
		$wp_filesystem->put_contents( $file_path, $wp_filesystem->get_contents( $temp_file ), 0644 );
		wp_delete_file( $temp_file ); // Always delete the original.
	}

	// Try to scale down and crop it. Somehow, at least in combination with S3
	// Uploads, `WP_Image_Editor::save()` attempts to write the image to S3
	// storage, which I guess fails because, well, for one, the path doesn't
	// match. Which is why we moved it before doing this.
	$image = wp_get_image_editor( $file_path );

	if ( ! is_wp_error( $image ) ) {
		$image->resize( $width, $height, true );
		$result = $image->save( $file_path );

		if ( isset( $result['path'] ) && $file_path !== $result['path'] ) {
			// The image editor's `save()` method has altered our temp file's
			// path (e.g., added an extension that wasn't there).
			wp_delete_file( $file_path ); // Delete "old" image.
			$file_path = $result['path']; // And update the file path (and name).
		} elseif ( is_wp_error( $result ) ) {
			debug_log( "[IndieBlocks] Could not resize $file_path: " . $result->get_error_message() . '.' );
		}
	} else {
		debug_log( "[IndieBlocks] Could not load $file_path into WordPress' image editor: " . $image->get_error_message() . '.' );
	}

	// And return the local URL.
	return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
}

/**
 * Queries for a post's "facepile" comments.
 *
 * @param  int      $post_id Post ID.
 * @param  string[] $types   Comment types to include.
 * @return array             Facepile comments.
 */
function get_facepile_comments( $post_id, $types ) {
	$facepile_comments = wp_cache_get( md5( "indieblocks:facepile-comments:{$post_id}:" . wp_json_encode( $types ) ) );

	if ( false !== $facepile_comments ) {
		return $facepile_comments;
	}

	// When the "facepile" setting's enabled, we remove the very comments we now want to fetch, so we have to
	// temporarily disable that behavior.
	remove_action( 'pre_get_comments', array( Webmention::class, 'comment_query' ) );

	/* @todo: Check if we could maybe get rid of the `class_exist()` calls. */
	if ( class_exists( '\\Webmention\\Comment_Walker' ) && method_exists( \Webmention\Comment_Walker::class, 'comment_query' ) ) {
		// The Webmention plugin, as of v5.3.0, also modifies the comment query.
		$wm_removed = remove_action( 'pre_get_comments', array( \Webmention\Comment_Walker::class, 'comment_query' ) );
	}

	if ( class_exists( '\\Activitypub\\Comment' ) && method_exists( \Activitypub\Comment::class, 'comment_query' ) ) {
		// The ActivityPub plugin, as of v3.2.2, also modifies the comment query.
		$ap_removed = remove_action( 'pre_get_comments', array( \Activitypub\Comment::class, 'comment_query' ) );
	}

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
				'value'   => apply_filters( 'indieblocks_facepile_kinds', $types, $post_id ),
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
		'type__in' => apply_filters( 'indieblocks_facepile_kinds', $types, $post_id ),
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
		add_action( 'pre_get_comments', array( Webmention::class, 'comment_query' ) );
	}

	if ( ! empty( $wm_removed ) ) {
		// Restore also the Webmention plugin's callback.
		add_action( 'pre_get_comments', array( \Webmention\Comment_Walker::class, 'comment_query' ) );
	}

	if ( ! empty( $ap_removed ) ) {
		// Restore also the ActivityPub plugin's callback.
		add_action( 'pre_get_comments', array( \Activitypub\Comment::class, 'comment_query' ) );
	}

	// Allow filtering the resulting comments.
	$facepile_comments = apply_filters( 'indieblocks_facepile_comments', $facepile_comments->comments, $post_id );

	// Cache for the duration of the request (and then some)?
	wp_cache_set( md5( "indieblocks:facepile-comments:{$post_id}:" . wp_json_encode( $types ) ), $facepile_comments, '', 10 );

	return $facepile_comments;
}

/**
 * Get post or comment meta.
 *
 * @param  \WP_Post|\WP_Comment $obj      Post or comment.
 * @param  string               $meta_key The meta key.
 * @return mixed                          The meta value (or an empty string).
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
 * Update (or add) post or comment meta.
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

/**
 * Echoes the "facepile" icons.
 */
function print_facepile_icons() {
	$icons = dirname( __DIR__ ) . '/assets/webmention-icons.svg';

	if ( is_readable( $icons ) ) {
		require_once $icons;
	}
}

/**
 * Replaces a media URL with its "proxy" alternative.
 *
 * @param  string $url Media URL.
 * @return string      Proxy URL.
 */
function proxy_image( $url ) {
	$options = get_options();

	if ( empty( $options['image_proxy'] ) ) {
		return $url;
	}

	if ( empty( $options['image_proxy_secret'] ) ) {
		return $url;
	}

	if ( ! empty( $options['image_proxy_http_only'] ) && 0 === stripos( $url, 'https://' ) ) {
		return $url;
	}

	$query_string = http_build_query(
		array(
			'hash' => hash_hmac( 'sha1', $url, $options['image_proxy_secret'] ),
			'url'  => rawurlencode( $url ),
		)
	);

	return get_rest_url( null, '/indieblocks/v1/imageproxy' ) . "?$query_string";
}

/**
 * Recursively search `innerBlocks`.
 *
 * @link https://gist.github.com/bjorn2404/9b2b98b18c2fe47570895a63c62b8a93
 *
 * @param  array  $blocks     Blocks to search through.
 * @param  string $block_name The type of block to search for.
 * @return array              Matching blocks.
 */
function parse_inner_blocks( $blocks, $block_name ) {
	$block_data = array();

	if ( ! is_array( $blocks ) ) {
		return $block_data;
	}

	foreach ( $blocks as $block ) {
		if ( ! empty( $block['innerBlocks'] ) && $block_name !== $block['blockName'] ) {
			$inner_data = parse_inner_blocks( $block['innerBlocks'], $block_name );
			$block_data = array_merge( $block_data, $inner_data );
		} elseif ( $block_name === $block['blockName'] ) {
			$block_data[] = $block;
		}
	}

	return $block_data;
}
