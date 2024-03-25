<?php
/**
 * @package IndieBlocks
 */

if ( ! isset( $block->context['postId'] ) ) {
	return;
}

$location = get_post_meta( $block->context['postId'], 'geo_address', true );

if ( empty( $location ) ) {
	return;
}

$output = '<span class="p-name">' . esc_html( $location ) . '</span>';

if ( ! empty( $attributes['includeWeather'] ) ) {
	$weather = get_post_meta( $block->context['postId'], '_indieblocks_weather', true );
}

if ( ! empty( $weather['description'] ) && ! empty( $weather['temperature'] ) ) {
	$temp = $weather['temperature'];
	$temp = $temp > 100 // Older plugin versions supported only degrees Celsius, newer versions only Kelvin.
		? $temp - 273.15
		: $temp;

	$options = \IndieBlocks\get_options();

	if ( empty( $options['weather_units'] ) || 'metric' === $options['weather_units'] ) {
		$temp_unit = '&nbsp;°C';
	} else {
		$temp      = 32 + $temp * 9 / 5;
		$temp_unit = '&nbsp;°F';
	}
	$temp = number_format( round( $temp ) ); // Round.

	$sep = ! empty( $attributes['separator'] ) ? $attributes['separator'] : ' • ';
	$sep = apply_filters( 'indieblocks_location_separator', $sep, $block->context['postId'] );

	$output .= '<span class="sep" aria-hidden="true">' . esc_html( $sep ) . '</span><span class="indieblocks-weather">' . esc_html( $temp . $temp_unit ) . ', ' . esc_html( strtolower( $weather['description'] ) ) . '</span>';
}

$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo apply_filters( 'indieblocks_location_html', '<span class="h-geo">' . $output . '</span>', $block->context['postId'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
