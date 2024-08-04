<?php
/**
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Bundles ActivityPub hook callbacks.
 */
class ActivityPub_Compat {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		if ( ! defined( 'INDIEBLOCKS_ACTIVITYPUB_INTEGRATION' ) || ! INDIEBLOCKS_ACTIVITYPUB_INTEGRATION ) {
			return;
		}

		/** @todo: If we were to run this at `plugins_loaded` or so, we can maybe check for dependencies _here_ rather than in the various callbacks. */
		add_filter( 'activitypub_activity_object_array', array( __CLASS__, 'add_in_reply_to_url' ), 99, 2 );

		add_filter( 'activitypub_activity_object_array', array( __CLASS__, 'transform_to_announce' ), 99, 2 );
		add_filter( 'activitypub_activity_object_array', array( __CLASS__, 'transform_to_undo_announce' ), 99, 2 );

		add_filter( 'activitypub_activity_object_array', array( __CLASS__, 'transform_to_like' ), 99, 2 );
		add_filter( 'activitypub_activity_object_array', array( __CLASS__, 'transform_to_undo_like' ), 99, 2 );

		add_filter( 'activitypub_extract_mentions', array( __CLASS__, 'add_mentions' ), 99, 3 );
	}

	/**
	 * Adds the `inReplyTo` property to reply posts.
	 *
	 * @param  array  $array Activity or object (array).
	 * @param  string $class Class name.
	 * @return array         The updated array.
	 */
	public static function add_in_reply_to_url( $array, $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
		if ( ! class_exists( '\\Activitypub\\Http' ) ) {
			return $array;
		}

		if ( ! method_exists( \Activitypub\Http::class, 'get_remote_object' ) ) {
			return $array;
		}

		if ( ! function_exists( '\\Activitypub\\object_to_uri' ) ) {
			return $array;
		}

		if ( 'activity' === $class && ! empty( $array['object']['inReplyTo'] ) ) {
			// An `inReplyTo` property already exists.
			return $array;
		}

		if ( 'base_object' === $class && ! empty( $array['inReplyTo'] ) ) {
			// An `inReplyTo` property already exists.
			return $array;
		}

		// Retrieve the original WP object.
		$post_or_comment = static::get_wp_object( $array, $class );
		if ( ! $post_or_comment || ! $post_or_comment instanceof \WP_Post ) {
			// We only support posts (and not comments). Comments already support replying to Fediverse statuses.
			return $array;
		}

		$post_content = apply_filters( 'the_content', $post_or_comment->post_content );

		$in_reply_to_url = static::get_in_reply_to_url( $post_content );
		if ( empty( $in_reply_to_url ) ) {
			// Could not find an `in-reply-to` URL.
			return $array;
		}

		$remote_object = \Activitypub\Http::get_remote_object( $in_reply_to_url );
		if ( ! is_array( $remote_object ) || empty( $remote_object['attributedTo'] ) ) {
			return $array;
		}

		$actor_url = \Activitypub\object_to_uri( $remote_object['attributedTo'] );
		if ( empty( $actor_url ) ) {
			return $array;
		}

		// Found an `in-reply-to` and an actor URL. Add `inReplyTo` property.
		if ( 'activity' === $class ) {
			$array['object']['inReplyTo'] = $in_reply_to_url;
		} elseif ( 'base_object' === $class ) {
			$array['inReplyTo'] = $in_reply_to_url;
		}

		// Trim any reply context off the post content. Because important bits of said content may actually be inside
		// Reply block, we can't just not render it. But we could render its inner blocks, and any other blocks.
		// Eventually. For now, a regex will have to do.
		if ( preg_match( '~<div class="e-content">.+?</div>~s', $post_content, $match ) ) {
			$copy               = clone $post_or_comment;
			$copy->post_content = $match[0]; // The `e-content` only, without reply context (if any).

			// Regenerate "ActivityPub content" using the "slimmed down" post content. We ourselves use the
			// "original" post, hence the need to pass a copy with modified content.
			// Caveat: Any blocks outside (the Reply block's) `e-content` get ignored!
			$content = apply_filters( 'activitypub_the_content', $match[0], $copy );

			if ( 'activity' === $class ) {
				$array['object']['content'] = $content;

				foreach ( $array['object']['contentMap'] as $locale => $value ) {
					$array['object']['contentMap'][ $locale ] = $content;
				}
			} elseif ( 'base_object' === $class ) {
				$array['content'] = $content;

				foreach ( $array['contentMap'] as $locale => $value ) {
					$array['contentMap'][ $locale ] = $content;
				}
			}
		}

		return $array;
	}

	/**
	 * Turns a "repost" into an ActivityPub Announce activity.
	 *
	 * @param  array  $array Activity or object (array).
	 * @param  string $class Class name.
	 * @return array         The updated array.
	 */
	public static function transform_to_announce( $array, $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
		if ( ! class_exists( '\\Activitypub\\Http' ) ) {
			return $array;
		}

		if ( ! method_exists( \Activitypub\Http::class, 'get_remote_object' ) ) {
			return $array;
		}

		if ( ! function_exists( '\\Activitypub\\object_to_uri' ) ) {
			return $array;
		}

		if ( 'activity' === $class && ! empty( $array['object']['inReplyTo'] ) ) {
			// As per "the IndieWeb's 'post type discovery' algorithm," a reply is, first and foremost, a reply.
			return $array;
		}

		if ( 'base_object' === $class && ! empty( $array['inReplyTo'] ) ) {
			return $array;
		}

		// Retrieve the original WP object.
		$post_or_comment = static::get_wp_object( $array, $class );
		if ( ! $post_or_comment || ! $post_or_comment instanceof \WP_Post ) {
			// We only support posts (and not comments). Comments already support replying to Fediverse statuses.
			return $array;
		}

		$post_content = apply_filters( 'the_content', $post_or_comment->post_content );

		$repost_of_url = static::get_repost_of_url( $post_content );
		if ( empty( $repost_of_url ) ) {
			// Could not find a `repost-of` URL.
			return $array;
		}

		$remote_object = \Activitypub\Http::get_remote_object( $repost_of_url );
		if ( ! is_array( $remote_object ) || empty( $remote_object['attributedTo'] ) ) {
			return $array;
		}

		$actor_url = \Activitypub\object_to_uri( $remote_object['attributedTo'] );
		if ( empty( $actor_url ) ) {
			return $array;
		}

		// Found a `repost-of` and an actor URL. Turn `Create` (and `Update`) activities into Announces.
		if (
			'base_object' === $class ||
			( 'activity' === $class && isset( $array['type'] ) && in_array( $array['type'], array( 'Create', 'Update' ), true ) )
		) {
			/**
			 * Mastodon example:
			 *
			 * ```
			 * array(
			 *     '@context'  => 'https://www.w3.org/ns/activitystreams',
			 *     'id'        => 'https://indieweb.social/users/janboddez/statuses/112475177142233425/activity', // The Announce activity JSON is actually served at this URL.
			 *     'type'      => 'Announce',
			 *     'actor'     => 'https://indieweb.social/users/janboddez',
			 *     'published' => '2024-05-20T19:56:42Z',
			 *     'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			 *     'cc'        => array(
			 *         'https://jan.boddez.net/author/jan',
			 *         'https://indieweb.social/users/janboddez/followers',
			 *     ),
			 *     'object'    => 'https://jan.boddez.net/notes/39ed3b1cfb',
			 * )
			 * ```
			 */
			$array = array_intersect_key(
				$array,
				array_flip( array( '@context', 'id', 'type', 'actor', 'published', 'to', 'cc', 'object' ) )
			);

			// Rework the top-line ID a bit.
			$array['id'] = strtok( $array['id'], '#' ) . '#activity';
			strtok( '', '' );

			$array['type']   = 'Announce';
			$array['object'] = esc_url_raw( $repost_of_url );

			if ( 'activity' === $class ) {
				// We want to get this right when we `Undo` reposts.
				add_post_meta( $post_or_comment->ID, '_indieblocks_activitypub_announce', $array, true );
			}
		}

		return $array;
	}

	/**
	 * Turns deletion of a "repost" into an ActivityPub Undo (Announce) activity.
	 *
	 * @param  array  $array Activity or object (array).
	 * @param  string $class Class name.
	 * @return array         The updated array.
	 */
	public static function transform_to_undo_announce( $array, $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
		if ( 'activity' !== $class ) {
			return $array;
		}

		if ( ! isset( $array['type'] ) || 'Delete' !== $array['type'] ) {
			return $array;
		}

		// Retrieve the original WP object.
		$post_or_comment = static::get_wp_object( $array, $class );
		if ( ! $post_or_comment || ! $post_or_comment instanceof \WP_Post ) {
			return $array;
		}

		$announce = get_post_meta( $post_or_comment->ID, '_indieblocks_activitypub_announce', true );
		if ( empty( $announce ) ) {
			return $array;
		}

		$array['type']   = 'Undo'; // Rather than Delete.
		$array['object'] = $announce;

		update_post_meta( $post_or_comment->ID, '_indieblocks_activitypub_undo_announce', $array );
		delete_post_meta( $post_or_comment->ID, '_indieblocks_activitypub_announce' );

		return $array;
	}

	/**
	 * Turns a "like" into an ActivityPub Like activity.
	 *
	 * @param  array  $array Activity or object (array).
	 * @param  string $class Class name.
	 * @return array         The updated array.
	 */
	public static function transform_to_like( $array, $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
		if ( ! class_exists( '\\Activitypub\\Http' ) ) {
			return $array;
		}

		if ( ! method_exists( \Activitypub\Http::class, 'get_remote_object' ) ) {
			return $array;
		}

		if ( ! function_exists( '\\Activitypub\\object_to_uri' ) ) {
			return $array;
		}

		if (
			'activity' === $class &&
			( ! empty( $array['object']['inReplyTo'] ) || isset( $array['type'] ) && 'Announce' === $array['type'] )
		) {
			// As per the "IndieWeb's" 'post type discovery' algorithm.
			return $array;
		}

		if ( 'base_object' === $class && ! empty( $array['inReplyTo'] ) ) {
			return $array;
		}

		// Retrieve the original WP object.
		$post_or_comment = static::get_wp_object( $array, $class );
		if ( ! $post_or_comment || ! $post_or_comment instanceof \WP_Post ) {
			// We only support posts (and not comments). Comments already support replying to Fediverse statuses.
			return $array;
		}

		$post_content = apply_filters( 'the_content', $post_or_comment->post_content );

		$like_of_url = static::get_like_of_url( $post_content );
		if ( empty( $like_of_url ) ) {
			// Could not find a `like-of` URL.
			return $array;
		}

		$remote_object = \Activitypub\Http::get_remote_object( $like_of_url );
		if ( ! is_array( $remote_object ) || empty( $remote_object['attributedTo'] ) ) {
			return $array;
		}

		$actor_url = \Activitypub\object_to_uri( $remote_object['attributedTo'] );
		if ( empty( $actor_url ) ) {
			return $array;
		}

		// Found a `like-of` and an actor URL. Turn `Create` (and `Update`) activities into Announces.
		if (
			'base_object' === $class ||
			( 'activity' === $class && isset( $array['type'] ) && in_array( $array['type'], array( 'Create', 'Update' ), true ) )
		) {
			$array = array_intersect_key(
				$array,
				array_flip( array( '@context', 'id', 'type', 'actor', 'published', 'to', 'cc', 'object' ) )
			);

			// Rework the top-line ID a bit.
			$array['id'] = strtok( $array['id'], '#' ) . '#activity';
			strtok( '', '' );

			$array['type']   = 'Like';
			$array['object'] = esc_url_raw( $like_of_url );

			if ( 'activity' === $class ) {
				// We want to get this right when we `Undo` reposts.
				add_post_meta( $post_or_comment->ID, '_indieblocks_activitypub_like', $array, true );
			}
		}

		return $array;
	}

	/**
	 * Turns deletion of a "like" into an ActivityPub Undo (Like) activity.
	 *
	 * @param  array  $array Activity or object (array).
	 * @param  string $class Class name.
	 * @return array         The updated array.
	 */
	public static function transform_to_undo_like( $array, $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
		if ( 'activity' !== $class ) {
			return $array;
		}

		if ( ! isset( $array['type'] ) || 'Delete' !== $array['type'] ) {
			return $array;
		}

		// Retrieve the original WP object.
		$post_or_comment = static::get_wp_object( $array, $class );
		if ( ! $post_or_comment || ! $post_or_comment instanceof \WP_Post ) {
			return $array;
		}

		$like = get_post_meta( $post_or_comment->ID, '_indieblocks_activitypub_like', true );
		if ( empty( $like ) ) {
			return $array;
		}

		$array['type']   = 'Undo'; // Rather than Delete.
		$array['object'] = $like;

		update_post_meta( $post_or_comment->ID, '_indieblocks_activitypub_undo_like', $array );
		delete_post_meta( $post_or_comment->ID, '_indieblocks_activitypub_like' );

		return $array;
	}

	/**
	 * Adds a "mention" to reply posts.
	 *
	 * We want the remote post's author to know about our reply. This ensures a
	 * `Mention` tag gets added, and that they get added to the `cc` field.
	 *
	 * @param  array    $mentions     Associative array of accounts to mention.
	 * @param  string   $post_content Post content.
	 * @param  \WP_Post $wp_object    Post (or comment?) object.
	 * @return array                  Filtered array.
	 */
	public static function add_mentions( $mentions, $post_content, $wp_object ) {
		if ( ! class_exists( '\\Activitypub\\Http' ) ) {
			return $mentions;
		}

		if ( ! method_exists( \Activitypub\Http::class, 'get_remote_object' ) ) {
			return $mentions;
		}

		if ( ! function_exists( '\\Activitypub\\object_to_uri' ) ) {
			return $mentions;
		}

		if ( ! function_exists( '\\Activitypub\\get_remote_metadata_by_actor' ) ) {
			return $mentions;
		}

		if ( ! $wp_object instanceof \WP_Post ) {
			return $mentions;
		}

		$in_reply_to_url = static::get_in_reply_to_url( $post_content );
		if ( ! empty( $in_reply_to_url ) ) {
			$remote_object = \Activitypub\Http::get_remote_object( $in_reply_to_url );
		} else {
			$repost_of_url = static::get_repost_of_url( $post_content );
			if ( ! empty( $repost_of_url ) ) {
				$remote_object = \Activitypub\Http::get_remote_object( $repost_of_url );
			} else {
				$like_of_url = static::get_like_of_url( $post_content );
				if ( ! empty( $like_of_url ) ) {
					$remote_object = \Activitypub\Http::get_remote_object( $like_of_url );
				}
			}
		}

		if ( empty( $remote_object ) || ! is_array( $remote_object ) || empty( $remote_object['attributedTo'] ) ) {
			return $mentions;
		}

		$actor_url = \Activitypub\object_to_uri( $remote_object['attributedTo'] );
		if ( empty( $actor_url ) ) {
			return $mentions;
		}

		$meta = \Activitypub\get_remote_metadata_by_actor( $actor_url );
		if ( ! is_array( $meta ) || ( empty( $meta['id'] ) && empty( $meta['url'] ) ) ) {
			return $mentions;
		}

		if ( ! empty( $meta['preferredUsername'] ) ) {
			$handle = $meta['preferredUsername'];
		} elseif ( ! empty( $meta['name'] ) ) {
			$handle = $meta['name']; // Looks like for Mastodon this is the user's chosen display name.
		} else {
			$handle = esc_url_raw( $actor_url );
		}

		if ( ! preg_match( '~^https?://~', $handle ) ) {
			if ( false === strpos( $handle, '@' ) && ! preg_match( '~\s~', $handle ) ) {
				$host = wp_parse_url( $actor_url, PHP_URL_HOST );
				if ( ! empty( $host ) ) {
					// Add domain name.
					$handle .= '@' . $host;
				}
			}

			$handle = '@' . $handle;
		}

		$mentions[ $handle ] = esc_url_raw( $actor_url );

		return array_unique( $mentions );
	}

	/**
	 * Derives the post or comment object from an "ActivityPub" array.
	 *
	 * @param  array  $array             The ActivityPub activity or object.
	 * @param  string $class             The ActivityPub "class" name.
	 * @return \WP_Post|\WP_Comment|null Post or comment object, or null.
	 */
	protected static function get_wp_object( $array, $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
		if ( 'activity' === $class && isset( $array['object']['id'] ) ) {
			// Activity.
			$object_id = $array['object']['id'];
		} elseif ( 'base_object' === $class && isset( $array['id'] ) ) {
			$object_id = $array['id'];
		}

		if ( empty( $object_id ) ) {
			return null;
		}

		$query = wp_parse_url( $object_id, PHP_URL_QUERY );
		if ( ! empty( $query ) ) {
			parse_str( $query, $args );
		}

		if ( isset( $args['c'] ) && ctype_digit( $args['c'] ) ) {
			// Comment.
			$post_or_comment = get_comment( $args['c'] );
		} else {
			// Post.
			$post_or_comment = get_post( url_to_postid( $object_id ) );
		}

		if ( empty( $post_or_comment->post_author ) && empty( $post_or_comment->user_id ) ) {
			// Not a post or user comment, most likely. Bail.
			return null;
		}

		return $post_or_comment;
	}

	/**
	 * Parses an HTML string and returns either an in-reply-to URL or `null`.
	 *
	 * @param  string $post_content Post content.
	 * @return string|null          In-reply-to URL.
	 */
	protected static function get_in_reply_to_url( $post_content ) {
		$in_reply_to_url = null;

		/** Link https://github.com/WordPress/gutenberg/issues/46029#issuecomment-1326330988 */
		$processor = new \WP_HTML_Tag_Processor( $post_content );

		if ( $processor->next_tag( array( 'class_name' => 'u-in-reply-to' ) ) ) {
			$in_reply_to_url = $processor->get_attribute( 'href' );

			if ( null === $in_reply_to_url ) {
				// Might be a `.u-url` be nested inside, e.g, `.h-cite.u-in-reply-to`.
				$processor->next_tag( array( 'class_name' => 'u-url' ) );
				$in_reply_to_url = $processor->get_attribute( 'href' );
			}
		}

		return $in_reply_to_url;
	}

	/**
	 * Parses an HTML string and returns either a repost-of URL or `null`.
	 *
	 * @param  string $post_content Post content.
	 * @return string|null          Repost-of URL.
	 */
	protected static function get_repost_of_url( $post_content ) {
		$repost_of_url = null;
		$processor     = new \WP_HTML_Tag_Processor( $post_content );

		if ( $processor->next_tag( array( 'class_name' => 'u-repost-of' ) ) ) {
			$repost_of_url = $processor->get_attribute( 'href' );

			if ( null === $repost_of_url ) {
				// Might be a `.u-url` be nested inside, e.g, `.h-cite.u-repost-of`.
				$processor->next_tag( array( 'class_name' => 'u-url' ) );
				$repost_of_url = $processor->get_attribute( 'href' );
			}
		}

		return $repost_of_url;
	}

	/**
	 * Parses an HTML string and returns either a like-of URL or `null`.
	 *
	 * @param  string $post_content Post content.
	 * @return string|null          Like-of URL.
	 */
	protected static function get_like_of_url( $post_content ) {
		$like_of_url = null;
		$processor   = new \WP_HTML_Tag_Processor( $post_content );

		if ( $processor->next_tag( array( 'class_name' => 'u-like-of' ) ) ) {
			$like_of_url = $processor->get_attribute( 'href' );

			if ( null === $like_of_url ) {
				// Might be a `.u-url` be nested inside, e.g, `.h-cite.u-like-of`.
				$processor->next_tag( array( 'class_name' => 'u-url' ) );
				$like_of_url = $processor->get_attribute( 'href' );
			}
		}

		return $like_of_url;
	}
}
