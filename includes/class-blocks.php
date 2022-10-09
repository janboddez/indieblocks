<?php
/**
 * Where Gutenberg blocks are registered.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * All things Gutenberg.
 */
class Blocks {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'init', array( __CLASS__, 'register_template' ), 20 );
	}

	/**
	 * Registers the "Note Context" block.
	 */
	public static function register_block() {
		register_block_type( dirname( __DIR__ ) . '/blocks/context' );

		// This oughta happen automatically, but whatevs.
		$script_handle = generate_block_asset_handle( 'indieblocks/context', 'editorScript' );
		wp_set_script_translations( $script_handle, 'indieblocks', dirname( __DIR__ ) . '/languages' );
	}

	/**
	 * Registers Note and Like block templates.
	 */
	public static function register_template() {
		foreach ( array( 'indieblocks_like', 'indieblocks_note' ) as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );

			if ( ! $post_type_object ) {
				// Post type not active.
				continue;
			}

			$post_type_object->template = array(
				array(
					'indieblocks/context',
					'indieblocks_like' === $post_type
						? array( 'kind' => 'u-like-of' )
						: array(),
				),
			);

			if ( 'indieblocks_note' === $post_type ) {
				$post_type_object->template[] = array(
					'core/group',
					array( 'className' => 'e-content' ),
					array(
						array( 'core/paragraph' ),
					),
				);
			}
		}
	}
}
