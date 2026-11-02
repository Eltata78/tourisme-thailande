<?php
/**
 * Mode carte : coordonnées des activités du catalogue qui n'en avaient pas (géocodées avec OpenStreetMap,
 * ou point de départ habituel de l'activité), et page /carte-thailande/ (gabarit Carte des activités).
 * Les activités « partout en Thaïlande » (yoga, randonnée, surf…) restent sans repère. Rejouable.
 */
$geo = array(
	'cours-de-cuisine-chiang-mai' => '18.7905,98.9925',   // vieille ville, où partent les cours
	'longtail-boat-ayutthaya'     => '14.3449,100.5788',  // embarcadère du Wat Phanan Choeng
	'muay-thai-ayutthaya'         => '14.3560,100.5650',
	'ou-voir-muay-thai-bangkok'   => '13.7610,100.5088',  // stade Rajadamnern
	'full-moon-party'             => '9.6756,100.0656',   // Haad Rin
	'plongee-koh-samui'           => '9.5340,100.0620',   // Chaweng
	'snorkeling-koh-samui'        => '9.3880,99.9700',    // Koh Tan et Koh Madsum
	'plongee-koh-tao'             => '10.0973,99.8278',   // Sairee
	'plongee-koh-phi-phi'         => '7.7392,98.7694',    // Ton Sai
	'snorkeling-koh-phi-phi'      => '7.6766,98.7661',    // Maya Bay
	'plongee-krabi'               => '8.0300,98.8190',    // Ao Nang
	'james-bond-island-ko-tapu'   => '8.2746,98.5009',    // Khao Phing Kan
	'plongee-phuket'              => '7.8197,98.3505',    // embarcadère de Chalong
);
$out = array( 'missing' => array() );
foreach ( $geo as $slug => $ll ) {
	$p = get_posts( array( 'post_type' => array( 'page', 'post' ), 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ) );
	if ( ! $p ) {
		$out['missing'][] = $slug;
		continue;
	}
	update_post_meta( $p[0]->ID, 'tt_geo', $ll );
}
$page = get_page_by_path( 'carte-thailande' );
$data = array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Carte des activités en Thaïlande',
	'post_name'    => 'carte-thailande',
	'post_content' => '<!-- wp:tt/explorer /-->',
	'post_parent'  => 0,
);
if ( $page ) {
	$data['ID'] = $page->ID;
}
$id = wp_insert_post( $data, true );
if ( is_wp_error( $id ) ) {
	return $id->get_error_message();
}
update_post_meta( $id, '_wp_page_template', 'page-carte' );
update_post_meta( $id, 'tt_h1', 'Carte des activités' );
update_post_meta( $id, 'tt_gyg_query', 'Thaïlande' );
update_post_meta( $id, '_yoast_wpseo_title', 'Carte des activités en Thaïlande : visites, plongée, îles %%sep%% %%sitename%%' );
update_post_meta( $id, '_yoast_wpseo_metadesc', 'Toutes nos activités en Thaïlande sur une seule carte : temples, marchés, îles, plongée, spectacles. Filtre par type, zoome sur une destination et réserve en ligne.' );
if ( ! has_post_thumbnail( $id ) ) {
	set_post_thumbnail( $id, 1626 );
}
delete_transient( 'tt_search_index_v1' );
$out['page'] = array( $id, get_permalink( $id ) );
return $out;
