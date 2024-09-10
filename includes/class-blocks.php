<?php
/**
 * Where Gutenberg blocks are registered.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

use IndieBlocks\Webmention\Webmention;

class Blocks {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );

		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'init', array( __CLASS__, 'register_block_patterns' ), 15 );
		add_action( 'init', array( __CLASS__, 'register_block_templates' ), 20 );

		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );

		add_filter( 'excerpt_allowed_wrapper_blocks', array( __CLASS__, 'excerpt_allow_wrapper_blocks' ) );
		add_filter( 'excerpt_allowed_blocks', array( __CLASS__, 'excerpt_allow_blocks' ) );
		add_filter( 'the_excerpt_rss', array( __CLASS__, 'excerpt_feed' ) );

		$options = get_options();
		if ( ! empty( $options['webmention_facepile'] ) ) {
			add_action( 'pre_get_comments', array( Webmention::class, 'comment_query' ) );
			add_filter( 'get_comments_number', array( Webmention::class, 'comment_count' ), 999, 2 );
		}

		if ( ! empty( $options['facepile_block_hook'] ) ) {
			add_filter( 'hooked_block_types', array( Webmention::class, 'hook_facepile_block' ), 10, 4 );
			add_filter( 'hooked_block_indieblocks/facepile', array( Webmention::class, 'modify_hooked_facepile_block' ), 10, 5 );
		}
	}

	/**
	 * Registers common JS.
	 */
	public static function register_scripts() {
		wp_register_script(
			'indieblocks-common',
			plugins_url( '/assets/common.js', __DIR__ ),
			array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
			Plugin::PLUGIN_VERSION,
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
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
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
	 * Registers block patterns.
	 *
	 * These are mostly outdated now.
	 */
	public static function register_block_patterns() {
		// Did previously implement this as a block variation (with different default inner blocks), and while that may
		// have worked even "better," the `isActive` part was kind of hard to figure out, and thus the variation would
		// "look" as "just another" Facepile block. Plus, it's kind of a lot of attributes; maybe this should be a
		// different block (or perhaps "inner block") altogether?
		/** @todo: Add screen reader text to the SVG icon rather than in yet another Paragraph block. */
		register_block_pattern(
			'indieblocks/reaction-counts',
			array(
				'title'      => __( 'Reaction counts', 'indieblocks' ),
				'categories' => array( 'text' ),
				'content'    => '<!-- wp:indieblocks/facepile -->
					<div class="wp-block-indieblocks-facepile">
						<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
						<div class="wp-block-group">
							<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
							<div class="wp-block-group">
								<!-- wp:paragraph {"className":"screen-reader-text"} -->
								<p class="screen-reader-text">' . esc_html__( 'Likes', 'indieblocks' ) . '</p>
								<!-- /wp:paragraph -->

								<!-- wp:indieblocks/facepile-content {"type":["like"],"countOnly":true,"forceShow":true} /-->
							</div>
							<!-- /wp:group -->

							<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
							<div class="wp-block-group">
								<!-- wp:paragraph {"className":"screen-reader-text"} -->
								<p class="screen-reader-text">' . esc_html__( 'Bookmarks', 'indieblocks' ) . '</p>
								<!-- /wp:paragraph -->

								<!-- wp:indieblocks/facepile-content {"type":["bookmark"],"countOnly":true,"forceShow":true} /-->
							</div>
							<!-- /wp:group -->

							<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
							<div class="wp-block-group">
								<!-- wp:paragraph {"className":"screen-reader-text"} -->
								<p class="screen-reader-text">' . esc_html__( 'Reposts', 'indieblocks' ) . '</p>
								<!-- /wp:paragraph -->

								<!-- wp:indieblocks/facepile-content {"type":["repost"],"countOnly":true,"forceShow":true} /-->
							</div>
							<!-- /wp:group -->
						</div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:indieblocks/facepile -->',
			)
		);

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
}
