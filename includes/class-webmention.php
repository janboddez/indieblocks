<?php
/**
 * Main plugin class.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Main Webmention class.
 */
class Webmention {
	/**
	 * Database table version.
	 *
	 * @var string $db_version Database table version, in case we ever want to upgrade its structure.
	 */
	private static $db_version = '1.0';

	/**
	 * Registers hook callbacks.
	 */
	public static function register() {
		// Deactivation and uninstall hooks.
		register_activation_hook( dirname( __DIR__ ) . '/indieblocks.php', array( __CLASS__, 'activate' ) );
		register_deactivation_hook( dirname( __DIR__ ) . '/indieblocks.php', array( __CLASS__, 'deactivate' ) );
		register_uninstall_hook( dirname( __DIR__ ) . '/indieblocks.php', array( __CLASS__, 'uninstall' ) );

		// Schedule WP-Cron job.
		add_action( 'init', array( __CLASS__, 'schedule_cron' ) );

		add_action( 'rest_api_init', array( Webmention_Receiver::class, 'register_route' ) );
		add_action( 'process_webmentions', array( Webmention_Receiver::class, 'process_webmentions' ) );
		add_action( 'add_meta_boxes_comment', array( Webmention_Receiver::class, 'add_meta_box' ) );
		add_action( 'wp_head', array( Webmention_Receiver::class, 'webmention_link' ) );

		add_action( 'transition_post_status', array( Webmention_Sender::class, 'schedule_webmention' ), 10, 3 );
		add_action( 'webmention_comments_send', array( Webmention_Sender::class, 'send_webmention' ) );
		add_action( 'add_meta_boxes', array( Webmention_Sender::class, 'add_meta_box' ) );
	}

	/**
	 * Sets WordPress up for use with this plugin.
	 */
	public static function activate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'webmention_comments';
		$charset_collate = $wpdb->get_charset_collate();

		// Create database table.
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
			source varchar(191) DEFAULT '' NOT NULL,
			post_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
			ip varchar(100) DEFAULT '' NOT NULL,
			status varchar(20) DEFAULT '' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );

		// Store current database version.
		add_option( 'webmention_comments_db_version', static::$db_version );
	}

	/**
	 * Ensures the `process_webmentions` event keeps running.
	 */
	public static function schedule_cron() {
		// Set up cron event for Webmention processing.
		if ( false === wp_next_scheduled( 'process_webmentions' ) ) {
			wp_schedule_event( time(), 'hourly', 'process_webmentions' );
		}
	}

	/**
	 * Stops the cron job upon deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'process_webmentions' );
	}

	/**
	 * Cleans up for real during uninstall.
	 */
	public static function uninstall() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'webmention_comments';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'webmention_comments_db_version' );
	}
}
