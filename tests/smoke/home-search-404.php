<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// Publishes what it needs, renders home, search, about and 404, then restores statuses and meta.
global $wpdb;
$slugs    = array( 'energylandia', 'hyperion', 'nothing-for-ten-minutes', 'hyperion-dan-simmons', 'about' );
$posts    = array();
$original = array();
foreach ( $slugs as $slug ) {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type IN ('dp_park','dp_ride','dp_pairing','page') AND post_status <> 'trash'", $slug ) );
	$id || WP_CLI::error( "Missing seed post $slug" );
	$posts[ $slug ]    = get_post( $id );
	$original[ $slug ] = $posts[ $slug ]->post_status;
}
$ride_id       = $posts['hyperion']->ID;
$original_feat = get_post_meta( $ride_id, 'dp_featured', true );

$restore = static function () use ( $posts, $original, $ride_id, $original_feat ) {
	global $wpdb;
	foreach ( $posts as $slug => $post ) {
		$wpdb->update( $wpdb->posts, array( 'post_status' => $original[ $slug ] ), array( 'ID' => $post->ID ) );
		clean_post_cache( $post->ID );
	}
	update_post_meta( $ride_id, 'dp_featured', $original_feat );
	foreach ( array( 'dp_ride', 'dp_park', 'dp_pairing' ) as $t ) {
		wp_cache_delete( "posts-$t", 'counts' );
	}
};
$fail    = static function ( string $msg ) use ( $restore ) {
	$restore();
	WP_CLI::error( $msg );
};

// Count what's published before we publish anything, so the totals check is exact.
$before = DropAndPair\Queries::totals();
foreach ( array( 'energylandia', 'hyperion', 'nothing-for-ten-minutes', 'about' ) as $slug ) {
	if ( 'publish' !== $original[ $slug ] ) {
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $posts[ $slug ]->ID ) );
		clean_post_cache( $posts[ $slug ]->ID );
	}
}
foreach ( array( 'dp_ride', 'dp_park', 'dp_pairing' ) as $type ) {
	wp_cache_delete( "posts-$type", 'counts' );
}
update_post_meta( $ride_id, 'dp_featured', true );

$render = static function ( string $template ): string {
	$html = file_get_contents( get_theme_root() . '/drop-and-pair/templates/' . $template );
	$html = substr( $html, strpos( $html, '<!-- wp:group' ) );
	$html = substr( $html, 0, strpos( $html, '<!-- wp:template-part {"slug":"footer"' ) );
	return do_blocks( $html );
};

// --- Home ---
$home = $render( 'front-page.html' );
$html = $home;
// By reference: $html is reassigned for each page below.
$has  = static function ( string $needle ) use ( &$html ) {
	return false !== strpos( $html, $needle );
};
foreach ( array( 'Curated pairings for theme park rides', 'Before the queue and after the drop.', 'Browse the rides', 'How this works', 'Worth a ride', 'Newly added', 'Browse by pairing type', 'Suggest a pairing', 'href="/suggest/"' ) as $needle ) {
	$has( $needle ) || $fail( "Home missing: $needle" );
}
$totals = DropAndPair\Queries::totals();
$has( sprintf( '%d ride%s · %d park%s · %d pairing%s', $totals['rides'], 1 === $totals['rides'] ? '' : 's', $totals['parks'], 1 === $totals['parks'] ? '' : 's', $totals['pairings'], 1 === $totals['pairings'] ? '' : 's' ) ) || $fail( 'Totals line missing' );
$totals['rides'] === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type='dp_ride' AND post_status='publish'" ) || $fail( 'Ride total is not the published count' );
$totals['rides'] >= 1 && $before['rides'] <= $totals['rides'] || $fail( 'Totals did not move' );
$has( get_the_title( $ride_id ) ) || $fail( 'Published ride missing from home' );
$has( 'Aquila' ) && $fail( 'Draft ride on home' );
substr_count( $html, 'class="dp-ticket"' ) >= 1 + 9 || $fail( 'Expected ride tickets plus nine moment tickets' );
$has( 'href="' . esc_url( home_url( '/rides/?pairing=before' ) ) . '"' ) || $fail( 'Moment link missing' );
$pos = -1;
foreach ( DropAndPair\Vocab::MOMENTS as $slug => $m ) {
	$at = strpos( $html, 'pairing=' . $slug . '"' );
	$at > $pos || $fail( "Moment $slug out of order" );
	$pos = $at;
}
$has( 'Admit one · Coaster' ) || $fail( 'Ride type missing from ride ticket' );
$has( get_the_title( $posts['energylandia'] ) ) || $fail( 'Park missing from ride ticket' );

$has( '1 pairing<' ) || $has( '0 pairings<' ) || $has( ' pairings<' ) || $fail( 'Moment pairing counts missing' );
$has( '1 pairings' ) && $fail( 'Count not pluralised' );

// --- Search ---
$search = static function ( string $term, bool $main = true ) {
	$q = new WP_Query();
	if ( $main ) {
		$GLOBALS['wp_the_query'] = $q;
		$GLOBALS['wp_query']     = $q;
	}
	$q->query( array( 's' => $term ) );
	return $q;
};
$q   = $search( 'Hyperion' );
$ids = wp_list_pluck( $q->posts, 'ID' );
in_array( $ride_id, $ids, true ) || $fail( 'Search missed the published ride' );
in_array( $posts['hyperion-dan-simmons']->ID, $ids, true ) && $fail( 'Search returned a draft pairing' );
foreach ( $q->posts as $p ) {
	in_array( $p->post_type, DropAndPair\Search::TYPES, true ) && 'publish' === $p->post_status || $fail( 'Unexpected result ' . $p->post_type . '/' . $p->post_status );
}
$html = $render( 'search.html' );
$has( 'dp-label">Ride<' ) || $fail( 'Result type label missing' );
$has( 'Search results for' ) || $fail( 'Search heading missing' );

$other = new WP_Query();
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = $q;
$other->query( array( 's' => 'Hyperion', 'post_type' => 'post' ) );
'post' === $other->get( 'post_type' ) || $fail( 'A non-main query was rewritten' );

$none = $search( 'zzzqqqxxx' );
$none->have_posts() && $fail( 'Nonsense search found something' );
$html = $render( 'search.html' );
$has( 'Nothing matched that' ) || $fail( 'No-results copy missing' );

// --- About (page template + imported content) ---
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query();
global $post;
$post = $posts['about'];
setup_postdata( $post );
$html = $render( 'page.html' );
wp_reset_postdata();
$has( 'Pairing types</h2>' ) && $has( '<h1' ) || $fail( 'About page not rendered' );
$has( '<dt class="dp-type-list__name dp-type--before">Before</dt>' ) || $fail( 'About pairing type classes missing' );

// --- 404 ---
$html = $render( '404.html' );
$has( 'Nothing on that shelf' ) && $has( 'href="/rides/"' ) && $has( 'href="/pairings/"' ) || $fail( '404 copy or links missing' );

$restore();
foreach ( $posts as $slug => $p ) {
	get_post_status( $p->ID ) === $original[ $slug ] || WP_CLI::error( "Status of $slug not restored" );
}
WP_CLI::success( 'OK' );
