<?php
/**
 * @package IndieBlocks
 */

namespace IndieBlocks;

class Facepile_Content_Block {
	/**
	 * Registers the Facepile Content block.
	 */
	public static function register() {
		register_block_type_from_metadata( __DIR__, array( 'render_callback' => array( __CLASS__, 'render_facepile_content_block' ) ) );

		// This oughta happen automatically, but whatevs.
		wp_set_script_translations(
			generate_block_asset_handle( 'indieblocks/facepile-content', 'editorScript' ),
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);
	}

	/**
	 * Renders the `indieblocks/facepile-content` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                Rendered HTML.
	 */
	public static function render_facepile_content_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$facepile_comments = \IndieBlocks\get_facepile_comments( $block->context['postId'] );

		if ( empty( $facepile_comments ) ) {
			return '';
		}

		add_action( 'wp_footer', array( __CLASS__, 'print_icons' ), 999 );

		// Enqueue front-end block styles.
		wp_enqueue_style( 'indieblocks-facepile', plugins_url( '/assets/facepile.css', dirname( __DIR__ ) ), array(), Plugin::PLUGIN_VERSION, false );

		// Limit comments. Might provide a proper option later.
		$facepile_num      = apply_filters( 'indieblocks_facepile_num', 25, $block->context['postId'] );
		$facepile_comments = array_slice( $facepile_comments, 0, $facepile_num );

		$output = '';

		foreach ( $facepile_comments as $comment ) {
			$avatar = get_avatar( $comment, 40 );

			if ( empty( $avatar ) ) {
				continue;
			}

			$processor = new \WP_HTML_Tag_Processor( $avatar );
			$processor->next_tag( 'img' );

			if ( ! empty( $attributes['backgroundColor'] ) ) {
				$processor->set_attribute( 'style', 'background:' . esc_attr( $attributes['backgroundColor'] ) ); // Even though `WP_HTML_Tag_Processor::set_attribute()` will run, e.g., `esc_attr()` for us.
			}

			$alt = $processor->get_attribute( 'alt' );
			$alt = ! empty( $alt ) ? $alt : get_comment_author( $comment );

			$processor->set_attribute( 'alt', esc_attr( $alt ) );

			$avatar = $processor->get_updated_html();

			$source = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_source', true );
			$kind   = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_kind', true );

			if ( in_array( $comment->comment_type, array( 'bookmark', 'like', 'repost' ), true ) ) {
				// Mentions initiated by the Webmention plugin use a slightly different data structure.
				$source = get_comment_meta( $comment->comment_ID, 'url', true );
				if ( empty( $source ) ) {
					$source = get_comment_meta( $comment->comment_ID, 'webmention_source_url', true );
				}

				$kind   = $comment->comment_type;
			}

			$classes = array(
				'bookmark' => 'p-bookmark',
				'like'     => 'p-like',
				'repost'   => 'p-repost',
			);
			$class   = isset( $classes[ $kind ] ) ? esc_attr( $classes[ $kind ] ) : '';

			$titles = array(
				'bookmark' => '&hellip; bookmarked this!',
				'like'     => '&hellip; liked this!',
				'repost'   => '&hellip; reposted this!',
			);
			$title  = isset( $titles[ $kind ] ) ? esc_attr( $titles[ $kind ] ) : '';

			if ( ! empty( $source ) ) {
				$el = '<li class="h-cite' . ( ! empty( $class ) ? " $class" : '' ) . '"' . ( ! empty( $title ) ? ' title="' . $title . '"' : '' ) . '>' .
				'<a class="u-url" href="' . esc_url( $source ) . '" target="_blank" rel="noopener noreferrer"><span class="h-card p-author">' . $avatar . '</span>' .
				( ! empty( $attributes['icons'] ) && ! empty( $kind )
					? '<svg class="icon indieblocks-icon-' . $kind . '" aria-hidden="true" role="img"><use href="#indieblocks-icon-' . $kind . '" xlink:href="#indieblocks-icon-' . $kind . '"></use></svg>'
					: ''
				) .
				"</a></li>\n";
			} else {
				$el = '<li class="h-cite' . ( ! empty( $class ) ? " $class" : '' ) . '"' . ( ! empty( $title ) ? ' title="' . $title . '"' : '' ) . '>' .
				'<span class="p-author h-card">' . $avatar . '</span>' .
				( ! empty( $attributes['icons'] ) && ! empty( $kind )
					? '<svg class="icon indieblocks-icon-' . $kind . '" aria-hidden="true" role="img"><use href="#indieblocks-icon-' . $kind . '" xlink:href="#indieblocks-icon-' . $kind . '"></use></svg>'
					: ''
				) .
				"</li>\n";
			}

			$icon_style = '';
			if ( ! empty( $attributes['color'] ) ) {
				$icon_style .= "color:{$attributes['color']};";
			}
			if ( ! empty( $attributes['iconBackgroundColor'] ) ) {
				$icon_style .= "background-color:{$attributes['iconBackgroundColor']};";
			}

			if ( ! empty( $icon_style ) ) {
				$processor = new \WP_HTML_Tag_Processor( $el );
				$processor->next_tag( 'svg' );

				$processor->set_attribute( 'style', esc_attr( $icon_style ) );
				$el = $processor->get_updated_html();
			}

			$output .= $el;
		}

		if ( ! empty( $attributes['avatarSize'] ) ) {
			$avatar_size    = esc_attr( (int) $attributes['avatarSize'] );
			$opening_ul_tag = "<ul class='indieblocks-avatar-size-{$avatar_size}'>";
		} else {
			$opening_ul_tag = '<ul>';
		}

		$wrapper_attributes = get_block_wrapper_attributes();

		return '<div ' . $wrapper_attributes . '>' .
			$opening_ul_tag . trim( $output ) . '</ul>' .
		'</div>';
	}

	/**
	 * Outputs bookmark, like, and repost icons so they can be used anywhere on
	 * the page.
	 */
	public static function print_icons() {
		$icons = dirname( dirname( __DIR__ ) ) . '/assets/webmention-icons.svg';

		if ( is_readable( $icons ) ) {
			require_once $icons;
		}
	}
}
