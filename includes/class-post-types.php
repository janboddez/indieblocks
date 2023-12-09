<?php
/**
 * Short-form custom post types.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Introduces a number of short-form custom post types, and more.
 */
class Post_Types {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		$options = get_options();

		// Register short-form post types.
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 9 );

		// Custom permalinks and date-based archives.
		add_action( 'init', array( __CLASS__, 'custom_permalinks' ) );
		add_action( 'init', array( __CLASS__, 'create_date_archives' ) );

		if ( ! empty( $options['date_archives'] ) ) {
			add_action( 'wp', array( __CLASS__, 'set_404_if_empty' ) );
		}

		if ( ! empty( $options['permalink_format'] ) ) {
			add_filter( 'post_type_link', array( __CLASS__, 'post_type_link' ), 10, 2 );
			add_filter( 'wp_unique_post_slug', array( __CLASS__, 'prevent_slug_clashes' ), 10, 6 );
		}

		// If applicable, include short-form post types on the blog page, in
		// author archives, or in category and tag archives.
		add_action( 'pre_get_posts', array( __CLASS__, 'include_in_archives' ), 99 );

		if ( ! empty( $options['automatic_titles'] ) ) {
			// Automatically generate a "post title" for short-form posts.
			add_filter( 'wp_insert_post_data', array( __CLASS__, 'set_title' ), 10, 2 );
		}

		if ( ! empty( $options['random_slugs'] ) ) {
			// Generate a random slug for short-form posts.
			add_filter( 'wp_insert_post_data', array( __CLASS__, 'set_slug' ), 11, 2 );
		}

		if ( ! empty( $options['enable_notes'] ) ) {
			add_filter( 'save_post_indieblocks_note', array( __CLASS__, 'set_post_meta' ) );
			add_filter( 'rest_after_insert_indieblocks_note', array( __CLASS__, 'set_post_meta' ) );
		}

		if ( ! empty( $options['enable_likes'] ) ) {
			add_filter( 'save_post_indieblocks_like', array( __CLASS__, 'set_post_meta' ) );
			add_filter( 'rest_after_insert_indieblocks_like', array( __CLASS__, 'set_post_meta' ) );
		}
	}

	/**
	 * Registers custom post types.
	 */
	public static function register_post_types() {
		$options = get_options();

		if ( ! empty( $options['post_types'] ) || ! empty( $options['enable_notes'] ) ) {
			// Notes.
			$args = array(
				'labels'            => array(
					'name'               => __( 'Notes', 'indieblocks' ),
					'singular_name'      => __( 'Note', 'indieblocks' ),
					'add_new'            => __( 'New Note', 'indieblocks' ),
					'add_new_item'       => __( 'Add New Note', 'indieblocks' ),
					'edit_item'          => __( 'Edit Note', 'indieblocks' ),
					'view_item'          => __( 'View Note', 'indieblocks' ),
					'view_items'         => __( 'View Notes', 'indieblocks' ),
					'search_items'       => __( 'Search Notes', 'indieblocks' ),
					'not_found'          => __( 'No notes found.', 'indieblocks' ),
					'not_found_in_trash' => __( 'No notes found in trash.', 'indieblocks' ),
				),
				'public'            => true,
				'has_archive'       => true,
				'show_in_nav_menus' => true,
				'rewrite'           => array(
					'slug'       => __( 'notes', 'indieblocks' ),
					'with_front' => ! empty( $options['permalink_format'] ) && 0 === strpos( $options['permalink_format'], '/%front%' ) ? true : false,
				),
				'supports'          => array( 'author', 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'trackbacks', 'comments', 'wpcom-markdown' ),
				'menu_icon'         => 'dashicons-format-status',
				'capability_type'   => 'post',
				'map_meta_cap'      => true,
				'menu_position'     => 5,
			);

			if ( ! empty( $options['enable_blocks'] ) ) {
				// Enable the block editor.
				$args['show_in_rest'] = true;
			}

			if ( ! empty( $options['note_taxonomies'] ) || ! empty( $options['default_taxonomies'] ) ) {
				// Enable WordPress' default categories and tags.
				$args['taxonomies'] = array( 'category', 'post_tag' );
			}

			register_post_type( 'indieblocks_note', $args );
		}

		if ( ! empty( $options['post_types'] ) || ! empty( $options['enable_likes'] ) ) {
			// Likes.
			$args = array(
				'labels'            => array(
					'name'               => __( 'Likes', 'indieblocks' ),
					'singular_name'      => __( 'Like', 'indieblocks' ),
					'add_new'            => __( 'New Like', 'indieblocks' ),
					'add_new_item'       => __( 'Add New Like', 'indieblocks' ),
					'edit_item'          => __( 'Edit Like', 'indieblocks' ),
					'view_item'          => __( 'View Like', 'indieblocks' ),
					'view_items'         => __( 'View Likes', 'indieblocks' ),
					'search_items'       => __( 'Search Likes', 'indieblocks' ),
					'not_found'          => __( 'No likes found.', 'indieblocks' ),
					'not_found_in_trash' => __( 'No likes found in trash.', 'indieblocks' ),
				),
				'public'            => true,
				'has_archive'       => true,
				'show_in_nav_menus' => true,
				'rewrite'           => array(
					'slug'       => __( 'likes', 'indieblocks' ),
					'with_front' => ! empty( $options['permalink_format'] ) && 0 === strpos( $options['permalink_format'], '/%front%' ) ? true : false,
				),
				'supports'          => array( 'author', 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'trackbacks', 'comments', 'wpcom-markdown' ),
				'menu_icon'         => 'dashicons-heart',
				'capability_type'   => 'post',
				'map_meta_cap'      => true,
				'menu_position'     => 5,
			);

			if ( ! empty( $options['enable_blocks'] ) ) {
				$args['show_in_rest'] = true;
			}

			if ( ! empty( $options['like_taxonomies'] ) ) {
				// Enable WordPress' default categories and tags.
				$args['taxonomies'] = array( 'category', 'post_tag' );
			}

			register_post_type( 'indieblocks_like', $args );
		}
	}

	/**
	 * Sets a random slug for short-form content.
	 *
	 * @param  array $data    Filtered data.
	 * @param  array $postarr Original data.
	 * @return array          Updated (slashed) data.
	 */
	public static function set_slug( $data, $postarr ) {
		if ( ! empty( $postarr['ID'] ) ) {
			// Not a new post. Bail.
			return $data;
		}

		if ( ! in_array( $data['post_type'], array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
			return $data;
		}

		global $wpdb;

		// Generate a random slug for short-form content, i.e., notes and likes.
		do {
			// Generate random slug.
			$slug = bin2hex( openssl_random_pseudo_bytes( 5 ) );

			// Check uniqueness.
			$result = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM $wpdb->posts WHERE post_name = %s LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} while ( $result );

		$data['post_name'] = $slug;

		return $data;
	}

	/**
	 * Automatically sets a post title for short-form content, so that it's
	 * easier to browse within WP Admin.
	 *
	 * The one exception is bookmarks, which often _do_ have an actual title.
	 *
	 * @param  array $data    Filtered data.
	 * @param  array $postarr Original data, mostly.
	 * @return array          Updated (slashed) data.
	 */
	public static function set_title( $data, $postarr ) {
		if ( ! in_array( $data['post_type'], array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
			return $data;
		}

		// Whether bookmarks should get autogenerated titles.
		$ignore_bookmark_titles = apply_filters( 'indieblocks_ignore_bookmark_titles', true );

		if ( ! empty( $postarr['meta_input']['mf2_bookmark-of'][0] ) && ! empty( $data['post_title'] ) && $ignore_bookmark_titles ) {
			// Leave _non-empty_ bookmark titles alone. Only affects Micropub
			// posts, though.
			return $data;
		}

		/*
		 * In all other cases, let's generate a post title.
		 *
		 * Some default "filters." Use the `indieblocks_post_title` filter to
		 * undo or extend.
		 */
		$content = wp_unslash( $data['post_content'] );
		$content = apply_filters( 'the_content', $content );

		$options = get_options();
		if ( ! empty( $options['like_and_bookmark_titles'] ) ) {
			// Ideally this would run only when a post is first inserted, but it
			// looks like, since Gutenberg, this function is called multiple
			// times, and that `content` is in fact empty the very first time.
			/* @todo: Look for a more appropriate hook. */
			$hentry = $content;

			if ( ! preg_match( '~ class=("|\')([^"\']*?)e-content([^"\']*?)("|\')~', $content ) ) {
				$hentry = '<div class="e-content">' . $hentry . '</div>';
			}

			$hentry = '<div class="h-entry">' . $hentry . '</div>';

			$parser = new Parser();
			$parser->parse( $hentry );

			// When one of the newer "h-cite" blocks is in use, this'll return
			// the link text, which **can** be overriden by authors.
			$link_name = $parser->get_link_name();

			if ( empty( $link_name ) ) {
				// Let's try and actually parse the linked page.
				$link_url = $parser->get_link_url();

				if ( ! empty( $link_url ) ) {
					$parser = new Parser( esc_url_raw( $link_url ) );
					$parser->parse();

					$link_name = $parser->get_name(); // This could still be a nonsense name, which is why we want this to be overridable by authors!
				}
			}
		}

		if ( ! empty( $link_name ) ) {
			// If a "linked" title was found, use that.
			$title = sanitize_text_field( $link_name );

			// Avoid double-encoded characters.
			$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		} else {
			// Set title to a truncated copy of the content.
			$title = sanitize_text_field( $content );

			// Avoid double-encoded characters.
			$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );

			// Shorten.
			$title = wp_trim_words( $title, 8, ' ...' );
			// Prevent duplicate ellipses.
			$title = str_replace( '... ...', '...', $title );
			$title = str_replace( '… ...', '...', $title );
		}

		$title = wp_slash( $title );

		// Define a filter that allows others to do something else entirely.
		$data['post_title'] = apply_filters( 'indieblocks_post_title', $title, $data['post_title'], $data['post_content'] );

		return $data;
	}

	/**
	 * Set meta for certain IndieWeb post types.
	 *
	 * @param int|\WP_Post $post Post ID or object, depending on where the request originated.
	 */
	public static function set_post_meta( $post ) {
		$post = get_post( $post );

		if ( empty( $post->post_content ) ) {
			return;
		}

		$parser = post_content_parser( $post );

		$kind = $parser->get_type();
		if ( ! empty( $kind ) ) {
			update_post_meta( $post->ID, '_indieblocks_kind', $kind );
		} else {
			delete_post_meta( $post->ID, '_indieblocks_kind' );
		}

		$linked_url = $parser->get_link_url();
		if ( ! empty( $linked_url ) ) {
			update_post_meta( $post->ID, '_indieblocks_linked_url', esc_url_raw( $linked_url ) );
		} else {
			delete_post_meta( $post->ID, '_indieblocks_linked_url' );
		}
	}

	/**
	 * Includes notes in author or taxonomy archives, or on the blog page.
	 *
	 * @param  WP_Query $query The WP_Query object.
	 * @return WP_Query        Modified query object.
	 */
	public static function include_in_archives( $query ) {
		if ( is_admin() ) {
			return $query;
		}

		if ( ! $query->is_main_query() ) {
			return $query;
		}

		if ( ! empty( $query->query_vars['suppress_filters'] ) ) {
			return $query;
		}

		if ( ! $query->is_home() && ! $query->is_author() && ! $query->is_category() && ! $query->is_tag() ) {
			// Unsupported archive type.
			return $query;
		}

		$post_types = $query->get( 'post_type' );
		$post_types = ! empty( $post_types ) ? $post_types : array( 'post' );
		if ( is_string( $post_types ) ) {
			$post_types = explode( ',', $post_types );
		}
		$post_types = array_filter( (array) $post_types );

		$options = get_options();

		// Show on blog page.
		if ( ! empty( $options['notes_in_home'] ) && $query->is_home() ) {
			$post_types[] = 'indieblocks_note';
		}

		if ( ! empty( $options['likes_in_home'] ) && $query->is_home() ) {
			$post_types[] = 'indieblocks_like';
		}

		// Include in author archives.
		if ( ! empty( $options['notes_in_author'] ) && $query->is_author() ) {
			$post_types[] = 'indieblocks_note';
		}

		if ( ! empty( $options['likes_in_author'] ) && $query->is_author() ) {
			$post_types[] = 'indieblocks_like';
		}

		// Include in category and tag archives.
		if (
			! empty( $options['enable_notes'] ) &&
			( ! empty( $options['note_taxonomies'] ) || ! empty( $options['default_taxonomies'] ) ) && // Old name.
			( $query->is_category() || $query->is_tag() )
		) {
			$post_types[] = 'indieblocks_note';
		}

		if (
			! empty( $options['enable_likes'] ) &&
			( ! empty( $options['like_taxonomies'] ) ) &&
			( $query->is_category() || $query->is_tag() )
		) {
			$post_types[] = 'indieblocks_like';
		}

		$query->set( 'post_type', array_unique( $post_types ) );
	}

	/**
	 * Enable custom note and like permalinks.
	 */
	public static function custom_permalinks() {
		$options = get_options();

		if ( ! empty( $options['permalink_format'] ) ) {
			$post_types = array();

			if ( ! empty( $options['enable_notes'] ) ) {
				$post_types[] = 'indieblocks_note';
			}

			if ( ! empty( $options['enable_likes'] ) ) {
				$post_types[] = 'indieblocks_like';
			}

			foreach ( $post_types as $post_type ) {
				$post_type = get_post_type_object( $post_type );

				if ( empty( $post_type->rewrite['slug'] ) ) {
					return;
				}

				// CPTs don't actually use `postname`.
				$permalink_format = str_replace(
					array( '/%front%', '%postname%' ),
					array( '', "%{$post_type->name}%" ),
					$options['permalink_format']
				);

				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				// @todo: Use `add_rewrite_tag()`?
				add_permastruct(
					$post_type->name,
					$post_type->rewrite['slug'] . $permalink_format,
					array( 'with_front' => ! empty( $options['permalink_format'] ) && 0 === strpos( $options['permalink_format'], '/%front%' ) ? true : false )
				);
			}
		}
	}

	/**
	 * Enable date-based archives.
	 */
	public static function create_date_archives() {
		$options = get_options();

		if ( empty( $options['date_archives'] ) ) {
			return;
		}

		$post_types = array();

		if ( ! empty( $options['enable_notes'] ) ) {
			$post_types[] = 'indieblocks_note';
		}

		if ( ! empty( $options['enable_likes'] ) ) {
			$post_types[] = 'indieblocks_like';
		}

		foreach ( $post_types as $post_type ) {
			$post_type = get_post_type_object( $post_type );

			if ( empty( $post_type->rewrite['slug'] ) ) {
				return;
			}

			$post_type_front = $post_type->rewrite['slug'];

			if ( 0 === strpos( $options['permalink_format'], '/%front%' ) ) {
				global $wp_rewrite;

				if ( ! empty( $wp_rewrite->front ) ) {
					$post_type_front = trim( $wp_rewrite->front, '/' ) . '/' . $post_type_front;
				}
			}

			// Day.
			add_rewrite_rule(
				'^' . $post_type_front . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/([0-9]{1,})/?$',
				'index.php?post_type=' . $post_type->name . '&year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]',
				'top'
			);

			add_rewrite_rule(
				'^' . $post_type_front . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$',
				'index.php?post_type=' . $post_type->name . '&year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
				'top'
			);

			// Month.
			add_rewrite_rule(
				'^' . $post_type_front . '/([0-9]{4})/([0-9]{1,2})/page/([0-9]{1,})/?$',
				'index.php?post_type=' . $post_type->name . '&year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]',
				'top'
			);

			add_rewrite_rule(
				'^' . $post_type_front . '/([0-9]{4})/([0-9]{1,2})/?$',
				'index.php?post_type=' . $post_type->name . '&year=$matches[1]&monthnum=$matches[2]',
				'top'
			);

			// Year.
			add_rewrite_rule(
				'^' . $post_type_front . '/([0-9]{4})/page/([0-9]{1,})/?$',
				'index.php?post_type=' . $post_type->name . '&year=$matches[1]&paged=$matches[2]',
				'top'
			);

			add_rewrite_rule(
				'^' . $post_type_front . '/([0-9]{4})/?$',
				'index.php?post_type=' . $post_type->name . '&year=$matches[1]',
				'top'
			);
		}
	}

	/**
	 * Returns a proper permalink for IndieBlocks posts.
	 *
	 * @param  string  $permalink Post permalink.
	 * @param  WP_Post $post     Post object.
	 * @return string            Filtered permalink.
	 */
	public static function post_type_link( $permalink, $post ) {
		if ( ! in_array( get_post_type( $post ), array( 'indieblocks_note', 'indieblocks_like' ), true ) ) {
			return $permalink;
		}

		return str_replace(
			array(
				'%year%',
				'%monthnum%',
				'%day%',
				'%postname%',
			),
			array(
				get_the_date( 'Y', $post->ID ),
				get_the_date( 'm', $post->ID ),
				get_the_date( 'd', $post->ID ),
				$post->post_name,
			),
			$permalink
		);
	}

	/**
	 * Returns a 404 when a date-based note or like archive is empty, much like
	 * WordPress does for normal posts.
	 */
	public static function set_404_if_empty() {
		if ( is_admin() ) {
			return;
		}

		if ( ! is_date() ) {
			return;
		}

		if ( ! is_post_type_archive( array( 'indieblocks_note', 'indieblocks_like' ) ) ) {
			return;
		}

		global $wp_query;

		if ( ! empty( $wp_query->posts ) ) {
			return;
		}

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Prevents clashes bewteen post URLs and date-based archives.
	 *
	 * @param  string $slug          Unique (for its post type) post slug.
	 * @param  int    $post_ID       Post ID.
	 * @param  string $post_status   Post status.
	 * @param  string $post_type     Post type.
	 * @param  int    $post_parent   Post parent.
	 * @param  string $original_slug Original slug.
	 * @return string                Unique slug that shouldn't cause problems with date archives.
	 */
	public static function prevent_slug_clashes( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
		// Note that although `$slug` is already guaranteed unique, it can still
		// clash with date archives.
		if ( ! in_array( $post_type, array( 'indieblocks_note', 'indieblocks_like' ), true ) ) {
			return $slug;
		}

		if ( ! preg_match( '~^\d+$~', $slug ) ) {
			// We're insterested only in slugs that are 100% digits.
			return $slug;
		}

		$options = get_options();

		if ( empty( $options['date_archives'] ) ) {
			// No date archives means no clashes.
			return $slug;
		}

		if ( empty( $options['permalink_format'] ) ) {
			$options['permalink_format'] = '/%postname%/';
		}

		// The following lines are lifted almost verbatim from core.
		$permastructs   = array_values( array_filter( explode( '/', $options['permalink_format'] ) ) );
		$postname_index = array_search( '%postname%', $permastructs, true );

		$slug_num = (int) $slug;

		if ( 0 === $postname_index ||
			( $postname_index && '%year%' === $permastructs[ $postname_index - 1 ] && 13 > $slug_num ) ||
			( $postname_index && '%monthnum%' === $permastructs[ $postname_index - 1 ] && 32 > $slug_num )
		) {
			global $wpdb;

			$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d LIMIT 1";
			$suffix    = 2; // There's no way `$slug` ends in a suffix already; we previously confirmed it is _numbers only_.

			do {
				// Ensure the "suffixed" slug is _also unique_.
				$alt_post_name = _truncate_post_slug( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_ID ) );
				++$suffix;
			} while ( $post_name_check );

			return $alt_post_name;
		}

		return $slug;
	}
}
