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
	private $url;

	/**
	 * Page DOM.
	 *
	 * @var \DOMDocument $dom DOM of the page we're parsing.
	 */
	private $dom;

	/**
	 * Microformats2.
	 *
	 * @var array $mf2 Microformats2 representation of the page we're parsing.
	 */
	private $mf2;

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
	 * Fetches the page, then loads its DOM.
	 *
	 * @param string $content (Optional) HTML to be parsed instead.
	 */
	public function parse( $content = '' ) {
		if ( ! empty( $this->url ) ) {
			$hash = hash( 'sha256', esc_url_raw( $this->url ) );
		} elseif ( ! empty( $content ) ) {
			$hash = hash( 'sha256', $content );
		}

		if ( ! empty( $hash ) ) {
			// Check for existing data.
			$mf2 = get_transient( 'indieblocks:mf2:' . $hash );
		}

		if ( ! empty( $mf2 ) ) {
			$this->mf2 = $mf2;
			return;
		}

		if ( empty( $content ) && ! empty( $this->url ) ) {
			// No `$content` was passed along. First check the cache.
			$content = get_transient( 'indieblocks:html:' . $hash );

			if ( empty( $content ) ) {
				// Download the page, and store the result.
				$response = remote_get( $this->url );
				$content  = wp_remote_retrieve_body( $response );
				set_transient( 'indieblocks:html:' . $hash, $content, 3600 ); // Cache, even if empty.
			}
		}

		if ( empty( $content ) ) {
			// Can't continue without HTML.
			return;
		}

		// Parse for microformats, and store the outcome.
		$mf2 = Mf2\parse( $content, $this->url );
		set_transient( 'indieblocks:mf2:' . $hash, $mf2, 3600 ); // Cache, even if empty or faulty.

		$this->mf2 = $mf2;
	}

	/**
	 * Returns a page name.
	 *
	 * @return string Current page's name or title.
	 */
	public function get_name() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['name'][0] ) ) {
				return $post['properties']['name'][0];
			}

			return ''; // If this thing supports microformats but does not have a name, assume a note.
		}

		$title = $this->dom->getElementsByTagName( 'title' );

		if ( isset( $title->length ) && $title->length > 0 ) {
			return trim( $title->item( 0 )->textContent );
		}

		return '';
	}

	/**
	 * Returns the author, if it can find one.
	 *
	 * @return string Page author.
	 */
	public function get_author() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['author'][0] ) && is_string( $post['properties']['author'][0] ) ) {
				return $post['properties']['author'][0];
			}

			if ( ! empty( $post['properties']['author'][0]['properties']['name'][0] ) ) {
				return $post['properties']['author'][0]['properties']['name'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the author URL, if it can find one.
	 *
	 * @return string Author URL.
	 */
	public function get_author_url() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['author'][0]['properties']['url'][0] ) ) {
				return $post['properties']['author'][0]['properties']['url'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the author's avatar, we can find one.
	 *
	 * @return string Avatar URL.
	 */
	public function get_avatar() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['author'][0]['properties']['photo'][0] ) ) {
				return $post['properties']['author'][0]['properties']['photo'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the URL, i.e., `href` value, of the current page's first "like,"
	 * "bookmark," or "repost" link.
	 *
	 * @return string Link URL.
	 */
	public function get_link_url() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['repost-of'][0] ) && filter_var( $post['properties']['repost-of'][0], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['repost-of'][0];
			}

			if ( ! empty( $post['properties']['repost-of'][0]['value'] ) && filter_var( $post['properties']['repost-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['repost-of'][0]['value'];
			}

			if ( ! empty( $post['properties']['like-of'][0] ) && filter_var( $post['properties']['like-of'][0], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['like-of'][0];
			}

			if ( ! empty( $post['properties']['like-of'][0]['value'] ) && filter_var( $post['properties']['like-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['like-of'][0]['value'];
			}

			if ( ! empty( $post['properties']['bookmark-of'][0] ) && filter_var( $post['properties']['bookmark-of'][0], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['bookmark-of'][0];
			}

			if ( ! empty( $post['properties']['bookmark-of'][0]['value'] ) && filter_var( $post['properties']['bookmark-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['bookmark-of'][0]['value'];
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
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['repost-of'][0]['properties']['name'][0] ) && is_string( $post['properties']['repost-of'][0]['properties']['name'][0] ) ) {
				return $post['properties']['repost-of'][0]['properties']['name'][0];
			}

			if ( ! empty( $post['properties']['like-of'][0]['properties']['name'][0] ) && is_string( $post['properties']['like-of'][0]['properties']['name'][0] ) ) {
				return $post['properties']['like-of'][0]['properties']['name'][0];
			}

			if ( ! empty( $post['properties']['bookmark-of'][0]['properties']['name'][0] ) && is_string( $post['properties']['bookmark-of'][0]['properties']['name'][0] ) ) {
				return $post['properties']['bookmark-of'][0]['properties']['name'][0];
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
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['type'][0] ) && in_array( $post['type'][0], array( 'event', 'recipe', 'review' ), true ) ) {
				return $post['type'];
			}

			if ( ! empty( $post['properties']['repost-of'] ) ) {
				return 'repost';
			}

			if ( ! empty( $post['properties']['in-reply-to'] ) ) {
				return 'reply';
			}

			if ( ! empty( $post['properties']['like-of'] ) ) {
				return 'like';
			}

			if ( ! empty( $post['properties']['bookmark-of'] ) ) {
				return 'bookmark';
			}

			if ( ! empty( $post['properties']['follow-of'] ) ) {
				return 'follow';
			}

			if ( ! empty( $post['properties']['checkin'] ) ) {
				return 'checkin';
			}

			if ( ! empty( $post['properties']['video'] ) ) {
				return 'video';
			}

			if ( ! empty( $post['properties']['video'] ) ) {
				return 'audio';
			}

			if ( ! empty( $post['properties']['photo'] ) ) {
				return 'photo';
			}

			$name = ! empty( $post['properties']['name'] )
				? trim( $post['properties']['name'] )
				: '';

			if ( empty( $name ) ) {
				return 'note';
			}

			$content = '';

			if ( ! empty( $post['properties']['content']['text'] ) ) {
				$content = $post['properties']['content']['text'];
			} elseif ( ! empty( $post['properties']['summary'] ) ) {
				$content = $post['properties']['summary'];
			}

			$name    = preg_replace( '~\s+~', ' ', $name );
			$content = preg_replace( '~\s+~', ' ', $content );

			if ( 0 !== strpos( $content, $name ) ) {
				return 'article';
			}

			return 'note';
		}

		return '';
	}
}
