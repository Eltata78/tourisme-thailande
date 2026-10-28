<?php
/**
 * Réglages partenaires et constructeurs de liens d'affiliation.
 */

defined( 'ABSPATH' ) || exit;

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
 * Lien de liste d'hôtels Trip.com avec les identifiants partenaires.
 */
function tt_trip_url( int $city, string $sub, array $stay = array() ): string {
	$p    = tt_partners();
	$args = array_filter(
		array(
			'city'       => $city,
			'checkin'    => $stay['checkin'] ?? '',
			'checkout'   => $stay['checkout'] ?? '',
			'adult'      => $stay['adult'] ?? '',
			'children'   => $stay['children'] ?? '',
			'crn'        => 1,
			'Allianceid' => $p['trip_alliance_id'],
			'SID'        => $p['trip_sid'],
			'trip_sub1'  => $sub,
		),
		static fn( $v ) => '' !== $v
	);
	return add_query_arg( $args, 'https://www.trip.com/hotels/list' );
}

/**
 * Villes Trip.com proposées dans les formulaires (identifiants à confirmer dans l'outil partenaire).
 */
function tt_trip_cities(): array {
	return apply_filters(
		'tt_trip_cities',
		array(
			'Bangkok'     => 359,
			'Phuket'      => 725,
			'Chiang Mai'  => 623,
			'Krabi'       => 1240,
			'Koh Samui'   => 1229,
			'Koh Phangan' => 1231,
			'Koh Tao'     => 1230,
			'Pattaya'     => 622,
			'Chiang Rai'  => 1223,
			'Ayutthaya'   => 1225,
		)
	);
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
