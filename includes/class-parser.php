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
		$hash = hash( 'sha256', esc_url_raw( $this->url ) );

		if ( empty( $content ) ) {
			// No `$content` was passed along.
			$content = get_transient( 'indieblocks:html:' . $hash );
			if ( empty( $content ) && ! empty( $this->url ) ) {
				// Download page.
				$response = remote_get( $this->url );
				$content  = wp_remote_retrieve_body( $response );
				set_transient( 'indieblocks:html:' . $hash, $content, 3600 ); // Cache, even if empty.
			}
		}

		if ( empty( $content ) ) {
			// Can't do anything without HTML.
			return;
		}

		$content = mb_convert_encoding( $content, 'HTML-ENTITIES', mb_detect_encoding( $content ) );

		libxml_use_internal_errors( true );

		$this->dom->loadHTML( $content );

		$mf2 = get_transient( 'indieblocks:mf2:' . $hash );
		if ( empty( $mf2 ) ) {
			$mf2 = Mf2\parse( $content, $this->url );
			set_transient( 'indieblocks:mf2:' . $hash, $mf2, 3600 );
		}

		$this->mf2 = Mf2\parse( $content, $this->url );
	}

	/**
	 * Returns a page name.
	 *
	 * @return string Current page's name or title.
	 */
	public function get_name() {
		if ( ! empty( $this->mf2['items'][0]['type'][0] ) && 'h-entry' === $this->mf2['items'][0]['type'][0] ) {
			$hentry = $this->mf2['items'][0];

			if ( ! empty( $hentry['properties']['name'][0] ) ) {
				return $hentry['properties']['name'][0];
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
		if ( ! empty( $this->mf2['items'][0]['type'][0] ) && 'h-entry' === $this->mf2['items'][0]['type'][0] ) {
			$hentry = $this->mf2['items'][0];

			if ( ! empty( $hentry['properties']['author'][0] ) && is_string( $hentry['properties']['author'][0] ) ) {
				return $hentry['properties']['author'][0];
			}

			if ( ! empty( $hentry['properties']['author'][0]['properties']['name'][0] ) ) {
				return $hentry['properties']['author'][0]['properties']['name'][0];
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
		if ( ! empty( $this->mf2['items'][0]['type'][0] ) && 'h-entry' === $this->mf2['items'][0]['type'][0] ) {
			$hentry = $this->mf2['items'][0];

			if ( ! empty( $hentry['properties']['author'][0]['properties']['url'][0] ) ) {
				return $hentry['properties']['author'][0]['properties']['url'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the author's avatar, if it can find one.
	 *
	 * @return string Avatar URL.
	 */
	public function get_avatar() {
		if ( ! empty( $this->mf2['items'][0]['type'][0] ) && 'h-entry' === $this->mf2['items'][0]['type'][0] ) {
			$hentry = $this->mf2['items'][0];

			if ( ! empty( $hentry['properties']['author'][0]['properties']['photo'][0] ) ) {
				return $hentry['properties']['author'][0]['properties']['photo'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the URL, i.e., `href` value, of the first "like," or "bookmark,"
	 * or "repost" link.
	 *
	 * @return string Link URL.
	 */
	public function get_link_url() {
		if ( ! empty( $this->mf2['items'][0]['type'][0] ) && 'h-entry' === $this->mf2['items'][0]['type'][0] ) {
			$hentry = $this->mf2['items'][0];

			if ( ! empty( $hentry['properties']['repost-of'][0] ) && filter_var( $hentry['properties']['repost-of'][0], FILTER_VALIDATE_URL ) ) {
				return $hentry['properties']['repost-of'][0];
			}

			if ( ! empty( $hentry['properties']['repost-of'][0]['value'] ) && filter_var( $hentry['properties']['repost-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $hentry['properties']['repost-of'][0]['value'];
			}

			if ( ! empty( $hentry['properties']['like-of'][0] ) && filter_var( $hentry['properties']['like-of'][0], FILTER_VALIDATE_URL ) ) {
				return $hentry['properties']['like-of'][0];
			}

			if ( ! empty( $hentry['properties']['like-of'][0]['value'] ) && filter_var( $hentry['properties']['like-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $hentry['properties']['like-of'][0]['value'];
			}

			if ( ! empty( $hentry['properties']['bookmark-of'][0] ) && filter_var( $hentry['properties']['bookmark-of'][0], FILTER_VALIDATE_URL ) ) {
				return $hentry['properties']['bookmark-of'][0];
			}

			if ( ! empty( $hentry['properties']['bookmark-of'][0]['value'] ) && filter_var( $hentry['properties']['bookmark-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $hentry['properties']['bookmark-of'][0]['value'];
			}
		}

		return '';
	}

	/**
	 * Returns the name (i.e., the text content) of the first "like," or
	 * "bookmark," or "repost" link.
	 *
	 * @return string Link name.
	 */
	public function get_link_name() {
		if ( ! empty( $this->mf2['items'][0]['type'][0] ) && 'h-entry' === $this->mf2['items'][0]['type'][0] ) {
			$hentry = $this->mf2['items'][0];

			if ( ! empty( $hentry['properties']['repost-of'][0]['properties']['name'][0] ) && is_string( $hentry['properties']['repost-of'][0]['properties']['name'][0] ) ) {
				return $hentry['properties']['repost-of'][0]['properties']['name'][0];
			}

			if ( ! empty( $hentry['properties']['like-of'][0]['properties']['name'][0] ) && is_string( $hentry['properties']['like-of'][0]['properties']['name'][0] ) ) {
				return $hentry['properties']['like-of'][0]['properties']['name'][0];
			}

			if ( ! empty( $hentry['properties']['bookmark-of'][0]['properties']['name'][0] ) && is_string( $hentry['properties']['bookmark-of'][0]['properties']['name'][0] ) ) {
				return $hentry['properties']['bookmark-of'][0]['properties']['name'][0];
			}
		}

		return '';
	}
}
