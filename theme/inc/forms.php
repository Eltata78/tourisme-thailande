<?php
/**
 * Formulaires de réservation : soumission directe chez le partenaire, tag d'affiliation inclus.
 *
 * Sans JavaScript, ce sont de simples formulaires GET. front.js ajoute les boutons +/−,
 * les âges des enfants et les dates par défaut.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formulaire hôtels Trip.com (fr.trip.com, prix en euros).
 *
 * @param array{uid:string,title:string,dest:string,cmp:string,compact?:bool} $o
 */
function tt_trip_form( array $o ): string {
	$p    = tt_partners();
	$uid  = 'tt-trip-' . sanitize_key( $o['uid'] );
	$key  = tt_trip_key( $o['dest'] ?? '' );
	$opts = '';
	foreach ( tt_trip_destinations() as $k => $d ) {
		// La ville part dans le formulaire même sans JavaScript ; les autres paramètres sont ajoutés à l'envoi par front.js.
		$extra = array_diff_key( $d[1], array( 'city' => 1 ) );
		$opts .= sprintf(
			'<option value="%s"%s%s>%s</option>',
			esc_attr( (string) ( $d[1]['city'] ?? '' ) ),
			$extra ? ' data-tt-q="' . esc_attr( wp_json_encode( $extra ) ) . '"' : '',
			selected( $k, $key, false ),
			esc_html( $d[0] )
		);
	}
	ob_start();
	?>
	<form class="tt-wdg<?php echo ! empty( $o['compact'] ) ? ' is-compact' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>" action="<?php echo esc_url( TT_TRIP_HOTELS ); ?>" method="get" target="_blank" data-tt-wdg="trip">
		<p class="tt-wdg__t"><?php echo esc_html( $o['title'] ); ?></p>
		<label class="tt-f is-full">Destination<select name="city" id="<?php echo esc_attr( $uid ); ?>-city"><?php echo $opts; // phpcs:ignore ?></select></label>
		<label class="tt-f is-date">Arrivée<input type="date" name="checkin" id="<?php echo esc_attr( $uid ); ?>-in" data-tt-date="30" required></label>
		<label class="tt-f is-date">Départ<input type="date" name="checkout" id="<?php echo esc_attr( $uid ); ?>-out" data-tt-date="33" required></label>
		<label class="tt-f">Adultes<input type="number" name="adult" id="<?php echo esc_attr( $uid ); ?>-ad" value="2" min="1" max="9" data-tt-step></label>
		<label class="tt-f">Enfants<input type="number" name="children" id="<?php echo esc_attr( $uid ); ?>-ch" value="0" min="0" max="6" data-tt-step data-tt-children></label>
		<div class="tt-wdg__ages is-full" data-tt-ages></div>
		<input type="hidden" name="crn" value="1">
		<input type="hidden" name="locale" value="fr-FR">
		<input type="hidden" name="curr" value="EUR">
		<input type="hidden" name="Allianceid" value="<?php echo esc_attr( $p['trip_alliance_id'] ); ?>">
		<input type="hidden" name="SID" value="<?php echo esc_attr( $p['trip_sid'] ); ?>">
		<input type="hidden" name="trip_sub1" value="<?php echo esc_attr( $o['cmp'] ); ?>">
		<button type="submit" class="tt-btn tt-btn--gold is-full">Voir les hôtels disponibles</button>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * Formulaire activités GetYourGuide : recherche libre avec suggestions, date, envoi à la recherche GetYourGuide.
 *
 * Le texte saisi part avec la destination (« Muay Thai » -> « Muay Thai Bangkok ») ; champ vide : la requête par défaut.
 * Suggestions pendant la saisie (front.js) : choix de la page, activités du catalogue à cette destination, idées courantes.
 *
 * @param array{uid:string,title:string,query:string,cmp:string,place?:string,choices?:array<string,string>,compact?:bool} $o
 */
function tt_gyg_form( array $o ): string {
	$p     = tt_partners();
	$uid   = 'tt-gyg-' . sanitize_key( $o['uid'] );
	$place = $o['place'] ?? '';
	ob_start();
	?>
	<form class="tt-wdg<?php echo ! empty( $o['compact'] ) ? ' is-compact' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>" action="<?php echo esc_url( $p['gyg_domain'] . '/s/' ); ?>" method="get" target="_blank" data-tt-wdg="gyg"
		data-tt-place="<?php echo esc_attr( $place ); ?>" data-tt-acts="<?php echo esc_attr( wp_json_encode( tt_activity_suggestions( $place, $o['choices'] ?? array() ), JSON_UNESCAPED_UNICODE ) ); ?>">
		<p class="tt-wdg__t"><?php echo esc_html( $o['title'] ); ?></p>
		<div class="tt-ac is-full">
			<label class="tt-f">Activité<input type="search" id="<?php echo esc_attr( $uid ); ?>-what" placeholder="Marché flottant, Muay Thai…" autocomplete="off" enterkeyhint="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-list"></label>
			<div class="tt-search__sugg" id="<?php echo esc_attr( $uid ); ?>-list" role="listbox" hidden></div>
		</div>
		<label class="tt-f is-full">Date<input type="date" name="date_from" id="<?php echo esc_attr( $uid ); ?>-date" data-tt-date="30" data-tt-same="date_to"></label>
		<input type="hidden" name="q" value="<?php echo esc_attr( $o['query'] ); ?>">
		<input type="hidden" name="date_to" value="">
		<input type="hidden" name="partner_id" value="<?php echo esc_attr( $p['gyg_partner_id'] ); ?>">
		<input type="hidden" name="cmp" value="<?php echo esc_attr( $o['cmp'] ); ?>">
		<button type="submit" class="tt-btn tt-btn--gold is-full">Voir les activités disponibles</button>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * Suggestions du formulaire activités : [libellé, requête]. Choix de la page, puis activités du catalogue à cette
 * destination, puis idées courantes (cherchées avec la destination).
 *
 * @param array<string,string> $choices Libellé => requête.
 * @return array<int,array{0:string,1:string}>
 */
function tt_activity_suggestions( string $place, array $choices = array() ): array {
	$out = array();
	foreach ( $choices as $label => $q ) {
		if ( 'Toutes les activités' !== $label ) {
			$out[ $label ] = $q;
		}
	}
	foreach ( tt_catalog_items() as $it ) {
		if ( '' !== $place && $it['dest'] === $place ) {
			$out[ $it['title'] ] = $it['query'];
		}
	}
	$ideas = array( 'Visite guidée', 'Temples', 'Marché flottant', 'Marché de nuit', 'Street food', 'Cours de cuisine thaï', 'Muay Thai', 'Massage thaï', 'Croisière', 'Excursion en bateau', 'Excursion dans les îles', 'Snorkeling', 'Plongée', 'Sanctuaire d\'éléphants', 'Randonnée', 'Kayak', 'Tyrolienne', 'Tour à vélo', 'Spectacle', 'Excursion à la journée', 'Transfert aéroport' );
	foreach ( $ideas as $idea ) {
		$out += array( $idea => $idea );
	}
	return array_map( null, array_keys( $out ), array_values( $out ) );
}

/**
 * Choix « Envie de » saisis dans l'éditeur, une ligne par choix : « Libellé | requête ».
 */
function tt_parse_choices( string $raw, string $fallback ): array {
	$out = array();
	foreach ( preg_split( '/\R/', $raw ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( '' !== $parts[0] ) {
			$out[ $parts[0] ] = $parts[1] ?? $parts[0];
		}
	}
	return $out ? $out : array( 'Toutes les activités' => $fallback );
}
