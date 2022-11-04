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
	 * URL of the page we're parsing.
	 *
	 * @var string $url URL of the page we're parsing.
	 */
	private $url;

	/**
	 * DOM of the page we're parsing.
	 *
	 * @var \DOMDocument $content DOM of the page we're parsing.
	 */
	private $dom;

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
				$content  = wp_remote_retrieve_body( $response );

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
	}

	/**
	 * Returns page title.
	 *
	 * @return string Current page's `title` element.
	 */
	public function get_title() {
		$title = $this->dom->getElementsByTagName( 'title' );

		if ( isset( $title->length ) && $title->length > 0 ) {
			return trim( $title->item( 0 )->textContent );
		}

		return '';
	}

	/**
	 * Returns post kind.
	 *
	 * @return string Detected kind, or empty string.
	 */
	public function get_kind() {
		$xpath = new \DOMXPath( $this->dom );

		foreach ( $xpath->query( '//*[contains(concat(" ", @class, " "), " u-like-of ")]' ) as $result ) {
			return 'like';
		}

		foreach ( $xpath->query( '//*[contains(concat(" ", @class, " "), " u-in-reply-to ")]' ) as $result ) {
			return 'reply';
		}

		foreach ( $xpath->query( '//*[contains(concat(" ", @class, " "), " u-bookmark-of ")]' ) as $result ) {
			return 'bookmark';
		}

		return '';
	}
}
