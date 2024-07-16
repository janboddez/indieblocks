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
		'orderby'             => 'date',
		'order'               => 'DESC',
	);

	$posts = get_posts( $args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	set_transient( $transient, $posts, MONTH_IN_SECONDS );
}

if ( empty( $posts ) ) {
	return;
}

$count  = count( $posts );
$output = "<ul style='list-style: none;'>\n";

foreach ( $posts as $i => $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$post_year = get_the_date( 'Y', $post );

	if ( 0 === $i ) {
		$current_year = $post_year;

		/* translators: %d: year */
		$output .= '<li><strong>' . sprintf( esc_html__( '&hellip; in %d', 'indieblocks' ), (int) $post_year ) . "</strong>\n";
		$output .= "<ul style='list-style: none;'>\n";
	} elseif ( $post_year !== $current_year ) {
		$current_year = $post_year;

		$output .= "</ul>\n</li>\n";
		/* translators: %d: year */
		$output .= '<li><strong>' . sprintf( esc_html__( '&hellip; in %d', 'indieblocks' ), (int) $post_year ) . "</strong>\n";
		$output .= "<ul style='list-style: none;'>\n";
	}

	$output .= "<li>\n";
	$output .= '<p class="entry-excerpt">' . get_the_excerpt( $post ) . "</p>\n";
	$output .= '<span class="has-small-font-size"><a href="' . esc_url( get_permalink( $post ) ) . '">' . get_the_title( $post ) . '</a></span>';
	$output .= "</li>\n";

	if ( $i === $count - 1 ) {
		$output .= "\n</ul>\n</li>\n";
	}
}

$output .= "</ul>\n";

$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
