<?php
// Run with: studio wp eval-file <this file>. Prints OK on success.
// Every post it makes (its own fixtures and the drafts the handler creates) is deleted at the end.
use DropAndPair\Submission\Handler;

$types    = array( 'dp_ride', 'dp_park', 'dp_pairing' );
$all_ids  = static fn() => get_posts( array( 'post_type' => $types, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
$before   = $all_ids();
$tokens   = array();
$ips      = array( '192.0.2.10', '192.0.2.11', '192.0.2.12', '192.0.2.13' );
$page_opt = get_option( Handler::PAGE_OPTION );
$checked  = get_option( Handler::PAGE_CHECKED );
$mail     = array();
$stored   = static function () {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '\\_transient\\_dp\\_form\\_%'" );
};

$cleanup = static function () use ( $all_ids, $before, &$tokens, $ips, $page_opt, $checked ) {
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
	false === $checked ? delete_option( Handler::PAGE_CHECKED ) : update_option( Handler::PAGE_CHECKED, $checked );
	unset( $_GET['ride'], $_GET['dp_form'], $_GET['thanks'], $_GET['dp_error'] );
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
		'ride'               => 'Smoke ride (Smoke park)',
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

// 1. Existing ride, by its label. Park and country are ignored for it, as they're on screen with JS off.
$url = Handler::process( $payload( array( 'park' => 'Stray park', 'new_park_country' => 'Strayland' ) ), $ips[0], time() );
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

// 3. New ride at the existing park (typed without its accent), then new ride at a new park.
Handler::process( $payload( array( 'ride' => 'Smoke new ride', 'park' => '  smoke PÄRK ' ) ), $ips[0], time() );
1 === $count( 'dp_ride' ) && 0 === $count( 'dp_park' ) || $fail( 'New ride: expected one draft ride and no park' );
$new_ride = get_posts( array( 'post_type' => 'dp_ride', 'post_status' => 'draft', 'title' => 'Smoke new ride', 'fields' => 'ids' ) )[0] ?? 0;
$park === (int) get_post_meta( $new_ride, 'dp_park_id', true ) || $fail( 'New ride not linked to the park' );
1 === count( get_post_meta( $new_ride, 'dp_pairings', true ) ) || $fail( 'New ride has no pairing link' );

Handler::process( $payload( array( 'ride' => 'Smoke newer ride', 'park' => 'Smoke new park', 'new_park_country' => 'Smokeland' ) ), $ips[0], time() );
$new_park = get_posts( array( 'post_type' => 'dp_park', 'post_status' => 'draft', 'title' => 'Smoke new park', 'fields' => 'ids' ) )[0] ?? 0;
$new_park || $fail( 'New park not created as a draft' );
'Smokeland' === get_post_meta( $new_park, 'dp_country', true ) || $fail( 'New park country not saved' );
$newer = get_posts( array( 'post_type' => 'dp_ride', 'post_status' => 'draft', 'title' => 'Smoke newer ride', 'fields' => 'ids' ) )[0] ?? 0;
$new_park === (int) get_post_meta( $newer, 'dp_park_id', true ) || $fail( 'Newer ride not linked to the new park' );
3 === count( $mail ) || $fail( 'Expected three emails by now' );
str_contains( $mail[2]['message'], 'post.php?post=' . $new_park . '&action=edit' ) || $fail( 'Email has no park edit link' );
array( 'Reply-To: sam@example.com' ) === $mail[2]['headers'] || $fail( 'Reply-To not set' );

// 3b. A visitor's titles are stored as typed, not as kses would have them ("Fish &amp; Chips"),
// and still print escaped. A stray "<" is the one thing encoded, so it can't open markup.
// WP-CLI turns kses off; a logged-out web request has it on, so put it back for this part.
0 === get_current_user_id() && ! current_user_can( 'unfiltered_html' ) || $fail( 'Titles must be checked as a logged-out visitor' );
$had_kses = has_filter( 'title_save_pre', 'wp_filter_kses' );
kses_init_filters();
$titled = static function ( string $type, string $title ) {
	return get_posts( array( 'post_type' => $type, 'post_status' => 'draft', 'title' => $title, 'fields' => 'ids' ) )[0] ?? 0;
};
Handler::process( $payload( array( 'ride' => "Rock 'n' Roll Coaster", 'park' => 'Fish & Chips Land', 'new_park_country' => 'Smokeland', 'title' => 'Fish & Chips' ) ), $ips[3], time() );
Handler::process( $payload( array( 'title' => "Rock 'n' Roll" ) ), $ips[3], time() );
Handler::process( $payload( array( 'title' => 'Kids <3 it <img src=x onerror=alert(1)//' ) ), $ips[3], time() );
$fish = $titled( 'dp_pairing', 'Fish & Chips' );
$fish || $fail( '"Fish & Chips" not stored as typed' );
$titled( 'dp_park', 'Fish & Chips Land' ) || $fail( 'Park title not stored as typed' );
$rock_ride = $titled( 'dp_ride', "Rock 'n' Roll Coaster" );
$rock_ride || $fail( 'Ride title not stored as typed' );
$rock = $titled( 'dp_pairing', "Rock 'n' Roll" );
$rock || $fail( "\"Rock 'n' Roll\" not stored as typed" );
$kids = $titled( 'dp_pairing', 'Kids &lt;3 it &lt;img src=x onerror=alert(1)//' );
$kids || $fail( 'A stray "<" in a title was not encoded' );
has_filter( 'title_save_pre', 'wp_filter_kses' ) || $fail( 'kses title filter was not put back' );
false === $had_kses && kses_remove_filters();
// Printed as the blocks print them: the & and < come out as entities, never raw markup.
'Fish &amp; Chips' === esc_html( get_post_field( 'post_title', $fish, 'raw' ) ) || $fail( 'Title not escaped once on output' );
'Kids &lt;3 it &lt;img src=x onerror=alert(1)//' === esc_html( get_post_field( 'post_title', $kids, 'raw' ) ) || $fail( 'Encoded title double-escaped or unescaped' );
str_contains( get_the_title( $kids ), '<img' ) && $fail( 'get_the_title gives raw markup' );
str_contains( end( $mail )['subject'], 'Kids <3 it' ) || $fail( 'Email subject should carry the title as typed' );

// 4. Errors come back with the values, escaped, and per-field messages.
$snapshot = $all_ids();
$url      = Handler::process( $payload( array( Handler::NONCE_FIELD => 'nope' ) ), $ips[1], time() );
$saved    = Handler::saved_form( (string) $token_of( $url ) );
$saved && str_contains( (string) $saved['form_error'], 'open a while' ) || $fail( 'Bad nonce did not come back with a note' );

$url   = Handler::process( $payload( array( 'title' => '<script>alert(1)</script>', 'moment' => 'snack', 'email' => 'nope' ) ), $ips[1], time() );
$token = $token_of( $url );
$saved = Handler::saved_form( (string) $token );
// Stripped, the title is empty, so it's wrong as well as the moment and email.
$saved && array( 'title', 'moment', 'email' ) === array_keys( $saved['errors'] ) || $fail( 'Expected title, moment and email errors' );
! isset( $saved['values']['email'] ) || $fail( 'The email was stored with the form' );
$_GET['dp_form'] = $token;
$html            = do_blocks( '<!-- wp:drop-and-pair/suggest-form /-->' );
unset( $_GET['dp_form'] );
str_contains( $html, '<script>alert' ) && $fail( 'Raw input echoed into the form' );
str_contains( $html, '&lt;script&gt;alert(1)&lt;/script&gt;' ) || $fail( 'Title value not re-shown' );
str_contains( $html, 'Pick a pairing type, so I know where it goes.' ) || $fail( 'Moment error not shown' );
str_contains( $html, 'value="nope"' ) && $fail( 'Email re-shown; it should never be stored' );
str_contains( $html, "pop it in again if you" ) || $fail( 'No note asking for the email again' );
str_contains( $html, 'name="dp_form" value="' . $token . '"' ) || $fail( 'Re-shown form does not carry its token' );
$all_ids() === $snapshot || $fail( 'A failed submission created posts' );

// A new ride with no park asks for one, and keeps what was typed for the ride.
$saved = Handler::saved_form( (string) $token_of( Handler::process( $payload( array( 'ride' => 'Smoke unknown ride' ) ), $ips[1], time() ) ) );
$saved && array( 'park' ) === array_keys( $saved['errors'] ) || $fail( 'A new ride with no park should only ask for the park' );
'Smoke unknown ride' === $saved['values']['ride'] || $fail( 'The typed ride was not kept' );
$all_ids() === $snapshot || $fail( 'A failed submission created posts' );

// The token is single use: sending the re-shown form deletes the saved values.
$token_of( Handler::process( $payload( array( 'dp_form' => $token, 'moment' => 'snack' ) ), $ips[1], time() ) );
null === Handler::saved_form( (string) $token ) || $fail( 'Saved values survived the next submit' );

// 5. Rate limit: five an hour per address, then a polite no. Over the limit nothing is
// stored at all, not even for a bad nonce, so repeating it can't fill wp_options.
$third = $ips[2];
for ( $i = 0; $i < 5; $i++ ) {
	$token_of( Handler::process( $payload( array( 'moment' => 'snack' ) ), $third, time() ) );
}
$count_before = $stored();
foreach ( array( $payload(), $payload( array( Handler::NONCE_FIELD => 'nope' ) ) ) as $over ) {
	$url = Handler::process( $over, $third, time() );
	add_query_arg( 'dp_error', 'busy', Handler::page_url() ) === $url || $fail( "Over the limit, expected ?dp_error=busy, got $url" );
	$token_of( $url );
}
$count_before === $stored() || $fail( 'A rate-limited post stored a transient' );
$all_ids() === $snapshot || $fail( 'A rate-limited submission created posts' );
$_GET['dp_error'] = 'busy';
str_contains( do_blocks( '<!-- wp:drop-and-pair/suggest-form /-->' ), 'Have a breather and try again in a bit.' ) || $fail( 'Busy message not shown' );
unset( $_GET['dp_error'] );

// 6. ?ride= fills in a published ride only; the suggestions offer published rides and
// parks only; ?thanks shows the thank-you.
$_GET['ride'] = (string) $ride;
$html         = do_blocks( '<!-- wp:drop-and-pair/suggest-form /-->' );
str_contains( $html, 'name="ride" value="Smoke ride (Smoke park)"' ) || $fail( '?ride= did not fill in the ride' );
str_contains( $html, '<option value="Smoke ride (Smoke park)" data-title="Smoke ride">' ) || $fail( 'Ride not suggested with its label and title' );
str_contains( $html, '<option value="Smoke park">' ) || $fail( 'Park not suggested' );
str_contains( $html, 'list="dp-suggest-rides"' ) && str_contains( $html, 'list="dp-suggest-parks"' ) || $fail( 'Inputs not tied to their suggestions' );
$_GET['ride'] = (string) $new_ride;
$html         = do_blocks( '<!-- wp:drop-and-pair/suggest-form /-->' );
str_contains( $html, 'Smoke new ride' ) && $fail( 'A draft ride is offered or filled in' );
str_contains( $html, 'Smoke new park' ) && $fail( 'A draft park is offered' );
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
'1' === (string) get_option( Handler::PAGE_CHECKED ) || $fail( 'ensure_page did not set its flag' );

// Once the flag is set, the per-request check does nothing; activation clears it.
delete_option( Handler::PAGE_OPTION );
Handler::maybe_ensure_page();
false === get_option( Handler::PAGE_OPTION ) || $fail( 'maybe_ensure_page ran despite the flag' );
Handler::activate();
$page_id === (int) get_option( Handler::PAGE_OPTION ) || $fail( 'activate() did not find the page again' );

// 8. A page deleted for good comes back on the next admin load or a 404 at /suggest/,
// not only on reactivation. A missing ID is found again by slug; with no page at the
// slug either, a fresh one is made. The real page is put back as it was afterwards.
$gone = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Smoke gone' ) );
wp_delete_post( $gone, true );
update_option( Handler::PAGE_OPTION, $gone );
update_option( Handler::PAGE_CHECKED, 1 );
Handler::recheck_page();
$page_id === (int) get_option( Handler::PAGE_OPTION ) || $fail( 'recheck_page did not find the page again' );
$writes = 0;
$count_writes = static function ( $option ) use ( &$writes ) {
	in_array( $option, array( Handler::PAGE_OPTION, Handler::PAGE_CHECKED ), true ) && $writes++;
};
add_action( 'update_option', $count_writes );
add_action( 'delete_option', $count_writes );
Handler::recheck_page();
remove_action( 'update_option', $count_writes );
remove_action( 'delete_option', $count_writes );
0 === $writes || $fail( 'recheck_page wrote options while the page was there' );

global $wpdb, $wp, $wp_query;
$real_name = get_post_field( 'post_name', $page_id );
$wpdb->update( $wpdb->posts, array( 'post_name' => 'smoke-held-suggest' ), array( 'ID' => $page_id ) );
clean_post_cache( $page_id );
update_option( Handler::PAGE_OPTION, $gone );
$went      = null;
$catch     = static function ( $location ) use ( &$went ) {
	$went = $location;
	return false;
};
$fresh     = 0;
$was_req   = $wp->request;
try {
	add_filter( 'wp_redirect', $catch );
	$wp->request = 'suggest';
	$wp_query->set_404();
	Handler::restore_page_on_404();
	$fresh = (int) get_option( Handler::PAGE_OPTION );
	$fresh && $fresh !== $page_id || throw new RuntimeException( 'The 404 path did not make a new page' );
	'publish' === get_post_status( $fresh ) && 'suggest' === get_post_field( 'post_name', $fresh ) || throw new RuntimeException( 'New page is not published at /suggest/' );
	str_contains( get_post_field( 'post_content', $fresh ), 'drop-and-pair/suggest-form' ) || throw new RuntimeException( 'New page has no form' );
	get_permalink( $fresh ) === $went || throw new RuntimeException( "The 404 path redirected to $went" );
	// Not a 404, or some other URL: nothing happens.
	$went = null;
	$wp->request = 'elsewhere';
	Handler::restore_page_on_404();
	null === $went || throw new RuntimeException( 'Redirected from an unrelated 404' );
} catch ( RuntimeException $e ) {
	$error = $e->getMessage();
} finally {
	remove_filter( 'wp_redirect', $catch );
	$wp->request = $was_req;
	$wp_query->init();
	$fresh && $fresh !== $page_id && wp_delete_post( $fresh, true );
	$wpdb->update( $wpdb->posts, array( 'post_name' => $real_name ), array( 'ID' => $page_id ) );
	clean_post_cache( $page_id );
}
isset( $error ) && $fail( $error );

$cleanup();
$all_ids() === $before || WP_CLI::error( 'Smoke left posts behind' );
WP_CLI::success( 'OK' );
