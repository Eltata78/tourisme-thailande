<?php
/**
 * Cohabitation avec Elementor pendant la migration.
 *
 * Tant qu'Elementor est actif, ses modèles Theme Builder (article, archives…) ne doivent plus
 * remplacer les templates du thème, et ses scripts ne se chargent que sur les pages encore
 * construites avec lui.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'elementor/theme/need_override_location', '__return_false' );

/**
 * La page courante est-elle encore rendue par Elementor ?
 */
function tt_is_elementor_page(): bool {
	return is_singular() && 'builder' === get_post_meta( get_queried_object_id(), '_elementor_edit_mode', true );
}

/**
 * Retire les scripts et styles Elementor / ElementsKit des pages qui n'en ont plus besoin.
 * Exécuté plusieurs fois : Elementor ajoute certains fichiers tardivement (widgets, polices).
 */
function tt_dequeue_builder_assets(): void {
	if ( is_admin() || tt_is_elementor_page() ) {
		return;
	}
	$prefixes = array( 'elementor', 'ekit', 'elementskit', 'e-', 'widget-', 'swiper', 'hello-plus', 'ehp-', 'magnific-popup', 'imagesloaded' );
	foreach ( array( wp_scripts(), wp_styles() ) as $deps ) {
		foreach ( (array) $deps->queue as $handle ) {
			foreach ( $prefixes as $p ) {
				if ( str_starts_with( $handle, $p ) ) {
					$deps instanceof WP_Scripts ? wp_dequeue_script( $handle ) : wp_dequeue_style( $handle );
					break;
				}
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'tt_dequeue_builder_assets', 999 );
add_action( 'wp_print_styles', 'tt_dequeue_builder_assets', 0 );
add_action( 'wp_print_footer_scripts', 'tt_dequeue_builder_assets', 0 );
add_action( 'wp_footer', 'tt_dequeue_builder_assets', 0 );
