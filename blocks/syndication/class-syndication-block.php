<?php

namespace IndieBlocks;

class Syndication_Block {
	/**
	 * Registers the Syndication block.
	 */
	public static function register() {
		register_block_type_from_metadata( __DIR__, array( 'render_callback' => array( __CLASS__, 'render_syndication_block' ) ) );

		// This oughta happen automatically, but whatevs.
		wp_set_script_translations(
			generate_block_asset_handle( 'indieblocks/syndication', 'editorScript' ),
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);
	}

	/**
	 * Renders the `indieblocks/syndication` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                Rendered HTML.
	 */
	public static function render_syndication_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$urls = array_filter(
			array(
				__( 'Mastodon', 'indieblocks' ) => get_post_meta( $block->context['postId'], '_share_on_mastodon_url', true ),
				__( 'Pixelfed', 'indieblocks' ) => get_post_meta( $block->context['postId'], '_share_on_pixelfed_url', true ),
			)
		);

		// Allow developers to parse in other plugins' links.
		$urls = apply_filters( 'indieblocks_syndication_links', $urls, $block->context['postId'] );

		if ( empty( $urls ) ) {
			return '';
		}

		$output = esc_html__( 'Also on', 'indieblocks' ) . ' ';

		foreach ( $urls as $name => $url ) {
			$output .= ' <a class="u-syndication" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>, ';
		}

		$wrapper_attributes = get_block_wrapper_attributes();

		return '<div ' . $wrapper_attributes . '>' .
			rtrim( $output, ', ' ) .
		'</div>';
	}
}
