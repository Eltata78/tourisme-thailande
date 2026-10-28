<?php
/**
 * Carte des visites : repères des pages de la destination, rendus par MapLibre (auto-hébergé) à l'approche du bloc.
 *
 * Sur un hub : ses sous-pages. Sur une sous-page : la page et ses voisines, centrée sur la page.
 * Une page sans coordonnées (activité sans lieu fixe) n'a pas de repère.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_post = get_post( $block->context['postId'] ?? null );
if ( ! $tt_post ) {
	return;
}
$tt_kids  = get_pages( array( 'parent' => $tt_post->ID, 'sort_column' => 'menu_order,post_title' ) );
$tt_hub   = $tt_kids || ! $tt_post->post_parent ? $tt_post : get_post( $tt_post->post_parent );
$tt_pages = $tt_kids ?: ( $tt_post->post_parent ? get_pages( array( 'parent' => $tt_post->post_parent, 'sort_column' => 'menu_order,post_title' ) ) : array( $tt_post ) );
$tt_cmp   = tt_cmp( $attributes['placement'], $tt_post->ID );
$tt_ctx   = tt_destination_context( $tt_post->ID );

$tt_pins = array();
foreach ( $tt_pages as $tt_p ) {
	$tt_geo = tt_geo( $tt_p->ID );
	if ( ! $tt_geo ) {
		continue;
	}
	$tt_self  = $tt_p->ID === $tt_post->ID;
	$tt_query = (string) get_post_meta( $tt_p->ID, 'tt_gyg_query', true ) ?: ( $tt_ctx['gyg'] ?? get_the_title( $tt_p ) );
	$tt_pins[] = array(
		'lat' => $tt_geo[0],
		'lng' => $tt_geo[1],
		't'   => (string) get_post_meta( $tt_p->ID, 'tt_card_title', true ) ?: get_the_title( $tt_p ),
		'u'   => $tt_self ? '' : get_permalink( $tt_p ),
		'g'   => tt_gyg_url( $tt_query, $tt_cmp ),
		'b'   => $tt_kids || $tt_p->post_parent ? 'Voir les offres' : 'Voir les activités',
		'i'   => (string) get_the_post_thumbnail_url( $tt_p, 'tt-card' ),
		'c'   => $tt_self,
	);
}
// Lieux cités par le guide du hub (sans page dédiée) : un repère par lieu, à la place du repère unique de la destination.
$tt_extra = $tt_hub->ID === $tt_post->ID ? (array) json_decode( (string) get_post_meta( $tt_post->ID, 'tt_map_pins', true ), true ) : array();
if ( $tt_extra && ! $tt_kids ) {
	$tt_pins = array();
}
if ( $tt_extra ) {
	foreach ( $tt_extra as $tt_x ) {
		if ( isset( $tt_x['t'], $tt_x['lat'], $tt_x['lng'] ) ) {
			$tt_pins[] = array(
				'lat' => (float) $tt_x['lat'],
				'lng' => (float) $tt_x['lng'],
				't'   => (string) $tt_x['t'],
				'u'   => '',
				'g'   => tt_gyg_url( (string) ( $tt_x['q'] ?? $tt_x['t'] ), $tt_cmp ),
				'b'   => 'Voir les activités',
				'i'   => '',
				'c'   => false,
			);
		}
	}
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
