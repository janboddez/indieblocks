<?php
/**
 * @package IndieBlocks
 */

namespace IndieBlocks;

class Link_Preview_Block {
	/**
	 * Registers the Link Preview block.
	 */
	public static function register() {
		register_block_type_from_metadata( __DIR__, array( 'render_callback' => array( __CLASS__, 'render_link_preview_block' ) ) );

		// This oughta happen automatically, but whatevs.
		wp_set_script_translations(
			generate_block_asset_handle( 'indieblocks/link-preview', 'editorScript' ),
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);
	}

	/**
	 * Renders the `indieblocks/link-preview` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                The block's output HTML.
	 */
	public static function render_link_preview_block( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$card = get_post_meta( $block->context['postId'], '_indieblocks_link_preview', true );

		if ( empty( $card['title'] ) || empty( $card['url'] ) ) {
			return '';
		}

		$border_style = '';

		if ( ! empty( $attributes['borderColor'] ) ) {
			$border_style .= "border-color:var(--wp--preset--color--{$attributes['borderColor']});";
		} elseif ( ! empty( $attributes['style']['border']['color'] ) ) {
			$border_style .= "border-color:{$attributes['style']['border']['color']};";
		}

		if ( ! empty( $attributes['style']['border']['width'] ) ) {
			$border_style .= "border-width:{$attributes['style']['border']['width']};";
		}

		if ( ! empty( $attributes['style']['border']['radius'] ) ) {
			$border_style .= "border-radius:{$attributes['style']['border']['radius']};";
		}

		$border_style = trim( $border_style );

		ob_start();
		?>
		<a class="indieblocks-card" href="<?php echo esc_url( $card['url'] ); ?>" rel="nofollow">
			<?php
			printf( '<div class="indieblocks-card-thumbnail" style="%s">', esc_attr( trim( $border_style . ' border-block:none;border-inline-start:none;border-radius:0 !important;' ) ) );

			if ( ! empty( $card['thumbnail'] ) ) :
				?>
				<img src="<?php echo esc_url_raw( $card['thumbnail'] ); ?>" width="90" height="90" alt="">
				<?php
			endif;

			echo '</div>';
			?>
			<div class="indieblocks-card-body" style="width: calc(100% - 90px - <?php echo isset( $attributes['style']['border']['width'] ) ? esc_attr( $attributes['style']['border']['width'] ) : '0px'; ?>);">
				<strong><?php echo esc_html( $card['title'] ); ?></strong>
				<small><?php echo esc_html( preg_replace( '~www.~', '', wp_parse_url( $card['url'], PHP_URL_HOST ) ) ); ?></small>
			</div>
		</a>
		<?php
		$output = ob_get_clean();

		$wrapper_attributes = get_block_wrapper_attributes();

		$output = '<div ' . $wrapper_attributes . ' >' .
			$output .
		'</div>';

		$processor = new \WP_HTML_Tag_Processor( $output );
		$processor->next_tag( 'div' );

		$style = $processor->get_attribute( 'style' );
		if ( null === $style ) {
			$processor->set_attribute( 'style', esc_attr( $border_style ) );
		} else {
			// Append our styles.
			$processor->set_attribute( 'style', esc_attr( rtrim( $style, ';' ) . ";$border_style" ) );
		}

		return $processor->get_updated_html();
	}
}
