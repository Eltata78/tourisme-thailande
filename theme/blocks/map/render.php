<?php
/**
 * Carte des visites : repères des pages de la destination, rendus par MapLibre (auto-hébergé) à l'approche du bloc.
 *
 * Sur un hub : ses fiches (sous-pages). Sur une fiche : elle et ses voisines, centrée sur elle.
 * Une fiche sans coordonnées (activité sans lieu fixe) n'a pas de repère ; un hub sans fiche n'affiche pas de carte.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_post = get_post( $block->context['postId'] ?? null );
if ( ! $tt_post ) {
	return;
}
$tt_map   = tt_map_places( $tt_post );
$tt_hub   = $tt_map['hub'];
$tt_cmp   = tt_cmp( $attributes['placement'], $tt_post->ID );
$tt_ctx   = tt_destination_context( $tt_post->ID );

$tt_pins = array();
foreach ( $tt_map['places'] as $tt_p ) {
	$tt_geo   = tt_geo( $tt_p->ID );
	$tt_self  = $tt_p->ID === $tt_post->ID;
	$tt_link  = (string) get_post_meta( $tt_p->ID, 'tt_gyg_url', true );
	$tt_query = (string) get_post_meta( $tt_p->ID, 'tt_gyg_query', true ) ?: ( $tt_ctx['gyg'] ?? get_the_title( $tt_p ) );
	$tt_pins[] = array(
		'lat' => $tt_geo[0],
		'lng' => $tt_geo[1],
		't'   => (string) get_post_meta( $tt_p->ID, 'tt_card_title', true ) ?: get_the_title( $tt_p ),
		'u'   => $tt_self ? '' : get_permalink( $tt_p ),
		// Lien d'affiliation de l'activité s'il est renseigné sur la fiche, sinon la recherche de l'activité.
		'g'   => $tt_link ? tt_gyg_tag( $tt_link, $tt_cmp ) : tt_gyg_url( $tt_query, $tt_cmp ),
		'b'   => $tt_link ? 'Voir les disponibilités' : 'Voir les offres',
		'i'   => (string) get_the_post_thumbnail_url( $tt_p, 'tt-card' ),
		'm'   => (string) get_the_post_thumbnail_url( $tt_p, 'thumbnail' ),
		'c'   => $tt_self,
	);
}
if ( ! $tt_pins ) {
	return;
}
// Fond de carte vectoriel OpenFreeMap : libre, sans clé, usage commercial autorisé, noms français disponibles.
$tt_cfg = array(
	'pins'   => $tt_pins,
	'center' => tt_geo( $tt_hub->ID ),
	'zoom'   => (int) $attributes['zoom'],
	'fit'    => $tt_hub->ID === $tt_post->ID, // hub : cadrer sur l'ensemble des repères
	'style'  => apply_filters( 'tt_map_style', 'https://tiles.openfreemap.org/styles/positron' ),
);
$tt_name = $tt_ctx['name'] ?? get_the_title( $tt_hub );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-map' ) ); ?> data-tt-map="<?php echo esc_attr( wp_json_encode( $tt_cfg ) ); ?>" role="region" aria-label="<?php echo esc_attr( 'Carte des visites à ' . $tt_name ); ?>"></div>
