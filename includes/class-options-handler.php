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
		'enable_blocks'       => true,
		'post_types'          => false,
		'default_taxonomies'  => false,
		'custom_menu_order'   => true,
		'automatic_titles'    => false,
		'random_slugs'        => false,
		'include_in_search'   => false,
		'modified_feeds'      => false,
		'add_featured_images' => false,
		'location_functions'  => false,
		'add_mf2'             => false,
		'micropub'            => true,
		'webmention'          => false,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options = get_option( 'indieblocks_settings', $this->options );
	}

	/**
	 * Interacts with WordPress's Plugin API.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'create_menu' ) );
		add_action( 'update_option_indieblocks_settings', array( $this, 'flush_permalinks' ), 10, 2 );
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

		register_setting(
			'indieblocks-settings-group',
			'indieblocks_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	/**
	 * Handles submitted options.
	 *
	 * @param  array $settings Settings as submitted through WP Admin.
	 * @return array Options to be stored.
	 */
	public function sanitize_settings( $settings ) {
		$orig_options  = get_option( 'indieblocks_settings', array() );
		$this->options = array(
			'enable_blocks'       => isset( $settings['enable_blocks'] ) ? true : false,
			'post_types'          => isset( $settings['post_types'] ) ? true : false,
			'default_taxonomies'  => isset( $settings['default_taxonomies'] ) ? true : false,
			'custom_menu_order'   => isset( $settings['custom_menu_order'] ) ? true : false,
			'automatic_titles'    => isset( $settings['automatic_titles'] ) ? true : false,
			'random_slugs'        => isset( $settings['random_slugs'] ) ? true : false,
			'include_in_search'   => isset( $settings['include_in_search'] ) ? true : false,
			'modified_feeds'      => isset( $settings['modified_feeds'] ) ? true : false,
			'add_featured_images' => isset( $settings['add_featured_images'] ) ? true : false,
			'location_functions'  => isset( $settings['location_functions'] ) ? true : false,
			'add_mf2'             => isset( $settings['add_mf2'] ) ? true : false,
			'micropub'            => isset( $settings['micropub'] ) ? true : false,
			'webmention'          => isset( $settings['webmention'] ) ? true : false,
		);

		// Updated settings.
		return $this->options;
	}

	/**
	 * Flushes permalinks when needed.
	 *
	 * Permalinks are flushed both here and whenever the plugin's (de)activated.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 */
	public function flush_permalinks( $old_value, $new_value ) {
		$flush = false;

		if ( empty( $old_value['post_types'] ) && ! empty( $new_value['post_types'] ) ) {
			$flush = true;
		}
		if ( empty( $new_value['post_types'] ) && ! empty( $old_value['post_types'] ) ) {
			$flush = true;
		}
		if ( empty( $old_value['modified_feeds'] ) && ! empty( $new_value['modified_feeds'] ) ) {
			$flush = true;
		}
		if ( empty( $new_value['modified_feeds'] ) && ! empty( $old_value['modified_feeds'] ) ) {
			$flush = true;
		}

		if ( $flush ) {
			flush_rewrite_rules();
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
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Blocks', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[enable_blocks]" value="1" <?php checked( ! empty( $this->options['enable_blocks'] ) ); ?>/> <?php esc_html_e( 'Enable blocks?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Introduces a &ldquo;Context&rdquo; block that helps ensure replies, likes, etc., are microformatted correctly. More such &ldquo;IndieWeb&rdquo; blocks will surely follow!', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Post Types', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[post_types]" value="1" <?php checked( ! empty( $this->options['post_types'] ) ); ?>/> <?php esc_html_e( 'Enable short-form post types?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Enable note and like post types. If the &ldquo;Blocks&rdquo; option is enabled, too, this will register a starter block template for notes and likes.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Taxonomies', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[default_taxonomies]" value="1" <?php checked( ! empty( $this->options['default_taxonomies'] ) ); ?>/> <?php esc_html_e( 'Enable Note categories and tags?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Requires &ldquo;Post Types.&rdquo;) Enable note categories and tags.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Custom Menu Order', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[custom_menu_order]" value="1" <?php checked( ! empty( $this->options['custom_menu_order'] ) ); ?>/> <?php esc_html_e( 'Custom menu order?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Requires &ldquo;Post Types.&rdquo;) Group (regular) posts, notes, and likes at the top of WordPress&rsquo; admin menu.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Autogenerate Titles', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[automatic_titles]" value="1" <?php checked( ! empty( $this->options['automatic_titles'] ) ); ?>/> <?php esc_html_e( 'Automatically generate titles?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Requires &ldquo;Post Types.&rdquo;) Autogenerate note and like titles. (Your theme should probably hide these, still.)', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Random Slugs', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[random_slugs]" value="1" <?php checked( ! empty( $this->options['random_slugs'] ) ); ?>/> <?php esc_html_e( 'Generate random slugs?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Requires &ldquo;Post Types.&rdquo;) Autogenerate note and like slugs. Disable for WordPress&rsquo; default behavior.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Feed Modifications', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[modified_feeds]" value="1" <?php checked( ! empty( $this->options['modified_feeds'] ) ); ?>/> <?php esc_html_e( 'Modify feeds?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Disables all but RSS feeds, and, depending on you permalinks settings, will include notes in your main feed and set up a separate post-only feed.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Location and Weather', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[location_functions]" value="1" <?php checked( ! empty( $this->options['location_functions'] ) ); ?>/> <?php esc_html_e( 'Enable location functions?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Add basic location and weather data to posts.', 'indieblocks' ); ?></p></td>
					</tr>
					<!--
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Include in Search', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[include_in_search]" value="1" <?php checked( ! empty( $this->options['include_in_search'] ) ); ?>/> <?php esc_html_e( 'Include Notes in search?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Requires &ldquo;Post Types.&rdquo;) Include notes (and pages, but not likes) in search results.', 'indieblocks' ); ?></p></td>
					</tr>
					//-->
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Microformats', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[add_mf2]" value="1" <?php checked( ! empty( $this->options['add_mf2'] ) ); ?>/> <?php esc_html_e( 'Enable microformats2?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Experimental) Adds microformats2 to this site&rsquo;s front end. Requires the active theme to support WordPress&rsquo; new Site Editor.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Micropub', 'indieblocks' ); ?></label></th>
						<td><label><input type="checkbox" name="indieblocks_settings[micropub]" value="1" <?php checked( ! empty( $this->options['micropub'] ) ); ?>/> <?php esc_html_e( 'Deeper Micropub integration?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Adds post type and taxonomy data to responses to Micropub &ldquo;config&rdquo; queries.', 'indieblocks' ); ?></p></td>
					</tr>
				</table>
				<p class="submit"><?php submit_button( __( 'Save Changes' ), 'primary', 'submit', false ); ?></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns this plugin's settings.
	 *
	 * @return array This plugin's settings.
	 */
	public function get_options() {
		return $this->options;
	}
}
