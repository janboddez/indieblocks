<?php

namespace IndieBlocks\Commands;

class Commands {
	/**
	 * @subcommand cache-image
	 *
	 * Downloads and resizes an image.
	 *
	 * <url>
	 * : The URL of the image.
	 *
	 * [--w=<width>]
	 * : The target width.
	 *
	 * [--h=<height>]
	 * : The target height.
	 *
	 * [--dir=<dir>]
	 * : The destination folder, relative to, typically, `wp-content/uploads`.
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args "Associated" arguments.
	 */
	public function cache_image( $args, $assoc_args ) {
		$options = \IndieBlocks\get_options();
		if ( empty( $options['cache_avatars'] ) ) {
			\WP_CLI::error( 'Avatar cache disabled.' );
			return;
		}

		$url = trim( $args[0] );
		if ( ! preg_match( '~^https?://~', $url ) ) {
			$url = 'http://' . ltrim( $url, '/' );
		}

		if ( ! wp_http_validate_url( $url ) ) {
			\WP_CLI::error( 'Invalid URL.' );
			return;
		}

		$dir = isset( $assoc_args['dir'] )
			? $assoc_args['dir']
			: 'indieblocks-avatars';
		$dir = sanitize_title( $dir );

		$hash     = hash( 'sha256', esc_url_raw( $url ) );
		$ext      = pathinfo( $url, PATHINFO_EXTENSION );
		$filename = $hash . ( ! empty( $ext ) ? '.' . $ext : '' );

		\WP_CLI::log( "Saving to `$dir/$filename`." );

		$width = isset( $assoc_args['w'] ) && ctype_digit( (string) $assoc_args['w'] )
			? (int) $assoc_args['w']
			: 150;

		$height = isset( $assoc_args['h'] ) && ctype_digit( (string) $assoc_args['h'] )
			? (int) $assoc_args['h']
			: 150;

		\IndieBlocks\store_image( $url, $filename, $dir, $width, $height );

		\WP_CLI::success( 'All done!' );
	}
}
