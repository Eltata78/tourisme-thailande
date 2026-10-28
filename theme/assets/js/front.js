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
				html += '<a class="gyg" role="option" target="_blank" rel="sponsored nofollow noopener" href="' + esc( cfg.gyg.domain + '/s/?q=' + encodeURIComponent( q ) + '&partner_id=' + cfg.gyg.partner + '&cmp=tt-search' ) + '"><span>Activités « ' + esc( q ) + ' » sur GetYourGuide</span></a>';
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
			if ( window.gtag ) window.gtag( 'event', 'tt_partner_form', { partner: form.getAttribute( 'data-tt-wdg' ), form_id: form.id } );
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

	/* ---------- Barre mobile : visible une fois le hero passé ---------- */
	var bar = $( '[data-tt-mbar]' ), hero = $( '.tt-hero' ) || $( 'h1' );
	if ( bar && hero && 'IntersectionObserver' in window ) {
		new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) { bar.classList.toggle( 'is-visible', ! e.isIntersecting && e.boundingClientRect.top < 0 ); } );
		} ).observe( hero );
	}

	/* ---------- Sommaire : section en cours ---------- */
	$$( '[data-tt-toc]' ).forEach( function ( toc ) {
		var links = $$( 'a', toc );
		if ( ! ( 'IntersectionObserver' in window ) ) return;
		var io = new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) {
				if ( e.isIntersecting ) links.forEach( function ( a ) { a.classList.toggle( 'is-on', a.getAttribute( 'href' ) === '#' + e.target.id ); } );
			} );
		}, { rootMargin: '0px 0px -70% 0px' } );
		links.forEach( function ( a ) { var t = document.getElementById( a.getAttribute( 'href' ).slice( 1 ) ); if ( t ) io.observe( t ); } );
	} );
} )();
