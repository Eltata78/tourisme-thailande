<?php
/**
 * Colonnes de liens du pied de page : clé `footer` de tt_site(), [titre, [[libellé, adresse]…]]…
 * Rendu sans enveloppe : chaque entrée devient une colonne du bloc Colonnes qui entoure ce bloc.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

// Apostrophes laissées telles quelles : wptexturize les met en forme comme le reste du pied de page.
$tt_txt = static fn( $s ) => htmlspecialchars( (string) $s, ENT_NOQUOTES, 'UTF-8', false );

foreach ( (array) tt_site()['footer'] as $tt_col ) {
	list( $tt_title, $tt_links ) = array_values( (array) $tt_col ) + array( '', array() );
	echo "\n" . '<div class="wp-block-column is-layout-flow wp-block-column-is-layout-flow">' . "\n" . '<h4 class="wp-block-heading">' . $tt_txt( $tt_title ) . "</h4>\n\n\n" . '<ul class="wp-block-list">';
	foreach ( (array) $tt_links as $tt_link ) {
		list( $tt_label, $tt_url ) = array_values( (array) $tt_link ) + array( '', '' );
		echo '<li><a href="' . esc_url( $tt_url ) . '">' . $tt_txt( $tt_label ) . '</a></li>';
	}
	echo "</ul>\n</div>\n";
}
