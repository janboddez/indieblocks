<?php
/**
 * Webmention/microformats parser.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Microformats parser.
 */
class Webmention_Parser {
	/**
	 * Updates comment (meta)data using microformats.
	 *
	 * @param array  $commentdata Comment (meta)data.
	 * @param string $html        HTML of the webmention source.
	 * @param string $source      Webmention source URL.
	 * @param string $target      Webmention target URL.
	 */
	public static function parse_microformats( &$commentdata, $html, $source, $target ) {
		// Parse source HTML.
		$mf = \IndieBlocks\Mf2\parse( $html, esc_url_raw( $source ) );

		if ( empty( $mf['items'][0]['type'][0] ) ) {
			// Nothing to see here.
			return;
		}

		if ( 'h-entry' === $mf['items'][0]['type'][0] ) {
			// Topmost item is an h-entry. Let's try to parse it.
			static::parse_hentry( $commentdata, $mf['items'][0], $source, $target );
			return;
		} elseif ( 'h-feed' === $mf['items'][0]['type'][0] ) {
			// Topmost item is an h-feed.
			if ( empty( $mf['items'][0]['children'] ) || ! is_array( $mf['items'][0]['children'] ) ) {
				// Nothing to see here.
				return;
			}

			// Loop through its children.
			foreach ( $mf['items'][0]['children'] as $child ) {
				if ( empty( $child['type'][0] ) ) {
					continue;
				}

				if ( static::parse_hentry( $commentdata, $child, $source, $target ) ) {
					// Found a valid h-entry; stop here.
					return;
				}
			}
		}
	}

	/**
	 * Updates comment (meta)data using h-entry properties.
	 *
	 * @param  array  $commentdata Comment (meta)data.
	 * @param  array  $hentry      Array describing an h-entry.
	 * @param  string $source      Source URL.
	 * @param  string $target      Target URL.
	 * @return bool                True on success, false on failure.
	 */
	public static function parse_hentry( &$commentdata, $hentry, $source, $target ) {
		// Update author name.
		if ( ! empty( $hentry['properties']['author'][0]['properties']['name'][0] ) ) {
			$commentdata['comment_author'] = $hentry['properties']['author'][0]['properties']['name'][0];
		}

		// Update author URL.
		if ( ! empty( $hentry['properties']['author'][0]['properties']['url'][0] ) ) {
			$commentdata['comment_author_url'] = $hentry['properties']['author'][0]['properties']['url'][0];
		}

		// Add author avatar. In a future version, we could choose to run this
		// through an image proxy of sorts, or cache a copy locally.
		if ( ! empty( $hentry['properties']['author'][0]['properties']['photo'][0] ) ) {
			$commentdata['comment_meta']['webmention_avatar'] = esc_url_raw( $hentry['properties']['author'][0]['properties']['photo'][0] );
		}

		// Update comment datetime.
		if ( ! empty( $hentry['properties']['published'][0] ) ) {
			$host = wp_parse_url( $source, PHP_URL_HOST );

			if ( false !== stripos( $host, 'brid-gy.appspot.com' ) ) {
				// Bridgy, we know, uses GMT.
				$commentdata['comment_date']     = get_date_from_gmt( date( 'Y-m-d H:i:s', strtotime( $hentry['properties']['published'][0] ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$commentdata['comment_date_gmt'] = date( 'Y-m-d H:i:s', strtotime( $hentry['properties']['published'][0] ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			} else {
				// Most WordPress sites do not.
				$commentdata['comment_date']     = date( 'Y-m-d H:i:s', strtotime( $hentry['properties']['published'][0] ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$commentdata['comment_date_gmt'] = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $hentry['properties']['published'][0] ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			}
		}

		// Update source URL.
		if ( ! empty( $hentry['properties']['url'][0] ) ) {
			$commentdata['comment_meta']['webmention_source'] = esc_url_raw( $hentry['properties']['url'][0] );
		}

		$hentry_kind = '';

		// @see https://github.com/aaronpk/Aperture/blob/c5abf1530f753691e35129e03bd5c7b319f738c9/aperture/app/Entry.php#L99.

		// We currently only support reposts, likes, replies, and bookmarks, and
		// regular "mentions." RSVPs, recipes, and whatnot are treated as mere
		// mentions.
		if ( ! empty( $hentry['properties']['content'][0]['html'] ) && false !== stripos( $hentry['properties']['content'][0]['html'], $target ) ) {
			$hentry_kind = 'mention';
		}

		if ( ! empty( $hentry['properties']['bookmark-of'] ) && in_array( $target, (array) $hentry['properties']['bookmark-of'], true ) ) {
			$hentry_kind = 'bookmark';
		}

		if ( ! empty( $hentry['properties']['bookmark-of'][0]['properties']['url'] ) && in_array( $target, (array) $hentry['properties']['bookmark-of'][0]['properties']['url'], true ) ) {
			$hentry_kind = 'bookmark';
		}

		if ( ! empty( $hentry['properties']['in-reply-to'] ) && in_array( $target, (array) $hentry['properties']['in-reply-to'], true ) ) {
			$hentry_kind = 'reply';
		}

		if ( ! empty( $hentry['properties']['in-reply-to'][0]['properties']['url'] ) && in_array( $target, (array) $hentry['properties']['in-reply-to'][0]['properties']['url'], true ) ) {
			$hentry_kind = 'reply';
		}

		if ( ! empty( $hentry['properties']['like-of'] ) && in_array( $target, (array) $hentry['properties']['like-of'], true ) ) {
			$hentry_kind = 'like';
		}

		if ( ! empty( $hentry['properties']['like-of'][0]['properties']['url'] ) && in_array( $target, (array) $hentry['properties']['like-of'][0]['properties']['url'], true ) ) {
			$hentry_kind = 'like';
		}

		if ( ! empty( $hentry['properties']['repost-of'] ) && in_array( $target, (array) $hentry['properties']['repost-of'], true ) ) {
			$hentry_kind = 'repost';
		}

		if ( ! empty( $hentry['properties']['repost-of'][0]['properties']['url'] ) && in_array( $target, (array) $hentry['properties']['repost-of'][0]['properties']['url'], true ) ) {
			$hentry_kind = 'repost';
		}

		// Update h-entry kind (or type).
		if ( ! empty( $hentry_kind ) ) {
			$commentdata['comment_meta']['webmention_kind'] = $hentry_kind;
		}

		// Update comment content.
		$comment_content = $commentdata['comment_content'];

		switch ( $hentry_kind ) {
			case 'bookmark':
				$comment_content = __( '&hellip; bookmarked this!', 'indieblocks' );
				break;

			case 'like':
				$comment_content = __( '&hellip; liked this!', 'indieblocks' );
				break;

			case 'repost':
				$comment_content = __( '&hellip; reposted this!', 'indieblocks' );
				break;

			case 'mention':
			case 'reply':
			default:
				if ( ! empty( $hentry['properties']['content'][0]['html'] )
					&& mb_strlen( wp_strip_all_tags( $hentry['properties']['content'][0]['html'] ), 'UTF-8' ) <= 500 ) {
					// If the mention is short enough, simply show it in its entirety.
					$comment_content = wp_strip_all_tags( $hentry['properties']['content'][0]['html'] );
				} else {
					// Fetch the bit of text surrounding the link to our page.
					$context = static::fetch_context( $hentry['properties']['content'][0]['html'], $target );

					if ( '' !== $context ) {
						$comment_content = $context;
					} elseif ( ! empty( $hentry['properties']['content'][0]['html'] ) ) {
						// Simply show an excerpt of the webmention source.
						$comment_content = wp_trim_words(
							wp_strip_all_tags( $hentry['properties']['content'][0]['html'] ),
							25,
							' [&hellip;]'
						);
					}
				}
		}

		$commentdata['comment_content'] = apply_filters(
			'indieblocks_webmention_comment',
			$comment_content,
			$hentry,
			$source,
			$target
		);

		// Well, we've replaced whatever comment data we could find.
		return true;
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
}
