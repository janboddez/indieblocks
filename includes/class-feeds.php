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
		$plugin  = IndieBlocks::get_instance();
		$options = $plugin->get_options_handler()->get_options();

		if ( ! empty( $options['modified_feeds'] ) ) {
			// Include microblog entries in the site's main feed (but only if we use
			// a "permalink front").
			add_filter( 'request', array( __CLASS__, 'include_in_main_feed' ), 9 );

			// Create a new, post-only feed (but only if we use a "permalink
			// front").
			add_filter( 'init', array( __CLASS__, 'create_post_feed' ) );

			// And adapt that feed's title.
			add_filter( 'wp_title_rss', array( __CLASS__, 'set_post_feed_title' ) );

			// @todo: Move these behind a different setting. Users might not want to drop Atom, or JSON Feed, support.

			// Disable all but RSS feeds. This way, we only have to worry about
			// one format anymore.
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
			// add_action( 'template_redirect', array( __CLASS__, 'disable_non_rss_feeds' ) );

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
	 * Disables feeds, except RSS2, for which we're using a modified template.
	 */
	public static function disable_non_rss_feeds() {
		if ( is_feed() && ! is_feed( 'rss2' ) ) {
			global $wp_query;

			$wp_query->set_404();
			status_header( 404 );

			// Note: the above isn't enough if we want to display a (HTML) 404
			// page, too.
			header( 'Content-Type: text/html' );
			locate_template( '404.php', true, true ); // @todo: This may not work for block themes!
			exit;
		}
	}

	/**
	 * Hides note titles from RSS feeds.
	 */
	public static function load_custom_rss2_template() {
		require_once dirname( dirname( __FILE__ ) ) . '/templates/feed-rss2.php';
	}

	/**
	 * Hides note titles from Atom feeds.
	 */
	public static function load_custom_atom_template() {
		require_once dirname( dirname( __FILE__ ) ) . '/templates/feed-atom.php';
	}

	/**
	 * Includes microblog posts in the site's main RSS feed.
	 *
	 * @param array $query_vars The array of requested query variables.
	 */
	public static function include_in_main_feed( $query_vars ) {
		if ( '' === static::get_front() ) {
			// @todo: Replace this by an actual setting.
			return $query_vars;
		}

		// Target only the main feed.
		if ( isset( $query_vars['feed'] ) && ! isset( $query_vars['post_type'] ) ) {
			$query_vars['post_type'] = array( 'post', 'indieblocks_note' );
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

		add_rewrite_rule( $front . '/feed/?$', 'index.php?post_type=post&feed=rss2', 'top' );
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
	 * Prepends Featured Images to RSS items.
	 *
	 * @param  string $content The post content.
	 * @return string          Modified content.
	 */
	public static function feed_thumbnails( $content ) {
		global $post;

		if ( has_post_thumbnail( $post->ID ) ) {
			$content = '<p>' . get_the_post_thumbnail( $post->ID ) . '</p>' . PHP_EOL . $content;
		}

		return wpautop( $content );
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
