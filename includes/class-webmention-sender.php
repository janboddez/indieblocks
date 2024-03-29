<?php
/**
 * Webmention sender.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Webmention sender.
 */
class Webmention_Sender {
	/**
	 * Schedules the sending of webmentions, if enabled.
	 *
	 * Scans for outgoing links, but leaves fetching Webmention endpoints to the
	 * callback function queued in the background.
	 *
	 * @param int                  $obj_id     Post or comment ID.
	 * @param \WP_Post|\WP_Comment $obj        Post or comment object.
	 * @param mixed                $deprecated Used to be the post object, now deprecated.
	 */
	public static function schedule_webmention( $obj_id, $obj = null, $deprecated = null ) {
		if ( null !== $deprecated ) {
			_deprecated_argument( 'post', '0.10.0', 'Passing a third argument to `\IndieBlocks\Webmention_Sender::schedule_webmention()` is deprecated.' );
		}

		if ( defined( 'OUTGOING_WEBMENTIONS' ) && ! OUTGOING_WEBMENTIONS ) {
			// Disabled.
			return;
		}

		if ( 'comment_post' === current_filter() ) {
			$obj = get_comment( $obj_id );
		}

		if ( $obj instanceof \WP_Post ) {
			if ( 'publish' !== $obj->post_status ) {
				// Do not send webmention on delete/unpublish, for now.
				return;
			}

			/* @see https://github.com/WordPress/gutenberg/issues/15094#issuecomment-1021288811. */
			if ( ! empty( $_REQUEST['meta-box-loader'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// Avoid scheduling (or posting, in case of no delay) webmentions
				// more than once.
				return;
			}

			if ( wp_is_post_revision( $obj->ID ) || wp_is_post_autosave( $obj->ID ) ) {
				return;
			}

			if ( ! in_array( $obj->post_type, Webmention::get_supported_post_types(), true ) ) {
				return;
			}
		} elseif ( '1' !== $obj->comment_approved ) {
			// Do not send webmention on delete/unpublish, for now.
			return;
		}

		$urls = array();

		if ( $obj instanceof \WP_Post ) {
			// Fetch our post's HTML.
			$html = apply_filters( 'the_content', $obj->post_content );

			// Scan it for outgoing links.
			$urls = static::find_outgoing_links( $html );
		} elseif ( ! empty( $obj->comment_parent ) ) {
			// Add in the parent's, if any, Webmention source.
			$source = get_comment_meta( $obj->comment_parent, 'indieblocks_webmention_source', true );
			if ( ! empty( $source ) ) {
				$urls[] = $source;
			}
		}

		// Parse in targets that may have been there previously, but don't
		// delete them, yet.
		$history = get_meta( $obj, '_indieblocks_webmention_history' );

		if ( ! empty( $history ) && is_array( $history ) ) {
			$urls = array_merge( $urls, $history );
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

			if ( empty( $endpoint ) || false === wp_http_validate_url( $endpoint ) ) {
				// Skip.
				continue;
			}

			// Found an endpoint.
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

				add_meta( $obj, '_indieblocks_webmention', 'scheduled' );
				wp_schedule_single_event( time() + $delay, 'indieblocks_webmention_send', array( $obj ) );
			} else {
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
			if ( 'publish' !== $obj->post_status ) {
				// Do not send webmention on delete/unpublish, for now.
				debug_log( '[IndieBlocks/Webmention] Post ' . $obj->ID . ' is not published.' );
				return;
			}

			if ( ! in_array( $obj->post_type, Webmention::get_supported_post_types(), true ) ) {
				// This post type doesn't support Webmention.
				debug_log( '[IndieBlocks/Webmention] Post ' . $obj->ID . ' is of an unsupported type.' );
				return;
			}
		} elseif ( '1' !== $obj->comment_approved ) {
			debug_log( '[IndieBlocks/Webmention] Comment ' . $obj->comment_ID . " isn't approved." );
			return;
		}

		$urls = array();

		if ( $obj instanceof \WP_Post ) {
			// Fetch our post's HTML.
			$html = apply_filters( 'the_content', $obj->post_content );

			// Scan it for outgoing links.
			$urls = static::find_outgoing_links( $html );
		} elseif ( ! empty( $obj->comment_parent ) ) {
			// Add in the parent's, if any, Webmention source.
			$source = get_comment_meta( $obj->comment_parent, 'indieblocks_webmention_source', true );
			if ( ! empty( $source ) ) {
				$urls[] = $source;
			}
		}

		// Parse in (_and_ then forget) targets that may have been there before.
		// This also means that "historic" targets are excluded from retries!
		// Note that we _also_ retarget pages that threw an error or we
		// otherwise failed to reach previously. Both are probably acceptable.
		$history = get_meta( $obj, '_indieblocks_webmention_history' );
		delete_meta( $obj, '_indieblocks_webmention_history' );

		if ( ! empty( $history ) && is_array( $history ) ) {
			$urls = array_merge( $urls, $history );
		}

		if ( empty( $urls ) ) {
			// One or more links must've been removed. Nothing to do. Bail.
			debug_log( '[IndieBlocks/Webmention] No outgoing URLs found.' );
			return;
		}

		$urls = array_unique( $urls );

		// Fetch whatever Webmention-related stats we've got for this post.
		$webmention = get_meta( $obj, '_indieblocks_webmention' );

		if ( empty( $webmention ) || ! is_array( $webmention ) ) {
			// Ensure `$webmention` is an array.
			$webmention = array();
		}

		foreach ( $urls as $url ) {
			// Try to find a Webmention endpoint. If we're lucky, one was
			// cached previously.
			$endpoint = static::webmention_discover_endpoint( $url );

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
				debug_log( $response );

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

		$response = wp_remote_head(
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
	 * Adds meta box.
	 */
	public static function add_meta_box() {
		if ( defined( 'OUTGOING_WEBMENTIONS' ) && ! OUTGOING_WEBMENTIONS ) {
			// Disabled.
			return;
		}

		if ( 'add_meta_boxes' === current_action() ) {
			global $post;

			if ( empty( $post->ID ) ) {
				return;
			}

			if ( '' === get_post_meta( $post->ID, '_indieblocks_webmention', true ) ) {
				return;
			}

			// We may no longer need this, because if the `_indieblocks_webmention`
			// custom field is non-empty, we'll likely want to show it.
			$supported_post_types = Webmention::get_supported_post_types();

			if ( empty( $supported_post_types ) ) {
				return;
			}

			// Add meta box, for those post types that are supported.
			add_meta_box(
				'indieblocks-webmention',
				__( 'Webmention', 'indieblocks' ),
				array( __CLASS__, 'render_meta_box' ),
				$supported_post_types,
				'normal',
				'default'
			);
		} elseif ( 'add_meta_boxes_comment' === current_action() ) {
			global $comment;

			if ( empty( $comment->comment_ID ) ) {
				return;
			}

			if ( '' === get_comment_meta( $comment->comment_ID, '_indieblocks_webmention', true ) ) {
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
			// Webmention data.
			$webmention = get_post_meta( $obj->ID, '_indieblocks_webmention', true );
			$type       = 'post';
		} elseif ( $obj instanceof \WP_Comment ) {
			$webmention = get_comment_meta( $obj->comment_ID, '_indieblocks_webmention', true );
			$type       = 'comment';
		}

		if ( ! empty( $webmention ) && is_array( $webmention ) ) :
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
	 * Reschedules a previously sent webmention.
	 *
	 * Should only ever be called through AJAX.
	 */
	public static function reschedule_webmention() {
		if ( ! isset( $_POST['_wp_nonce'] ) ) {
			status_header( 400 );
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

			$history = get_post_meta( $obj_id, '_indieblocks_webmention', true );

			if ( '' !== $history && is_array( $history ) ) {
				add_post_meta( $obj_id, '_indieblocks_webmention_history', array_column( $history, 'target' ), true );
				delete_post_meta( $obj_id, '_indieblocks_webmention' );
			}

			static::schedule_webmention( $obj_id, get_post( $obj_id ) );
		} elseif ( 'comment' === $_POST['type'] ) {
			if ( ! current_user_can( 'edit_comment', $obj_id ) ) {
				status_header( 400 );
				esc_html_e( 'Insufficient rights.', 'indieblocks' );
				wp_die();
			}

			$history = get_comment_meta( $obj_id, '_indieblocks_webmention', true );

			if ( '' !== $history && is_array( $history ) ) {
				add_comment_meta( $obj_id, '_indieblocks_webmention_history', array_column( $history, 'target' ), true );
				delete_comment_meta( $obj_id, '_indieblocks_webmention' );
			}

			static::schedule_webmention( $obj_id, get_comment( $obj_id ) );
		}

		wp_die();
	}
}
