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
	 */
	const DB_VERSION = '1.0';

	/**
	 * Registers hook callbacks.
	 */
	public static function register() {
		// Schedule WP-Cron job.
		add_action( 'init', array( __CLASS__, 'schedule_cron' ) );

		add_action( 'add_meta_boxes', array( Webmention_Sender::class, 'add_meta_box' ) );
		add_action( 'transition_post_status', array( Webmention_Sender::class, 'schedule_webmention' ), 10, 3 );
		add_action( 'indieblocks_webmention_send', array( Webmention_Sender::class, 'send_webmention' ) );

		add_action( 'add_meta_boxes_comment', array( Webmention_Receiver::class, 'add_meta_box' ) );
		add_action( 'rest_api_init', array( Webmention_Receiver::class, 'register_route' ) );
		add_action( 'wp_head', array( Webmention_Receiver::class, 'webmention_link' ) );
		add_action( 'indieblocks_process_webmentions', array( Webmention_Receiver::class, 'process_webmentions' ) );
	}

	/**
	 * Run database migrations.
	 *
	 * This should run whenever the plugin is first activated, for now,
	 * regardless of whether Webmention's actually enabled or not.
	 */
	public static function migrate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'indieblocks_webmentions';
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
		add_option( 'indieblocks_webmention_db_version', self::DB_VERSION );
	}

	/**
	 * Ensures the `indieblocks_process_webmentions` event keeps running.
	 */
	public static function schedule_cron() {
		// Set up cron event for Webmention processing.
		if ( false === wp_next_scheduled( 'indieblocks_process_webmentions' ) ) {
			wp_schedule_event( time(), 'hourly', 'indieblocks_process_webmentions' );
		}
	}

	/**
	 * Stops the cron job upon deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'indieblocks_process_webmentions' );
	}

	/**
	 * Cleans up for real during uninstall. Note that general plugin settings
	 * are _not_ deleted.
	 */
	public static function uninstall() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'indieblocks_webmention';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'indieblocks_webmention_db_version' );
	}
}
