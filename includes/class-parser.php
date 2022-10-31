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
	 */
	public function parse() {
		$content = get_transient( 'indieblocks:' . hash( 'sha256', esc_url_raw( $this->url ) ) );

		if ( empty( $content ) ) {
			$response = remote_get( $this->url );
			$content  = wp_remote_retrieve_body( $response );

			set_transient( 'indieblocks:' . hash( 'sha256', esc_url_raw( $this->url ) ), $content, 3600 );
		}

		if ( empty( $content ) ) {
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
}
