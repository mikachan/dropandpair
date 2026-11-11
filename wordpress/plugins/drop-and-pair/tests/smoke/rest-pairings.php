<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// A published ride read over REST: logged out it shows only the public links, never an
// email; someone who can edit the ride sees every link with its email. Writes from any
// client are normalised. A suggestion stored while the editor has the ride open survives
// the editor's next save. Every post it makes is deleted at the end.
// rest_do_request dispatches without the authentication step, so coming soon mode
// (which refuses anonymous REST there) doesn't get in the way and stays on.
$ids  = array();
$was  = get_current_user_id();
$done = static function () use ( &$ids, $was ) {
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	wp_set_current_user( $was );
};
$fail = static function ( string $msg ) use ( $done ) {
	$done();
	WP_CLI::error( $msg );
};
$make = static function ( string $type, string $status, string $title ) use ( &$ids, $fail ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => $status, 'post_title' => $title ) );
	$id && ! is_wp_error( $id ) || $fail( "Could not create $title" );
	$ids[] = (int) $id;
	return (int) $id;
};

$live    = $make( 'dp_pairing', 'publish', 'Smoke live pairing' );
$unrated = $make( 'dp_pairing', 'publish', 'Smoke unrated pairing' );
$draft   = $make( 'dp_pairing', 'draft', 'Smoke draft pairing' );
$trashed = $make( 'dp_pairing', 'draft', 'Smoke trashed pairing' );
wp_trash_post( $trashed );
$ride = $make( 'dp_ride', 'publish', 'Smoke REST ride' );

$link = static fn( int $id, ?int $strength ) => array(
	'pairing_id'      => $id,
	'blurb'           => "Blurb $id",
	'strength'        => $strength,
	'suggested_by'    => 'Sam',
	'submitter_email' => 'sam@example.com',
);
// Stored straight into the table, so the scrub hooks leave the live link's email in place:
// the REST view must hide it on its own.
global $wpdb;
$links = array( $link( $live, 4 ), $link( $unrated, null ), $link( $draft, 3 ), $link( $trashed, 5 ) );
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $ride, 'meta_key' => 'dp_pairings', 'meta_value' => maybe_serialize( $links ) ) );
wp_cache_delete( $ride, 'post_meta' );

$get = static function ( string $context = 'view' ) use ( $ride, $fail ) {
	$req = new WP_REST_Request( 'GET', "/wp/v2/dp_ride/$ride" );
	$req->set_param( 'context', $context );
	$res = rest_do_request( $req );
	200 === $res->get_status() || $fail( "GET ($context) failed: " . $res->get_status() );
	return $res->get_data()['meta']['dp_pairings'];
};

wp_set_current_user( 0 );
$public = $get();
array( $live ) === array_column( $public, 'pairing_id' ) || $fail( 'Logged out saw non-public links: ' . wp_json_encode( $public ) );
array_key_exists( 'submitter_email', $public[0] ) && $fail( 'Logged out saw a submitter_email key' );
false === strpos( wp_json_encode( $public ), 'sam@example.com' ) || $fail( 'Email in the logged-out response' );
// The callback on its own, with no ride in the global post, gives the public view too.
$GLOBALS['post'] = null;
array( $live ) === array_column( DropAndPair\ContentTypes::prepare_pairings_for_rest( $links ), 'pairing_id' ) || $fail( 'No-ride fallback was not the public view' );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$admins || $fail( 'No administrator' );
wp_set_current_user( $admins[0]->ID );
$all = $get( 'edit' );
array( $live, $unrated, $draft, $trashed ) === array_column( $all, 'pairing_id' ) || $fail( 'Admin did not get every link' );
array( 'sam@example.com' ) === array_values( array_unique( array_column( $all, 'submitter_email' ) ) ) || $fail( 'Admin did not get the emails' );

// Writes are normalised whoever sends them: here the editor's REST route with messy values.
$req = new WP_REST_Request( 'POST', "/wp/v2/dp_ride/$ride" );
$req->set_header( 'content-type', 'application/json' );
$req->set_body(
	wp_json_encode(
		array(
			'meta' => array(
				'dp_pairings' => array(
					array( 'pairing_id' => $draft, 'blurb' => '  Spaced out  ', 'strength' => 9, 'suggested_by' => ' ', 'submitter_email' => null ),
					array( 'pairing_id' => $draft, 'blurb' => 'Duplicate', 'strength' => 2, 'suggested_by' => null, 'submitter_email' => null ),
					array( 'pairing_id' => $live, 'blurb' => '', 'strength' => 2, 'suggested_by' => null, 'submitter_email' => null ),
				),
			),
		)
	)
);
$res = rest_do_request( $req );
200 === $res->get_status() || $fail( 'POST failed: ' . wp_json_encode( $res->get_data() ) );
$stored = get_post_meta( $ride, 'dp_pairings', true );
$expect = array(
	array( 'pairing_id' => $draft, 'blurb' => 'Spaced out', 'strength' => null, 'suggested_by' => null, 'submitter_email' => null ),
);
$expect === $stored || $fail( 'REST write was not normalised: ' . wp_json_encode( $stored ) );

update_post_meta( $ride, 'dp_pairings', array( array( 'pairing_id' => (string) $live, 'blurb' => ' b ', 'strength' => '3' ), 'junk' ) );
array( array( 'pairing_id' => $live, 'blurb' => 'b', 'strength' => 3, 'suggested_by' => null, 'submitter_email' => null ) ) === get_post_meta( $ride, 'dp_pairings', true ) || $fail( 'update_post_meta was not normalised' );

// A suggestion lands while the editor has the ride open. The editor's base is what it
// loaded; its save resends that list (one blurb edited) and must not drop the suggestion.
$open    = $make( 'dp_ride', 'draft', 'Smoke open-in-editor ride' );
$first   = $make( 'dp_pairing', 'draft', 'Smoke first pairing' );
$second  = $make( 'dp_pairing', 'draft', 'Smoke second pairing' );
$arrival = $make( 'dp_pairing', 'draft', 'Smoke arriving suggestion' );
update_post_meta( $open, 'dp_pairings', array( $link( $first, 3 ), $link( $second, 2 ) ) );
$loaded = ( static function () use ( $open, $fail ) {
	$req = new WP_REST_Request( 'GET', "/wp/v2/dp_ride/$open" );
	$req->set_param( 'context', 'edit' );
	$res = rest_do_request( $req );
	200 === $res->get_status() || $fail( 'GET open ride failed' );
	return $res->get_data()['meta']['dp_pairings'];
} )();
$base = array_column( $loaded, 'pairing_id' );

// As Handler::create stores a suggestion.
$links   = get_post_meta( $open, 'dp_pairings', true );
$links[] = array( 'pairing_id' => $arrival, 'blurb' => 'Just in', 'strength' => null, 'suggested_by' => 'Vis', 'submitter_email' => 'vis@example.com' );
update_post_meta( $open, 'dp_pairings', wp_slash( $links ) );

$save = static function ( array $pairings, ?array $base ) use ( $open, $fail ) {
	$body = array( 'meta' => array( 'dp_pairings' => $pairings ) );
	if ( null !== $base ) {
		$body['dp_pairings_base'] = $base;
	}
	$req = new WP_REST_Request( 'POST', "/wp/v2/dp_ride/$open" );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	$res = rest_do_request( $req );
	200 === $res->get_status() || $fail( 'Open ride POST failed: ' . wp_json_encode( $res->get_data() ) );
	return get_post_meta( $open, 'dp_pairings', true );
};

$edited             = $loaded;
$edited[0]['blurb'] = 'Edited in the sidebar';
$after              = $save( $edited, $base );
array( $first, $second, $arrival ) === array_column( $after, 'pairing_id' ) || $fail( 'Arrival was dropped: ' . wp_json_encode( $after ) );
'Edited in the sidebar' === $after[0]['blurb'] || $fail( 'The editor\'s edit did not win' );
'vis@example.com' === $after[2]['submitter_email'] || $fail( 'Arrival lost its email' );
'sam@example.com' === $after[1]['submitter_email'] || $fail( 'A resent link lost its email' );

// Now the editor has shown all three and deliberately removes the second.
$after = $save( array( $after[0], $after[2] ), array( $first, $second, $arrival ) );
array( $first, $arrival ) === array_column( $after, 'pairing_id' ) || $fail( 'Deliberate removal was undone: ' . wp_json_encode( $after ) );

// Without a base the list is taken as sent, as before.
$after = $save( array( $after[0] ), null );
array( $first ) === array_column( $after, 'pairing_id' ) || $fail( 'A save without a base was merged' );

$done();
WP_CLI::success( 'OK' );
