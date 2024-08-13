<?php
/**
 * Main plugin class.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

use IndieBlocks\Image_Proxy\Image_Proxy;
use IndieBlocks\Webmention\Webmention;

class Plugin {
	/**
	 * Plugin version.
	 */
	const PLUGIN_VERSION = '0.13.1';

	/**
	 * Options handler.
	 *
	 * @var Options_Handler $options_handler Options handler.
	 */
	private $options_handler;

	/**
	 * This class's single instance.
	 *
	 * @var Plugin $instance Plugin instance.
	 */
	private static $instance;

	/**
	 * Returns the single instance of this class.
	 *
	 * @return Plugin This class's single instance.
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
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			// Register our new CLI commands.
			\WP_CLI::add_command( 'indieblocks', Commands\Commands::class );
		}

		// Enable i18n.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Run database migrations, etc.
		register_activation_hook( dirname( __DIR__ ) . '/indieblocks.php', array( $this, 'activate' ) );
		register_deactivation_hook( dirname( __DIR__ ) . '/indieblocks.php', array( $this, 'deactivate' ) );
		register_uninstall_hook( dirname( __DIR__ ) . '/indieblocks.php', array( Webmention::class, 'uninstall' ) );

		// Bit of a trick to "forget" about older keys.
		add_filter( 'option_indieblocks_settings', array( Options_Handler::class, 'prep_options' ) );

		// Set up the settings page.
		$this->options_handler = new Options_Handler();
		$this->options_handler->register();

		// Load "modules." We hook these up to `plugins_loaded` rather than
		// directly call the `register()` methods. This allows other plugins to
		// more easily unhook them.
		$options = $this->options_handler->get_options();

		if ( ! empty( $options['webmention'] ) ) {
			add_action( 'plugins_loaded', array( Webmention::class, 'register' ) );
		}

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
		add_action( 'plugins_loaded', array( Post_Types::class, 'register' ) );

		// Everything Site Editor/theme microformats.
		if ( $this->theme_supports_blocks() ) {
			add_filter( 'pre_get_avatar', array( Theme_Mf2::class, 'get_avatar_html' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( Webmention::class, 'enqueue_styles' ), 10, 3 );

			if ( ! empty( $options['add_mf2'] ) ) {
				add_action( 'plugins_loaded', array( Theme_Mf2::class, 'register' ) );
			}
		}

		// Micropub hook callbacks.
		add_action( 'plugins_loaded', array( Micropub_Compat::class, 'register' ) );

		// Link preview cards.
		if ( ! empty( $options['preview_cards'] ) ) {
			add_action( 'plugins_loaded', array( Preview_Cards::class, 'register' ) );
		}

		add_action( 'rest_api_init', array( Image_Proxy::class, 'register' ) );
	}

	/**
	 * Registers permalinks on activation.
	 *
	 * We flush permalinks every time the post types or feed options are
	 * changed, and each time the plugin is (de)activated.
	 */
	public function activate() {
		flush_permalinks();
	}

	/**
	 * Deschedules the Webmention cron job, and resets permalinks on plugin
	 * deactivation.
	 */
	public function deactivate() {
		Webmention::deactivate();
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
