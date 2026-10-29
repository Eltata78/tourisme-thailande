/**
 * Front : menus, recherche instantanée, formulaires partenaires, barre mobile, sommaire actif.
 * Tout le contenu est déjà dans le HTML : ce script n'ajoute que du confort.
 */
( function () {
	var cfg = window.ttFront || {};
	var $ = function ( s, r ) { return ( r || document ).querySelector( s ); };
	var $$ = function ( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); };

	/* ---------- Panneaux (recherche, tiroir mobile) ---------- */
	$$( '[data-tt-toggle]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var panel = document.getElementById( btn.getAttribute( 'data-tt-toggle' ) );
			if ( ! panel ) return;
			var open = panel.hidden;
			panel.hidden = ! open;
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) {
				var input = $( 'input[type=search]', panel );
				if ( input ) input.focus();
			}
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) return;
		$$( '.tt-search-panel, .tt-drawer' ).forEach( function ( p ) { p.hidden = true; } );
		$$( '[data-tt-toggle]' ).forEach( function ( b ) { b.setAttribute( 'aria-expanded', 'false' ); } );
	} );


	/* ---------- Cookies : bandeau de consentement ; les scripts tiers ne se chargent qu'après accord ---------- */
	var consentBox = $( '#tt-consent' );
	// Choix enregistré (« a1.p0.horodatage ») : { a: audience, p: partenaires }, ou null.
	function readConsent() {
		var m = document.cookie.match( /(?:^|; )tt_consent=([^;]+)/ ), c = {};
		if ( ! m ) return null;
		decodeURIComponent( m[ 1 ] ).split( '.' ).forEach( function ( part ) { if ( /^[ap][01]$/.test( part ) ) c[ part.charAt( 0 ) ] = '1' === part.charAt( 1 ); } );
		return c;
	}
	// Scripts neutralisés (type text/plain) des catégories acceptées : remplacés par de vrais scripts.
	function runConsented( c ) {
		$$( 'script[type="text/plain"][data-tt-consent]' ).forEach( function ( s ) {
			if ( ! c[ s.getAttribute( 'data-tt-consent' ) ] ) return;
			var n = document.createElement( 'script' );
			Array.prototype.forEach.call( s.attributes, function ( at ) {
				if ( [ 'type', 'data-src', 'data-tt-consent' ].indexOf( at.name ) === -1 ) n.setAttribute( at.name, at.value );
			} );
			if ( s.getAttribute( 'data-src' ) ) { n.src = s.getAttribute( 'data-src' ); n.async = true; } else n.text = s.text;
			s.parentNode.replaceChild( n, s );
		} );
	}
	function saveConsent( c ) {
		var prev = readConsent();
		document.cookie = 'tt_consent=' + encodeURIComponent( 'a' + ( c.a ? 1 : 0 ) + '.p' + ( c.p ? 1 : 0 ) + '.' + Math.round( Date.now() / 1000 ) ) +
			'; Max-Age=15552000; Path=/; SameSite=Lax' + ( 'https:' === location.protocol ? '; Secure' : '' );
		consentBox.hidden = true;
		// Accord retiré : cookies d'audience effacés et page rechargée, pour que les scripts déjà chargés s'arrêtent.
		if ( prev && ( ( prev.a && ! c.a ) || ( prev.p && ! c.p ) ) ) {
			document.cookie.split( '; ' ).forEach( function ( kv ) {
				var name = kv.split( '=' )[ 0 ];
				if ( ! /^_ga/.test( name ) ) return;
				// Google les dépose sur le domaine le plus large possible : on essaie chaque niveau (preprod.site.fr, site.fr).
				var parts = location.hostname.split( '.' ), doms = [ '' ];
				for ( var i = 0; i < parts.length - 1; i++ ) doms.push( '; Domain=.' + parts.slice( i ).join( '.' ) );
				doms.forEach( function ( d ) { document.cookie = name + '=; Max-Age=0; Path=/' + d; } );
			} );
			location.reload();
			return;
		}
		runConsented( c );
	}
	if ( consentBox ) {
		var choices = $( '.tt-consent__choices', consentBox ), chooseBtn = $( '[data-consent="choose"]', consentBox ), stored = readConsent();
		var openChoices = function () {
			var c = readConsent() || {};
			$$( 'input', choices ).forEach( function ( i ) { i.checked = !! c[ i.name ]; } );
			choices.hidden = false;
			chooseBtn.textContent = 'Enregistrer mes choix';
		};
		if ( stored ) runConsented( stored );
		else consentBox.hidden = false;
		consentBox.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-consent]' ), k = b && b.getAttribute( 'data-consent' );
			if ( 'all' === k ) saveConsent( { a: true, p: true } );
			else if ( 'none' === k ) saveConsent( { a: false, p: false } );
			else if ( 'choose' === k ) {
				if ( choices.hidden ) openChoices();
				else saveConsent( { a: $( '[name=a]', choices ).checked, p: $( '[name=p]', choices ).checked } );
			}
		} );
		// Lien « Cookies » (footer, mentions légales) : rouvre le bandeau sur les choix en cours.
		document.addEventListener( 'click', function ( e ) {
			var a = e.target.closest( 'a[href$="#reglages-cookies"]' );
			if ( ! a ) return;
			e.preventDefault();
			openChoices();
			consentBox.hidden = false;
		} );
	}


	/* ---------- Mesure : clics sur les CTA poussés dans le dataLayer, lus par GTM (plan : docs/plan-de-taggage.md) ---------- */
	// Rien ne part chez Google sans accord : GTM ne se charge qu'après, et reprend alors les événements en attente.
	var dataLayer = window.dataLayer = window.dataLayer || [];
	var pageSlug = ( dataLayer.filter( function ( e ) { return e && e.page_slug; } )[ 0 ] || {} ).page_slug || '';
	// Partenaire d'un lien : GetYourGuide (liens courts gyg.me compris), Trip.com, sinon rien.
	function partnerOf( u ) {
		if ( /(^|\.)getyourguide\.[a-z.]+$|(^|\.)gyg\.me$/.test( u.hostname ) ) return 'getyourguide';
		if ( /(^|\.)trip\.com$/.test( u.hostname ) ) return 'trip.com';
		return '';
	}
	// Composant qui porte le CTA : bloc du thème (gyg-card, cta-card, hero, mobile-bar…), sinon texte de l'article.
	function componentOf( el ) {
		if ( el.closest( '.tt-search__sugg' ) ) return 'recherche-suggestion';
		var b = el.closest( '[class*="wp-block-tt-"]' ), m = b && b.className.match( /wp-block-tt-([a-z0-9-]+)/ );
		if ( m ) return m[ 1 ];
		return el.closest( '.wp-block-post-content' ) ? 'texte' : 'autre';
	}
	// Emplacement : paramètre de campagne (cmp chez GetYourGuide, trip_sub1 chez Trip.com) sans le préfixe « tt-{page}- ».
	function placementOf( v ) {
		v = ( v || '' ).replace( /^tt-/, '' );
		return pageSlug && 0 === v.indexOf( pageSlug + '-' ) ? v.slice( pageSlug.length + 1 ) : v;
	}
	// Hauteur du CTA dans la page, par quart : 0-25 (haut de page) à 75-100 (bas).
	function positionOf( el ) {
		var q = Math.floor( ( el.getBoundingClientRect().top + window.scrollY ) / ( document.documentElement.scrollHeight || 1 ) * 4 );
		return [ '0-25', '25-50', '50-75', '75-100' ][ Math.max( 0, Math.min( 3, q ) ) ];
	}
	function labelOf( el ) {
		return ( el.getAttribute( 'aria-label' ) || el.textContent || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 100 );
	}
	// CTA interne : liens des blocs du site (cartes, catalogue, carte, suggestions), pas le menu, le pied de page ni le texte.
	function isInternalCta( a, comp ) {
		if ( 'recherche-suggestion' === comp ) return true;
		if ( a.closest( '.wp-block-tt-site-header' ) ) return a.matches( '.tt-btn, .tt-header__map' );
		return ! a.closest( 'footer' ) && [ 'texte', 'autre', 'toc', 'brand' ].indexOf( comp ) === -1;
	}
	function trackLink( e ) {
		var a = e.target.closest && e.target.closest( 'a[href]' ), u;
		if ( ! a || ( 'auxclick' === e.type && 1 !== e.button ) ) return;
		try { u = new URL( a.href, location.href ); } catch ( err ) { return; }
		var partner = partnerOf( u ), comp = componentOf( a );
		if ( partner ) {
			dataLayer.push( {
				event: 'affiliate_click', cta_partner: partner, cta_component: comp,
				cta_placement: placementOf( u.searchParams.get( 'cmp' ) || u.searchParams.get( 'trip_sub1' ) ) || ( 'texte' === comp ? 'lien-texte' : '' ),
				cta_text: labelOf( a ), cta_url: u.hostname + u.pathname, cta_position: positionOf( a ),
			} );
		} else if ( u.hostname === location.hostname && ! ( u.pathname === location.pathname && u.hash ) && isInternalCta( a, comp ) ) {
			dataLayer.push( { event: 'cta_click', cta_component: comp, cta_text: labelOf( a ), cta_url: u.pathname, cta_position: positionOf( a ) } );
		}
	}
	document.addEventListener( 'click', trackLink, true );
	document.addEventListener( 'auxclick', trackLink, true );
	// Formulaires partenaires (hôtels Trip.com, activités) : envoi = clic d'affiliation.
	function trackForm( form ) {
		var trip = 'trip' === form.getAttribute( 'data-tt-wdg' ), field = form.elements[ trip ? 'trip_sub1' : 'cmp' ];
		dataLayer.push( {
			event: 'affiliate_click', cta_partner: trip ? 'trip.com' : 'getyourguide', cta_component: componentOf( form ),
			cta_placement: placementOf( field && field.value ), cta_text: 'formulaire',
			cta_url: form.action.replace( /^https?:\/\/|\?.*$/g, '' ), cta_position: positionOf( form ),
		} );
	}

	/* ---------- Méga-menu : ouverture au clic (tactile, clavier) et fermeture en cliquant ailleurs ---------- */
	$$( '.tt-nav__item.has-mega > .tt-nav__top' ).forEach( function ( link ) {
		var item = link.parentNode;
		link.addEventListener( 'click', function ( e ) {
			// Sur écran tactile, le premier toucher ouvre le panneau au lieu de suivre le lien.
			if ( window.matchMedia( '(hover: none)' ).matches && ! item.classList.contains( 'is-open' ) ) {
				e.preventDefault();
				$$( '.tt-nav__item.is-open' ).forEach( function ( o ) { o.classList.remove( 'is-open' ); } );
				item.classList.add( 'is-open' );
			}
		} );
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest( '.tt-nav__item' ) ) $$( '.tt-nav__item.is-open' ).forEach( function ( o ) { o.classList.remove( 'is-open' ); } );
	} );
	// Souris : un panneau ouvert ne bascule vers une autre rubrique qu'après un court arrêt dessus (120 ms),
	// pour qu'un trajet en diagonale vers le panneau ne déclenche pas la rubrique voisine ; fermeture 150 ms après la sortie.
	var nav = $( '.tt-nav' );
	if ( nav && window.matchMedia( '(hover: hover)' ).matches ) {
		var cur = null, tSwitch = 0, tClose = 0;
		var show = function ( it ) {
			clearTimeout( tSwitch );
			clearTimeout( tClose );
			if ( cur && cur !== it ) cur.classList.remove( 'is-open' );
			cur = it;
			if ( it ) it.classList.add( 'is-open' );
		};
		nav.classList.add( 'is-js' );
		$$( '.tt-nav__item.has-mega', nav ).forEach( function ( it ) {
			it.addEventListener( 'mouseenter', function () {
				clearTimeout( tClose );
				clearTimeout( tSwitch );
				if ( ! cur || cur === it ) show( it );
				else tSwitch = setTimeout( function () { show( it ); }, 120 );
			} );
			it.addEventListener( 'mouseleave', function () {
				clearTimeout( tSwitch );
				tClose = setTimeout( function () { show( null ); }, 150 );
			} );
		} );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key ) show( null ); } );
	}

	/* ---------- Recherche instantanée ---------- */
	var index = null, loading = null;
	var norm = function ( s ) { return ( s || '' ).normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase(); };
	var esc = function ( s ) { return String( s ).replace( /[&<>"]/g, function ( ch ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ ch ]; } ); };
	function loadIndex() {
		if ( index || loading || ! cfg.searchIndex ) return loading;
		loading = fetch( cfg.searchIndex ).then( function ( r ) { return r.json(); } ).then( function ( rows ) {
			index = rows.map( function ( r ) { return { t: r[ 0 ], u: r[ 1 ], k: r[ 2 ], n: norm( r[ 0 ] + ' ' + r[ 3 ] ), tn: norm( r[ 0 ] ) }; } );
			return index;
		} ).catch( function () { index = []; } );
		return loading;
	}
	function find( q ) {
		var nq = norm( q.trim() );
		if ( nq.length < 2 || ! index ) return [];
		var words = nq.split( /\s+/ );
		return index.map( function ( r ) {
			if ( ! words.every( function ( w ) { return r.n.indexOf( w ) > -1; } ) ) return null;
			var s = 10;
			if ( r.tn.indexOf( nq ) === 0 ) s += 30; else if ( ( ' ' + r.tn ).indexOf( ' ' + nq ) > -1 ) s += 15;
			if ( 'Destination' === r.k ) s += 20; else if ( 'Guide' !== r.k ) s += 8;
			return [ s, r ];
		} ).filter( Boolean ).sort( function ( a, b ) { return b[ 0 ] - a[ 0 ]; } ).map( function ( x ) { return x[ 1 ]; } );
	}
	$$( '[data-tt-search]' ).forEach( function ( form ) {
		var input = $( 'input[type=search]', form ), box = $( '.tt-search__sugg', form ), active = -1;
		function render() {
			var q = input.value, res = find( q ), html = '';
			if ( ! q.trim() ) { box.hidden = true; return; }
			var places = res.filter( function ( r ) { return 'Guide' !== r.k; } ).slice( 0, 4 );
			var guides = res.filter( function ( r ) { return 'Guide' === r.k; } ).slice( 0, 4 );
			var row = function ( r ) { return '<a role="option" href="' + esc( r.u ) + '"><span>' + esc( r.t ) + '</span><small>' + esc( r.k ) + '</small></a>'; };
			if ( places.length ) html += '<p class="h">Destinations et visites</p>' + places.map( row ).join( '' );
			if ( guides.length ) html += '<p class="h">Guides</p>' + guides.map( row ).join( '' );
			if ( cfg.gyg ) {
				html += '<a class="gyg" role="option" target="_blank" rel="sponsored nofollow noopener" href="' + esc( cfg.gyg.domain + '/s/?q=' + encodeURIComponent( q ) + '&partner_id=' + cfg.gyg.partner + '&cmp=tt-search' ) + '"><span>Activités « ' + esc( q ) + ' » à réserver</span></a>';
			}
			box.innerHTML = html;
			box.hidden = false;
			active = places.length || guides.length ? 0 : -1;
			highlight();
		}
		function highlight() { $$( 'a', box ).forEach( function ( a, i ) { a.classList.toggle( 'is-on', i === active ); } ); }
		input.addEventListener( 'focus', function () { loadIndex(); } );
		input.addEventListener( 'input', function () { ( loadIndex() || Promise.resolve() ).then( render ); render(); } );
		input.addEventListener( 'keydown', function ( e ) {
			var links = $$( 'a', box );
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
				e.preventDefault();
				active = Math.max( 0, Math.min( links.length - 1, active + ( 'ArrowDown' === e.key ? 1 : -1 ) ) );
				highlight();
			} else if ( 'Enter' === e.key && active > -1 && links[ active ] && ! box.hidden ) {
				var first = links[ active ];
				if ( ! first.classList.contains( 'gyg' ) ) { e.preventDefault(); window.location.href = first.href; }
			}
		} );
		document.addEventListener( 'click', function ( e ) { if ( ! form.contains( e.target ) ) box.hidden = true; } );
	} );

	/* ---------- Formulaires partenaires ---------- */
	var iso = function ( d ) { return d.toISOString().slice( 0, 10 ); };
	$$( '[data-tt-date]' ).forEach( function ( input ) {
		var d = new Date();
		d.setDate( d.getDate() + parseInt( input.getAttribute( 'data-tt-date' ), 10 ) );
		if ( ! input.value ) input.value = iso( d );
		input.min = iso( new Date() );
	} );
	$$( '[data-tt-step]' ).forEach( function ( input ) {
		var wrap = document.createElement( 'span' );
		wrap.className = 'tt-step';
		input.parentNode.insertBefore( wrap, input );
		[ -1, 1 ].forEach( function ( d, i ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.textContent = d < 0 ? '−' : '+';
			b.setAttribute( 'aria-label', d < 0 ? 'Moins' : 'Plus' );
			b.addEventListener( 'click', function () {
				var v = Math.max( +input.min, Math.min( +input.max, ( +input.value || 0 ) + d ) );
				input.value = v;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
			if ( 0 === i ) wrap.appendChild( b );
			if ( 0 === i ) wrap.appendChild( input );
			if ( 1 === i ) wrap.appendChild( b );
		} );
	} );
	$$( '[data-tt-children]' ).forEach( function ( input ) {
		var form = input.form, box = $( '[data-tt-ages]', form );
		input.addEventListener( 'change', function () {
			var n = +input.value || 0, cur = $$( 'select', box ).length;
			while ( cur < n ) {
				var l = document.createElement( 'label' );
				l.className = 'tt-f';
				l.innerHTML = 'Âge enfant ' + ( cur + 1 ) + '<select data-tt-age>' + Array.from( { length: 18 }, function ( _, a ) { return '<option' + ( 6 === a ? ' selected' : '' ) + '>' + a + '</option>'; } ).join( '' ) + '</select>';
				box.appendChild( l );
				cur++;
			}
			while ( cur > n ) { box.removeChild( box.lastChild ); cur--; }
		} );
	} );
	$$( '[data-tt-wdg]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function () {
			var same = $( '[data-tt-same]', form );
			if ( same ) form.elements[ same.getAttribute( 'data-tt-same' ) ].value = same.value;
			var ages = $$( '[data-tt-age]', form ).map( function ( s ) { return s.value; } );
			var hidden = form.elements.ages;
			if ( ages.length ) {
				if ( ! hidden ) { hidden = document.createElement( 'input' ); hidden.type = 'hidden'; hidden.name = 'ages'; form.appendChild( hidden ); }
				hidden.value = ages.join( ',' );
			} else if ( hidden ) {
				hidden.remove();
			}
			// Destinations Trip qui ne sont pas des villes (province, plage) : paramètres de recherche complémentaires.
			$$( '[data-tt-extra]', form ).forEach( function ( el ) { el.remove(); } );
			var opt = form.elements.city && form.elements.city.selectedOptions[ 0 ];
			if ( opt && opt.dataset.ttQ ) {
				var q = JSON.parse( opt.dataset.ttQ );
				Object.keys( q ).forEach( function ( k ) {
					var h = document.createElement( 'input' );
					h.type = 'hidden'; h.name = k; h.value = q[ k ]; h.setAttribute( 'data-tt-extra', '' );
					form.appendChild( h );
				} );
			}
			trackForm( form );
		} );
	} );


	/* ---------- Bandeau des destinations : défilement en boucle ---------- */
	$$( '.tt-quick p' ).forEach( function ( p ) {
		var label = $( 'strong', p ), links = $$( 'a', p );
		if ( ! links.length ) return;
		var set = document.createElement( 'span' );
		set.className = 'tt-marquee__set';
		links.forEach( function ( a ) { set.appendChild( a ); } );
		var copy = set.cloneNode( true );
		copy.setAttribute( 'aria-hidden', 'true' );
		$$( 'a', copy ).forEach( function ( a ) { a.tabIndex = -1; } );
		var track = document.createElement( 'span' ), box = document.createElement( 'span' );
		track.className = 'tt-marquee__track';
		box.className = 'tt-marquee';
		track.appendChild( set );
		track.appendChild( copy );
		box.appendChild( track );
		p.textContent = '';
		if ( label ) { label.className = 'tt-marquee__label'; p.appendChild( label ); }
		p.appendChild( box );
		p.closest( '.tt-quick' ).classList.add( 'is-marquee' );
	} );


	/* ---------- Carte des visites : MapLibre chargé à l'approche du bloc ---------- */
	var mapLib;
	function loadMapLib() {
		if ( window.maplibregl ) return Promise.resolve();
		if ( ! mapLib ) {
			mapLib = new Promise( function ( ok, ko ) {
				var css = document.createElement( 'link' ), js = document.createElement( 'script' );
				css.rel = 'stylesheet';
				css.href = cfg.maplib + 'maplibre-gl.css';
				js.src = cfg.maplib + 'maplibre-gl.js';
				js.onload = ok;
				js.onerror = ko;
				document.head.appendChild( css );
				document.head.appendChild( js );
			} );
		}
		return mapLib;
	}
	function cardHtml( p ) {
		// Toute la carte (photo, nom, bouton) mène aux offres ; « Le guide » reste un lien interne à part.
		var go = ' href="' + esc( p.g ) + '" target="_blank" rel="sponsored nofollow noopener"';
		return '<div class="tt-mapcard__in">' + ( p.i ? '<a class="tt-mapcard__img"' + go + ' tabindex="-1"><img src="' + esc( p.i ) + '" alt=""></a>' : '' ) +
			'<div class="tt-mapcard__b"><a class="tt-mapcard__t"' + go + '>' + esc( p.t ) + '</a><div class="tt-mapcard__a">' +
			'<a class="tt-btn tt-btn--gold"' + go + '>' + esc( p.b ) + '</a>' +
			( p.u ? '<a class="tt-mapcard__guide" href="' + esc( p.u ) + '">Le guide</a>' : '' ) + '</div></div>' +
			'<button type="button" class="tt-mapcard__x" aria-label="Fermer">×</button></div>';
	}
	// Toucher court sur un repère : géré ici, car la carte annule son propre clic au moindre glissement du doigt.
	function onTap( el, fn ) {
		var start = null;
		el.addEventListener( 'pointerdown', function ( e ) { start = { x: e.clientX, y: e.clientY, t: Date.now() }; } );
		el.addEventListener( 'pointercancel', function () { start = null; } );
		el.addEventListener( 'pointerup', function ( e ) {
			if ( start && Math.abs( e.clientX - start.x ) < 14 && Math.abs( e.clientY - start.y ) < 14 && Date.now() - start.t < 900 ) fn();
			start = null;
		} );
		el.addEventListener( 'click', function ( e ) { if ( 0 === e.detail ) fn(); } ); // clavier
	}
	function km( a, b ) {
		var r = Math.PI / 180, dLat = ( b[ 1 ] - a[ 1 ] ) * r, dLng = ( b[ 0 ] - a[ 0 ] ) * r;
		var h = Math.pow( Math.sin( dLat / 2 ), 2 ) + Math.cos( a[ 1 ] * r ) * Math.cos( b[ 1 ] * r ) * Math.pow( Math.sin( dLng / 2 ), 2 );
		return 12742 * Math.asin( Math.sqrt( h ) );
	}
	function styleMap( map ) {
		// Noms en français quand OpenStreetMap les connaît, sinon en anglais ou translittérés : jamais d'écriture thaïe seule.
		var name = [ 'coalesce', [ 'get', 'name:fr' ], [ 'get', 'name:en' ], [ 'get', 'name:latin' ], [ 'get', 'name' ] ];
		map.getStyle().layers.forEach( function ( l ) {
			var tf = l.layout && l.layout[ 'text-field' ];
			if ( 'symbol' === l.type && tf && JSON.stringify( tf ).indexOf( 'name' ) > -1 ) map.setLayoutProperty( l.id, 'text-field', name );
		} );
		[ [ 'background', 'background-color', '#F6F5F0' ], [ 'water', 'fill-color', '#C7DCE7' ], [ 'park', 'fill-color', '#DCE8D3' ], [ 'landcover_wood', 'fill-color', '#D6E3CE' ] ].forEach( function ( c ) {
			if ( map.getLayer( c[ 0 ] ) ) map.setPaintProperty( c[ 0 ], c[ 1 ], c[ 2 ] );
		} );
	}
	// Repères trop proches à ce niveau de zoom : une seule bulle « n lieux » qui zoome sur eux au clic.
	function group( map, pins, groups ) {
		var ml = window.maplibregl, gap = 46, todo = [];
		groups.splice( 0 ).forEach( function ( g ) { g.remove(); } );
		pins.forEach( function ( x ) { x.el.classList.remove( 'is-grouped' ); x.xy = map.project( [ x.p.lng, x.p.lat ] ); if ( ! x.p.c ) todo.push( x ); } );
		var sets = [];
		while ( todo.length ) {
			var first = todo.shift(), near = [ first ];
			todo = todo.filter( function ( x ) {
				var close = Math.abs( x.xy.x - first.xy.x ) < gap && Math.abs( x.xy.y - first.xy.y ) < gap;
				if ( close ) near.push( x );
				return ! close;
			} );
			if ( near.length > 1 ) sets.push( near );
		}
		sets.forEach( function ( near ) {
			var bounds = new ml.LngLatBounds(), el = document.createElement( 'button' ), lead = near[ 0 ].p;
			near.forEach( function ( x ) { x.el.classList.add( 'is-grouped' ); bounds.extend( [ x.p.lng, x.p.lat ] ); } );
			el.type = 'button';
			el.className = 'tt-pin tt-pin--group' + ( lead.m ? ' has-img' : '' );
			el.innerHTML = ( lead.m ? '<img class="tt-pin__img" src="' + esc( lead.m ) + '" alt="">' : '<span class="tt-pin__dot"></span>' ) +
				'<span class="tt-pin__count">' + near.length + '</span><span class="tt-pin__label">' + esc( near.map( function ( x ) { return x.p.t; } ).slice( 0, 3 ).join( ', ' ) + ( near.length > 3 ? '…' : '' ) ) + '</span>';
			// Différé : la carte interrompt toute animation à la fin du toucher, qui arrive juste après.
			onTap( el, function () { setTimeout( function () { map.fitBounds( bounds, { padding: 70, maxZoom: map.getZoom() + 3 } ); }, 60 ); } );
			var m = new ml.Marker( { element: el, anchor: 'center' } ).setLngLat( bounds.getCenter() ).addTo( map );
			el.setAttribute( 'aria-label', near.length + ' lieux : zoomer' );
			groups.push( m );
		} );
	}
	function drawMap( box ) {
		var data = JSON.parse( box.getAttribute( 'data-tt-map' ) ), ml = window.maplibregl;
		var pts = data.pins.map( function ( p ) { return [ p.lng, p.lat ]; } );
		var cur = data.pins.filter( function ( p ) { return p.c; } )[ 0 ];
		var map = new ml.Map( {
			container: box, style: data.style, center: pts[ 0 ], zoom: 10, attributionControl: false,
			cooperativeGestures: true, dragRotate: false, pitchWithRotate: false, touchPitch: false,
			locale: {
				'CooperativeGesturesHandler.WindowsHelpText': 'Ctrl + molette pour zoomer sur la carte',
				'CooperativeGesturesHandler.MacHelpText': '⌘ + molette pour zoomer sur la carte',
				'CooperativeGesturesHandler.MobileHelpText': 'Deux doigts pour déplacer la carte',
				'NavigationControl.ZoomIn': 'Zoomer', 'NavigationControl.ZoomOut': 'Dézoomer'
			}
		} );
		map.touchZoomRotate.disableRotation();
		map.addControl( new ml.NavigationControl( { showCompass: false } ), 'top-left' );
		map.addControl( new ml.AttributionControl( { compact: true } ), 'top-right' );
		map.on( 'style.load', function () { styleMap( map ); } );
		map.once( 'load', function () {
			var attrib = box.querySelector( '.maplibregl-compact-show' );
			if ( attrib ) attrib.classList.remove( 'maplibregl-compact-show' );
		} );
		var pins = [], groups = [], card = document.createElement( 'div' ), active = null;
		card.className = 'tt-mapcard';
		card.hidden = true;
		box.appendChild( card );
		function close() {
			card.hidden = true;
			if ( active ) active.classList.remove( 'is-active' );
			active = null;
		}
		function select( p, el ) {
			if ( active ) active.classList.remove( 'is-active' );
			active = el;
			el.classList.add( 'is-active' );
			card.innerHTML = cardHtml( p );
			card.classList.toggle( 'is-plain', ! p.i );
			card.hidden = false;
			card.querySelector( '.tt-mapcard__x' ).addEventListener( 'click', close );
			// Le repère choisi ne doit pas rester caché sous la carte.
			var xy = map.project( [ p.lng, p.lat ] ), limit = box.clientHeight - card.offsetHeight - 50;
			if ( xy.y > limit ) map.panBy( [ 0, xy.y - limit ], { duration: 300 } );
			if ( window.gtag ) window.gtag( 'event', 'tt_map_pin', { pin: p.t } );
		}
		map.on( 'click', function ( e ) {
			var t = e.originalEvent && e.originalEvent.target;
			if ( ! ( t && t.closest && t.closest( '.tt-pin, .tt-mapcard' ) ) ) close();
		} );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key ) close(); } );
		data.pins.forEach( function ( p ) {
			var el = document.createElement( 'button' );
			el.type = 'button';
			el.className = 'tt-pin' + ( p.c ? ' is-cur' : '' );
			el.setAttribute( 'aria-label', p.t );
			el.innerHTML = ( p.m ? '<img class="tt-pin__img" src="' + esc( p.m ) + '" alt="">' : '<span class="tt-pin__dot"></span>' ) + '<span class="tt-pin__label">' + esc( p.t ) + '</span>';
			if ( p.m ) el.classList.add( 'has-img' );
			onTap( el, function () { select( p, el ); } );
			new ml.Marker( { element: el, anchor: 'center' } ).setLngLat( [ p.lng, p.lat ] ).addTo( map );
			el.setAttribute( 'aria-label', p.t ); // MapLibre pose « Map marker » par défaut
			pins.push( { p: p, el: el } );
		} );
		map.on( 'zoomend', function () { group( map, pins, groups ); } );
		map.once( 'idle', function () { group( map, pins, groups ); } );
		if ( cur && pts.length > 1 && ! data.fit ) {
			map.jumpTo( { center: [ cur.lng, cur.lat ], zoom: data.zoom || 11.5 } );
		} else if ( 1 === pts.length ) {
			map.jumpTo( { center: pts[ 0 ], zoom: data.zoom || 9.5 } );
		} else {
			// Les lieux proches du centre de la destination cadrent la carte ; les excursions lointaines restent visibles en dézoomant.
			var near = data.center ? pts.filter( function ( pt ) { return km( pt, [ data.center[ 1 ], data.center[ 0 ] ] ) < 60; } ) : pts;
			var bounds = new ml.LngLatBounds();
			( near.length > 1 ? near : pts ).forEach( function ( pt ) { bounds.extend( pt ); } );
			map.fitBounds( bounds, { padding: 48, maxZoom: 13, duration: 0 } );
		}
		box.classList.add( 'is-ready' );
	}
	$$( '[data-tt-map]' ).forEach( function ( box ) {
		var go = function () { loadMapLib().then( function () { drawMap( box ); } ); };
		if ( ! ( 'IntersectionObserver' in window ) ) return go();
		var io = new IntersectionObserver( function ( es ) {
			if ( es.some( function ( e ) { return e.isIntersecting; } ) ) { io.disconnect(); go(); }
		}, { rootMargin: '400px 0px' } );
		io.observe( box );
	} );

	/* ---------- Mode carte : toutes les activités sur une grande carte, filtres par type, liste de la zone affichée ---------- */
	function drawExplorer( sec ) {
		var data = JSON.parse( sec.getAttribute( 'data-tt-explorer' ) ), ml = window.maplibregl;
		var box = $( '.tt-explorer__map', sec ), side = $( '.tt-explorer__side', sec ), count = $( '[data-ex-count]', sec );
		var chips = $$( '[data-ex-kind]', sec ), dest = $( '[data-ex-dest]', sec ), listLink = $( '[data-ex-listlink]', sec ), toggle = $( '[data-ex-toggle]', sec );
		var rows = {}, others = $$( '.tt-explorer__other li', sec ), thailand = new ml.LngLatBounds();
		data.pins.forEach( function ( p ) { thailand.extend( [ p.lng, p.lat ] ); } ); // vue d'ensemble : cadrée sur les activités
		$$( '.tt-explorer__list li[data-ex-id]', sec ).forEach( function ( li ) { rows[ li.getAttribute( 'data-ex-id' ) ] = li; } );
		var params = new URLSearchParams( location.search ), kind = params.get( 'k' ) || '';
		if ( ! chips.some( function ( c ) { return c.getAttribute( 'data-ex-kind' ) === kind; } ) ) kind = '';
		var map = new ml.Map( {
			container: box, style: data.style, bounds: thailand, fitBoundsOptions: { padding: 40 }, attributionControl: false,
			dragRotate: false, pitchWithRotate: false, touchPitch: false,
			locale: { 'NavigationControl.ZoomIn': 'Zoomer', 'NavigationControl.ZoomOut': 'Dézoomer' }
		} );
		map.touchZoomRotate.disableRotation();
		map.addControl( new ml.NavigationControl( { showCompass: false } ), 'top-right' );
		map.addControl( new ml.AttributionControl( { compact: true } ), 'bottom-right' );
		map.on( 'style.load', function () { styleMap( map ); } );
		var pins = [], byId = {}, groups = [], card = document.createElement( 'div' ), active = null, inView = 0, openRow = null;
		card.className = 'tt-mapcard';
		card.hidden = true;
		box.appendChild( card );
		var shown = function () { return pins.filter( function ( x ) { return ! kind || x.p.k === kind; } ); };
		function mark( id, on ) { if ( rows[ id ] ) rows[ id ].classList.toggle( 'is-active', on ); }
		function close() {
			card.hidden = true;
			var was = active;
			active = null;
			if ( was ) {
				was.el.classList.remove( 'is-active' );
				mark( was.p.id, false );
				was.p.c = false;
				group( map, shown(), groups );
			}
		}
		// Repère choisi : toujours affiché seul (jamais fondu dans une bulle « n lieux »).
		function spot( x ) {
			active = x;
			x.p.c = true;
			x.el.classList.add( 'is-active' );
			mark( x.p.id, true );
			group( map, shown(), groups );
		}
		// Ligne de la liste dépliée : photo, description, article et réservation. Une seule à la fois.
		function expand( li, on ) {
			$( '.tt-explorer__head', li ).setAttribute( 'aria-expanded', String( on ) );
			$( '.tt-explorer__more', li ).hidden = ! on;
			li.classList.toggle( 'is-open', on );
			openRow = on ? li : ( openRow === li ? null : openRow );
		}
		function select( x ) {
			if ( openRow && openRow !== rows[ x.p.id ] ) expand( openRow, false );
			close();
			spot( x );
			card.innerHTML = cardHtml( x.p );
			card.classList.toggle( 'is-plain', ! x.p.i );
			card.hidden = false;
			card.querySelector( '.tt-mapcard__x' ).addEventListener( 'click', close );
			var xy = map.project( [ x.p.lng, x.p.lat ] ), limit = box.clientHeight - card.offsetHeight - 50;
			if ( xy.y > limit ) map.panBy( [ 0, xy.y - limit ], { duration: 300 } );
			if ( rows[ x.p.id ] && side.offsetParent ) rows[ x.p.id ].scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
			if ( window.gtag ) window.gtag( 'event', 'tt_map_pin', { pin: x.p.t, source: 'mode-carte' } );
		}
		// Liste : les activités du filtre qui sont dans la zone affichée.
		function sync() {
			var b = map.getBounds(), n = 0;
			pins.forEach( function ( x ) {
				var on = ( ! kind || x.p.k === kind ) && b.contains( [ x.p.lng, x.p.lat ] );
				if ( rows[ x.p.id ] ) rows[ x.p.id ].hidden = ! on;
				n += on ? 1 : 0;
			} );
			others.forEach( function ( li ) { li.hidden = !! kind && li.getAttribute( 'data-ex-k' ) !== kind; } );
			count.textContent = n ? n + ( n > 1 ? ' activités' : ' activité' ) + ' dans cette zone' : 'Aucune activité dans cette zone : dézoome ou change de filtre.';
			inView = n;
			label();
		}
		function label() { toggle.textContent = sec.classList.contains( 'is-listing' ) ? 'Voir la carte' : 'Liste (' + inView + ')'; }
		function refresh() {
			pins.forEach( function ( x ) { x.el.classList.toggle( 'is-off', !! kind && x.p.k !== kind ); } );
			if ( active && active.el.classList.contains( 'is-off' ) ) close();
			group( map, shown(), groups );
			sync();
		}
		function setKind( k ) {
			kind = k;
			chips.forEach( function ( c ) { c.setAttribute( 'aria-pressed', String( c.getAttribute( 'data-ex-kind' ) === kind ) ); } );
			var q = new URLSearchParams( location.search );
			if ( kind ) q.set( 'k', kind ); else q.delete( 'k' );
			history.replaceState( null, '', location.pathname + ( q.toString() ? '?' + q : '' ) );
			if ( listLink ) listLink.href = listLink.href.split( '?' )[ 0 ] + ( kind ? '?k=' + kind : '' );
			refresh();
		}
		function goTo( d ) {
			var b = new ml.LngLatBounds();
			pins.forEach( function ( x ) { if ( x.p.d === d ) b.extend( [ x.p.lng, x.p.lat ] ); } );
			if ( d && ! b.isEmpty() ) map.fitBounds( b, { padding: 70, maxZoom: 12.5 } );
			else map.fitBounds( thailand, { padding: 40 } );
		}
		data.pins.forEach( function ( p ) {
			var el = document.createElement( 'button' );
			el.type = 'button';
			el.className = 'tt-pin' + ( p.m ? ' has-img' : '' );
			el.innerHTML = ( p.m ? '<img class="tt-pin__img" src="' + esc( p.m ) + '" alt="" loading="lazy">' : '<span class="tt-pin__dot"></span>' ) + '<span class="tt-pin__label">' + esc( p.t ) + '</span>';
			var x = { p: p, el: el };
			onTap( el, function () { select( x ); } );
			new ml.Marker( { element: el, anchor: 'center' } ).setLngLat( [ p.lng, p.lat ] ).addTo( map );
			el.setAttribute( 'aria-label', p.t );
			pins.push( x );
			byId[ p.id ] = x;
			var li = rows[ p.id ];
			if ( li ) {
				li.addEventListener( 'mouseenter', function () { el.classList.add( 'is-hover' ); } );
				li.addEventListener( 'mouseleave', function () { el.classList.remove( 'is-hover' ); } );
			}
		} );
		$$( '.tt-explorer__head', sec ).forEach( function ( head ) {
			var li = head.parentNode;
			head.addEventListener( 'click', function () {
				var on = openRow !== li, x = byId[ li.getAttribute( 'data-ex-id' ) ];
				if ( openRow && openRow !== li ) expand( openRow, false );
				expand( li, on );
				if ( ! on ) { if ( x ) close(); return; }
				setTimeout( function () { li.scrollIntoView( { block: 'nearest', behavior: 'smooth' } ); }, 30 );
				// Activité de la carte : son repère est marqué ; la carte ne bouge que s'il est hors de la zone affichée,
				// sans changer le zoom, pour que la liste en cours de lecture ne se réduise pas d'un coup.
				if ( x ) {
					close();
					spot( x );
					if ( ! map.getBounds().contains( [ x.p.lng, x.p.lat ] ) ) map.easeTo( { center: [ x.p.lng, x.p.lat ], duration: 600 } );
				}
				if ( window.gtag ) window.gtag( 'event', 'tt_map_list_open', { pin: $( 'b', head ).textContent } );
			} );
		} );
		map.on( 'click', function ( e ) {
			var t = e.originalEvent && e.originalEvent.target;
			if ( ! ( t && t.closest && t.closest( '.tt-pin, .tt-mapcard' ) ) ) close();
		} );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key ) close(); } );
		map.on( 'zoomend', function () { group( map, shown(), groups ); } );
		map.on( 'moveend', sync );
		map.once( 'idle', function () {
			setKind( kind );
			if ( params.get( 'd' ) ) { dest.value = params.get( 'd' ); goTo( dest.value ); }
		} );
		chips.forEach( function ( c ) { c.addEventListener( 'click', function () { setKind( c.getAttribute( 'data-ex-kind' ) ); } ); } );
		dest.addEventListener( 'change', function () { close(); goTo( dest.value ); } );
		toggle.addEventListener( 'click', function () {
			var open = ! sec.classList.contains( 'is-listing' );
			sec.classList.toggle( 'is-listing', open );
			toggle.setAttribute( 'aria-expanded', String( open ) );
			label();
		} );
		sec.classList.add( 'is-ready' );
		box.classList.add( 'is-ready' );
	}
	$$( '[data-tt-explorer]' ).forEach( function ( sec ) { loadMapLib().then( function () { drawExplorer( sec ); } ); } );

	/* ---------- Barre mobile : visible une fois le hero passé ---------- */
	var bar = $( '[data-tt-mbar]' ), hero = $( '.tt-hero' ) || $( 'h1' );
	if ( bar && hero && 'IntersectionObserver' in window ) {
		new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) { bar.classList.toggle( 'is-visible', ! e.isIntersecting && e.boundingClientRect.top < 0 ); } );
		} ).observe( hero );
	}

	/* ---------- Sommaire : section en cours, et barre fixe à faire glisser sur mobile ---------- */
	$$( '[data-tt-toc]' ).forEach( function ( toc ) {
		var links = $$( 'a', toc ), prose = toc.closest( '.tt-layout' ) || document.body;
		if ( links.length < 2 ) return;
		// Mêmes liens en pastilles, dans une barre qui apparaît en haut de l'écran pendant la lecture (mobile).
		var bar = document.createElement( 'nav' );
		bar.className = 'tt-tocbar';
		bar.setAttribute( 'aria-label', toc.getAttribute( 'aria-label' ) || 'Sommaire' );
		bar.innerHTML = '<div class="tt-tocbar__track">' + links.map( function ( a ) {
			return '<a href="' + esc( a.getAttribute( 'href' ) ) + '">' + esc( a.textContent ) + '</a>';
		} ).join( '' ) + '</div><span class="tt-tocbar__progress"></span>';
		document.body.appendChild( bar );
		var chips = $$( 'a', bar ), track = $( '.tt-tocbar__track', bar ), progress = $( '.tt-tocbar__progress', bar );
		function setActive( id ) {
			links.forEach( function ( a ) { a.classList.toggle( 'is-on', a.getAttribute( 'href' ) === '#' + id ); } );
			chips.forEach( function ( c ) {
				var on = c.getAttribute( 'href' ) === '#' + id;
				c.classList.toggle( 'is-on', on );
				if ( on ) track.scrollTo( { left: c.offsetLeft - ( track.clientWidth - c.offsetWidth ) / 2, behavior: 'smooth' } );
			} );
		}
		chips.forEach( function ( c ) {
			c.addEventListener( 'click', function ( e ) {
				var t = document.getElementById( c.getAttribute( 'href' ).slice( 1 ) );
				if ( ! t ) return;
				e.preventDefault();
				window.scrollTo( { top: t.getBoundingClientRect().top + window.scrollY - bar.offsetHeight - 12, behavior: 'smooth' } );
				history.replaceState( null, '', c.getAttribute( 'href' ) );
				setActive( t.id );
			} );
		} );
		var heads = links.map( function ( a ) { return document.getElementById( a.getAttribute( 'href' ).slice( 1 ) ); } ), current = null, ticking = false;
		function onScroll() {
			ticking = false;
			var r = prose.getBoundingClientRect(), h = window.innerHeight;
			bar.classList.toggle( 'is-visible', r.top < 0 && r.bottom > h * 0.35 );
			progress.style.transform = 'scaleX(' + Math.min( 1, Math.max( 0, -r.top / Math.max( 1, r.height - h ) ) ) + ')';
			// Section en cours : le dernier titre passé sous le haut de l'écran (barre comprise).
			var id = null;
			heads.forEach( function ( t ) { if ( t && t.getBoundingClientRect().top < 120 ) id = t.id; } );
			id = id || ( heads[ 0 ] && heads[ 0 ].id );
			if ( id !== current ) { current = id; setActive( id ); }
		}
		window.addEventListener( 'scroll', function () { if ( ! ticking ) { ticking = true; requestAnimationFrame( onScroll ); } }, { passive: true } );
		onScroll();
	} );
	// Catalogue d'activités : recherche, filtres (destination ET type, plusieurs choix par filtre) et tri, sans rechargement.
	// L'état est repris dans l'adresse (?q=…&d=bangkok,phuket&k=temples) : un lien peut ouvrir le catalogue déjà filtré.
	$$( '[data-tt-cat]' ).forEach( function ( cat ) {
		var grid = $( '.tt-catalog__grid', cat ), q = $( '[data-cat-q]', cat ), sort = $( '[data-cat-sort]', cat ), side = $( '.tt-catalog__side', cat );
		var count = $( '[data-cat-count]', cat ), chips = $( '[data-cat-chips]', cat ), empty = $( '.tt-catalog__empty', cat ), offers = $( '[data-cat-offers]', cat );
		var openBtn = $( '[data-cat-open]', cat ), apply = $( '[data-cat-apply]', cat ), boxes = $$( 'input[type=checkbox]', side ), tiles = $$( '[data-cat-tile]', cat ), mapLink = $( '[data-cat-maplink]', cat );
		var items = $$( 'li', grid ).map( function ( li, i ) {
			return { li: li, d: li.dataset.d, k: li.dataset.k, ki: +li.dataset.ki, s: norm( li.textContent + ' ' + li.dataset.s ), t: norm( $( 'h3', li ).textContent ), o: i };
		} );
		var plural = function ( n ) { return n + ( n > 1 ? ' activités' : ' activité' ); };
		function selected( name ) { return boxes.filter( function ( b ) { return b.name === name && b.checked; } ).map( function ( b ) { return b.value; } ); }
		function update() {
			var d = selected( 'd' ), k = selected( 'k' ), words = norm( q.value ).split( /\s+/ ).filter( Boolean ), n = 0;
			items.forEach( function ( it ) {
				var ok = ( ! d.length || d.indexOf( it.d ) > -1 ) && ( ! k.length || k.indexOf( it.k ) > -1 ) && words.every( function ( w ) { return it.s.indexOf( w ) > -1; } );
				it.li.hidden = ! ok;
				n += ok ? 1 : 0;
			} );
			count.textContent = plural( n );
			apply.textContent = n ? 'Voir les ' + plural( n ) : 'Aucune activité';
			empty.hidden = n > 0;
			if ( ! n ) offers.href = offers.dataset.href.replace( '__Q__', encodeURIComponent( q.value.trim() || 'Thaïlande' ) );
			chips.innerHTML = boxes.filter( function ( b ) { return b.checked; } ).map( function ( b ) {
				return '<button type="button" data-name="' + b.name + '" data-value="' + esc( b.value ) + '">' + esc( b.dataset.label ) + ' <span aria-hidden="true">×</span><span class="screen-reader-text">(retirer le filtre)</span></button>';
			} ).join( '' );
			tiles.forEach( function ( t ) { t.setAttribute( 'aria-pressed', String( k.length === 1 && k[ 0 ] === t.dataset.catTile ) ); } );
			openBtn.textContent = 'Filtrer' + ( d.length + k.length ? ' (' + ( d.length + k.length ) + ')' : '' );
			if ( mapLink ) {
				var mq = new URLSearchParams();
				if ( 1 === k.length ) mq.set( 'k', k[ 0 ] );
				if ( 1 === d.length ) mq.set( 'd', d[ 0 ] );
				mapLink.href = mapLink.href.split( '?' )[ 0 ] + ( mq.toString() ? '?' + mq : '' );
			}
			var params = new URLSearchParams( location.search );
			[ [ 'q', q.value.trim() ], [ 'd', d.join( ',' ) ], [ 'k', k.join( ',' ) ] ].forEach( function ( p ) { if ( p[ 1 ] ) params.set( p[ 0 ], p[ 1 ] ); else params.delete( p[ 0 ] ); } );
			history.replaceState( null, '', location.pathname + ( params.toString() ? '?' + params : '' ) + location.hash );
		}
		function reorder() {
			var by = sort.value;
			items.slice().sort( function ( a, b ) {
				if ( 'az' === by ) return a.t.localeCompare( b.t, 'fr' );
				if ( 'kind' === by ) return a.ki - b.ki || a.o - b.o;
				return a.o - b.o;
			} ).forEach( function ( it ) { grid.appendChild( it.li ); } );
		}
		function toggleSheet( open ) {
			cat.classList.toggle( 'is-filtering', open );
			document.documentElement.classList.toggle( 'tt-lock', open );
			openBtn.setAttribute( 'aria-expanded', String( open ) );
			if ( open ) $( 'input', side ).focus( { preventScroll: true } );
		}
		// État initial depuis l'adresse.
		var init = new URLSearchParams( location.search );
		q.value = init.get( 'q' ) || '';
		[ 'd', 'k' ].forEach( function ( name ) {
			( init.get( name ) || '' ).split( ',' ).forEach( function ( v ) { boxes.forEach( function ( b ) { if ( b.name === name && b.value === v ) b.checked = true; } ); } );
		} );
		boxes.forEach( function ( b ) { b.addEventListener( 'change', update ); } );
		q.addEventListener( 'input', update );
		sort.addEventListener( 'change', reorder );
		chips.addEventListener( 'click', function ( e ) {
			var c = e.target.closest( 'button' );
			if ( ! c ) return;
			boxes.forEach( function ( b ) { if ( b.name === c.dataset.name && b.value === c.dataset.value ) b.checked = false; } );
			update();
		} );
		$( '[data-cat-clear]', cat ).addEventListener( 'click', function () { boxes.forEach( function ( b ) { b.checked = false; } ); q.value = ''; update(); } );
		// Raccourci par type : n'affiche que ce type (second clic : tous les types).
		tiles.forEach( function ( t ) {
			t.addEventListener( 'click', function () {
				var only = 'true' !== t.getAttribute( 'aria-pressed' );
				boxes.forEach( function ( b ) { if ( 'k' === b.name ) b.checked = only && b.value === t.dataset.catTile; } );
				update();
				var top = $( '.tt-catalog__body', cat ).getBoundingClientRect().top;
				if ( top < 0 || top > window.innerHeight * 0.6 ) window.scrollTo( { top: top + window.scrollY - 16, behavior: 'smooth' } );
			} );
		} );
		openBtn.addEventListener( 'click', function () { toggleSheet( true ); } );
		$$( '[data-cat-close]', cat ).forEach( function ( b ) { b.addEventListener( 'click', function () { toggleSheet( false ); } ); } );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && cat.classList.contains( 'is-filtering' ) ) toggleSheet( false ); } );
		update();
	} );
} )();
