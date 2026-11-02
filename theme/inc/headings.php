<?php
/**
 * Ancres automatiques sur les titres H2/H3, utilisées par le sommaire.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Identifiant d'ancre stable à partir d'un texte de titre.
 */
function tt_heading_id( string $text ): string {
	$id = sanitize_title( remove_accents( wp_strip_all_tags( html_entity_decode( $text ) ) ) );
	return substr( $id, 0, 60 );
}

add_filter(
	'render_block_core/heading',
	static function ( string $html, array $block ): string {
		$level = $block['attrs']['level'] ?? 2;
		if ( $level > 3 || preg_match( '/<h[1-6][^>]*\sid=/', $html ) ) {
			return $html;
		}
		$id = tt_heading_id( $html );
		if ( ! $id ) {
			return $html;
		}
		return preg_replace( '/<h([1-6])/', '<h$1 id="' . esc_attr( $id ) . '"', $html, 1 );
	},
	10,
	2
);
