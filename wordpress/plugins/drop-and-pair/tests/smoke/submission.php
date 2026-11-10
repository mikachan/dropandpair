<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// Every post it makes (its own fixtures and the drafts the handler creates) is deleted at the end.
use DropAndPair\Submission\Handler;

$types    = array( 'dp_ride', 'dp_park', 'dp_pairing' );
$all_ids  = static fn() => get_posts( array( 'post_type' => $types, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
$before   = $all_ids();
$tokens   = array();
$ips      = array( '192.0.2.10', '192.0.2.11', '192.0.2.12' );
$page_opt = get_option( Handler::PAGE_OPTION );
$mail     = array();

$cleanup = static function () use ( $all_ids, $before, &$tokens, $ips, $page_opt ) {
	foreach ( array_diff( $all_ids(), $before ) as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $tokens as $token ) {
		delete_transient( 'dp_form_' . $token );
	}
	foreach ( $ips as $ip ) {
		delete_transient( 'dp_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 ) );
	}
	update_option( Handler::PAGE_OPTION, $page_opt );
	unset( $_GET['ride'], $_GET['dp_form'], $_GET['thanks'] );
};
$fail = static function ( string $msg ) use ( $cleanup ) {
	$cleanup();
	WP_CLI::error( $msg );
};
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mail ) {
		$mail[] = $atts;
		return true;
	},
	10,
	2
);

$make = static function ( string $type, string $title, array $meta = array() ) use ( $fail ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'meta_input' => $meta ) );
	$id && ! is_wp_error( $id ) || $fail( "Could not create $title" );
	return (int) $id;
};
$park = $make( 'dp_park', 'Smoke park', array( 'dp_country' => 'Smokeland' ) );
$ride = $make( 'dp_ride', 'Smoke ride', array( 'dp_park_id' => $park ) );
update_post_meta( $ride, 'dp_pairings', array() );
$fixtures = $all_ids();

/** Posts of this type the handler has made so far. */
$count = static function ( string $type ) use ( $all_ids, $fixtures ) {
	return count( array_filter( array_diff( $all_ids(), $fixtures ), static fn( $id ) => get_post_type( $id ) === $type ) );
};
$payload = static function ( array $overrides = array() ) use ( $ride ) {
	return $overrides + array(
		Handler::NONCE_FIELD => wp_create_nonce( Handler::ACTION ),
		'started_at'         => (string) ( time() - 10 ),
		'website_url'        => '',
		'ride_id'            => (string) $ride,
		'title'              => 'Smoke chips',
		'moment'             => 'eat-and-drink',
		'blurb'              => 'Hot chips after a cold splash, eaten on the sea wall.',
		'link'               => 'https://example.com/chips',
		'name'               => 'Smoke Sam',
		'email'              => 'sam@example.com',
	);
};
$token_of = static function ( string $url ) use ( &$tokens ) {
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
	if ( isset( $q['dp_form'] ) ) {
		$tokens[] = $q['dp_form'];
	}
	return $q['dp_form'] ?? null;
};

// 1. Existing ride.
$url = Handler::process( $payload(), $ips[0], time() );
$url === Handler::thanks_url() || $fail( "Existing ride: expected the thank-you, got $url" );
1 === $count( 'dp_pairing' ) || $fail( 'Existing ride: expected one new pairing' );
0 === $count( 'dp_ride' ) && 0 === $count( 'dp_park' ) || $fail( 'Existing ride: made a ride or park it should not have' );
$pairing = get_post( array_values( array_diff( $all_ids(), $fixtures ) )[0] );
'draft' === $pairing->post_status || $fail( 'Pairing is not a draft' );
'https://example.com/chips' === get_post_meta( $pairing->ID, 'dp_url', true ) || $fail( 'Pairing link not saved' );
array( 'eat-and-drink' ) === wp_get_object_terms( $pairing->ID, 'dp_moment', array( 'fields' => 'slugs' ) ) || $fail( 'Moment not set' );
'publish' === get_post_status( $ride ) || $fail( 'Ride is no longer published' );
$links = get_post_meta( $ride, 'dp_pairings', true );
$last  = end( $links );
$last['pairing_id'] === $pairing->ID || $fail( 'Last link is not the new pairing' );
null === $last['strength'] || $fail( 'Strength should be null' );
'Smoke Sam' === $last['suggested_by'] || $fail( 'suggested_by not saved' );
'sam@example.com' === $last['submitter_email'] || $fail( 'Email should be kept until the link is public' );
1 === count( $mail ) || $fail( 'Expected one email, got ' . count( $mail ) );
'New pairing suggestion: Smoke chips for Smoke ride' === $mail[0]['subject'] || $fail( 'Subject was ' . $mail[0]['subject'] );
get_option( 'admin_email' ) === $mail[0]['to'] || $fail( 'Email went to the wrong address' );
str_contains( $mail[0]['message'], 'post.php?post=' . $pairing->ID . '&action=edit' ) || $fail( 'Email has no pairing edit link' );
str_contains( $mail[0]['message'], 'Hot chips after a cold splash' ) || $fail( 'Email has no blurb' );

// 2. Bots: honeypot filled, or sent too fast. Both look like they worked and create nothing.
$snapshot = $all_ids();
foreach ( array( array( 'website_url' => 'http://spam.example' ), array( 'started_at' => (string) time() ), array( 'started_at' => '' ) ) as $bot ) {
	Handler::process( $payload( $bot ), $ips[0], time() ) === Handler::thanks_url() || $fail( 'Bot did not get the thank-you' );
}
$all_ids() === $snapshot || $fail( 'A bot submission created posts' );
1 === count( $mail ) || $fail( 'A bot submission sent an email' );

// 3. New ride at the existing park, then new ride at a new park.
Handler::process( $payload( array( 'ride_id' => '0', 'new_ride_name' => 'Smoke new ride', 'park_id' => (string) $park ) ), $ips[0], time() );
1 === $count( 'dp_ride' ) && 0 === $count( 'dp_park' ) || $fail( 'New ride: expected one draft ride and no park' );
$new_ride = get_posts( array( 'post_type' => 'dp_ride', 'post_status' => 'draft', 'title' => 'Smoke new ride', 'fields' => 'ids' ) )[0] ?? 0;
$park === (int) get_post_meta( $new_ride, 'dp_park_id', true ) || $fail( 'New ride not linked to the park' );
1 === count( get_post_meta( $new_ride, 'dp_pairings', true ) ) || $fail( 'New ride has no pairing link' );

Handler::process( $payload( array( 'ride_id' => '0', 'new_ride_name' => 'Smoke newer ride', 'park_id' => '0', 'new_park_name' => 'Smoke new park', 'new_park_country' => 'Smokeland' ) ), $ips[0], time() );
$new_park = get_posts( array( 'post_type' => 'dp_park', 'post_status' => 'draft', 'title' => 'Smoke new park', 'fields' => 'ids' ) )[0] ?? 0;
$new_park || $fail( 'New park not created as a draft' );
'Smokeland' === get_post_meta( $new_park, 'dp_country', true ) || $fail( 'New park country not saved' );
$newer = get_posts( array( 'post_type' => 'dp_ride', 'post_status' => 'draft', 'title' => 'Smoke newer ride', 'fields' => 'ids' ) )[0] ?? 0;
$new_park === (int) get_post_meta( $newer, 'dp_park_id', true ) || $fail( 'Newer ride not linked to the new park' );
3 === count( $mail ) || $fail( 'Expected three emails by now' );
str_contains( $mail[2]['message'], 'post.php?post=' . $new_park . '&action=edit' ) || $fail( 'Email has no park edit link' );
array( 'Reply-To: sam@example.com' ) === $mail[2]['headers'] || $fail( 'Reply-To not set' );

// 4. Errors come back with the values, escaped, and per-field messages.
$snapshot = $all_ids();
$url      = Handler::process( $payload( array( Handler::NONCE_FIELD => 'nope' ) ), $ips[1], time() );
$saved    = Handler::saved_form( (string) $token_of( $url ) );
$saved && str_contains( (string) $saved['form_error'], 'open a while' ) || $fail( 'Bad nonce did not come back with a note' );

$url   = Handler::process( $payload( array( 'title' => '<script>alert(1)</script>', 'moment' => 'snack', 'email' => 'nope' ) ), $ips[1], time() );
$token = $token_of( $url );
$saved = Handler::saved_form( (string) $token );
// The title's tags would be stripped on save, so only moment and email are wrong.
$saved && array( 'moment', 'email' ) === array_keys( $saved['errors'] ) || $fail( 'Expected moment and email errors' );
$_GET['dp_form'] = $token;
$html            = do_blocks( '<!-- wp:drop-and-pair/suggest-form /-->' );
unset( $_GET['dp_form'] );
str_contains( $html, '<script>alert' ) && $fail( 'Raw input echoed into the form' );
str_contains( $html, '&lt;script&gt;alert(1)&lt;/script&gt;' ) || $fail( 'Title value not re-shown' );
str_contains( $html, 'Pick a moment, so I know when it goes.' ) || $fail( 'Moment error not shown' );
str_contains( $html, 'value="nope"' ) || $fail( 'Email value not re-shown' );
$all_ids() === $snapshot || $fail( 'A failed submission created posts' );

// 5. Rate limit: five an hour per address, then a polite no (with the values kept).
$third = $ips[2];
for ( $i = 0; $i < 5; $i++ ) {
	$token_of( Handler::process( $payload( array( 'moment' => 'snack' ) ), $third, time() ) );
}
$url   = Handler::process( $payload(), $third, time() );
$saved = Handler::saved_form( (string) $token_of( $url ) );
$saved && "That's a lot of suggestions in one go. Have a breather and try again in a bit." === $saved['form_error'] || $fail( 'Sixth try was not rate limited' );
'Smoke chips' === $saved['values']['title'] || $fail( 'Rate-limited values not kept' );
$all_ids() === $snapshot || $fail( 'A rate-limited submission created posts' );

// 6. ?ride= pre-selects a published ride only; ?thanks shows the thank-you.
$_GET['ride'] = (string) $ride;
preg_match( '/<option value="' . $ride . '"\s+selected/', do_blocks( '<!-- wp:drop-and-pair/suggest-form /-->' ) ) || $fail( '?ride= did not pre-select' );
$_GET['ride'] = (string) $new_ride;
str_contains( do_blocks( '<!-- wp:drop-and-pair/suggest-form /-->' ), 'value="' . $new_ride . '"' ) && $fail( 'A draft ride is offered in the list' );
unset( $_GET['ride'] );
$_GET['thanks'] = '1';
str_contains( do_blocks( '<!-- wp:drop-and-pair/suggest-form /-->' ), "if it makes it onto the site your name will be next to it." ) || $fail( 'Thank-you not shown' );
unset( $_GET['thanks'] );

// 7. The page is found again rather than duplicated when the option goes missing.
$page_id = (int) get_option( Handler::PAGE_OPTION );
delete_option( Handler::PAGE_OPTION );
Handler::ensure_page();
$page_id === (int) get_option( Handler::PAGE_OPTION ) || $fail( 'ensure_page made a second page' );
1 === count( get_posts( array( 'post_type' => 'page', 'name' => 'suggest', 'post_status' => 'any', 'fields' => 'ids' ) ) ) || $fail( 'More than one suggest page' );

$cleanup();
$all_ids() === $before || WP_CLI::error( 'Smoke left posts behind' );
WP_CLI::success( 'OK' );
