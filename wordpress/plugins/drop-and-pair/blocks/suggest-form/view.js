/**
 * Shows the "new ride" and "new park" fields only when they're wanted.
 * Without this script they all stay visible, and the validator ignores the
 * ones that don't apply, so the form still works with JS off.
 */
( function () {
	function setUp( form ) {
		var ride = form.querySelector( '[name="ride_id"]' );
		var park = form.querySelector( '[name="park_id"]' );
		var newRide = form.querySelector( '[data-dp-new-ride]' );
		var newPark = form.querySelector( '[data-dp-new-park]' );
		if ( ! ride || ! park || ! newRide || ! newPark ) {
			return;
		}
		function sync() {
			newRide.hidden = ride.value !== '0';
			newPark.hidden = newRide.hidden || park.value !== '0';
		}
		form.classList.add( 'dp-suggest--js' );
		ride.addEventListener( 'change', sync );
		park.addEventListener( 'change', sync );
		sync();
	}

	function init() {
		document.querySelectorAll( '.dp-suggest__form' ).forEach( setUp );
		// After a failed send, move focus to the note saying what needs fixing.
		var notice = document.querySelector( '.dp-suggest__notice' );
		if ( notice ) {
			notice.focus();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
