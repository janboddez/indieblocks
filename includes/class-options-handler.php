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
		'hide_titles'         => false,
		'random_slugs'        => false,
		'date_archives'       => false,
		'notes_in_feed'       => true,
		'likes_in_feed'       => false,
		'notes_in_home'       => false,
		'likes_in_home'       => false,
		'modified_feeds'      => false,
		'add_featured_images' => false,
		'location_functions'  => false,
		'add_mf2'             => false,
		'micropub'            => false,
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
	}

	/**
	 * Flushes permalinks upon activation and whenever settings are saved.
	 */
	public function flush_permalinks() {
		Post_Types::register_post_types();
		Post_Types::create_date_archives();
		Feeds::create_post_feed();
		flush_rewrite_rules();
	}

	/**
	 * Registers the plugin settings page.
	 */
	public function create_menu() {
		if ( delete_transient( 'indieblocks_flush_permalinks' ) ) {
			$this->flush_permalinks();
		}

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
		$this->options = array(
			'enable_blocks'       => isset( $settings['enable_blocks'] ) ? true : false,
			'enable_notes'        => isset( $settings['enable_notes'] ) ? true : false,
			'notes_in_feed'       => isset( $settings['notes_in_feed'] ) ? true : false,
			'notes_in_home'       => isset( $settings['notes_in_home'] ) ? true : false,
			'default_taxonomies'  => isset( $settings['default_taxonomies'] ) ? true : false,
			'enable_likes'        => isset( $settings['enable_likes'] ) ? true : false,
			'likes_in_feed'       => isset( $settings['likes_in_feed'] ) ? true : false,
			'likes_in_home'       => isset( $settings['likes_in_home'] ) ? true : false,
			'custom_menu_order'   => isset( $settings['custom_menu_order'] ) ? true : false,
			'automatic_titles'    => isset( $settings['automatic_titles'] ) ? true : false,
			'hide_titles'         => isset( $settings['hide_titles'] ) ? true : false,
			'random_slugs'        => isset( $settings['random_slugs'] ) ? true : false,
			'date_archives'       => isset( $settings['date_archives'] ) ? true : false,
			'modified_feeds'      => isset( $settings['modified_feeds'] ) ? true : false,
			'add_featured_images' => isset( $settings['add_featured_images'] ) ? true : false,
			'location_functions'  => isset( $settings['location_functions'] ) ? true : false,
			'add_mf2'             => isset( $settings['add_mf2'] ) ? true : false,
			'micropub'            => isset( $settings['micropub'] ) ? true : false,
			'webmention'          => isset( $settings['webmention'] ) ? true : false,
		);

		// Instruct IndieBlocks to flush permalinks during the next request.
		set_transient( 'indieblocks_flush_permalinks', true );

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
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Blocks', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[enable_blocks]" value="1" <?php checked( ! empty( $this->options['enable_blocks'] ) ); ?>/> <?php esc_html_e( 'Enable blocks?', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Introduces a &ldquo;Context&rdquo; block that helps ensure replies, likes, etc., are microformatted correctly. More such &ldquo;IndieWeb&rdquo; blocks will surely follow!', 'indieblocks' ); ?></p></td>
					</tr>
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
						<th scope="row"><?php esc_html_e( 'Custom Menu Order', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[custom_menu_order]" value="1" <?php checked( ! empty( $this->options['custom_menu_order'] ) ); ?>/> <?php esc_html_e( 'Group posts, notes, and likes.', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Group (regular) posts, notes, and likes at the top of WordPress&rsquo; admin menu.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Autogenerate Titles', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[automatic_titles]" value="1" <?php checked( ! empty( $this->options['automatic_titles'] ) ); ?>/> <?php esc_html_e( 'Automatically generate titles', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Autogenerate note and like titles. (Your theme should probably hide these, still.)', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Random Slugs', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[random_slugs]" value="1" <?php checked( ! empty( $this->options['random_slugs'] ) ); ?>/> <?php esc_html_e( 'Generate random slugs', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( 'Autogenerate note and like slugs. Disable for WordPress&rsquo; default behavior.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Date-Based Archives', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[date_archives]" value="1" <?php checked( ! empty( $this->options['date_archives'] ) ); ?>/> <?php esc_html_e( 'Enable date-based archives', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Experimental) Enable year, month, and day archives for notes and likes.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Microformats', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[add_mf2]" value="1" <?php checked( ! empty( $this->options['add_mf2'] ) ); ?>/> <?php esc_html_e( 'Enable microformats', 'indieblocks' ); ?></label>
						<p style="margin-inline-start: 1.25em;"><label><input type="checkbox" name="indieblocks_settings[hide_titles]" value="1" <?php checked( ! empty( $this->options['hide_titles'] ) ); ?>/> <?php esc_html_e( 'Hide note and like titles', 'indieblocks' ); ?></label></p>
						<p class="description"><?php esc_html_e( '(Experimental) Adds microformats2 to your site&rsquo;s front end. Requires the active theme to support WordPress&rsquo; new Site Editor.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Feed Modifications', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[modified_feeds]" value="1" <?php checked( ! empty( $this->options['modified_feeds'] ) ); ?>/> <?php esc_html_e( 'Modify feeds', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Experimental) Remove note and like titles from RSS and Atom feeds. This may help feed readers recognize them as &ldquo;notes,&rdquo; but might conflict with existing custom feed templates.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Location and Weather', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[location_functions]" value="1" <?php checked( ! empty( $this->options['location_functions'] ) ); ?>/> <?php esc_html_e( 'Enable location functions', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Experimental) Add basic location and weather data&mdash;not yet shown on the front end&mdash;to posts.', 'indieblocks' ); ?></p></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Micropub', 'indieblocks' ); ?></th>
						<td><label><input type="checkbox" name="indieblocks_settings[micropub]" value="1" <?php checked( ! empty( $this->options['micropub'] ) ); ?>/> <?php esc_html_e( 'Deeper Micropub integration', 'indieblocks' ); ?></label>
						<p class="description"><?php esc_html_e( '(Experimental) Add post type and category data to responses to Micropub &ldquo;config&rdquo; queries.', 'indieblocks' ); ?></p></td>
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
