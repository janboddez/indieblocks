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
		add_filter( 'activitypub_activity_object_array', array( __CLASS__, 'add_in_reply_to_url' ), 99, 2 );
		add_filter( 'activitypub_extract_mentions', array( __CLASS__, 'add_mentions' ), 99, 3 );
	}

	/**
	 * Adds the `inReplyTo` property to reply posts.
	 *
	 * @param  array  $array  Activity or object (array).
	 * @param  string $class  Class name.
	 * @return array          The updated array.
	 */
	public static function add_in_reply_to_url( $array, $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound,Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
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
	 * Adds a mention to posts we think are replies, reposts, or likes.
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
		if ( empty( $in_reply_to_url ) ) {
			// Could not find an `in-reply-to` URL.
			return $mentions;
		}

		$remote_object = \Activitypub\Http::get_remote_object( $in_reply_to_url );
		if ( ! is_array( $remote_object ) || empty( $remote_object['attributedTo'] ) ) {
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
	 * Parses an HTML string and returns either an in-reply-to URL or an empty string.
	 *
	 * @param  string $post_content Post content.
	 * @return string               In-reply-to URL.
	 */
	protected static function get_in_reply_to_url( $post_content ) {
		$in_reply_to_url = '';

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
}
