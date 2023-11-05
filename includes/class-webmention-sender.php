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
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function schedule_webmention( $new_status, $old_status, $post ) {
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		/* @see https://github.com/WordPress/gutenberg/issues/15094#issuecomment-1021288811. */
		if ( ! empty( $_REQUEST['meta-box-loader'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Avoid scheduling (or posting, in case of no delay) webmentions
			// more than once.
			return;
		}

		if ( defined( 'OUTGOING_WEBMENTIONS' ) && ! OUTGOING_WEBMENTIONS ) {
			// Disabled.
			return;
		}

		if ( 'publish' !== $new_status ) {
			// Do not send webmention on delete/unpublish, for now.
			return;
		}

		if ( ! in_array( $post->post_type, Webmention::get_supported_post_types(), true ) ) {
			return;
		}

		// Fetch our post's HTML.
		$html = apply_filters( 'the_content', $post->post_content );

		// Scan it for outgoing links.
		$urls = static::find_outgoing_links( $html );

		// Parse in targets that may have been there previously, but don't delete
		// them, yet.
		$history = get_post_meta( $post->ID, '_indieblocks_webmention_history', true );
		if ( ! empty( $history ) && is_array( $history ) ) {
			$urls = array_unique( array_merge( $urls, $history ) );
		}

		if ( empty( $urls ) || ! is_array( $urls ) ) {
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
				error_log( "[Indieblocks/Webmention] Scheduling webmention for post {$post->ID}." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				wp_schedule_single_event( time() + $delay, 'indieblocks_webmention_send', array( $post->ID ) );

				add_post_meta( $post->ID, '_indieblocks_webmention', 'scheduled', true ); // Does not affect existing values.
			} else {
				// Send inline (although retries will be scheduled as always).
				static::send_webmention( $post->ID );
			}
		}
	}

	/**
	 * Attempts to send webmentions to Webmention-compatible URLs mentioned in
	 * a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function send_webmention( $post_id ) {
		$post = get_post( $post_id );

		if ( 'publish' !== $post->post_status ) {
			// Do not send webmentions on delete/unpublish, for now.
			return;
		}

		if ( ! in_array( $post->post_type, Webmention::get_supported_post_types(), true ) ) {
			// This post type doesn't support Webmention.
			return;
		}

		// Fetch our post's HTML.
		$html = apply_filters( 'the_content', $post->post_content );

		// Scan it for outgoing links, again, as things might have changed.
		$urls = static::find_outgoing_links( $html );

		// Parse in (and then forget) targets that may have been there before.
		// This also means that "historic" targets are excluded from retries!
		// Note that we _also_ retarget pages that threw an error or we
		// otherwise failed to reach previously. Both are probably acceptable.
		$history = get_post_meta( $post->ID, '_indieblocks_webmention_history', true );
		delete_post_meta( $post->ID, '_indieblocks_webmention_history' );
		if ( ! empty( $history ) && is_array( $history ) ) {
			$urls = array_unique( array_merge( $urls, $history ) );
		}

		if ( empty( $urls ) || ! is_array( $urls ) ) {
			// One or more links must've been removed. Nothing to do. Bail.
			return;
		}

		// Fetch whatever Webmention-related stats we've got for this post.
		$webmention = get_post_meta( $post->ID, '_indieblocks_webmention', true );

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
				error_log( '[Indieblocks/Webmention] Previously sent webmention for target ' . esc_url_raw( $url ) . '. Skipping.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				continue;
			}

			$retries = ! empty( $webmention[ $hash ]['retries'] )
				? (int) $webmention[ $hash ]['retries']
				: 0;

			if ( $retries >= 3 ) {
				// Stop here.
				error_log( '[Indieblocks/Webmention] Sending webmention to ' . esc_url_raw( $url ) . ' failed 3 times before. Not trying again.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				continue;
			}

			// Send the webmention.
			$response = remote_post(
				esc_url_raw( $endpoint ),
				false,
				array(
					'body' => array(
						'source' => get_permalink( $post->ID ),
						'target' => $url,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				// Something went wrong.
				error_log( '[Indieblocks/Webmention] Error trying to send a webmention to ' . esc_url_raw( $endpoint ) . ': ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				$webmention[ $hash ]['retries'] = $retries + 1;
				update_post_meta( $post->ID, '_indieblocks_webmention', $webmention );

				// Schedule a retry in 5 to 15 minutes.
				wp_schedule_single_event( time() + wp_rand( 300, 900 ), 'indieblocks_webmention_send', array( $post->ID ) );

				continue;
			} elseif ( wp_remote_retrieve_response_code( $response ) >= 500 ) {
				// Something went wrong.
				error_log( '[Indieblocks/Webmention] Error trying to send a webmention to ' . esc_url_raw( $endpoint ) . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$webmention[ $hash ]['retries'] = $retries + 1;
				update_post_meta( $post->ID, '_indieblocks_webmention', $webmention );

				// Schedule a retry in 5 to 15 minutes.
				wp_schedule_single_event( time() + wp_rand( 300, 900 ), 'indieblocks_webmention_send', array( $post->ID ) );

				continue;
			}

			// Success! (Or rather, no immediate error.) Store timestamp.
			$webmention[ $hash ]['sent'] = current_time( 'mysql' );
			$webmention[ $hash ]['code'] = wp_remote_retrieve_response_code( $response );

			error_log( '[Indieblocks/Webmention] Sent webmention to ' . esc_url_raw( $endpoint ) . '. Response code: ' . wp_remote_retrieve_response_code( $response ) . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		update_post_meta( $post->ID, '_indieblocks_webmention', $webmention );
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

		$html = '<div>' . mb_convert_encoding( $html, 'HTML-ENTITIES', mb_detect_encoding( $html ) ) . '</div>';

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
			error_log( '[Indieblocks/Webmention] Found endpoint (' . esc_url_raw( $endpoint ) . ') for ' . esc_url_raw( $url ) . ' in cache.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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

		$contents = wp_remote_retrieve_body( $response );

		if ( empty( $contents ) ) {
			return null;
		}

		$contents = mb_convert_encoding( $contents, 'HTML-ENTITIES', mb_detect_encoding( $contents ) );

		libxml_use_internal_errors( true );

		$doc = new \DOMDocument();
		$doc->loadHTML( $contents );

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
	}

	/**
	 * Renders meta box.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( $post ) {
		// Webmention data.
		$webmention = get_post_meta( $post->ID, '_indieblocks_webmention', true );

		if ( ! empty( $webmention ) && is_array( $webmention ) ) :
			?>
			<div style="display: flex; gap: 1em; align-items: start; justify-content: space-between;">
				<p style="margin: 0 0 6px;">
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

				<button type="button" class="button indieblocks-resend-webmention" data-nonce="<?php echo esc_attr( wp_create_nonce( 'indieblocks:resend-webmention:' . $post->ID ) ); ?>">
					<?php esc_html_e( 'Resend', 'indieblocks' ); ?>
				</button>
			</div>
		<?php elseif ( ! empty( $webmention ) && 'scheduled' === $webmention ) : // Unsure why `wp_next_scheduled()` won't work. ?>
			<p style="margin: 0 0 6px;"><?php esc_html_e( 'Webmention scheduled.', 'indieblocks' ); ?></p>
		<?php else : ?>
			<p style="margin: 0 0 6px;"><?php esc_html_e( 'No endpoints found.', 'indieblocks' ); ?></p>
			<?php
		endif;
	}

	/**
	 * Reschedules a previously sent webmention.
	 *
	 * Should only ever be called through AJAX.
	 */
	public static function reschedule_webmention() {
		if ( ! isset( $_POST['_wp_nonce'] ) || ! isset( $_POST['post_id'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wp_nonce'] ), 'indieblocks:resend-webmention:' . intval( $_POST['post_id'] ) ) ) {
			status_header( 400 );
			esc_html_e( 'Missing or invalid nonce.', 'indieblocks' );
			wp_die();
		}

		if ( ! ctype_digit( $_POST['post_id'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			status_header( 400 );
			esc_html_e( 'Invalid post ID.', 'indieblocks' );
			wp_die();
		}

		$post_id = (int) $_POST['post_id'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			status_header( 400 );
			esc_html_e( 'Insufficient rights.', 'indieblocks' );
			wp_die();
		}

		$history = get_post_meta( $post_id, '_indieblocks_webmention', true );

		if ( '' !== $history && is_array( $history ) ) {
			add_post_meta( $post_id, '_indieblocks_webmention_history', array_column( $history, 'target' ), true );
			delete_post_meta( $post_id, '_indieblocks_webmention' );
		}

		$post = get_post( $post_id );
		static::schedule_webmention( $post->post_status, $post->post_status, $post );

		wp_die();
	}
}
