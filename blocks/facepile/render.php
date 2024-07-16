<?php
/**
 * @package IndieBlocks
 */

if ( isset( $block->context['postId'] ) ) {
	$post_id = $block->context['postId']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
} elseif ( in_the_loop() ) {
	$post_id = get_the_ID(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}

if ( empty( $post_id ) ) {
	return;
}

$render = false;
$types  = array( 'bookmark', 'like', 'repost' );

$facepile_content_blocks = \IndieBlocks\parse_inner_blocks( $block->parsed_block['innerBlocks'], 'indieblocks/facepile-content' );
foreach ( $facepile_content_blocks as $inner_block ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	if ( isset( $inner_block['attrs']['type'] ) && is_array( $inner_block['attrs']['type'] ) ) {
		// If the `$type` attribute is set, use that.
		$types = $inner_block['attrs']['type'];
	}

	$facepile_comments = \IndieBlocks\get_facepile_comments( $post_id, $types );
	if ( ! empty( $facepile_comments ) ) {
		// As soon as we've found some "facepile comments," we're good. No need to process any other inner blocks.
		// @todo: Stop searching after the first result, too.
		$render = true;
		break;
	}
}

if ( ! $render ) {
	return;
}

echo $block->render( array( 'dynamic' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
