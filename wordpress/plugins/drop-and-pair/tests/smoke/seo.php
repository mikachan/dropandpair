<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// Publishes two rides and a pairing, checks feed order, description counts and redirects, then restores.
use DropAndPair\Seo;

global $wpdb;
$id_of = static function ( string $slug ) use ( $wpdb ): int {
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type IN ('dp_park','dp_ride','dp_pairing') AND post_status <> 'trash'", $slug ) );
	$id || WP_CLI::error( "Missing seed post $slug" );
	return $id;
};
$ids = array(
	'park' => $id_of( 'energylandia' ),
	'a'    => $id_of( 'hyperion' ),
	'b'    => $id_of( 'aquila' ),
	'pair' => $id_of( 'nothing-for-ten-minutes' ),
	'draft' => $id_of( 'bizarro' ),
);
$status   = array();
$meta     = array();
$dates = array();
foreach ( $ids as $k => $id ) {
	$status[ $k ] = get_post_status( $id );
	$row          = get_post( $id );
	$dates[ $k ]  = array( $row->post_date, $row->post_date_gmt, $row->post_modified, $row->post_modified_gmt );
}
foreach ( array( 'a', 'b' ) as $k ) {
	$meta[ $k ] = array(
		'added' => get_post_meta( $ids[ $k ], 'dp_added', false ),
		'links' => get_post_meta( $ids[ $k ], 'dp_pairings', true ),
	);
}
$restore = static function () use ( $ids, $status, $meta, $dates, $wpdb ) {
	foreach ( $ids as $k => $id ) {
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_status'       => $status[ $k ],
				'post_date'         => $dates[ $k ][0],
				'post_date_gmt'     => $dates[ $k ][1],
				'post_modified'     => $dates[ $k ][2],
				'post_modified_gmt' => $dates[ $k ][3],
			),
			array( 'ID' => $id )
		);
		clean_post_cache( $id );
	}
	foreach ( $meta as $k => $m ) {
		delete_post_meta( $ids[ $k ], 'dp_added' );
		foreach ( $m['added'] as $v ) {
			add_post_meta( $ids[ $k ], 'dp_added', $v );
		}
		update_post_meta( $ids[ $k ], 'dp_pairings', $m['links'] );
	}
};
$fail = static function ( string $msg ) {
	throw new RuntimeException( $msg );
};
try {

foreach ( array( 'park', 'a', 'b' ) as $k ) {
	wp_update_post( array( 'ID' => $ids[ $k ], 'post_status' => 'publish' ) );
}
// Pairing stays as-is (a draft in the seed data): it must not be counted.
update_post_meta( $ids['a'], 'dp_added', '2020-01-02' );
delete_post_meta( $ids['b'], 'dp_added' );

$q   = new WP_Query( Seo::feed_query_args() );
$got = wp_list_pluck( $q->posts, 'ID' );
$pos = array_flip( $got );
foreach ( $q->posts as $p ) {
	'publish' === $p->post_status && 'dp_ride' === $p->post_type || $fail( 'feed has a non-published or non-ride post' );
}
isset( $pos[ $ids['a'] ], $pos[ $ids['b'] ] ) || $fail( 'dated or undated ride missing from feed' );
$pos[ $ids['a'] ] < $pos[ $ids['b'] ] || $fail( 'undated ride should come after dated one' );

// Draft pairings are not counted.
$public = count( DropAndPair\Queries::public_links_for_ride( $ids['a'] ) );
$total  = count( (array) get_post_meta( $ids['a'], 'dp_pairings', true ) );
$desc   = Seo::ride_description( get_post( $ids['a'] ) );
if ( $total <= $public ) {
	$fail( "seed ride needs a draft pairing to prove the count ($public/$total)" );
}
$expect = $public > 0 ? $public . ( 1 === $public ? ' pairing.' : ' pairings.' ) : '';
if ( '' !== $expect ? substr( $desc, -strlen( $expect ) ) !== $expect : preg_match( '/\d+ pairings?\./', $desc ) ) {
	$fail( "description pairing count wrong: $desc" );
}
// JSON-LD mentions only public pairings.
$related = static fn() => array_column( Seo::ride_node( get_post( $ids['a'] ) )['isRelatedTo'] ?? array(), 'name' );
$draft_title = get_the_title( $ids['pair'] );
count( $related() ) === $public || $fail( 'JSON-LD related count should match public links' );
in_array( $draft_title, $related(), true ) && $fail( 'JSON-LD leaks a draft pairing title' );
// Now publish the pairing: count must rise by one.
wp_update_post( array( 'ID' => $ids['pair'], 'post_status' => 'publish' ) );
if ( count( DropAndPair\Queries::public_links_for_ride( $ids['a'] ) ) !== $public + 1 ) {
	$fail( 'publishing the pairing did not raise the public count' );
}
count( $related() ) === $public + 1 || $fail( 'JSON-LD should gain the published pairing' );
in_array( $draft_title, $related(), true ) || $fail( 'JSON-LD missing the published pairing title' );

// The real main feed: published ride in, draft ride out, title escaped, permalink guid.
$GLOBALS['dp_smoke_title'] = get_the_title( $ids['a'] ) . ', ' . get_the_title( $ids['park'] );
require_once __DIR__ . '/_gate.php';
$res = dp_smoke_without_gate( static fn() => wp_remote_get( home_url( '/feed/?dpnocache=' . time() ), array( 'timeout' => 30 ) ) );
is_wp_error( $res ) && $fail( 'feed fetch failed: ' . $res->get_error_message() );
$xml = wp_remote_retrieve_body( $res );
$feed = simplexml_load_string( $xml );
false === $feed && $fail( 'feed is not valid XML' );
str_contains( $xml, '<title>' . esc_html( $GLOBALS['dp_smoke_title'] ) . '</title>' ) || $fail( 'feed lacks "<Ride>, <Park>" title' );
str_contains( $xml, '<guid isPermaLink="true">' . get_permalink( $ids['a'] ) . '</guid>' ) || $fail( 'feed guid is not the permalink' );
$gist = (string) get_post_meta( $ids['a'], 'dp_gist', true );
str_contains( $xml, esc_html( substr( $gist, 0, 30 ) ) ) || str_contains( $xml, substr( $gist, 0, 30 ) ) || $fail( 'feed lacks the ride description' );
str_contains( $xml, 'pairing' ) || $fail( 'feed description lacks the pairing count' );
str_contains( $xml, get_the_title( $ids['draft'] ) ) && $fail( 'feed leaks a draft ride' );
str_contains( $xml, '<category><![CDATA[' ) || $fail( 'feed lacks ride type category' );

Seo::redirect_target( '/rss.xml' ) === '/feed/' || $fail( 'rss redirect' );
Seo::redirect_target( '/sitemap.xml' ) === '/wp-sitemap.xml' || $fail( 'sitemap redirect' );
Seo::redirect_target( '/search/?q=big+drop&x=1' ) === '/?s=big%20drop' || $fail( 'search redirect' );
Seo::redirect_target( '//evil.com/rss.xml' ) === null || $fail( 'protocol-relative path matched' );
Seo::redirect_target( '/search/?q[]=x' ) === '/?s=' || $fail( 'array q' );
Seo::redirect_target( '/rides/hyperion' ) === null || $fail( 'unrelated path redirected' );

$json = wp_json_encode( Seo::ride_node( get_post( $ids['a'] ) ) );
json_decode( $json ) !== null || $fail( 'ride node not valid JSON' );
Seo::describe( 'Gist.', 'Coaster', 'P', 'C', 1 ) === 'Gist. Coaster at P, C. 1 pairing.' || $fail( 'describe format' );

} catch ( Throwable $e ) {
	$restore();
	WP_CLI::error( $e->getMessage() );
}
$restore();
echo "OK\n";
