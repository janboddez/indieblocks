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

$transient = 'indieblocks:onthisday:' . get_the_date( 'Y-m-d', $post_id );
$posts     = get_transient( $transient ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( false === $posts ) {
	$args = array(
		'day'                 => get_the_date( 'd', $post_id ),
		'monthnum'            => get_the_date( 'm', $post_id ),
		'numberposts'         => 3,
		'ignore_sticky_posts' => true,
		'date_query'          => array(
			array(
				'year'    => get_the_date( 'Y', $post_id ),
				'compare' => '!=',
			),
		),
		// 'post__not_in'        => array( $post_id ),
	);

	$posts = get_posts( $args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	set_transient( $transient, $posts, MONTH_IN_SECONDS );
}

if ( empty( $posts ) ) {
	return;
}

echo $block->render( array( 'dynamic' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
