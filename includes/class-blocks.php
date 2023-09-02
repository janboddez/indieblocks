<?php
/**
 * Where Gutenberg blocks are registered.
 *
 * @todo: Move block registration and render functions to their respective folders?
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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'init', array( __CLASS__, 'register_block_patterns' ), 15 );
		add_action( 'init', array( __CLASS__, 'register_block_templates' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoint' ) );
		add_filter( 'excerpt_allowed_wrapper_blocks', array( __CLASS__, 'excerpt_allow_wrapper_blocks' ) );
		add_filter( 'excerpt_allowed_blocks', array( __CLASS__, 'excerpt_allow_blocks' ) );

		$options = get_options();
		if ( ! empty( $options['webmention_facepile'] ) ) {
			add_action( 'pre_get_comments', array( Webmention::class, 'comment_query' ) );
			add_filter( 'get_comments_number', array( Webmention::class, 'comment_count' ), 999, 2 );
		}
	}

	/**
	 * Registers common JS.
	 *
	 * `common.js` (to avoid too much code duplication) is required by the
	 * Bookmark, Like, Reply, and Repost blocks, and itself requires the
	 * `wp-element`, `wp-i18n`, `wp-api-fetch` assets.
	 */
	public static function register_scripts() {
		wp_register_script(
			'indieblocks-common',
			plugins_url( '/assets/common.js', __DIR__ ),
			array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
			\IndieBlocks\Plugin::PLUGIN_VERSION,
			true
		);

		wp_set_script_translations(
			'indieblocks-common',
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);

		wp_localize_script(
			'indieblocks-common',
			'indieblocks_common_obj',
			array(
				'assets_url' => plugins_url( '/assets/', __DIR__ ),
			)
		);
	}

	/**
	 * Registers the different blocks.
	 */
	public static function register_blocks() {
		// Dynamic blocks.
		foreach ( array( 'Facepile', 'Facepile_Content', 'Location', 'Syndication', 'Link_Preview' ) as $class ) {
			$class = '\\IndieBlocks\\' . $class . '_Block';
			$class::register();
		}

		// Static blocks.
		foreach ( array( 'context', 'bookmark', 'like', 'reply', 'repost' ) as $block ) {
			register_block_type( dirname( __DIR__ ) . "/blocks/$block" );

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

		register_block_pattern(
			'indieblocks/repost-starter-pattern',
			array(
				'title'       => __( 'Repost Starter Pattern', 'indieblocks' ),
				'description' => __( 'A nearly blank starter pattern for &ldquo;IndieWeb&rdquo;-style reposts.', 'indieblocks' ),
				'categories'  => array( 'text' ),
				'content'     => '<!-- wp:indieblocks/context -->
					<div class="wp-block-indieblocks-context"></div>
					<!-- /wp:indieblocks/context -->

					<!-- wp:quote {"className":"e-content"} -->
					<blockquote class="wp-block-quote e-content"><!-- wp:paragraph -->
					<p></p>
					<!-- /wp:paragraph --></blockquote>
					<!-- /wp:quote -->',
			)
		);
	}

	/**
	 * Registers the Like block template.
	 *
	 * I.e., a new like (the custom post type) will start with an (empty) Like
	 * block.
	 */
	public static function register_block_templates() {
		$post_type_object = get_post_type_object( 'indieblocks_like' );

		if ( ! $post_type_object ) {
			// Post type not active.
			return;
		}

		$post_type_object->template = array(
			array(
				'indieblocks/like',
				array(),
				array( array( 'core/paragraph' ) ),
			),
		);
	}

	/**
	 * Allows IndieBlocks' blocks `InnerBlocks` in excerpts.
	 *
	 * @param  array $allowed_wrapper_blocks Allowed wrapper blocks.
	 * @return array                         Filtered list of allowed blocks.
	 */
	public static function excerpt_allow_wrapper_blocks( $allowed_wrapper_blocks ) {
		$plugin_blocks = array( 'indieblocks/bookmark', 'indieblocks/like', 'indieblocks/reply', 'indieblocks/repost' );

		return array_merge( $allowed_wrapper_blocks, $plugin_blocks );
	}

	/**
	 * Allows IndieBlocks' context block in excerpts.
	 *
	 * @param  array $excerpt_allowed_blocks Allowed wrapper blocks.
	 * @return array                         Filtered list of allowed blocks.
	 */
	public static function excerpt_allow_blocks( $excerpt_allowed_blocks ) {
		$excerpt_allowed_blocks[] = 'indieblocks/context';

		return $excerpt_allowed_blocks;
	}

	/**
	 * Registers (block-related) REST API endpoints.
	 */
	public static function register_api_endpoint() {
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
	 * Returns certain metadata for a specific, often external, URL.
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
				'name'   => sanitize_text_field( $parser->get_name() ),
				'author' => array(
					'name' => sanitize_text_field( $parser->get_author() ),
					'url'  => esc_url_raw( $parser->get_author_url() ), // Not currently used by any block.
				),
			)
		);
	}
}
