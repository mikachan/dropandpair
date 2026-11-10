<?php
/**
 * "Park · Region, Country · Ride type", with the park linked.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

$ride_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$parts   = array();

$park = Queries::park_for_ride( $ride_id );
if ( $park ) {
	$parts[] = sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $park ) ), esc_html( get_the_title( $park ) ) );
	$place   = array_filter(
		array( get_post_meta( $park->ID, 'dp_region', true ), get_post_meta( $park->ID, 'dp_country', true ) ),
		static fn( $v ) => is_string( $v ) && '' !== $v
	);
	if ( $place ) {
		$parts[] = esc_html( implode( ', ', $place ) );
	}
}

$types = get_the_terms( $ride_id, 'dp_ride_type' );
if ( is_array( $types ) && $types ) {
	$parts[] = esc_html( Vocab::RIDE_TYPES[ $types[0]->slug ] ?? $types[0]->name );
}

if ( ! $parts ) {
	return;
}
printf( '<p %s>%s</p>', get_block_wrapper_attributes( array( 'class' => 'dp-ride-meta dp-label' ) ), implode( ' · ', $parts ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- parts are escaped above.
