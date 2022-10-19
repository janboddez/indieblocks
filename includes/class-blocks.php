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
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'init', array( __CLASS__, 'register_templates' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );
	}

	public static function register_api_endpoints() {
		register_rest_route(
			'indieblocks/v1',
			'/title',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( __CLASS__, 'get_title' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
			)
		);
	}

	public static function permission_callback() {
		return current_user_can( 'edit_posts' );
	}

	public static function get_title( $request ) {
		$url = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return '';
		}

		$parser = new Parser( $url );
		$parser->parse();

		$title = $parser->get_title();

		if ( empty( $title ) ) {
			$title = $url;
		}

		return rest_ensure_response( $title );
	}

	/**
	 * Registers the "Note Context" block.
	 */
	public static function register_blocks() {
		register_block_type( dirname( __DIR__ ) . '/blocks/context' );

		// This oughta happen automatically, but whatevs.
		$script_handle = generate_block_asset_handle( 'indieblocks/context', 'editorScript' );
		wp_set_script_translations( $script_handle, 'indieblocks', dirname( __DIR__ ) . '/languages' );

		register_block_type( dirname( __DIR__ ) . '/blocks/reply' );

		$script_handle = generate_block_asset_handle( 'indieblocks/context', 'editorScript' );
		wp_set_script_translations( $script_handle, 'indieblocks', dirname( __DIR__ ) . '/languages' );
	}

	/**
	 * Registers Note and Like block templates.
	 */
	public static function register_templates() {
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
