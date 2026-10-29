<?php
/**
 * Données de destination portées par les pages (hubs et sous-pages).
 *
 * Les métadonnées sont exposées dans l'API REST pour que le MCP puisse les écrire.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Métadonnées de page et leur description.
 */
function tt_page_meta_fields(): array {
	return array(
		'tt_destination' => array( 'type' => 'string', 'description' => 'Nom de la destination (ex. Bangkok). Hérité par les sous-pages.' ),
		'tt_gyg_query'   => array( 'type' => 'string', 'description' => 'Requête GetYourGuide par défaut (ex. Bangkok).' ),
		'tt_trip_city'   => array( 'type' => 'string', 'description' => 'Destination hôtels Trip.com (clé de tt_trip_destinations, ex. koh-samui).' ),
		'tt_intro'       => array( 'type' => 'string', 'description' => 'Accroche courte affichée dans le hero.' ),
		'tt_h1'          => array( 'type' => 'string', 'description' => 'Titre H1 affiché dans le hero (le titre de la page reste court pour les menus et le fil d\'Ariane).' ),
		'tt_card_title'  => array( 'type' => 'string', 'description' => 'Titre court utilisé sur les cartes (grilles de pages).' ),
		'tt_price_from'  => array( 'type' => 'number', 'description' => 'Prix « dès » affiché dans la barre mobile.' ),
		'tt_geo'         => array( 'type' => 'string', 'description' => 'Coordonnées du lieu « latitude,longitude » (repère sur la carte des visites).' ),
		'tt_gyg_url'     => array( 'type' => 'string', 'description' => 'Lien GetYourGuide de l\'activité de la fiche (page produit ou lien gyg.me) ; le tag partenaire est ajouté à l\'affichage.' ),
	);
}

add_action(
	'init',
	static function () {
		foreach ( tt_page_meta_fields() as $key => $def ) {
			register_post_meta(
				'page',
				$key,
				array(
					'type'          => $def['type'],
					'description'   => $def['description'],
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_pages' ),
				)
			);
		}
		// Articles : requête d'activités propre à l'article (destination dont il parle), sinon « Thaïlande ».
		register_post_meta(
			'post',
			'tt_gyg_query',
			array(
				'type'          => 'string',
				'description'   => 'Requête GetYourGuide de l\'article (ex. Koh Phangan).',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		add_post_type_support( 'page', 'excerpt' );
	}
);

/**
 * Contexte de destination de la page courante, hérité du hub parent si besoin.
 *
 * @return array{name:string,gyg:string,trip:int,price:float,hub:int}|null
 */
function tt_destination_context( ?int $post_id = null ): ?array {
	$post = get_post( $post_id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return null;
	}
	$chain = array_merge( array( $post->ID ), get_post_ancestors( $post ) );
	foreach ( $chain as $id ) {
		$name = (string) get_post_meta( $id, 'tt_destination', true );
		if ( '' !== $name ) {
			$gyg = (string) get_post_meta( $post->ID, 'tt_gyg_query', true );
			return array(
				'name'  => $name,
				'gyg'   => '' !== $gyg ? $gyg : ( (string) get_post_meta( $id, 'tt_gyg_query', true ) ?: $name ),
				'trip'  => tt_trip_key( get_post_meta( $id, 'tt_trip_city', true ) ) ?: tt_trip_key( $name ),
				'price' => (float) get_post_meta( $id, 'tt_price_from', true ),
				'hub'   => (int) $id,
			);
		}
	}
	return null;
}

/**
 * Contexte d'une page activité (gabarit Activité) : son libellé, la requête d'offres et la destination hôtels.
 * « Plongée à Phuket » -> offres « plongée Phuket », hôtels à Phuket ; une page Thaïlande n'a pas de destination hôtels.
 *
 * @return array{label:string,gyg:string,trip:string,place:string}|null
 */
function tt_activity_context( ?int $post_id = null ): ?array {
	$post = get_post( $post_id );
	if ( ! $post || 'page' !== $post->post_type || 'page-activite' !== get_page_template_slug( $post ) ) {
		return null;
	}
	$label = (string) get_post_meta( $post->ID, 'tt_card_title', true ) ?: wp_strip_all_tags( get_the_title( $post ) );
	$trip  = tt_trip_key( get_post_meta( $post->ID, 'tt_trip_city', true ) );
	return array(
		'label' => $label,
		'gyg'   => (string) get_post_meta( $post->ID, 'tt_gyg_query', true ) ?: $label,
		'trip'  => $trip,
		'place' => $trip ? tt_trip_destinations()[ $trip ][0] : 'Thaïlande',
	);
}

/**
 * Choix « Envie de » d'une page activité : elle, puis ses sous-pages (Plongée en Thaïlande -> Koh Tao, Phuket…).
 *
 * @return array<string,string> libellé => requête
 */
function tt_activity_choices( WP_Post $post ): array {
	$act = tt_activity_context( $post->ID );
	if ( ! $act ) {
		return array();
	}
	$out = array( $act['label'] => $act['gyg'] );
	foreach ( get_pages( array( 'parent' => $post->ID, 'sort_column' => 'menu_order,post_title' ) ) as $kid ) {
		$k = tt_activity_context( $kid->ID );
		if ( $k ) {
			$out[ $k['label'] ] = $k['gyg'];
		}
	}
	return $out;
}

/**
 * Bouton de réservation du header : contextuel sur les pages destination, générique ailleurs.
 *
 * @return array{label:string,url:string}
 */
function tt_header_cta(): array {
	$ctx = is_page() ? tt_destination_context() : null;
	if ( $ctx ) {
		return array(
			'label' => sprintf( 'Activités à %s', $ctx['name'] ),
			'url'   => tt_gyg_url( $ctx['gyg'], tt_cmp( 'header' ) ),
		);
	}
	$act = is_page() ? tt_activity_context() : null;
	if ( $act ) {
		return array(
			'label' => $act['label'],
			'url'   => tt_gyg_url( $act['gyg'], tt_cmp( 'header' ) ),
		);
	}
	// Article qui parle d'une destination : ses activités ; sinon le lien par défaut des réglages.
	$q = is_singular( 'post' ) ? (string) get_post_meta( get_queried_object_id(), 'tt_gyg_query', true ) : '';
	if ( '' !== $q && 'Thaïlande' !== $q ) {
		return array(
			'label' => sprintf( 'Activités à %s', $q ),
			'url'   => tt_gyg_url( $q, tt_cmp( 'header' ) ),
		);
	}
	$p = tt_partners();
	return array(
		'label' => $p['cta_default_label'],
		'url'   => str_starts_with( $p['cta_default_url'], 'http' ) ? $p['cta_default_url'] : home_url( $p['cta_default_url'] ),
	);
}

/**
 * Coordonnées d'une page (meta tt_geo « lat,lng »), ou null.
 *
 * @return array{0:float,1:float}|null
 */
function tt_geo( int $post_id ): ?array {
	if ( ! preg_match( '/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', (string) get_post_meta( $post_id, 'tt_geo', true ), $m ) ) {
		return null;
	}
	return array( (float) $m[1], (float) $m[2] );
}

/**
 * Lieux affichés par la carte d'une page : sur un hub, ses fiches ; sur une fiche, elle et ses voisines.
 * Seules les fiches géolocalisées (meta tt_geo) comptent. Sert à la carte et aux données structurées.
 *
 * @return array{hub:WP_Post,places:WP_Post[]}
 */
function tt_map_places( WP_Post $post ): array {
	$kids   = get_pages( array( 'parent' => $post->ID, 'sort_column' => 'menu_order,post_title' ) );
	$hub    = $kids || ! $post->post_parent ? $post : get_post( $post->post_parent );
	$pages  = $kids ?: ( $post->post_parent ? get_pages( array( 'parent' => $post->post_parent, 'sort_column' => 'menu_order,post_title' ) ) : array() );
	return array(
		'hub'    => $hub,
		'places' => array_values( array_filter( $pages, static fn( $p ) => null !== tt_geo( $p->ID ) ) ),
	);
}

/**
 * Distance en kilomètres entre deux points [lat, lng].
 */
function tt_km( array $a, array $b ): float {
	$r = M_PI / 180;
	$h = sin( ( $b[0] - $a[0] ) * $r / 2 ) ** 2 + cos( $a[0] * $r ) * cos( $b[0] * $r ) * sin( ( $b[1] - $a[1] ) * $r / 2 ) ** 2;
	return 12742 * asin( sqrt( $h ) );
}
