<?php
/**
 * Applique les pages destination converties (elementor2blocks.py) sur le site courant.
 * Attend $tt_rows : liste de {id, slug, title, content, meta, set_thumbnail}.
 * Sauvegarde le contenu Elementor d'origine une seule fois (_tt_backup_*), pour pouvoir revenir en arrière.
 */
$home = untrailingslashit( home_url() );
$out  = array();
foreach ( $tt_rows as $r ) {
	$p = get_post( (int) $r['id'] );
	if ( ! $p || 'page' !== $p->post_type || $p->post_name !== $r['slug'] ) {
		$out[] = array( $r['id'], $r['slug'], 'introuvable ou slug différent' );
		continue;
	}
	if ( '' === get_post_meta( $p->ID, '_tt_backup_content', true ) ) {
		update_post_meta( $p->ID, '_tt_backup_content', wp_slash( $p->post_content ) );
		update_post_meta( $p->ID, '_tt_backup_elementor_mode', get_post_meta( $p->ID, '_elementor_edit_mode', true ) );
		update_post_meta( $p->ID, '_tt_backup_template', get_post_meta( $p->ID, '_wp_page_template', true ) );
		update_post_meta( $p->ID, '_tt_backup_title', $p->post_title );
	}
	$content = str_replace( 'https://tourisme-thailande.fr', $home, $r['content'] );
	$data    = array( 'ID' => $p->ID, 'post_content' => wp_slash( $content ) );
	if ( ! empty( $r['title'] ) ) {
		$data['post_title'] = $r['title'];
	}
	$res = wp_update_post( $data, true );
	if ( is_wp_error( $res ) ) {
		$out[] = array( $p->ID, $r['slug'], $res->get_error_message() );
		continue;
	}
	update_post_meta( $p->ID, '_wp_page_template', 'page-destination' );
	update_post_meta( $p->ID, '_elementor_edit_mode', '' );
	foreach ( $r['meta'] as $k => $v ) {
		update_post_meta( $p->ID, $k, is_string( $v ) ? wp_slash( $v ) : $v );
	}
	if ( ! empty( $r['set_thumbnail'] ) && ! has_post_thumbnail( $p->ID ) ) {
		set_post_thumbnail( $p->ID, (int) $r['set_thumbnail'] );
	}
	$out[] = array( $p->ID, $r['slug'], 'ok', strlen( $content ) );
}
delete_transient( 'tt_search_index_v1' );
return $out;
