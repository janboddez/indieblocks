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
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_icons' ), 999 );
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
			\IndieBlocks\IndieBlocks::PLUGIN_VERSION,
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
		// (Semi-)dynamic blocks; these have a render callback.
		$dyn_blocks = array( 'facepile', 'facepile-content', 'location', 'syndication' );

		global $wp_version;

		$options = get_options();
		if ( ! empty( $options['preview_cards'] ) && version_compare( $wp_version, '6.2.0' ) >= 0 ) {
			$dyn_blocks[] = 'link-preview';
		}

		foreach ( $dyn_blocks as $block ) {
			register_block_type_from_metadata(
				dirname( __DIR__ ) . "/blocks/$block",
				array(
					'render_callback' => array( __CLASS__, 'render_' . str_replace( '-', '_', $block ) . '_block' ),
				)
			);

			// This oughta happen automatically, but whatevs.
			wp_set_script_translations(
				generate_block_asset_handle( "indieblocks/$block", 'editorScript' ),
				'indieblocks',
				dirname( __DIR__ ) . '/languages'
			);
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

	/**
	 * Renders the `indieblocks/facepile` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                Output HTML.
	 */
	public static function render_facepile_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$facepile_comments = static::get_facepile_comments( $block->context['postId'] );

		if ( empty( $facepile_comments ) ) {
			return '';
		}

		return $block->render( array( 'dynamic' => false ) );
	}

	/**
	 * Renders the `indieblocks/facepile-content` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                Facepile HTML, or an empty string.
	 */
	public static function render_facepile_content_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$facepile_comments = static::get_facepile_comments( $block->context['postId'] );

		if ( empty( $facepile_comments ) ) {
			return '';
		}

		// Enqueue front-end block styles.
		wp_enqueue_style( 'indieblocks-facepile', plugins_url( '/assets/facepile.css', dirname( __FILE__ ) ), array(), IndieBlocks::PLUGIN_VERSION, false );

		// Limit comments. Might provide a proper option later.
		$facepile_num      = apply_filters( 'indieblocks_facepile_num', 25, $block->context['postId'] );
		$facepile_comments = array_slice( $facepile_comments, 0, $facepile_num );

		$output = '';

		foreach ( $facepile_comments as $comment ) {
			$avatar = get_avatar( $comment, 40 );

			if ( empty( $avatar ) ) {
				continue;
			}

			// Add author name as `alt` text.
			if ( preg_match( '~ alt=("|\'){2}~', $avatar, $matches ) ) {
				$avatar = str_replace(
					" alt={$matches[1]}{$matches[1]}",
					" alt={$matches[1]}" . esc_attr( get_comment_author( $comment ) ) . "{$matches[1]}",
					$avatar
				);
			}

			$source = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_source', true );
			$kind   = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_kind', true );

			if ( in_array( $comment->comment_type, array( 'bookmark', 'like', 'repost' ), true ) ) {
				// Mentions initiated by the Webmention plugin use a slightly different data structure.
				$source = get_comment_meta( $comment->comment_ID, 'webmention_source_url', true );
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
				$output .= '<li class="h-cite' . ( ! empty( $class ) ? " $class" : '' ) . '"' . ( ! empty( $title ) ? ' title="' . $title . '"' : '' ) . '>' .
				'<a class="u-url" href="' . esc_url( $source ) . '" target="_blank" rel="noopener noreferrer"><span class="h-card p-author">' . $avatar . '</span>' .
				( ! empty( $attributes['icons'] ) && ! empty( $kind )
					? '<svg class="icon indieblocks-icon-' . $kind . '" aria-hidden="true" role="img"><use href="#indieblocks-icon-' . $kind . '" xlink:href="#indieblocks-icon-' . $kind . '"></use></svg>'
					: ''
				) .
				"</a></li>\n";
			} else {
				$output .= '<li class="h-cite' . ( ! empty( $class ) ? " $class" : '' ) . '"' . ( ! empty( $title ) ? ' title="' . $title . '"' : '' ) . '>' .
				'<span class="p-author h-card">' . $avatar . '</span>' .
				( ! empty( $attributes['icons'] ) && ! empty( $kind )
					? '<svg class="icon indieblocks-icon-' . $kind . '" aria-hidden="true" role="img"><use href="#indieblocks-icon-' . $kind . '" xlink:href="#indieblocks-icon-' . $kind . '"></use></svg>'
					: ''
				) .
				"</li>\n";
			}
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
	 * Renders the `indieblocks/location` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                Output HTML.
	 */
	public static function render_location_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$location = get_post_meta( $block->context['postId'], 'geo_address', true );

		if ( empty( $location ) ) {
			return '';
		}

		$output = '<span class="p-name">' . esc_html( $location ) . '</span>';

		if ( ! empty( $attributes['includeWeather'] ) ) {
			$weather = get_post_meta( $block->context['postId'], '_indieblocks_weather', true );
		}

		if ( ! empty( $weather['description'] ) && ! empty( $weather['temperature'] ) ) {
			$temp = $weather['temperature'];
			$temp = $temp > 100 // Older plugin versions supported only degress Celsius, newer versions only Kelvin.
				? $temp - 273.15
				: $temp;

			$options = get_options();

			if ( empty( $options['weather_units'] ) || 'metric' === $options['weather_units'] ) {
				$temp_unit = '&nbsp;°C';
			} else {
				$temp      = 32 + $temp * 9 / 5;
				$temp_unit = '&nbsp;°F';
			}
			$temp = number_format( round( $temp ) ); // Round.

			$sep = ! empty( $attributes['separator'] ) ? $attributes['separator'] : ' • ';
			$sep = apply_filters( 'indieblocks_location_separator', $sep, $block->context['postId'] );

			$output .= '<span class="sep" aria-hidden="true">' . esc_html( $sep ) . '</span><span class="indieblocks-weather">' . esc_html( $temp . $temp_unit ) . ', ' . esc_html( strtolower( $weather['description'] ) ) . '</span>';
		}

		$output = apply_filters( 'indieblocks_location_html', '<span class="h-geo">' . $output . '</span>', $block->context['postId'] );

		$wrapper_attributes = get_block_wrapper_attributes();

		return '<div ' . $wrapper_attributes . '>' .
			$output .
		'</div>';
	}

	/**
	 * Renders the `indieblocks/syndication` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                The block's output HTML.
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

		// Allow developers to parse in other plugins' links.
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

	/**
	 * Queries for a post's "facepile" comments.
	 *
	 * @param  int $post_id Post ID.
	 * @return array        Facepile comments.
	 */
	protected static function get_facepile_comments( $post_id ) {
		$facepile_comments = wp_cache_get( "indieblocks:facepile-comments:$post_id" );

		if ( false !== $facepile_comments ) {
			return $facepile_comments;
		}

		// When the "facepile" setting's enabled, we _remove_ the very comments
		// we now want to fetch, so we have to temporarily disable that
		// behavior.
		remove_action( 'pre_get_comments', array( \IndieBlocks\Webmention::class, 'comment_query' ) );

		// Placeholder.
		$facepile_comments           = new \stdClass();
		$facepile_comments->comments = array();

		// IndieBlocks' webmentions use custom fields to set them apart.
		$args = array(
			'post_id'    => $post_id,
			'fields'     => 'ids',
			'status'     => 'approve',
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => 'indieblocks_webmention_kind',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'indieblocks_webmention_kind',
					'compare' => 'IN',
					'value'   => apply_filters( 'indieblocks_facepile_kinds', array( 'bookmark', 'like', 'repost' ), $post_id ),
				),
			),
		);
		if ( 0 !== get_current_user_id() ) {
			$args['include_unapproved'] = array( get_current_user_id() );
		}
		$indieblocks_comments = new \WP_Comment_Query( $args );

		// The Webmention plugin's mentions use "proper" comment types.
		$args = array(
			'post_id'  => $post_id,
			'fields'   => 'ids',
			'status'   => 'approve',
			'type__in' => apply_filters( 'indieblocks_facepile_kinds', array( 'bookmark', 'like', 'repost' ), $post_id ),
		);
		if ( 0 !== get_current_user_id() ) {
			$args['include_unapproved'] = array( get_current_user_id() );
		}
		$webmention_comments = new \WP_Comment_Query( $args );

		$comment_ids = array_unique( array_merge( $indieblocks_comments->comments, $webmention_comments->comments ) );

		if ( ! empty( $comment_ids ) ) {
			// Grab 'em all.
			$facepile_comments = new \WP_Comment_Query(
				array(
					'comment__in' => $comment_ids,
					'post_id'     => $post_id,
					'order_by'    => 'comment_date',
					'order'       => 'ASC',
				)
			);
		}

		$options = get_options();
		if ( ! empty( $options['webmention_facepile'] ) ) {
			// Restore the filter disabled above, but only if it was active before!
			add_action( 'pre_get_comments', array( \IndieBlocks\Webmention::class, 'comment_query' ) );
		}

		// Allow filtering the resulting comments.
		$facepile_comments = apply_filters( 'indieblocks_facepile_comments', $facepile_comments->comments, $post_id );

		// Cache for the duration of the request (and then some)?
		wp_cache_set( "indieblocks:facepile-comments:$post_id", $facepile_comments, '', 10 );
		return $facepile_comments;
	}

	/**
	 * Outputs bookmark, like, and repost icons so they can be used anywhere on
	 * the page.
	 */
	public static function print_icons() {
		$icons = dirname( __DIR__ ) . '/assets/webmention-icons.svg';

		if ( is_readable( $icons ) ) {
			require_once $icons;
		}
	}

	/**
	 * Renders the `indieblocks/link-preview` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                The block's output HTML.
	 */
	public static function render_link_preview_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$card = get_post_meta( $block->context['postId'], '_indieblocks_link_preview', true );

		if ( empty( $card['title'] ) || empty( $card['url'] ) ) {
			return '';
		}

		// Enqueue front-end block styles.
		wp_enqueue_style( 'indieblocks-link-preview', plugins_url( '/assets/link-preview.css', dirname( __FILE__ ) ), array(), IndieBlocks::PLUGIN_VERSION, false );

		$border_style = '';
		if ( ! empty( $attributes['borderColor'] ) ) {
			$border_style .= "border-color:var(--wp--preset--color--{$attributes['borderColor']});";
		} elseif ( ! empty( $attributes['style']['border']['color'] ) ) {
			$border_style .= "border-color:{$attributes['style']['border']['color']};";
		}
		if ( ! empty( $attributes['style']['border']['width'] ) ) {
			$border_style .= "border-width:{$attributes['style']['border']['width']};";
		}
		if ( ! empty( $attributes['style']['border']['radius'] ) ) {
			$border_style .= "border-radius:{$attributes['style']['border']['radius']};";
		}
		$border_style = trim( $border_style );

		ob_start();
		?>
		<a class="indieblocks-card" href="<?php echo esc_url( $card['url'] ); ?>" rel="nofollow">
			<?php
			printf( '<div class="indieblocks-card-thumbnail" style="%s">', esc_attr( trim( $border_style . ' border-block: none; border-inline-start: none; border-radius: 0 !important;' ) ) );

			if ( ! empty( $card['thumbnail'] ) ) :
				// Check if file still exists.
				$upload_dir = wp_upload_dir();
				$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $card['thumbnail'] );

				if ( is_file( $file_path ) ) :
					?>
					<img src="<?php echo esc_url_raw( $card['thumbnail'] ); ?>" width="90" height="90" alt="">
					<?php
				endif;
			endif;

			echo '</div>';
			?>
			<div class="indieblocks-card-body" style="width: calc(100% - 90px - <?php echo isset( $attributes['style']['border']['width'] ) ? esc_attr( $attributes['style']['border']['width'] ) : '0px'; ?>);">
				<strong><?php echo esc_html( $card['title'] ); ?></strong>
				<small><?php echo esc_html( preg_replace( '~www.~', '', wp_parse_url( $card['url'], PHP_URL_HOST ) ) ); ?></small>
			</div>
		</a>
		<?php
		$output = ob_get_clean();

		$wrapper_attributes = get_block_wrapper_attributes();

		$output = '<div ' . $wrapper_attributes . ' >' .
			$output .
		'</div>';

		$processor = new \WP_HTML_Tag_Processor( $output );
		$processor->next_tag( 'div' );

		$style = $processor->get_attribute( 'style' );
		if ( null === $style ) {
			$processor->set_attribute( 'style', esc_attr( $border_style ) );
		} else {
			// Append our styles.
			$processor->set_attribute( 'style', esc_attr( rtrim( $style, ';' ) . ";$border_style" ) );
		}

		return $processor->get_updated_html();
	}
}
