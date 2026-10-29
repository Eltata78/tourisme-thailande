<?php
/**
 * Catalogue d'activités : toutes les pages (et quelques guides) qui portent un type d'activité (meta tt_kind).
 * Chaque carte mène à la page détaillée de l'activité, qui elle porte les offres partenaires.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Types d'activité, dans l'ordre d'affichage : clé => libellé.
 */
function tt_activity_kinds(): array {
	return array(
		'temples'     => 'Temples et culture',
		'marches'     => 'Marchés',
		'iles'        => 'Îles et bateau',
		'nature'      => 'Nature et parcs',
		'plongee'     => 'Plongée et snorkeling',
		'sorties'     => 'Spectacles et sorties',
		'experiences' => 'Expériences et loisirs',
	);
}

/**
 * Régions du filtre « Destination », dans l'ordre d'affichage : région => destinations.
 */
function tt_regions(): array {
	return array(
		'Le Nord'              => array( 'Chiang Mai', 'Chiang Rai', 'Pai', 'Mae Hong Son' ),
		'Bangkok et le Centre' => array( 'Bangkok', 'Ayutthaya', 'Pattaya', 'Kanchanaburi', 'Lopburi' ),
		'Golfe de Thaïlande'   => array( 'Koh Samui', 'Koh Phangan', 'Koh Tao', 'Ban Krut', 'Hua Hin' ),
		"Mer d'Andaman"        => array( 'Phuket', 'Krabi', 'Koh Phi Phi', 'Koh Lanta', 'Khao Lak' ),
	);
}

// Type d'activité et titre court valent aussi pour les articles : un guide d'activité peut entrer au catalogue.
add_action(
	'init',
	static function () {
		foreach ( array( 'tt_kind', 'tt_card_title' ) as $key ) {
			register_post_meta(
				'post',
				$key,
				array(
					'type'          => 'string',
					'description'   => tt_page_meta_fields()[ $key ]['description'],
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
);

/**
 * Activités du catalogue, triées par région, destination puis titre.
 *
 * @return array<int,array{id:int,title:string,url:string,img:int,dest:string,dslug:string,region:string,kind:string,search:string}>
 */
function tt_catalog_items(): array {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$kinds   = tt_activity_kinds();
	$regions = tt_regions();
	$rank    = array();
	foreach ( array_values( $regions ) as $i => $names ) {
		foreach ( $names as $name ) {
			$rank[ $name ] = $i;
		}
	}
	$posts = get_posts(
		array(
			'post_type'   => array( 'page', 'post' ),
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_key'    => 'tt_kind', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	$items = array();
	foreach ( $posts as $p ) {
		$kind = (string) get_post_meta( $p->ID, 'tt_kind', true );
		if ( ! isset( $kinds[ $kind ] ) ) {
			continue;
		}
		$ctx  = 'page' === $p->post_type ? tt_destination_context( $p->ID ) : null;
		$act  = $ctx ? null : tt_activity_context( $p->ID );
		$q    = (string) get_post_meta( $p->ID, 'tt_gyg_query', true );
		$dest = $ctx['name'] ?? ( $act['place'] ?? ( '' !== $q ? $q : 'Thaïlande' ) );
		$title = (string) get_post_meta( $p->ID, 'tt_card_title', true );
		if ( '' === $title ) {
			$title = 'post' === $p->post_type ? preg_replace( '/\s*[:?].*$/u', '', get_the_title( $p ) ) : get_the_title( $p ); // article : sans sa seconde partie
		}
		$title   = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' );
		$region  = 'Thaïlande' === $dest ? '' : ( array_keys( $regions )[ $rank[ $dest ] ?? -1 ] ?? 'Autres destinations' );
		$items[] = array(
			'id'     => $p->ID,
			'title'  => $title,
			'url'    => get_permalink( $p ),
			'img'    => (int) get_post_thumbnail_id( $p->ID ),
			'dest'   => $dest,
			'dslug'  => sanitize_title( $dest ),
			'region' => $region,
			'kind'   => $kind,
			'search' => implode( ' ', array( $dest, $kinds[ $kind ], get_the_title( $p ), (string) get_post_meta( $p->ID, 'tt_synonyms', true ), $p->post_parent ? get_the_title( $p->post_parent ) : '' ) ),
			'order'  => array( 'Thaïlande' === $dest ? 99 : ( $rank[ $dest ] ?? 50 ), $dest, remove_accents( $title ) ),
		);
	}
	usort( $items, static fn( $a, $b ) => $a['order'] <=> $b['order'] );
	return $items;
}

// Données structurées : la page catalogue est une collection, sa liste d'activités un ItemList.
add_filter(
	'wpseo_schema_webpage_type',
	static function ( $type ) {
		return is_page() && has_block( 'tt/catalog', get_queried_object() ) ? array( 'WebPage', 'CollectionPage' ) : $type;
	}
);
add_filter(
	'wpseo_schema_graph',
	static function ( $graph ) {
		$post = is_page() ? get_queried_object() : null;
		if ( ! is_array( $graph ) || ! $post instanceof WP_Post || ! has_block( 'tt/catalog', $post ) ) {
			return $graph;
		}
		$id    = get_permalink( $post ) . '#activites';
		$items = tt_catalog_items();
		foreach ( $graph as &$piece ) {
			if ( in_array( 'WebPage', (array) ( $piece['@type'] ?? array() ), true ) ) {
				$piece['mainEntity'] = array( '@id' => $id );
			}
		}
		unset( $piece );
		$graph[] = array(
			'@type'           => 'ItemList',
			'@id'             => $id,
			'name'            => wp_strip_all_tags( get_the_title( $post ) ),
			'numberOfItems'   => count( $items ),
			'itemListElement' => array_map(
				static fn( $it, $i ) => array( '@type' => 'ListItem', 'position' => $i + 1, 'url' => $it['url'], 'name' => $it['title'] ),
				$items,
				array_keys( $items )
			),
		);
		return $graph;
	}
);
