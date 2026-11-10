<?php
/**
 * "Region, Country" and the park's website. Parts with no value are left out.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$park_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$place   = array_filter(
	array( get_post_meta( $park_id, 'dp_region', true ), get_post_meta( $park_id, 'dp_country', true ) ),
	static fn( $v ) => is_string( $v ) && '' !== trim( $v )
);
$site    = get_post_meta( $park_id, 'dp_website', true );
$site    = is_string( $site ) ? esc_url( $site, array( 'http', 'https' ) ) : '';
$parts   = array();
if ( $place ) {
	$parts[] = esc_html( implode( ', ', $place ) );
}
if ( '' !== $site ) {
	$parts[] = sprintf( '<a href="%s" rel="noopener">Website</a>', $site );
}
if ( ! $parts ) {
	return;
}
printf( '<p %s>%s</p>', get_block_wrapper_attributes( array( 'class' => 'dp-park-meta dp-label' ) ), implode( ' · ', $parts ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- parts are escaped above.
