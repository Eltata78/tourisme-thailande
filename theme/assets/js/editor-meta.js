/**
 * Éditeur : panneau « Tourisme Thaïlande » dans les réglages du document, pour les métadonnées tt_*
 * (titre H1, accroche, type d'activité du catalogue, requête des offres, destination hôtels…).
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var c = wp.components;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	var cfg = window.ttMeta || {};
	if ( ! Panel || ! cfg.fields ) {
		return;
	}
	var options = function ( map, empty ) {
		return [ { label: empty, value: '' } ].concat( Object.keys( map ).map( function ( k ) { return { label: map[ k ], value: k }; } ) );
	};

	wp.plugins.registerPlugin( 'tt-meta', {
		render: function () {
			var meta = wp.data.useSelect( function ( s ) { return s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}; }, [] );
			var editPost = wp.data.useDispatch( 'core/editor' ).editPost;
			var keys = Object.keys( cfg.fields ).filter( function ( k ) { return k in meta; } );
			if ( ! keys.length ) {
				return null;
			}
			return el( Panel, { name: 'tt-meta', title: 'Tourisme Thaïlande' }, keys.map( function ( k ) {
				var f = cfg.fields[ k ];
				var set = function ( v ) { var o = {}; o[ k ] = v; editPost( { meta: o } ); };
				var common = { key: k, label: f.label, help: f.description, value: null == meta[ k ] ? '' : meta[ k ], onChange: set, __nextHasNoMarginBottom: true };
				if ( 'tt_kind' === k ) {
					return el( c.SelectControl, Object.assign( common, { options: options( cfg.kinds, 'Pas dans le catalogue' ) } ) );
				}
				if ( 'tt_trip_city' === k ) {
					return el( c.SelectControl, Object.assign( common, { options: options( cfg.trip, 'Aucune' ) } ) );
				}
				if ( 'tt_intro' === k ) {
					return el( c.TextareaControl, common );
				}
				if ( 'number' === f.type ) {
					return el( c.TextControl, Object.assign( common, { type: 'number', onChange: function ( v ) { set( '' === v ? 0 : Number( v ) ); } } ) );
				}
				return el( c.TextControl, common );
			} ) );
		}
	} );
} )( window.wp );
