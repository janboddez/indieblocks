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

$types = array( 'bookmark', 'like', 'repost' );
foreach ( $block->innerBlocks as $inner_block ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	if ( 'indieblocks/facepile-content' !== $inner_block->blockName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		continue;
	}

	if ( ! empty( $inner_block->attrs['type'] ) && is_array( $inner_block->attrs['type'] ) ) {
		$types = $inner_block->attrs['type'];
	}
}

if ( empty( $types ) ) {
	return;
}

$facepile_comments = \IndieBlocks\get_facepile_comments( $post_id, $types );

if ( empty( $facepile_comments ) ) {
	return;
}

echo $block->render( array( 'dynamic' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
