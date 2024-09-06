<?php

namespace IndieBlocks\Webmention;

use IndieBlocks\Plugin;

use function IndieBlocks\convert_encoding;
use function IndieBlocks\debug_log;
use function IndieBlocks\delete_meta;
use function IndieBlocks\get_meta;
use function IndieBlocks\get_options;
use function IndieBlocks\remote_get;
use function IndieBlocks\remote_post;
use function IndieBlocks\update_meta;

/**
 * Webmention sender.
 */
class Webmention_Sender {
	/**
	 * Registers hook callbacks.
	 */
	public static function register() {
		// Schedule sending of mentions when a supported post is published ...
		foreach ( Webmention::get_supported_post_types() as $post_type ) {
			add_action( "publish_{$post_type}", array( __CLASS__, 'schedule_webmention' ), 10, 2 );
		}

		// And when a post was just trashed.
		add_action( 'trashed_post', array( __CLASS__, 'schedule_webmention' ) );

		// And when a comment is first inserted into the database ...
		add_action( 'comment_post', array( __CLASS__, 'schedule_webmention' ) );

		// And when a comment is approved. Or a previously approved comment updated.
		add_action( 'comment_approved_comment', array( __CLASS__, 'schedule_webmention' ), 10, 2 );

		// Send previously scheduled mentions.
		add_action( 'indieblocks_webmention_send', array( __CLASS__, 'send_webmention' ) );

		// Add a "classic" meta box.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'add_meta_boxes_comment', array( __CLASS__, 'add_meta_box' ) );

		// Enqueue classic editor JS.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// Enqueue block editor sidebar.
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_field' ) );

		// Support (explicit) re-scheduling.
		add_action( 'wp_ajax_indieblocks_resend_webmention', array( __CLASS__, 'reschedule_webmention' ) );
	}

	/**
	 * Schedules the sending of webmentions, if enabled.
	 *
	 * Scans for outgoing links, but leaves fetching Webmention endpoints to the
	 * callback function queued in the background.
	 *
	 * @param int                         $obj_id     Post or comment ID.
	 * @param \WP_Post|\WP_Comment|string $obj        Post or comment object, or previous post status.
	 * @param mixed                       $deprecated Used to be the post object, now deprecated.
	 */
	public static function schedule_webmention( $obj_id, $obj = null, $deprecated = null ) {
		if ( null !== $deprecated ) {
			_deprecated_argument( 'post', '0.10.0', 'Passing a third argument to `\IndieBlocks\Webmention\Webmention_Sender::schedule_webmention()` is deprecated.' );
		}

		if ( defined( 'OUTGOING_WEBMENTIONS' ) && ! OUTGOING_WEBMENTIONS ) {
			// Disabled.
			return;
		}

		if ( null === $obj ) {
			// For the other hooks, we also pass an object, but not for these two.
			if ( 'comment_post' === current_filter() ) {
				$obj = get_comment( $obj_id );
			} elseif ( 'trashed_post' === current_filter() ) {
				$obj = get_post( $obj_id );
			}
		}

		if ( null === $obj ) {
			debug_log( '[IndieBlocks\Webmention] Unable to fetch object for ID ' . $obj_id . ' (filter: `' . current_filter() . '`).' );
			return;
		}

		if ( $obj instanceof \WP_Post ) {
			if ( ! in_array( $obj->post_status, array( 'publish', 'trash' ), true ) ) {
				return;
			}

			/* @see https://github.com/WordPress/gutenberg/issues/15094#issuecomment-1021288811. */
			if ( ! empty( $_REQUEST['meta-box-loader'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// Avoid scheduling (or posting, in case of no delay) webmentions more than once.
				return;
			}

			if ( wp_is_post_revision( $obj->ID ) || wp_is_post_autosave( $obj->ID ) ) {
				return;
			}

			if ( ! in_array( $obj->post_type, Webmention::get_supported_post_types(), true ) ) {
				return;
			}
		} elseif ( $obj instanceof \WP_Comment && '1' !== $obj->comment_approved ) {
			return;
		}

		$urls = array();

		if ( $obj instanceof \WP_Post && 'trash' !== $obj->post_status ) {
			// We scan posts' HTML for outgoing links.
			$html = apply_filters( 'the_content', $obj->post_content );
			$urls = static::find_outgoing_links( $html );
		} elseif ( ! empty( $obj->comment_parent ) ) {
			// Add the parent's, if any, Webmention source.
			$source = get_comment_meta( $obj->comment_parent, 'indieblocks_webmention_source', true );
			if ( ! empty( $source ) ) {
				$urls[] = $source;
			}
		}

		// Parse in targets that may have been there previously, but don't delete them, yet.
		$history = get_meta( $obj, '_indieblocks_webmention_history' );
		if ( ! empty( $history ) && is_array( $history ) ) {
			$urls = array_merge( $urls, array_column( $history, 'target' ) );
		}

		$urls = array_unique( $urls ); // For `array_search()` to work more reliably.

		if ( ! empty( $obj->comment_post_ID ) ) {
			// Prevent direct replies mentioning the post they're ... already
			// replying to. This should still allow mentions being sent to the
			// site itself, without sending one for each and every comment.
			$key = array_search( get_permalink( $obj->comment_post_ID ), $urls, true );

			if ( false !== $key ) {
				unset( $urls[ $key ] );
			}
		}

		if ( empty( $urls ) ) {
			// Nothing to do. Bail.
			return;
		}

		$schedule = false;

		foreach ( $urls as $url ) {
			// Try to find a Webmention endpoint.
			$endpoint = static::webmention_discover_endpoint( $url );

			if ( empty( $endpoint ) && ! empty( $history ) && is_array( $history ) ) {
				// Could this be an "old" target, and, if so, don't we have a
				// known endpoint for it?
				$key = array_search( $url, array_column( $history, 'target' ), true );

				if ( false !== $key && ! empty( $history[ $key ]['endpoint'] ) ) {
					$endpoint = $history[ $key ]['endpoint'];
				}
			}

			if ( empty( $endpoint ) || false === wp_http_validate_url( $endpoint ) ) {
				// Skip.
				continue;
			}

			// Found at least one endpoint.
			$schedule = true;
			break; // No need to look up _all_ endpoints (even though they're normally cached) to determine whether to schedule mentions.
		}

		if ( $schedule ) {
			$options = get_options();

			if ( ! isset( $options['webmention_delay'] ) || intval( $options['webmention_delay'] ) > 0 ) {
				$delay = empty( $options['webmention_delay'] )
					? wp_rand( 0, 300 ) // As before.
					: (int) $options['webmention_delay'];

				// Schedule sending out the actual webmentions.
				if ( $obj instanceof \WP_Post ) {
					debug_log( "[IndieBlocks/Webmention] Scheduling webmention for post {$obj->ID}." );
				} else {
					debug_log( "[IndieBlocks/Webmention] Scheduling webmention for comment {$obj->comment_ID}." );
				}

				update_meta( $obj, '_indieblocks_webmention_status', 'scheduled' );
				wp_schedule_single_event( time() + $delay, 'indieblocks_webmention_send', array( $obj ) );
			} else {
				update_meta( $obj, '_indieblocks_webmention_status', 'scheduled' );

				// Send inline (although retries will be scheduled as always).
				static::send_webmention( $obj );
			}
		}
	}

	/**
	 * Attempts to send webmentions to Webmention-compatible URLs mentioned in
	 * a post.
	 *
	 * @param \WP_Post|\WP_Comment $obj Post or comment object.
	 */
	public static function send_webmention( $obj ) {
		if ( $obj instanceof \WP_Post ) {
			if ( ! in_array( $obj->post_status, array( 'publish', 'trash' ), true ) ) {
				// Only send on publish/trash.
				return;
			}

			if ( ! in_array( $obj->post_type, Webmention::get_supported_post_types(), true ) ) {
				// This post type doesn't support Webmention.
				debug_log( '[IndieBlocks/Webmention] Post ' . $obj->ID . ' is of an unsupported type.' );
				return;
			}
		} elseif ( '1' !== $obj->comment_approved ) {
			// Send mentions only for approved comments.
			return;
		}

		$urls = array();

		if ( $obj instanceof \WP_Post && 'trash' !== $obj->post_status ) {
			// We scan posts' HTML for outgoing links.
			$html = apply_filters( 'the_content', $obj->post_content );
			$urls = static::find_outgoing_links( $html );
		} elseif ( ! empty( $obj->comment_parent ) ) {
			// Add in the parent's, if any, Webmention source.
			$source = get_comment_meta( $obj->comment_parent, 'indieblocks_webmention_source', true );
			if ( ! empty( $source ) ) {
				$urls[] = $source;
			}
		}

		// Parse in (and then forget) targets that may have been there before.
		$history = get_meta( $obj, '_indieblocks_webmention_history' );
		delete_meta( $obj, '_indieblocks_webmention_history' );

		if ( ! empty( $history ) && is_array( $history ) ) {
			$urls = array_merge( $urls, array_column( $history, 'target' ) );
		}

		if ( empty( $urls ) ) {
			// One or more links must've been removed. Nothing to do. Bail.
			debug_log( '[IndieBlocks/Webmention] No outgoing URLs found.' );
			return;
		}

		$urls = array_unique( $urls );

		// Fetch whatever Webmention-related stats we've got for this post.
		$webmention = static::get_webmention_meta( $obj );

		if ( empty( $webmention ) || ! is_array( $webmention ) ) {
			// Ensure `$webmention` is an array.
			$webmention = array();
		}

		foreach ( $urls as $url ) {
			// Try to find a Webmention endpoint. If we're lucky, one was
			// cached previously.
			$endpoint = static::webmention_discover_endpoint( $url );

			if ( empty( $endpoint ) && ! empty( $history ) && is_array( $history ) ) {
				// Could this be an "old" target, and, if so, don't we have a
				// known endpoint for it?
				$key = array_search( $url, array_column( $history, 'target' ), true );

				if ( false !== $key && ! empty( $history[ $key ]['endpoint'] ) ) {
					$endpoint = $history[ $key ]['endpoint'];
				}
			}

			if ( empty( $endpoint ) || false === wp_http_validate_url( $endpoint ) ) {
				// Skip.
				debug_log( '[IndieBlocks/Webmention] Could not find a Webmention endpoint for target ' . esc_url_raw( $url ) . '.' );
				continue;
			}

			$hash = hash( 'sha256', esc_url_raw( $url ) );

			$webmention[ $hash ]['endpoint'] = esc_url_raw( $endpoint );
			$webmention[ $hash ]['target']   = esc_url_raw( $url );

			if ( ! empty( $webmention[ $hash ]['sent'] ) ) {
				// Succesfully sent before. Skip. Note that this complicates
				// resending after an update quite a bit. In a future version,
				// we could store a hash of the post content, too, and use that
				// to send webmentions on actual updates.
				debug_log( '[IndieBlocks/Webmention] Previously sent webmention for target ' . esc_url_raw( $url ) . '. Skipping.' );
				continue;
			}

			$retries = ! empty( $webmention[ $hash ]['retries'] )
				? (int) $webmention[ $hash ]['retries']
				: 0;

			if ( $retries >= 3 ) {
				// Stop here.
				debug_log( '[IndieBlocks/Webmention] Sending webmention to ' . esc_url_raw( $url ) . ' failed 3 times before. Not trying again.' );
				continue;
			}

			// @codingStandardsIgnoreStart
			// Look for a target URL fragment (and possible parent comment).
			// $fragment = wp_parse_url( $url, PHP_URL_FRAGMENT );
			// if ( ! empty( $fragment ) && preg_match( '~^comment-\d+$~', $fragment ) ) {
			// 	$url = add_query_arg(
			// 		array( 'replytocom' => str_replace( 'comment-', '', str_replace( 'comment-', '', $fragment ) ) ),
			// 		$url
			// 	);
			// }
			// @codingStandardsIgnoreEnd

			// Send the webmention.
			$response = remote_post(
				esc_url_raw( $endpoint ),
				false,
				array(
					'body' => array(
						'source' => $obj instanceof \WP_Post ? get_permalink( $obj->ID ) : get_comment_link( $obj->comment_ID ),
						'target' => $url,
					),
				)
			);

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 500 ) {
				// Something went wrong.
				if ( is_wp_error( $response ) ) {
					debug_log( '[IndieBlocks/Webmention] Error trying to send a webmention to ' . esc_url_raw( $endpoint ) . ': ' . $response->get_error_message() . '.' );
				}

				$webmention[ $hash ]['retries'] = $retries + 1;
				update_meta( $obj, '_indieblocks_webmention', $webmention );

				// Schedule a retry in 5 to 15 minutes.
				wp_schedule_single_event( time() + wp_rand( 300, 900 ), 'indieblocks_webmention_send', array( $obj ) );

				continue;
			}

			// Success! (Or rather, no immediate error.) Store timestamp.
			$webmention[ $hash ]['sent'] = current_time( 'mysql' );
			$webmention[ $hash ]['code'] = wp_remote_retrieve_response_code( $response );

			debug_log( '[IndieBlocks/Webmention] Sent webmention to ' . esc_url_raw( $endpoint ) . '. Response code: ' . wp_remote_retrieve_response_code( $response ) . '.' );
		}

		update_meta( $obj, '_indieblocks_webmention', $webmention );
		delete_meta( $obj, '_indieblocks_webmention_status' );
	}

	/**
	 * Finds outgoing URLs inside a given bit of HTML.
	 *
	 * @param  string $html The HTML.
	 * @return array        Array of URLs.
	 */
	public static function find_outgoing_links( $html ) {
		if ( empty( $html ) ) {
			return array();
		}

		$html = '<div>' . convert_encoding( $html ) . '</div>';

		libxml_use_internal_errors( true );

		$doc = new \DOMDocument();
		$doc->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$xpath = new \DOMXPath( $doc );
		$urls  = array();

		foreach ( $xpath->query( '//a/@href' ) as $result ) {
			$urls[] = $result->value;
		}

		return $urls;
	}

	/**
	 * Finds a Webmention endpoint for the given URL.
	 *
	 * @link https://github.com/pfefferle/wordpress-webmention/blob/master/includes/functions.php#L174
	 *
	 * @param  string $url URL to ping.
	 * @return string|null Endpoint URL, or nothing on failure.
	 */
	public static function webmention_discover_endpoint( $url ) {
		$endpoint = get_transient( 'indieblocks:webmention_endpoint:' . hash( 'sha256', esc_url_raw( $url ) ) );

		if ( ! empty( $endpoint ) ) {
			// We've previously established the endpoint for this web page.
			debug_log( '[IndieBlocks/Webmention] Found endpoint (' . esc_url_raw( $endpoint ) . ') for ' . esc_url_raw( $url ) . ' in cache.' );
			return $endpoint;
		}

		$parsed_url = wp_parse_url( $url );

		if ( ! isset( $parsed_url['host'] ) ) {
			// Not a URL. This should never happen.
			return null;
		}

		$response = wp_safe_remote_head(
			esc_url_raw( $url ),
			array(
				'timeout'             => 11,
				'limit_response_size' => 1048576,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		// Check link header.
		$links = wp_remote_retrieve_header( $response, 'link' );

		if ( ! empty( $links ) ) {
			foreach ( (array) $links as $link ) {
				if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention(\.org)?\/?[\"\']?/i', $link, $result ) ) {
					$endpoint = \WP_Http::make_absolute_url( $result[1], $url );

					// Cache for one hour.
					set_transient( 'indieblocks:webmention_endpoint:' . hash( 'sha256', esc_url_raw( $url ) ), $endpoint, 3600 );

					return $endpoint;
				}
			}
		}

		if ( preg_match( '~(image|audio|video|model)/~is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			// Not an (X)HTML, SGML, or XML document. No use going further.
			return null;
		}

		// Now do a GET since we're going to look in the HTML headers (and we're
		// sure its not a binary file).
		$response = remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$content = wp_remote_retrieve_body( $response );
		if ( empty( $content ) ) {
			return null;
		}

		$content = convert_encoding( $content );
		libxml_use_internal_errors( true );
		$doc = new \DOMDocument();
		$doc->loadHTML( $content );

		$xpath = new \DOMXPath( $doc );

		foreach ( $xpath->query( '(//link|//a)[contains(concat(" ", @rel, " "), " webmention ") or contains(@rel, "webmention.org")]/@href' ) as $result ) {
			$endpoint = \WP_Http::make_absolute_url( $result->value, $url );

			// Cache for one hour.
			set_transient( 'indieblocks:webmention_endpoint:' . hash( 'sha256', esc_url_raw( $url ) ), $endpoint, HOUR_IN_SECONDS );

			return $endpoint;
		}

		// Nothing found.
		return null;
	}

	/**
	 * Adds the Webmention panel to Gutenberg's document sidebar.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_scripts( $hook_suffix = null ) {
		if ( defined( 'OUTGOING_WEBMENTIONS' ) && ! OUTGOING_WEBMENTIONS ) {
			// Outgoing mentions disabled.
			return;
		}

		if ( 'enqueue_block_editor_assets' === current_action() && ! apply_filters( 'indieblocks_webmention_meta_box', false ) ) {
			$current_screen = get_current_screen();
			if ( isset( $current_screen->post_type ) && in_array( $current_screen->post_type, Webmention::get_supported_post_types(), true ) ) {
				wp_enqueue_script(
					'indieblocks-webmention',
					plugins_url( '/assets/webmention.js', dirname( __DIR__ ) ),
					array(
						'wp-element',
						'wp-components',
						'wp-i18n',
						'wp-data',
						'wp-core-data',
						'wp-plugins',
						'wp-edit-post',
					),
					Plugin::PLUGIN_VERSION,
					false
				);

				global $post;

				$args = array(
					'ajaxurl'       => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
					'show_meta_box' => ! empty( $post->ID ) && '' !== static::get_webmention_meta( $post )
						? '1'
						: '0',
				);

				if ( ! empty( $post->ID ) ) {
					$args['nonce'] = wp_create_nonce( 'indieblocks:resend-webmention:' . $post->ID );
				}

				wp_localize_script(
					'indieblocks-webmention',
					'indieblocks_webmention_obj',
					$args
				);
			}

			return;
		}

		if ( ( 'post-new.php' === $hook_suffix || 'post.php' === $hook_suffix ) && apply_filters( 'indieblocks_webmention_meta_box', false ) ) {
			global $post;

			if ( ! empty( $post->post_type ) && in_array( $post->post_type, Webmention::get_supported_post_types(), true ) ) {
				$include = true;
			}
		}

		if ( ! empty( $include ) || 'comment.php' === $hook_suffix ) {
			// Enqueue JS.
			wp_enqueue_script( 'indieblocks-webmention-legacy', plugins_url( '/assets/webmention-legacy.js', dirname( __DIR__ ) ), array( 'jquery' ), Plugin::PLUGIN_VERSION, false );
			wp_localize_script(
				'indieblocks-webmention-legacy',
				'indieblocks_webmention_legacy_obj',
				array(
					'message' => esc_attr__( 'Webmention scheduled.', 'indieblocks' ),
				)
			);
		}
	}

	/**
	 * In the REST API, adds webmention meta to post objects.
	 */
	public static function register_rest_field() {
		foreach ( Webmention::get_supported_post_types() as $post_type ) {
			register_rest_field(
				$post_type,
				'indieblocks_webmention',
				array(
					'get_callback'    => array( __CLASS__, 'get_meta' ),
					'update_callback' => null,
				)
			);
		}
	}

	/**
	 * Returns webmention metadata.
	 *
	 * Could be used as both a `register_rest_route()` and
	 * `register_rest_field()` callback.
	 *
	 * @param  \WP_REST_Request|array $request API request (parameters).
	 * @return array|string|\WP_Error          Response (or error).
	 */
	public static function get_meta( $request ) {
		if ( is_array( $request ) ) {
			// `register_rest_field()` callback.
			$post_id = $request['id'];
		} else {
			// `register_rest_route()` callback.
			$post_id = $request->get_param( 'post_id' );
		}

		if ( empty( $post_id ) || ! is_int( $post_id ) ) {
			return new \WP_Error( 'invalid_id', 'Invalid post ID.', array( 'status' => 400 ) );
		}

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		$meta = get_post_meta( (int) $post_id, '_indieblocks_webmention', true ); // Using `$post_id` rather than `$post`, hence `get_post_meta()`.

		if ( empty( $meta ) ) {
			$meta = get_post_meta( (int) $post_id, '_indieblocks_webmention_status', true ); // Can still be empty.
		}

		return $meta;
	}

	/**
	 * Adds meta box.
	 */
	public static function add_meta_box() {
		if ( defined( 'OUTGOING_WEBMENTIONS' ) && ! OUTGOING_WEBMENTIONS ) {
			// Outgoing mentions disabled.
			return;
		}

		if ( 'add_meta_boxes' === current_action() ) {
			// "Create/Edit Post" screen.
			global $post;

			if ( empty( $post->ID ) ) {
				return;
			}

			if ( '' === static::get_webmention_meta( $post ) ) {
				return;
			}

			// We may no longer need this, because if the `_indieblocks_webmention`
			// custom field is non-empty, we'll likely want to show it even if
			// the post type does not currently support webmentions.
			$supported_post_types = Webmention::get_supported_post_types();

			if ( empty( $supported_post_types ) ) {
				return;
			}

			$args = array();

			if ( ! apply_filters( 'indieblocks_webmention_meta_box', false ) ) {
				$args['__back_compat_meta_box'] = true; // Hide for Gutenberg post types.
			}

			// Add meta box, for those post types that are supported.
			add_meta_box(
				'indieblocks-webmention',
				__( 'Webmention', 'indieblocks' ),
				array( __CLASS__, 'render_meta_box' ),
				$supported_post_types,
				'normal',
				'default',
				$args
			);
		} elseif ( 'add_meta_boxes_comment' === current_action() ) {
			// "Edit Comment" screen.
			global $comment;

			if ( empty( $comment->comment_ID ) ) {
				return;
			}

			if ( '' === static::get_webmention_meta( $comment ) ) {
				return;
			}

			add_meta_box(
				'indieblocks-webmention',
				__( 'Webmention', 'indieblocks' ),
				array( __CLASS__, 'render_meta_box' ),
				'comment',
				'normal',
				'default'
			);
		}
	}

	/**
	 * Renders meta box.
	 *
	 * @param \WP_Post|\WP_Comment $obj Post or comment being edited.
	 */
	public static function render_meta_box( $obj ) {
		if ( $obj instanceof \WP_Post ) {
			$type = 'post';
		} elseif ( $obj instanceof \WP_Comment ) {
			$type = 'comment';
		} else {
			return;
		}

		$webmention = static::get_webmention_meta( $obj );

		if ( is_array( $webmention ) ) :
			?>
			<div style="display: flex; gap: 1em; align-items: start; justify-content: space-between;">
				<p style="margin: 6px 0;">
					<?php
					$i = 0;

					foreach ( $webmention as $data ) {
						if ( ! empty( $data['endpoint'] ) ) {
							if ( $i > 0 ) {
								echo '<br />';
							}

							if ( ! empty( $data['sent'] ) ) {
								/* translators: 1: Webmention endpoint 2: Date sent */
								printf( __( 'Sent to %1$s on %2$s. Response code: %3$d.', 'indieblocks' ), '<a href="' . $data['endpoint'] . '" target="_blank" rel="noopener noreferrer" title="' . $data['target'] . '">' . $data['endpoint'] . '</a>', date( __( 'M j, Y \a\t H:i', 'indieblocks' ), strtotime( $data['sent'] ) ), $data['code'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.DateTime.RestrictedFunctions.date_date
							} elseif ( ! empty( $data['retries'] ) && $data['retries'] >= 3 ) {
								/* translators: Webmention endpoint */
								printf( __( 'Could not send webmention to %s.', 'indieblocks' ), '<a href="' . $data['endpoint'] . '" target="_blank" rel="noopener noreferrer" title="' . $data['target'] . '">' . $data['endpoint'] . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} elseif ( ! empty( $data['retries'] ) ) {
								/* translators: Webmention endpoint */
								printf( __( 'Could not send webmention to %s. Trying again soon.', 'indieblocks' ), '<a href="' . $data['endpoint'] . '" target="_blank" rel="noopener noreferrer" title="' . $data['target'] . '">' . $data['endpoint'] . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
						}

						++$i;
					}
					?>
				</p>

				<button type="button" class="button indieblocks-resend-webmention" data-type="<?php echo esc_attr( $type ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'indieblocks:resend-webmention:' . ( 'post' === $type ? $obj->ID : $obj->comment_ID ) ) ); ?>">
					<?php esc_html_e( 'Resend', 'indieblocks' ); ?>
				</button>
			</div>
		<?php elseif ( ! empty( $webmention ) && 'scheduled' === $webmention ) : // Unsure why `wp_next_scheduled()` won't work. ?>
			<div style="display: flex; gap: 1em; align-items: start; justify-content: space-between;">
				<p style="margin: 6px 0;"><?php esc_html_e( 'Webmention scheduled.', 'indieblocks' ); ?></p>

				<button type="button" class="button indieblocks-resend-webmention" data-type="<?php echo esc_attr( $type ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'indieblocks:resend-webmention:' . ( 'post' === $type ? $obj->ID : $obj->comment_ID ) ) ); ?>">
					<?php esc_html_e( 'Resend', 'indieblocks' ); ?>
				</button>
			</div>
		<?php else : ?>
			<p style="margin: 6px 0;"><?php esc_html_e( 'No endpoints found.', 'indieblocks' ); ?></p>
			<?php
		endif;
	}

	/**
	 * Returns Webmention metadata, like where and when mentions were sent.
	 *
	 * @param  \WP_Post|\WP_Comment $obj Post or comment being edited.
	 * @return array|string              Webmention metadata.
	 */
	protected static function get_webmention_meta( $obj ) {
		$webmention = get_meta( $obj, '_indieblocks_webmention' );

		if ( empty( $webmention ) ) {
			$webmention = get_meta( $obj, '_indieblocks_webmention_status' );
		}

		return $webmention;
	}

	/**
	 * Reschedules a previously sent webmention.
	 *
	 * Should only ever be called through AJAX.
	 */
	public static function reschedule_webmention() {
		if ( ! isset( $_POST['_wp_nonce'] ) ) {
			status_header( 400 ); // Guess this doesn't work ...
			esc_html_e( 'Missing nonce.', 'indieblocks' );
			wp_die();
		}

		if ( ! isset( $_POST['type'] ) ) {
			status_header( 400 );
			esc_html_e( 'Missing webmention type.', 'indieblocks' );
			wp_die();
		}

		if ( ! isset( $_POST['obj_id'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wp_nonce'] ), 'indieblocks:resend-webmention:' . intval( $_POST['obj_id'] ) ) ) {
			status_header( 400 );
			esc_html_e( 'Invalid nonce.', 'indieblocks' );
			wp_die();
		}

		if ( ! ctype_digit( $_POST['obj_id'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			status_header( 400 );
			esc_html_e( 'Invalid object ID.', 'indieblocks' );
			wp_die();
		}

		$obj_id = (int) $_POST['obj_id'];

		if ( 'post' === $_POST['type'] ) {
			if ( ! current_user_can( 'edit_post', $obj_id ) ) {
				status_header( 400 );
				esc_html_e( 'Insufficient rights.', 'indieblocks' );
				wp_die();
			}

			$post    = get_post( $obj_id );
			$history = static::get_webmention_meta( $post );

			if ( ! empty( $history ) && is_array( $history ) ) {
				add_post_meta( $obj_id, '_indieblocks_webmention_history', $history, true );
				delete_post_meta( $obj_id, '_indieblocks_webmention' );
			}

			echo 'Rescheduling mentions for post ' . intval( $obj_id ) . '.';
			static::schedule_webmention( $obj_id, get_post( $obj_id ) );
		} elseif ( 'comment' === $_POST['type'] ) {
			if ( ! current_user_can( 'edit_comment', $obj_id ) ) {
				status_header( 400 );
				esc_html_e( 'Insufficient rights.', 'indieblocks' );
				wp_die();
			}

			$comment = get_comment( $obj_id );
			$history = static::get_webmention_meta( $comment );

			if ( '' !== $history && is_array( $history ) ) {
				add_comment_meta( $obj_id, '_indieblocks_webmention_history', $history, true );
				delete_comment_meta( $obj_id, '_indieblocks_webmention' );
			}

			echo 'Rescheduling mentions for comment ' . intval( $obj_id ) . '.';
			static::schedule_webmention( $obj_id, get_comment( $obj_id ) );
		}

		wp_die();
	}
}
