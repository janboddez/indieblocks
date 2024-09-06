<?php

namespace IndieBlocks\Commands;

use function IndieBlocks\get_meta;
use function IndieBlocks\get_options;
use function IndieBlocks\store_image;
use function IndieBlocks\update_meta;

class Commands {
	/**
	 * Replaces a webmention avatar by a "local" copy of the same file.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The comment ID.
	 *
	 * [--w=<width>]
	 * : The target width.
	 *
	 * [--h=<height>]
	 * : The target height.
	 *
	 * [--dir=<dir>]
	 * : The destination folder, relative to, typically, `wp-content/uploads`.
	 *
	 * [--author=<author>]
	 * : Author URL.
	 *
	 * [--type=<type>]
	 * : `ap` for ActivityPub.
	 *
	 * @subcommand cache-avatar
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args "Associated" arguments.
	 */
	public function cache_avatar( $args, $assoc_args ) {
		$options = get_options();
		if ( empty( $options['cache_avatars'] ) ) {
			\WP_CLI::error( 'Avatar cache disabled.' );
			return;
		}

		$comment_id = trim( $args[0] );
		$comment    = get_comment( $comment_id );

		if ( ! $comment instanceof \WP_Comment ) {
			\WP_CLI::error( 'Invalid comment.' );
			return;
		}

		if ( isset( $assoc_args['type'] ) && 'ap' === $assoc_args['type'] ) {
			// This is a special case, to locally save avatars stored by the
			// ActivityPub plugin.
			$url = get_meta( $comment, 'avatar_url' );
			$dir = 'activitypub-avatars';
		} elseif ( isset( $assoc_args['type'] ) && 'wm' === $assoc_args['type'] ) {
			// This is a special case, to locally save avatars stored by the
			// ActivityPub plugin.
			$url = get_meta( $comment, 'avatar' );
			$dir = 'indieblocks-avatars'; // We're okay reusing this folder.
		} else {
			$url = get_meta( $comment, 'indieblocks_webmention_avatar' );
			$dir = isset( $assoc_args['dir'] )
				? $assoc_args['dir']
				: 'indieblocks-avatars';
			$dir = sanitize_title( $dir );
		}

		if ( empty( $url ) ) {
			\WP_CLI::error( 'Invalid avatar URL.' );
			return;
		}

		if ( isset( $assoc_args['author'] ) && filter_var( $assoc_args['author'], FILTER_VALIDATE_URL ) ) {
			$hash = hash( 'sha256', esc_url_raw( $assoc_args['author'] ) );
		} else {
			$hash = hash( 'sha256', esc_url_raw( $url ) );
		}

		$dir .= '/' . substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 );

		$ext      = pathinfo( $url, PATHINFO_EXTENSION );
		$filename = $hash . ( ! empty( $ext ) ? '.' . $ext : '' );

		\WP_CLI::log( "Saving to `$dir/$filename`." );

		$width = isset( $assoc_args['w'] ) && ctype_digit( (string) $assoc_args['w'] )
			? (int) $assoc_args['w']
			: 150;

		$height = isset( $assoc_args['h'] ) && ctype_digit( (string) $assoc_args['h'] )
			? (int) $assoc_args['h']
			: 150;

		$result = store_image( $url, $filename, $dir, $width, $height );

		if ( $result ) {
			if ( isset( $assoc_args['type'] ) && 'ap' === $assoc_args['type'] ) {
				// That special case again.
				update_meta( $comment, 'avatar_url', $result );
			} elseif ( isset( $assoc_args['type'] ) && 'wm' === $assoc_args['type'] ) {
				update_meta( $comment, 'avatar', $result );
			} else {
				update_meta( $comment, 'indieblocks_webmention_avatar', $result );
			}

			\WP_CLI::success( 'All done!' );
		} else {
			\WP_CLI::error( 'Something went wrong.' );
		}
	}

	/**
	 * Downloads and resizes an image.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The URL of the image.
	 *
	 * [--w=<width>]
	 * : The target width.
	 *
	 * [--h=<height>]
	 * : The target height.
	 *
	 * [--dir=<dir>]
	 * : The destination folder, relative to, typically, `wp-content/uploads`.
	 *
	 * [--author=<author>]
	 * : Author URL.
	 *
	 * @subcommand cache-image
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args "Associated" arguments.
	 */
	public function cache_image( $args, $assoc_args ) {
		$options = get_options();
		if ( empty( $options['cache_avatars'] ) ) {
			\WP_CLI::error( 'Avatar cache disabled.' );
			return;
		}

		$url = trim( $args[0] );
		if ( ! preg_match( '~^https?://~', $url ) ) {
			$url = 'http://' . ltrim( $url, '/' );
		}

		if ( ! wp_http_validate_url( $url ) ) {
			\WP_CLI::error( 'Invalid URL.' );
			return;
		}

		$dir = isset( $assoc_args['dir'] )
			? $assoc_args['dir']
			: 'indieblocks-avatars';
		$dir = sanitize_title( $dir );

		if ( isset( $assoc_args['author'] ) && filter_var( $assoc_args['author'], FILTER_VALIDATE_URL ) ) {
			$hash = hash( 'sha256', esc_url_raw( $assoc_args['author'] ) );
		} else {
			$hash = hash( 'sha256', esc_url_raw( $url ) );
		}

		$dir .= '/' . substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 );

		$ext      = pathinfo( $url, PATHINFO_EXTENSION );
		$filename = $hash . ( ! empty( $ext ) ? '.' . $ext : '' );

		\WP_CLI::log( "Saving to `$dir/$filename`." );

		$width = isset( $assoc_args['w'] ) && ctype_digit( (string) $assoc_args['w'] )
			? (int) $assoc_args['w']
			: 150;

		$height = isset( $assoc_args['h'] ) && ctype_digit( (string) $assoc_args['h'] )
			? (int) $assoc_args['h']
			: 150;

		$result = store_image( $url, $filename, $dir, $width, $height );

		if ( $result ) {
			\WP_CLI::success( 'All done!' );
		} else {
			\WP_CLI::error( 'Something went wrong.' );
		}
	}

	/**
	 * Deletes an avatar and all references to it.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The (local) image URL.
	 *
	 * [--key=<key>]
	 * : The comment meta key that holds the (local) URL.
	 *
	 * @subcommand delete-image
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args "Associated" arguments.
	 */
	public function delete_image( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$url = trim( $args[0] );
		if ( ! preg_match( '~^https?://~', $url ) ) {
			$url = 'http://' . ltrim( $url, '/' );
		}

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			\WP_CLI::error( 'Invalid URL.' );
			return;
		}

		$key = isset( $assoc_args['key'] )
			? $assoc_args['key']
			: 'indieblocks_webmention_avatar';

		$upload_dir = wp_upload_dir();
		$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		// Delete file.
		wp_delete_file( $file_path );

		// Delete all references to _this_ file.
		$result = delete_metadata(
			'comment',
			0,
			$key,
			esc_url_raw( $url ),
			true // Delete matching metadata entries for all objects.
		);

		if ( $result ) {
			\WP_CLI::success( 'All done!' );
		} else {
			\WP_CLI::error( 'Something went wrong.' );
		}
	}

	/**
	 * Deletes a link preview card.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The post ID.
	 *
	 * @subcommand delete-link-preview
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args "Associated" arguments.
	 */
	public function delete_link_preview( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$id = trim( $args[0] );
		if ( ! ctype_digit( (string) $id ) ) {
			\WP_CLI::error( 'Invalid ID.' );
			return;
		}

		$post = get_post( $id );
		if ( empty( $post ) ) {
			\WP_CLI::error( 'Invalid ID.' );
			return;
		}

		$card = get_post_meta( $post->ID, '_indieblocks_link_preview', true );
		if ( ! empty( $card['thumbnail'] ) ) {
			$upload_dir = wp_upload_dir();
			$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $card['thumbnail'] );

			\WP_CLI::log( 'Deleting link preview thumbnail.' );

			wp_delete_file( $file_path );
		}

		$result = delete_post_meta( $post->ID, '_indieblocks_link_preview' );

		if ( $result ) {
			\WP_CLI::success( 'All done!' );
		} else {
			\WP_CLI::error( 'Something went wrong.' );
		}
	}
}
