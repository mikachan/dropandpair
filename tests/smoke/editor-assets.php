<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
$fail = static function ( string $msg ) {
	WP_CLI::error( $msg );
};

$need  = array( 'wp-api-fetch', 'wp-plugins', 'wp-editor', 'wp-components', 'wp-core-data' );
$files = array(
	'dp-ride-sidebar'    => 'editor/ride-sidebar.js',
	'dp-park-sidebar'    => 'editor/park-sidebar.js',
	'dp-pairing-sidebar' => 'editor/pairing-sidebar.js',
);
$scripts = wp_scripts();
foreach ( $files as $handle => $file ) {
	isset( $scripts->registered[ $handle ] ) || $fail( "Script $handle is not registered" );
	$missing = array_diff( $need, $scripts->registered[ $handle ]->deps );
	$missing && $fail( "$handle lacks deps: " . implode( ',', $missing ) );
	file_exists( DROP_AND_PAIR_DIR . $file ) || $fail( "Missing file $file" );
}
isset( wp_styles()->registered['dp-editor'] ) || $fail( 'Style dp-editor is not registered' );

// Only the matching post type's script is enqueued, and it gets the vocab.
$enqueue = static function ( string $type ) {
	global $post, $current_screen;
	wp_dequeue_script( 'dp-ride-sidebar' );
	wp_dequeue_script( 'dp-park-sidebar' );
	wp_dequeue_script( 'dp-pairing-sidebar' );
	$post = (object) array( 'ID' => 0, 'post_type' => $type );
	do_action( 'enqueue_block_editor_assets' );
};
$enqueue( 'dp_ride' );
wp_script_is( 'dp-ride-sidebar', 'enqueued' ) || $fail( 'Ride script not enqueued for dp_ride' );
wp_script_is( 'dp-park-sidebar', 'enqueued' ) && $fail( 'Park script enqueued for dp_ride' );
$inline = implode( '', (array) $scripts->get_data( 'dp-ride-sidebar', 'before' ) );
str_contains( $inline, 'window.dropAndPair' ) || $fail( 'window.dropAndPair not provided' );
$enqueue( 'post' );
wp_script_is( 'dp-ride-sidebar', 'enqueued' ) && $fail( 'Ride script enqueued for a post' );

WP_CLI::success( 'OK' );
