<?php

if ( !defined( 'ABSPATH' ) )
	exit;


final class OPB_Parents_Meta_Box {

	private const REFRESH = 'opb_parents_meta_box_refresh';
	private const INSERT = 'opb_parents_meta_box_insert';
	private const DELETE = 'opb_parents_meta_box_delete';

	public static function init(): void {
		add_action( 'add_meta_boxes', [ 'OPB_Parents_Meta_Box', 'add_meta_boxes' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ 'OPB_Parents_Meta_Box', 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::REFRESH, [ 'OPB_Parents_Meta_Box', 'refresh_action' ] );
		add_action( 'wp_ajax_' . self::INSERT, [ 'OPB_Parents_Meta_Box', 'insert_action' ] );
		add_action( 'wp_ajax_' . self::DELETE, [ 'OPB_Parents_Meta_Box', 'delete_action' ] );
	}

	public static function add_meta_boxes( string $post_type, WP_Post $post ): void {
		if ( !current_user_can( 'edit_post', $post->ID ) )
			return;
		if ( $post_type !== 'post' )
			return;
		if ( !has_category( [ 'prayers', 'categories' ], $post ) )
			return;
		if ( $post->ID === 1 )
			return;
		add_meta_box( 'opb_parents', __( 'Parents', 'opb' ), [ 'OPB_Parents_Meta_Box', 'home_echo' ], NULL, 'normal' );
	}

	public static function admin_enqueue_scripts( string $hook_suffix ): void {
		if ( !current_user_can( 'edit_posts' ) )
			return;
		if ( $hook_suffix !== 'post.php' )
			return;
		wp_enqueue_style( 'opb-admin', OPB::url( 'admin.css' ), [], OPB::version() );
		wp_enqueue_script( 'opb-table', OPB::url( 'table.js' ), [ 'jquery' ], OPB::version() );
	}

	private static function home( WP_Post $post ): string {
		$ret = '';
		$meta = OPB::get_option();
		$meta_list = array_filter( $meta['meta_list'], function( array $meta ) use ( $post ): bool {
			return $meta['child'] === $post->ID;
		} );
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
				return has_category( 'prayers', $id );
			}, array_keys( $title_dict ) ),
		);
		$count_dict = array_fill_keys( array_keys( $title_dict ), 0 );
		foreach ( $meta['meta_list'] as $meta_item )
			$count_dict[ $meta_item['parent'] ]++;
		$ret .= '<div class="opb-table-home opb-flex-col opb-root" style="margin: -6px -12px -12px -12px;">' . "\n";
		$ret .= '<div class="opb-flex-row opb-flex-justify-between opb-flex-align-center">' . "\n";
		$ret .= self::refresh_button( $post );
		$ret .= '<span class="opb-table-spinner opb-leaf spinner" data-opb-table-spinner-toggle="is-active"></span>' . "\n";
		$ret .= '</div>' . "\n";
		$ret .= '<hr class="opb-leaf">' . "\n";
		$ret .= self::table( $post, $meta_list, $title_dict, $count_dict, $meta['timestamp'] );
		$ret .= '<div class="opb-flex-row">' . "\n";
		$ret .= self::insert_button( $post, $meta['timestamp'] );
		$ret .= '</div>' . "\n";
		$ret .= self::form( $title_dict, $leaf_dict, $count_dict );
		$ret .= '</div>' . "\n";
		return $ret;
	}

	public static function home_echo( WP_Post $post ): void {
		echo self::home( $post );
	}

	private static function refresh_button( WP_Post $post ): string {
		return sprintf( '<a%s>%s</a>', OPB::atts( [
			'href' => add_query_arg( [
				'action' => self::REFRESH,
				'post' => $post->ID,
				'nonce' => OPB::nonce_create( self::REFRESH, $post->ID ),
			], admin_url( 'admin-ajax.php' ) ),
			'class' => 'opb-table-link opb-leaf button',
		] ), esc_html__( 'Refresh', 'opb' ) ) . "\n";
	}

	public static function refresh_action(): void {
		$post = OPB_Request::get_post();
		if ( $post->post_type !== 'post' )
			exit( 'post' );
		if ( !has_category( [ 'prayers', 'categories' ], $post ) )
			exit( 'post' );
		if ( $post->ID === 1 )
			exit( 'post' );
		if ( !current_user_can( 'edit_post', $post->ID ) )
			exit( 'role' );
		OPB::nonce_verify( self::REFRESH, $post->ID );
		OPB::success( self::home( $post ) );
	}

	private static function table( WP_Post $post, array $meta_list, array $title_dict, array $count_dict, int $timestamp ): string {
		$ret = '';
		$ret .= '<div class="opb-leaf">' . "\n";
		$ret .= '<table class="fixed widefat striped">' . "\n";
		$ret .= '<thead>' . "\n";
		$ret .= self::table_head_row();
		$ret .= '</thead>' . "\n";
		$ret .= '<tbody>' . "\n";
		foreach ( $meta_list as $meta ) {
			$parent = $meta['parent'];
			$order = $meta['order'];
			$ret .= self::table_body_row( $post, $parent, $title_dict[ $parent ], $count_dict[ $parent ], $order, $timestamp );
		}
		$ret .= '</tbody>' . "\n";
		$ret .= '</table>' . "\n";
		$ret .= '</div>' . "\n";
		return $ret;
	}

	private static function table_head_row(): string {
		$ret = '';
		$ret .= '<tr>' . "\n";
		$ret .= sprintf( '<th>%s</th>', esc_html__( 'Parent', 'opb' ) ) . "\n";
		$ret .= sprintf( '<th>%s</th>', esc_html__( 'Count', 'opb' ) ) . "\n";
		$ret .= sprintf( '<th>%s</th>', esc_html__( 'Order', 'opb' ) ) . "\n";
		$ret .= '</tr>' . "\n";
		return $ret;
	}

	private static function table_body_row( WP_Post $post, int $parent, string $title, int $count, int $order, int $timestamp ): string {
		$actions = [];
		$actions[] = sprintf( '<span><a%s>%s</a></span>', OPB::atts( [
			'href' => get_permalink( $parent ),
		] ), esc_html__( 'View', 'opb' ) );
		$actions[] = sprintf( '<span><a%s>%s</a></span>', OPB::atts( [
			'href' => add_query_arg( [
				'post' => $parent,
				'action' => 'edit',
			], admin_url( 'post.php' ) ),
		] ), esc_html__( 'Edit', 'opb' ) );
		$actions[] = self::delete_link( $post, $parent, $title, $order, $timestamp );
		$ret = '';
		$ret .= '<tr>' . "\n";
		$ret .= '<td>' . "\n";
		$ret .= sprintf( '<strong>%s</strong>', esc_html( $title ) ) . "\n";
		$ret .= sprintf( '<div class="row-actions">%s</div>', implode( ' | ', $actions ) ) . "\n";
		$ret .= '</td>' . "\n";
		$ret .= sprintf( '<td>%d</td>', $count ) . "\n";
		$ret .= sprintf( '<td>%d</td>', $order ) . "\n";
		$ret .= '</tr>' . "\n";
		return $ret;
	}

	private static function form( array $title_dict, array $leaf_dict, array $count_dict ): string {
		$ret = '';
		$ret = '<div class="opb-table-form opb-table-form-meta opb-leaf opb-root opb-root-border opb-flex-col" style="display: none;">' . "\n";
		$ret .= '<label class="opb-flex-row opb-flex-justify-between opb-flex-align-center">' . "\n";
		$ret .= sprintf( '<span class="opb-leaf" style="width: 6em;">%s</span>', esc_html__( 'Parent', 'opb' ) ) . "\n";
		$ret .= '<select class="opb-table-field opb-leaf opb-flex-grow" data-opb-table-name="parent" />' . "\n";
		$ret .= '<option value="">&mdash;</option>' . "\n";
		foreach ( $title_dict as $id => $title ) {
			if ( $leaf_dict[ $id ] )
				continue;
			// TODO should be ancestor of root
			// TODO limit to DAG
			// TODO block edges with same vertices
			$ret .= sprintf( '<option value="%d">%s</option>', $id, esc_html( sprintf( '%s (%d)', $title, $count_dict[ $id ] ) ) ) . "\n";
		}
		$ret .= '</select>' . "\n";
		$ret .= '</label>' . "\n";
		$ret .= '<label class="opb-flex-row opb-flex-justify-between opb-flex-align-center">' . "\n";
		$ret .= sprintf( '<span class="opb-leaf" style="width: 6em;">%s</span>', esc_html__( 'Order', 'opb' ) ) . "\n";
		$ret .= '<input type="number" min="0" class="opb-table-field opb-leaf opb-flex-grow" data-opb-table-name="order">' . "\n";
		$ret .= '</label>' . "\n";
		$ret .= '<div class="opb-flex-row opb-flex-justify-between opb-flex-align-center">' . "\n";
		$ret .= sprintf( '<a href="" class="opb-table-link opb-table-submit opb-leaf button button-primary">%s</a>', esc_html__( 'Submit', 'opb' ) ) . "\n";
		$ret .= sprintf( '<a href="" class="opb-table-cancel opb-leaf button">%s</a>', esc_html__( 'Cancel', 'opb' ) ) . "\n";
		$ret .= '</div>' . "\n";
		$ret .= '</div>' . "\n";
		return $ret;
	}

	private static function insert_button( WP_Post $post, int $timestamp ): string {
		return sprintf( '<a%s>%s</a>', OPB::atts( [
			'href' => add_query_arg( [
				'action' => self::INSERT,
				'post' => $post->ID,
				'timestamp' => $timestamp,
				'nonce' => OPB::nonce_create( self::INSERT, $post->ID ),
			], admin_url( 'admin-ajax.php' ) ),
			'class' => 'opb-table-insert opb-leaf button',
			'data-opb-table-form' => '.opb-table-form-meta',
		] ), esc_html__( 'Insert', 'opb' ) ) . "\n";
	}

	public static function insert_action(): void {
		$post = OPB_Request::get_post();
		if ( $post->post_type !== 'post' )
			exit( 'post' );
		if ( !has_category( [ 'prayers', 'categories' ], $post ) )
			exit( 'post' );
		if ( $post->ID === 1 )
			exit( 'post' );
		if ( !current_user_can( 'edit_post', $post->ID ) )
			exit( 'role' );
		OPB::nonce_verify( self::INSERT, $post->ID );
		$meta = OPB::get_option();
		$timestamp = OPB_Request::get_int( 'timestamp' );
		if ( $timestamp !== $meta['timestamp'] )
			exit( 'timestamp' );
		$parent = OPB_Request::post_post( 'parent' );
		if ( !has_category( 'categories', $parent->ID ) )
			exit( 'parent' );
		if ( has_category( 'prayers', $parent->ID ) )
			exit( 'parent' );
		$order = OPB_Request::post_int( 'order', TRUE );
		$meta_list = $meta['meta_list'];
		if ( !is_null( $order ) ) {
			$meta_list = array_map( function( array $meta ) use ( $post, $parent, $order ): array {
				if ( $meta['parent'] !== $parent->ID || $meta['order'] < $order )
					return $meta;
				$meta['order']++;
				return $meta;
			}, $meta_list );
		} else {
			$order = count( array_filter( $meta_list, function( array $meta ) use ( $parent ): bool {
				return $meta['parent'] === $parent->ID;
			} ) );
		}
		$meta_list[] = [
			'parent' => $parent->ID,
			'child' => $post->ID,
			'order' => $order,
		];
		OPB::set_option( $meta_list );
		OPB::success( self::home( $post ) );
	}

	private static function delete_link( WP_Post $post, int $parent, string $title, int $order, int $timestamp ): string {
		return sprintf( '<span class="delete"><a%s>%s</a></span>', OPB::atts( [
			'href' => add_query_arg( [
				'action' => self::DELETE,
				'post' => $post->ID,
				'parent' => $parent,
				'order' => $order,
				'timestamp' => $timestamp,
				'nonce' => OPB::nonce_create( self::DELETE, $post->ID, $parent, $order ),
			], admin_url( 'admin-ajax.php' ) ),
			'class' => 'opb-table-link',
			'data-opb-table-confirm' => esc_attr( sprintf( __( 'Remove parent %s with order %d?', 'opb' ), $title, $order ) ),
		] ), esc_html__( 'Remove', 'opb' ) );
	}

	public static function delete_action(): void {
		$post = OPB_Request::get_post();
		if ( $post->post_type !== 'post' )
			exit( 'post' );
		if ( !has_category( [ 'prayers', 'categories' ], $post ) )
			exit( 'post' );
		if ( $post->ID === 1 )
			exit( 'post' );
		if ( !current_user_can( 'edit_post', $post->ID ) )
			exit( 'role' );
		$parent = OPB_Request::get_post( 'parent' );
		$order = OPB_Request::get_int( 'order' );
		OPB::nonce_verify( self::DELETE, $post->ID, $parent->ID, $order );
		$meta = OPB::get_option();
		$timestamp = OPB_Request::get_int( 'timestamp' );
		if ( $timestamp !== $meta['timestamp'] )
			exit( 'timestamp' );
		$meta_list = array_values( array_filter( $meta['meta_list'], function( array $meta ) use ( $post, $parent, $order ): bool {
			return $meta['parent'] !== $parent->ID || $meta['child'] !== $post->ID || $meta['order'] !== $order;
		} ) );
		OPB::set_option( $meta_list );
		OPB::success( self::home( $post ) );
	}
}

OPB_Parents_Meta_Box::init();
