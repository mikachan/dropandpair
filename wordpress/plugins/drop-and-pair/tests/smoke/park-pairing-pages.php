<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// Creates its own posts (deleted at the end, even on failure) and leaves existing ones alone.
$ids  = array();
$hidden = array(); // Published pairings drafted for the empty-moment check; always put back.
$unhide = static function () use ( &$hidden ) {
	global $wpdb;
	foreach ( $hidden as $id ) {
		// Direct write: no hooks, so nothing else about the post changes.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) );
		clean_post_cache( $id );
	}
	$hidden = array();
};
$fail = static function ( string $msg ) use ( &$ids, $unhide ) {
	$unhide();
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	WP_CLI::error( $msg );
};
$make = static function ( string $type, string $status, string $title, array $meta = array() ) use ( &$ids, $fail ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => $status, 'post_title' => $title, 'meta_input' => $meta ) );
	$id && ! is_wp_error( $id ) || $fail( "Could not create $title" );
	$ids[] = $id;
	return $id;
};
$render = static function ( int $post_id, string $template ) {
	$html = file_get_contents( get_theme_root() . '/drop-and-pair/templates/' . $template );
	$html = substr( $html, strpos( $html, '<!-- wp:group' ) );
	$html = substr( $html, 0, strpos( $html, '<!-- wp:template-part {"slug":"footer"' ) );
	global $post;
	$post = get_post( $post_id );
	setup_postdata( $post );
	$out = do_blocks( $html );
	wp_reset_postdata();
	return $out;
};

// --- Park page ---
$park   = $make( 'dp_park', 'publish', 'Zzz Smoke Park', array( 'dp_country' => 'Zzland', 'dp_region' => 'Smokeshire', 'dp_website' => 'https://example.com/park' ) );
$ride_a = $make( 'dp_ride', 'publish', 'Smoke Ride Alpha', array( 'dp_park_id' => $park, 'dp_opened' => 2011, 'dp_intensity' => 4, 'dp_roughness' => 2 ) );
$ride_b = $make( 'dp_ride', 'draft', 'Smoke Ride Draft', array( 'dp_park_id' => $park ) );
$ride_c = $make( 'dp_ride', 'publish', 'Smoke Ride Beta', array( 'dp_park_id' => $park ) );
wp_set_object_terms( $ride_a, 'coaster', 'dp_ride_type' );

$html = $render( $park, 'single-dp_park.html' );
// By reference: $html is reassigned for each page below.
$has  = static function ( string $needle ) use ( &$html ) {
	return false !== strpos( $html, $needle );
};
$has( 'Smoke Ride Alpha' ) && $has( 'Smoke Ride Beta' ) || $fail( 'Published rides missing from park page' );
$has( 'Smoke Ride Draft' ) && $fail( 'Draft ride shown on park page' );
strpos( $html, 'Smoke Ride Alpha' ) < strpos( $html, 'Smoke Ride Beta' ) || $fail( 'Rides not sorted by name' );
$has( 'Smokeshire, Zzland' ) || $fail( 'Region, Country missing' );
$has( 'href="https://example.com/park"' ) || $fail( 'Website link missing' );
$has( 'Coaster' ) && $has( '2011' ) && $has( 'strong' ) && $has( 'smooth' ) || $fail( 'Ride columns missing' );

// --- Park archive ---
$archive = do_blocks( '<!-- wp:drop-and-pair/parks-by-country /-->' );
false !== strpos( $archive, '<h2>Zzland</h2>' ) || $fail( 'Country heading missing' );
false !== strpos( $archive, '2 rides' ) || $fail( 'Ride count should count published rides only' );
wp_update_post( array( 'ID' => $park, 'post_status' => 'draft' ) );
false !== strpos( do_blocks( '<!-- wp:drop-and-pair/parks-by-country /-->' ), 'Zzz Smoke Park' ) && $fail( 'Draft park in archive' );
wp_update_post( array( 'ID' => $park, 'post_status' => 'publish' ) );

// --- Pairing page ---
$pairing = $make( 'dp_pairing', 'publish', 'Smoke Pairing', array( 'dp_description' => 'A smoke description', 'dp_url' => 'https://example.com/find' ) );
// Two moments: the earlier in Vocab order ("before") must win over alphabetical ("read" > "before" anyway, so use "do-next" vs "before" vs "eat-and-drink").
wp_set_object_terms( $pairing, array( 'eat-and-drink', 'before' ), 'dp_moment' );
\DropAndPair\Queries::moment_for_pairing( $pairing ) === 'before' || $fail( 'moment_for_pairing should pick the lowest Vocab rank' );
$link = static fn( $strength, $blurb ) => array( array( 'pairing_id' => $pairing, 'blurb' => $blurb, 'strength' => $strength, 'suggested_by' => 'Sam', 'submitter_email' => 'sam@example.com' ) );
update_post_meta( $ride_a, 'dp_pairings', $link( 4, 'Blurb for alpha' ) );
update_post_meta( $ride_b, 'dp_pairings', $link( 4, 'Blurb for draft ride' ) );
update_post_meta( $ride_c, 'dp_pairings', $link( null, 'Blurb for non-public link' ) );

$html = $render( $pairing, 'single-dp_pairing.html' );
$has( 'Smoke Ride Alpha' ) && $has( 'Blurb for alpha' ) || $fail( 'Public ride ticket missing' );
$has( 'strong match' ) && $has( 'Suggested by Sam' ) || $fail( 'Match word or suggester missing' );
$has( 'Smoke Ride Draft' ) && $fail( 'Draft ride listed on pairing page' );
$has( 'Smoke Ride Beta' ) && $fail( 'Non-public link listed on pairing page' );
$has( '@' ) && $fail( 'An @ appears in pairing page (email leak?)' );
$has( 'A smoke description' ) || $fail( 'Description missing' );
$has( '>Find it</a>' ) && $has( 'https://example.com/find' ) || $fail( 'Find it link missing' );
$has( 'stub-before' ) || $fail( 'Stub colour missing' );
delete_post_meta( $pairing, 'dp_url' );
false !== strpos( $render( $pairing, 'single-dp_pairing.html' ), 'Find it' ) && $fail( 'Find it shown with no url' );

// --- Ride browser: unrated intensity isn't offered, but a typed ?intensity=0 still filters ---
$was_qs = $_SERVER['QUERY_STRING'] ?? null;
$_SERVER['QUERY_STRING'] = '';
$html = do_blocks( '<!-- wp:drop-and-pair/ride-browser /-->' );
preg_match( '/<select id="dp-filter-intensity".*?<\/select>/s', $html, $select );
$select || $fail( 'Intensity select missing' );
str_contains( $select[0], 'value="4"' ) || $fail( 'Intensity 4 not offered' );
( str_contains( $select[0], 'value="0"' ) || str_contains( $select[0], 'Unrated' ) ) && $fail( 'Unrated intensity offered as a filter' );
$_SERVER['QUERY_STRING'] = 'intensity=0';
$html = do_blocks( '<!-- wp:drop-and-pair/ride-browser /-->' );
if ( null === $was_qs ) {
	unset( $_SERVER['QUERY_STRING'] );
} else {
	$_SERVER['QUERY_STRING'] = $was_qs;
}
$has( 'Smoke Ride Beta' ) && ! $has( 'Smoke Ride Alpha' ) || $fail( '?intensity=0 did not filter to unrated rides' );

// --- Ride pairings: the suggest link follows the suggest page, wherever it lives ---
$suggest_id = (int) get_option( \DropAndPair\Submission\Handler::PAGE_OPTION );
$moved      = static fn( $link, $id ) => $id === $suggest_id ? home_url( '/moved-suggest/' ) : $link;
add_filter( 'page_link', $moved, 10, 2 );
$html = $render( $ride_c, 'single-dp_ride.html' );
remove_filter( 'page_link', $moved, 10 );
$has( esc_url( add_query_arg( 'ride', $ride_c, home_url( '/moved-suggest/' ) ) ) ) || $fail( 'Ride page suggest link ignores the suggest page' );

// --- Pairing archive ---
// A pairing sits under one moment (its lowest Vocab rank), so "eat-and-drink" is not expected from the two-term pairing above.
$used = array();
foreach ( get_posts( array( 'post_type' => 'dp_pairing', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) as $pid ) {
	$used[] = \DropAndPair\Queries::moment_for_pairing( $pid );
}
$expected = array_values( array_intersect( array_keys( \DropAndPair\Vocab::MOMENTS ), $used ) );
// Make the last moment empty for certain: draft whatever is published there, restored below.
$empty = array_key_last( \DropAndPair\Vocab::MOMENTS );
global $wpdb;
foreach ( get_posts( array( 'post_type' => 'dp_pairing', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) as $pid ) {
	if ( \DropAndPair\Queries::moment_for_pairing( $pid ) === $empty ) {
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $pid ) );
		clean_post_cache( $pid );
		$hidden[] = $pid;
	}
}
$expected = array_values( array_diff( $expected, array( $empty ) ) );
// A draft pairing in the empty moment must not make a section either.
$draft = $make( 'dp_pairing', 'draft', 'Smoke Draft Pairing' );
wp_set_object_terms( $draft, $empty, 'dp_moment' );
$html = do_blocks( '<!-- wp:drop-and-pair/pairings-by-moment /-->' );
preg_match_all( '/<section class="dp-archive-group" id="([a-z-]+)"/', $html, $m );
$m[1] === $expected || $fail( 'Sections ' . implode( ',', $m[1] ) . ' != expected ' . implode( ',', $expected ) );
false !== strpos( $html, 'id="' . $empty . '"' ) && $fail( 'Empty moment shown' );
false !== strpos( $html, 'Things to do before you ride.' ) || $fail( 'Moment description missing' );
false !== strpos( $html, 'Smoke Pairing' ) || $fail( 'Pairing ticket missing from archive' );
false !== strpos( $html, 'Smoke Draft Pairing' ) && $fail( 'Draft pairing in archive' );

$unhide();
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
WP_CLI::success( 'OK' );
