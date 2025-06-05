<?php

if ( !defined( 'ABSPATH' ) )
	exit;

add_filter( 'the_content', function( string $content ): string {
	if ( !has_category( 'categories' ) )
		return $content;
	$edge_list = OPB::get_option()['edge_list'];
	$edge_list = array_filter( $edge_list, function( array $edge ): bool {
		return $edge['parent'] === get_the_ID();
	} );
	$content .= '<ul>' . "\n";
	foreach ( $edge_list as $edge ) {
		if ( $edge['parent'] !== get_the_ID() )
			continue;
		$content .= sprintf( '<li><a href="%s">%s</a></li>', get_permalink( $edge['child'] ), esc_html( get_the_title( $edge['child'] ) ) ) . "\n";
	}
	$content .= '</ul>' . "\n";
	return $content;
} );
