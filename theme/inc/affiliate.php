<?php
/**
 * Filet de sécurité de l'affiliation : tout lien GetYourGuide ou Trip.com d'un contenu (collé à la main, publié par
 * Content Machine…) qui n'a pas les identifiants partenaire les reçoit à l'affichage.
 *
 * - GetYourGuide : partner_id, et cmp « tt-{page}-lien-texte » (suivi par emplacement) s'il n'y en a pas.
 * - Trip.com : Allianceid et SID, et trip_sub1 « tt-{page}-lien-texte » s'il n'y en a pas.
 * Les liens courts gyg.me et trip.com/t (générateurs des espaces partenaires) portent déjà les identifiants.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lien partenaire complété de ses identifiants s'ils manquent ; autre lien : inchangé.
 */
function tt_affiliate_url( string $url, string $cmp ): string {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	$p    = tt_partners();
	if ( preg_match( '/(^|\.)getyourguide\.[a-z.]+$/', $host ) && false === strpos( $url, 'partner_id=' ) ) {
		$args = array( 'partner_id' => $p['gyg_partner_id'] );
		if ( false === strpos( $url, 'cmp=' ) ) {
			$args['cmp'] = $cmp;
		}
		return add_query_arg( $args, $url );
	}
	if ( preg_match( '/(^|\.)trip\.com$/', $host ) && false === strpos( $url, '/t/' ) && false === stripos( $url, 'allianceid=' ) ) {
		$args = array( 'Allianceid' => $p['trip_alliance_id'], 'SID' => $p['trip_sid'] );
		if ( false === strpos( $url, 'trip_sub1=' ) ) {
			$args['trip_sub1'] = $cmp;
		}
		return add_query_arg( $args, $url );
	}
	return $url;
}

// Après le rendu des blocs : tous les liens du contenu, quel que soit le bloc qui les porte.
add_filter(
	'the_content',
	static function ( $content ) {
		if ( is_admin() || is_feed() || false === stripos( $content, 'getyourguide.' ) && false === stripos( $content, 'trip.com' ) ) {
			return $content;
		}
		$cmp = tt_cmp( 'lien-texte' );
		return preg_replace_callback(
			'/(<a\s[^>]*?href=)(["\'])(https?:\/\/[^"\']+)\2/i',
			static function ( $m ) use ( $cmp ) {
				$url = html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' );
				$new = tt_affiliate_url( $url, $cmp );
				return $new === $url ? $m[0] : $m[1] . $m[2] . esc_url( $new ) . $m[2];
			},
			$content
		);
	},
	20
);
