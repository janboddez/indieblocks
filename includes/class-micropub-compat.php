<?php
/**
 * Bundles Micropub hook callbacks.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * If applicable, converts Micropub posts to blocks and sets a post type.
 * Optionally, adds improved Micropub config query support.
 */
class Micropub_Compat {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		$plugin  = IndieBlocks::get_instance();
		$options = $plugin->get_options_handler()->get_options();

		if ( ! empty( $options['enable_blocks'] ) ) {
			// Assuming anyone who has our block(s) enabled would want to use
			// them also in (supported) Micropub posts.
			add_filter( 'micropub_post_content', array( __CLASS__, 'set_post_content' ), 10, 2 );
		}

		if ( ! empty( $options['post_types'] ) ) {
			// Assuming anyone who has our post types enabled would also want
			// their Micropub posts to use them.
			add_filter( 'micropub_post_type', array( __CLASS__, 'set_post_type' ), 10, 2 );
		}

		if ( ! empty( $options['micropub'] ) ) {
			// Behind an extra option so folks that want to support more post
			// types or somehow not hook into config queries can more easily
			// disable these.
			if ( ! empty( $options['post_types'] ) ) {
				// Micropub users can often choose to limit possible post types
				// to those supported by their CMS. Filterable.
				add_filter( 'micropub_query', array( __CLASS__, 'query_post_types' ), 20, 2 );
			}

			if ( ! empty( $options['default_taxonomies'] ) ) {
				// Certain Micropub clients support existing category or tag
				// lookups. Filterable.
				add_filter( 'micropub_query', array( __CLASS__, 'query_categories' ), 20, 2 );
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
		);

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
		if ( ! empty( $input['properties']['like-of'][0] ) ) {
			$post_type = 'indieblocks_like';
		} elseif ( ! empty( $input['properties']['bookmark-of'][0] ) ) {
			$post_type = 'indieblocks_note';
		} elseif ( ! empty( $input['properties']['repost-of'][0] ) ) {
			$post_type = 'indieblocks_note';
		} elseif ( ! empty( $input['properties']['in-reply-to'][0] ) ) {
			$post_type = 'indieblocks_note';
		} elseif ( ! empty( $input['properties']['content'][0] ) && empty( $input['post_title'] ) ) {
			$post_type = 'indieblocks_note';
		}

		return $post_type;
	}

	/**
	 * Overrides default Micropub post content.
	 *
	 * @todo: Clean up.
	 *
	 * @param  string $post_content Post content.
	 * @param  array  $input        Input properties.
	 * @return string               Modified content.
	 */
	public static function set_post_content( $post_content, $input ) {
		$plugin  = IndieBlocks::get_instance();
		$options = $plugin->get_options_handler()->get_options();

		if ( empty( $options['enable_blocks'] ) ) {
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
		}

		return $post_content;
	}

	/**
	 * Render `e-content` for certain post types.
	 *
	 * @param  string $post_type (IndieWeb) post type.
	 * @param  array  $url       The URL being interacted with, if applicable.
	 * @param  array  $input     Micropub input arguments.
	 * @return string            Rendered content.
	 */
	public static function render( $post_type, $url = '', $input = array() ) {
		if ( ! empty( $url ) ) {
			if ( preg_match( '~https?://.+?(?:$|\s)~', $url, $matches ) ) {
				// Depending on the scenario, Micropub clients may add a page
				// title in front of the URL.
				$url = trim( $matches[0] );
			}

			// So that developers can, e.g., remove certain query strings.
			$url          = apply_filters( 'indieblocks_micropub_url', $url );
			$post_content = '';

			switch ( $post_type ) {
				case 'like':
					$post_content .= '<!-- wp:indieblocks/context {"kind":"u-like-of"} -->' . PHP_EOL;
					/* translators: %s: URL of the "liked" page. */
					$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Likes %s.', 'indieblocks' ), '<a class="u-like-of" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
						<!-- /wp:indieblocks/context -->' . PHP_EOL;
					break;

				case 'bookmark':
					$post_content .= '<!-- wp:indieblocks/context {"kind":"u-bookmark-of"} -->' . PHP_EOL;
					/* translators: %s: URL of the bookmarked page. */
					$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Bookmarked %s.', 'indieblocks' ), '<a class="u-bookmark-of" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
						<!-- /wp:indieblocks/context -->' . PHP_EOL;
					break;

				case 'reply':
					$post_content .= '<!-- wp:indieblocks/context {"kind":"u-in-reply-to"} -->' . PHP_EOL;
					/* translators: %s: URL of the page being replied to. */
					$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'In reply to %s.', 'indieblocks' ), '<a class="u-in-reply-to" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
						<!-- /wp:indieblocks/context -->' . PHP_EOL;
					break;

				case 'repost':
					$post_content .= '<!-- wp:indieblocks/context {"kind":"u-repost-of"} -->' . PHP_EOL;
					/* translators: %s: URL of the "page" being reposted. */
					$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Reposted %s.', 'indieblocks' ), '<a class="u-repost-of" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
						<!-- /wp:indieblocks/context -->' . PHP_EOL;
					break;
			}
		}

		if ( ! empty( $input['properties']['content'][0] ) ) {
			if ( 'repost' === $post_type ) {
				$post_content .= '<!-- wp:quote {"className":"e-content"} -->
					<blockquote class="wp-block-quote e-content"><p>' . wp_kses_post( $input['properties']['content'][0] ) . '</p></blockquote>
					<!-- /wp:quote -->';
			} else {
				$post_content .= '<!-- wp:group {"className":"e-content"} -->
					<div class="wp-block-group e-content"><!-- wp:paragraph -->
					<p>' . wp_kses_post( $input['properties']['content'][0] ) . '</p>
					<!-- /wp:paragraph --></div>
					<!-- /wp:group -->';
			}
		}

		return trim( $post_content );
	}
}