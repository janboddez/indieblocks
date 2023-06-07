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
		add_filter( 'term_links-category', array( __CLASS__, 'add_term_link_class' ) );
		add_filter( 'term_links-post_tag', array( __CLASS__, 'add_term_link_class' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_body_class' ), 99 );
		add_filter( 'post_class', array( __CLASS__, 'add_post_class' ), 99, 3 );
		add_filter( 'comment_class', array( __CLASS__, 'add_comment_class' ), 99 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'add_thumbnail_class' ) );
		add_filter( 'get_comment_link', array( __CLASS__, 'get_comment_link' ), 10, 2 );
		add_filter( 'pre_get_avatar', array( __CLASS__, 'get_avatar_html' ), 10, 3 );

		add_action( 'init', array( __CLASS__, 'deregister_core_blocks' ), 1 );
		add_action( 'init', array( __CLASS__, 'reregister_core_blocks' ) );
		add_action( 'init', array( __CLASS__, 'deregister_gutenberg_blocks' ) );
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
					$class = 'h-review';
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
		if ( preg_match( '~class=("|\')~', $html, $matches ) ) {
			$html = str_replace( "class={$matches[1]}", "class={$matches[1]}u-featured ", $html );
		} else {
			$html = str_replace( '<img ', '<img class="u-featured" ', $html );
		}

		return $html;
	}

	/**
	 * Deregisters some of WordPress's default post blocks.
	 */
	public static function deregister_core_blocks() {
		remove_action( 'init', 'register_block_core_post_author' );
		remove_action( 'init', 'register_block_core_post_content' );
		remove_action( 'init', 'register_block_core_post_date' );
		remove_action( 'init', 'register_block_core_post_excerpt' );
		remove_action( 'init', 'register_block_core_post_title' );
		remove_action( 'init', 'register_block_core_comment_author_name' );
		remove_action( 'init', 'register_block_core_comment_content' );
		remove_action( 'init', 'register_block_core_comment_date' );
	}

	/**
	 * Reregisters, and adds a custom render callback to, the same core post
	 * blocks.
	 */
	public static function reregister_core_blocks() {
		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/post-author',
			array(
				'render_callback' => array( __CLASS__, 'render_block_core_post_author' ),
			)
		);

		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/post-content',
			array(
				'render_callback' => array( __CLASS__, 'render_block_core_post_content' ),
			)
		);

		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/post-date',
			array(
				'render_callback' => array( __CLASS__, 'render_block_core_post_date' ),
			)
		);

		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/post-excerpt',
			array(
				'render_callback' => array( __CLASS__, 'render_block_core_post_excerpt' ),
			)
		);

		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/post-title',
			array(
				'render_callback' => array( __CLASS__, 'render_block_core_post_title' ),
			)
		);

		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/comment-author-name',
			array(
				'render_callback' => array( __CLASS__, 'render_block_core_comment_author_name' ),
			)
		);

		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/comment-content',
			array(
				'render_callback' => array( __CLASS__, 'render_block_core_comment_content' ),
			)
		);

		register_block_type_from_metadata(
			ABSPATH . WPINC . '/blocks/comment-date',
			array(
				'render_callback' => array( __CLASS__, 'render_block_core_comment_date' ),
			)
		);
	}

	/**
	 * Stops the Gutenberg plugin from registering these blocks a second time.
	 */
	public static function deregister_gutenberg_blocks() {
		remove_action( 'init', 'gutenberg_register_block_core_post_author', 20 );
		remove_action( 'init', 'gutenberg_register_block_core_post_content', 20 );
		remove_action( 'init', 'gutenberg_register_block_core_post_date', 20 );
		remove_action( 'init', 'gutenberg_register_block_core_post_excerpt', 20 );
		remove_action( 'init', 'gutenberg_register_block_core_post_title', 20 );
		remove_action( 'init', 'gutenberg_register_block_core_comment_author_name', 20 );
		remove_action( 'init', 'gutenberg_register_block_core_comment_content', 20 );
		remove_action( 'init', 'gutenberg_register_block_core_comment_date', 20 );
	}

	/**
	 * Adds `p-author` and `h-card` (and so on) to the post author block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               Post author HTML.
	 */
	public static function render_block_core_post_author( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			$author_id = get_query_var( 'author' );
		} else {
			$author_id = get_post_field( 'post_author', $block->context['postId'] );
		}

		if ( empty( $author_id ) ) {
			return '';
		}

		$avatar = ! empty( $attributes['avatarSize'] ) ? get_avatar(
			$author_id,
			$attributes['avatarSize'],
			'',
			'',
			array( 'class' => 'u-photo' )
		) : null;

		$byline  = ! empty( $attributes['byline'] ) ? $attributes['byline'] : false;
		$classes = array_merge(
			isset( $attributes['itemsJustification'] ) ? array( 'items-justified-' . $attributes['itemsJustification'] ) : array(),
			isset( $attributes['textAlign'] ) ? array( 'has-text-align-' . $attributes['textAlign'] ) : array()
		);

		$author_url = get_the_author_meta( 'url' );
		if ( empty( $author_url ) ) {
			$author_url = home_url( '/' );
		}

		$classes[] = 'h-card p-author';

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		return sprintf( '<div %1$s>', $wrapper_attributes ) .
		( ! empty( $attributes['showAvatar'] ) ? '<div class="wp-block-post-author__avatar">' . $avatar . '</div>' : '' ) .
		'<div class="wp-block-post-author__content">' .
		( ! empty( $byline ) ? '<p class="wp-block-post-author__byline">' . wp_kses_post( $byline ) . '</p>' : '' ) .
		'<p class="wp-block-post-author__name"><a class="u-url" href="' . esc_url( $author_url ) . '" rel="author me"><span class="p-name">' . get_the_author_meta( 'display_name', $author_id ) . '</span></a></p>' .
		( ! empty( $attributes['showBio'] ) ? '<p class="wp-block-post-author__bio">' . get_the_author_meta( 'user_description', $author_id ) . '</p>' : '' ) .
		'</div>' .
		'</div>';
	}

	/**
	 * Adds `e-content` to the post content block (but only if `$content`
	 * doesn't already include an element with such a class.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               Post content HTML.
	 */
	public static function render_block_core_post_content( $attributes, $content, $block ) {
		static $seen_ids = array();

		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$post_id = $block->context['postId'];

		if ( isset( $seen_ids[ $post_id ] ) ) {
			// WP_DEBUG_DISPLAY must only be honored when WP_DEBUG. This precedent
			// is set in `wp_debug_mode()`.
			$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG &&
				defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;

			return $is_debug ?
				// translators: Visible only in the front end, this warning takes the place of a faulty block.
				__( '[block rendering halted]' ) :
				'';
		}

		$seen_ids[ $post_id ] = true;

		// Check is needed for backward compatibility with third-party plugins
		// that might rely on the `in_the_loop` check; calling `the_post` sets it to true.
		if ( ! in_the_loop() && have_posts() ) {
			the_post();
		}

		// When inside the main loop, we want to use queried object
		// so that `the_preview` for the current post can apply.
		// We force this behavior by omitting the third argument (post ID) from the `get_the_content`.
		$content = get_the_content();
		// Check for nextpage to display page links for paginated posts.
		if ( has_block( 'core/nextpage' ) ) {
			$content .= wp_link_pages( array( 'echo' => 0 ) );
		}

		/** This filter is documented in wp-includes/post-template.php */
		$content = apply_filters( 'the_content', str_replace( ']]>', ']]&gt;', $content ) );
		unset( $seen_ids[ $post_id ] );

		if ( empty( $content ) ) {
			return '';
		}

		$classes = array( 'entry-content' );

		if ( ! preg_match( '~class=("|\')([^"\']*?)e-content([^"\']*?)("|\')~', $content ) ) {
			$classes[] = 'e-content';
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		return (
			'<div ' . $wrapper_attributes . '>' .
				$content .
			'</div>'
		);
	}

	/**
	 * Adds `dt-published` and `u-url` to the post date block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               Post date HTML.
	 */
	public static function render_block_core_post_date( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$post_ID            = $block->context['postId'];
		$align_class_name   = empty( $attributes['textAlign'] ) ? '' : "has-text-align-{$attributes['textAlign']}";
		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $align_class_name ) );
		$formatted_date     = get_the_date( empty( $attributes['format'] ) ? '' : $attributes['format'], $post_ID );
		if ( isset( $attributes['isLink'] ) && $attributes['isLink'] ) {
			$formatted_date = sprintf( '<a href="%1s" class="u-url">%2s</a>', get_the_permalink( $post_ID ), $formatted_date );
		}

		return sprintf(
			'<div %1$s><time class="dt-published" datetime="%2$s">%3$s</time></div>',
			$wrapper_attributes,
			esc_attr( get_the_date( 'c', $post_ID ) ),
			$formatted_date
		);
	}

	/**
	 * Adds `p-summary` to the post excerpt block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               Post summary HTML.
	 */
	public static function render_block_core_post_excerpt( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$excerpt = get_the_excerpt();

		if ( empty( $excerpt ) ) {
			return '';
		}

		$more_text           = ! empty( $attributes['moreText'] ) ? '<a class="wp-block-post-excerpt__more-link" href="' . esc_url( get_the_permalink( $block->context['postId'] ) ) . '">' . wp_kses_post( $attributes['moreText'] ) . '</a>' : '';
		$filter_excerpt_more = function( $more ) use ( $more_text ) {
			return empty( $more_text ) ? $more : '';
		};
		/**
		 * Some themes might use `excerpt_more` filter to handle the
		 * `more` link displayed after a trimmed excerpt. Since the
		 * block has a `more text` attribute we have to check and
		 * override if needed the return value from this filter.
		 * So if the block's attribute is not empty override the
		 * `excerpt_more` filter and return nothing. This will
		 * result in showing only one `read more` link at a time.
		 */
		add_filter( 'excerpt_more', $filter_excerpt_more );

		$classes = array();

		if ( isset( $attributes['textAlign'] ) ) {
			$classes[] = "has-text-align-{$attributes['textAlign']}";
		}

		$classes[] = 'p-summary';

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		$content               = '<p class="wp-block-post-excerpt__excerpt">' . $excerpt;
		$show_more_on_new_line = ! isset( $attributes['showMoreOnNewLine'] ) || $attributes['showMoreOnNewLine'];
		if ( $show_more_on_new_line && ! empty( $more_text ) ) {
			$content .= '</p><p class="wp-block-post-excerpt__more-text">' . $more_text . '</p>';
		} else {
			$content .= " $more_text</p>";
		}
		remove_filter( 'excerpt_more', $filter_excerpt_more );
		return sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, $content );
	}

	/**
	 * Adds `p-name` and `u-url` to the post title block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               Post title HTML.
	 */
	public static function render_block_core_post_title( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$post_ID = $block->context['postId'];
		$title   = get_the_title();

		if ( ! $title ) {
			return '';
		}

		$tag_name = 'h2';
		$classes  = array();

		if ( ! empty( $attributes['textAlign'] ) ) {
			$classes[] = "has-text-align-{$attributes['textAlign']}";
		}

		// Note and Like titles, by default, do not get the `p-name` class.
		$options = get_options();

		if ( ! in_array( get_post_type(), array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
			// Not a like or note.
			$classes[] = 'p-name';
		} elseif ( ! empty( $options['unhide_like_and_bookmark_titles'] ) && '' !== get_post_meta( $post_ID, '_indieblocks_linked_url', true ) ) {
				// Do not hide like, bookmark, and repost titles.
				$classes[] = 'p-name';
		} elseif ( ! empty( $options['hide_titles'] ) ) {
				// Hide titles. Counting on core/the theme to provide the CSS.
				$classes[] = 'screen-reader-text';
		}

		if ( isset( $attributes['level'] ) ) {
			$tag_name = 0 === $attributes['level'] ? 'p' : 'h' . $attributes['level'];
		}

		$permalink = get_the_permalink( $post_ID );

		if ( ! empty( $options['like_and_bookmark_titles'] ) && in_array( get_post_type(), array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
			$linked_url = get_post_meta( $post_ID, '_indieblocks_linked_url', true );
			$permalink  = ! empty( $linked_url )
				? $linked_url
				: $permalink;
		}

		if ( isset( $attributes['isLink'] ) && $attributes['isLink'] ) {
			$title = sprintf( '<a href="%1$s" target="%2$s" rel="%3$s" class="u-url">%4$s</a>', esc_url( $permalink ), esc_attr( $attributes['linkTarget'] ), esc_attr( $attributes['rel'] ), $title );
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		return sprintf(
			'<%1$s %2$s>%3$s</%1$s>',
			$tag_name,
			$wrapper_attributes,
			$title
		);
	}

	/**
	 * Adds `p-author` and `h-card` classes to the `core/comment-author-name`
	 * block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               Return the post comment's author.
	 */
	public static function render_block_core_comment_author_name( $attributes, $content, $block ) {
		if ( ! isset( $block->context['commentId'] ) ) {
			return '';
		}

		$comment            = get_comment( $block->context['commentId'] );
		$commenter          = wp_get_current_commenter();
		$show_pending_links = isset( $commenter['comment_author'] ) && $commenter['comment_author'];
		if ( empty( $comment ) ) {
			return '';
		}

		$classes = array( 'p-author', 'h-card' );
		if ( isset( $attributes['textAlign'] ) ) {
			$classes[] = 'has-text-align-' . $attributes['textAlign'];
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );
		$comment_author     = get_comment_author( $comment );
		$link               = get_comment_author_url( $comment );

		if ( ! empty( $link ) && ! empty( $attributes['isLink'] ) && ! empty( $attributes['linkTarget'] ) ) {
			$comment_author = sprintf( '<a rel="external nofollow ugc" href="%1s" target="%2s" class="u-url">%3s</a>', esc_url( $link ), esc_attr( $attributes['linkTarget'] ), $comment_author );
		}
		if ( '0' === $comment->comment_approved && ! $show_pending_links ) {
			$comment_author = wp_kses( $comment_author, array() );
		}

		return sprintf(
			'<div %1$s>%2$s</div>',
			$wrapper_attributes,
			$comment_author
		);
	}

	/**
	 * Adds the `p-content` and `p-name` classes to the `core/comment-content`
	 * block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               Return the post comment's content.
	 */
	public static function render_block_core_comment_content( $attributes, $content, $block ) {
		if ( ! isset( $block->context['commentId'] ) ) {
			return '';
		}

		$comment            = get_comment( $block->context['commentId'] );
		$commenter          = wp_get_current_commenter();
		$show_pending_links = isset( $commenter['comment_author'] ) && $commenter['comment_author'];
		if ( empty( $comment ) ) {
			return '';
		}

		$args         = array();
		$comment_text = get_comment_text( $comment, $args );
		if ( ! $comment_text ) {
			return '';
		}

		/** This filter is documented in wp-includes/comment-template.php */
		$comment_text = apply_filters( 'comment_text', $comment_text, $comment, $args );

		$moderation_note = '';
		if ( '0' === $comment->comment_approved ) {
			$commenter = wp_get_current_commenter();

			if ( $commenter['comment_author_email'] ) {
				$moderation_note = __( 'Your comment is awaiting moderation.' );
			} else {
				$moderation_note = __( 'Your comment is awaiting moderation. This is a preview; your comment will be visible after it has been approved.' );
			}
			$moderation_note = '<p><em class="comment-awaiting-moderation">' . $moderation_note . '</em></p>';
			if ( ! $show_pending_links ) {
				$comment_text = wp_kses( $comment_text, array() );
			}
		}

		$classes = array( 'p-content', 'p-name' );
		if ( isset( $attributes['textAlign'] ) ) {
			$classes[] = 'has-text-align-' . $attributes['textAlign'];
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		return sprintf(
			'<div %1$s>%2$s%3$s</div>',
			$wrapper_attributes,
			$moderation_note,
			$comment_text
		);
	}

	/**
	 * Adds `dt-published` to the `core/comment-date` block, and `u-url` to the
	 * comment's permalink.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               Return the post comment's date.
	 */
	public static function render_block_core_comment_date( $attributes, $content, $block ) {
		if ( ! isset( $block->context['commentId'] ) ) {
			return '';
		}

		$comment = get_comment( $block->context['commentId'] );
		if ( empty( $comment ) ) {
			return '';
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => '' ) );
		$formatted_date     = get_comment_date(
			isset( $attributes['format'] ) ? $attributes['format'] : '',
			$comment
		);
		$link               = get_comment_link( $comment );

		if ( ! empty( $attributes['isLink'] ) ) {
			$formatted_date = sprintf( '<a href="%1s" class="u-url">%2s</a>', esc_url( $link ), $formatted_date );
		}

		return sprintf(
			'<div %1$s><time datetime="%2$s" class="dt-published">%3$s</time></div>',
			$wrapper_attributes,
			esc_attr( get_comment_date( 'c', $comment ) ),
			$formatted_date
		);
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
	 * @param  string|null $avatar  Default HTML.
	 * @param  mixed       $comment Avatar to retrieve.
	 * @param  array       $args    Additional arguments.
	 * @return string|null          Avatar HTML.
	 */
	public static function get_avatar_html( $avatar, $comment, $args ) {
		$options = get_options();

		if ( empty( $options['cache_avatars'] ) ) {
			return null;
		}

		if ( ! $comment instanceof \WP_Comment ) {
			return null;
		}

		$url = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_avatar', true );

		if ( in_array( $comment->comment_type, array( 'bookmark', 'like', 'repost' ), true ) ) {
			// Mention created by the Webmention plugin.
			$url = get_comment_meta( $comment->comment_ID, 'avatar', true ); // This may be an external URL.
		}

		if ( empty( $url ) ) {
			return null;
		}

		$upload_dir = wp_upload_dir();
		$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		if ( ! is_file( $file_path ) ) {
			return null;
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
	 * WordPress' `core/comments` block has this issue with comment counts.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block default content.
	 * @param WP_Block $block      Block instance.
	 * @return string Returns the filtered post comments for the current post wrapped inside "p" tags.
	 */
	public static function render_block_core_comments( $attributes, $content, $block ) {
		global $post;

		$post_id = $block->context['postId'];
		if ( ! isset( $post_id ) ) {
			return '';
		}

		// @codingStandardsIgnoreStart
		// $comment_args = array(
		// 	'post_id' => $post_id,
		// 	'count'   => true,
		// 	'status'  => 'approve',
		// );
		// Return early if there are no comments and comments are closed.
		// if ( ! comments_open( $post_id ) && get_comments( $comment_args ) === 0 ) {
		// @codingStandardsIgnoreEnd
		if ( ! comments_open( $post_id ) && in_array( get_comments_number( $post_id ), array( 0, '0' ), true ) ) {
			return '';
		}

		// If this isn't the legacy block, we need to render the static version of this block.
		$is_legacy = 'core/post-comments' === $block->name || ! empty( $attributes['legacy'] );
		if ( ! $is_legacy ) {
			return $block->render( array( 'dynamic' => false ) );
		}

		$post_before = $post;
		$post        = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		ob_start();

		/*
		* There's a deprecation warning generated by WP Core.
		* Ideally this deprecation is removed from Core.
		* In the meantime, this removes it from the output.
		*/
		add_filter( 'deprecated_file_trigger_error', '__return_false' );
		comments_template();
		remove_filter( 'deprecated_file_trigger_error', '__return_false' );

		$output = ob_get_clean();
		$post   = $post_before; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$classnames = array();
		// Adds the old class name for styles' backwards compatibility.
		if ( isset( $attributes['legacy'] ) ) {
			$classnames[] = 'wp-block-post-comments';
		}
		if ( isset( $attributes['textAlign'] ) ) {
			$classnames[] = 'has-text-align-' . $attributes['textAlign'];
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array( 'class' => implode( ' ', $classnames ) )
		);

		/*
		* Enqueues scripts and styles required only for the legacy version. That is
		* why they are not defined in `block.json`.
		*/
		wp_enqueue_script( 'comment-reply' );
		enqueue_legacy_post_comments_block_styles( $block->name );

		return sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, $output );
	}
}
