<?php
/**
 * Applique les articles convertis (posts2blocks.py) sur le site courant.
 * Attend $tt_rows : liste de {id, slug, content, meta}. Le contenu d'origine est sauvegardé une seule fois (_tt_backup_content).
 */
$home = untrailingslashit( home_url() );
$out  = array();
foreach ( $tt_rows as $r ) {
	$p = get_post( (int) $r['id'] );
	if ( ! $p || 'post' !== $p->post_type || $p->post_name !== $r['slug'] ) {
		$out[] = array( $r['id'], $r['slug'], 'introuvable ou slug différent' );
		continue;
	}
	if ( '' === get_post_meta( $p->ID, '_tt_backup_content', true ) ) {
		update_post_meta( $p->ID, '_tt_backup_content', wp_slash( $p->post_content ) );
	}
	$content = str_replace( 'https://tourisme-thailande.fr', $home, $r['content'] );
	$res     = wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $content ) ), true );
	if ( is_wp_error( $res ) ) {
		$out[] = array( $p->ID, $r['slug'], $res->get_error_message() );
		continue;
	}
	foreach ( $r['meta'] as $k => $v ) {
		update_post_meta( $p->ID, $k, wp_slash( $v ) );
	}
	$out[] = array( $p->ID, $r['slug'], 'ok', strlen( $content ) );
}
return $out;
