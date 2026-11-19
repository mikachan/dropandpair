<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// Publishes what it needs, renders the ride template for hyperion, then restores statuses and meta.
$slugs    = array( 'energylandia', 'hyperion', 'nothing-for-ten-minutes', 'an-ice-cream-you-have-to-eat-fast', 'hyperion-dan-simmons' );
$posts    = array();
$original = array();
foreach ( $slugs as $slug ) {
	// Not WP_Query's `name`: for drafts it matches on a slug the post may not have yet.
	global $wpdb;
	$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type IN ('dp_park','dp_ride','dp_pairing') AND post_status <> 'trash'", $slug ) );
	$found = $id ? array( get_post( $id ) ) : array();
	$found || WP_CLI::error( "Missing seed post $slug" );
	$posts[ $slug ]    = $found[0];
	$original[ $slug ] = $found[0]->post_status;
}
$ride_id       = $posts['hyperion']->ID;
$original_meta = get_post_meta( $ride_id, 'dp_pairings', true );

$restore = static function () use ( $posts, $original, $ride_id, $original_meta ) {
	foreach ( $posts as $slug => $post ) {
		global $wpdb;
		// Direct write: wp_update_post would fire the email-scrubbing hooks again.
		$wpdb->update( $wpdb->posts, array( 'post_status' => $original[ $slug ] ), array( 'ID' => $post->ID ) );
		clean_post_cache( $post->ID );
	}
	update_post_meta( $ride_id, 'dp_pairings', $original_meta );
};
$fail    = static function ( string $msg ) use ( $restore ) {
	$restore();
	WP_CLI::error( $msg );
};

foreach ( array( 'energylandia', 'hyperion', 'nothing-for-ten-minutes', 'an-ice-cream-you-have-to-eat-fast' ) as $slug ) {
	wp_update_post( array( 'ID' => $posts[ $slug ]->ID, 'post_status' => 'publish' ) );
}
// Give every link a fake submitter email, then null the ice cream strength.
$ice_id = $posts['an-ice-cream-you-have-to-eat-fast']->ID;
$links  = array_map(
	static function ( $link ) use ( $ice_id ) {
		$link['submitter_email'] = 'secret-' . $link['pairing_id'] . '@example.com';
		if ( (int) $link['pairing_id'] === $ice_id ) {
			$link['strength'] = null;
		}
		return $link;
	},
	$original_meta
);
update_post_meta( $ride_id, 'dp_pairings', $links );

$template = file_get_contents( get_theme_root() . '/drop-and-pair/templates/single-dp_ride.html' );
// Header and footer are theme parts, not what's under test; render only the main group.
$template = substr( $template, strpos( $template, '<!-- wp:group' ) );
$template = substr( $template, 0, strpos( $template, '<!-- wp:template-part {"slug":"footer"' ) );

global $post;
$post = get_post( $ride_id );
setup_postdata( $post );
$html = do_blocks( $template );
wp_reset_postdata();

$has = static fn( string $needle ) => false !== strpos( $html, $needle );
// Titles come from the posts, so a retitle in the editor doesn't break the check.
$title = static fn( string $slug ) => esc_html( get_the_title( $posts[ $slug ] ) );
$has( $title( 'nothing-for-ten-minutes' ) ) || $fail( 'Public pairing missing' );
$has( 'strong match' ) || $fail( 'Strength word missing' );
$has( $title( 'hyperion-dan-simmons' ) ) && $fail( 'Draft pairing shown' );
$has( $title( 'an-ice-cream-you-have-to-eat-fast' ) ) && $fail( 'Pairing with no strength shown' );
$has( '★' ) && $fail( 'Stars shown' );
$has( '77 m' ) || $fail( 'Height missing' );
$has( '1,450 m' ) || $fail( 'Length missing' );
$has( '●●●●●' ) || $fail( 'Intensity dots missing' );
$has( '@' ) && $fail( 'An @ appears in the output (email leak?)' );
$has( 'Energylandia' ) || $fail( 'Park missing from ride meta' );
$has( '/suggest/?ride=' ) || $fail( 'Suggest link missing' );

// Empty state: with every link non-public the copy appears.
update_post_meta( $ride_id, 'dp_pairings', array() );
$post = get_post( $ride_id );
setup_postdata( $post );
$empty = do_blocks( $template );
wp_reset_postdata();
false !== strpos( $empty, 'No pairings yet. Got one?' ) || $fail( 'Empty state missing' );

$restore();
foreach ( $posts as $slug => $p ) {
	get_post_status( $p->ID ) === $original[ $slug ] || WP_CLI::error( "Status of $slug not restored" );
}
WP_CLI::success( 'OK' );
