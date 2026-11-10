<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// A published ride read over REST: logged out it shows only the public links, never an
// email; someone who can edit the ride sees every link with its email. Writes from any
// client are normalised. Every post it makes is deleted at the end.
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

$done();
WP_CLI::success( 'OK' );
