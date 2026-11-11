/**
 * Shows the park and country fields only when they're wanted: the park for a ride
 * that isn't on the site yet, the country for a park that isn't either. Without
 * this script they all stay visible, and the validator ignores the ones that
 * don't apply, so the form still works with JS off. The server does the real
 * matching; this only has to agree with it closely enough to show the right fields.
 */
( function () {
	/** Accents, case and extra spaces don't count, as on the server. */
	function normalise( text ) {
		return text
			.normalize( 'NFD' )
			.replace( /[\u0300-\u036f]/g, '' )
			.toLowerCase()
			.replace( /\s+/g, ' ' )
			.trim();
	}

	/** The datalist's values, plus any bare ride titles it marks as safe to match. */
	function known( input ) {
		var names = {};
		if ( input.list ) {
			input.list.querySelectorAll( 'option' ).forEach( function ( option ) {
				names[ normalise( option.value ) ] = true;
				if ( option.dataset.title ) {
					names[ normalise( option.dataset.title ) ] = true;
				}
			} );
		}
		return names;
	}

	/** Typed, and not one that's already on the site. */
	function isNew( input, names ) {
		var typed = normalise( input.value );
		return typed !== '' && ! names[ typed ];
	}

	function setUp( form ) {
		var ride = form.querySelector( '[name="ride"]' );
		var park = form.querySelector( '[name="park"]' );
		var newRide = form.querySelector( '[data-dp-new-ride]' );
		var newPark = form.querySelector( '[data-dp-new-park]' );
		if ( ! ride || ! park || ! newRide || ! newPark ) {
			return;
		}
		var rides = known( ride );
		var parks = known( park );
		function sync() {
			newRide.hidden = ! isNew( ride, rides );
			newPark.hidden = newRide.hidden || ! isNew( park, parks );
		}
		form.classList.add( 'dp-suggest--js' );
		[ ride, park ].forEach( function ( input ) {
			input.addEventListener( 'input', sync );
			input.addEventListener( 'change', sync );
		} );
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
