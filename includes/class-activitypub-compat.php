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
		add_filter( 'activitypub_activity_object_array', array( __CLASS__, 'add_in_reply_to_url' ), 99, 4 );
		add_filter( 'activitypub_extract_mentions', array( __CLASS__, 'add_mentions' ), 99, 3 );
	}

	/**
	 * Adds the `inReplyTo` property to reply posts.
	 *
	 * @param  array                             $array  Activity or object (array).
	 * @param  string                            $class  Class name.
	 * @param  string                            $id     Activity or object ID.
	 * @param  \Activitypub\Activity\Base_Object $object Activity or object (object).
	 * @return array                                     The updated array.
	 */
	public static function add_in_reply_to_url( $array, $class, $id, $object ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound,Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! class_exists( '\\Activitypub\\Http' ) ) {
			return $array;
		}

		if ( ! method_exists( \Activitypub\Http::class, 'get_remote_object' ) ) {
			return $array;
		}

		if ( ! function_exists( '\\Activitypub\\object_to_uri' ) ) {
			return $array;
		}

		$post_or_comment = static::get_object( $array, $class ); // Could probably also use `$id` or even `$object`, but this works.
		if ( ! $post_or_comment ) {
			return $array;
		}

		if ( ! $post_or_comment instanceof \WP_Post ) {
			return $array;
		}

		$reply_to_url = '';

		/** Link https://github.com/WordPress/gutenberg/issues/46029#issuecomment-1326330988 */
		$processor = new \WP_HTML_Tag_Processor( $post_or_comment->post_content );

		if ( $processor->next_tag( array( 'class_name' => 'u-in-reply-to' ) ) ) {
			// Assuming a Reply block, which has its `.u-url` inside (and thus following) `.u-in-reply-to`.
			$processor->next_tag( array( 'class_name' => 'u-url' ) );
			$reply_to_url = $processor->get_attribute( 'href' );
		}

		if ( empty( $reply_to_url ) ) {
			return $array;
		}

		$object = \Activitypub\Http::get_remote_object( $reply_to_url );
		if ( ! is_array( $object ) || empty( $object['attributedTo'] ) ) {
			return $array;
		}

		$actor_url = \Activitypub\object_to_uri( $object['attributedTo'] );

		if ( empty( $actor_url ) ) {
			return $array;
		}

		// Add `inReplyTo` property.
		if ( 'activity' === $class ) {
			$array['object']['inReplyTo'] = $reply_to_url;
		} elseif ( 'base_object' === $class ) {
			$array['inReplyTo'] = $reply_to_url;
		}

		// Trim any reply context off the post content. Because important bits of said content may actually be
		// inside the Reply block, we can't just not render it. But we could render its inner blocks, and any other
		// blocks. Eventually. For now, a regex will have to do.
		$content = apply_filters( 'the_content', $post_or_comment->post_content ); // Wish we didn't have to do this *again*.

		if ( preg_match( '~<div class="e-content">.+?</div>~s', $content, $match ) ) {
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

		/** @todo: Move to a function and cache the result. */
		$reply_to_url = '';

		/** Link https://github.com/WordPress/gutenberg/issues/46029#issuecomment-1326330988 */
		$processor = new \WP_HTML_Tag_Processor( $wp_object->post_content );

		if ( $processor->next_tag( array( 'class_name' => 'u-in-reply-to' ) ) ) {
			// Assuming a Reply block, which has its `.u-url` inside (and thus following) `.u-in-reply-to`.
			$processor->next_tag( array( 'class_name' => 'u-url' ) );
			$reply_to_url = $processor->get_attribute( 'href' );
		}

		if ( empty( $reply_to_url ) ) {
			return $mentions;
		}

		$object = \Activitypub\Http::get_remote_object( $reply_to_url );
		if ( ! is_array( $object ) || empty( $object['attributedTo'] ) ) {
			return $mentions;
		}

		$actor_url = \Activitypub\object_to_uri( $object['attributedTo'] );

		if ( empty( $actor_url ) ) {
			return $mentions;
		}

		$meta = \Activitypub\get_remote_metadata_by_actor( $actor_url );

		if ( is_array( $meta ) && ( ! empty( $meta['id'] ) || ! empty( $meta['url'] ) ) ) {
			if ( ! empty( $meta['preferredUsername'] ) ) {
				$handle = $meta['preferredUsername'];
			} elseif ( ! empty( $meta['name'] ) ) {
				$handle = $meta['name'];
			} else {
				$handle = $actor_url;
			}

			// $actor_url = isset( $meta['url'] )
			// 	? \Activitypub\object_to_uri( $meta['url'] )
			// 	: $actor_url;

			if ( false === strpos( $handle, '@' ) && ! preg_match( '~\s~', $handle ) && ! preg_match( '~^https?://~', $handle ) ) {
				$host = wp_parse_url( $actor_url, PHP_URL_HOST );
				if ( ! empty( $host ) ) {
					// Add domain name.
					$handle .= '@' . $host;
				}
			}

			if ( ! preg_match( '~^https?://~', $handle ) ) {
				$handle = '@' . $handle;
			}

			$mentions[ $handle ] = $actor_url;
		}

		return array_unique( $mentions );
	}

	/**
	 * Derives the post or comment object from an "ActivityPub" array.
	 *
	 * @param  array  $array             The ActivityPub activity or object.
	 * @param  string $class             The ActivityPub "class" name.
	 * @return \WP_Post|\WP_Comment|null Post or comment object, or null.
	 */
	protected static function get_object( $array, $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
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
}
