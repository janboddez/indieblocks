<?php
/**
 * Options handler.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Deals with settings and stuff.
 */
class Options_Handler {
	/**
	 * Plugin option schema.
	 */
	const SCHEMA = array(
		'enable_blocks'          => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'add_mf2'                => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'hide_titles'            => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'unhide_bookmark_titles' => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'unhide_like_titles'     => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'full_content'           => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'enable_notes'           => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'notes_in_author'        => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'notes_in_home'          => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'notes_in_feed'          => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'note_taxonomies'        => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'enable_likes'           => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'likes_in_author'        => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'likes_in_feed'          => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'likes_in_home'          => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'like_taxonomies'        => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'random_slugs'           => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'automatic_titles'       => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'bookmark_titles'        => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'like_titles'            => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'date_archives'          => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'permalink_format'       => array(
			'type'    => 'string',
			'default' => '/%postname%/',
		),
		'modified_feeds'         => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'webmention'             => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'webmention_post_types'  => array(
			'type'    => 'array',
			'default' => array( 'post' ),
			'items'   => array( 'type' => 'string' ),
		),
		'webmention_delay'       => array(
			'type'    => 'integer',
			'default' => 300,
		),
		'cache_avatars'          => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'webmention_facepile'    => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'facepile_block_hook'    => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'add_featured_images'    => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'location_functions'     => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'weather_units'          => array(
			'type'    => 'string',
			'default' => 'metric',
		),
		'micropub'               => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'parse_markdown'         => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'preview_cards'          => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'image_proxy'            => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'image_proxy_secret'     => array(
			'type'    => 'string',
			'default' => '',
		),
	);

	/**
	 * Plugin options.
	 *
	 * @var array $options Plugin options.
	 */
	private $options = array();

	/**
	 * Valid permalink options (for IndieBlocks' CPTs).
	 */
	const PERMALINK_FORMATS = array(
		'/%postname%/',
		'/%year%/%monthnum%/%postname%/',
		'/%year%/%monthnum%/%day%/%postname%/',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$options = get_option( 'indieblocks_settings' );

		// Ensure `$this->options` is an array, and that all keys get a value.
		$this->options = array_merge(
			array_combine( array_keys( self::SCHEMA ), array_column( self::SCHEMA, 'default' ) ),
			is_array( $options )
				? $options
				: array()
		); // Note that this affects only `$this->options` as used by this plugin, and not, e.g., whatever shows in the REST API.
	}

	/**
	 * Interacts with WordPress's Plugin API.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'create_menu' ) );
		add_action( 'init', array( $this, 'flush_permalinks' ), 9 );
		add_action( 'rest_api_init', array( $this, 'add_settings' ) );
	}

	/**
	 * Flushes permalinks whenever settings are saved.
	 */
	public function flush_permalinks() {
		if ( delete_transient( 'indieblocks_flush_permalinks' ) ) {
			flush_permalinks();
		}
	}

	/**
	 * Registers the plugin settings page.
	 */
	public function create_menu() {
		add_options_page(
			__( 'IndieBlocks', 'indieblocks' ),
			__( 'IndieBlocks', 'indieblocks' ),
			'manage_options',
			'indieblocks',
			array( $this, 'settings_page' )
		);

		add_action( 'admin_init', array( $this, 'add_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Registers the actual options.
	 */
	public function add_settings() {
		// Pre-initialize settings. `add_option()` will _not_ override existing
		// options, so it's safe to use here.
		add_option( 'indieblocks_settings', $this->options );

		$schema = self::SCHEMA;
		foreach ( $schema as &$row ) {
			unset( $row['default'] );
		}

		// Prep for Gutenberg.
		register_setting(
			'indieblocks-settings-group',
			'indieblocks_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'type'              => 'object',
				'show_in_rest'      => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => $schema,
					),
				),
			)
		);
	}
	/**
	 * Enqueues JS file.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'indieblocks-admin',
			plugins_url( '/assets/admin.js', __DIR__ ),
			array(),
			Plugin::PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Handles submitted options.
	 *
	 * @param  array $settings Settings as submitted through WP Admin.
	 * @return array           Options to be stored.
	 */
	public function sanitize_settings( $settings ) {
		// @todo: What about potential (future) conflicts with Gutenberg here?
		// We can try to detect whether the request is coming from a REST route
		// and create a new `case` that sanitizes all options.
		$active_tab = $this->get_active_tab();

		switch ( $active_tab ) {
			case 'blocks':
				$options = array(
					'enable_blocks'          => isset( $settings['enable_blocks'] ) ? true : false,
					'add_mf2'                => isset( $settings['add_mf2'] ) ? true : false,
					'hide_titles'            => isset( $settings['hide_titles'] ) ? true : false,
					'unhide_bookmark_titles' => isset( $settings['unhide_bookmark_titles'] ) ? true : false,
					'unhide_like_titles'     => isset( $settings['unhide_like_titles'] ) ? true : false,
					'full_content'           => isset( $settings['full_content'] ) ? true : false,
				);

				$this->options = array_merge( $this->options, $options );
				return $this->options;

			case 'post_types':
				$options = array(
					'enable_notes'     => isset( $settings['enable_notes'] ) ? true : false,
					'notes_in_author'  => isset( $settings['notes_in_author'] ) ? true : false,
					'notes_in_feed'    => isset( $settings['notes_in_feed'] ) ? true : false,
					'notes_in_home'    => isset( $settings['notes_in_home'] ) ? true : false,
					'note_taxonomies'  => isset( $settings['note_taxonomies'] ) ? true : false,
					'enable_likes'     => isset( $settings['enable_likes'] ) ? true : false,
					'likes_in_author'  => isset( $settings['likes_in_author'] ) ? true : false,
					'likes_in_feed'    => isset( $settings['likes_in_feed'] ) ? true : false,
					'likes_in_home'    => isset( $settings['likes_in_home'] ) ? true : false,
					'like_taxonomies'  => isset( $settings['like_taxonomies'] ) ? true : false,
					'random_slugs'     => isset( $settings['random_slugs'] ) ? true : false,
					'automatic_titles' => isset( $settings['automatic_titles'] ) ? true : false,
					'bookmark_titles'  => isset( $settings['bookmark_titles'] ) ? true : false,
					'like_titles'      => isset( $settings['like_titles'] ) ? true : false,
					'date_archives'    => isset( $settings['date_archives'] ) ? true : false,
					'modified_feeds'   => isset( $settings['modified_feeds'] ) ? true : false,
				);

				$permalink_format = '/%postname%/';
				if ( isset( $settings['permalink_format'] ) && in_array( $settings['permalink_format'], static::get_permalink_formats(), true ) ) {
					$permalink_format = $settings['permalink_format'];
				}
				$options['permalink_format'] = apply_filters( 'indieblocks_permalink_format', $permalink_format );

				// Ensure these (now deprecated) settings don't come back and bite us.
				unset( $options['post_types'] );
				unset( $options['custom_menu_order'] );
				unset( $options['default_taxonomies'] );
				unset( $options['like_and_bookmark_titles'] );
				unset( $options['unhide_like_and_bookmark_titles'] );

				$this->options = array_merge( $this->options, $options );

				// Instruct IndieBlocks to flush permalinks during the next request.
				set_transient( 'indieblocks_flush_permalinks', true );

				// Updated settings.
				return $this->options;

			case 'webmention':
				$options = array(
					'webmention'          => isset( $settings['webmention'] ) ? true : false,
					'webmention_delay'    => isset( $settings['webmention_delay'] ) && ctype_digit( $settings['webmention_delay'] )
						? (int) $settings['webmention_delay']
						: 0,
					'webmention_facepile' => isset( $settings['webmention_facepile'] ) ? true : false,
					'facepile_block_hook' => isset( $settings['facepile_block_hook'] ) ? true : false,
					'cache_avatars'       => isset( $settings['cache_avatars'] ) ? true : false,
					'image_proxy'         => isset( $settings['image_proxy'] ) ? true : false,
					'image_proxy_secret'  => isset( $settings['image_proxy_secret'] )
						? $settings['image_proxy_secret']
						: '',
				);

				$webmention_post_types = array();
				$supported_post_types  = $this->get_post_types( 'names' );
				if ( isset( $settings['webmention_post_types'] ) && is_array( $settings['webmention_post_types'] ) ) {
					foreach ( $settings['webmention_post_types'] as $post_type ) {
						if ( in_array( $post_type, $supported_post_types, true ) ) {
							$webmention_post_types[] = $post_type;
						}
					}
				}
				$options['webmention_post_types'] = $webmention_post_types;

				$this->options = array_merge( $this->options, $options );
				return $this->options;

			case 'misc':
				$options = array(
					'add_featured_images' => isset( $settings['add_featured_images'] ) ? true : false,
					'location_functions'  => isset( $settings['location_functions'] ) ? true : false,
					'weather_units'       => isset( $settings['weather_units'] ) && in_array( $settings['weather_units'], array( 'metric', 'imperial' ), true )
						? $settings['weather_units']
						: 'metric',
					'micropub'            => isset( $settings['micropub'] ) ? true : false,
					'parse_markdown'      => isset( $settings['parse_markdown'] ) ? true : false,
					'preview_cards'       => isset( $settings['preview_cards'] ) ? true : false,
				);

				$this->options = array_merge( $this->options, $options );
				return $this->options;
		}
	}

	/**
	 * Echoes the plugin options form.
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'IndieBlocks', 'indieblocks' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				// Print nonces and such.
				settings_fields( 'indieblocks-settings-group' );

				$active_tab = $this->get_active_tab();
				?>
				<h2 class="nav-tab-wrapper">
					<a href="<?php echo esc_url( $this->get_options_url( 'blocks' ) ); ?>" class="nav-tab <?php echo esc_attr( 'blocks' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Microformats and Blocks', 'indieblocks' ); ?></a>
					<a href="<?php echo esc_url( $this->get_options_url( 'post_types' ) ); ?>" class="nav-tab <?php echo esc_attr( 'post_types' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Post Types', 'indieblocks' ); ?></a>
					<a href="<?php echo esc_url( $this->get_options_url( 'webmention' ) ); ?>" class="nav-tab <?php echo esc_attr( 'webmention' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Webmention', 'indieblocks' ); ?></a>
					<a href="<?php echo esc_url( $this->get_options_url( 'misc' ) ); ?>" class="nav-tab <?php echo esc_attr( 'misc' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Miscellaneous', 'indieblocks' ); ?></a>
				</h2>

				<?php if ( 'blocks' === $active_tab ) : ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Blocks', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[enable_blocks]" value="1" <?php checked( ! empty( $this->options['enable_blocks'] ) ); ?>/> <?php esc_html_e( 'Enable blocks', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( 'Introduces several blocks that help ensure replies, likes, etc., are microformatted correctly.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row" rowspan="3"><?php esc_html_e( 'Block Theme Enhancements', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[add_mf2]" value="1" <?php checked( ! empty( $this->options['add_mf2'] ) ); ?>/> <?php esc_html_e( 'Enable microformats', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( 'Adds microformats2 to your site&rsquo;s front end. Requires the active theme to support WordPress&rsquo; new Site Editor.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<td>
								<label><input type="checkbox" name="indieblocks_settings[hide_titles]" value="1" <?php checked( ! empty( $this->options['hide_titles'] ) ); ?>/> <?php esc_html_e( 'Hide note and like titles', 'indieblocks' ); ?></label>
								<p class="description"><?php esc_html_e( '(Experimental) Attempts to (visually) hide note and like titles, if you have enabled microformats and your theme supports the Site Editor.', 'indieblocks' ); ?></p>
								<div style="margin-inline-start: 1.25em; margin-block-start: 0.5em;">
									<label><input type="checkbox" name="indieblocks_settings[unhide_bookmark_titles]" value="1" <?php checked( ! empty( $this->options['unhide_bookmark_titles'] ) ); ?>/> <?php esc_html_e( 'Exempt bookmark titles', 'indieblocks' ); ?></label>
									<p class="description"><?php _e( 'Do <em>not</em> hide bookmark titles, <em>and</em> have them link to the bookmarked page.', 'indieblocks' ); // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction ?></p>
									<label style="display: inline-block; margin-block-start: 0.5em;"><input type="checkbox" name="indieblocks_settings[unhide_like_titles]" value="1" <?php checked( ! empty( $this->options['unhide_like_titles'] ) ); ?>/> <?php esc_html_e( 'Exempt like titles', 'indieblocks' ); ?></label>
									<p class="description"><?php _e( 'Do <em>not</em> hide like titles, <em>and</em> have them link to the liked page.', 'indieblocks' ); // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction ?></p>
								</div>
							</td>
						</tr>
						<tr valign="top">
							<td>
								<label><input type="checkbox" name="indieblocks_settings[full_content]" value="1" <?php checked( ! empty( $this->options['full_content'] ) ); ?>/> <?php _e( '<em>Always</em> show notes and likes in full', 'indieblocks' ); // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction ?></label>
								<p class="description"><?php esc_html_e( 'Attempts to dynamically replace instances of the Post Excerpt block with a Post Content block, but only for short-form post types such as notes and likes.', 'indieblocks' ); ?></p>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<?php if ( 'post_types' === $active_tab ) : ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row" rowspan="2"><?php esc_html_e( 'Custom Post Types', 'indieblocks' ); ?></th>
							<td>
								<label><input type="checkbox" name="indieblocks_settings[enable_notes]" value="1" <?php checked( ! empty( $this->options['enable_notes'] ) ); ?>/> <?php esc_html_e( 'Enable &ldquo;Notes&rdquo;', 'indieblocks' ); ?></label>
								<div style="margin-inline-start: 1.25em; margin-block-start: 0.25em;">
									<label><input type="checkbox" name="indieblocks_settings[notes_in_feed]" value="1" <?php checked( ! empty( $this->options['notes_in_feed'] ) ); ?>/> <?php esc_html_e( 'Include in main feed', 'indieblocks' ); ?></label><br />
									<label><input type="checkbox" name="indieblocks_settings[notes_in_home]" value="1" <?php checked( ! empty( $this->options['notes_in_home'] ) ); ?>/> <?php esc_html_e( 'Show on blog page', 'indieblocks' ); ?></label><br />
									<label><input type="checkbox" name="indieblocks_settings[notes_in_author]" value="1" <?php checked( ! empty( $this->options['notes_in_author'] ) ); ?>/> <?php esc_html_e( 'Include in author archives', 'indieblocks' ); ?></label><br />
									<label><input type="checkbox" name="indieblocks_settings[note_taxonomies]" value="1" <?php checked( ! empty( $this->options['note_taxonomies'] ) ); ?>/> <?php esc_html_e( 'Enable categories and tags', 'indieblocks' ); ?></label>
								</div>
							</td>
						</tr>
						<tr valign="top">
							<td><label><input type="checkbox" name="indieblocks_settings[enable_likes]" value="1" <?php checked( ! empty( $this->options['enable_likes'] ) ); ?>/> <?php esc_html_e( 'Enable &ldquo;Likes&rdquo;', 'indieblocks' ); ?></label>
							<div style="margin-inline-start: 1.25em; margin-block-start: 0.25em;">
								<label><input type="checkbox" name="indieblocks_settings[likes_in_feed]" value="1" <?php checked( ! empty( $this->options['likes_in_feed'] ) ); ?>/> <?php esc_html_e( 'Include in main feed', 'indieblocks' ); ?></label><br />
								<label><input type="checkbox" name="indieblocks_settings[likes_in_home]" value="1" <?php checked( ! empty( $this->options['likes_in_home'] ) ); ?>/> <?php esc_html_e( 'Show on blog page', 'indieblocks' ); ?></label><br />
								<label><input type="checkbox" name="indieblocks_settings[likes_in_author]" value="1" <?php checked( ! empty( $this->options['likes_in_author'] ) ); ?>/> <?php esc_html_e( 'Include in author archives', 'indieblocks' ); ?></label><br />
								<label><input type="checkbox" name="indieblocks_settings[like_taxonomies]" value="1" <?php checked( ! empty( $this->options['like_taxonomies'] ) ); ?>/> <?php esc_html_e( 'Enable categories and tags', 'indieblocks' ); ?></label>
							</div>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Random Slugs', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[random_slugs]" value="1" <?php checked( ! empty( $this->options['random_slugs'] ) ); ?>/> <?php esc_html_e( 'Generate random slugs', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( 'Autogenerate unique note and like slugs. Disable for WordPress&rsquo; default behavior.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Autogenerate Titles', 'indieblocks' ); ?></th>
							<td>
								<label><input type="checkbox" name="indieblocks_settings[automatic_titles]" value="1" <?php checked( ! empty( $this->options['automatic_titles'] ) ); ?>/> <?php esc_html_e( 'Automatically generate titles', 'indieblocks' ); ?></label>
								<div style="margin-inline-start: 1.25em; margin-block-start: 0.25em;">
									<label><input type="checkbox" name="indieblocks_settings[bookmark_titles]" value="1" <?php checked( ! empty( $this->options['bookmark_titles'] ) ); ?>/> <?php esc_html_e( 'Have bookmark titles reflect bookmarked pages', 'indieblocks' ); ?></label>
									<p class="description"><?php _e( '&ldquo;Bookmarks&rdquo; are <em>notes that contain a Bookmark block</em>.', 'indieblocks' ); // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction ?></p>
									<label style="display: inline-block; margin-block-start: 0.5em;"><input type="checkbox" name="indieblocks_settings[like_titles]" value="1" <?php checked( ! empty( $this->options['like_titles'] ) ); ?>/> <?php esc_html_e( 'Have like titles reflect liked pages', 'indieblocks' ); ?></label>
								</div>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Web Feeds', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[modified_feeds]" value="1" <?php checked( ! empty( $this->options['modified_feeds'] ) ); ?>/> <?php esc_html_e( 'Hide note and like titles', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Remove note (and like) titles from RSS and Atom feeds. This may help feed readers recognize them as &ldquo;notes,&rdquo; but might conflict with existing custom feed templates.', 'indieblocks' ); ?></p></td>
						</tr>
						<?php if ( get_option( 'permalink_structure' ) ) : ?>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Date-Based Archives', 'indieblocks' ); ?></th>
								<td><label><input type="checkbox" name="indieblocks_settings[date_archives]" value="1" <?php checked( ! empty( $this->options['date_archives'] ) ); ?>/> <?php esc_html_e( 'Enable date-based archives', 'indieblocks' ); ?></label>
								<p class="description"><?php esc_html_e( '(Experimental) Enable year, month, and day archives for notes and likes.', 'indieblocks' ); ?></p></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Permalink Format', 'indieblocks' ); ?></th>
								<td>
									<?php foreach ( static::get_permalink_formats() as $i => $format ) : ?>
										<?php echo ( 0 !== $i ? '<br />' : '' ); ?><label><input type="radio" name="indieblocks_settings[permalink_format]" value="<?php echo esc_attr( $format ); ?>" <?php checked( isset( $this->options['permalink_format'] ) ? $this->options['permalink_format'] : '/%postname%/', $format ); ?> /> <code><?php echo esc_html( $this->get_example_permalink( $format ) ); ?></code></label>
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( '(Experimental) Set a custom note and like permalink format.', 'indieblocks' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
					</table>
				<?php endif; ?>

				<?php if ( 'webmention' === $active_tab ) : ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Webmention', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[webmention]" value="1" <?php checked( ! empty( $this->options['webmention'] ) ); ?>/> <?php esc_html_e( 'Enable Webmention', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Automatically notify pages you&rsquo;ve linked to, and allow other websites to do the same. You&rsquo;ll probably want to leave this disabled if you&rsquo;re already using a different Webmention plugin.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Post Types', 'indieblocks' ); ?></th>
							<td>
								<?php foreach ( $this->get_post_types() as $i => $post_type ) : ?>
									<?php echo ( 0 !== $i ? '<br />' : '' ); ?><label><input type="checkbox" name="indieblocks_settings[webmention_post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( isset( $this->options['webmention_post_types'] ) && in_array( $post_type->name, (array) $this->options['webmention_post_types'], true ) ); ?> /> <?php echo esc_html( isset( $post_type->labels->singular_name ) ? $post_type->labels->singular_name : $post_type->name ); ?></code></label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'The post types for which webmentions (outgoing and incoming) should be enabled.', 'indieblocks' ); ?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="indieblocks_settings[webmention_delay]"><?php esc_html_e( 'Webmention Delay', 'indieblocks' ); ?></label></th>
							<td><input type="number" style="width: 6em;" id="indieblocks_settings[webmention_delay]" name="indieblocks_settings[webmention_delay]" value="<?php echo esc_attr( isset( $this->options['webmention_delay'] ) ? $this->options['webmention_delay'] : 0 ); ?>" />
							<p class="description"><?php esc_html_e( 'The time, in seconds, WordPress should delay sending webmentions after a post is first published.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( '&ldquo;Proxy&rdquo; Avatars', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[image_proxy]" value="1" <?php checked( ! empty( $this->options['image_proxy'] ) ); ?>/> <?php esc_html_e( '&ldquo;Reverse proxy&rdquo; avatars', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( 'Serve remote avatars from this site&rsquo;s domain.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="indieblocks-image-proxy-secret"><?php esc_html_e( 'Proxy Secret', 'indieblocks' ); ?></label></th>
							<td><input type="text" name="indieblocks_settings[image_proxy_secret]" id="indieblocks-image-proxy-secret" style="min-width: 25%;" value="<?php echo ! empty( $this->options['image_proxy_secret'] ) ? esc_attr( $this->options['image_proxy_secret'] ) : ''; ?>" />
							<button type="button" class="button" id="indieblocks-generate-secret"><?php esc_html_e( 'Generate', 'indieblocks' ); ?></button>
							<p class="description"><?php esc_html_e( 'To work, the image proxy needs a (sufficiently random) secret, much like an autogenerated password.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Cache Avatars', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[cache_avatars]" value="1" <?php checked( ! empty( $this->options['cache_avatars'] ) ); ?>/> <?php esc_html_e( 'Cache webmention avatars', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Attempt to locally cache webmention avatars.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Facepile', 'indieblocks' ); ?></th>
							<td>
								<label><input type="checkbox" name="indieblocks_settings[webmention_facepile]" value="1" <?php checked( ! empty( $this->options['webmention_facepile'] ) ); ?>/> <?php esc_html_e( '&ldquo;Facepile&rdquo; bookmarks, likes, and reposts', 'indieblocks' ); ?></label>
								<p class="description"><?php esc_html_e( '(Experimental) Display bookmarks, likes, and reposts separate from &ldquo;regular&rdquo; comments.', 'indieblocks' ); ?></p>
								<label style="display: inline-block; margin-block-start: 0.5em;"><input type="checkbox" name="indieblocks_settings[facepile_block_hook]" value="1" <?php checked( ! empty( $this->options['facepile_block_hook'] ) ); ?>/> <?php esc_html_e( 'Auto-insert Facepile block.', 'indieblocks' ); ?></label>
								<p class="description"><?php esc_html_e( '(Experimental) Automatically insert a Facepile block in front of every Comments block. (You can still customize its look and feel by editing it in the Site Editor.)', 'indieblocks' ); ?></p>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<?php if ( 'misc' === $active_tab ) : ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Featured Images', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[add_featured_images]" value="1" <?php checked( ! empty( $this->options['add_featured_images'] ) ); ?>/> <?php esc_html_e( 'Add Featured Images to feeds', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Prepend Featured Images to feed items.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Location and Weather', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[location_functions]" value="1" <?php checked( ! empty( $this->options['location_functions'] ) ); ?>/> <?php esc_html_e( 'Enable location functions', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Add basic location and weather data to posts.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Weather Units', 'indieblocks' ); ?></th>
							<td><label><input type="radio" name="indieblocks_settings[weather_units]" value="metric" <?php checked( empty( $this->options['weather_units'] ) || 'metric' === $this->options['weather_units'] ); ?>/> <?php esc_html_e( 'Metric', 'indieblocks' ); ?></label><br />
							<label><input type="radio" name="indieblocks_settings[weather_units]" value="imperial" <?php checked( ! empty( $this->options['weather_units'] ) && 'imperial' === $this->options['weather_units'] ); ?>/> <?php esc_html_e( 'Imperial', 'indieblocks' ); ?></label></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Micropub', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[micropub]" value="1" <?php checked( ! empty( $this->options['micropub'] ) ); ?>/> <?php esc_html_e( 'Deeper Micropub integration', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Add post type and category data to responses to Micropub &ldquo;config&rdquo; queries.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Parse Markdown', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[parse_markdown]" value="1" <?php checked( ! empty( $this->options['parse_markdown'] ) ); ?>/> <?php esc_html_e( 'Parse Markdown', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Parse Markdown inside &ldquo;Micropub&rdquo; notes or likes.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Link Preview Cards', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[preview_cards]" value="1" <?php checked( ! empty( $this->options['preview_cards'] ) ); ?>/> <?php esc_html_e( 'Generate preview cards', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Fetch link metadata in order to generate &ldquo;link preview cards.&rdquo;', 'indieblocks' ); ?></p></td>
						</tr>
					</table>
				<?php endif; ?>

				<p class="submit"><?php submit_button( __( 'Save Changes' ), 'primary', 'submit', false ); ?></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns the active tab.
	 *
	 * @return string Active tab.
	 */
	private function get_active_tab() {
		if ( ! empty( $_POST['submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$query_string = wp_parse_url( wp_get_referer(), PHP_URL_QUERY );

			if ( empty( $query_string ) ) {
				return 'blocks';
			}

			parse_str( $query_string, $query_vars );

			if ( isset( $query_vars['tab'] ) && in_array( $query_vars['tab'], array( 'post_types', 'webmention', 'misc' ), true ) ) {
				return $query_vars['tab'];
			}

			return 'blocks';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'post_types', 'webmention', 'misc' ), true ) ) {
			return $_GET['tab']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		return 'blocks';
	}

	/**
	 * Returns this plugin's options URL with a `tab` query parameter.
	 *
	 * @param  string $tab Target tab.
	 * @return string      Options page URL.
	 */
	public function get_options_url( $tab = 'blocks' ) {
		return add_query_arg(
			array(
				'page' => 'indieblocks',
				'tab'  => $tab,
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Returns this plugin's settings.
	 *
	 * @return array This plugin's settings.
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Maps this plugin's older settings to their newer equivalents.
	 *
	 * @param  array $options Plugin settings as retrieved from the datbase.
	 * @return array          This plugin's settings.
	 */
	public static function prep_options( $options ) {
		// We used to enable/disable both post types together.
		$options['enable_notes'] = isset( $options['enable_notes'] )
			? $options['enable_notes']
			: ! empty( $options['post_types'] );

		$options['enable_likes'] = isset( $options['enable_likes'] )
			? $options['enable_likes']
			: ! empty( $options['post_types'] );

		// We used to only allow notes to use WP's default taxonomies.
		$options['note_taxonomies'] = isset( $options['note_taxonomies'] )
			? $options['note_taxonomies']
			: ! empty( $options['default_taxonomies'] );

		// There were no separate "smart title" settings for bookmarks and likes.
		$options['bookmark_titles'] = isset( $options['bookmark_titles'] )
			? $options['bookmark_titles']
			: ! empty( $options['like_and_bookmark_titles'] );

		$options['like_titles'] = isset( $options['like_titles'] )
			? $options['like_titles']
			: ! empty( $options['like_and_bookmark_titles'] );

		$options['unhide_like_titles'] = isset( $options['unhide_like_titles'] )
			? $options['unhide_like_titles']
			: ! empty( $options['unhide_like_and_bookmark_titles'] );

		$options['unhide_bookmark_titles'] = isset( $options['unhide_bookmark_titles'] )
			? $options['unhide_bookmark_titles']
			: ! empty( $options['unhide_like_and_bookmark_titles'] );

		return array_intersect_key( $options, self::SCHEMA ); // Avoid REST API errors.
	}

	/**
	 * Generates example permalinks for use in the settings table.
	 *
	 * @param  string $format Permalink format.
	 * @return string         Example permalink.
	 */
	protected function get_example_permalink( $format ) {
		$example_front = __( 'notes', 'indieblocks' ); // Just a default.

		if ( ! empty( $this->options['enable_notes'] ) ) {
			$post_type = get_post_type_object( 'indieblocks_note' );

			if ( ! empty( $post_type->rewrite['slug'] ) ) {
				$example_front = $post_type->rewrite['slug']; // Actual notes slug.
			}
		} elseif ( ! empty( $this->options['enable_likes'] ) ) {
			$post_type = get_post_type_object( 'indieblocks_like' );

			if ( ! empty( $post_type->rewrite['slug'] ) ) {
				$example_front = $post_type->rewrite['slug']; // If notes are disabled but likes are enabled, use the likes slug.
			}
		}

		if ( 0 === strpos( $format, '/%front%' ) ) {
			global $wp_rewrite;

			if ( ! empty( $wp_rewrite->front ) ) {
				$example_front = trim( $wp_rewrite->front, '/' ) . "/$example_front";
			}

			$format = str_replace( '/%front%', '', $format );
		}

		$example_front = '/' . ltrim( $example_front, '/' ) . str_replace(
			array( '%year%', '%monthnum%', '%day%', '%postname%' ),
			array( date( 'Y' ), date( 'm' ), date( 'd' ), __( 'sample-post', 'indieblocks' ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$format
		);

		$permalink_structure = get_option( 'permalink_structure' );

		if ( is_string( $permalink_structure ) && '/' !== substr( $permalink_structure, -1 ) ) {
			// If permalinks were set up without trailing slash, hide it.
			$example_front = substr( $example_front, 0, -1 );
		}

		return $example_front;
	}

	/**
	 * Returns post types we may want to enable Webmention for.
	 *
	 * @param  string $output Expected post type format.
	 * @return array          Array of supported post types.
	 */
	protected function get_post_types( $output = 'objects' ) {
		$post_types = get_post_types( array( 'public' => true ), $output );
		unset( $post_types['attachment'] );

		return array_values( $post_types );
	}

	/**
	 * Returns those permalink formats we consider valid.
	 */
	protected function get_permalink_formats() {
		global $wp_rewrite;

		if ( empty( $wp_rewrite->front ) || '/' === $wp_rewrite->front ) {
			// Nothing to do.
			return self::PERMALINK_FORMATS;
		}

		$permalink_formats = self::PERMALINK_FORMATS;

		// Parse-in the "permalink-fronted" variants.
		foreach ( self::PERMALINK_FORMATS as $format ) {
			$permalink_formats[] = '/%front%' . $format;
		}

		return $permalink_formats;
	}
}
