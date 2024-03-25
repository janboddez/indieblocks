<?php
/**
 * @package IndieBlocks
 */

if ( ! isset( $block->context['postId'] ) ) {
	return;
}

$facepile_comments = \IndieBlocks\get_facepile_comments( $block->context['postId'] );

if ( empty( $facepile_comments ) ) {
	return;
}

echo $block->render( array( 'dynamic' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
