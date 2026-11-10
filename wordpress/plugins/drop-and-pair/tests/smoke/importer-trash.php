<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
$store = new \DropAndPair\Import\WpPostStore();
$id    = wp_insert_post( array( 'post_type' => 'dp_ride', 'post_status' => 'draft', 'post_title' => 'Smoke trash ride', 'post_name' => 'smoke-trash-ride' ) );
$id && ! is_wp_error( $id ) || WP_CLI::error( 'Could not create ride' );

$found_draft = $store->find_by_slug( 'dp_ride', 'smoke-trash-ride' );
wp_trash_post( $id );
$found_trash = $store->find_by_slug( 'dp_ride', 'smoke-trash-ride' );
$status      = get_post_status( $id );
wp_delete_post( $id, true );
$gone = $store->find_by_slug( 'dp_ride', 'smoke-trash-ride' );

$found_draft === $id || WP_CLI::error( 'Draft ride not found' );
'trash' === $status || WP_CLI::error( 'Ride was not trashed' );
$found_trash === $id || WP_CLI::error( 'Trashed ride not found' );
null === $gone || WP_CLI::error( 'Deleted ride still found' );
WP_CLI::success( 'OK' );
