<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
$fail = static function ( string $msg ) {
	WP_CLI::error( $msg );
};

foreach ( array( 'dp_park', 'dp_ride', 'dp_pairing' ) as $pt ) {
	post_type_exists( $pt ) || $fail( "Missing post type $pt" );
}
foreach ( array( 'dp_ride_type', 'dp_vibe', 'dp_moment' ) as $tax ) {
	taxonomy_exists( $tax ) || $fail( "Missing taxonomy $tax" );
}

$moments = get_terms( array( 'taxonomy' => 'dp_moment', 'hide_empty' => false, 'orderby' => 'term_id', 'fields' => 'slugs' ) );
$expect  = array_keys( \DropAndPair\Vocab::MOMENTS );
$moments === $expect || $fail( 'Moment terms differ: ' . implode( ',', (array) $moments ) );

$types = get_terms( array( 'taxonomy' => 'dp_ride_type', 'hide_empty' => false, 'orderby' => 'term_id', 'fields' => 'slugs' ) );
$types === array_keys( \DropAndPair\Vocab::RIDE_TYPES ) || $fail( 'Ride type terms differ' );

array_key_exists( 'dp_pairings', get_registered_meta_keys( 'post', 'dp_ride' ) ) || $fail( 'dp_pairings meta missing' );

// Lazy seeding: a deleted term is re-created.
$later = get_term_by( 'slug', 'later', 'dp_moment' );
$later || $fail( 'later term missing before delete test' );
wp_delete_term( $later->term_id, 'dp_moment' );
\DropAndPair\ContentTypes::maybe_seed_terms();
get_term_by( 'slug', 'later', 'dp_moment' ) || $fail( 'Deleted moment term was not re-seeded' );

// Email privacy: logged-out REST reads omit submitter_email, editors still see it.
$ride = wp_insert_post( array( 'post_type' => 'dp_ride', 'post_status' => 'publish', 'post_title' => 'Smoke ride' ) );
$ride && ! is_wp_error( $ride ) || $fail( 'Could not create test ride' );
$pairing = wp_insert_post( array( 'post_type' => 'dp_pairing', 'post_status' => 'draft', 'post_title' => 'Smoke pairing' ) );
$pairing && ! is_wp_error( $pairing ) || $fail( 'Could not create test pairing' );
update_post_meta(
	$ride,
	'dp_pairings',
	array( array( 'pairing_id' => $pairing, 'blurb' => 'x', 'strength' => 3, 'suggested_by' => 'Sam', 'submitter_email' => 'sam@example.com' ) )
);
$read = static function () use ( $ride ) {
	return rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/dp_ride/$ride" ) )->get_data();
};
$editors = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
try {
	wp_set_current_user( 0 );
	$public = $read();
	isset( $public['meta']['dp_pairings'][0] ) || $fail( 'Public read returned no pairings meta' );
	array_key_exists( 'submitter_email', $public['meta']['dp_pairings'][0] ) && $fail( 'submitter_email leaked to logged-out read' );
	$editors || $fail( 'No administrator to test with' );
	wp_set_current_user( $editors[0]->ID );
	$private = $read();
	( $private['meta']['dp_pairings'][0]['submitter_email'] ?? null ) === 'sam@example.com' || $fail( 'Editor cannot see submitter_email' );
} finally {
	wp_set_current_user( 0 );
	wp_delete_post( $ride, true );
	wp_delete_post( $pairing, true );
}

echo "OK\n";
