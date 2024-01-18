<?php
/**
 * Parse (certain) outgoing links.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Attempts to parse (certain) outgoing links.
 */
class Parser {
	/**
	 * Page URL.
	 *
	 * @var string $url URL of the page we're parsing.
	 */
	protected $url;

	/**
	 * Page DOM.
	 *
	 * @var \DOMDocument $dom DOM of the page we're parsing.
	 */
	protected $dom;

	/**
	 * Microformats2.
	 *
	 * @var array $mf2 Microformats2 representation of the page we're parsing.
	 */
	protected $mf2;

	/**
	 * Constructor.
	 *
	 * @param string|null $url URL of the page we'll be parsing.
	 */
	public function __construct( $url = null ) {
		$this->url = $url;
		$this->dom = new \DOMDocument();
	}

	/**
	 * Fetches the page, then loads its DOM. Then loads its mf2.
	 *
	 * @param string $content (Optional) HTML to be parsed instead.
	 */
	public function parse( $content = '' ) {
		// Create a "key," for caching.
		if ( ! empty( $this->url ) ) {
			$hash = hash( 'sha256', esc_url_raw( $this->url ) );
		} elseif ( ! empty( $content ) ) {
			$hash = hash( 'sha256', $content );
		}

		if ( empty( $content ) && ! empty( $this->url ) ) {
			// No `$content` was passed along, but a URL was.
			$content = get_transient( 'indieblocks:html:' . $hash );

			if ( false === $content ) {
				// Could not find a cached version. Download page.
				$response = remote_get( $this->url );
				$content  = '';

				$code = wp_remote_retrieve_response_code( $response );

				if ( is_wp_error( $response ) ) {
					// The remote server returned a (client or server) error.
					debug_log( '[IndieBlocks] The server at ' . esc_url_raw( $this->url ) . ' responded with the following error: ' . $response->get_error_message() . '.' );
				} elseif ( '' === $code || $code >= 400 ) {
					// The remote server returned a (client or server) error.
					debug_log( '[IndieBlocks] The server at ' . esc_url_raw( $this->url ) . " responded with the following HTTP status code: $code." );
				} else {
					$content = wp_remote_retrieve_body( $response );
				}
				set_transient( 'indieblocks:html:' . $hash, $content, 3600 ); // Cache, even if empty.
			} else {
				debug_log( '[IndieBlocks] Found HTML for ' . esc_url_raw( $this->url ) . ' in cache.' );
			}
		}

		if ( empty( $content ) ) {
			// We need HTML to be able to load the DOM.
			return;
		}

		// Load DOM.
		$content = convert_encoding( $content );
		libxml_use_internal_errors( true );
		$this->dom->loadHTML( $content );

		// Attempt to also load mf2.
		$mf2 = get_transient( 'indieblocks:mf2:' . $hash );

		if ( empty( $mf2 ) ) {
			$fragment = wp_parse_url( $this->url, PHP_URL_FRAGMENT );
			if ( ! empty( $fragment ) ) {
				// If the URL contains a fragment, parse only the corresponding
				// page section.
				// @todo: Do this for `$this->dom`, too?
				$xpath = new \DOMXPath( $this->dom );

				foreach ( $xpath->query( "//*[@id='$fragment']" ) as $el ) {
					$content = $this->dom->saveHTML( $el );
					break;
				}
			}

			$mf2 = Mf2\parse( $content, $this->url );
			set_transient( 'indieblocks:mf2:' . $hash, $mf2, 3600 );
		}

		$this->mf2 = $mf2;
	}

	/**
	 * Returns page's (source) URL.
	 *
	 * @return string Page URL.
	 */
	public function get_url() {
		if ( ! empty( $this->mf2['items'][0]['properties'] ) ) {
			$props = $this->mf2['items'][0]['properties'];

			if ( ! empty( $props['url'][0] ) && wp_http_validate_url( $props['url'][0] ) ) {
				return esc_url_raw( $props['url'][0] );
			}
		}

		if ( ! empty( $this->url ) ) {
			return $this->url;
		}

		return '';
	}

	/**
	 * Returns the page's name.
	 *
	 * @param  bool $mf2 Whether to consider microformats. Set to `false` so skip microformats parsing.
	 * @return string    Current page's name or title.
	 */
	public function get_name( $mf2 = true ) {
		if (
			$mf2 &&
			! empty( $this->mf2['items'][0]['type'] ) && array_intersect( (array) $this->mf2['items'][0]['type'], array( 'h-entry', 'h-cite', 'h-recipe', 'h-review' ) ) &&
			! empty( $this->mf2['items'][0]['properties'] )
		) {
			// Microformats.
			$props = $this->mf2['items'][0]['properties'];
			$name  = ! empty( $props['name'][0] ) && is_string( $props['name'][0] )
				? sanitize_text_field( $props['name'][0] ) // Also strips tags and line breaks.
				: '';

			if ( '' === $name ) {
				// Could be a note.
				return '';
			}

			// Need some form of content to compare `$name` to.
			$content = '';
			if ( ! empty( $props['content'][0]['value'] ) ) {
				$content = $props['content'][0]['value'];
			} elseif ( ! empty( $post['summary'][0] ) ) {
				$content = $props['summary'][0];
			}

			$content = preg_replace( '~\s+~', ' ', strtolower( sanitize_textarea_field( $content ) ) ); // Also strips tags.
			$check   = preg_replace( '~\s+~', ' ', strtolower( sanitize_textarea_field( $name ) ) );

			if ( $name === $content ) {
				// We can stop here.
				return '';
			}

			if ( '...' === substr( $check, -3 ) ) {
				$check = substr( $check, 0, -3 );
			} elseif ( '…' === substr( $check, -1 ) ) {
				$check = substr( $check, 0, -1 );
			}

			if ( 0 === strpos( $content, $check ) ) {
				// `$name` looks like a (possibly truncated) copy of `$content`.
				if ( preg_replace( '~\s+~', ' ', strtolower( $name ) ) !== $content && str_word_count( $content ) > 4 ) { // Good enough.
					// If content is long-ish and name not an _identical_ copy,
					// it could just be that this really is an article that
					// starts off with its title.
					return $name;
				}

				return ''; // Probably a note after all.
			}

			return $name;
		}

		// No microformats.
		$meta = $this->dom->getElementsByTagName( 'meta' );
		foreach ( $meta as $el ) {
			// @codingStandardsIgnoreLine
			if ( 'og:title' === $el->getAttribute( 'name' ) || 'twitter:title' === $el->getAttribute( 'name' ) ) {
				return sanitize_text_field( $el->getAttribute( 'content' ) ); // @codingStandardsIgnoreLine
			}
		}

		// Fallback to `title`.
		$title = $this->dom->getElementsByTagName( 'title' );
		foreach ( $title as $el ) {
			return sanitize_text_field( $el->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		return '';
	}

	/**
	 * Returns the page's author.
	 *
	 * @return string Page author.
	 */
	public function get_author() {
		if ( ! empty( $this->mf2['items'][0]['properties'] ) ) {
			// Microformats.
			$properties = $this->mf2['items'][0]['properties'];

			if ( ! empty( $properties['author'][0] ) && is_string( $properties['author'][0] ) ) {
				return $properties['author'][0];
			}

			if ( ! empty( $properties['author'][0]['properties']['name'][0] ) ) {
				return $properties['author'][0]['properties']['name'][0];
			}
		}

		// No microformats.
		$meta = $this->dom->getElementsByTagName( 'meta' );
		foreach ( $meta as $el ) {
			// @codingStandardsIgnoreLine
			if ( 'author' === $el->getAttribute( 'name' ) )	 {
				// @codingStandardsIgnoreLine
				return sanitize_text_field( $el->getAttribute( 'content' ) ); // Returns an empty string if the `content` attribute does not exist.
			}
		}

		return '';
	}

	/**
	 * Returns the author URL, if it can find one.
	 *
	 * Not currently in use.
	 *
	 * @return string Author URL.
	 */
	public function get_author_url() {
		if ( ! empty( $this->mf2['items'][0]['properties'] ) ) {
			$props = $this->mf2['items'][0]['properties'];

			if ( ! empty( $props['author'][0]['properties']['url'][0] ) && filter_var( $props['author'][0]['properties']['url'][0], FILTER_VALIDATE_URL ) ) {
				return $props['author'][0]['properties']['url'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the author's avatar, if we can find one.
	 *
	 * @return string Avatar URL.
	 */
	public function get_avatar() {
		if ( empty( $this->mf2['items'][0]['properties'] ) ) {
			return '';
		}

		$props = $this->mf2['items'][0]['properties'];

		if ( ! empty( $props['author'][0]['properties']['photo'][0] ) && filter_var( $props['author'][0]['properties']['photo'][0], FILTER_VALIDATE_URL ) ) {
			return $props['author'][0]['properties']['photo'][0];
		}

		if ( ! empty( $props['author'][0]['properties']['photo'][0]['value'] ) && filter_var( $props['author'][0]['properties']['photo'][0]['value'], FILTER_VALIDATE_URL ) ) {
			return $props['author'][0]['properties']['photo'][0]['value'];
		}

		return '';
	}

	/**
	 * Returns a "page thumbnail," if we can find one.
	 *
	 * @return string Image URL.
	 */
	public function get_image() {
		$meta = $this->dom->getElementsByTagName( 'meta' );
		foreach ( $meta as $el ) {
			if ( 'og:image' === $el->getAttribute( 'property' ) || 'twitter:image' === $el->getAttribute( 'property' ) ) {
				$url = $el->getAttribute( 'content' );
				return wp_http_validate_url( $url ) ? esc_url_raw( $url ) : '';
			}
		}

		return '';
	}

	/**
	 * Returns the published date.
	 *
	 * @return string (UTC) datetime string in `Y-m-d H:i:s` format.
	 */
	public function get_published() {
		if ( ! empty( $this->mf2['items'][0]['properties']['published'][0] ) ) {
			return gmdate( 'Y-m-d H:i:s', strtotime( $this->mf2['items'][0]['properties']['published'][0] ) );
		}

		return '';
	}

	/**
	 * Returns page's ("inner") content.
	 *
	 * @return string Content.
	 */
	public function get_content() {
		if ( ! empty( $this->mf2['items'][0]['properties']['content'][0]['html'] ) ) {
			return $this->mf2['items'][0]['properties']['content'][0]['html'];
		} elseif ( ! empty( $this->mf2['items'][0]['properties']['content'][0]['text'] ) ) {
			return $this->mf2['items'][0]['properties']['content'][0]['text'];
		} elseif ( ! empty( $this->mf2['items'][0]['properties']['content'][0] && is_string( $this->mf2['items'][0]['properties']['content'][0] ) ) ) {
			return $this->mf2['items'][0]['properties']['content'][0];
		}

		return '';
	}

	/**
	 * Returns the URL, i.e., `href` value, of the current page's first "like,"
	 * "bookmark," or "repost" link.
	 *
	 * @param  bool $mf2 Whether to consider only microformatted links (or only _other_ links).
	 * @return string    Link URL.
	 */
	public function get_link_url( $mf2 = true ) {
		if ( $mf2 && ! empty( $this->mf2['items'][0]['properties'] ) ) {
			$props = $this->mf2['items'][0]['properties'];

			if ( ! empty( $props['repost-of'][0] ) && filter_var( $props['repost-of'][0], FILTER_VALIDATE_URL ) ) {
				return $props['repost-of'][0];
			}

			if ( ! empty( $props['repost-of'][0]['value'] ) && filter_var( $props['repost-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $props['repost-of'][0]['value'];
			}

			if ( ! empty( $props['like-of'][0] ) && filter_var( $props['like-of'][0], FILTER_VALIDATE_URL ) ) {
				return $props['like-of'][0];
			}

			if ( ! empty( $props['like-of'][0]['value'] ) && filter_var( $props['like-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $props['like-of'][0]['value'];
			}

			if ( ! empty( $props['bookmark-of'][0] ) && filter_var( $props['bookmark-of'][0], FILTER_VALIDATE_URL ) ) {
				return $props['bookmark-of'][0];
			}

			if ( ! empty( $props['bookmark-of'][0]['value'] ) && filter_var( $props['bookmark-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $props['bookmark-of'][0]['value'];
			}
		} else {
			$links = $this->dom->getElementsByTagName( 'a' );
			foreach ( $links as $link ) {
				// @codingStandardsIgnoreLine
				if ( ! $link->hasAttribute( 'href' ) ) {
					continue;
				}

				return esc_url_raw( $link->getAttribute( 'href' ) );
			}
		}

		return '';
	}

	/**
	 * Returns the name (i.e., the text content) of the current page's first
	 * "like," "bookmark," or "repost" link.
	 *
	 * @return string Link name.
	 */
	public function get_link_name() {
		if ( ! empty( $this->mf2['items'][0]['properties'] ) ) {
			$props = $this->mf2['items'][0]['properties'];

			if ( ! empty( $props['repost-of'][0]['properties']['name'][0] ) && is_string( $props['repost-of'][0]['properties']['name'][0] ) ) {
				return $props['repost-of'][0]['properties']['name'][0];
			}

			if ( ! empty( $props['like-of'][0]['properties']['name'][0] ) && is_string( $props['like-of'][0]['properties']['name'][0] ) ) {
				return $props['like-of'][0]['properties']['name'][0];
			}

			if ( ! empty( $props['bookmark-of'][0]['properties']['name'][0] ) && is_string( $props['bookmark-of'][0]['properties']['name'][0] ) ) {
				return $props['bookmark-of'][0]['properties']['name'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the current page's "IndieWeb post type."
	 *
	 * @return string Post type.
	 */
	public function get_type() {
		if ( empty( $this->mf2['items'][0] ) ) {
			return '';
		}

		$post = $this->mf2['items'][0];

		if ( ! empty( $post['type'][0] ) && 'h-feed' === $post['type'][0] ) {
			return 'feed';
		}

		if ( ! empty( $post['type'][0] ) && in_array( $post['type'][0], array( 'h-event', 'h-recipe', 'h-review' ), true ) ) {
			return preg_replace( '~^h-~', '', $post['type'] );
		}

		$props = $post['properties'];

		if ( ! empty( $props['repost-of'] ) ) {
			return 'repost';
		}

		if ( ! empty( $props['in-reply-to'] ) ) {
			return 'reply';
		}

		if ( ! empty( $props['like-of'] ) ) {
			return 'like';
		}

		if ( ! empty( $props['bookmark-of'] ) ) {
			return 'bookmark';
		}

		if ( ! empty( $props['follow-of'] ) ) {
			return 'follow';
		}

		if ( ! empty( $props['checkin'] ) ) {
			return 'checkin';
		}

		if ( ! empty( $props['video'] ) ) {
			return 'video';
		}

		if ( ! empty( $props['video'] ) ) {
			return 'audio';
		}

		if ( ! empty( $props['photo'] ) ) {
			return 'photo';
		}

		if ( '' === $this->get_name() ) {
			return 'note';
		}

		return 'article';
	}

	/**
	 * Returns the current page's parsed microformats array.
	 *
	 * @return array Mf2 array.
	 */
	public function get_mf2() {
		return $this->mf2;
	}
}
