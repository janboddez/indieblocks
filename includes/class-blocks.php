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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'init', array( __CLASS__, 'register_block_patterns' ), 15 );
		add_action( 'init', array( __CLASS__, 'register_block_templates' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );
	}

	/**
	 * Registers common JS.
	 */
	public static function register_scripts() {
		wp_register_script(
			'indieblocks-common',
			plugins_url( '/assets/common.js', dirname( __FILE__ ) ),
			array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
			\IndieBlocks\IndieBlocks::PLUGIN_VERSION,
			true
		);

		wp_set_script_translations(
			'indieblocks-common',
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);
	}

	/**
	 * Registers the different blocks.
	 */
	public static function register_blocks() {
		register_block_type_from_metadata(
			dirname( __DIR__ ) . '/blocks/reactions',
			array(
				'render_callback' => array( __CLASS__, 'render_reactions_block' ),
			)
		);

		// This oughta happen automatically, but whatevs.
		wp_set_script_translations(
			generate_block_asset_handle( 'indieblocks/reactions', 'editorScript' ),
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);

		register_block_type_from_metadata(
			dirname( __DIR__ ) . '/blocks/syndication',
			array(
				'render_callback' => array( __CLASS__, 'render_syndication_block' ),
			)
		);

		// This oughta happen automatically, but whatevs.
		wp_set_script_translations(
			generate_block_asset_handle( 'indieblocks/syndication', 'editorScript' ),
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
	 * Registers Note and Like block templates.
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
	 * Renders the `indieblocks/reactions` block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               The filtered post content of the current post.
	 */
	public static function render_reactions_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$args = array(
			'post_id'    => $block->context['postId'],
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => 'indieblocks_webmention_kind',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'indieblocks_webmention_kind',
					'compare' => 'IN',
					'value'   => array( 'bookmark', 'like', 'repost' ),
				),
			),
		);

		remove_action( 'pre_get_comments', array( \IndieBlocks\Webmention::class, 'comment_query' ) );
		$comments_query = new \WP_Comment_Query( $args );
		add_action( 'pre_get_comments', array( \IndieBlocks\Webmention::class, 'comment_query' ) );

		if ( empty( $comments_query->comments ) ) {
			return '';
		}

		// Enqueue front-end block styles.
		wp_enqueue_style( 'indieblocks-reactions', plugins_url( '/assets/reactions.css', dirname( __FILE__ ) ), array(), IndieBlocks::PLUGIN_VERSION, false );

		// @todo: Limit comments. We'll fix this later.
		$comments = array_slice( $comments_query->comments, 0, 25 );
		$output   = '';

		foreach ( $comments as $comment ) {
			$avatar = get_avatar( $comment, 40 );
			$source = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_source', true );

			if ( ! empty( $source ) ) {
				$output .= '<span class="indieblocks-avatar"><a href="' . esc_url( $source ) . '" target="_blank" rel="noopener noreferrer">' . $avatar . '</a></span>';
			} else {
				$output .= '<span class="indieblocks-avatar">' . $avatar . '</span>';
			}
		}

		$wrapper_attributes = get_block_wrapper_attributes();

		return '<div ' . $wrapper_attributes . '>' .
			rtrim( $output, ', ' ) .
		'</div>';
	}

	/**
	 * Renders the `indieblocks/syndication` block.
	 *
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block default content.
	 * @param  WP_Block $block      Block instance.
	 * @return string               The filtered post content of the current post.
	 */
	public static function render_syndication_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$urls = array_filter(
			array(
				__( 'Mastodon', 'indieblocks' ) => get_post_meta( $block->context['postId'], '_share_on_mastodon_url', true ),
				__( 'Pixelfed', 'indieblocks' ) => get_post_meta( $block->context['postId'], '_share_on_pixelfed_url', true ),
			)
		);

		$urls = apply_filters( 'indieblocks_syndication_links', $urls, $block->context['postId'] );

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
