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
		add_filter( 'post_class', array( __CLASS__, 'add_post_class' ), 99 );

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
		if ( in_array( 'h-feed', $classes, true ) ) {
			return $classes;
		}

		if ( is_home() || is_archive() || is_search() ) {
			$classes[] = 'h-feed';
		} elseif ( is_singular() ) {
			$classes[] = 'h-entry';
		}

		return $classes;
	}

	/**
	 * Adds `h-entry` to individual posts (on, e.g., archive pages).
	 *
	 * @param  array $classes Array of class names.
	 * @return array          The filtered array.
	 */
	public static function add_post_class( $classes ) {
		if ( in_array( 'h-entry', $classes, true ) ) {
			return $classes;
		}

		if ( is_admin() || is_singular() ) {
			// Single posts get `h-entry` added to `body` instead.
			return $classes;
		}

		$classes[] = 'h-entry';

		return $classes;
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
			return '';
		}

		$author_id = get_post_field( 'post_author', $block->context['postId'] );
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
	 * Adds `e-content` to the post content block (but only if `$content` doesn't
	 * already include such a class.
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

		if ( ! preg_match( '~ class=("|\')([^"\']*?)e-content([^"\']*?)("|\')~', $content ) ) {
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
		$options = IndieBlocks::get_instance()
			->get_options_handler()
			->get_options();

		if ( ! in_array( get_post_type(), array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
			$classes[] = 'p-name';
		} elseif ( ! empty( $options['hide_titles'] ) ) {
			$classes[] = 'screen-reader-text';
		}

		if ( isset( $attributes['level'] ) ) {
			$tag_name = 0 === $attributes['level'] ? 'p' : 'h' . $attributes['level'];
		}

		if ( isset( $attributes['isLink'] ) && $attributes['isLink'] ) {
			$title = sprintf( '<a href="%1$s" target="%2$s" rel="%3$s" class="u-url">%4$s</a>', get_the_permalink( $post_ID ), esc_attr( $attributes['linkTarget'] ), esc_attr( $attributes['rel'] ), $title );
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		return sprintf(
			'<%1$s %2$s>%3$s</%1$s>',
			$tag_name,
			$wrapper_attributes,
			$title
		);
	}
}
