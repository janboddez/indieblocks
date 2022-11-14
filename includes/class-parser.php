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
	 * @param  string $url URL of the page we'll be parsing.
	 */
	public function __construct( $url ) {

		$this->url = $url;
		$this->dom = new \DOMDocument();

		libxml_use_internal_errors( true );
	}

	/**
	 * Fetches the page, then loads its DOM.
	 *
	 * @param string $content (Optional) HTML to be parsed instead.
	 */
	public function parse( $content = '' ) {
		if ( empty( $content ) ) {
			// No `$content` was passed along.
			$content = get_transient( 'indieblocks:html:' . hash( 'sha256', esc_url_raw( $this->url ) ) );

			if ( empty( $content ) ) {
				// Download page.
				$response = remote_get( $this->url );
				$content  = wp_remote_retrieve_body( $response ); // Cache, even if empty.

				set_transient( 'indieblocks:html:' . hash( 'sha256', esc_url_raw( $this->url ) ), $content, 3600 );
			}
		}

		if ( empty( $content ) ) {
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// `$content` is (still) empty.
			return;
		}

		$content = mb_convert_encoding( $content, 'HTML-ENTITIES', mb_detect_encoding( $content ) );

		$this->dom->loadHTML( $content );

		$this->mf2 = Mf2\parse( $content, $this->url );
	}

	/**
	 * Returns page name.
	 *
	 * @return string Current page's `title` (for now) element.
	 */
	public function get_name() {
		if ( ! empty( $this->mf2['items'][0]['type'][0] ) && 'h-entry' === $this->mf2['items'][0]['type'][0] ) {
			$hentry = $this->mf2['items'][0];

			if ( ! empty( $hentry['properties']['name'][0] ) ) {
				return $hentry['properties']['name'][0];
			}
		}

		$title = $this->dom->getElementsByTagName( 'title' );

		if ( isset( $title->length ) && $title->length > 0 ) {
			return trim( $title->item( 0 )->textContent );
		}

		return '';
	}

	/**
	 * Returns page author.
	 *
	 * @return string Page author.
	 */
	public function get_author() {
		if ( ! empty( $this->mf2['items'][0]['type'][0] ) && 'h-entry' === $this->mf2['items'][0]['type'][0] ) {
			$hentry = $this->mf2['items'][0];

			if ( ! empty( $hentry['properties']['author'][0]['properties']['name'][0] ) ) {
				return $hentry['properties']['author'][0]['properties']['name'][0];
			}
		}

		return '';
	}
}
