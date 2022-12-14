<?php
/**
 * Helper functions.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Returns this plugin's options.
 *
 * Roughly equal to `get_option( 'indieblocks_settings' )`.
 *
 * @return array Current plugin settings.
 */
function get_options() {
	return IndieBlocks::get_instance()
		->get_options_handler()
		->get_options();
}

/**
 * Registers this plugin's _active_ permalinks, then flushes the rewrite cache.
 */
function flush_permalinks() {
	Post_Types::register_post_types();
	Post_Types::custom_permalinks();
	Post_Types::create_date_archives();
	Feeds::create_post_feed();

	flush_rewrite_rules();
}

/**
 * Wrapper around `wp_remote_get()`.
 *
 * @param  string $url          URL to fetch.
 * @param  bool   $json         Whether to accept (only) JSON.
 * @return WP_Response|WP_Error Response.
 */
function remote_get( $url, $json = false ) {
	$args = array(
		'timeout'             => 11,
		'limit_response_size' => 1048576,
		'user-agent'          => get_user_agent(),
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

/**
 * Wrapper around `wp_remote_post()`.
 *
 * @param  string $url          URL to fetch.
 * @param  bool   $json         Whether to accept (only) JSON.
 * @param  array  $args         Arguments for `wp_remote_post()`.
 * @return WP_Response|WP_Error Response.
 */
function remote_post( $url, $json = false, $args = array() ) {
	$args = array_merge(
		array(
			'timeout'             => 11,
			'limit_response_size' => 1048576,
			'user-agent'          => get_user_agent(),
		),
		$args
	);

	if ( $json ) {
		$args['headers'] = array( 'Accept' => 'application/json' );
	}

	$args = apply_filters( 'indieblocks_fetch_args', $args, $url );

	return wp_remote_post(
		esc_url_raw( $url ),
		$args
	);
}

/**
 * Returns a user agent.
 *
 * @param  string $url The URL we're looking to fetch.
 * @return string      User agent.
 */
function get_user_agent( $url = '' ) {
	$wp_version = get_bloginfo( 'version' );
	$user_agent = 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) . '; IndieBlocks';

	// Allow developers to override this user agent.
	return apply_filters( 'indieblocks_fetch_user_agent', $user_agent, $url );
}
