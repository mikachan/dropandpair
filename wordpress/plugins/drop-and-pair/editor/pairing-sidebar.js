/** Pairing sidebar: description, link and image. Plain browser JS on wp.* globals. */
( function ( wp ) {
	var el = wp.element.createElement;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var __ = wp.i18n.__;
	var TYPE = 'dp_pairing';

	function PairingPanel() {
		var _m = useEntityProp( 'postType', TYPE, 'meta' );
		var meta = _m[ 0 ] || {};
		var setMeta = _m[ 1 ];
		function field( Control, key, label, extra ) {
			return el( Control, Object.assign( {
				label: label,
				value: meta[ key ] || '',
				onChange: function ( v ) {
					var patch = {};
					patch[ key ] = v;
					setMeta( patch );
				},
			}, extra || {} ) );
		}
		var c = wp.components;
		return el(
			wp.editor.PluginDocumentSettingPanel,
			{ name: 'dp-pairing', title: __( 'Pairing details', 'drop-and-pair' ) },
			field( c.TextareaControl, 'dp_description', __( 'Description', 'drop-and-pair' ) ),
			field( c.TextControl, 'dp_url', __( 'Link', 'drop-and-pair' ), { type: 'url' } ),
			field( c.TextControl, 'dp_image', __( 'Image URL', 'drop-and-pair' ), { type: 'url' } )
		);
	}

	wp.plugins.registerPlugin( 'dp-pairing-sidebar', {
		render: function () {
			var type = useSelect( function ( select ) { return select( 'core/editor' ).getCurrentPostType(); }, [] );
			return type === TYPE ? el( PairingPanel ) : null;
		},
	} );
}( window.wp ) );
