<?php
/**
 * Plugin Name: Drop and Pair
 * Description: Rides, parks and pairings for Drop and Pair.
 * Version: 0.1.0
 * Requires PHP: 8.1
 * Text Domain: drop-and-pair
 */

defined( 'ABSPATH' ) || exit;

define( 'DROP_AND_PAIR_VERSION', '0.1.0' );
define( 'DROP_AND_PAIR_DIR', plugin_dir_path( __FILE__ ) );
define( 'DROP_AND_PAIR_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( DROP_AND_PAIR_DIR . 'vendor/autoload.php' ) ) {
	require_once DROP_AND_PAIR_DIR . 'vendor/autoload.php';
}

DropAndPair\ContentTypes::hooks();
DropAndPair\RidePairings::hooks();
register_activation_hook( __FILE__, array( DropAndPair\ContentTypes::class, 'seed_terms' ) );
