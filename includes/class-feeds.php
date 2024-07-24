<?php
/**
 * Web feed tweaks.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Modifies (RSS) feeds.
 */
class Feeds {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		$options = get_options();

		// Include microblog entries in the site's main feed (but only if the
		// setting is enabled for either CPT).
		add_filter( 'request', array( __CLASS__, 'include_in_main_feed' ), 9 );

		// Create a new, post-only feed (but only if a "permalink front" is
		// in use).
		add_filter( 'init', array( __CLASS__, 'create_post_feed' ) );

		if ( ! empty( $options['modified_feeds'] ) ) {
			// Using custom RSS and Atom templates, prevent _note_ titles from
			// being displayed in these feeds. Will cause conflicts with
			// existing custom feed templates.
			remove_all_actions( 'do_feed_rss2' );
			remove_all_actions( 'do_feed_atom' );
			add_action( 'do_feed_rss2', array( __CLASS__, 'load_custom_rss2_template' ), 20 );
			add_action( 'do_feed_atom', array( __CLASS__, 'load_custom_atom_template' ), 20 );
		}

		if ( ! empty( $options['add_featured_images'] ) ) {
			// Prepend Featured Images to feed items.
			add_filter( 'the_excerpt_rss', array( __CLASS__, 'feed_thumbnails' ) );
			add_filter( 'the_content_feed', array( __CLASS__, 'feed_thumbnails' ) );
		}
	}

	/**
	 * Hides note titles from RSS feeds.
	 */
	public static function load_custom_rss2_template() {
		require_once dirname( __DIR__ ) . '/templates/feed-rss2.php';
	}

	/**
	 * Hides note titles from Atom feeds.
	 */
	public static function load_custom_atom_template() {
		require_once dirname( __DIR__ ) . '/templates/feed-atom.php';
	}

	/**
	 * Includes microblog posts in the site's main RSS feed.
	 *
	 * @param array $query_vars The array of requested query variables.
	 */
	public static function include_in_main_feed( $query_vars ) {
		$options = get_options();

		if ( empty( $options['notes_in_feed'] ) && empty( $options['likes_in_feed'] ) ) {
			// Do nothing.
			return $query_vars;
		}

		// Target only the main feed.
		if ( isset( $query_vars['feed'] ) && ! isset( $query_vars['post_type'] ) ) {
			/* @link https://github.com/pfefferle/wordpress-rss-club/issues/1 */
			$query_vars['post_type'] = array( 'post', 'rssclub' );

			if ( ! empty( $options['notes_in_feed'] ) ) {
				$query_vars['post_type'][] = 'indieblocks_note';
			}

			if ( ! empty( $options['likes_in_feed'] ) ) {
				$query_vars['post_type'][] = 'indieblocks_like';
			}
		}

		return $query_vars;
	}

	/**
	 * Defines a new feed URL for just articles (i.e., WordPress's default
	 * posts).
	 */
	public static function create_post_feed() {
		$front = static::get_front();

		if ( empty( $front ) ) {
			// Do nothing.
			return;
		}

		add_rewrite_rule( "^$front/feed/?$", 'index.php?post_type=post&feed=rss2', 'top' );
		add_rewrite_rule( "^$front/feed/atom/?$", 'index.php?post_type=post&feed=atom', 'top' );

		// Set the new feed's title.
		add_filter( 'wp_title_rss', array( __CLASS__, 'set_post_feed_title' ) );

		// Set the new feed's title.
		add_action( 'wp_head', array( __CLASS__, 'add_post_feed_link' ), 9 );
	}

	/**
	 * Modifies the post (i.e., "article") feed's title.
	 *
	 * @param  string $title Feed title.
	 * @return string        Modified feed title.
	 */
	public static function set_post_feed_title( $title ) {
		$front = static::get_front();

		if ( empty( $front ) ) {
			// Do nothing.
			return;
		}

		global $wp;

		if ( isset( $wp->request ) && 0 === strpos( $wp->request, $front . '/feed' ) ) {
			/* translators: %s: site title */
			$title = sprintf( __( 'Posts &#8211; %s', 'indieblocks' ), get_bloginfo( 'title' ) );
			$title = apply_filters( 'indieblocks_post_feed_title', $title );
		}

		return $title;
	}

	/**
	 * Adds a `link`, in `head`, to the newly created post feed.
	 */
	public static function add_post_feed_link() {
		$front = static::get_front();

		if ( empty( $front ) ) {
			// Do nothing.
			return;
		}

		/* translators: %s: site title */
		$title = sprintf( __( 'Posts &#8211; %s', 'indieblocks' ), get_bloginfo( 'title' ) );
		$title = apply_filters( 'indieblocks_post_feed_title', $title );

		$feed_url = home_url( "$front/feed/" );

		$permalink_structure = get_option( 'permalink_structure' );
		if ( is_string( $permalink_structure ) && '/' !== substr( $permalink_structure, -1 ) ) {
			// If permalinks were set up without trailing slash, hide it.
			$feed_url = substr( $feed_url, 0, -1 );
		}

		echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr( $title ) . '" href="' . esc_url( $feed_url ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Prepends Featured Images to RSS items.
	 *
	 * @param  string $content The post content.
	 * @return string          Modified content.
	 */
	public static function feed_thumbnails( $content ) {
		global $post;

		if ( ! empty( $post->ID ) && has_post_thumbnail( $post->ID ) ) {
			$content = '<p>' . get_the_post_thumbnail( $post->ID ) . '</p>' . PHP_EOL . $content;
		}

		return $content;
	}

	/**
	 * Returns the permalink structure's "front," if any.
	 *
	 * @return string The permalink front, without slashes, or an empty string.
	 */
	public static function get_front() {
		global $wp_rewrite;

		return isset( $wp_rewrite->front ) ? trim( $wp_rewrite->front, '/' ) : '';
	}
}
