<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// A ride made by the suggestion form has only dp_park_id. The editor reads it over REST and
// sends the whole meta object back with any meta edit, so that round trip must save (no
// rest_invalid_stored_value 500) and leave the unset fields unset, not turned into zeros.
// Every post it makes is deleted at the end.
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
$make = static function ( string $type, string $title, array $meta = array() ) use ( &$ids, $fail ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'draft', 'post_title' => $title, 'meta_input' => $meta ) );
	$id && ! is_wp_error( $id ) || $fail( "Could not create $title" );
	$ids[] = (int) $id;
	return (int) $id;
};

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$admins || $fail( 'No administrator' );
wp_set_current_user( $admins[0]->ID );

$park    = $make( 'dp_park', 'Smoke form park' );
$ride    = $make( 'dp_ride', 'Smoke form ride', array( 'dp_park_id' => $park ) );
$pairing = $make( 'dp_pairing', 'Smoke form pairing' );

$get  = static function () use ( $ride, $fail ) {
	$req = new WP_REST_Request( 'GET', "/wp/v2/dp_ride/$ride" );
	$req->set_param( 'context', 'edit' );
	$res = rest_do_request( $req );
	200 === $res->get_status() || $fail( 'GET failed: ' . $res->get_status() );
	return $res->get_data()['meta'];
};
$post = static function ( array $meta ) use ( $ride ) {
	$req = new WP_REST_Request( 'POST', "/wp/v2/dp_ride/$ride" );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( array( 'meta' => $meta ) ) );
	return rest_do_request( $req );
};

$numeric = array( 'dp_opened', 'dp_inversions', 'dp_height', 'dp_speed', 'dp_length', 'dp_intensity', 'dp_roughness' );
$meta    = $get();
foreach ( $numeric as $key ) {
	array_key_exists( $key, $meta ) && null === $meta[ $key ] || $fail( "Unset $key did not read as null: " . wp_json_encode( $meta[ $key ] ?? 'missing' ) );
}
$park === $meta['dp_park_id'] || $fail( 'dp_park_id did not read back' );
false === $meta['dp_featured'] || $fail( 'Unset dp_featured did not read as false' );

// The editor adds a pairing: the whole meta object goes back, unset fields and all.
$meta['dp_pairings'] = array( array( 'pairing_id' => $pairing, 'blurb' => 'Added in the editor', 'strength' => 3, 'suggested_by' => null, 'submitter_email' => null ) );
$res                 = $post( $meta );
200 === $res->get_status() || $fail( 'Saving a form-made ride failed: ' . $res->get_status() . ' ' . wp_json_encode( $res->get_data() ) );
array( $pairing ) === array_column( get_post_meta( $ride, 'dp_pairings', true ), 'pairing_id' ) || $fail( 'dp_pairings did not save' );
foreach ( $numeric as $key ) {
	metadata_exists( 'post', $ride, $key ) && $fail( "Unset $key was stored as " . wp_json_encode( get_post_meta( $ride, $key, true ) ) );
}
// Core stores the false it's sent as '' (as it does an empty string); either way, not featured.
get_post_meta( $ride, 'dp_featured', true ) && $fail( 'dp_featured turned on' );

// Choosing values stores them, a real zero included; clearing one (null) unsets it again.
$res = $post( array( 'dp_intensity' => 4, 'dp_inversions' => 0, 'dp_height' => 30.5 ) );
200 === $res->get_status() || $fail( 'Setting values failed: ' . wp_json_encode( $res->get_data() ) );
$meta = $get();
4 === $meta['dp_intensity'] && 0 === $meta['dp_inversions'] && 30.5 === (float) $meta['dp_height'] || $fail( 'Chosen values did not read back: ' . wp_json_encode( $meta ) );
$res = $post( array_merge( $meta, array( 'dp_height' => null, 'dp_intensity' => null ) ) );
200 === $res->get_status() || $fail( 'Clearing values failed: ' . wp_json_encode( $res->get_data() ) );
metadata_exists( 'post', $ride, 'dp_height' ) && $fail( 'Cleared dp_height is still stored' );
metadata_exists( 'post', $ride, 'dp_intensity' ) && $fail( 'Cleared dp_intensity is still stored' );
'0' === get_post_meta( $ride, 'dp_inversions', true ) || $fail( 'Zero inversions was lost' );
foreach ( array( 'dp_opened', 'dp_speed', 'dp_length', 'dp_roughness' ) as $key ) {
	metadata_exists( 'post', $ride, $key ) && $fail( "Unset $key was stored by a later save" );
}

$done();
WP_CLI::success( 'OK' );
