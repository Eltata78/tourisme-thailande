<?php
/**
 * Formulaires de réservation : soumission directe chez le partenaire, tag d'affiliation inclus.
 *
 * Sans JavaScript, ce sont de simples formulaires GET. front.js ajoute les boutons +/−,
 * les âges des enfants et les dates par défaut.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formulaire hôtels Trip.com.
 *
 * @param array{uid:string,title:string,city:int,cmp:string,compact?:bool} $o
 */
function tt_trip_form( array $o ): string {
	$p    = tt_partners();
	$uid  = 'tt-trip-' . sanitize_key( $o['uid'] );
	$opts = '';
	foreach ( tt_trip_cities() as $name => $id ) {
		$opts .= sprintf( '<option value="%d"%s>%s</option>', $id, selected( $id, (int) $o['city'], false ), esc_html( $name ) );
	}
	ob_start();
	?>
	<form class="tt-wdg<?php echo ! empty( $o['compact'] ) ? ' is-compact' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>" action="https://www.trip.com/hotels/list" method="get" target="_blank" data-tt-wdg="trip">
		<p class="tt-wdg__t"><?php echo esc_html( $o['title'] ); ?></p>
		<label class="tt-f is-full">Destination<select name="city" id="<?php echo esc_attr( $uid ); ?>-city"><?php echo $opts; // phpcs:ignore ?></select></label>
		<label class="tt-f is-date">Arrivée<input type="date" name="checkin" id="<?php echo esc_attr( $uid ); ?>-in" data-tt-date="30" required></label>
		<label class="tt-f is-date">Départ<input type="date" name="checkout" id="<?php echo esc_attr( $uid ); ?>-out" data-tt-date="33" required></label>
		<label class="tt-f">Adultes<input type="number" name="adult" id="<?php echo esc_attr( $uid ); ?>-ad" value="2" min="1" max="9" data-tt-step></label>
		<label class="tt-f">Enfants<input type="number" name="children" id="<?php echo esc_attr( $uid ); ?>-ch" value="0" min="0" max="6" data-tt-step data-tt-children></label>
		<div class="tt-wdg__ages is-full" data-tt-ages></div>
		<input type="hidden" name="crn" value="1">
		<input type="hidden" name="Allianceid" value="<?php echo esc_attr( $p['trip_alliance_id'] ); ?>">
		<input type="hidden" name="SID" value="<?php echo esc_attr( $p['trip_sid'] ); ?>">
		<input type="hidden" name="trip_sub1" value="<?php echo esc_attr( $o['cmp'] ); ?>">
		<button type="submit" class="tt-btn tt-btn--gold is-full">Voir les hôtels disponibles</button>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * Formulaire activités GetYourGuide.
 *
 * @param array{uid:string,title:string,query:string,cmp:string,choices?:array<string,string>,compact?:bool} $o
 */
function tt_gyg_form( array $o ): string {
	$p       = tt_partners();
	$uid     = 'tt-gyg-' . sanitize_key( $o['uid'] );
	$choices = ! empty( $o['choices'] ) ? $o['choices'] : array( 'Toutes les activités' => $o['query'] );
	$opts    = '';
	foreach ( $choices as $label => $q ) {
		$opts .= sprintf( '<option value="%s">%s</option>', esc_attr( $q ), esc_html( $label ) );
	}
	ob_start();
	?>
	<form class="tt-wdg<?php echo ! empty( $o['compact'] ) ? ' is-compact' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>" action="<?php echo esc_url( $p['gyg_domain'] . '/s/' ); ?>" method="get" target="_blank" data-tt-wdg="gyg">
		<p class="tt-wdg__t"><?php echo esc_html( $o['title'] ); ?></p>
		<label class="tt-f is-full">Envie de<select name="q" id="<?php echo esc_attr( $uid ); ?>-q"><?php echo $opts; // phpcs:ignore ?></select></label>
		<label class="tt-f is-full">Date<input type="date" name="date_from" id="<?php echo esc_attr( $uid ); ?>-date" data-tt-date="30" data-tt-same="date_to"></label>
		<input type="hidden" name="date_to" value="">
		<input type="hidden" name="partner_id" value="<?php echo esc_attr( $p['gyg_partner_id'] ); ?>">
		<input type="hidden" name="cmp" value="<?php echo esc_attr( $o['cmp'] ); ?>">
		<button type="submit" class="tt-btn tt-btn--gold is-full">Voir les activités disponibles</button>
	</form>
	<?php
	return (string) ob_get_clean();
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
