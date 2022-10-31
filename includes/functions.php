<?php
/**
 * Helper functions.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Wrapper around `wp_remote_get()`.
 *
 * @param  string $url          URL to fetch.
 * @param  bool   $json         Whether to accept (only) JSON.
 * @return WP_Response|WP_Error Response.
 */
function remote_get( $url, $json = false ) {
	$wp_version = get_bloginfo( 'version' );
	$user_agent = 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) . '; IndieBlocks';

	$args = array(
		'timeout'             => 11,
		'limit_response_size' => 1048576,
		'user-agent'          => $user_agent,
	);

	if ( $json ) {
		$args['headers'] = array( 'Accept' => 'application/json' );
	}

	$args = apply_filters( 'indieblocks_fetch_args', $args, $url );

	return wp_remote_get(
		esc_url_raw( $url ),
		$args
	);
}
