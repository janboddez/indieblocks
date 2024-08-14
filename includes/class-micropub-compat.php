<?php
/**
 * Bundles Micropub hook callbacks.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * If applicable, converts Micropub posts to blocks and sets a post type. Optionally, adds improved Micropub config
 * query support.
 */
class Micropub_Compat {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		$options = get_options();

		if ( ! empty( $options['post_types'] ) || ! empty( $options['enable_notes'] ) || ! empty( $options['enable_likes'] ) ) {
			// Assuming anyone who has our post types enabled would also want their Micropub posts to use them.
			add_filter( 'micropub_post_type', array( __CLASS__, 'set_post_type' ), 10, 2 );
		}

		if ( ! empty( $options['micropub'] ) ) {
			// Behind an extra option so folks who want to support more post types or somehow not hook into config
			// queries can more easily disable these.
			if ( ! empty( $options['post_types'] ) || ! empty( $options['enable_notes'] ) || ! empty( $options['enable_likes'] ) ) {
				// Micropub users can often choose to limit possible post types to those supported by their CMS.
				add_filter( 'micropub_query', array( __CLASS__, 'query_post_types' ), 20, 2 );
			}

			if ( ! empty( $options['note_taxonomies'] ) || ! empty( $options['default_taxonomies'] ) || ! empty( $options['like_taxonomies'] ) ) {
				// Certain Micropub clients support existing category or tag lookups.
				add_filter( 'micropub_query', array( __CLASS__, 'query_categories' ), 20, 2 );
			}

			if ( ! empty( $options['enable_blocks'] ) ) {
				// Rather than assume anyone who has our block(s) enabled would want to use them also in (supported)
				// Micropub posts, let's stick this, too, behind the `micropub` setting.
				add_filter( 'micropub_post_content', array( __CLASS__, 'set_post_content' ), 10, 2 );

				// Prevent the Micropub plugin from altering post content dynamically.
				add_filter( 'micropub_dynamic_render', '__return_false', 99 );
			}
		}
	}

	/**
	 * Adds a (filterable) `post-types` property to Micropub config responses.
	 *
	 * @param  array $response Micropub response.
	 * @param  array $input    Micropub query paramaters.
	 * @return array           Filtered response.
	 */
	public static function query_post_types( $response, $input ) {
		if ( ! isset( $input['q'] ) || 'config' !== $input['q'] ) {
			return $response;
		}

		if ( isset( $response['post-types'] ) ) {
			// Don't update existing post types.
			return $response;
		}

		$post_types = array(
			array(
				'type' => 'article',
				'name' => 'Article',
			),
		);

		$options = get_options();

		if ( ! empty( $options['post_types'] ) || ! empty( $options['enable_notes'] ) ) {
			// Add all (explicitly supported) short-form post types.
			$post_types = array_merge(
				$post_types,
				array(
					array(
						'type' => 'bookmark',
						'name' => 'Bookmark',
					),
					array(
						'type' => 'like',
						'name' => 'Like',
					),
					array(
						'type' => 'note',
						'name' => 'Note',
					),
					array(
						'type' => 'reply',
						'name' => 'Reply',
					),
					array(
						'type' => 'repost',
						'name' => 'Repost',
					),
				)
			);
		} elseif ( ! empty( $options['enable_likes'] ) ) {
			// Add _only_ likes.
			$post_types[] = array(
				'type' => 'like',
				'name' => 'Like',
			);
		}

		// Allow developers to override these settings.
		$post_types = apply_filters( 'indieblocks_micropub_post_types', $post_types );

		if ( ! empty( $post_types ) ) {
			$response['post-types'] = $post_types;
		}

		return $response;
	}

	/**
	 * Adds a (filterable) `categories` property to Micropub config responses.
	 *
	 * @param  array $response Micropub response.
	 * @param  array $input    Micropub query paramaters.
	 * @return array           Filtered response.
	 */
	public static function query_categories( $response, $input ) {
		if ( ! isset( $input['q'] ) || 'category' !== $input['q'] ) {
			return $response;
		}

		if ( isset( $response['categories'] ) ) {
			return $response;
		}

		$categories = array();

		foreach ( array_merge( get_categories(), get_tags() ) as $cat ) {
			$categories[] = $cat->name;
		}

		$categories = apply_filters( 'indieblocks_micropub_categories', $categories );

		if ( ! empty( $categories ) ) {
			$response['categories'] = $categories;
		}

		return $response;
	}

	/**
	 * Maps Micropub entries to a custom post type.
	 *
	 * @param  string $post_type Post type.
	 * @param  array  $input     Input data.
	 * @return string            Post type slug.
	 */
	public static function set_post_type( $post_type, $input ) {
		$options = get_options();

		if ( ! empty( $options['post_types'] ) || ! empty( $options['enable_notes'] ) ) {
			if ( ! empty( $input['properties']['like-of'][0] ) ) {
				$post_type = 'indieblocks_note';
			} elseif ( ! empty( $input['properties']['bookmark-of'][0] ) ) {
				$post_type = 'indieblocks_note';
			} elseif ( ! empty( $input['properties']['repost-of'][0] ) ) {
				$post_type = 'indieblocks_note';
			} elseif ( ! empty( $input['properties']['in-reply-to'][0] ) ) {
				$post_type = 'indieblocks_note';
			} elseif ( ! empty( $input['properties']['content'][0] ) && empty( $input['post_title'] ) ) {
				$post_type = 'indieblocks_note';
			}
		}

		if ( ! empty( $options['post_types'] ) || ! empty( $options['enable_likes'] ) ) {
			if ( ! empty( $input['properties']['like-of'][0] ) ) {
				$post_type = 'indieblocks_like';
			}
		}

		return $post_type;
	}

	/**
	 * Overrides default Micropub post content.
	 *
	 * @param  string $post_content Post content.
	 * @param  array  $input        Input properties.
	 * @return string               Modified content.
	 */
	public static function set_post_content( $post_content, $input ) {
		// Figure out the post type once more.
		$post_type = static::set_post_type( 'post', $input );

		if ( ! use_block_editor_for_post_type( $post_type ) ) {
			// Do nothing.
			return $post_content;
		}

		// Replace default content with block-based content.
		if ( ! empty( $input['properties']['like-of'][0] ) ) {
			$post_content = static::render( 'like', $input['properties']['like-of'][0], $input );
		} elseif ( ! empty( $input['properties']['bookmark-of'][0] ) ) {
			$post_content = static::render( 'bookmark', $input['properties']['bookmark-of'][0], $input );
		} elseif ( ! empty( $input['properties']['repost-of'][0] ) ) {
			$post_content = static::render( 'repost', $input['properties']['repost-of'][0], $input );
		} elseif ( ! empty( $input['properties']['in-reply-to'][0] ) ) {
			$post_content = static::render( 'reply', $input['properties']['in-reply-to'][0], $input );
		} else {
			$post_content = static::render( 'note', '', $input );
		}

		return $post_content;
	}

	/**
	 * Render `e-content` for certain post types.
	 *
	 * @param  string $post_type (IndieWeb) post type.
	 * @param  string $url       The URL being interacted with, if applicable.
	 * @param  array  $input     Micropub input arguments.
	 * @return string            Rendered content.
	 */
	public static function render( $post_type, $url = '', $input = array() ) {
		// Determine the _actual_ content, if any.
		if ( ! empty( $input['properties']['content'][0] ) ) {
			$content = $input['properties']['content'][0];

			$options = get_options();
			if ( ! empty( $options['parse_markdown'] ) ) {
				$content = Michelf\MarkdownExtra::defaultTransform( $content );
			}

			$content = wp_kses_post( $content );
			$content = apply_filters( 'indieblocks_inner_content', $content, $input );
		}

		$post_content = '';

		if ( ! empty( $url ) ) {
			// Could be we're looking at a bookmark, reply, repost or like.
			if ( preg_match( '~https?://.+?(?:$|\s)~', $url, $matches ) ) {
				// Depending on the scenario, Micropub clients may add a page title in front of the URL.
				$url = trim( $matches[0] ); // Keep only the URL.
			}

			// So that developers can, e.g., remove certain query strings.
			$url = apply_filters( 'indieblocks_micropub_url', $url );

			// Try to parse the web page at this URL.
			$parser = new Parser( $url );
			$parser->parse();

			$name   = sanitize_text_field( ! empty( $input['post_title'] ) ? $input['post_title'] : $parser->get_name() );
			$author = sanitize_text_field( $parser->get_author() );

			switch ( $post_type ) {
				case 'like':
					if ( '' !== $author ) {
						// We're given an author name; use the newer Like block.
						if ( empty( $content ) ) {
							$post_content .= '<!-- wp:indieblocks/like -->' . PHP_EOL;
						} else {
							$post_content .= '<!-- wp:indieblocks/like {"empty":false} -->' . PHP_EOL;
						}

						$name = ( '' !== $name ? $name : esc_url( $url ) );

						$post_content .= '<div class="wp-block-indieblocks-like"><div class="u-like-of h-cite"><p><i>';
						$post_content .= sprintf(
							/* translators: %1$s: Link to the "liked" page. %2$s: Author of the "liked" page. */
							__( 'Likes %1$s by %2$s.', 'indieblocks' ),
							'<a class="u-url' . ( esc_url( $url ) !== $name ? ' p-name' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>',
							'<span class="p-author">' . esc_html( $author ) . '</span>'
						);
						$post_content .= '</i></p></div>' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<div class="e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>';
						}

						$post_content .= '</div>
							<!-- /wp:indieblocks/like -->' . PHP_EOL;
					} elseif ( '' !== $name ) {
						// We've got a post title; use the Like block, but without byline.
						if ( empty( $content ) ) {
							$post_content .= '<!-- wp:indieblocks/like -->' . PHP_EOL;
						} else {
							$post_content .= '<!-- wp:indieblocks/like {"empty":false} -->' . PHP_EOL;
						}

						$post_content .= '<div class="wp-block-indieblocks-like"><div class="u-like-of h-cite"><p><i>';
						$post_content .= sprintf(
							/* translators: %s: Link to the "liked" page. */
							__( 'Likes %s.', 'indieblocks' ),
							'<a class="u-url' . ( esc_url( $url ) !== $name ? ' p-name' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'
						);
						$post_content .= '</i></p></div>' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<div class="e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>';
						}

						$post_content .= '</div>
							<!-- /wp:indieblocks/like -->' . PHP_EOL;
					} else {
						// Use the simpler Context block.
						$post_content .= '<!-- wp:indieblocks/context -->' . PHP_EOL;
						/* translators: %s: Link to the "liked" page. */
						$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Likes %s.', 'indieblocks' ), '<a class="u-like-of" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
							<!-- /wp:indieblocks/context -->' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<!-- wp:group {"className":"e-content"} -->
							<div class="wp-block-group e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>
							<!-- /wp:group -->';
						}
					}
					break;

				case 'bookmark':
					if ( '' !== $author ) {
						// We're given an author name; use the newer Bookmark block.
						if ( empty( $content ) ) {
							$post_content .= '<!-- wp:indieblocks/bookmark -->' . PHP_EOL;
						} else {
							$post_content .= '<!-- wp:indieblocks/bookmark {"empty":false} -->' . PHP_EOL;
						}

						$name = ( '' !== $name ? $name : esc_url( $url ) );

						$post_content .= '<div class="wp-block-indieblocks-bookmark"><div class="u-bookmark-of h-cite"><p><i>';
						$post_content .= sprintf(
							/* translators: %1$s: Link to the bookmarked page. %2$s: Author of the bookmarked page. */
							__( 'Bookmarked %1$s by %2$s.', 'indieblocks' ),
							'<a class="u-url' . ( esc_url( $url ) !== $name ? ' p-name' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>',
							'<span class="p-author">' . esc_html( $author ) . '</span>'
						);
						$post_content .= '</i></p></div>' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<div class="e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>';
						}

						$post_content .= '</div>
							<!-- /wp:indieblocks/bookmark -->' . PHP_EOL;
					} elseif ( '' !== $name ) {
						// We've got a post title; use the Bookmark block, but without byline.
						if ( empty( $content ) ) {
							$post_content .= '<!-- wp:indieblocks/bookmark -->' . PHP_EOL;
						} else {
							$post_content .= '<!-- wp:indieblocks/bookmark {"empty":false} -->' . PHP_EOL;
						}

						$post_content .= '<div class="wp-block-indieblocks-bookmark"><div class="u-bookmark-of h-cite"><p><i>';
						$post_content .= sprintf(
							/* translators: %s: Link to the bookmarked page. */
							__( 'Bookmarked %s.', 'indieblocks' ),
							'<a class="u-url' . ( esc_url( $url ) !== $name ? ' p-name' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'
						);
						$post_content .= '</i></p></div>' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<div class="e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>';
						}

						$post_content .= '</div>
							<!-- /wp:indieblocks/bookmark -->' . PHP_EOL;
					} else {
						// Use the simpler Context block.
						$post_content .= '<!-- wp:indieblocks/context -->' . PHP_EOL;
						/* translators: %s: Link to the bookmarked page. */
						$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Bookmarked %s.', 'indieblocks' ), '<a class="u-bookmark-of" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
							<!-- /wp:indieblocks/context -->' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<!-- wp:group {"className":"e-content"} -->
							<div class="wp-block-group e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>
							<!-- /wp:group -->';
						}
					}
					break;

				case 'reply':
					if ( '' !== $author ) {
						// We're given an author name; use the newer Reply block.
						if ( empty( $content ) ) {
							$post_content .= '<!-- wp:indieblocks/reply -->' . PHP_EOL;
						} else {
							$post_content .= '<!-- wp:indieblocks/reply {"empty":false} -->' . PHP_EOL;
						}

						$name = ( '' !== $name ? $name : esc_url( $url ) );

						$post_content .= '<div class="wp-block-indieblocks-reply"><div class="u-in-reply-to h-cite"><p><i>';
						$post_content .= sprintf(
							/* translators: %1$s: Link to the page being replied to. %2$s: Author of the page being replied to. */
							__( 'In reply to %1$s by %2$s.', 'indieblocks' ),
							'<a class="u-url' . ( esc_url( $url ) !== $name ? ' p-name' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>',
							'<span class="p-author">' . esc_html( $author ) . '</span>'
						);
						$post_content .= '</i></p></div>' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<div class="e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>';
						}

						$post_content .= '</div>
							<!-- /wp:indieblocks/reply -->' . PHP_EOL;
					} elseif ( '' !== $name ) {
						// We've got a post title; use the Reply block, but without byline.
						if ( empty( $content ) ) {
							$post_content .= '<!-- wp:indieblocks/reply -->' . PHP_EOL;
						} else {
							$post_content .= '<!-- wp:indieblocks/reply {"empty":false} -->' . PHP_EOL;
						}

						$post_content .= '<div class="wp-block-indieblocks-reply"><div class="u-in-reply-to h-cite"><p><i>';
						$post_content .= sprintf(
							/* translators: %s: Link to the page being replied to. */
							__( 'In reply to %s.', 'indieblocks' ),
							'<a class="u-url' . ( esc_url( $url ) !== $name ? ' p-name' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'
						);
						$post_content .= '</i></p></div>' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<div class="e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>';
						}

						$post_content .= '</div>
							<!-- /wp:indieblocks/reply -->' . PHP_EOL;
					} else {
						// Use the simpler Context block.
						$post_content .= '<!-- wp:indieblocks/context -->' . PHP_EOL;
						/* translators: %s: Link to the page being replied to. */
						$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'In reply to %s.', 'indieblocks' ), '<a class="u-in-reply-to" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
							<!-- /wp:indieblocks/context -->' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<!-- wp:group {"className":"e-content"} -->
							<div class="wp-block-group e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></div>
							<!-- /wp:group -->';
						}
					}
					break;

				case 'repost':
					if ( '' !== $author ) {
						// We're given an author name; use the newer Repost block.
						if ( empty( $content ) ) {
							$post_content .= '<!-- wp:indieblocks/repost -->' . PHP_EOL;
						} else {
							$post_content .= '<!-- wp:indieblocks/repost {"empty":false} -->' . PHP_EOL;
						}

						$name = ( '' !== $name ? $name : esc_url( $url ) );

						$post_content .= '<div class="wp-block-indieblocks-repost"><div class="u-repost-of h-cite"><p><i>';
						$post_content .= sprintf(
							/* translators: %1$s: Link to the "page" being reposted. %2$s: Author of the "page" being reposted. */
							__( 'Reposted %1$s by %2$s.', 'indieblocks' ),
							'<a class="u-url' . ( esc_url( $url ) !== $name ? ' p-name' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>',
							'<span class="p-author">' . esc_html( $author ) . '</span>'
						);
						$post_content .= '</i></p>' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<blockquote class="wp-block-quote e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></blockquote>';
						}

						$post_content .= '</div></div>
							<!-- /wp:indieblocks/repost -->' . PHP_EOL;
					} elseif ( '' !== $name ) {
						// We've got a post title; use the Repost block, but without byline.
						if ( empty( $content ) ) {
							$post_content .= '<!-- wp:indieblocks/repost -->' . PHP_EOL;
						} else {
							$post_content .= '<!-- wp:indieblocks/repost {"empty":false} -->' . PHP_EOL;
						}

						$post_content .= '<div class="wp-block-indieblocks-repost"><div class="u-repost-of h-cite"><p><i>';
						$post_content .= sprintf(
							/* translators: %s: Link to the "page" being reposted. */
							__( 'Reposted %s.', 'indieblocks' ),
							'<a class="u-url' . ( esc_url( $url ) !== $name ? ' p-name' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'
						);
						$post_content .= '</i></p>' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<blockquote class="wp-block-quote e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></blockquote>';
						}

						$post_content .= '</div></div>
							<!-- /wp:indieblocks/repost -->' . PHP_EOL;
					} else {
						// Use the simpler Context block.
						$post_content .= '<!-- wp:indieblocks/context -->' . PHP_EOL;
						/* translators: %s: Link to the "page" being reposted. */
						$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Reposted %s.', 'indieblocks' ), '<a class="u-repost-of" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' ) . '</i></div>
							<!-- /wp:indieblocks/context -->' . PHP_EOL;

						if ( ! empty( $content ) ) {
							$post_content .= '<!-- wp:quote {"className":"e-content"} -->
								<blockquote class="wp-block-quote e-content"><!-- wp:freeform -->' . $content . '<!-- /wp:freeform --></blockquote>
								<!-- /wp:quote -->';
						}
					}
					break;
			}
		} elseif ( 'note' === $post_type && ! empty( $content ) ) {
			$post_content .= '<!-- wp:freeform -->' . $content . '<!-- /wp:freeform -->';
		}

		return trim( $post_content );
	}
}
