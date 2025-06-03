<?php

/*
 * Plugin Name: Orthodox Prayer Book
 * Plugin URI: https://github.com/constracti/orthodox-prayer-book-wp
 * Description: Customization plugin of Orthodox Prayer Book website.
 * Version: 0.1
 * Requires PHP: 8.0
 * Author: constracti
 * Author URI: https://github.com/constracti
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( !defined( 'ABSPATH' ) )
	exit;

final class OPB {

	public static function dir( string $dir ): string {
		return plugin_dir_path( __FILE__ ) . $dir;
	}
}

$files = glob( OPB::dir( '*.php' ) );
foreach ( $files as $file ) {
	if ( $file !== __FILE__ )
		require_once( $file );
}

// root has id 1
// root is a category
// root has no parents

// other categories have at least one parent

// prayers have at least one parent

// parent is always a category
// parent is an ancestor of root
