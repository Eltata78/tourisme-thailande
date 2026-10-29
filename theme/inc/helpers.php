<?php
/**
 * Réglages partenaires et constructeurs de liens d'affiliation.
 */

defined( 'ABSPATH' ) || exit;

const TT_TRIP_HOTELS = 'https://fr.trip.com/hotels/list';

/**
 * Réglages partenaires, surchargeables via l'option `tt_partners`.
 */
function tt_partners(): array {
	static $opts = null;
	if ( null === $opts ) {
		$opts = wp_parse_args(
			(array) get_option( 'tt_partners', array() ),
			array(
				'gyg_partner_id'    => 'YADMZ9Q',
				'gyg_domain'        => 'https://www.getyourguide.fr',
				'trip_alliance_id'  => '7320286',
				'trip_sid'          => '274143472',
				'cta_default_label' => 'Réserver une activité',
				'cta_default_url'   => '/activites-thailande/',
			)
		);
	}
	return $opts;
}

/**
 * Lien de recherche GetYourGuide avec le tag partenaire.
 */
function tt_gyg_url( string $query, string $cmp, string $date = '' ): string {
	$p    = tt_partners();
	$args = array(
		'q'          => $query,
		'partner_id' => $p['gyg_partner_id'],
		'cmp'        => $cmp,
	);
	if ( $date ) {
		$args['date_from'] = $date;
		$args['date_to']   = $date;
	}
	return add_query_arg( array_map( 'rawurlencode', $args ), $p['gyg_domain'] . '/s/' );
}

/**
 * Ajoute le tag partenaire à un lien GetYourGuide existant (fiche activité, lien gyg.me…).
 */
function tt_gyg_tag( string $url, string $cmp ): string {
	if ( ! preg_match( '#^https://(www\.)?getyourguide\.#', $url ) ) {
		return $url; // Les liens courts gyg.me portent déjà le tag.
	}
	return add_query_arg(
		array(
			'partner_id' => tt_partners()['gyg_partner_id'],
			'cmp'        => rawurlencode( $cmp ),
		),
		$url
	);
}

/**
 * Destinations hôtels Trip.com : paramètres de recherche repris des liens générés dans l'espace partenaire.
 * Krabi se cherche à l'échelle de la province, Ban Krut comme un lieu (plage) rattaché à Bang Saphan.
 *
 * @return array<string,array{0:string,1:array<string,scalar>}> clé => [libellé, paramètres]
 */
function tt_trip_destinations(): array {
	return apply_filters(
		'tt_trip_destinations',
		array(
			'bangkok'     => array( 'Bangkok', array( 'city' => 359 ) ),
			'ayutthaya'   => array( 'Ayutthaya', array( 'city' => 36030 ) ),
			'pattaya'     => array( 'Pattaya', array( 'city' => 622 ) ),
			'chiang-mai'  => array( 'Chiang Mai', array( 'city' => 623 ) ),
			'chiang-rai'  => array( 'Chiang Rai', array( 'city' => 647 ) ),
			'phuket'      => array( 'Phuket', array( 'city' => 725 ) ),
			'krabi'       => array( 'Krabi', array( 'city' => -1, 'provinceId' => 10129, 'countryId' => 4, 'searchType' => 'P', 'searchWord' => 'Krabi', 'searchValue' => '39|10129*39*10129*1' ) ),
			'koh-phi-phi' => array( 'Koh Phi Phi', array( 'city' => 1228 ) ),
			'koh-samui'   => array( 'Koh Samui', array( 'city' => 1229 ) ),
			'koh-tao'     => array( 'Koh Tao', array( 'city' => 19957 ) ),
			'ban-krut'    => array( 'Ban Krut', array( 'cityId' => 62158, 'provinceId' => 11042, 'countryId' => 4, 'searchType' => 'LM', 'optionId' => 8248394, 'searchValue' => '13|8248394*13*11.3451418|99.5644769|Ban Krut Beach|8248394|1', 'old' => 1 ) ),
		)
	);
}

/**
 * Clé de destination Trip à partir d'une clé, d'un nom (« Koh Samui ») ou d'un ancien identifiant de ville.
 */
function tt_trip_key( $value ): string {
	$dests = tt_trip_destinations();
	$key   = sanitize_title( (string) $value );
	if ( isset( $dests[ $key ] ) ) {
		return $key;
	}
	foreach ( $dests as $k => $d ) {
		if ( is_numeric( $value ) && (int) $value === (int) ( $d[1]['city'] ?? 0 ) ) {
			return $k;
		}
	}
	return '';
}

/**
 * Lien hôtels Trip.com (version française, prix en euros) avec les identifiants partenaires.
 *
 * @param array{checkin?:string,checkout?:string,adult?:int,children?:int} $stay
 */
function tt_trip_url( string $dest, string $sub, array $stay = array() ): string {
	$p     = tt_partners();
	$key   = tt_trip_key( $dest );
	$extra = $key ? tt_trip_destinations()[ $key ][1] : array();
	$args  = array_filter(
		$extra + array(
			'checkin'    => isset( $stay['checkin'] ) ? str_replace( '-', '/', $stay['checkin'] ) : '',
			'checkout'   => isset( $stay['checkout'] ) ? str_replace( '-', '/', $stay['checkout'] ) : '',
			'adult'      => $stay['adult'] ?? '',
			'children'   => $stay['children'] ?? '',
			'crn'        => 1,
			'locale'     => 'fr-FR',
			'curr'       => 'EUR',
			'Allianceid' => $p['trip_alliance_id'],
			'SID'        => $p['trip_sid'],
			'trip_sub1'  => $sub,
		),
		static fn( $v ) => '' !== $v
	);
	return add_query_arg( array_map( 'rawurlencode', $args ), TT_TRIP_HOTELS );
}

/**
 * Identifiant de campagne lisible : tt-{slug de la page}-{emplacement}.
 */
function tt_cmp( string $placement, ?int $post_id = null ): string {
	$post = get_post( $post_id );
	$slug = $post ? $post->post_name : 'site';
	return sanitize_title( 'tt-' . $slug . '-' . $placement );
}

/**
 * Logo du site : emblème (drapeau et temple) et nom en texte, lisible à la hauteur d'un header.
 * Le nom se change avec le filtre tt_brand_name.
 */
function tt_brand(): string {
	return sprintf(
		'<span class="tt-brand"><img class="tt-brand__mark" src="%s" width="41" height="40" alt="" decoding="async"><span class="tt-brand__name">%s</span></span>',
		esc_url( TT_URI . '/assets/img/emblem.webp' ),
		esc_html( apply_filters( 'tt_brand_name', 'Tourisme Thaïlande' ) )
	);
}

/**
 * Image responsive d'une pièce jointe, avec chargement prioritaire optionnel.
 */
function tt_img( int $id, string $size = 'large', array $attr = array() ): string {
	if ( ! $id ) {
		return '';
	}
	return wp_get_attachment_image( $id, $size, false, $attr );
}

/**
 * Formulaire de recherche : fonctionne sans JS (page de résultats ou redirection directe),
 * enrichi de suggestions instantanées par assets/js/front.js.
 */
function tt_search_form( string $uid, string $placeholder = 'Bangkok, Wat Pho, full moon party…' ): string {
	$id = 'tt-q-' . sanitize_key( $uid );
	return sprintf(
		'<form class="tt-search" role="search" method="get" action="%1$s" data-tt-search>
			<label class="screen-reader-text" for="%2$s">Rechercher une destination, une visite ou un guide</label>
			<div class="tt-search__bar">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
				<input type="search" id="%2$s" name="s" autocomplete="off" placeholder="%3$s">
				<button type="submit" class="tt-btn tt-btn--indigo">Rechercher</button>
			</div>
			<div class="tt-search__sugg" role="listbox" hidden></div>
		</form>',
		esc_url( home_url( '/' ) ),
		esc_attr( $id ),
		esc_attr( $placeholder )
	);
}
