<?php

namespace IndieBlocks\Webmention;

/**
 * Main Webmention class.
 */
class Webmention {
	/**
	 * Database table version.
	 */
	const DB_VERSION = '3.0';

	/**
	 * Registers hook callbacks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'init' ) );

		Webmention_Sender::register();
		Webmention_Receiver::register();
	}

	/**
	 * Ensures the `indieblocks_process_webmentions` event keeps running.
	 */
	public static function init() {
		// Set up cron event for Webmention processing.
		if ( false === wp_next_scheduled( 'indieblocks_process_webmentions' ) ) {
			wp_schedule_event( time(), 'hourly', 'indieblocks_process_webmentions' );
		}

		// Run database migrations, if applicable.
		static::migrate();
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

		$table_name = $wpdb->prefix . 'indieblocks_webmentions';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'indieblocks_webmention_db_version' );
	}

	/**
	 * Run database migrations.
	 *
	 * This should run whenever the plugin is first activated, for now,
	 * regardless of whether Webmention's actually enabled or not.
	 */
	public static function migrate() {
		if ( get_option( 'indieblocks_webmention_db_version' ) !== self::DB_VERSION ) {
			global $wpdb;

			$table_name      = $wpdb->prefix . 'indieblocks_webmentions';
			$charset_collate = $wpdb->get_charset_collate();

			// Create database table.
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
				source varchar(191) DEFAULT '' NOT NULL,
				target varchar(191) DEFAULT '' NOT NULL,
				post_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
				ip varchar(100) DEFAULT '' NOT NULL,
				status varchar(20) DEFAULT '' NOT NULL,
				created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				modified_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				PRIMARY KEY (id)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			dbDelta( $sql );

			// Store current database version.
			update_option( 'indieblocks_webmention_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Returns post types that support Webmention.
	 *
	 * @return array Supported post types.
	 */
	public static function get_supported_post_types() {
		$options = \IndieBlocks\get_options();

		$supported_post_types = isset( $options['webmention_post_types'] ) ? $options['webmention_post_types'] : array( 'post', 'indieblocks_note' );
		$supported_post_types = (array) apply_filters( 'indieblocks_webmention_post_types', $supported_post_types );

		return $supported_post_types;
	}

	/**
	 * Excludes bookmarks, likes and reposts from comment queries.
	 *
	 * @todo: Use some kind of "comment taxonomy" instead?
	 *
	 * @param  WP_Comment_Query $query Comment count.
	 */
	public static function comment_query( $query ) {
		if ( is_admin() ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		if ( isset( $query->query_vars['count'] ) && true === $query->query_vars['count'] ) {
			return;
		}

		global $post;

		// Exclude likes and reposts by the Webmention plugin.
		$query->query_vars['type__not_in'] = apply_filters( 'indieblocks_facepile_kinds', array( 'bookmark', 'like', 'repost' ), ! empty( $post->ID ) ? $post->ID : null );

		// Exclude likes and reposts. The bad thing is, this screws up comment counts.
		$query->query_vars['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => 'indieblocks_webmention_kind',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'indieblocks_webmention_kind',
				'compare' => 'NOT IN',
				'value'   => apply_filters( 'indieblocks_facepile_kinds', array( 'bookmark', 'like', 'repost' ), ! empty( $post->ID ) ? $post->ID : null ),
			),
		);
	}

	/**
	 * Returns a post's comment count, sans bookmarks, likes and reposts.
	 *
	 * Reason we override the comment count is our `pre_get_comments` filter
	 * screws up counts!
	 *
	 * @param  int $count   Comment count.
	 * @param  int $post_id Post ID.
	 * @return int          Comment count, excluding bookmarks, likes and reposts.
	 */
	public static function comment_count( $count, $post_id ) {
		if ( is_admin() ) {
			return $count;
		}

		if ( ! is_singular() ) {
			return $count;
		}

		$comment_types = (array) apply_filters( 'indieblocks_facepile_kinds', array( 'bookmark', 'like', 'repost' ), $post_id );
		$comment_types = array_values( $comment_types ); // Ensure `$comment_types` has numeric keys.

		global $wpdb;

		$comments    = $wpdb->prefix . 'comments';
		$commentmeta = $wpdb->prefix . 'commentmeta';

		$sql = "SELECT COUNT(c.comment_ID) FROM $comments AS c
			LEFT JOIN $commentmeta AS m ON c.comment_ID = m.comment_id AND m.meta_key = 'indieblocks_webmention_kind'
			WHERE c.comment_post_ID = %d
			AND (c.comment_approved = '1' OR (c.comment_approved = '0' AND c.user_id = %d AND c.user_id <> 0))
			AND (c.comment_parent = 0 OR EXISTS (
				SELECT 1 FROM $comments AS p
				WHERE p.comment_ID = c.comment_parent AND (p.comment_approved = '1' OR (p.comment_approved = '0' AND p.user_id = %d AND p.user_id <> 0))
			))";

		$sql = $wpdb->prepare( $sql, $post_id, get_current_user_id(), get_current_user_id() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $comment_types ) ) {
			$sql .= $wpdb->prepare(
				sprintf(
					' AND c.comment_type NOT IN (%1$s) AND (m.meta_key IS NULL OR m.meta_value NOT IN (%1$s))', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
					implode( ',', array_fill( 0, count( $comment_types ), '%s' ) )
				),
				array_merge( $comment_types, $comment_types ) // Need two occurrences of this!
			);
		}

		$count = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$count = is_null( $count )
			? 0
			: (string) $count; // Has to be a string, or literal `0`!

		return $count;
	}

	/**
	 * Fixes the display of comments added by the Webmention plugin, even after
	 * it is deactivated.
	 *
	 * @link https://github.com/pfefferle/wordpress-webmention/pull/457
	 */
	public static function enqueue_styles() {
		$current_screen = get_current_screen();
		if ( isset( $current_screen->base ) && 'dashboard' === $current_screen->base ) {
			wp_enqueue_style( 'indieblocks-webmention-admin', plugins_url( '/assets/admin.css', dirname( __DIR__ ) ), array(), \IndieBlocks\Plugin::PLUGIN_VERSION );
		}
	}

	/**
	 * Automatically inserts a Facepile block in front of `core/comments`.
	 *
	 * @param  array                             $hooked_blocks Hooked block types.
	 * @param  string                            $position      Relative position.
	 * @param  string                            $anchor_block  Anchor block type.
	 * @param  \WP_Block_Template|\WP_Post|array $context       Block template, template part, `wp_navigation` post type, or pattern the anchor block belongs to.
	 * @return array                                            Modified hook block types.
	 */
	public static function hook_facepile_block( $hooked_blocks, $position, $anchor_block, $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 'core/comments' === $anchor_block && 'before' === $position ) {
			$hooked_blocks[] = 'indieblocks/facepile';
		}

		return $hooked_blocks;
	}

	/**
	 * Sets hooked Facepile blocks' inner content and the like.
	 *
	 * @param  array|null                        $parsed_hooked_block Parsed block array for the given hooked block type, or `null`.
	 * @param  string                            $hooked_block        Hooked block type.
	 * @param  string                            $position            Relative position of the hooked block.
	 * @param  array                             $parsed_anchor_block Anchor block, in parsed block array format.
	 * @param  \WP_Block_Template|\WP_Post|array $context             Block template, template part, `wp_navigation` post type, or pattern the anchor block belongs to.
	 * @return array                                                  Modified parsed block array.
	 */
	public static function modify_hooked_facepile_block( $parsed_hooked_block, $hooked_block, $position, $parsed_anchor_block, $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( null === $parsed_hooked_block ) {
			return $parsed_hooked_block;
		}

		if (
			'core/comments' === $parsed_anchor_block['blockName'] &&
			'before' === $position
		) {
			// Note that we don't specifiy font size or spacing attributes.
			$parsed_hooked_block = array(
				'blockName'    => 'indieblocks/facepile',
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/heading',
						'innerHTML'    => '<h2 class="wp-block-heading">' . esc_html__( 'Likes, Bookmarks, and Reposts', 'indieblocks' ) . '</h2>',
						'innerContent' => array(
							'<h2 class="wp-block-heading">' . esc_html__( 'Likes, Bookmarks, and Reposts', 'indieblocks' ) . '</h2>',
						),
					),
					array(
						'blockName' => 'indieblocks/facepile-content',
						'attrs'     => array(
							'avatarSize'      => 3,
							'icons'           => true,
							'backgroundColor' => '#fff',
						),
					),
				),
				'innerHTML'    => '<div class="wp-block-indieblocks-facepile"></div>',
				'innerContent' => array(
					'<div class="wp-block-indieblocks-facepile">',
					null,
					'',
					null,
					'</div>',
				),
			);
		}

		return $parsed_hooked_block;
	}
}
