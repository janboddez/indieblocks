<?php
/**
 * @package IndieBlocks
 */

namespace IndieBlocks;

class Location_Block {
	/**
	 * Registers the Location block.
	 */
	public static function register() {
		register_block_type_from_metadata( __DIR__, array( 'render_callback' => array( __CLASS__, 'render' ) ) );

		// This oughta happen automatically, but whatevs.
		wp_set_script_translations(
			generate_block_asset_handle( 'indieblocks/location', 'editorScript' ),
			'indieblocks',
			dirname( __DIR__ ) . '/languages'
		);
	}

	/**
	 * Renders the `indieblocks/location` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                Rendered HTML.
	 */
	public static function render( $attributes, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) {
			return '';
		}

		$location = get_post_meta( $block->context['postId'], 'geo_address', true );

		if ( empty( $location ) ) {
			return '';
		}

		$output = '<span class="p-name">' . esc_html( $location ) . '</span>';

		if ( ! empty( $attributes['includeWeather'] ) ) {
			$weather = get_post_meta( $block->context['postId'], '_indieblocks_weather', true );
		}

		if ( ! empty( $weather['description'] ) && ! empty( $weather['temperature'] ) ) {
			$temp = $weather['temperature'];
			$temp = $temp > 100 // Older plugin versions supported only degress Celsius, newer versions only Kelvin.
				? $temp - 273.15
				: $temp;

			$options = get_options();

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

		$output = apply_filters( 'indieblocks_location_html', '<span class="h-geo">' . $output . '</span>', $block->context['postId'] );

		$wrapper_attributes = get_block_wrapper_attributes();

		return '<div ' . $wrapper_attributes . '>' .
			$output .
		'</div>';
	}
}
