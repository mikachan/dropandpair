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

echo "OK\n";
