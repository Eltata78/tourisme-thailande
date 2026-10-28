<?php
/**
 * Applique le contenu généré par build_content.py.
 *
 * Attend $tt_payload (tableau décodé) et $tt_mode ('local' | 'preprod').
 * Sur le preprod : met à jour les pages existantes (accueil #21, Bangkok #417) en gardant
 * une copie du contenu et des métadonnées Elementor pour pouvoir revenir en arrière.
 */

$log = array();

$tt_upsert_page = static function ( string $slug, string $title, string $content, int $id = 0 ) use ( &$log ): int {
	if ( ! $id ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		$id       = ( $existing && 'page' === $existing->post_type ) ? $existing->ID : 0;
	}
	if ( $id ) {
		if ( '' === (string) get_post_meta( $id, '_tt_backup_content', true ) ) {
			update_post_meta( $id, '_tt_backup_content', wp_slash( get_post_field( 'post_content', $id ) ) );
			update_post_meta( $id, '_tt_backup_elementor_mode', get_post_meta( $id, '_elementor_edit_mode', true ) );
			update_post_meta( $id, '_tt_backup_template', get_post_meta( $id, '_wp_page_template', true ) );
		}
		wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $content ) ) );
		$log[] = "mis à jour : $slug (#$id)";
	} else {
		$id    = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => wp_slash( $content ) ) );
		$log[] = "créé : $slug (#$id)";
	}
	// Elementor ne prend plus la main sur le rendu de la page (ses données restent en base).
	update_post_meta( $id, '_elementor_edit_mode', '' );
	return (int) $id;
};

// --- Accueil
$home_id = $tt_upsert_page( 'accueil', 'Tourisme-thailande.fr : la référence du tourisme en Thaïlande !', $tt_payload['home'], 'preprod' === $tt_mode ? 21 : 0 );
update_post_meta( $home_id, '_wp_page_template', '' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_id );

// --- Bangkok
$bkk_id = $tt_upsert_page( 'bangkok', 'Bangkok : guide complet de la ville', $tt_payload['bangkok'], 'preprod' === $tt_mode ? 417 : 0 );
wp_update_post( array( 'ID' => $bkk_id, 'post_title' => 'Bangkok : guide complet de la ville', 'post_excerpt' => $tt_payload['bangkok_intro'] ) );
update_post_meta( $bkk_id, '_wp_page_template', 'page-destination' );
update_post_meta( $bkk_id, 'tt_destination', 'Bangkok' );
update_post_meta( $bkk_id, 'tt_gyg_query', 'Bangkok' );
update_post_meta( $bkk_id, 'tt_trip_city', 359 );
update_post_meta( $bkk_id, 'tt_price_from', 27 );
update_post_meta( $bkk_id, 'tt_synonyms', 'bkk, krung thep, bangkok' );
if ( ! get_post_thumbnail_id( $bkk_id ) && $tt_payload['bangkok_thumb'] ) {
	set_post_thumbnail( $bkk_id, (int) $tt_payload['bangkok_thumb'] );
}

// --- Menu principal (refonte), à partir de la structure colonne par colonne
$menu_name = 'Menu principal (refonte)';
$menu      = wp_get_nav_menu_object( $menu_name );
if ( $menu ) {
	foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $old ) {
		wp_delete_post( $old->ID, true );
	}
	$menu_id = $menu->term_id;
} else {
	$menu_id = wp_create_nav_menu( $menu_name );
}
$add = static function ( array $args, int $parent = 0 ) use ( $menu_id ): int {
	$obj = null;
	if ( ! empty( $args['path'] ) && '#' !== $args['path'] ) {
		foreach ( array( 'page', 'post' ) as $pt ) {
			$found = get_page_by_path( trim( $args['path'], '/' ), OBJECT, $pt );
			if ( $found && $pt === $found->post_type && 'publish' === $found->post_status ) {
				$obj = $found;
				break;
			}
		}
	}
	$data = array(
		'menu-item-title'       => $args['title'],
		'menu-item-status'      => 'publish',
		'menu-item-parent-id'   => $parent,
		'menu-item-classes'     => $args['classes'] ?? '',
		'menu-item-description' => $args['desc'] ?? '',
	);
	if ( $obj ) {
		$data += array( 'menu-item-type' => 'post_type', 'menu-item-object' => $obj->post_type, 'menu-item-object-id' => $obj->ID );
	} else {
		$data += array( 'menu-item-type' => 'custom', 'menu-item-url' => ! empty( $args['url'] ) ? home_url( $args['url'] ) : '#' );
	}
	return (int) wp_update_nav_menu_item( $menu_id, 0, $data );
};
foreach ( $tt_payload['menu'] as $top ) {
	$top_id = $add( array( 'title' => $top['title'], 'url' => $top['url'], 'path' => $top['url'] ) );
	foreach ( $top['cols'] as $col ) {
		if ( ! empty( $col['feat'] ) ) {
			$add( array( 'title' => $col['title'], 'url' => $col['feat'], 'path' => $col['feat'], 'classes' => 'tt-feat', 'desc' => $col['desc'] ), $top_id );
			continue;
		}
		$col_id = $add( array( 'title' => $col['title'], 'url' => '#' ), $top_id );
		foreach ( $col['links'] as $l ) {
			$add( array( 'title' => $l['title'], 'url' => $l['url'], 'path' => $l['url'] ), $col_id );
		}
	}
}
$locs            = (array) get_theme_mod( 'nav_menu_locations', array() );
$locs['primary'] = $menu_id;
set_theme_mod( 'nav_menu_locations', $locs );
$log[] = 'menu : ' . count( wp_get_nav_menu_items( $menu_id ) ) . ' éléments';

delete_transient( 'tt_search_index_v1' );
return $log;
