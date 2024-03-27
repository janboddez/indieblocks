<?php
/**
 * Where Gutenberg blocks are registered.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

class Blocks {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_scripts' ), 11 );
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'init', array( __CLASS__, 'register_block_patterns' ), 15 );
		add_action( 'init', array( __CLASS__, 'register_block_templates' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_meta' ) );
		add_filter( 'excerpt_allowed_wrapper_blocks', array( __CLASS__, 'excerpt_allow_wrapper_blocks' ) );
		add_filter( 'excerpt_allowed_blocks', array( __CLASS__, 'excerpt_allow_blocks' ) );
		add_filter( 'the_excerpt_rss', array( __CLASS__, 'excerpt_feed' ) );

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
	 * Enqueues additional scripts, like the Location sidebar panel thingy.
	 */
	public static function enqueue_scripts() {
		$current_screen = get_current_screen();

		if (
			isset( $current_screen->post_type ) &&
			in_array( $current_screen->post_type, apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ), true )
		) {
			wp_enqueue_script(
				'indieblocks-location',
				plugins_url( '/assets/location.js', __DIR__ ),
				array(
					'wp-element',
					'wp-components',
					'wp-i18n',
					'wp-data',
					'wp-core-data',
					'wp-plugins',
					'wp-edit-post',
					'wp-api-fetch',
					'wp-url',
				),
				\IndieBlocks\Plugin::PLUGIN_VERSION,
				false
			);
		}
	}

	/**
	 * Registers the different blocks.
	 */
	public static function register_blocks() {
		$blocks = array(
			'bookmark',
			'context',
			'facepile',
			'facepile-content',
			'like',
			'link-preview',
			'location',
			'reply',
			'repost',
			'syndication',
		);

		foreach ( $blocks as $block ) {
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
	 * Allows, well, everything in feed excerpts.
	 *
	 * @param  string $excerpt Current post excerpt.
	 * @return string          Filtered excerpt.
	 */
	public static function excerpt_feed( $excerpt ) {
		global $post;

		if ( empty( $post->post_content ) ) {
			return $excerpt;
		}

		$post_types = (array) apply_filters( 'indieblocks_short-form_post_types', array( 'indieblocks_like', 'indieblocks_note' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return $excerpt;
		}

		$content = apply_filters( 'the_content', $post->post_content );
		$content = str_replace( ']]>', ']]&gt;', $content );

		$excerpt_length = (int) apply_filters( 'excerpt_length', 55 );
		$excerpt_more   = apply_filters( 'excerpt_more', ' [&hellip;]' );
		$excerpt        = wp_trim_words( $content, $excerpt_length, $excerpt_more );

		return $excerpt;
	}

	/**
	 * Registers (block-related) REST API endpoints.
	 */
	public static function register_api_endpoints() {
		register_rest_route(
			'indieblocks/v1',
			'/meta',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( __CLASS__, 'get_url_meta' ),
				'permission_callback' => array( __CLASS__, 'url_permission_callback' ),
			)
		);

		register_rest_route(
			'indieblocks/v1',
			'/location',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( __CLASS__, 'get_location_meta' ),
				'permission_callback' => array( __CLASS__, 'location_permission_callback' ),
			)
		);
	}

	/**
	 * Returns certain metadata for a specific, often external, URL.
	 *
	 * @param  \WP_REST_Request $request   WP REST API request.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public static function get_url_meta( $request ) {
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

	/**
	 * URL meta permission callback.
	 *
	 * @return bool If the request's authorized or not.
	 */
	public static function url_permission_callback() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Exposes Location metadata to the REST API.
	 *
	 * @param  \WP_REST_Request|array $request API request (parameters).
	 * @return array|\WP_Error                 Response (or error).
	 */
	public static function get_location_meta( $request ) {
		if ( is_array( $request ) ) {
			$post_id = $request['id'];
		} else {
			$post_id = $request->get_param( 'post_id' );
		}

		if ( empty( $post_id ) || ! ctype_digit( (string) $post_id ) ) {
			return new \WP_Error( 'invalid_id', 'Invalid post ID.', array( 'status' => 400 ) );
		}

		$post_id = (int) $post_id;

		return array(
			'name' => get_post_meta( $post_id, 'geo_address', true ),
		);
	}

	/**
	 * Location REST API permission callback.
	 *
	 * @param  \WP_REST_Request $request WP REST API request.
	 * @return bool                      If the request's authorized.
	 */
	public static function location_permission_callback( $request ) {
		$post_id = $request->get_param( 'post_id' );

		if ( empty( $post_id ) || ! ctype_digit( (string) $post_id ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Registers (some of) IndieBlocks' custom fields for use with the REST API.
	 */
	public static function register_meta() {

		$post_types = apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) );

		foreach ( $post_types as $post_type ) {
			if ( use_block_editor_for_post_type( $post_type ) ) {
				// Allow these fields to be *set* by the block editor.
				register_post_meta(
					$post_type,
					'geo_latitude',
					array(
						'single'            => true,
						'show_in_rest'      => true,
						'type'              => 'string',
						'default'           => '',
						'auth_callback'     => function () {
							return current_user_can( 'edit_posts' );
						},
						'sanitize_callback' => function ( $meta_value ) {
							return sanitize_text_field( $meta_value );
						},
					)
				);

				register_post_meta(
					$post_type,
					'geo_longitude',
					array(
						'single'            => true,
						'show_in_rest'      => true,
						'type'              => 'string',
						'default'           => '',
						'auth_callback'     => function () {
							return current_user_can( 'edit_posts' );
						},
						'sanitize_callback' => function ( $meta_value ) {
							return sanitize_text_field( $meta_value );
						},
					)
				);

				register_post_meta(
					$post_type,
					'geo_address',
					array(
						'single'            => true,
						'show_in_rest'      => true,
						'type'              => 'string',
						'default'           => '',
						'auth_callback'     => function () {
							return current_user_can( 'edit_posts' );
						},
						'sanitize_callback' => function ( $meta_value ) {
							return sanitize_text_field( $meta_value );
						},
					)
				);
			}
		}
	}
}
