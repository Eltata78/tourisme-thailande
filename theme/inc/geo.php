<?php
/**
 * Géolocalisation automatique des pages qui ont leur place sur une carte : destinations, leurs sous-pages
 * (cartes des pages destination) et activités du catalogue (page carte).
 *
 * À la publication, si « Coordonnées » est vide, le lieu est cherché sur OpenStreetMap (Nominatim), autour de
 * la destination ; à défaut, les coordonnées de la destination sont reprises (à vérifier). Le champ reste
 * modifiable ; le vider relance la recherche à l'enregistrement suivant. Les activités « partout en Thaïlande »
 * n'ont pas de repère, comme voulu.
 * À chaque publication d'une de ces pages, le cache des pages qui l'affichent (catalogue, carte, destination) est vidé.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ce qu'il faut géolocaliser pour une page : sa destination, et si la page est la destination elle-même.
 *
 * @return array{name:string,is_hub:bool,hub:int,near:?array}|null Null : pas de place sur une carte.
 */
function tt_geo_target( WP_Post $p ): ?array {
	$ctx = 'page' === $p->post_type ? tt_destination_context( $p->ID ) : null;
	if ( $ctx ) {
		$hub = (int) $ctx['hub'];
		return array( 'name' => $ctx['name'], 'is_hub' => $hub === $p->ID, 'hub' => $hub, 'near' => $hub === $p->ID ? null : tt_geo( $hub ) );
	}
	if ( ! get_post_meta( $p->ID, 'tt_kind', true ) ) {
		return null;
	}
	$act  = tt_activity_context( $p->ID );
	$name = $act ? $act['place'] : (string) get_post_meta( $p->ID, 'tt_gyg_query', true );
	if ( '' === $name || 'Thaïlande' === $name ) {
		return null;
	}
	$hub = get_posts( array( 'post_type' => 'page', 'meta_key' => 'tt_destination', 'meta_value' => $name, 'numberposts' => 1, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return array( 'name' => $name, 'is_hub' => false, 'hub' => (int) ( $hub[0] ?? 0 ), 'near' => $hub ? tt_geo( (int) $hub[0] ) : null );
}

/**
 * Coordonnées d'une page sur OpenStreetMap. Recherches dans l'ordre : le titre, le titre sans son mot générique de tête
 * (« Marché de… »), leurs versions anglaises (les noms français manquent souvent : Grand Palace, Sanctuary of Truth),
 * puis le slug, souvent le nom local (damnoen-saduak). Parmi les résultats, le plus proche de la destination.
 * Page activité générique (« Plongée à Koh Tao ») : directement les coordonnées de la destination.
 *
 * @return array{0:float,1:float,2:string}|null Latitude, longitude, source (osm ou destination).
 */
function tt_geocode( WP_Post $p, array $t ): ?array {
	if ( $t['near'] && 'page-activite' === get_page_template_slug( $p ) ) {
		return array( $t['near'][0], $t['near'][1], 'destination' );
	}
	if ( $t['is_hub'] ) {
		$queries = array( $t['name'] );
	} else {
		$en      = array(
			'palais' => 'palace', 'sanctuaire' => 'sanctuary', 'vérité' => 'truth', 'cascades' => 'waterfall', 'cascade' => 'waterfall',
			'île' => 'island', 'îles' => 'islands', 'plage' => 'beach', 'marché' => 'market', 'flottant' => 'floating', 'nuit' => 'night',
			'parc' => 'park', 'national' => 'national', 'montagne' => 'mountain', 'grotte' => 'cave', 'lac' => 'lake', 'musée' => 'museum',
			'pont' => 'bridge', 'rivière' => 'river', 'vieille' => 'old', 'ville' => 'city', 'cochons' => 'pig', 'singes' => 'monkey',
			'éléphants' => 'elephant', 'tigres' => 'tiger', 'jardin' => 'garden', 'tour' => 'tower', 'bouddha' => 'buddha',
		);
		$english = static fn( $s ) => trim( preg_replace( '/\s+/', ' ', preg_replace( '/\b(?:de la|du|des|de|d\'|l\'|la|le|les|aux|au|et|à)\b\s*/iu', ' ', strtr( mb_strtolower( $s ), $en ) ) ) );
		$label   = (string) get_post_meta( $p->ID, 'tt_card_title', true ) ?: html_entity_decode( wp_strip_all_tags( get_the_title( $p ) ), ENT_QUOTES, 'UTF-8' );
		$slug    = trim( str_replace( '-', ' ', preg_replace( '/(^|-)' . preg_quote( sanitize_title( $t['name'] ), '/' ) . '(?=-|$)/', '$1', $p->post_name ) ) );
		// Nom entre parenthèses (« Temple de l'Aube (Wat Arun) ») et titre sans sa suite après « : » ou « – ».
		$paren   = preg_match( '/\(([^)]+)\)/u', $label, $m ) ? trim( $m[1] ) : '';
		$label   = trim( preg_replace( '/\s*(?:\([^)]*\)|\s[:–-]\s.*)$/u', '', $label ) );
		$short   = preg_replace( '/^(?:visiter|visite|découvrir|marché(?: flottant)?|temple|plage|île|cascades?|parc(?: national)?|musée|quartier|grotte|mont|lac)\s+(?:de la\s+|d[eu]s?\s+|d\'|l\'|à\s+)?/iu', '', $label );
		$queries = array_values( array_unique( array_filter( array( $paren, $label, $short, $english( $label ), $english( $short ), $slug ) ) ) );
	}
	foreach ( array_slice( $queries, 0, 5 ) as $i => $q ) {
		if ( $i ) {
			sleep( 1 ); // règles d'usage de Nominatim : une requête par seconde au plus
		}
		$args = array( 'q' => $q, 'format' => 'jsonv2', 'limit' => 8, 'countrycodes' => 'th', 'accept-language' => 'fr,en' );
		if ( $t['near'] ) {
			list( $lat, $lng ) = $t['near'];
			// Large (excursions à la journée : Damnoen Saduak depuis Bangkok) ; le plus proche l'emporte.
			$args += array( 'viewbox' => sprintf( '%F,%F,%F,%F', $lng - 0.8, $lat + 0.8, $lng + 0.8, $lat - 0.8 ), 'bounded' => 1 );
		} elseif ( ! $t['is_hub'] ) {
			$args['q'] .= ', ' . $t['name'];
		}
		$res  = wp_remote_get( 'https://nominatim.openstreetmap.org/search?' . http_build_query( $args ), array( 'timeout' => 6, 'user-agent' => 'tourisme-thailande.fr (géolocalisation des pages ; ' . home_url( '/' ) . ')' ) );
		$data = is_wp_error( $res ) ? array() : (array) json_decode( wp_remote_retrieve_body( $res ), true );
		// Cours d'eau écartés : leur point est le milieu du fleuve, parfois à 50 km (Chao Phraya).
		$data = array_values( array_filter( $data, static fn( $r ) => isset( $r['lat'], $r['lon'] ) && 'waterway' !== ( $r['category'] ?? $r['class'] ?? '' ) ) );
		if ( ! $data ) {
			continue;
		}
		if ( $t['near'] ) {
			$dist = static fn( $r ) => ( $r['lat'] - $t['near'][0] ) ** 2 + ( ( $r['lon'] - $t['near'][1] ) * cos( deg2rad( $t['near'][0] ) ) ) ** 2;
			usort( $data, static fn( $a, $b ) => $dist( $a ) <=> $dist( $b ) );
		}
		return array( (float) $data[0]['lat'], (float) $data[0]['lon'], 'osm' );
	}
	return $t['near'] ? array( $t['near'][0], $t['near'][1], 'destination' ) : null;
}

add_action(
	'wp_after_insert_post',
	static function ( int $id, WP_Post $post ) {
		if ( 'publish' !== $post->post_status || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return;
		}
		$t = tt_geo_target( $post );
		if ( ! $t ) {
			return;
		}
		if ( '' === trim( (string) get_post_meta( $id, 'tt_geo', true ) ) ) {
			// Déjà cherché sans résultat pour ce titre et cette destination : pas de nouvel appel à chaque enregistrement.
			$try = md5( $post->post_title . '|' . $post->post_name . '|' . $t['name'] );
			if ( get_post_meta( $id, '_tt_geo_tried', true ) !== $try ) {
				$geo = tt_geocode( $post, $t );
				if ( $geo ) {
					update_post_meta( $id, 'tt_geo', sprintf( '%.5f,%.5f', $geo[0], $geo[1] ) );
					update_post_meta( $id, '_tt_geo_source', $geo[2] );
				} else {
					update_post_meta( $id, '_tt_geo_tried', $try );
				}
			}
		}
		// Pages qui affichent celle-ci : catalogue, page carte, sa destination et les sous-pages (cartes voisines).
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND ( post_content LIKE '%wp:tt/catalog%' OR post_content LIKE '%wp:tt/explorer%' )" );
		if ( $t['hub'] ) {
			$ids[] = $t['hub'];
			$ids   = array_merge( $ids, get_posts( array( 'post_type' => 'page', 'post_parent' => $t['hub'], 'numberposts' => -1, 'fields' => 'ids' ) ) );
		}
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $pid ) {
			do_action( 'litespeed_purge_post', $pid );
		}
	},
	20,
	2
);
