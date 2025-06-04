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

// TODO check before continuing
// root has id 1
// root is a category
// root has no parents
// other categories have at least one parent
// prayers have at least one parent
// parent is always a category
// parent is an ancestor of root

// TODO block category change
// TODO handle post deletion


final class OPB {

	public static function init(): void {
		$files = glob( OPB::dir( '*.php' ) );
		foreach ( $files as $file ) {
			if ( $file !== __FILE__ )
				require_once( $file );
		}
	}

	public static function dir( string $rel ): string {
		return plugin_dir_path( __FILE__ ) . $rel;
	}

	public static function url( string $rel ): string {
		return plugin_dir_url( __FILE__ ) . $rel;
	}

	public static function version(): string {
		$plugin_data = get_plugin_data( __FILE__ );
		// return $plugin_data['Version'];
		return strval( time() );
	}

	public static function now(): int {
		return current_time( 'timestamp', TRUE );
	}

	public static function success( string $html ): void {
		header( 'content-type: application/json' );
		exit( json_encode( [
			'html' => $html,
		] ) );
	}

	public static function atts( array $atts ): string {
		$ret = '';
		foreach ( $atts as $prop => $val ) {
			$ret .= sprintf( ' %s="%s"', $prop, $val );
		}
		return $ret;
	}

	// nonce

	private static function nonce_action( string $action, string ...$args ): string {
		foreach ( $args as $arg )
			$action .= '_' . $arg;
		return $action;
	}

	public static function nonce_create( string $action, string ...$args ): string {
		return wp_create_nonce( self::nonce_action( $action, ...$args ) );
	}

	public static function nonce_verify( string $action, string ...$args ): void {
		$nonce = OPB_Request::get_str( 'nonce' );
		if ( !wp_verify_nonce( $nonce, self::nonce_action( $action, ...$args ) ) )
			exit( 'nonce' );
	}

	// option

	public static function get_option(): array {
		$meta = get_option( 'opb' );
		$new = [
			'meta_list' => [],
			'timestamp' => self::now(),
		];
		if ( !is_array( $meta ) )
			return $new;
		if ( count( $meta ) !== 2 )
			return $new;
		if ( !isset( $meta['meta_list'] ) || !is_array( $meta['meta_list'] ) )
			return $new;
		if ( !isset( $meta['timestamp'] ) || !is_int( $meta['timestamp'] ) )
			return $new;
		return $meta;
	}

	public static function set_option( array $meta_list ): void {
		$parent_list = [];
		foreach ( $meta_list as $meta ) {
			$parent = $meta['parent'];
			if ( !isset( $parent_list[ $parent ] ) )
				$parent_list[ $parent ] = [];
			$parent_list[ $parent ][] = $meta;
		}
		foreach ( $parent_list as $parent => $meta_list ) {
			usort( $meta_list, function( array $meta1, array $meta2 ): int {
				return $meta1['order'] <=> $meta2['order'];
			} );
			$parent_list[ $parent ] = array_map( function( int $order, array $meta ): array {
				$meta['order'] = $order;
				return $meta;
			}, array_keys( $meta_list ), $meta_list );
		}
		$meta_list = array_merge( ...$parent_list );
		$meta = [
			'meta_list' => $meta_list,
			'timestamp' => self::now(),
		];
		update_option( 'opb', $meta );
	}
}

OPB::init();
