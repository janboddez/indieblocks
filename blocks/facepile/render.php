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

$facepile_comments = \IndieBlocks\get_facepile_comments( $post_id );

if ( empty( $facepile_comments ) ) {
	return;
}

echo $block->render( array( 'dynamic' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
