<?php
/**
 * Recherche : index JSON pour les suggestions et redirection directe vers un guide.
 */

defined( 'ABSPATH' ) || exit;

const TT_SEARCH_TRANSIENT = 'tt_search_index_v1';

/**
 * Normalise une chaîne pour la comparaison (minuscules, sans accents).
 */
function tt_norm( string $s ): string {
	return strtolower( trim( remove_accents( html_entity_decode( wp_strip_all_tags( $s ) ) ) ) );
}

/**
 * Index des contenus publiés : [titre, chemin, type, mots-clés, ID].
 */
function tt_search_index(): array {
	$cached = get_transient( TT_SEARCH_TRANSIENT );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$rows  = array();
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);
	$front = (int) get_option( 'page_on_front' );
	foreach ( $pages as $p ) {
		if ( $p->ID === $front ) {
			continue;
		}
		$type = 'Page';
		if ( get_post_meta( $p->ID, 'tt_destination', true ) ) {
			$type = 'Destination';
		} elseif ( $p->post_parent ) {
			$type = 'Visite';
		} elseif ( str_contains( get_page_template_slug( $p ), 'activite' ) ) {
			$type = 'Activité';
		}
		$parent = $p->post_parent ? get_the_title( $p->post_parent ) : '';
		$rows[] = array( html_entity_decode( get_the_title( $p ) ), wp_make_link_relative( get_permalink( $p ) ), $type, trim( $parent . ' ' . get_post_meta( $p->ID, 'tt_synonyms', true ) ), $p->ID );
	}
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $posts as $id ) {
		$rows[] = array( html_entity_decode( get_the_title( $id ) ), wp_make_link_relative( get_permalink( $id ) ), 'Guide', (string) get_post_meta( $id, 'tt_synonyms', true ), $id );
	}
	set_transient( TT_SEARCH_TRANSIENT, $rows, DAY_IN_SECONDS );
	return $rows;
}

add_action( 'save_post', static fn() => delete_transient( TT_SEARCH_TRANSIENT ) );
add_action( 'deleted_post', static fn() => delete_transient( TT_SEARCH_TRANSIENT ) );

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'tt/v1',
			'/search-index',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$res = rest_ensure_response( tt_search_index() );
					$res->header( 'Cache-Control', 'public, max-age=3600' );
					return $res;
				},
			)
		);
	}
);

// Synonymes de recherche (ex. « bkk krung thep » sur la page Bangkok).
add_action(
	'init',
	static function () {
		foreach ( array( 'page', 'post' ) as $type ) {
			register_post_meta(
				$type,
				'tt_synonyms',
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
);

/**
 * Requête qui désigne sans ambiguïté une destination ou une visite : on ouvre directement le guide.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_search() || is_paged() ) {
			return;
		}
		$q = tt_norm( get_search_query( false ) );
		if ( strlen( $q ) < 3 ) {
			return;
		}
		foreach ( tt_search_index() as $row ) {
			if ( ! in_array( $row[2], array( 'Destination', 'Visite', 'Activité' ), true ) ) {
				continue;
			}
			$names = array_merge( array( tt_norm( $row[0] ) ), array_map( 'tt_norm', explode( ',', (string) get_post_meta( $row[4], 'tt_synonyms', true ) ) ) );
			$title = tt_norm( preg_replace( '/\s*:.*$/', '', $row[0] ) );
			if ( $q === $title || in_array( $q, $names, true ) ) {
				wp_safe_redirect( home_url( $row[1] ), 302 );
				exit;
			}
		}
	}
);
