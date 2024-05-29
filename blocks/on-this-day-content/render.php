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
	);

	$posts = get_posts( $args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	set_transient( $transient, $posts, MONTH_IN_SECONDS );
}

if ( empty( $posts ) ) {
	return;
}

$output = "<ul style='list-style: none;'>\n";

foreach ( $posts as $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$output .= "<li>\n";
	$output .= '<p style="margin-bottom: 0;">' . get_the_excerpt( $post ) . "</p>\n";
	$output .= '<span class="has-small-font-size"><a href="' . esc_url( get_permalink( $post ) ) . '">' . get_the_title( $post ) . '</a>';
	$output .= '<span class="sep" aria-hidden="true"> • </span>';
	$output .= '<a href="' . esc_url( get_permalink( $post ) ) . '">' . get_the_date( get_option( 'date_format' ), $post ) . '</a></span>';
	$output .= "</li>\n";
}

$output .= "\n</ul>";

$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
