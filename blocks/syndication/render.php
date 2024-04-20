<?php
/**
 * @package IndieBlocks
 */

use function IndieBlocks\debug_log;

if ( ! isset( $block->context['postId'] ) ) {
	return;
}

$syndication_urls = array_filter(
	array(
		__( 'Mastodon', 'indieblocks' ) => get_post_meta( $block->context['postId'], '_share_on_mastodon_url', true ),
		__( 'Pixelfed', 'indieblocks' ) => get_post_meta( $block->context['postId'], '_share_on_pixelfed_url', true ),
	)
);

// Allow developers to parse in other plugins' links.
$syndication_urls = apply_filters( 'indieblocks_syndication_links', $syndication_urls, $block->context['postId'] );

if ( empty( $syndication_urls ) ) {
	return;
}

$output = esc_html( isset( $attributes['prefix'] ) ? trim( $attributes['prefix'] ) . ' ' : '' );

foreach ( $syndication_urls as $name => $syndication_url ) {
	$output .= '<a class="u-syndication" href="' . esc_url( $syndication_url ) . '">' . esc_html( $name ) . '</a>, ';
}

$output = rtrim( $output, ', ' ) . esc_html( isset( $attributes['suffix'] ) ? ' ' . trim( $attributes['suffix'] ) : '' );

$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
