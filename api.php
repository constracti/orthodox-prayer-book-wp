<?php

if ( !defined( 'ABSPATH' ) )
	exit;

// https://prayers.raktivan.gr/wp-admin/admin-ajax.php?action=opb_api_data_1
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
	$node_list = get_posts( [
		'category_name' => 'prayers,categories',
		'nopaging' => TRUE,
		'date_query' => $date_query,
	] );
	$node_list = array_map( function( WP_Post $post ): array {
		return [
			'id' => $post->ID,
			'title' => $post->post_title,
			'excerpt' => $post->post_excerpt,
			'content' => $post->post_content,
			'created' => $post->post_date_gmt,
			'modified' => $post->post_modified_gmt,
			'leaf' => has_category( 'prayers', $post->ID ),
		];
	}, $node_list );
	$edge_list = OPB::get_option();
	$edge_list = $edge_list['edge_list'];
	$edge_list = array_map( function( array $edge ): array {
		return [ $edge['parent'], $edge['child'], $edge['order'] ];
	}, $edge_list );
	header( 'content-type: application/json' );
	exit( json_encode( [
		'timestamp' => $now,
		'id_list' => $id_list,
		'node_list' => $node_list,
		'edge_list' => $edge_list,
	] ) );
} );

// https://prayers.raktivan.gr/wp-admin/admin-ajax.php?action=opb_api_timestamp_1
add_action( 'wp_ajax_nopriv_opb_api_timestamp_1', function(): void {
	$option = OPB::get_option();
	$timestamp = $option['timestamp'];
	$node_list = get_posts( [
		'category_name' => 'prayers,categories',
		'posts_per_page' => 1,
		'orderby' => 'modified',
		'order' => 'DESC',
	] );
	foreach ( $node_list as $node ) {
		$modified = get_post_modified_time( gmt: TRUE, post: $node );
		if ( $modified > $timestamp )
			$timestamp = $modified;
	}
	echo $timestamp;
	exit;
} );
