<?php

if ( !defined( 'ABSPATH' ) )
	exit;

add_action( 'add_meta_boxes_post', function( WP_Post $post ): void {
	if ( !current_user_can( 'edit_post', $post->ID ) )
		return;
	if ( $post->ID === 1 )
		return;
	if ( !has_category( [ 'prayers', 'categories' ], $post ) )
		return;
	/*
	$now = current_time( 'timestamp', TRUE );
	update_option( 'opb', [
		'timestamp' => $now,
		'meta_list' => [
			[ 'parent' => 1, 'child' => 9, 'order' => 1 ],
		],
	] );
	*/
	add_meta_box( 'opb_parents', __( 'Parents', 'opb' ), function( WP_Post $post ): void {
		$meta = get_option( 'opb' );
		$order_dict = array_filter( $meta['meta_list'], function( array $meta ) use ( $post ): bool {
			return $meta['child'] === $post->ID;
		} );
		$order_dict = array_combine(
			array_column( $order_dict, 'parent' ),
			array_column( $order_dict, 'order' ),
		);
		var_dump( $order_dict );
		$title_dict = get_posts( [
			'category_name' => 'prayers,categories',
			'nopaging' => TRUE,
			'orderby' => 'title',
			'order' => 'ASC',
		] );
		$title_dict = array_combine(
			array_column( $title_dict, 'ID' ),
			array_column( $title_dict, 'post_title' ),
		);
		$leaf_dict = array_combine(
			array_keys( $title_dict ),
			array_map( function( int $id ): bool {
				return get_the_category( $id )[0]->slug === 'prayers';
			}, array_keys( $title_dict ) ),
		);
		echo '<div>' . "\n";
		echo '<table class="fixed widefat striped">' . "\n";
		echo '<thead>' . "\n";
		echo '<tr>' . "\n";
		echo sprintf( '<th>%s</th>', esc_html__( 'Parent', 'opb' ) ) . "\n";
		echo sprintf( '<th>%s</th>', esc_html__( 'Order', 'opb' ) ) . "\n";
		echo '</tr>' . "\n";
		echo '</thead>' . "\n";
		echo '<tbody>' . "\n";
		foreach ( $order_dict as $id => $order ) {
			echo '<tr>' . "\n";
			echo sprintf( '<td>%s</td>', esc_html( $title_dict[ $id ] ) ) . "\n";
			echo sprintf( '<td>%d</td>', $order ) . "\n";
			echo '</tr>' . "\n";
		}
		echo '</tbody>' . "\n";
		echo '</table>' . "\n";
		echo sprintf( '<input value="%d">', $meta['timestamp'] ) . "\n"; // TODO type="hidden"
		echo '<label>' . "\n";
		echo sprintf( '<span>%s</span>', esc_html__( 'Parent', 'opb' ) ) . "\n";
		echo '<select>' . "\n";
		echo '<option value=""></option>' . "\n";
		foreach ( $title_dict as $id => $title ) {
			if ( $id === $post->ID )
				continue;
			echo sprintf( '<option value="%d"%s>%s</option>', $id, disabled( $leaf_dict[ $id ], TRUE, FALSE ), esc_html( $title ) ) . "\n";
		}
		echo '</select>' . "\n";
		echo '</label>' . "\n";
		echo '<label>' . "\n";
		echo sprintf( '<span>%s</span>', esc_html__( 'Order', 'opb' ) ) . "\n";
		echo '<input type="number" min="1" class="small-text">' . "\n";
		echo '</label>' . "\n";
		echo '<div>' . "\n";
		echo sprintf( '<a href="" class="button button-primary">%s</a>', esc_html__( 'Submit', 'opb' ) ) . "\n";
		echo sprintf( '<a href="" class="button">%s</a>', esc_html__( 'Cancel', 'opb' ) ) . "\n";
		echo '</div>' . "\n";
		echo '</div>' . "\n";
	}, NULL, 'normal' );
} );
