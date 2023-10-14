<?php
/**
 * All things "link preview" cards.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * An attempt to add microformats to block themes.
 */
class Preview_Cards {
	/**
	 * Registers action callbacks.
	 */
	public static function register() {
		add_filter( 'publish_indieblocks_note', array( __CLASS__, 'schedule' ), 20, 2 );
		add_filter( 'publish_indieblocks_like', array( __CLASS__, 'schedule' ), 20, 2 );

		add_action( 'indieblocks_preview_card', array( __CLASS__, 'add_meta' ) );

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_field' ) );

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_indieblocks_delete_preview_card', array( __CLASS__, 'delete_preview_card' ) );
	}

	/**
	 * Schedules the "generation" of link preview cards.
	 *
	 * @param  int      $post_id Post ID.
	 * @param  \WP_Post $post    Post object.
	 */
	public static function schedule( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// We could try and parse Open Graph metadata immediately, but let's
		// schedule it into the near future instead, to speed up publishing a
		// bit.
		wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'indieblocks_preview_card', array( $post->ID ) );
	}

	/**
	 * Saves preview card metadata as custom fields.
	 *
	 * @param int $post_id  Post ID.
	 */
	public static function add_meta( $post_id ) {
		$post = get_post( $post_id );

		if ( empty( $post->post_content ) ) {
			return;
		}

		$parser     = post_content_parser( $post );
		$linked_url = $parser->get_link_url( false ); // Get the first link, regardless of microformats.

		if ( empty( $linked_url ) ) {
			delete_post_meta( $post_id, '_indieblocks_link_preview' );
			return;
		}

		$parser = new Parser( $linked_url );
		$parser->parse();

		$name = $parser->get_name( false ); // Also consider non-mf2 titles, e.g., for notes.
		if ( '' === $name ) {
			delete_post_meta( $post_id, '_indieblocks_link_preview' );
			return;
		}

		$thumbnail = '';
		$image     = $parser->get_image();
		if ( '' !== $image ) {
			$thumbnail = static::create_thumbnail( $image, $post );
		}

		$meta = array_filter( // Remove empty values.
			array(
				'title'     => $name,
				'url'       => $linked_url,
				'thumbnail' => $thumbnail,
			)
		);
		update_post_meta( $post->ID, '_indieblocks_link_preview', $meta );
	}

	/**
	 * Creates a link preview thumbnail and returns its local URL.
	 *
	 * @param  string $url Image URL.
	 * @return string      Local thumbnail URL.
	 */
	protected static function create_thumbnail( $url ) {
		$dir = 'indieblocks-cards';

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['subdir'] ) ) {
			// Add month and year, to be able to keep track of things.
			$dir .= '/' . trim( $upload_dir['subdir'], '/' );
		}

		$hash     = hash( 'sha256', esc_url_raw( $url ) );
		$ext      = pathinfo( $url, PATHINFO_EXTENSION );
		$filename = $hash . ( ! empty( $ext ) ? '.' . $ext : '' );

		return store_image( $url, $filename, $dir );
	}

	/**
	 * Registers a custom REST API endpoint for reading (but not writing) our
	 * location data.
	 */
	public static function register_rest_field() {
		foreach ( array( 'post', 'indieblocks_note', 'indieblocks_like' ) as $post_type ) {
			register_rest_field(
				$post_type,
				'indieblocks_link_preview',
				array(
					'get_callback'    => array( __CLASS__, 'get_meta' ),
					'update_callback' => null,
				)
			);
		}
	}

	/**
	 * Returns link preview metadata.
	 *
	 * @param  array $params WP REST API request.
	 * @return mixed         Response.
	 */
	public static function get_meta( $params ) {
		$post_id = $params['id'];

		if ( empty( $post_id ) || ! ctype_digit( $post_id ) ) {
			return new \WP_Error( 'invalid_id', 'Invalid post ID.', array( 'status' => 400 ) );
		}

		$post_id = (int) $post_id;

		$link_preview = get_transient( "indieblocks:$post_id:link_preview" );
		if ( false === $link_preview ) {
			$link_preview = get_post_meta( $post_id, '_indieblocks_link_preview', true );
			set_transient( "indieblocks:$post_id:link_preview", $link_preview, 300 );
		}

		return $link_preview; // Either an empty string, or an associated array (which gets translated into a JSON object).
	}

	/**
	 * Adds meta box.
	 */
	public static function add_meta_box() {
		global $post;

		if ( empty( $post->ID ) ) {
			return;
		}

		$card = get_post_meta( $post->ID, '_indieblocks_link_preview', true );

		if ( empty( $card['title'] ) || empty( $card['url'] ) ) {
			return;
		}

		// Add meta box, for those post types that are supported.
		add_meta_box(
			'indieblocks-link-preview',
			__( 'Link Preview', 'indieblocks' ),
			array( __CLASS__, 'render_meta_box' ),
			null,
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
		$card = get_post_meta( $post->ID, '_indieblocks_link_preview', true );

		if ( ! empty( $card['title'] ) && ! empty( $card['url'] ) ) :
			?>
			<div style="display: flex; gap: 1em; justify-content: space-between;">
				<div style="width: 100%;">
					<p style="margin-block-start: 0;"><label for="indieblocks_link_preview_title"><?php esc_html_e( 'Title', 'indieblocks' ); ?></label><br />
					<input type="url" id="indieblocks_link_preview_title" value="<?php echo esc_attr( $card['title'] ); ?>" class="widefat" readonly="readonly" /></p>

					<p><label for="indieblocks_link_preview_url"><?php esc_html_e( 'URL', 'indieblocks' ); ?></label><br />
					<input type="url" id="indieblocks_link_preview_url" value="<?php echo esc_url( $card['url'] ); ?>" class="widefat" readonly="readonly" /></p>

					<?php if ( ! empty( $card['thumbnail'] ) ) : ?>
						<p><label for="indieblocks_link_preview_thumbnail"><?php esc_html_e( 'Thumbnail', 'indieblocks' ); ?></label><br />
						<input type="url" id="indieblocks_link_preview_thumbnail" value="<?php echo esc_url( $card['thumbnail'] ); ?>" class="widefat" style="vertical-align: baseline;" readonly="readonly" /></p>
					<?php endif; ?>
				</div>
				<button type="button" style="margin-block: 1.67em 1em;" class="button indieblocks-delete-preview-card" data-nonce="<?php echo esc_attr( wp_create_nonce( 'indieblocks:delete-preview-card:' . $post->ID ) ); ?>">
					<?php esc_html_e( 'Delete', 'indieblocks' ); ?>
				</button>
			</div>
			<?php
		endif;
	}

	/**
	 * Adds admin scripts and styles.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		$include = false;

		if ( 'post-new.php' === $hook_suffix || 'post.php' === $hook_suffix ) {
			global $post;

			if ( empty( $post ) ) {
				// Can't do much without a `$post` object.
				return;
			}

			$card = get_post_meta( $post->ID, '_indieblocks_link_preview', true );

			if ( empty( $card['title'] ) || empty( $card['url'] ) ) {
				return;
			}

			// Enqueue CSS and JS.
			wp_enqueue_script( 'indieblocks-preview-cards', plugins_url( '/assets/preview-cards.js', __DIR__ ), array( 'jquery' ), Plugin::PLUGIN_VERSION, false );
			wp_localize_script(
				'indieblocks-preview-cards',
				'indieblocks_preview_cards_obj',
				array(
					'message' => esc_attr__( 'Are you sure?', 'indieblocks' ),
				)
			);
		}
	}

	/**
	 * Deletes previously stored link preview cards.
	 *
	 * Should only ever be called through AJAX.
	 */
	public static function delete_preview_card() {
		if ( ! isset( $_POST['_wp_nonce'] ) || ! isset( $_POST['post_id'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wp_nonce'] ), 'indieblocks:delete-preview-card:' . intval( $_POST['post_id'] ) ) ) {
			status_header( 400 );
			esc_html_e( 'Missing or invalid nonce.', 'indieblocks' );
			wp_die();
		}

		if ( ! ctype_digit( $_POST['post_id'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			status_header( 400 );
			esc_html_e( 'Invalid comment ID.', 'indieblocks' );
			wp_die();
		}

		$post_id = (int) $_POST['post_id'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			status_header( 400 );
			esc_html_e( 'Insufficient rights.', 'indieblocks' );
			wp_die();
		}

		$card = get_post_meta( $post->ID, '_indieblocks_link_preview', true );

		delete_post_meta( $post_id, '_indieblocks_link_preview' );
		delete_transient( "indieblocks:$post_id:link_preview" );

		if ( ! empty( $card['thumbnail'] ) ) {
			$upload_dir = wp_upload_dir();
			$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $card['thumbnail'] );

			// Delete file.
			if ( is_file( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}

		wp_die();
	}
}
