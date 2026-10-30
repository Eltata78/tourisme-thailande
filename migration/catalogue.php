<?php
/**
 * Catalogue d'activités : type d'activité de chaque page (meta tt_kind), titres courts des cartes,
 * et page /activites-thailande/ (gabarit Catalogue d'activités). Idempotent : peut être rejoué.
 * À exécuter via execute-php sur le site cible.
 */
$kinds = array(
	'temples'     => array( 'wat-chaiwatthanaram-ayutthaya', 'wat-mahathat-ayutthaya', 'grand-palais-bangkok', 'rattanakosin', 'wat-arun', 'wat-pho', 'wat-phra-kaew',
		'lopburi-cite-singes-thailande', 'vieille-ville-temples-lanna', 'wat-phra-that-doi-suthep', 'wat-plai-laem', 'big-buddha-pattaya', 'sanctuaire-de-la-verite',
		'wat-yansangwararam', 'khao-chi-chan', 'big-buddha-wat-chalong-phuket', 'vieille-ville-phuket-old-town' ),
	'marches'     => array( 'asiatique-the-riverfront', 'marche-chatuchak', 'damnoen-saduak', 'marche-nocturne-chiang-mai', 'marche-flottant-pattaya' ),
	'iles'        => array( 'longtail-boat-ayutthaya', 'chao-phraya', 'ile-aux-cochons-koh-madsum-samui', 'koh-tan', 'parc-national-mu-ko-ang-thong', 'koh-larn',
		'excursion-iles-phi-phi', 'bateau-thailande', 'james-bond-island-ko-tapu' ),
	'nature'      => array( 'chutes-bua-tong', 'elephant-nature-park', 'parc-national-doi-inthanon', 'cascades-na-muang', 'jardin-de-nong-nooch', 'randonnee-thailande' ),
	'plongee'     => array( 'plongee-thailande', 'plongee-koh-phi-phi', 'plongee-koh-samui', 'plongee-koh-tao', 'plongee-krabi', 'plongee-phuket',
		'snorkeling-thailande', 'snorkeling-koh-phi-phi', 'snorkeling-koh-samui', 'requin-baleine-thailande' ),
	'sorties'     => array( 'chiang-mai-night-safari', 'phuket-fantasea', 'patong-bangla-road', 'simon-cabaret-phuket', 'ou-voir-muay-thai-bangkok', 'full-moon-party' ),
	'experiences' => array( 'muay-thai-ayutthaya', 'cours-de-cuisine-chiang-mai', 'skyline-adventure-chiang-mai', 'safari-world-bangkok', 'silverlake', 'yoga-thailande',
		'sak-yant-tatouage', 'surf-thailande' ),
);
// Titres courts : la destination est déjà sur la carte (étiquette), inutile de la répéter.
$titles = array(
	'cours-de-cuisine-chiang-mai'      => 'Cours de cuisine',
	'ile-aux-cochons-koh-madsum-samui' => 'Île aux cochons (Koh Madsum)',
	'sanctuaire-de-la-verite'          => 'Sanctuaire de la Vérité',
	'koh-larn'                         => 'Koh Larn',
	'silverlake'                       => 'Silverlake',
	'big-buddha-pattaya'               => 'Big Buddha',
	'skyline-adventure-chiang-mai'     => 'Skyline Adventure',
	'safari-world-bangkok'             => 'Safari World',
	'ou-voir-muay-thai-bangkok'        => 'Combat de Muay Thai',
	'sak-yant-tatouage'                => 'Tatouage Sak Yant',
	'requin-baleine-thailande'         => 'Nager avec le requin-baleine',
	'surf-thailande'                   => 'Surf',
	'full-moon-party'                  => 'Full Moon Party',
	'james-bond-island-ko-tapu'        => 'James Bond Island',
);
$find = static function ( $slug ) {
	$p = get_posts( array( 'post_type' => array( 'page', 'post' ), 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ) );
	return $p ? $p[0] : null;
};
$out = array( 'missing' => array() );
foreach ( $kinds as $kind => $slugs ) {
	foreach ( $slugs as $slug ) {
		$p = $find( $slug );
		if ( ! $p ) {
			$out['missing'][] = $slug;
			continue;
		}
		update_post_meta( $p->ID, 'tt_kind', $kind );
		$out[ $kind ] = ( $out[ $kind ] ?? 0 ) + 1;
	}
}
foreach ( $titles as $slug => $title ) {
	$p = $find( $slug );
	if ( $p ) {
		update_post_meta( $p->ID, 'tt_card_title', $title );
	}
}

// Page catalogue.
$content = '<!-- wp:tt/catalog {"tiles":"temples:1337 marches:1483 iles:1626 nature:2577 plongee:1562 sorties:3091 experiences:2684"} /-->'
	. "\n\n" . '<!-- wp:tt/gyg-search ' . wp_json_encode(
		array(
			'placement' => 'catalogue',
			'layout'    => 'band',
			'title'     => 'Tu sais déjà ce que tu veux faire ? Réserve directement',
			'choices'   => "Toutes les activités | Thaïlande\nPlongée | plongée Thaïlande\nExcursions en bateau | excursion bateau Thaïlande\nTemples et visites guidées | visite guidée temples Thaïlande\nSanctuaires d'éléphants | sanctuaire éléphants Thaïlande\nCours de cuisine | cours de cuisine Thaïlande",
		),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	) . ' /-->';
$page = get_page_by_path( 'activites-thailande' );
$data = array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Activités en Thaïlande',
	'post_name'    => 'activites-thailande',
	'post_content' => wp_slash( $content ),
	'post_parent'  => 0,
);
if ( $page ) {
	$data['ID'] = $page->ID;
}
$id = wp_insert_post( $data, true );
if ( is_wp_error( $id ) ) {
	return $id->get_error_message();
}
update_post_meta( $id, '_wp_page_template', 'page-catalogue' );
update_post_meta( $id, 'tt_gyg_query', 'Thaïlande' );
update_post_meta( $id, '_yoast_wpseo_title', 'Activités en Thaïlande : visites, excursions et plongée à réserver %%sep%% %%sitename%%' );
update_post_meta( $id, '_yoast_wpseo_metadesc', 'Toutes nos activités en Thaïlande : temples, marchés, îles, plongée, spectacles… Filtre par destination et par type, lis nos conseils et réserve au meilleur prix.' );
if ( ! has_post_thumbnail( $id ) ) {
	set_post_thumbnail( $id, 1626 );
}
delete_transient( 'tt_search_index_v1' );
$out['page'] = array( $id, get_permalink( $id ) );
return $out;
