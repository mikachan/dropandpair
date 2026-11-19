<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// Proves the meta the editor sidebar edits round-trips through REST, as the block editor does.
$fail = static function ( string $msg ) {
	WP_CLI::error( $msg );
};

global $wpdb;
$id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'dp_ride' AND post_name = 'hyperion' LIMIT 1" );
$id || $fail( 'Imported ride "hyperion" not found; run the importer first' );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$admins || $fail( 'No administrator' );
wp_set_current_user( $admins[0]->ID );

$get = static function () use ( $id ) {
	$req = new WP_REST_Request( 'GET', "/wp/v2/dp_ride/$id" );
	$req->set_param( 'context', 'edit' );
	$res = rest_do_request( $req );
	200 === $res->get_status() || WP_CLI::error( 'GET failed: ' . $res->get_status() );
	return $res->get_data()['meta'];
};
$post = static function ( array $meta ) use ( $id ) {
	$req = new WP_REST_Request( 'POST', "/wp/v2/dp_ride/$id" );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( array( 'meta' => $meta ) ) );
	$res = rest_do_request( $req );
	200 === $res->get_status() || WP_CLI::error( 'POST failed: ' . wp_json_encode( $res->get_data() ) );
};

$before = $get();
count( $before['dp_pairings'] ) >= 2 || $fail( 'Expected hyperion to have several pairing links' );
$orig = array( 'dp_pairings' => $before['dp_pairings'], 'dp_manufacturer' => $before['dp_manufacturer'] );

try {
	$reversed = array_reverse( $before['dp_pairings'] );
	$post( array( 'dp_pairings' => $reversed, 'dp_manufacturer' => 'Round-trip maker' ) );
	$after = $get();
	wp_list_pluck( $after['dp_pairings'], 'pairing_id' ) === wp_list_pluck( $reversed, 'pairing_id' ) || $fail( 'Pairing order did not stick' );
	$after['dp_pairings'] === $reversed || $fail( 'Pairing fields changed in the round trip' );
	'Round-trip maker' === $after['dp_manufacturer'] || $fail( 'Manufacturer did not stick' );
} finally {
	$post( $orig );
}
$back = $get();
$back['dp_pairings'] === $orig['dp_pairings'] && $back['dp_manufacturer'] === $orig['dp_manufacturer'] || $fail( 'Original values were not restored' );

WP_CLI::success( 'OK' );
