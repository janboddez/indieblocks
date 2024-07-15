<?php
/**
 * Where we attempt to add microformats to block themes.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * An attempt to add microformats to block themes.
 */
class Theme_Mf2 {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'filter_core_blocks' ) );
		add_filter( 'term_links-category', array( __CLASS__, 'add_term_link_class' ) );
		add_filter( 'term_links-post_tag', array( __CLASS__, 'add_term_link_class' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_body_class' ), 99 );
		add_filter( 'post_class', array( __CLASS__, 'add_post_class' ), 99, 3 );
		add_filter( 'comment_class', array( __CLASS__, 'add_comment_class' ), 99 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'add_thumbnail_class' ) );
		add_filter( 'get_comment_link', array( __CLASS__, 'get_comment_link' ), 10, 2 );
		// add_filter( 'get_avatar_url', array( __CLASS__, 'get_avatar_url' ), 10, 2 );
	}

	/**
	 * Adds `p-category` to taxonomy term links.
	 *
	 * @param  array $links Array of term links.
	 * @return array        The filtered array.
	 */
	public static function add_term_link_class( $links ) {
		foreach ( $links as $i => $link ) {
			if ( false === strpos( $link, ' class=' ) ) {
				// If `$link` doesn't already have a `class` attribute, add one.
				// @todo: Move to proper DOM manipulation.
				$links[ $i ] = str_replace( ' rel="tag">', ' rel="tag" class="p-category">', $link );
			}
		}

		return $links;
	}

	/**
	 * Adds `h-feed` or `h-entry` to `body`.
	 *
	 * @param  array $classes Array of class names.
	 * @return array          The filtered array.
	 */
	public static function add_body_class( $classes ) {
		if ( is_home() || is_archive() || is_search() ) {
			$class = 'h-feed';
		} elseif ( is_singular() ) {
			global $wp_query;

			$class = 'h-entry';
			$post  = $wp_query->get_queried_object();

			if ( ! empty( $post->post_content ) ) {
				if ( preg_match( '~class=("|\')([^"\']*?)p-ingredient([^"\']*?)("|\')~', $post->post_content ) ) {
					// Decent chance this is a recipe.
					$class = 'h-recipe';
				} elseif ( preg_match( '~class=("|\')([^"\']*?)p-rating([^"\']*?)("|\')~', $post->post_content ) ) {
					// Decent chance this is a review.
					$class = 'h-review';
				} elseif ( preg_match( '~class=("|\')([^"\']*?)dt-start([^"\']*?)("|\')~', $post->post_content ) ) {
					// This could be an event.
					$class = 'h-event';
				}
			}
		}

		if ( ! empty( $class ) ) {
			$classes[] = apply_filters( 'indieblocks_body_class', $class );
		}

		return array_unique( $classes );
	}

	/**
	 * Adds `h-entry` to individual posts (on, e.g., archive pages).
	 *
	 * @param  array $classes   An array of post class names.
	 * @param  array $css_class An array of additional class names added to the post.
	 * @param  int   $post_id   The post ID.
	 * @return array            The filtered array.
	 */
	public static function add_post_class( $classes, $css_class, $post_id ) {
		if ( is_admin() || is_singular() ) {
			// Single posts get `h-entry` added to `body` instead.
			return $classes;

			// @todo: What about a single page that lists a number of posts? Shouldn't those get an `h-entry` class, too?
		}

		$class = 'h-entry';
		$post  = get_post( $post_id );

		if ( ! empty( $post->post_content ) ) {
			if ( preg_match( '~class=("|\')([^"\']*?)p-ingredient([^"\']*?)("|\')~', $post->post_content ) ) {
				// Decent chance this is a recipe.
				$class = 'h-recipe';
			} elseif ( preg_match( '~class=("|\')([^"\']*?)p-rating([^"\']*?)("|\')~', $post->post_content ) ) {
				// Decent chance this is a review.
				$class = 'h-review';
			} elseif ( preg_match( '~class=("|\')([^"\']*?)dt-start([^"\']*?)("|\')~', $post->post_content ) ) {
				// This could be an event.
				$class = 'h-event';
			}
		}

		$classes[] = apply_filters( 'indieblocks_post_class', $class );

		return array_unique( $classes );
	}

	/**
	 * Adds `h-cite` and `u-comment` classes to comments.
	 *
	 * @param  array $classes Array of class names.
	 * @return array          The filtered array.
	 */
	public static function add_comment_class( $classes ) {
		if ( is_admin() ) {
			return $classes;
		}

		$classes[] = 'u-comment';
		$classes[] = 'h-cite';

		return array_unique( $classes );
	}

	/**
	 * Adds `u-featured` to featured images.
	 *
	 * @param  string $html Featured image HTML.
	 * @return string       Updated HTML.
	 */
	public static function add_thumbnail_class( $html ) {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$processor->next_tag( 'img' );
		$processor->add_class( 'u-featured' );

		return $processor->get_updated_html();
	}

	/**
	 * Reregisters, and adds a custom render callback to, the same core post
	 * blocks.
	 */
	public static function filter_core_blocks() {
		add_filter( 'render_block_core/post-author-name', array( __CLASS__, 'render_block_core_post_author_name' ), 11, 3 );
		add_filter( 'render_block_core/post-author', array( __CLASS__, 'render_block_core_post_author' ), 11, 3 );
		add_filter( 'render_block_core/post-content', array( __CLASS__, 'render_block_core_post_content' ), 11, 3 );
		add_filter( 'render_block_core/post-date', array( __CLASS__, 'render_block_core_post_date' ), 11, 3 );
		add_filter( 'render_block_core/post-excerpt', array( __CLASS__, 'render_block_core_post_excerpt' ), 11, 3 );
		add_filter( 'render_block_core/post-title', array( __CLASS__, 'render_block_core_post_title' ), 11, 3 );
		add_filter( 'render_block_core/comment-author-name', array( __CLASS__, 'render_block_core_comment_author_name' ), 11, 3 );
		add_filter( 'render_block_core/comment-content', array( __CLASS__, 'render_block_core_comment_content' ), 11, 3 );
		add_filter( 'render_block_core/comment-date', array( __CLASS__, 'render_block_core_comment_date' ), 11, 3 );
	}

	/**
	 * Adds `u-author` and `h-card` (and so on) to the post author name block.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_post_author( $content, $block, $instance ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		$processor->next_tag( 'div' );
		$processor->add_class( 'h-card u-author' );

		if ( ! empty( $instance->attributes['avatarSize'] ) && ! empty( $instance->attributes['showAvatar'] ) ) {
			$processor->next_tag( 'img' );
			$processor->add_class( 'u-photo' );
		}

		$processor->next_tag( array( 'class_name' => 'wp-block-post-author__name' ) );
		$processor->add_class( 'p-name' );

		if ( ! empty( $instance->attributes['isLink'] ) ) {
			$processor->next_tag( 'a' );
			$processor->add_class( 'u-url' );
			$processor->set_attribute( 'rel', 'author me' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Adds `p-author` and `h-card` (and so on) to the post author name block.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_post_author_name( $content, $block, $instance ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		$processor->next_tag( 'div' );
		$processor->add_class( 'h-card' );
		$processor->add_class( 'p-author' );

		if ( ! empty( $instance->attributes['isLink'] ) ) {
			$processor->next_tag( 'a' );
			$processor->add_class( 'u-url' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Adds `e-content` to the post content block, but only if `$content`
	 * doesn't already include an element with such a class.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_post_content( $content, $block, $instance ) {
		if ( ! preg_match( '~class=("|\')([^"\']*?)e-content([^"\']*?)("|\')~', $content ) ) {
			$processor = new \WP_HTML_Tag_Processor( $content );
			$processor->next_tag( 'div' );
			$processor->add_class( 'e-content' );
			$content = $processor->get_updated_html();
		}

		return $content;
	}

	/**
	 * Adds `dt-published` and `u-url` to the post date block.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_post_date( $content, $block, $instance ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		$processor->next_tag( 'time' );
		$processor->add_class( 'dt-published' );

		if ( ! empty( $instance->attributes['isLink'] ) ) {
			$processor->next_tag( 'a' );
			$processor->add_class( 'u-url' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Adds `p-summary` to the post excerpt block.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_post_excerpt( $content, $block, $instance ) {
		$post_types = (array) apply_filters( 'indieblocks_short-form_post_types', array( 'indieblocks_like', 'indieblocks_note' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$options = get_options();
		if (
			! empty( $options['full_content'] ) &&
			! empty( $instance->context['postId'] ) &&
			! empty( $instance->context['postType'] ) &&
			in_array( $instance->context['postType'], $post_types, true )
		) {
			// Return a (rendered) Post Content block instead.
			/** @see Theme_Mf2::render_block_core_post_content() */
			return (
				new \WP_Block(
					array( 'blockName' => 'core/post-content' ),
					array(
						'postId'   => $instance->context['postId'],
						'postType' => $instance->context['postType'],
					)
				)
			)->render();
		}

		// Add a `p-summary` class.
		$processor = new \WP_HTML_Tag_Processor( $content );
		$processor->next_tag( 'div' );
		$processor->add_class( 'p-summary' );

		return $processor->get_updated_html();
	}

	/**
	 * Adds `p-name` and `u-url` to the post title block.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_post_title( $content, $block, $instance ) {
		$post_types = (array) apply_filters( 'indieblocks_short-form_post_types', array( 'indieblocks_like', 'indieblocks_note' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		$options    = get_options();

		$processor = new \WP_HTML_Tag_Processor( $content );
		$processor->next_tag( array( 'class_name' => 'wp-block-post-title' ) );

		if ( ! in_array( get_post_type(), $post_types, true ) ) {
			// Neither a like nor a note.
			$processor->add_class( 'p-name' );
		} else {
			$post_id = $instance->context['postId'];
			$kind    = get_kind( $post_id ); // Attempt to detect "kind" only for "our" post types, at it would wrongly return "note" for articles.

			if ( 'bookmark' === $kind && ( ! empty( $options['unhide_bookmark_titles'] ) || ! empty( $options['unhide_like_and_bookmark_titles'] ) ) ) {
				$processor->add_class( 'p-name' );
			} elseif ( 'like' === $kind && ( ! empty( $options['unhide_like_titles'] ) || ! empty( $options['unhide_like_and_bookmark_titles'] ) ) ) {
				$processor->add_class( 'p-name' );
			} elseif ( ! empty( $options['hide_titles'] ) ) {
				$processor->add_class( 'screen-reader-text' );
				// I'd use `wp_add_inline_style()` but that just adds the styles
				// over and over again for each block.
				wp_enqueue_style(
					'indieblocks-remove-extra-margin',
					plugins_url( '/assets/remove-extra-margin.css', __DIR__ ),
					array(),
					Plugin::PLUGIN_VERSION,
					false
				);
			}
		}

		if ( ! empty( $instance->attributes['isLink'] ) ) {
			$processor->next_tag( 'a' );

			if ( ! in_array( get_post_type(), array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
				// Not a like or note. This one's easy.
				$processor->add_class( 'u-url' );
			} else {
				// Attempt to detect "kind" only for "our" post types, at it
				// would wrongly return "note" for articles.
				$kind = ! isset( $kind ) ? get_kind( $post_id ) : $kind;

				if (
					( 'bookmark' === $kind && ( ! empty( $options['bookmark_titles'] ) || ! empty( $options['like_and_bookmark_titles'] ) ) ) ||
					( 'like' === $kind && ( ! empty( $options['like_titles'] ) || ! empty( $options['like_and_bookmark_titles'] ) ) )
				) {
					$linked_url = get_linked_url( $post_id );
				}

				// If we did find a bookmarked, liked, etc., URL, add the
				// appropriate `u-*` class.
				if ( 'bookmark' === $kind && ! empty( $linked_url ) ) {
					$processor->add_class( 'u-bookmark-of' );
				} elseif ( 'like' === $kind && ! empty( $linked_url ) ) {
					$processor->add_class( 'u-like-of' );
				} elseif ( 'repost' === $kind && ! empty( $linked_url ) ) {
					$processor->add_class( 'u-repost-of' );
				} else {
					$processor->add_class( 'u-url' );
				}
			}
		}

		return $processor->get_updated_html();
	}

	/**
	 * Adds `p-author` and `h-card` classes to the `core/comment-author-name`
	 * block.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_comment_author_name( $content, $block, $instance ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		$processor->next_tag( array( 'class_name' => 'wp-block-comment-author-name' ) );
		$processor->add_class( 'p-author' );
		$processor->add_class( 'h-card' );

		if ( ! empty( $instance->attributes['isLink'] ) ) {
			$processor->next_tag( 'a' );
			$processor->add_class( 'u-url' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Adds the `p-content` and `p-name` classes to the `core/comment-content`
	 * block.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_comment_content( $content, $block, $instance ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		$processor->next_tag( 'div' );
		$processor->add_class( 'p-content' );
		$processor->add_class( 'p-name' );

		return $processor->get_updated_html();
	}

	/**
	 * Adds `dt-published` to the `core/comment-date` block, and `u-url` to the
	 * comment's permalink.
	 *
	 * @param string    $content  Rendered block HTML.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string             Updated block HTML.
	 */
	public static function render_block_core_comment_date( $content, $block, $instance ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		$processor->next_tag( 'time' );
		$processor->add_class( 'dt-published' );

		if ( ! empty( $instance->attributes['isLink'] ) ) {
			$processor->next_tag( 'a' );
			$processor->add_class( 'u-url' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Filter comment links.
	 *
	 * @param  string     $link    The comment permalink with '#comment-$id' appended.
	 * @param  WP_Comment $comment The current comment object.
	 * @return string              Comment link.
	 */
	public static function get_comment_link( $link, $comment ) {
		$source = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_source', true );

		if ( empty( $source ) ) {
			$source = get_comment_meta( $comment->comment_ID, 'webmention_source_url', true );
		}

		if ( empty( $source ) ) {
			return $link;
		}

		return $source;
	}

	/**
	 * Quick 'n' dirty way to display webmention avatars.
	 *
	 * Note that the core blocks used by block themes call author and avatar
	 * blocks separately, which is why these typically _don't_ sit inside the
	 * author `h-card`.
	 *
	 * @param  string|null $avatar  Default `null`, or another plugin's HTML.
	 * @param  mixed       $comment Avatar to retrieve.
	 * @param  array       $args    Additional arguments.
	 * @return string|null          Avatar HTML.
	 */
	public static function get_avatar_html( $avatar, $comment, $args ) {
		if ( ! $comment instanceof \WP_Comment ) {
			return $avatar;
		}

		$url = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_avatar', true );

		if ( in_array( $comment->comment_type, array( 'bookmark', 'like', 'repost' ), true ) ) {
			// Mention created by the Webmention plugin.
			$url = get_comment_meta( $comment->comment_ID, 'avatar', true ); // This may be an external URL.
		}

		if ( empty( $url ) ) {
			$url = get_comment_meta( $comment->comment_ID, 'avatar_url', true ); // Created by the ActivityPub plugin.
		}

		if ( empty( $url ) ) {
			// Created by core. Note: Why do we need this? Doesn't core ... do
			// exactly this when we end up returning `null`? A: Yes, but it
			// won't be proxied!
			$url = get_avatar_url( $comment );
		}

		if ( empty( $url ) ) {
			return $avatar; // Let core (or another plugin) do its thing.
		}

		$options = get_options();
		if ( ! empty( $options['image_proxy'] ) && 0 !== strpos( $url, home_url() ) ) {
			$url = proxy_image( $url );
		}

		$width  = (int) ( ! empty( $args['width'] ) ? $args['width'] : 96 );
		$height = (int) ( ! empty( $args['height'] ) ? $args['height'] : 96 );

		$classes   = ! empty( $args['class'] ) ? (array) $args['class'] : array();
		$classes[] = "avatar avatar-{$width} photo";
		$classes   = trim( implode( ' ', $classes ) );

		return sprintf(
			'<img src="%s" alt="%s" width="%d" height="%d" class="%s" %s/>',
			esc_url( $url ),
			esc_attr( get_comment_author( $comment ) ),
			$width,
			$height,
			$classes,
			! empty( $args['extra_attr'] ) ? $args['extra_attr'] : ''
		);
	}

	/**
	 * Filter (only) avatar URLs.
	 *
	 * Currently unused.
	 *
	 * @param  string $url         Avatar URL.
	 * @param  mixed  $id_or_email User ID, Gravatar MD5 hash, user email, WP_User object, WP_Post object, or WP_Comment object.
	 * @return string              Avatar URL.
	 */
	public static function get_avatar_url( $url, $id_or_email ) {
		_deprecated_function( __METHOD__, '0.13.1' );

		if ( ! $id_or_email instanceof \WP_Comment ) {
			return $url;
		}

		$avatar_url = get_comment_meta( $id_or_email->comment_ID, 'indieblocks_webmention_avatar', true );

		if ( empty( $avatar_url ) && in_array( $id_or_email->comment_type, array( 'bookmark', 'like', 'repost' ), true ) ) {
			// Mention created by the Webmention plugin.
			$avatar_url = get_comment_meta( $id_or_email->comment_ID, 'avatar', true ); // This may be an external URL.
		}

		if ( empty( $avatar_url ) ) {
			$avatar_url = get_comment_meta( $id_or_email->comment_ID, 'avatar_url', true ); // (Likely) created by the ActivityPub plugin.
		}

		if ( ! empty( $avatar_url ) ) {
			$url = $avatar_url;
		}

		$options = get_options();
		if ( ! empty( $options['image_proxy'] ) && wp_http_validate_url( $url ) && 0 !== strpos( $url, home_url() ) ) {
			return proxy_image( $url );
		}

		return $url;
	}
}
