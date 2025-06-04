<?php

if ( !defined( 'ABSPATH' ) )
	exit;

add_filter( 'the_content', function( string $content ): string {
	if ( !has_category( 'categories' ) )
		return $content;
	$meta_list = OPB::get_option()['meta_list'];
	$meta_list = array_filter( $meta_list, function( array $meta ): bool {
		return $meta['parent'] === get_the_ID();
	} );
	$content .= '<ul>' . "\n";
	foreach ( $meta_list as $meta ) {
		if ( $meta['parent'] !== get_the_ID() )
			continue;
		$content .= sprintf( '<li><a href="%s">%s</a></li>', get_permalink( $meta['child'] ), esc_html( get_the_title( $meta['child'] ) ) ) . "\n";
	}
	$content .= '</ul>' . "\n";
	return $content;
} );
