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
	 * Registers the "Note Context" block.
	 */
	public static function register_blocks() {
		register_block_type( dirname( __DIR__ ) . '/blocks/context' );

		// This oughta happen automatically, but whatevs.
		$script_handle = generate_block_asset_handle( 'indieblocks/context', 'editorScript' );
		wp_set_script_translations( $script_handle, 'indieblocks', dirname( __DIR__ ) . '/languages' );
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

			if ( 'indieblocks_note' === $post_type ) {
				$post_type_object->template[] = array(
					'core/group',
					array( 'className' => 'e-content' ),
					array(
						array( 'core/paragraph' ),
					),
				);
			}
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
			'/title',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( __CLASS__, 'get_title' ),
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
	 * Returns the contents of a web page's `title` element.
	 *
	 * @param  \WP_REST_Request $request   WP REST API request.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public static function get_title( $request ) {
		$url = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return rest_ensure_response( '' );
		}

		$url = rawurldecode( $url );

		if ( ! wp_http_validate_url( $url ) ) {
			return rest_ensure_response( '' );
		}

		$parser = new Parser( $url );
		$parser->parse();

		$title = $parser->get_title();

		return rest_ensure_response( $title );
	}
}
