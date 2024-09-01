<?php

namespace IndieBlocks\Webmention;

use function IndieBlocks\debug_log;
use function IndieBlocks\remote_get;
use function IndieBlocks\webmentions_open;

/**
 * Webmention receiver.
 */
class Webmention_Receiver {
	/**
	 * Registers hook callbacks.
	 */
	public static function register() {
		// Register Webmention endpoint.
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoint' ) );

		// Publicize Webmention endpoint.
		add_action( 'wp_head', array( __CLASS__, 'webmention_link' ) );
		add_action( 'template_redirect', array( __CLASS__, 'webmention_link' ) );

		// Process stored mentions.
		add_action( 'indieblocks_process_webmentions', array( __CLASS__, 'process_webmentions' ) );
		add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allowed_html' ), 10, 2 );

		// If applicable, add a comment meta box.
		add_action( 'add_meta_boxes_comment', array( __CLASS__, 'add_meta_box' ) );

		// Support avatar deletion.
		add_action( 'wp_ajax_indieblocks_delete_avatar', array( __CLASS__, 'delete_avatar' ) );
	}

	/**
	 * Registers a new REST API route.
	 */
	public static function register_api_endpoint() {
		register_rest_route(
			'indieblocks/v1',
			'/webmention',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'store_webmention' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Stores incoming webmentions and that's about it.
	 *
	 * @param  \WP_REST_Request $request API request.
	 * @return \WP_REST_Response         API response.
	 */
	public static function store_webmention( $request ) {
		debug_log( '[IndieBlocks/Webmention] Got request: ' . wp_json_encode( $request->get_params() ) );

		// Verify source nor target are invalid URLs.
		if ( empty( $request['source'] ) || ! wp_http_validate_url( $request['source'] ) || empty( $request['target'] ) || ! wp_http_validate_url( $request['target'] ) ) {
			debug_log( '[IndieBlocks/Webmention] Invalid source or target.' );
			return new \WP_Error( 'invalid_request', 'Invalid source or target', array( 'status' => 400 ) );
		}

		// Get the target post's slug, sans permalink front.
		$slug = trim( basename( wp_parse_url( $request['target'], PHP_URL_PATH ) ), '/' );

		// We don't currently differentiate between "incoming" and "outgoing" post types.
		$supported_post_types = Webmention::get_supported_post_types();

		// Fetch the post.
		$post = get_page_by_path( $slug, OBJECT, $supported_post_types );
		$post = apply_filters( 'indieblocks_webmention_post', $post, $request['target'], $supported_post_types );

		if ( empty( $post ) || 'publish' !== $post->post_status ) {
			// Not found.
			debug_log( '[IndieBlocks/Webmention] Target post not found.' );
			return new \WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
		}

		// Set sender's IP address.
		$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', apply_filters( 'indieblocks_webmention_sender_ip', $ip, $request ) );

		global $wpdb;

		// Insert webmention into database.
		$num_rows = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'indieblocks_webmentions',
			array(
				'source'     => esc_url_raw( $request['source'] ),
				'target'     => esc_url_raw( $request['target'] ),
				'post_id'    => $post->ID,
				'ip'         => $ip,
				'status'     => 'draft',
				'created_at' => current_time( 'mysql' ),
			)
		);

		if ( false !== $num_rows ) {
			debug_log( '[IndieBlocks/Webmention] Stored mention for later processing.' );

			// Create an empty REST response and add an 'Accepted' status code.
			$response = new \WP_REST_Response( array() );
			$response->set_status( 202 );

			return $response;
		}

		debug_log( '[IndieBlocks/Webmention] Could not insert mention into database.' );
		return new \WP_Error( 'server_error', 'Internal server error', array( 'status' => 500 ) );
	}

	/**
	 * Processes queued webmentions. Typically triggered by WP Cron.
	 */
	public static function process_webmentions() {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'indieblocks_webmentions';
		$webmentions = $wpdb->get_results( "SELECT id, source, target, post_id, ip, created_at FROM $table_name WHERE status = 'draft' LIMIT 5" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $webmentions ) || ! is_array( $webmentions ) ) {
			// Empty queue.
			return;
		}

		foreach ( $webmentions as $webmention ) {
			$update = false;

			// Look for an existing comment with this source URL, see if it
			// needs updated or deleted.
			$query = new \WP_Comment_Query(
				array(
					'comment_post_ID' => $webmention->post_id,
					'orderby'         => 'comment_ID',
					'order'           => 'DESC',
					'fields'          => 'ids',
					'limit'           => 1,
					'meta_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						'relation' => 'AND',
						array(
							'key'     => 'indieblocks_webmention_source',
							'compare' => 'EXISTS',
						),
						array(
							'key'     => 'indieblocks_webmention_source',
							'compare' => '=',
							'value'   => esc_url_raw( $webmention->source ),
						),
						array(
							'key'     => 'indieblocks_webmention_target',
							'compare' => 'EXISTS',
						),
						array(
							'key'     => 'indieblocks_webmention_target',
							'compare' => '=',
							'value'   => esc_url_raw( $webmention->target ),
						),
					),
				)
			);

			$comments = $query->comments;

			// Fetch source HTML.
			debug_log( "[IndieBlocks/Webmention] Fetching the page at {$webmention->source}." );
			$response = remote_get( $webmention->source );

			if ( is_wp_error( $response ) ) {
				// Something went wrong.
				debug_log( "[IndieBlocks/Webmention] Something went wrong fetching the page at {$webmention->source}: " . $response->get_error_message() . '.' );
				continue;
			}

			if ( ! empty( $comments ) && is_array( $comments ) ) {
				// Found an existing comment. Treat mention as update or delete.
				$update     = true;
				$comment_id = reset( $comments );

				debug_log( "[IndieBlocks/Webmention] Found an existing comment ({$comment_id}) for this mention." );

				if ( in_array( wp_remote_retrieve_response_code( $response ), array( 404, 410 ), true ) ) {
					// Delete.
					if ( wp_delete_comment( $comment_id ) ) {
						$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
							$table_name,
							array(
								'status'      => 'deleted',
								'modified_at' => current_time( 'mysql' ),
							),
							array( 'id' => $webmention->id ),
							null,
							array( '%d' )
						);
					} else {
						debug_log( "[IndieBlocks/Webmention] Something went wrong deleting comment {$comment_id} for source URL (" . esc_url_raw( $webmention->source ) . '.' );
					}

					continue;
				}
			} elseif ( ! webmentions_open( $webmention->post_id ) ) {
				// New mention, while comments are closed.
				debug_log( "[IndieBlocks/Webmention] Webmentions closed for the post with ID {$webmention->post_id}." );
				return;
			}

			// Continue onward. At this point, we're dealing with either an
			// update, or a brand-new mention.
			$html   = wp_remote_retrieve_body( $response );
			$target = ! empty( $webmention->target )
				? $webmention->target
				: get_permalink( $webmention->post_id );

			$target   = remove_query_arg( 'replytocom', $target ); // Just in case.
			$fragment = strval( wp_parse_url( $target, PHP_URL_FRAGMENT ) );

			if ( false === stripos( $html, preg_replace( "~#$fragment$~", '', $target ) ) ) { // Strip fragment when comparing.
				debug_log( "[IndieBlocks/Webmention] The page at {$webmention->source} does not seem to mention our target URL ($target)." );

				// Target URL not (or no longer) mentioned by source. Mark webmention as processed.
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$table_name,
					array(
						'status'      => 'invalid',
						'modified_at' => current_time( 'mysql' ),
					),
					array( 'id' => $webmention->id ),
					null,
					array( '%d' )
				);

				// Skip to next webmention.
				continue;
			}

			debug_log( "[IndieBlocks/Webmention] The page at {$webmention->source} seems to mention our target URL ($target); creating new comment." );

			// Grab source domain.
			$host = wp_parse_url( $webmention->source, PHP_URL_HOST );

			// Look for a possible parent comment. We could _also_ look at the
			// `replytocom` query argument, but ... I'm not sure we should?
			if ( ! empty( $fragment ) && preg_match( '~^comment-\d+$~', $fragment ) ) {
				$parent = get_comment( str_replace( 'comment-', '', str_replace( 'comment-', '', $fragment ) ) );
			}

			// Some defaults.
			$commentdata = array(
				'comment_post_ID'      => apply_filters( 'indieblocks_webmention_post_id', $webmention->post_id ),
				'comment_author'       => $host,
				'comment_author_email' => '', // Stop setting this, as it might (?) auto-approve certain (or all?) mentions, depending on the Discussion settings.
				'comment_author_url'   => esc_url_raw( wp_parse_url( $webmention->source, PHP_URL_SCHEME ) . '://' . $host ),
				'comment_author_IP'    => $webmention->ip,
				'comment_content'      => __( '&hellip; commented on this.', 'indieblocks' ),
				'comment_parent'       => ! empty( $parent ) && $webmention->post_id === $parent->comment_post_ID ? $parent->comment_ID : 0,
				'user_id'              => 0,
				'comment_date'         => $webmention->created_at,
				'comment_date_gmt'     => get_gmt_from_date( $webmention->created_at ),
				'comment_type'         => '', // We don't currently set this to, e.g., `webmention`, as doing so affects how reactions are displayed insice WP Admin.
				'comment_meta'         => array(
					'indieblocks_webmention_source' => esc_url_raw( $webmention->source ),
					'indieblocks_webmention_target' => esc_url_raw( $webmention->target ),
				),
			);

			// Search source for supported microformats, and update
			// `$commentdata` accordingly.
			Webmention_Parser::parse_microformats( $commentdata, $html, $webmention->source, $target );

			// Disable comment flooding check.
			remove_action( 'check_comment_flood', 'check_comment_flood_db' );

			// Update or insert comment.
			if ( $update ) {
				$commentdata['comment_ID'] = $comment_id;

				$comment  = get_comment( $comment_id );
				$original = preg_replace( '~\s+~', ' ', wp_strip_all_tags( (string) $comment->comment_text ) );

				$commentdata['comment_approved'] = '0';

				if ( ! empty( $commentdata['comment_content'] ) && preg_replace( '~\s+~', ' ', wp_strip_all_tags( (string) $comment->comment_text ) ) === $original ) {
					$commentdata['comment_approved'] = '1';
				}

				debug_log( "[IndieBlocks/Webmention] Updating comment {$comment_id}." );
				$result = wp_update_comment( $commentdata, true );
			} else {
				debug_log( '[IndieBlocks/Webmention] Creating new comment.' );
				$result = wp_new_comment( $commentdata, true );
			}

			$status = $update ? 'updated' : 'created';

			if ( is_wp_error( $result ) ) {
				// For troubleshooting.
				debug_log( $result );

				if ( in_array( 'comment_duplicate', $result->get_error_codes(), true ) ) {
					// Log if deemed duplicate. Could come in useful if we ever
					// wanna support "updated" webmentions.
					$status = 'duplicate';
				}
			}

			// Mark webmention as processed.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_name,
				array(
					'status'      => $status,
					'modified_at' => current_time( 'mysql' ),
				),
				array( 'id' => $webmention->id ),
				null,
				array( '%d' )
			);

			debug_log( "[IndieBlocks/Webmention] And we're done parsing this particular mention." );
		}
	}

	/**
	 * Adds comment meta box.
	 */
	public static function add_meta_box() {
		global $comment;

		if ( empty( $comment->comment_ID ) ) {
			return;
		}

		if ( '' === get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_source', true ) ) {
			return;
		}

		add_meta_box(
			'indieblocks',
			__( 'Webmention', 'indieblocks' ),
			array( __CLASS__, 'render_meta_box' ),
			'comment',
			'normal',
			'high'
		);
	}

	/**
	 * Renders a (read-only) "Webmention" comment meta box.
	 *
	 * @param \WP_Comment $comment Comment being edited.
	 */
	public static function render_meta_box( $comment ) {
		// Webmention data.
		$source = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_source', true );
		$kind   = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_kind', true );
		$avatar = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_avatar', true );
		?>
			<p><label for="indieblocks_webmention_source"><?php esc_html_e( 'Source', 'indieblocks' ); ?></label><br />
			<input type="url" id="indieblocks_webmention_source" value="<?php echo esc_url( $source ); ?>" class="widefat" readonly="readonly" /></p>

			<p><label for="indieblocks_webmention_kind"><?php esc_html_e( 'Type', 'indieblocks' ); ?></label><br />
			<input type="url" id="indieblocks_webmention_kind" value="<?php echo esc_attr( ucfirst( $kind ) ); ?>" class="widefat" readonly="readonly" /></p>

			<?php if ( '' !== $avatar ) : ?>
				<p><label for="indieblocks_webmention_avatar"><?php esc_html_e( 'Avatar', 'indieblocks' ); ?></label><br />
				<span style="display: flex; gap: 1em; justify-content: space-between;">
					<input type="url" id="indieblocks_webmention_avatar" value="<?php echo esc_url( $avatar ); ?>" class="widefat" style="vertical-align: baseline;" readonly="readonly" />
					<button type="button" class="button indieblocks-delete-avatar" data-nonce="<?php echo esc_attr( wp_create_nonce( 'indieblocks:delete-avatar:' . $comment->comment_ID ) ); ?>">
						<?php esc_html_e( 'Delete', 'indieblocks' ); ?>
					</button>
				</span></p>
			<?php endif; ?>
		<?php
	}

	/**
	 * Prints the Webmention endpoint (on pages that actually support them).
	 */
	public static function webmention_link() {
		if ( class_exists( '\\Webmention\\Receiver' ) ) {
			// Avoid outputting the endpoint when the Webmention plugin is
			// active as well.
			return;
		}

		if ( ! is_singular() || ! webmentions_open() ) {
			return;
		}

		if ( 'template_redirect' === current_filter() ) {
			// Add `Link` header.
			header( 'Link: <' . esc_url( get_rest_url( null, '/indieblocks/v1/webmention' ) ) . '>; rel="webmention"', false );
		} elseif ( 'wp_head' === current_filter() ) {
			// Output `link` tag.
			echo '<link rel="webmention" href="' . esc_url( get_rest_url( null, '/indieblocks/v1/webmention' ) ) . '" />' . PHP_EOL;
		}
	}

	/**
	 * Deletes a previously stored avatar.
	 *
	 * Should only ever be called through AJAX.
	 */
	public static function delete_avatar() {
		if ( ! isset( $_POST['_wp_nonce'] ) || ! isset( $_POST['comment_id'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wp_nonce'] ), 'indieblocks:delete-avatar:' . intval( $_POST['comment_id'] ) ) ) {
			status_header( 400 );
			esc_html_e( 'Missing or invalid nonce.', 'indieblocks' );
			wp_die();
		}

		if ( ! ctype_digit( $_POST['comment_id'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			status_header( 400 );
			esc_html_e( 'Invalid comment ID.', 'indieblocks' );
			wp_die();
		}

		$comment_id = (int) $_POST['comment_id'];

		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			status_header( 400 );
			esc_html_e( 'Insufficient rights.', 'indieblocks' );
			wp_die();
		}

		$url = get_comment_meta( $comment_id, 'indieblocks_webmention_avatar', true );

		if ( '' !== $url ) {
			$upload_dir = wp_upload_dir();
			$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

			// Delete file.
			wp_delete_file( $file_path );

			// Delete all references to this file (i.e., not just this
			// comment's meta field).
			delete_metadata(
				'comment',
				$comment_id,
				'indieblocks_webmention_avatar',
				esc_url_raw( $url ),
				true // Delete matching metadata entries for all objects.
			);
		}

		wp_die();
	}

	/**
	 * Adds line breaks to the list of allowed comment tags.
	 *
	 * @param  array  $allowedtags Allowed HTML tags.
	 * @param  string $context     Context.
	 * @return array               Filtered tag list.
	 */
	public static function allowed_html( $allowedtags, $context = '' ) {
		if ( 'pre_comment_content' !== $context ) {
			return $allowedtags;
		}

		if ( ! array_key_exists( 'br', $allowedtags ) ) {
			$allowedtags['br'] = array();
		}

		if ( ! array_key_exists( 'p', $allowedtags ) ) {
			$allowedtags['p'] = array();
		}

		return $allowedtags;
	}
}
