<?php
/**
 * Where Gutenberg blocks are registered.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * All things Gutenberg.
 */
class Blocks {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'init', array( __CLASS__, 'register_block_patterns' ), 15 );
		add_action( 'init', array( __CLASS__, 'register_block_templates' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );
	}

	/**
	 * Registers the different blocks.
	 */
	public static function register_blocks() {
		register_block_type_from_metadata(
			dirname( __DIR__ ) . '/blocks/syndication-links',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);

		// This oughta happen automatically, but whatevs.
		wp_set_script_translations(
			generate_block_asset_handle( 'indieblocks/syndication-links', 'editorScript' ),
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);

		foreach ( array( 'context', 'bookmark', 'like', 'reply', 'repost' ) as $block ) {
			register_block_type( dirname( __DIR__ ) . "/blocks/$block" );

			// This oughta happen automatically, but whatevs.
			wp_set_script_translations(
				generate_block_asset_handle( "indieblocks/$block", 'editorScript' ),
				'indieblocks',
				dirname( __DIR__ ) . '/languages'
			);
		}
	}

	/**
	 * Registers block patterns.
	 */
	public static function register_block_patterns() {
		register_block_pattern(
			'indieblocks/note-starter-pattern',
			array(
				'title'       => __( 'Note Starter Pattern', 'indieblocks' ),
				'description' => __( 'A nearly blank starter pattern for &ldquo;IndieWeb&rdquo;-style notes.', 'indieblocks' ),
				'categories'  => array( 'text' ),
				'content'     => '<!-- wp:indieblocks/context -->
					<div class="wp-block-indieblocks-context"></div>
					<!-- /wp:indieblocks/context -->

					<!-- wp:group {"className":"e-content"} -->
					<div class="wp-block-group e-content"><!-- wp:paragraph -->
					<p></p>
					<!-- /wp:paragraph --></div>
					<!-- /wp:group -->',
			)
		);
	}

	/**
	 * Registers Note and Like block templates.
	 */
	public static function register_block_templates() {
		foreach ( array( 'indieblocks_like', 'indieblocks_note' ) as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );

			if ( ! $post_type_object ) {
				// Post type not active.
				continue;
			}

			$post_type_object->template = array(
				array(
					'indieblocks/context',
					'indieblocks_like' === $post_type
						? array( 'kind' => 'u-like-of' )
						: array(),
				),
			);

			// if ( 'indieblocks_note' === $post_type ) {
			// 	$post_type_object->template[] = array(
			// 		'core/group',
			// 		array( 'className' => 'e-content' ),
			// 		array(
			// 			array( 'core/paragraph' ),
			// 		),
			// 	);
			// }
		}
	}

	/**
	 * Registers (block-related) REST API endpoints.
	 *
	 * @todo: (Eventually) also add an "author" endpoint. Or have the single endpoint return both title and author information.
	 */
	public static function register_api_endpoints() {
		register_rest_route(
			'indieblocks/v1',
			'/meta',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( __CLASS__, 'get_meta' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
			)
		);
	}

	/**
	 * The one, for now, REST API permission callback.
	 *
	 * @return bool If the request's authorized or not.
	 */
	public static function permission_callback() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Returns certain metadata.
	 *
	 * @param  \WP_REST_Request $request   WP REST API request.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public static function get_meta( $request ) {
		$url = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return new \WP_Error(
				'missing_url',
				'Missing URL.',
				array( 'status' => 400 )
			);
		}

		$url = rawurldecode( $url );

		if ( ! wp_http_validate_url( $url ) ) {
			return new \WP_Error(
				'invalid_url',
				'Invalid URL.',
				array( 'status' => 400 )
			);
		}

		$parser = new Parser( $url );
		$parser->parse();

		return new \WP_REST_Response(
			array(
				'name'   => $parser->get_name(),
				'author' => array(
					'name' => $parser->get_author(),
					'url'  => $parser->get_author_url(),
				),
			)
		);
	}

	/**
	 * Renders the `indieblocks/syndication-links` block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string             Returns the filtered post content of the current post.
	 */
	public static function render_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$urls = array_filter(
			array(
				__( 'Mastodon', 'indieblocks' ) => get_post_meta( $block->context['postId'], '_share_on_mastodon_url', true ),
				__( 'Pixelfed', 'indieblocks' ) => get_post_meta( $block->context['postId'], '_share_on_pixelfed_url', true ),
			)
		);

		$urls = apply_filters( 'indieblocks_syndication_links', $urls );

		if ( empty( $urls ) ) {
			return '';
		}

		$output = esc_html__( 'Also on', 'indieblocks' ) . ' ';

		foreach ( $urls as $name => $url ) {
			$output .= ' <a class="u-syndication" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>, ';
		}

		$wrapper_attributes = get_block_wrapper_attributes();

		return '<div ' . $wrapper_attributes . '>' .
			rtrim( $output, ', ' ) .
		'</div>';
	}
}
