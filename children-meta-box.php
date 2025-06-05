<?php

if ( !defined( 'ABSPATH' ) )
	exit;


final class OPB_Children_Meta_Box {

	private const REFRESH = 'opb_children_meta_box_refresh';
	private const INSERT = 'opb_children_meta_box_insert';
	private const MOVE_UP = 'opb_children_meta_box_move_up';
	private const MOVE_DOWN = 'opb_children_meta_box_move_down';
	private const DELETE = 'opb_children_meta_box_delete';

	public static function init(): void {
		add_action( 'add_meta_boxes', [ 'OPB_Children_Meta_Box', 'add_meta_boxes' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ 'OPB_Children_Meta_Box', 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::REFRESH, [ 'OPB_Children_Meta_Box', 'refresh_action' ] );
		add_action( 'wp_ajax_' . self::INSERT, [ 'OPB_Children_Meta_Box', 'insert_action' ] );
		add_action( 'wp_ajax_' . self::MOVE_UP, [ 'OPB_Children_Meta_Box', 'move_up_action' ] );
		add_action( 'wp_ajax_' . self::MOVE_DOWN, [ 'OPB_Children_Meta_Box', 'move_down_action' ] );
		add_action( 'wp_ajax_' . self::DELETE, [ 'OPB_Children_Meta_Box', 'delete_action' ] );
	}

	public static function add_meta_boxes( string $post_type, WP_Post $post ): void {
		if ( !current_user_can( 'edit_post', $post->ID ) )
			return;
		if ( $post_type !== 'post' )
			return;
		if ( !has_category( 'categories', $post ) )
			return;
		add_meta_box( 'opb_children', __( 'Children', 'opb' ), [ 'OPB_Children_Meta_Box', 'home_echo' ], NULL, 'normal' );
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
		$option = OPB::get_option();
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
		$n = count( array_filter( $option['edge_list'], function( array $edge ) use ( $post ): bool {
			return $edge['parent'] === $post->ID;
		} ) );
		$ret = '';
		$ret .= '<div class="opb-table-home opb-flex-col opb-root" style="margin: -6px -12px -12px -12px;">' . "\n";
		$ret .= '<div class="opb-flex-row opb-flex-justify-between opb-flex-align-center">' . "\n";
		$ret .= self::refresh_button( $post );
		$ret .= '<span class="opb-table-spinner opb-leaf spinner" data-opb-table-spinner-toggle="is-active"></span>' . "\n";
		$ret .= '</div>' . "\n";
		$ret .= '<hr class="opb-leaf">' . "\n";
		$ret .= self::table( $post, $option, $title_dict );
		$ret .= '<div class="opb-flex-row">' . "\n";
		$ret .= self::insert_button( $post, $n, $option['timestamp'] );
		$ret .= '</div>' . "\n";
		$ret .= self::form( $title_dict, $n );
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
		if ( !has_category( 'categories', $post ) )
			exit( 'post' );
		if ( !current_user_can( 'edit_post', $post->ID ) )
			exit( 'role' );
		OPB::nonce_verify( self::REFRESH, $post->ID );
		OPB::success( self::home( $post ) );
	}

	private static function table( WP_Post $post, array $option, array $title_dict ): string {
		$ret = '';
		$ret .= '<div class="opb-leaf">' . "\n";
		$ret .= '<table class="fixed widefat striped">' . "\n";
		$ret .= '<thead>' . "\n";
		$ret .= self::table_head_row();
		$ret .= '</thead>' . "\n";
		$ret .= '<tbody>' . "\n";
		$edge_list = array_values( array_filter( $option['edge_list'], function( array $edge ) use ( $post ): bool {
			return $edge['parent'] === $post->ID;
		} ) );
		$n = count( $edge_list );
		foreach ( $edge_list as $i => $edge ) {
			$child = $edge['child'];
			$order = $edge['order'];
			$ret .= self::table_body_row( $post, $child, $title_dict[ $child ], $order, $option['timestamp'], $i === 0, $i === $n - 1 );
		}
		$ret .= '</tbody>' . "\n";
		$ret .= '</table>' . "\n";
		$ret .= '</div>' . "\n";
		return $ret;
	}

	private static function table_head_row(): string {
		$ret = '';
		$ret .= '<tr>' . "\n";
		$ret .= sprintf( '<th>%s</th>', esc_html__( 'Child', 'opb' ) ) . "\n";
		$ret .= sprintf( '<th>%s</th>', esc_html__( 'Order', 'opb' ) ) . "\n";
		$ret .= '</tr>' . "\n";
		return $ret;
	}

	private static function table_body_row( WP_Post $post, int $child, string $title, int $order, int $timestamp, bool $first, bool $last ): string {
		$actions = [];
		$actions[] = sprintf( '<span><a%s>%s</a></span>', OPB::atts( [
			'href' => get_permalink( $child ),
		] ), esc_html__( 'View', 'opb' ) );
		$actions[] = sprintf( '<span><a%s>%s</a></span>', OPB::atts( [
			'href' => add_query_arg( [
				'post' => $child,
				'action' => 'edit',
			], admin_url( 'post.php' ) ),
		] ), esc_html__( 'Edit', 'opb' ) );
		$actions[] = $first ? self::move_up_text() : self::move_up_link( $post, $order, $timestamp );
		$actions[] = $last ? self::move_down_text() : self::move_down_link( $post, $order, $timestamp );
		$actions[] = self::delete_link( $post, $child, $title, $order, $timestamp );
		$ret = '';
		$ret .= '<tr>' . "\n";
		$ret .= '<td>' . "\n";
		$ret .= sprintf( '<strong>%s</strong>', esc_html( $title ) ) . "\n";
		$ret .= sprintf( '<div class="row-actions">%s</div>', implode( ' | ', $actions ) ) . "\n";
		$ret .= '</td>' . "\n";
		$ret .= sprintf( '<td>%d</td>', $order ) . "\n";
		$ret .= '</tr>' . "\n";
		return $ret;
	}

	private static function form( array $title_dict, int $n ): string {
		$ret = '';
		$ret = '<div class="opb-table-form opb-table-form-edge opb-leaf opb-root opb-root-border opb-flex-col" style="display: none;">' . "\n";
		$ret .= '<label class="opb-flex-row opb-flex-justify-between opb-flex-align-center">' . "\n";
		$ret .= sprintf( '<span class="opb-leaf" style="width: 6em;">%s</span>', esc_html__( 'Child', 'opb' ) ) . "\n";
		$ret .= '<select class="opb-table-field opb-leaf opb-flex-grow" data-opb-table-name="child" />' . "\n";
		$ret .= '<option value="">&mdash;</option>' . "\n";
		foreach ( $title_dict as $id => $title )
			$ret .= sprintf( '<option value="%d">%s</option>', $id, esc_html( $title ) ) . "\n";
		$ret .= '</select>' . "\n";
		$ret .= '</label>' . "\n";
		$ret .= '<label class="opb-flex-row opb-flex-justify-between opb-flex-align-center">' . "\n";
		$ret .= sprintf( '<span class="opb-leaf" style="width: 6em;">%s</span>', esc_html__( 'Order', 'opb' ) ) . "\n";
		$ret .= sprintf( '<input type="number" min="0" max="%d" class="opb-table-field opb-leaf opb-flex-grow" data-opb-table-name="order">', $n ) . "\n";
		$ret .= '</label>' . "\n";
		$ret .= '<div class="opb-flex-row opb-flex-justify-between opb-flex-align-center">' . "\n";
		$ret .= sprintf( '<a href="" class="opb-table-link opb-table-submit opb-leaf button button-primary">%s</a>', esc_html__( 'Submit', 'opb' ) ) . "\n";
		$ret .= sprintf( '<a href="" class="opb-table-cancel opb-leaf button">%s</a>', esc_html__( 'Cancel', 'opb' ) ) . "\n";
		$ret .= '</div>' . "\n";
		$ret .= '</div>' . "\n";
		return $ret;
	}

	private static function insert_button( WP_Post $post, int $n, int $timestamp ): string {
		return sprintf( '<a%s>%s</a>', OPB::atts( [
			'href' => add_query_arg( [
				'action' => self::INSERT,
				'post' => $post->ID,
				'timestamp' => $timestamp,
				'nonce' => OPB::nonce_create( self::INSERT, $post->ID ),
			], admin_url( 'admin-ajax.php' ) ),
			'class' => 'opb-table-insert opb-leaf button',
			'data-opb-table-form' => '.opb-table-form-edge',
			'data-opb-table-field-order' => $n,
		] ), esc_html__( 'Insert', 'opb' ) ) . "\n";
	}

	public static function insert_action(): void {
		$post = OPB_Request::get_post();
		if ( $post->post_type !== 'post' )
			exit( 'post' );
		if ( !has_category( 'categories', $post ) )
			exit( 'post' );
		if ( !current_user_can( 'edit_post', $post->ID ) )
			exit( 'role' );
		OPB::nonce_verify( self::INSERT, $post->ID );
		$option = OPB::get_option();
		$timestamp = OPB_Request::get_int( 'timestamp' );
		if ( $timestamp !== $option['timestamp'] )
			exit( 'timestamp' );
		$child = OPB_Request::post_post( 'child' );
		if ( !has_category( [ 'prayers', 'categories' ], $child ) )
			exit( 'child' );
		$order = OPB_Request::post_int( 'order' );
		if ( $order < 0 )
			exit( 'order' );
		$n = count( array_filter( $option['edge_list'], function( array $edge ) use ( $post ): bool {
			return $edge['parent'] === $post->ID;
		} ) );
		if ( $order > $n )
			exit( 'order' );
		$edge_list = $option['edge_list'];
		$edge_list = array_map( function( array $edge ) use ( $post, $order ): array {
			if ( $edge['parent'] !== $post->ID || $edge['order'] < $order )
				return $edge;
			$edge['order']++;
			return $edge;
		}, $edge_list );
		$edge_list[] = [
			'parent' => $post->ID,
			'child' => $child->ID,
			'order' => $order,
		];
		OPB::set_option( $edge_list );
		OPB::success( self::home( $post ) );
	}

	private static function move_up_link( WP_Post $post, int $order, int $timestamp ): string {
		return sprintf( '<span><a%s>%s</a></span>', OPB::atts( [
			'href' => add_query_arg( [
				'action' => self::MOVE_UP,
				'post' => $post->ID,
				'order' => $order,
				'timestamp' => $timestamp,
				'nonce' => OPB::nonce_create( self::MOVE_UP, $post->ID, $order ),
			], admin_url( 'admin-ajax.php' ) ),
			'class' => 'opb-table-link',
		] ), esc_html__( 'Move Up', 'opb' ) );
	}

	private static function move_up_text(): string {
		return sprintf( '<span>%s</span>', esc_html__( 'Move Up', 'opb' ) );
	}

	public static function move_up_action(): void {
		$post = OPB_Request::get_post();
		if ( $post->post_type !== 'post' )
			exit( 'post' );
		if ( !has_category( 'categories', $post ) )
			exit( 'post' );
		if ( !current_user_can( 'edit_post', $post->ID ) )
			exit( 'role' );
		$order = OPB_Request::get_int( 'order' );
		OPB::nonce_verify( self::MOVE_UP, $post->ID, $order );
		$option = OPB::get_option();
		$timestamp = OPB_Request::get_int( 'timestamp' );
		if ( $timestamp !== $option['timestamp'] )
			exit( 'timestamp' );
		if ( $order < 1 )
			exit( 'order' );
		$edge_list = array_map( function( array $edge ) use ( $post, $order ): array {
			if ( $edge['parent'] !== $post->ID )
				return $edge;
			if ( $edge['order'] === $order )
				$edge['order']--;
			elseif ( $edge['order'] === $order - 1 )
				$edge['order']++;
			return $edge;
		}, $option['edge_list'] );
		OPB::set_option( $edge_list );
		OPB::success( self::home( $post ) );
	}

	private static function move_down_link( WP_Post $post, int $order, int $timestamp ): string {
		return sprintf( '<span><a%s>%s</a></span>', OPB::atts( [
			'href' => add_query_arg( [
				'action' => self::MOVE_DOWN,
				'post' => $post->ID,
				'order' => $order,
				'timestamp' => $timestamp,
				'nonce' => OPB::nonce_create( self::MOVE_DOWN, $post->ID, $order ),
			], admin_url( 'admin-ajax.php' ) ),
			'class' => 'opb-table-link',
		] ), esc_html__( 'Move Down', 'opb' ) );
	}

	private static function move_down_text(): string {
		return sprintf( '<span>%s</span>', esc_html__( 'Move Down', 'opb' ) );
	}

	public static function move_down_action(): void {
		$post = OPB_Request::get_post();
		if ( $post->post_type !== 'post' )
			exit( 'post' );
		if ( !has_category( 'categories', $post ) )
			exit( 'post' );
		if ( !current_user_can( 'edit_post', $post->ID ) )
			exit( 'role' );
		$order = OPB_Request::get_int( 'order' );
		OPB::nonce_verify( self::MOVE_DOWN, $post->ID, $order );
		$option = OPB::get_option();
		$timestamp = OPB_Request::get_int( 'timestamp' );
		if ( $timestamp !== $option['timestamp'] )
			exit( 'timestamp' );
		$n = count( array_filter( $option['edge_list'], function( array $edge ) use ( $post ): bool {
			return $edge['parent'] === $post->ID;
		} ) );
		if ( $order >= $n - 1 )
			exit( 'order' );
		$edge_list = array_map( function( array $edge ) use ( $post, $order ): array {
			if ( $edge['parent'] !== $post->ID )
				return $edge;
			if ( $edge['order'] === $order )
				$edge['order']++;
			elseif ( $edge['order'] === $order + 1 )
				$edge['order']--;
			return $edge;
		}, $option['edge_list'] );
		OPB::set_option( $edge_list );
		OPB::success( self::home( $post ) );
	}

	private static function delete_link( WP_Post $post, int $child, string $title, int $order, int $timestamp ): string {
		return sprintf( '<span class="delete"><a%s>%s</a></span>', OPB::atts( [
			'href' => add_query_arg( [
				'action' => self::DELETE,
				'post' => $post->ID,
				'child' => $child,
				'order' => $order,
				'timestamp' => $timestamp,
				'nonce' => OPB::nonce_create( self::DELETE, $post->ID, $child, $order ),
			], admin_url( 'admin-ajax.php' ) ),
			'class' => 'opb-table-link',
			'data-opb-table-confirm' => esc_attr( sprintf( __( 'Remove child %s with order %d?', 'opb' ), $title, $order ) ),
		] ), esc_html__( 'Remove', 'opb' ) );
	}

	public static function delete_action(): void {
		$post = OPB_Request::get_post();
		if ( $post->post_type !== 'post' )
			exit( 'post' );
		if ( !has_category( 'categories', $post ) )
			exit( 'post' );
		if ( !current_user_can( 'edit_post', $post->ID ) )
			exit( 'role' );
		$child = OPB_Request::get_post( 'child' );
		$order = OPB_Request::get_int( 'order' );
		OPB::nonce_verify( self::DELETE, $post->ID, $child->ID, $order );
		$option = OPB::get_option();
		$timestamp = OPB_Request::get_int( 'timestamp' );
		if ( $timestamp !== $option['timestamp'] )
			exit( 'timestamp' );
		$edge_list = array_values( array_filter( $option['edge_list'], function( array $edge ) use ( $post, $child, $order ): bool {
			return $edge['parent'] !== $post->ID || $edge['child'] !== $child->ID || $edge['order'] !== $order;
		} ) );
		OPB::set_option( $edge_list );
		OPB::success( self::home( $post ) );
	}
}

OPB_Children_Meta_Box::init();
