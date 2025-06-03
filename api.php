<?php

if ( !defined( 'ABSPATH' ) )
	exit;

add_action( 'wp_ajax_nopriv_opb_api_1', function(): void {
	$after = isset( $_GET['after'] ) ? intval( $_GET['after'] ) : 0;
	$after = wp_date( 'Y-m-d H:i:s', $after, new DateTimeZone( 'UTC' ) );
	$now = current_time( 'timestamp', TRUE );
	$date_query = [
		[
			'column' => 'post_modified_gmt',
			'after' => $after,
			'inclusive' => TRUE,
		],
	];
	$id_list = get_posts( [
		'category_name' => 'prayers,categories',
		'nopaging' => TRUE,
		'fields' => 'ids',
	] );
	$post_list = get_posts( [
		'category_name' => 'prayers,categories',
		'nopaging' => TRUE,
		'date_query' => $date_query,
	] );
	$post_list = array_map( function( WP_Post $post ): array {
		return [
			'id' => $post->ID,
			'date' => $post->post_date_gmt,
			'content' => $post->post_content,
			'title' => $post->post_title,
			'excerpt' => $post->post_excerpt,
			'modified' => $post->post_modified_gmt,
			'leaf' => get_the_category( $post->ID )[0]->slug === 'prayers',
		];
	}, $post_list );
	$meta_list = get_option( 'opb' );
	$meta_list = $meta_list['meta_list'];
	$meta_list = array_map( function( array $meta ): array {
		return [ $meta['parent'], $meta['child'], $meta['order'] ];
	}, $meta_list );
	header( 'content-type: application/json' );
	exit( json_encode( [
		'timestamp' => $now,
		'id_list' => $id_list,
		'post_list' => $post_list,
		'meta_list' => $meta_list,
	] ) );
} );
