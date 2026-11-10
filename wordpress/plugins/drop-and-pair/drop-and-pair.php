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

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'dropandpair', DropAndPair\Import\Command::class );
}

/**
 * Editor sidebars: one plain-JS script per post type (no build step), each adding a
 * document settings panel for that type's meta. Registered always, enqueued only
 * on the matching post type's editor.
 */
add_action(
	'init',
	static function () {
		$deps = array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-i18n' );
		foreach ( array( 'ride', 'park', 'pairing' ) as $name ) {
			wp_register_script( "dp-$name-sidebar", DROP_AND_PAIR_URL . "editor/$name-sidebar.js", $deps, DROP_AND_PAIR_VERSION, true );
		}
		wp_register_style( 'dp-editor', DROP_AND_PAIR_URL . 'editor/editor.css', array(), DROP_AND_PAIR_VERSION );
	}
);

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$post = get_post();
		$type = $post ? $post->post_type : '';
		$map  = array(
			'dp_ride'    => 'dp-ride-sidebar',
			'dp_park'    => 'dp-park-sidebar',
			'dp_pairing' => 'dp-pairing-sidebar',
		);
		if ( ! isset( $map[ $type ] ) ) {
			return;
		}
		$moments = array();
		foreach ( DropAndPair\Vocab::MOMENTS as $slug => $moment ) {
			$moments[ $slug ] = $moment['label'];
		}
		$labels = static function ( callable $fn ): array {
			$out = array();
			for ( $i = 1; $i <= 5; $i++ ) {
				$out[ $i ] = $fn( $i );
			}
			return $out;
		};
		$vocab = array(
			'moments'   => $moments,
			'rideTypes' => DropAndPair\Vocab::RIDE_TYPES,
			'intensity' => $labels( array( DropAndPair\Vocab::class, 'intensity_label' ) ),
			'comfort'   => $labels( array( DropAndPair\Vocab::class, 'comfort_label' ) ),
			'strength'  => $labels( array( DropAndPair\Vocab::class, 'strength_label' ) ),
		);
		wp_enqueue_script( $map[ $type ] );
		wp_add_inline_script( $map[ $type ], 'window.dropAndPair = ' . wp_json_encode( $vocab ) . ';', 'before' );
		wp_enqueue_style( 'dp-editor' );
	}
);
