<?php
// Shared by the smokes that fetch the front end over HTTP, logged out. Coming soon mode would
// answer those with a 503, so they open the gate for the run and put it back afterwards.
// A killed process can leave the gate open; coming-soon.sh would then fail, which is the point.

/** Runs $fn with coming soon mode off, restoring the previous value even if it throws. */
function dp_smoke_without_gate( callable $fn ) {
	$was = get_option( 'dp_coming_soon', false );
	update_option( 'dp_coming_soon', '0' );
	try {
		return $fn();
	} finally {
		if ( false === $was ) {
			delete_option( 'dp_coming_soon' );
		} else {
			update_option( 'dp_coming_soon', $was );
		}
	}
}
