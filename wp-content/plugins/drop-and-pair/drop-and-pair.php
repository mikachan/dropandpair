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

// Composer is for development (PHPUnit). The deployed plugin has no vendor/, so it
// falls back to this PSR-4 loader: DropAndPair\Foo\Bar is src/Foo/Bar.php.
if ( file_exists( DROP_AND_PAIR_DIR . 'vendor/autoload.php' ) ) {
	require_once DROP_AND_PAIR_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'DropAndPair\\';
			if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
				return;
			}
			$file = DROP_AND_PAIR_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
		}
	);
}

DropAndPair\Analytics::hooks();
DropAndPair\AssetVersions::hooks();
DropAndPair\ComingSoon::hooks();
DropAndPair\ContentTypes::hooks();
DropAndPair\RidePairings::hooks();
DropAndPair\Search::hooks();
DropAndPair\Seo::hooks();
DropAndPair\Submission\Handler::hooks();
register_activation_hook( __FILE__, array( DropAndPair\ContentTypes::class, 'seed_terms' ) );
register_activation_hook( __FILE__, array( DropAndPair\Submission\Handler::class, 'activate' ) );
register_activation_hook( __FILE__, array( DropAndPair\ComingSoon::class, 'activate' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'dropandpair', DropAndPair\Import\Command::class );
}

/** Dynamic blocks: each folder under blocks/ has a block.json and a PHP render file. */
add_action(
	'init',
	static function () {
		foreach ( glob( DROP_AND_PAIR_DIR . 'blocks/*/block.json' ) ?: array() as $file ) {
			register_block_type( dirname( $file ) );
		}
	}
);

/**
 * Editor sidebars: one plain-JS script per post type (no build step), each adding a
 * document settings panel for that type's meta. Registered always, enqueued only
 * on the matching post type's editor.
 */
add_action(
	'init',
	static function () {
		$deps = array( 'wp-api-fetch', 'wp-plugins', 'wp-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-i18n' );
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
