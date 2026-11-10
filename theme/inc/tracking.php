<?php
/**
 * Mesure : contexte de chaque page dans le dataLayer et conteneur Google Tag Manager.
 *
 * - Contexte (type de page, thématique, destination, type d'activité) poussé en tête de page, avant GTM :
 *   les balises GA4 du conteneur l'envoient avec chaque événement.
 * - Clics sur les CTA : poussés par front.js (événements affiliate_click et cta_click).
 * - Conteneur GTM : identifiant dans l'option tt_partners['gtm_id'] ; chargé seulement après accord
 *   « mesure d'audience », comme Google Analytics. Une fois réglé, il remplace la balise Analytics de Site Kit
 *   (le conteneur porte sa propre balise Google, sinon chaque page serait comptée deux fois).
 * - Sans GTM : balise Google Analytics 4 directe, identifiant dans l'option tt_google['ga4_id'] (G-XXXXXXX), avec le
 *   Consent Mode v2. Tout est neutralisé jusqu'à l'accord « mesure d'audience » : aucun script Google, aucun cookie
 *   avant. À l'accord, front.js exécute l'ordre « consentement par défaut refusé, mesure d'audience accordée »
 *   (les signaux publicitaires restent refusés), puis charge gtag.js. Les événements du dataLayer (affiliate_click,
 *   cta_click) sont relayés à GA4 par front.js. Cette balise remplace celle de Site Kit.
 * - Search Console : balise de validation de propriété, option tt_google['gsc_verify'].
 * Plan de taggage : docs/plan-de-taggage.md.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contexte de la page affichée, pour l'analyse par type de page et par thématique.
 *
 * @return array{page_type:string,thematique:string,destination:string,activity_kind:string,page_slug:string}
 */
function tt_page_context(): array {
	$c = array( 'page_type' => 'autre', 'thematique' => '', 'destination' => '', 'activity_kind' => '', 'page_slug' => '' );
	$o = get_queried_object();
	if ( is_front_page() ) {
		$c['page_type'] = 'accueil';
	} elseif ( is_singular( 'post' ) ) {
		$c['page_type']  = 'article';
		$primary         = (int) get_post_meta( $o->ID, '_yoast_wpseo_primary_category', true );
		$cats            = get_the_category( $o->ID );
		$cat             = $primary ? get_term( $primary, 'category' ) : ( $cats[0] ?? null );
		$c['thematique'] = $cat instanceof WP_Term ? html_entity_decode( $cat->name, ENT_QUOTES, 'UTF-8' ) : '';
	} elseif ( is_page() ) {
		$tpl = get_page_template_slug( $o );
		$ctx = tt_destination_context( $o->ID );
		$act = tt_activity_context( $o->ID );
		if ( 'page-catalogue' === $tpl ) {
			$c['page_type'] = 'catalogue';
		} elseif ( 'page-carte' === $tpl ) {
			$c['page_type'] = 'carte';
		} elseif ( get_post_meta( $o->ID, 'tt_destination', true ) ) {
			$c['page_type'] = 'destination';
		} elseif ( 'page-activite' === $tpl ) {
			$c['page_type'] = 'activite';
		} elseif ( $o->post_parent && $ctx ) {
			$c['page_type'] = 'visite';
		} else {
			$c['page_type'] = 'page';
		}
		$c['destination'] = $ctx['name'] ?? ( $act['place'] ?? '' );
		$c['thematique']  = array(
			'destination' => 'Destinations',
			'activite'    => 'Activités',
			'visite'      => 'Visites',
			'catalogue'   => 'Activités',
			'carte'       => 'Activités',
		)[ $c['page_type'] ] ?? 'Pages';
	} elseif ( is_category() ) {
		$c['page_type']  = 'categorie';
		$c['thematique'] = html_entity_decode( single_cat_title( '', false ), ENT_QUOTES, 'UTF-8' );
	} elseif ( is_home() ) {
		$c['page_type'] = 'blog';
	} elseif ( is_author() ) {
		$c['page_type'] = 'auteur';
	} elseif ( is_search() ) {
		$c['page_type'] = 'recherche';
	} elseif ( is_404() ) {
		$c['page_type'] = '404';
	}
	if ( is_singular() ) {
		$kind               = (string) get_post_meta( $o->ID, 'tt_kind', true );
		$c['activity_kind'] = tt_activity_kinds()[ $kind ] ?? '';
		$c['page_slug']     = $o->post_name;
	}
	return $c;
}

// Contexte en tout premier dans le head : disponible dès le chargement du conteneur.
add_action(
	'wp_head',
	static function () {
		printf( "<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push(%s);</script>\n", wp_json_encode( tt_page_context(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) );
	},
	1
);

// Conteneur GTM, neutralisé jusqu'à l'accord (front.js le réactive, voir inc/consent.php).
add_action(
	'wp_head',
	static function () {
		$id = tt_partners()['gtm_id'];
		if ( ! preg_match( '/^GTM-[A-Z0-9]+$/', $id ) ) {
			return;
		}
		printf(
			'<script type="text/plain" data-tt-consent="a">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","%s");</script>' . "\n",
			esc_js( $id )
		);
	},
	2
);

// Avec GTM, la balise Google du conteneur remplace celle de Site Kit (Site Kit garde ses tableaux de bord).
add_filter( 'googlesitekit_analytics-4_tag_blocked', static fn( $blocked ) => $blocked || (bool) preg_match( '/^GTM-[A-Z0-9]+$/', tt_partners()['gtm_id'] ) );

/**
 * Réglages Google posés à distance par la Content Machine (tt/v1/setup) : ga4_id, gsc_verify.
 *
 * @return array{ga4_id:string,gsc_verify:string}
 */
function tt_google(): array {
	$o = (array) get_option( 'tt_google', array() );
	return array(
		'ga4_id'     => preg_match( '/^G-[A-Z0-9]+$/', (string) ( $o['ga4_id'] ?? '' ) ) ? (string) $o['ga4_id'] : '',
		'gsc_verify' => preg_match( '/^[A-Za-z0-9_-]{10,100}$/', (string) ( $o['gsc_verify'] ?? '' ) ) ? (string) $o['gsc_verify'] : '',
	);
}

// Balise GA4 directe (sites sans GTM), neutralisée jusqu'à l'accord, avec le Consent Mode v2.
add_action(
	'wp_head',
	static function () {
		$id = tt_google()['ga4_id'];
		if ( ! $id || preg_match( '/^GTM-[A-Z0-9]+$/', tt_partners()['gtm_id'] ) ) {
			return;
		}
		$ctx = array_filter( array_diff_key( tt_page_context(), array( 'page_slug' => 0 ) ), 'strlen' );
		printf(
			'<script type="text/plain" data-tt-consent="a">window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments);};' .
			'gtag("consent","default",{ad_storage:"denied",ad_user_data:"denied",ad_personalization:"denied",analytics_storage:"denied",functionality_storage:"granted",security_storage:"granted"});' .
			'gtag("consent","update",{analytics_storage:"granted"});gtag("set","ads_data_redaction",true);gtag("js",new Date());gtag("set",%2$s);gtag("config",%1$s);window.ttGa4=%1$s;</script>' . "\n" .
			'<script type="text/plain" data-tt-consent="a" data-src="https://www.googletagmanager.com/gtag/js?id=%3$s"></script>' . "\n",
			wp_json_encode( $id ),
			wp_json_encode( (object) $ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ),
			esc_attr( $id )
		);
	},
	2
);

// Balise GA4 directe en place : celle de Site Kit est coupée, sinon chaque page serait comptée deux fois.
add_filter( 'googlesitekit_analytics-4_tag_blocked', static fn( $blocked ) => $blocked || '' !== tt_google()['ga4_id'] );

// Validation de la propriété Search Console (balise meta, sans cookie).
add_action(
	'wp_head',
	static function () {
		$code = tt_google()['gsc_verify'];
		if ( $code ) {
			printf( '<meta name="google-site-verification" content="%s" />' . "\n", esc_attr( $code ) );
		}
	},
	1
);

// Réglages Google changés : le cache des pages est vidé pour que la balise apparaisse tout de suite.
add_action( 'update_option_tt_google', static fn() => do_action( 'litespeed_purge_all' ) );
add_action( 'add_option_tt_google', static fn() => do_action( 'litespeed_purge_all' ) );
