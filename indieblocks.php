<?php
/**
 * Plugin Name: IndieBlocks
 * Description: Leverage blocks and custom post types to easily "IndieWebify" your WordPress site.
 * Plugin URI:  https://jan.boddez.net/wordpress/indieblocks
 * Author:      Jan Boddez
 * Author URI:  https://jan.boddez.net/
 * License:     GNU General Public License v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: indieblocks
 * Version:     0.2.0
 *
 * @author  Jan Boddez <jan@janboddez.be>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 * @package IndieBlocks
 */

namespace IndieBlocks;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load dependencies.
foreach ( glob( __DIR__ . '/includes/*.php' ) as $file ) {
	require_once $file;
}

$indieblocks = IndieBlocks::get_instance();
$indieblocks->register();
