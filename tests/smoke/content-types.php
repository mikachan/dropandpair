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

// Email privacy: a logged-out REST read gets only public links (none here: the pairing is a
// draft), so no email; editors still see the link and its email. rest-pairings.php covers more.
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
$problem = null;
try {
	wp_set_current_user( 0 );
	$public = $read();
	if ( ! isset( $public['meta'] ) || array() !== $public['meta']['dp_pairings'] ) {
		$problem = 'Logged-out read should get no links while the pairing is a draft';
	} elseif ( ! $editors ) {
		$problem = 'No administrator to test with';
	} else {
		wp_set_current_user( $editors[0]->ID );
		$private = $read();
		( $private['meta']['dp_pairings'][0]['submitter_email'] ?? null ) === 'sam@example.com' || $problem = 'Editor cannot see submitter_email';
	}
} finally {
	// $fail exits, which would skip this, so failures are reported after the cleanup.
	wp_set_current_user( 0 );
	wp_delete_post( $ride, true );
	wp_delete_post( $pairing, true );
}
null === $problem || $fail( $problem );

echo "OK\n";
