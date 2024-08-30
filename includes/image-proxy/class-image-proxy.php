<?php

namespace IndieBlocks\Image_Proxy;

class Image_Proxy {
	/**
	 * Registers the REST API route.
	 */
	public static function register() {
		register_rest_route(
			'indieblocks/v1',
			'/imageproxy',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( __CLASS__, 'proxy' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Serves remote images.
	 *
	 * @param  \WP_REST_Request $request REST API request.
	 * @return void|\WP_Error            Nothing, or an error if something goes wrong.
	 */
	public static function proxy( $request ) {
		if ( $request->get_header( 'if-modified-since' ) || $request->get_header( 'if-none-match' ) ) {
			// It would seem the client already has the requested item.
			http_response_code( 304 ); // This ... seems to sometimes work?
			exit;
		}

		$url = $request->get_param( 'url' );
		$url = is_string( $url ) ? rawurldecode( $url ) : null;

		if ( empty( $url ) || ! wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'invalid_url', esc_html__( 'Invalid URL.', 'indieblocks' ), array( 'status' => 400 ) );
		}

		$hash = $request->get_param( 'hash' );

		$options = \IndieBlocks\get_options();
		if ( ! empty( $options['image_proxy_secret'] ) && hash_hmac( 'sha1', $url, $options['image_proxy_secret'] ) !== $hash ) {
			return new \WP_Error( 'invalid_hash', esc_html__( 'Invalid hash.', 'indieblocks' ), array( 'status' => 400 ) );
		}

		$headers = array_filter(
			array(
				'Accept-Encoding'         => $request->get_header( 'accept-encoding' ),
				'Connection'              => 'close',
				'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'",
				'Range'                   => $request->get_header( 'range' ),
				'X-Content-Type-Options'  => 'nosniff',
				'X-Frame-Options'         => 'deny',
				'X-XSS-Protection'        => '1; mode=block',
			)
		);

		$args = array(
			'http' => array(
				'header'          => array_map(
					function ( $key, $value ) {
						return $key . ': ' . $value;
					},
					array_keys( $headers ),
					$headers
				),
				'follow_location' => true,
				'ignore_errors'   => true, // "Allow," i.e., don't crash on, HTTP errors (4xx, 5xx).
				'timeout'         => 11,
				'user_agent'      => \IndieBlocks\get_user_agent( $url ),
			),
		);

		$stream = stream_context_create( $args );
		$handle = fopen( $url, 'rb', false, $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return new \WP_Error( 'unkown_error', esc_html__( 'Could not open URL.', 'indieblocks' ), array( 'status' => 0 ) );
		}

		// Newly received headers.
		list( $code, $headers ) = static::get_headers( $handle );

		if ( ! in_array( $code, array( 200, 201, 202, 203, 206, 301, 302, 304, 307, 308 ), true ) ) {
			// Return an empty response.
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			return new \WP_Error( 'unkown_error', esc_html__( 'Something went wrong.', 'indieblocks' ), array( 'status' => $code ) );
		}

		$headers['Cache-Control'] = 'public, max-age=31536000';

		http_response_code( $code );

		// Send all headers.
		foreach ( $headers as $key => $value ) {
			if ( 'location' === strtolower( $key ) ) {
				// Except this one.
				continue;
			}

			header( $key . ': ' . $value );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		// Pass thru the original file without loading it in memory entirely.
		fpassthru( $handle );
		exit;
	}

	/**
	 * Returns a HTTP response code and headers.
	 *
	 * @param  resource $stream REST API request.
	 * @return array            An array containing a response code, and an associative array of response headers.
	 */
	protected static function get_headers( $stream ) {
		$status  = 0;
		$headers = array();

		$metadata = stream_get_meta_data( $stream );

		foreach ( $metadata['wrapper_data'] as $line ) {
			if ( preg_match( '~^http/.+? (\d+) .*?$~i', $line, $match ) ) {
				// Keeps only the most recent status code. E.g., after a redirect.
				$status = (int) $match[1];
				continue;
			}

			$row = explode( ': ', $line );

			if ( count( $row ) > 1 ) {
				$headers[ array_shift( $row ) ] = implode( ': ', $row );
			}
		}

		return array( $status, $headers );
	}
}
