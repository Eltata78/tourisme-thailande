<?php
/**
 * Catalogue d'activités : toutes les pages (et quelques guides) qui portent un type d'activité (meta tt_kind).
 * Chaque carte mène à la page détaillée de l'activité, qui elle porte les offres partenaires.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Types d'activité, dans l'ordre d'affichage : clé => libellé (profil du pays, tt_site()['kinds']).
 */
function tt_activity_kinds(): array {
	return (array) tt_site()['kinds'];
}

/**
 * Régions du filtre « Destination », dans l'ordre d'affichage : région => destinations (profil du pays).
 */
function tt_regions(): array {
	return (array) tt_site()['regions'];
}

// Type d'activité et titre court valent aussi pour les articles : un guide d'activité peut entrer au catalogue.
add_action(
	'init',
	static function () {
		foreach ( array( 'tt_kind', 'tt_card_title', 'tt_geo' ) as $key ) {
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
		$dest = $ctx['name'] ?? ( $act['place'] ?? ( '' !== $q ? $q : tt_country() ) );
		$title = (string) get_post_meta( $p->ID, 'tt_card_title', true );
		if ( '' === $title ) {
			$title = 'post' === $p->post_type ? preg_replace( '/\s*[:?].*$/u', '', get_the_title( $p ) ) : get_the_title( $p ); // article : sans sa seconde partie
		}
		$title   = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' );
		$region  = tt_country() === $dest ? '' : ( array_keys( $regions )[ $rank[ $dest ] ?? -1 ] ?? 'Autres destinations' );
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
			'order'  => array( tt_country() === $dest ? 99 : ( $rank[ $dest ] ?? 50 ), $dest, remove_accents( $title ) ),
			'geo'    => tt_geo( $p->ID ),
			'gyg'    => (string) get_post_meta( $p->ID, 'tt_gyg_url', true ),
			'query'  => $act['gyg'] ?? ( 'post' === $p->post_type ? $title . ( tt_country() === $dest ? '' : ' ' . $dest ) : ( $q ?: ( $ctx['gyg'] ?? $title ) ) ),
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

/**
 * Catalogue ou carte sans aucune activité (site qui démarre) : titre de la page, phrase d'attente
 * et lien vers toutes les offres du pays chez GetYourGuide, au lieu d'une page vide.
 */
function tt_catalog_empty_state( WP_Post $post, string $placement ): string {
	$url = tt_gyg_url( tt_country(), tt_cmp( $placement, $post->ID ) );
	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'tt-catalog tt-catalog--empty' ) ) . '><header class="tt-catalog__head">'
		. '<h1>' . esc_html( (string) get_post_meta( $post->ID, 'tt_h1', true ) ?: get_the_title( $post ) ) . '</h1>'
		. '<p>' . esc_html( sprintf( 'Nos fiches d\'activités %s arrivent bientôt. En attendant, toutes les visites et excursions sont chez notre partenaire, avec annulation gratuite possible.', tt_site()['in'] ) ) . '</p>'
		. '<p><a class="tt-btn tt-btn--gold" href="' . esc_url( $url ) . '" target="_blank" rel="sponsored nofollow noopener">' . esc_html( 'Voir les activités ' . tt_site()['in'] ) . '</a></p>'
		. '</header></section>';
}
