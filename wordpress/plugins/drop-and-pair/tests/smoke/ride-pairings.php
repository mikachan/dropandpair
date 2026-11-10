<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
$ids  = array();
$fail = static function ( string $msg ) use ( &$ids ) {
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	WP_CLI::error( $msg );
};
$make = static function ( string $type, string $status, string $title ) use ( &$ids, $fail ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => $status, 'post_title' => $title ) );
	$id && ! is_wp_error( $id ) || $fail( "Could not create $title" );
	$ids[] = $id;
	return $id;
};

$pairing_a = $make( 'dp_pairing', 'draft', 'Smoke pairing A' );
$pairing_b = $make( 'dp_pairing', 'draft', 'Smoke pairing B' );
$ride      = $make( 'dp_ride', 'publish', 'Smoke ride' );

$link = static fn( int $id ) => array( 'pairing_id' => $id, 'blurb' => 'x', 'strength' => 3, 'suggested_by' => 'Sam', 'submitter_email' => 'sam@example.com' );
update_post_meta( $ride, 'dp_pairings', array( $link( $pairing_a ), $link( $pairing_b ) ) );

$emails = static function () use ( $ride ) {
	return array_column( get_post_meta( $ride, 'dp_pairings', true ), 'submitter_email', 'pairing_id' );
};

// Both pairings are drafts, so both emails must survive the save.
$emails() === array( $pairing_a => 'sam@example.com', $pairing_b => 'sam@example.com' ) || $fail( 'Email lost while pairings are drafts' );

wp_update_post( array( 'ID' => $pairing_a, 'post_status' => 'publish' ) );
$after = $emails();
null === $after[ $pairing_a ] || $fail( 'Email not scrubbed after pairing published' );
'sam@example.com' === $after[ $pairing_b ] || $fail( 'Draft pairing link lost its email' );

// A published post that isn't a dp_pairing is not a live pairing: its link keeps the email.
$page = $make( 'page', 'publish', 'Smoke page' );
update_post_meta( $ride, 'dp_pairings', array( $link( $page ) ) );
'sam@example.com' === ( $emails()[ $page ] ?? null ) || $fail( 'Link to a non-pairing post lost its email' );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
WP_CLI::success( 'OK' );
