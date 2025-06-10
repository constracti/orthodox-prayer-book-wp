<?php

if ( !defined( 'ABSPATH' ) )
	exit;

add_action( 'set_object_terms', 'opb_post_category_action', 10, 6 );

function opb_post_category_action( int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
	if ( $taxonomy !== 'category' )
		return;
	$post = get_post( $object_id );
	if ( is_null( $post ) )
		return;
	if ( $post->post_type !== 'post' )
		return;
	remove_action( 'set_object_terms', 'opb_post_category_action', 10 );
	$option = OPB::get_option();
	$term_category = get_category_by_slug( 'categories' );
	$term_prayer = get_category_by_slug( 'prayers' );
	$is_category = has_category( $term_category->term_id, $post );
	$is_prayer = has_category( $term_prayer->term_id, $post );
	$make_category = FALSE;
	$make_prayer_or_category = FALSE;
	foreach ( $option['edge_list'] as $edge ) {
		if ( $edge['parent'] === $post->ID )
			$make_category = TRUE;
		if ( $edge['child'] === $post->ID )
			$make_prayer_or_category = TRUE;
	}
	if ( $make_category ) {
		wp_set_post_categories( $post->ID, $term_category->term_id );
	} elseif ( $make_prayer_or_category ) {
		if ( $is_prayer || !$is_category ) {
			wp_set_post_categories( $post->ID, $term_prayer->term_id );
		} else {
			wp_set_post_categories( $post->ID, $term_category->term_id );
		}
	}
	add_action( 'set_object_terms', 'opb_post_category_action', 10, 6 );
}

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
