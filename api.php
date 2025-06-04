<?php

if ( !defined( 'ABSPATH' ) )
	exit;

add_action( 'wp_ajax_nopriv_opb_api_data_1', function(): void {
	$after = OPB_Request::get_int( 'after', TRUE ) ?? 0;
	$after = wp_date( 'Y-m-d H:i:s', $after, new DateTimeZone( 'UTC' ) );
	$now = OPB::now();
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
			'leaf' => has_category( 'prayers', $post->ID ),
		];
	}, $post_list );
	$meta_list = OPB::get_option();
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
