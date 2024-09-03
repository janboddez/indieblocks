<?php

namespace IndieBlocks\Webmention;

use IndieBlocks\Parser;

use function IndieBlocks\get_options;
use function IndieBlocks\store_image;

/**
 * Parses a webmention's source page for microformats.
 */
class Webmention_Parser {
	/**
	 * Updates comment (meta)data using microformats.
	 *
	 * @param  array  $commentdata Comment (meta)data.
	 * @param  string $html        HTML of the webmention source.
	 * @param  string $source      Webmention source URL.
	 * @param  string $target      Webmention target URL.
	 * @return void
	 */
	public static function parse_microformats( &$commentdata, $html, $source, $target ) {
		if ( preg_match( '~/\?c=\d+$~', $source ) ) {
			// Source looks like a comment shortlink. Let's try to get the full
			// URL.
			$response = wp_safe_remote_head(
				esc_url_raw( $source ),
				array(
					'limit_response_size' => 1048576,
					'timeout'             => 11,
					'user-agent'          => get_user_agent(),
				)
			);

			$url = ( (array) wp_remote_retrieve_header( $response, 'location' ) )[0];
			if ( ! empty( $url ) && false !== filter_var( $url, FILTER_VALIDATE_URL ) ) {
				// If we were forwarded, use the (first) destination URL. If the
				// source URL was for a WordPress comment, it _should_ forward
				// to a URL that ends in a "comment fragment" instead.
				$source = $url;
			}
		}

		$parser = new Parser( $source );
		$parser->parse( $html );

		$kind = $parser->get_type();
		if ( empty( $kind ) || 'feed' === $kind ) {
			// Source doesn't appear to be a single entry. Still, we can try to
			// set a (better) comment content.
			$comment_content = $commentdata['comment_content'];

			$content = $parser->get_content();
			$context = static::fetch_context( $content, $target );

			if ( '' !== $context ) {
				$comment_content = $context; // Already sanitized (and escaped).
			} elseif ( ! empty( $content ) ) {
				// Simply show an excerpt.
				$comment_content = wp_trim_words( $content, 25, ' [&hellip;]' );
			}

			$commentdata['comment_content'] = apply_filters( 'indieblocks_webmention_comment', $comment_content, $source, $target );

			return;
		}

		static::parse_hentry( $commentdata, $parser, $source, $target );
	}

	/**
	 * Updates comment (meta)data using h-entry properties.
	 *
	 * @param array  $commentdata Comment (meta)data.
	 * @param Parser $parser      Parser instance.
	 * @param string $source      Source URL.
	 * @param string $target      Target URL.
	 */
	public static function parse_hentry( &$commentdata, $parser, $source, $target ) {
		$author = $parser->get_author();
		if ( ! empty( $author ) ) {
			$commentdata['comment_author'] = $author;
		}

		$author_url = $parser->get_author_url();
		if ( ! empty( $author_url ) ) {
			$commentdata['comment_author_url'] = $author_url;
		}

		$avatar_url = $parser->get_avatar();
		if ( ! empty( $avatar_url ) ) {
			$avatar = static::store_avatar( $avatar_url, $author_url );
			if ( ! empty( $avatar ) ) {
				$commentdata['comment_meta']['indieblocks_webmention_avatar'] = $avatar;
			}
		}

		$published = $parser->get_published();
		if ( ! empty( $published ) ) {
			$commentdata['comment_date']     = get_date_from_gmt( $published, 'Y-m-d H:i:s' );
			$commentdata['comment_date_gmt'] = date( 'Y-m-d H:i:s', strtotime( $published ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		}

		// Overwrite the source URL only for mentions sent by Bridgy.
		$host = wp_parse_url( $source, PHP_URL_HOST );
		if ( false !== stripos( $host, 'brid.gy' ) ) {
			$commentdata['comment_meta']['indieblocks_webmention_source'] = esc_url_raw( $parser->get_url() ?: $source ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		}

		$hentry_kind = $parser->get_type();
		if ( ! empty( $hentry_kind ) ) {
			$commentdata['comment_meta']['indieblocks_webmention_kind'] = $hentry_kind;
		}

		// Update comment content.
		$comment_content = $commentdata['comment_content'];

		switch ( $hentry_kind ) {
			case 'bookmark':
				$comment_content = __( '&hellip; bookmarked this!', 'indieblocks' );
				break;

			case 'favorite':
			case 'like':
				$comment_content = __( '&hellip; liked this!', 'indieblocks' );
				break;

			case 'repost':
				$comment_content = __( '&hellip; reposted this!', 'indieblocks' );
				break;

			case 'read':
				$comment_content = __( '&hellip; (wants to) read this!', 'indieblocks' );
				break;

			case 'mention':
			case 'reply':
			default:
				$content = $parser->get_content();

				if ( ! empty( $content ) && mb_strlen( sanitize_text_field( $content ) ) <= 500 ) {
					// If `$content` is short enough, simply store it in its entirety.
					$comment_content = wp_kses( $content, 'pre_comment_content' ); // WordPress will still sanitize this, but ...
				} else {
					// Fetch the bit of text surrounding the link to our page.
					$context = static::fetch_context( $content, $target );

					if ( '' !== $context ) {
						$comment_content = $context; // Already sanitized (and escaped).
					} elseif ( ! empty( $content ) ) {
						// Simply show an excerpt.
						$comment_content = wp_trim_words( $content, 25, ' [&hellip;]' );
					}
				}
		}

		$commentdata['comment_content'] = apply_filters( 'indieblocks_webmention_comment', $comment_content, $source, $target );
	}

	/**
	 * Returns the text surrounding a (back)link. Very heavily inspired by
	 * WordPress core.
	 *
	 * @link https://github.com/WordPress/WordPress/blob/1dcf3eef7a191bd0a6cd21d4382b8b5c5a25c886/wp-includes/class-wp-xmlrpc-server.php#L6929
	 *
	 * @param  string $html   The remote page's source.
	 * @param  string $target The target URL.
	 * @return string         The excerpt, or an empty string if the target isn't found.
	 */
	public static function fetch_context( $html, $target ) {
		// Work around bug in `strip_tags()`.
		$html = str_replace( '<!DOC', '<DOC', $html );
		$html = preg_replace( '/[\r\n\t ]+/', ' ', $html );
		$html = preg_replace( '/<\/*(h1|h2|h3|h4|h5|h6|p|th|td|li|dt|dd|pre|caption|input|textarea|button|body)[^>]*>/', "\n\n", $html );

		// Remove all script and style tags, including their content.
		$html = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $html );
		// Just keep the tag we need.
		$html = strip_tags( $html, '<a>' );

		$p = explode( "\n\n", $html );

		$preg_target = preg_quote( $target, '|' );

		foreach ( $p as $para ) {
			if ( strpos( $para, $target ) !== false ) {
				preg_match( '|<a[^>]+?' . $preg_target . '[^>]*>([^>]+?)</a>|', $para, $context );

				if ( empty( $context ) ) {
					// The URL isn't in a link context; keep looking.
					continue;
				}

				// We're going to use this fake tag to mark the context in a
				// bit. The marker is needed in case the link text appears more
				// than once in the paragraph.
				$excerpt = preg_replace( '|\</?wpcontext\>|', '', $para );

				// Prevent really long link text.
				if ( strlen( $context[1] ) > 100 ) {
					$context[1] = substr( $context[1], 0, 100 ) . '&#8230;';
				}

				$marker      = '<wpcontext>' . $context[1] . '</wpcontext>';  // Set up our marker.
				$excerpt     = str_replace( $context[0], $marker, $excerpt ); // Swap out the link for our marker.
				$excerpt     = strip_tags( $excerpt, '<wpcontext>' );         // Strip all tags but our context marker.
				$excerpt     = trim( $excerpt );
				$preg_marker = preg_quote( $marker, '|' );
				$excerpt     = preg_replace( "|.*?\s(.{0,200}$preg_marker.{0,200})\s.*|s", '$1', $excerpt );
				$excerpt     = strip_tags( $excerpt );                        // phpcs:ignore

				break;
			}
		}

		if ( empty( $excerpt ) ) {
			// Link to target not found.
			return '';
		}

		return '[&#8230;] ' . esc_html( $excerpt ) . ' [&#8230;]';
	}

	/**
	 * Caches avatars locally.
	 *
	 * @param  string $avatar_url Avatar URL.
	 * @param  string $author_url Avatar URL.
	 * @return string|null        Local avatar path, or nothing on failure.
	 */
	protected static function store_avatar( $avatar_url, $author_url = '' ) {
		$options = get_options();
		if ( empty( $options['cache_avatars'] ) ) {
			return null;
		}

		if ( ! empty( $author_url ) ) {
			$hash = hash( 'sha256', esc_url_raw( $author_url ) );
		} else {
			$hash = hash( 'sha256', esc_url_raw( $avatar_url ) );
		}

		$dir  = 'indieblocks-avatars/';
		$dir .= substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 );

		$ext      = pathinfo( $avatar_url, PATHINFO_EXTENSION );
		$filename = $hash . ( ! empty( $ext ) ? '.' . $ext : '' );

		return store_image( $avatar_url, $filename, $dir );
	}
}
