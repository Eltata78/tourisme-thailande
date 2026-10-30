<?php
/**
 * Applique les pages activité converties (activites2blocks.py) sur le site courant.
 * Attend $tt_rows : liste de {id, slug, content, meta, thumbnail}.
 * Sauvegarde une seule fois le contenu Elementor, le gabarit et l'image à la une d'origine (_tt_backup_*).
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
		update_post_meta( $p->ID, '_tt_backup_thumb', (int) get_post_thumbnail_id( $p->ID ) );
	}
	$content = str_replace( 'https://tourisme-thailande.fr', $home, $r['content'] );
	$res     = wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $content ) ), true );
	if ( is_wp_error( $res ) ) {
		$out[] = array( $p->ID, $r['slug'], $res->get_error_message() );
		continue;
	}
	update_post_meta( $p->ID, '_wp_page_template', 'page-activite' );
	update_post_meta( $p->ID, '_elementor_edit_mode', '' );
	foreach ( $r['meta'] as $k => $v ) {
		update_post_meta( $p->ID, $k, is_string( $v ) ? wp_slash( $v ) : $v );
	}
	// Image à la une distincte par page : les grilles de pages les affichent côte à côte.
	if ( ! empty( $r['thumbnail'] ) && wp_attachment_is_image( (int) $r['thumbnail'] ) ) {
		set_post_thumbnail( $p->ID, (int) $r['thumbnail'] );
	}
	$out[] = array( $p->ID, $r['slug'], 'ok', strlen( $content ) );
}
delete_transient( 'tt_search_index_v1' );
return $out;
