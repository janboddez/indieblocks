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

if ( isset( $attributes['type'] ) && is_array( $attributes['type'] ) ) {
	$types = $attributes['type'];
}

if ( empty( $types ) ) {
	return;
}

$facepile_comments = \IndieBlocks\get_facepile_comments( $post_id, $types );

if ( empty( $facepile_comments ) && empty( $attributes['forceShow'] ) ) {
	return;
}

add_action( 'wp_footer', '\\IndieBlocks\\print_facepile_icons', 999 );

$output = '';

if ( ! empty( $attributes['countOnly'] ) ) {
	if ( ! empty( $attributes['icons'] ) && ! empty( $attributes['type'] ) ) {
		$kind    = ( (array) $attributes['type'] )[0];
		$output .= '<div class="indieblocks-count"><svg class="icon indieblocks-icon-' . $kind . '" aria-hidden="true" role="img"><use href="#indieblocks-icon-' . $kind . '" xlink:href="#indieblocks-icon-' . $kind . '"></use></svg> ' . count( $facepile_comments ) . '</div>';
	} else {
		$output .= '<div class="indieblocks-count">' . count( $facepile_comments ) . '</div>';
	}
} else {
	// Limit number of avatars shown. Might provide a proper option later.
	$facepile_num      = apply_filters( 'indieblocks_facepile_num', 25, $post_id );
	$facepile_comments = array_slice( $facepile_comments, 0, $facepile_num );

	foreach ( $facepile_comments as $facepile_comment ) {
		$avatar = get_avatar( $facepile_comment, 40 );

		if ( empty( $avatar ) ) {
			continue;

			// So, normally, WordPress would return a "Mystery Man" or whatever avatar, if there was none. Still, could
			// be it doesn't in case there's no author email address.
			// $avatar = '<img loading="lazy" decoding="async" src="' . plugins_url( '/assets/mm.png', dirname( __DIR__ ) ) . '" width="40" height="40" class="avatar avatar-40 photo">';
		}

		$processor = new \WP_HTML_Tag_Processor( $avatar );
		$processor->next_tag( 'img' );

		if ( ! empty( $attributes['backgroundColor'] ) ) {
			$processor->set_attribute( 'style', 'background:' . esc_attr( $attributes['backgroundColor'] ) ); // Even though `WP_HTML_Tag_Processor::set_attribute()` will run, e.g., `esc_attr()` for us.
		}

		$alt = $processor->get_attribute( 'alt' );
		$alt = ! empty( $alt ) ? $alt : get_comment_author( $facepile_comment );

		$processor->set_attribute( 'alt', esc_attr( $alt ) );

		$avatar = $processor->get_updated_html();

		$source = get_comment_meta( $facepile_comment->comment_ID, 'indieblocks_webmention_source', true );
		$kind   = get_comment_meta( $facepile_comment->comment_ID, 'indieblocks_webmention_kind', true );

		if ( in_array( $facepile_comment->comment_type, array( 'bookmark', 'like', 'repost' ), true ) ) {
			// Mentions initiated by the Webmention plugin use a slightly different data structure.
			$source = get_comment_meta( $facepile_comment->comment_ID, 'url', true );
			if ( empty( $source ) ) {
				$source = get_comment_meta( $facepile_comment->comment_ID, 'webmention_source_url', true );
			}

			$kind = $facepile_comment->comment_type;
		}

		$classes = array(
			'bookmark' => 'p-bookmark',
			'like'     => 'p-like',
			'repost'   => 'p-repost',
		);
		$class   = isset( $classes[ $kind ] ) ? esc_attr( $classes[ $kind ] ) : '';

		$titles     = array(
			'bookmark' => '&hellip; bookmarked this!',
			'like'     => '&hellip; liked this!',
			'repost'   => '&hellip; reposted this!',
		);
		$title_attr = isset( $titles[ $kind ] ) ? esc_attr( $titles[ $kind ] ) : '';

		if ( ! empty( $source ) ) {
			$el = '<li class="h-cite' . ( ! empty( $class ) ? " $class" : '' ) . '"' . ( ! empty( $title_attr ) ? ' title="' . $title_attr . '"' : '' ) . '>' .
			'<a class="u-url" href="' . esc_url( $source ) . '" target="_blank" rel="noopener noreferrer"><span class="h-card p-author">' . $avatar . '</span>' .
			( ! empty( $attributes['icons'] ) && ! empty( $kind )
				? '<svg class="icon indieblocks-icon-' . $kind . '" aria-hidden="true" role="img"><use href="#indieblocks-icon-' . $kind . '" xlink:href="#indieblocks-icon-' . $kind . '"></use></svg>'
				: ''
			) .
			"</a></li>\n";
		} else {
			$el = '<li class="h-cite' . ( ! empty( $class ) ? " $class" : '' ) . '"' . ( ! empty( $title_attr ) ? ' title="' . $title_attr . '"' : '' ) . '>' .
			'<span class="p-author h-card">' . $avatar . '</span>' .
			( ! empty( $attributes['icons'] ) && ! empty( $kind )
				? '<svg class="icon indieblocks-icon-' . $kind . '" aria-hidden="true" role="img"><use href="#indieblocks-icon-' . $kind . '" xlink:href="#indieblocks-icon-' . $kind . '"></use></svg>'
				: ''
			) .
			"</li>\n";
		}

		$icon_style = '';
		if ( ! empty( $attributes['color'] ) ) {
			$icon_style .= "color:{$attributes['color']};";
		}
		if ( ! empty( $attributes['iconBackgroundColor'] ) ) {
			$icon_style .= "background-color:{$attributes['iconBackgroundColor']};";
		}

		if ( ! empty( $icon_style ) ) {
			$processor = new \WP_HTML_Tag_Processor( $el );
			$processor->next_tag( 'svg' );

			$processor->set_attribute( 'style', esc_attr( $icon_style ) );
			$el = $processor->get_updated_html();
		}

		$output .= $el;
	}

	if ( ! empty( $attributes['avatarSize'] ) ) {
		$avatar_size    = esc_attr( (int) $attributes['avatarSize'] );
		$opening_ul_tag = "<ul class='indieblocks-avatar-size-{$avatar_size}'>";
	} else {
		$opening_ul_tag = '<ul>';
	}

	$output = $opening_ul_tag . trim( $output ) . '</ul>';
}

$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
