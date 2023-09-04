<?php
/**
 * @package IndieBlocks
 */

namespace IndieBlocks;

class Facepile_Block {
	/**
	 * Registers the Facepile block.
	 */
	public static function register() {
		register_block_type_from_metadata( __DIR__, array( 'render_callback' => array( __CLASS__, 'render_facepile_block' ) ) );

		// This oughta happen automatically, but whatevs.
		wp_set_script_translations(
			generate_block_asset_handle( 'indieblocks/facepile', 'editorScript' ),
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);
	}

	/**
	 * Renders the `indieblocks/facepile` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                Rendered HTML.
	 */
	public static function render_facepile_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$facepile_comments = \IndieBlocks\get_facepile_comments( $block->context['postId'] );

		if ( empty( $facepile_comments ) ) {
			return '';
		}

		return $block->render( array( 'dynamic' => false ) );
	}
}
