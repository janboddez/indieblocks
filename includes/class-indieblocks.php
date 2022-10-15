<?php
/**
 * Main plugin class.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Main IndieBlocks class.
 */
class IndieBlocks {
	/**
	 * This class's single instance.
	 *
	 * @var IndieBlocks $instance Plugin instance.
	 */
	private static $instance;

	/**
	 * Options handler.
	 *
	 * @var Options_Handler $options_handler Options handler.
	 */
	private $options_handler;

	/**
	 * Returns the single instance of this class.
	 *
	 * @return IndieBlocks This class's single instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks and such.
	 */
	public function register() {
		// Enable i18n.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Run database migrations, etc.
		register_activation_hook( dirname( __DIR__ ) . '/indieblocks.php', array( $this, 'activate' ) );
		register_deactivation_hook( dirname( __DIR__ ) . '/indieblocks.php', array( $this, 'deactivate' ) );

		// Set up the settings page.
		$this->options_handler = new Options_Handler();
		$this->options_handler->register();

		// Load "modules." We hook these up to `plugins_loaded` rather than
		// directly call the `register()` methods. This allows other plugins to
		// more easily unhook them.
		$options = $this->options_handler->get_options();

		// Gutenberg blocks.
		if ( ! empty( $options['enable_blocks'] ) ) {
			add_action( 'plugins_loaded', array( Blocks::class, 'register' ) );
		}

		// `Feeds::register()` runs its own option check.
		add_action( 'plugins_loaded', array( Feeds::class, 'register' ) );

		// Location and weather functions.
		if ( ! empty( $options['location_functions'] ) ) {
			add_action( 'plugins_loaded', array( Location::class, 'register' ) );
		}

		// Custom Post Types.
		if ( ! empty( $options['post_types'] ) ) {
			add_action( 'plugins_loaded', array( Post_Types::class, 'register' ) );
		}

		// Everything Site Editor/theme microformats.
		if ( ! empty( $options['add_mf2'] ) && $this->theme_supports_blocks() ) {
			add_action( 'plugins_loaded', array( Theme_Mf2::class, 'register' ) );
		}

		// Micropub hook callbacks.
		add_action( 'plugins_loaded', array( Micropub_Compat::class, 'register' ) );
	}

	/**
	 * Registers permalinks on activation.
	 *
	 * We flush permalinks every time the post types or feed options are
	 * changed, and each time the plugin is (de)activated.
	 */
	public function activate() {
		$options = $this->options_handler->get_options();

		if ( ! empty( $options['post_types'] ) ) {
			Post_Types::register_post_types();
		}

		if ( ! empty( $options['modified_feeds'] ) ) {
			Feeds::create_post_feed();
		}

		flush_rewrite_rules();
	}

	/**
	 * Flushes permalinks on deactivation.
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Enable i18n.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'indieblocks', false, basename( dirname( __DIR__ ) ) . '/languages' );
	}

	/**
	 * Returns our options handler.
	 *
	 * @return Options_Handler Options handler.
	 */
	public function get_options_handler() {
		return $this->options_handler;
	}

	/**
	 * Whether the active theme supports blocks.
	 */
	protected function theme_supports_blocks() {
		return is_readable( get_template_directory() . '/templates/index.html' ) || current_theme_supports( 'add_block_template_part_support' );
	}
}
