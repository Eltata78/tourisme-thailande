/**
 * Éditeur : enregistre tous les blocs tt/* avec un aperçu serveur et des réglages
 * générés à partir des attributs de block.json. Pas d'étape de build.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var be = wp.blockEditor;
	var c = wp.components;
	var SSR = wp.serverSideRender;

	var LABELS = {
		title: 'Titre', subtitle: 'Accroche', variant: 'Variante', widget: 'Formulaire', widgetTitle: 'Titre du formulaire',
		photos: 'Nombre de photos', imageId: 'Image', imageUrl: 'URL d\'image (si pas dans la médiathèque)', place: 'Lieu',
		kind: 'Type d\'activité', rating: 'Note (ex. 4,8)', reviews: 'Nombre d\'avis', meta: 'Infos (durée, départ…)',
		price: 'Prix « dès » en €', url: 'Lien (fiche GetYourGuide ou lien court)', query: 'Requête GetYourGuide',
		placement: 'Emplacement (pour le suivi cmp)', layout: 'Mise en page', city: 'Ville Trip.com (identifiant)',
		choices: 'Choix « Envie de » (une ligne : Libellé | requête)', label: 'Libellé', sub: 'Sous-titre', button: 'Texte du bouton',
		placeholder: 'Texte indicatif', trim: 'Mots à retirer des libellés (séparés par des virgules)'
	};
	var SKIP = [ 'align', 'lock', 'metadata', 'className', 'style' ];

	function control( key, def, value, set ) {
		var label = LABELS[ key ] || key;
		var onChange = function ( v ) {
			var o = {};
			o[ key ] = v;
			set( o );
		};
		if ( 'imageId' === key ) {
			return el( be.MediaUploadCheck, { key: key },
				el( be.MediaUpload, {
					allowedTypes: [ 'image' ],
					value: value,
					onSelect: function ( m ) { onChange( m.id ); },
					render: function ( p ) {
						return el( 'div', { style: { marginBottom: '16px' } },
							el( c.Button, { variant: 'secondary', onClick: p.open }, value ? 'Changer l\'image' : 'Choisir une image' ),
							value ? el( c.Button, { variant: 'link', isDestructive: true, onClick: function () { onChange( 0 ); } }, 'Retirer' ) : null
						);
					}
				} )
			);
		}
		if ( def.enum ) {
			return el( c.SelectControl, { key: key, label: label, value: value, options: def.enum.map( function ( v ) { return { label: v, value: v }; } ), onChange: onChange } );
		}
		if ( 'boolean' === def.type ) {
			return el( c.ToggleControl, { key: key, label: label, checked: !! value, onChange: onChange } );
		}
		if ( 'number' === def.type ) {
			return el( c.TextControl, { key: key, label: label, type: 'number', value: value || '', onChange: function ( v ) { onChange( v === '' ? 0 : Number( v ) ); } } );
		}
		if ( 'choices' === key || 'subtitle' === key ) {
			return el( c.TextareaControl, { key: key, label: label, value: value, onChange: onChange } );
		}
		return el( c.TextControl, { key: key, label: label, value: value, onChange: onChange } );
	}

	( window.ttBlocks || [] ).forEach( function ( name ) {
		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				var type = wp.blocks.getBlockType( name );
				var postId = wp.data.select( 'core/editor' ) && wp.data.select( 'core/editor' ).getCurrentPostId();
				var fields = Object.keys( type.attributes ).filter( function ( k ) { return SKIP.indexOf( k ) === -1; } );
				return el( 'div', be.useBlockProps(),
					fields.length ? el( be.InspectorControls, null,
						el( c.PanelBody, { title: 'Réglages', initialOpen: true },
							fields.map( function ( k ) { return control( k, type.attributes[ k ], props.attributes[ k ], props.setAttributes ); } )
						)
					) : null,
					el( c.Disabled, null, el( SSR, { block: name, attributes: props.attributes, urlQueryArgs: postId ? { post_id: postId } : {} } ) )
				);
			},
			save: function () { return null; }
		} );
	} );
} )( window.wp );
