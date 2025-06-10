<?php

if ( !defined( 'ABSPATH' ) )
	exit;

add_action( 'trashed_post', 'opb_post_delete_action' );
add_action( 'deleted_post', 'opb_post_delete_action' );

function opb_post_delete_action( int $post_id ): void {
	$option = OPB::get_option();
	$edge_list = $option['edge_list'];
	$edge_list = array_filter( $edge_list, function( array $edge ) use ( $post_id ): bool {
		return $edge['parent'] !== $post_id && $edge['child'] !== $post_id;
	} );
	OPB::set_option( $edge_list );
}
