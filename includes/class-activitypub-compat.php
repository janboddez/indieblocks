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
