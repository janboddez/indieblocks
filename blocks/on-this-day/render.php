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
	// Transient not found. Run the query, and store the outcome.
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
		'orderby'             => 'date',
		'order'               => 'DESC',
	);

	$posts = get_posts( $args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	// Cache for (up to) one month.
	set_transient( $transient, $posts, MONTH_IN_SECONDS );
}

if ( empty( $posts ) ) {
	// Nothing to show. Don't output anything.
	return;
}

echo $block->render( array( 'dynamic' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
