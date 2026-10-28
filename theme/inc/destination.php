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
		'tt_trip_city'   => array( 'type' => 'integer', 'description' => 'Identifiant de ville Trip.com.' ),
		'tt_intro'       => array( 'type' => 'string', 'description' => 'Accroche courte affichée dans le hero.' ),
		'tt_price_from'  => array( 'type' => 'number', 'description' => 'Prix « dès » affiché dans la barre mobile.' ),
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
				'trip'  => (int) get_post_meta( $id, 'tt_trip_city', true ) ?: (int) ( tt_trip_cities()[ $name ] ?? 0 ),
				'price' => (float) get_post_meta( $id, 'tt_price_from', true ),
				'hub'   => (int) $id,
			);
		}
	}
	return null;
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
	$p = tt_partners();
	return array(
		'label' => $p['cta_default_label'],
		'url'   => str_starts_with( $p['cta_default_url'], 'http' ) ? $p['cta_default_url'] : home_url( $p['cta_default_url'] ),
	);
}
