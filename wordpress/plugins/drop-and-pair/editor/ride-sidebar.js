/**
 * Ride sidebar: every ride meta field, the park picker and the pairing links.
 * Plain browser JS on wp.* globals; there is no build step.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var c = wp.components;
	var __ = wp.i18n.__;
	var vocab = window.dropAndPair || {};
	var TYPE = 'dp_ride';
	var NONE = '';

	function scale( labels ) {
		var opts = [ { label: __( 'Not set', 'drop-and-pair' ), value: NONE } ];
		Object.keys( labels || {} ).forEach( function ( n ) {
			opts.push( { label: n + ' – ' + labels[ n ], value: String( n ) } );
		} );
		return opts;
	}

	function titleOf( post ) {
		var t = post && post.title && ( post.title.raw || post.title.rendered );
		return t || __( '(no title)', 'drop-and-pair' );
	}

	function statusLabel( status ) {
		return status === 'publish' ? __( 'published', 'drop-and-pair' ) : status;
	}

	// Empty input clears the meta (REST deletes it when sent null).
	function toNumber( value, integer ) {
		if ( value === '' || value === null || value === undefined ) {
			return null;
		}
		var n = integer ? parseInt( value, 10 ) : parseFloat( value );
		return isNaN( n ) ? null : n;
	}

	function ParkPicker( props ) {
		var _s = useState( '' );
		var search = _s[ 0 ];
		var setSearch = _s[ 1 ];
		var parkId = props.value || 0;
		var data = useSelect(
			function ( select ) {
				var core = select( 'core' );
				var q = { per_page: 20, status: 'any', search: search, orderby: 'title', order: 'asc', context: 'edit' };
				return {
					current: parkId ? core.getEntityRecord( 'postType', 'dp_park', parkId ) : null,
					found: core.getEntityRecords( 'postType', 'dp_park', q ),
				};
			},
			[ search, parkId ]
		);
		var options = ( data.found || [] ).map( function ( p ) {
			return { value: p.id, label: titleOf( p ) + ( p.status === 'publish' ? '' : ' (' + statusLabel( p.status ) + ')' ) };
		} );
		if ( data.current && ! options.some( function ( o ) { return o.value === parkId; } ) ) {
			options.unshift( { value: parkId, label: titleOf( data.current ) } );
		}
		return el( c.ComboboxControl, {
			label: __( 'Park', 'drop-and-pair' ),
			value: parkId || null,
			options: options,
			onFilterValueChange: setSearch,
			onChange: function ( id ) {
				props.onChange( id ? id : null );
			},
		} );
	}

	function PairingLink( props ) {
		var link = props.link;
		var strengthMissing = link.strength === null || link.strength === undefined;
		return el(
			'div',
			{ className: 'dp-pairing-link' },
			el(
				'div',
				{ className: 'dp-pairing-link__head' },
				el(
					'span',
					{ className: 'dp-pairing-link__title' },
					props.post ? titleOf( props.post ) : '#' + link.pairing_id,
					props.post && props.post.status !== 'publish'
						? el( 'span', { className: 'dp-pairing-link__status' }, ' (' + statusLabel( props.post.status ) + ')' )
						: null
				),
				el(
					'span',
					null,
					el( c.Button, { icon: 'arrow-up-alt2', label: __( 'Move up', 'drop-and-pair' ), disabled: props.index === 0, onClick: props.onUp, size: 'small' } ),
					el( c.Button, { icon: 'arrow-down-alt2', label: __( 'Move down', 'drop-and-pair' ), disabled: props.isLast, onClick: props.onDown, size: 'small' } ),
					el( c.Button, { icon: 'trash', label: __( 'Remove', 'drop-and-pair' ), isDestructive: true, onClick: props.onRemove, size: 'small' } )
				)
			),
			strengthMissing
				? el( c.Notice, { status: 'warning', isDismissible: false }, __( 'Set a match before this shows', 'drop-and-pair' ) )
				: null,
			el( c.TextareaControl, {
				label: __( 'Blurb', 'drop-and-pair' ),
				value: link.blurb || '',
				onChange: function ( v ) {
					props.onPatch( { blurb: v } );
				},
			} ),
			el( c.SelectControl, {
				label: __( 'Match', 'drop-and-pair' ),
				value: strengthMissing ? NONE : String( link.strength ),
				options: scale( vocab.strength ),
				onChange: function ( v ) {
					props.onPatch( { strength: v === NONE ? null : parseInt( v, 10 ) } );
				},
			} ),
			link.suggested_by ? el( 'p', { className: 'dp-pairing-link__meta' }, __( 'Suggested by ', 'drop-and-pair' ) + link.suggested_by ) : null,
			link.submitter_email
				? el( c.TextControl, { label: __( 'Submitter email', 'drop-and-pair' ), value: link.submitter_email, readOnly: true, onChange: function () {} } )
				: null
		);
	}

	function PairingsEditor( props ) {
		var links = props.links || [];
		var _s = useState( '' );
		var search = _s[ 0 ];
		var setSearch = _s[ 1 ];
		var ids = links.map( function ( l ) { return l.pairing_id; } );
		var data = useSelect(
			function ( select ) {
				var core = select( 'core' );
				return {
					linked: ids.length
						? core.getEntityRecords( 'postType', 'dp_pairing', { include: ids, per_page: 100, status: 'any', context: 'edit' } )
						: [],
					found: core.getEntityRecords( 'postType', 'dp_pairing', { per_page: 20, status: 'any', search: search, orderby: 'title', order: 'asc', context: 'edit' } ),
				};
			},
			[ search, ids.join( ',' ) ]
		);
		var byId = {};
		( data.linked || [] ).forEach( function ( p ) { byId[ p.id ] = p; } );

		var options = ( data.found || [] )
			.filter( function ( p ) { return ids.indexOf( p.id ) === -1; } )
			.map( function ( p ) {
				return { value: p.id, label: titleOf( p ) + ' (' + statusLabel( p.status ) + ')' };
			} );

		function move( from, to ) {
			var next = links.slice();
			var item = next.splice( from, 1 )[ 0 ];
			next.splice( to, 0, item );
			props.onChange( next );
		}

		return el(
			Fragment,
			null,
			links.map( function ( link, i ) {
				return el( PairingLink, {
					key: link.pairing_id,
					link: link,
					post: byId[ link.pairing_id ],
					index: i,
					isLast: i === links.length - 1,
					onUp: function () { move( i, i - 1 ); },
					onDown: function () { move( i, i + 1 ); },
					onRemove: function () {
						props.onChange( links.filter( function ( _l, j ) { return j !== i; } ) );
					},
					onPatch: function ( patch ) {
						props.onChange( links.map( function ( l, j ) { return j === i ? Object.assign( {}, l, patch ) : l; } ) );
					},
				} );
			} ),
			el( c.ComboboxControl, {
				label: __( 'Add a pairing', 'drop-and-pair' ),
				value: null,
				options: options,
				onFilterValueChange: setSearch,
				onChange: function ( id ) {
					if ( ! id ) {
						return;
					}
					props.onChange( links.concat( [ { pairing_id: id, blurb: '', strength: null, suggested_by: null, submitter_email: null } ] ) );
				},
			} )
		);
	}

	function FurtherReading( props ) {
		var items = props.items || [];
		function patch( i, change ) {
			props.onChange( items.map( function ( it, j ) { return j === i ? Object.assign( {}, it, change ) : it; } ) );
		}
		return el(
			Fragment,
			null,
			items.map( function ( it, i ) {
				return el(
					'div',
					{ className: 'dp-pairing-link', key: i },
					el( c.TextControl, { label: __( 'Title', 'drop-and-pair' ), value: it.title || '', onChange: function ( v ) { patch( i, { title: v } ); } } ),
					el( c.TextControl, { label: __( 'URL', 'drop-and-pair' ), type: 'url', value: it.url || '', onChange: function ( v ) { patch( i, { url: v } ); } } ),
					el( c.Button, {
						variant: 'link',
						isDestructive: true,
						onClick: function () { props.onChange( items.filter( function ( _x, j ) { return j !== i; } ) ); },
					}, __( 'Remove', 'drop-and-pair' ) )
				);
			} ),
			el( c.Button, {
				variant: 'secondary',
				onClick: function () { props.onChange( items.concat( [ { title: '', url: '' } ] ) ); },
			}, __( 'Add a link', 'drop-and-pair' ) )
		);
	}

	function RidePanel() {
		var _m = useEntityProp( 'postType', TYPE, 'meta' );
		var meta = _m[ 0 ] || {};
		var setMeta = _m[ 1 ];
		function set( key, value ) {
			var patch = {};
			patch[ key ] = value;
			setMeta( Object.assign( {}, meta, patch ) );
		}
		function text( key, label, extra ) {
			return el( c.TextControl, Object.assign( { label: label, value: meta[ key ] || '', onChange: function ( v ) { set( key, v ); } }, extra || {} ) );
		}
		function number( key, label, integer, extra ) {
			var v = meta[ key ];
			return el( c.TextControl, Object.assign( {
				label: label,
				type: 'number',
				value: v === null || v === undefined ? '' : String( v ),
				onChange: function ( val ) { set( key, toNumber( val, integer ) ); },
			}, extra || {} ) );
		}
		function rating( key, label, labels ) {
			var v = meta[ key ];
			return el( c.SelectControl, {
				label: label,
				value: v ? String( v ) : NONE,
				options: scale( labels ),
				onChange: function ( val ) { set( key, val === NONE ? null : parseInt( val, 10 ) ); },
			} );
		}

		return el(
			wp.editor.PluginDocumentSettingPanel,
			{ name: 'dp-ride', title: __( 'Ride details', 'drop-and-pair' ), className: 'dp-editor-panel' },
			el( ParkPicker, { value: meta.dp_park_id, onChange: function ( id ) { set( 'dp_park_id', id ); } } ),
			text( 'dp_manufacturer', __( 'Manufacturer', 'drop-and-pair' ) ),
			number( 'dp_opened', __( 'Opened (year)', 'drop-and-pair' ), true ),
			text( 'dp_added', __( 'Added (YYYY-MM-DD)', 'drop-and-pair' ), { placeholder: '2026-10-01' } ),
			rating( 'dp_intensity', __( 'Intensity', 'drop-and-pair' ), vocab.intensity ),
			rating( 'dp_roughness', __( 'Comfort (roughness)', 'drop-and-pair' ), vocab.comfort ),
			number( 'dp_height', __( 'Height (m)', 'drop-and-pair' ), false ),
			number( 'dp_speed', __( 'Speed (km/h)', 'drop-and-pair' ), false ),
			number( 'dp_length', __( 'Length (m)', 'drop-and-pair' ), false ),
			number( 'dp_inversions', __( 'Inversions', 'drop-and-pair' ), true, { min: 0 } ),
			el( c.ToggleControl, { label: __( 'Featured', 'drop-and-pair' ), checked: !! meta.dp_featured, onChange: function ( v ) { set( 'dp_featured', v ); } } ),
			el( c.TextareaControl, { label: __( 'Gist', 'drop-and-pair' ), value: meta.dp_gist || '', onChange: function ( v ) { set( 'dp_gist', v ); } } ),
			el( 'h3', null, __( 'Further reading', 'drop-and-pair' ) ),
			el( FurtherReading, { items: meta.dp_further_reading, onChange: function ( v ) { set( 'dp_further_reading', v ); } } ),
			el( 'h3', null, __( 'Pairings', 'drop-and-pair' ) ),
			el( PairingsEditor, { links: meta.dp_pairings, onChange: function ( v ) { set( 'dp_pairings', v ); } } )
		);
	}

	wp.plugins.registerPlugin( 'dp-ride-sidebar', {
		render: function () {
			var type = useSelect( function ( select ) { return select( 'core/editor' ).getCurrentPostType(); }, [] );
			return type === TYPE ? el( RidePanel ) : null;
		},
	} );
}( window.wp ) );
