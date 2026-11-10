/** Park sidebar: country, region and website. Plain browser JS on wp.* globals. */
( function ( wp ) {
	var el = wp.element.createElement;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var __ = wp.i18n.__;
	var TYPE = 'dp_park';

	function ParkPanel() {
		var _m = useEntityProp( 'postType', TYPE, 'meta' );
		var meta = _m[ 0 ] || {};
		var setMeta = _m[ 1 ];
		function field( key, label, extra ) {
			return el( wp.components.TextControl, Object.assign( {
				label: label,
				value: meta[ key ] || '',
				onChange: function ( v ) {
					var patch = {};
					patch[ key ] = v;
					setMeta( patch );
				},
			}, extra || {} ) );
		}
		return el(
			wp.editor.PluginDocumentSettingPanel,
			{ name: 'dp-park', title: __( 'Park details', 'drop-and-pair' ) },
			field( 'dp_country', __( 'Country', 'drop-and-pair' ) ),
			field( 'dp_region', __( 'Region', 'drop-and-pair' ) ),
			field( 'dp_website', __( 'Website', 'drop-and-pair' ), { type: 'url' } )
		);
	}

	wp.plugins.registerPlugin( 'dp-park-sidebar', {
		render: function () {
			var type = useSelect( function ( select ) { return select( 'core/editor' ).getCurrentPostType(); }, [] );
			return type === TYPE ? el( ParkPanel ) : null;
		},
	} );
}( window.wp ) );
