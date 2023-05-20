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
	 * Plugin options.
	 *
	 * @var array $options Plugin options.
	 */
	private $options = array(
		'enable_blocks'                   => true,
		'add_mf2'                         => false,
		'enable_notes'                    => false,
		'notes_in_home'                   => false,
		'notes_in_feed'                   => true,
		'default_taxonomies'              => false,
		'enable_likes'                    => false,
		'likes_in_feed'                   => false,
		'likes_in_home'                   => false,
		'random_slugs'                    => false,
		'automatic_titles'                => false,
		'like_and_bookmark_titles'        => false,
		'hide_titles'                     => false,
		'unhide_like_and_bookmark_titles' => false,
		'date_archives'                   => false,
		'permalink_format'                => '/%postname%/',
		'modified_feeds'                  => false,
		'webmention'                      => false,
		'webmention_post_types'           => array(),
		'webmention_delay'                => 300,
		'cache_avatars'                   => false,
		'webmention_facepile'             => false,
		'add_featured_images'             => false,
		'location_functions'              => false,
		'weather_units'                   => 'metric',
		'micropub'                        => false,
		'parse_markdown'                  => false,
	);

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
		$options = get_option( 'indieblocks_settings', array() );

		if ( is_array( $options ) ) {
			$this->options = array_merge( $this->options, $options );
		}
	}

	/**
	 * Interacts with WordPress's Plugin API.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'create_menu' ) );
		add_action( 'init', array( $this, 'flush_permalinks' ), 9 );
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
	}

	/**
	 * Registers the actual options.
	 */
	public function add_settings() {
		// Pre-initialize settings. `add_option()` will _not_ override existing
		// options, so it's safe to use here.
		add_option( 'indieblocks_settings', $this->options );

		$active_tab = $this->get_active_tab();

		register_setting(
			'indieblocks-settings-group',
			'indieblocks_settings',
			array( 'sanitize_callback' => array( $this, "sanitize_{$active_tab}_settings" ) )
		);
	}

	/**
	 * Handles submitted "Blocks" options.
	 *
	 * @param  array $settings Settings as submitted through WP Admin.
	 * @return array           Options to be stored.
	 */
	public function sanitize_blocks_settings( $settings ) {
		$options = array(
			'enable_blocks' => isset( $settings['enable_blocks'] ) ? true : false,
			'add_mf2'       => isset( $settings['add_mf2'] ) ? true : false,
		);

		$this->options = array_merge( $this->options, $options );

		// Updated settings.
		return $this->options;
	}

	/**
	 * Handles submitted "Post Types" options.
	 *
	 * @param  array $settings Settings as submitted through WP Admin.
	 * @return array           Options to be stored.
	 */
	public function sanitize_post_types_settings( $settings ) {
		$options = array(
			'enable_notes'                    => isset( $settings['enable_notes'] ) ? true : false,
			'notes_in_feed'                   => isset( $settings['notes_in_feed'] ) ? true : false,
			'notes_in_home'                   => isset( $settings['notes_in_home'] ) ? true : false,
			'default_taxonomies'              => isset( $settings['default_taxonomies'] ) ? true : false,
			'enable_likes'                    => isset( $settings['enable_likes'] ) ? true : false,
			'likes_in_feed'                   => isset( $settings['likes_in_feed'] ) ? true : false,
			'likes_in_home'                   => isset( $settings['likes_in_home'] ) ? true : false,
			'random_slugs'                    => isset( $settings['random_slugs'] ) ? true : false,
			'automatic_titles'                => isset( $settings['automatic_titles'] ) ? true : false,
			'like_and_bookmark_titles'        => isset( $settings['like_and_bookmark_titles'] ) ? true : false,
			'hide_titles'                     => isset( $settings['hide_titles'] ) ? true : false,
			'unhide_like_and_bookmark_titles' => isset( $settings['unhide_like_and_bookmark_titles'] ) ? true : false,
			'date_archives'                   => isset( $settings['date_archives'] ) ? true : false,
			'modified_feeds'                  => isset( $settings['modified_feeds'] ) ? true : false,
		);

		$permalink_format = '/%postname%/';

		if ( isset( $settings['permalink_format'] ) && in_array( $settings['permalink_format'], self::PERMALINK_FORMATS, true ) ) {
			$permalink_format = $settings['permalink_format'];
		}

		$options['permalink_format'] = apply_filters( 'indieblocks_permalink_format', $permalink_format );

		// Ensure these (now deprecated) settings don't come back and bite us.
		unset( $options['post_types'] );
		unset( $options['custom_menu_order'] );

		$this->options = array_merge( $this->options, $options );

		// Instruct IndieBlocks to flush permalinks during the next request.
		set_transient( 'indieblocks_flush_permalinks', true );

		// Updated settings.
		return $this->options;
	}

	/**
	 * Handles submitted "Webmention" options.
	 *
	 * @param  array $settings Settings as submitted through WP Admin.
	 * @return array           Options to be stored.
	 */
	public function sanitize_webmention_settings( $settings ) {
		$options = array(
			'webmention'          => isset( $settings['webmention'] ) ? true : false,
			'webmention_delay'    => isset( $settings['webmention_delay'] ) && ctype_digit( $settings['webmention_delay'] )
				? (int) $settings['webmention_delay']
				: 0,
			'webmention_facepile' => isset( $settings['webmention_facepile'] ) ? true : false,
			'cache_avatars'       => isset( $settings['cache_avatars'] ) ? true : false,
		);

		$webmention_post_types = array();

		if ( isset( $settings['webmention_post_types'] ) && is_array( $settings['webmention_post_types'] ) ) {
			foreach ( $settings['webmention_post_types'] as $post_type ) {
				if ( in_array( $post_type, array_keys( $this->get_post_types() ), true ) ) {
					$webmention_post_types[] = $post_type;
				}
			}
		}

		$options['webmention_post_types'] = $webmention_post_types;

		$this->options = array_merge( $this->options, $options );

		// Updated settings.
		return $this->options;
	}

	/**
	 * Handles submitted "Miscallaneous" options.
	 *
	 * @param  array $settings Settings as submitted through WP Admin.
	 * @return array           Options to be stored.
	 */
	public function sanitize_misc_settings( $settings ) {
		$options = array(
			'add_featured_images' => isset( $settings['add_featured_images'] ) ? true : false,
			'location_functions'  => isset( $settings['location_functions'] ) ? true : false,
			'weather_units'       => isset( $settings['weather_units'] ) && in_array( $settings['weather_units'], array( 'metric', 'imperial' ), true )
				? $settings['weather_units']
				: 'metric',
			'micropub'            => isset( $settings['micropub'] ) ? true : false,
			'parse_markdown'      => isset( $settings['parse_markdown'] ) ? true : false,
		);

		$this->options = array_merge( $this->options, $options );

		// Updated settings.
		return $this->options;
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
							<p class="description"><?php esc_html_e( 'Introduces a &ldquo;Context&rdquo; block that helps ensure replies, likes, etc., are microformatted correctly. More such &ldquo;IndieWeb&rdquo; blocks will surely follow!', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Microformats', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[add_mf2]" value="1" <?php checked( ! empty( $this->options['add_mf2'] ) ); ?>/> <?php esc_html_e( 'Enable microformats', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Adds microformats2 to your site&rsquo;s front end. Requires the active theme to support WordPress&rsquo; new Site Editor.', 'indieblocks' ); ?></p></td>
						</tr>
					</table>
				<?php endif; ?>

				<?php if ( 'post_types' === $active_tab ) : ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row" rowspan="2"><?php esc_html_e( 'Custom Post Types', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[enable_notes]" value="1" <?php checked( ! empty( $this->options['post_types'] ) || ! empty( $this->options['enable_notes'] ) ); ?>/> <?php esc_html_e( 'Enable &ldquo;Notes&rdquo;', 'indieblocks' ); ?></label>
							<p style="margin-inline-start: 1.25em;"><label><input type="checkbox" name="indieblocks_settings[notes_in_feed]" value="1" <?php checked( ! empty( $this->options['notes_in_feed'] ) ); ?>/> <?php esc_html_e( 'Include in main feed', 'indieblocks' ); ?></label>
							<br /><label><input type="checkbox" name="indieblocks_settings[notes_in_home]" value="1" <?php checked( ! empty( $this->options['notes_in_home'] ) ); ?>/> <?php esc_html_e( 'Show on blog page', 'indieblocks' ); ?></label>
							<br /><label><input type="checkbox" name="indieblocks_settings[default_taxonomies]" value="1" <?php checked( ! empty( $this->options['default_taxonomies'] ) ); ?>/> <?php esc_html_e( 'Enable categories and tags', 'indieblocks' ); ?></label></p></td>
						</tr>
						<tr valign="top">
							<td><label><input type="checkbox" name="indieblocks_settings[enable_likes]" value="1" <?php checked( ! empty( $this->options['post_types'] ) || ! empty( $this->options['enable_likes'] ) ); ?>/> <?php esc_html_e( 'Enable &ldquo;Likes&rdquo;', 'indieblocks' ); ?></label>
							<p style="margin-inline-start: 1.25em;"><label><input type="checkbox" name="indieblocks_settings[likes_in_feed]" value="1" <?php checked( ! empty( $this->options['likes_in_feed'] ) ); ?>/> <?php esc_html_e( 'Include in main feed', 'indieblocks' ); ?></label>
							<br /><label><input type="checkbox" name="indieblocks_settings[likes_in_home]" value="1" <?php checked( ! empty( $this->options['likes_in_home'] ) ); ?>/> <?php esc_html_e( 'Show on blog page', 'indieblocks' ); ?></label></p>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Random Slugs', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[random_slugs]" value="1" <?php checked( ! empty( $this->options['random_slugs'] ) ); ?>/> <?php esc_html_e( 'Generate random slugs', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( 'Autogenerate unique note and like slugs. Disable for WordPress&rsquo; default behavior.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Autogenerate Titles', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[automatic_titles]" value="1" <?php checked( ! empty( $this->options['automatic_titles'] ) ); ?>/> <?php esc_html_e( 'Automatically generate titles', 'indieblocks' ); ?></label>
							<p style="margin-inline-start: 1.25em;"><label><input type="checkbox" name="indieblocks_settings[like_and_bookmark_titles]" value="1" <?php checked( ! empty( $this->options['like_and_bookmark_titles'] ) ); ?>/> <?php esc_html_e( 'Have like and bookmark titles reflect linked (i.e., liked or bookmarked) pages', 'indieblocks' ); ?></label></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Hide Titles', 'indieblocks' ); ?></th>
							<td>
								<label><input type="checkbox" name="indieblocks_settings[hide_titles]" value="1" <?php checked( ! empty( $this->options['hide_titles'] ) ); ?>/> <?php esc_html_e( 'Hide note and like titles', 'indieblocks' ); ?></label>
								<p class="description"><?php esc_html_e( '(Experimental) Attempts to (visually) hide note and like titles, if you have enabled microformats and your theme supports the Full-Site Editor.', 'indieblocks' ); ?></p>
								<div style="margin-inline-start: 1.25em; margin-block-start: 0.25em;">
									<label><input type="checkbox" name="indieblocks_settings[unhide_like_and_bookmark_titles]" value="1" <?php checked( ! empty( $this->options['unhide_like_and_bookmark_titles'] ) ); ?>/> <?php esc_html_e( 'Exempt like and bookmark titles', 'indieblocks' ); ?></label>
									<p class="description"><?php _e( 'Do <em>not</em> hide not and bookmark titles, <em>and</em> have them link to the liked or bookmarked page.', 'indieblocks' ); // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction ?></p>
								</div>
							</td>
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
									<?php foreach ( self::PERMALINK_FORMATS as $i => $format ) : ?>
										<?php echo ( 0 !== $i ? '<br />' : '' ); ?><label><input type="radio" name="indieblocks_settings[permalink_format]" value="<?php echo esc_attr( $format ); ?>" <?php checked( isset( $this->options['permalink_format'] ) ? $this->options['permalink_format'] : '/%postname%/', $format ); ?> /> <code><?php echo esc_html( $this->get_example_permalink( $format ) ); ?></code></label>
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( '(Experimental) Set a custom note and like permalink format.', 'indieblocks' ); ?></p>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Feed Modifications', 'indieblocks' ); ?></th>
								<td><label><input type="checkbox" name="indieblocks_settings[modified_feeds]" value="1" <?php checked( ! empty( $this->options['modified_feeds'] ) ); ?>/> <?php esc_html_e( 'Modify feeds', 'indieblocks' ); ?></label>
								<p class="description"><?php esc_html_e( '(Experimental) Remove note and like titles from RSS and Atom feeds. This may help feed readers recognize them as &ldquo;notes,&rdquo; but might conflict with existing custom feed templates.', 'indieblocks' ); ?></p></td>
							</tr>
						<?php endif; ?>
					</table>
				<?php endif; ?>

				<?php if ( 'webmention' === $active_tab ) : ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Webmention', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[webmention]" value="1" <?php checked( ! empty( $this->options['webmention'] ) ); ?>/> <?php esc_html_e( 'Enable Webmention', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Automatically notify pages you&rsquo;ve linked to, and allow other websites to do the same.', 'indieblocks' ); ?></p></td>
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
							<th scope="row"><?php esc_html_e( 'Avatars', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[cache_avatars]" value="1" <?php checked( ! empty( $this->options['cache_avatars'] ) ); ?>/> <?php esc_html_e( 'Cache avatars', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Attempt to locally cache avatars. Uncheck to disable webmention avatars altogether.', 'indieblocks' ); ?></p></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Facepile', 'indieblocks' ); ?></th>
							<td><label><input type="checkbox" name="indieblocks_settings[webmention_facepile]" value="1" <?php checked( ! empty( $this->options['webmention_facepile'] ) ); ?>/> <?php esc_html_e( '&ldquo;Facepile&rdquo; bookmarks, likes, and reposts', 'indieblocks' ); ?></label>
							<p class="description"><?php esc_html_e( '(Experimental) Display bookmarks, likes, and reposts separate from &ldquo;regular&rdquo; comments.', 'indieblocks' ); ?></p></td>
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
							<p class="description"><?php esc_html_e( '(Experimental) Add basic location and weather data&mdash;not yet shown on the front end&mdash;to posts.', 'indieblocks' ); ?></p></td>
						</tr>
						<!--
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Weather Units', 'indieblocks' ); ?></th>
							<td><label><input type="radio" name="indieblocks_settings[weather_units]" value="metric" <?php checked( empty( $this->options['weather_units'] ) || 'metric' === $this->options['weather_units'] ); ?>/> <?php esc_html_e( 'Metric', 'indieblocks' ); ?></label><br />
							<label><input type="radio" name="indieblocks_settings[weather_units]" value="imperial" <?php checked( ! empty( $this->options['weather_units'] ) && 'imperial' === $this->options['weather_units'] ); ?>/> <?php esc_html_e( 'Imperial', 'indieblocks' ); ?></label></td>
						</tr>
						//-->
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
	 * Generates example permalinks for use in the settings table.
	 *
	 * @param  string $format Permalink format.
	 * @return string         Example permalink.
	 */
	private function get_example_permalink( $format ) {
		$example_front = __( 'notes', 'indieblocks' );

		if ( ! empty( $this->options['enable_notes'] ) ) {
			$post_type = get_post_type_object( 'indieblocks_note' );

			if ( ! empty( $post_type->rewrite['slug'] ) ) {
				$example_front = $post_type->rewrite['slug'];
			}
		} elseif ( ! empty( $this->options['enable_likes'] ) ) {
			$post_type = get_post_type_object( 'indieblocks_like' );

			if ( ! empty( $post_type->rewrite['slug'] ) ) {
				$example_front = $post_type->rewrite['slug'];
			}
		}

		return '/' . $example_front . str_replace(
			array( '%year%', '%monthnum%', '%day%', '%postname%' ),
			array( date( 'Y' ), date( 'm' ), date( 'd' ), __( 'sample-post', 'indieblocks' ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$format
		);
	}

	/**
	 * Returns post types we may want to enable Webmention for.
	 */
	private function get_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		return $post_types;
	}
}
