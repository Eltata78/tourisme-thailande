<?php
/**
 * Articles : une carte d'activités (GetYourGuide) entre le premier et le deuxième intertitre H2, ajoutée à l'affichage.
 *
 * Vaut pour tous les articles, Content Machine compris, sans toucher à leur contenu. Rien n'est ajouté si l'article a déjà
 * un CTA d'activités avant cet endroit ; jamais collée à un autre CTA (bloc hôtels en fin de section : la carte remonte
 * après le premier paragraphe de la section). Sans H2 : après le troisième paragraphe. Suivi : emplacement « section-1 ».
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le bloc mène-t-il aux activités (bloc GetYourGuide ou lien vers getyourguide.* / gyg.me), y compris dans ses blocs internes ?
 */
function tt_block_has_gyg( array $block ): bool {
	if ( in_array( $block['blockName'], array( 'tt/cta-card', 'tt/gyg-card', 'tt/gyg-spotlight', 'tt/gyg-compare', 'tt/gyg-search' ), true )
		|| preg_match( '#getyourguide\.|gyg\.me#', (string) $block['innerHTML'] ) ) {
		return true;
	}
	foreach ( $block['innerBlocks'] as $inner ) {
		if ( tt_block_has_gyg( $inner ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Nombre d'intertitres H2 du bloc, y compris dans ses blocs internes (encadrés, groupes).
 */
function tt_block_h2_count( array $block ): int {
	$n = 'core/heading' === $block['blockName'] && 2 === (int) ( $block['attrs']['level'] ?? 2 ) ? 1 : 0;
	foreach ( $block['innerBlocks'] as $inner ) {
		$n += tt_block_h2_count( $inner );
	}
	return $n;
}

/**
 * Attributs de la carte : destination de l'article (requête des offres), photo d'une activité de cette destination.
 */
function tt_article_cta_attrs( int $post_id ): array {
	$query = (string) get_post_meta( $post_id, 'tt_gyg_query', true ) ?: 'Thaïlande';
	$items = array_values( array_filter( tt_catalog_items(), static fn( $it ) => $it['img'] ) );
	$here  = array_values( array_filter( $items, static fn( $it ) => 'Thaïlande' !== $it['dest'] && false !== mb_stripos( $query, $it['dest'] ) ) );
	$pool  = $here ?: $items;
	$dest  = $here ? $here[0]['dest'] : '';
	return array(
		'title'     => $dest ? 'En train de préparer ton séjour à ' . $dest . ' ?' : 'En train de préparer ton séjour en Thaïlande ?',
		'text'      => 'Visites, excursions et activités à réserver en ligne, la plupart avec annulation gratuite jusqu\'à 24 h avant.',
		'label'     => 'Voir les activités',
		'query'     => $query,
		'imageId'   => $pool ? (int) $pool[ $post_id % count( $pool ) ]['img'] : 0,
		'placement' => 'section-1',
	);
}

// Avant le rendu des blocs (do_blocks est à la priorité 9) : la carte est un vrai bloc, rendu comme les autres.
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_singular( 'post' ) || is_admin() || is_feed() || wp_is_json_request() || ! in_the_loop() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		$blocks = parse_blocks( $content );
		$real   = array_keys( array_filter( $blocks, static fn( $b ) => null !== $b['blockName'] ) );
		// Blocs de premier niveau qui portent un H2 (lui-même ou dans un encadré) : un index par H2 rencontré.
		$h2 = array();
		foreach ( $real as $i ) {
			$h2 = array_merge( $h2, array_fill( 0, tt_block_h2_count( $blocks[ $i ] ), $i ) );
		}
		$paras  = array_values( array_filter( $real, static fn( $i ) => 'core/paragraph' === $blocks[ $i ]['blockName'] ) );
		if ( isset( $h2[1] ) ) {
			$at = $h2[1];
		} elseif ( isset( $h2[0] ) ) {
			$at = count( $blocks );
		} elseif ( isset( $paras[2] ) ) {
			$at = $paras[2] + 1;
		} else {
			return $content;
		}
		foreach ( array_slice( $blocks, 0, $at ) as $b ) {
			if ( tt_block_has_gyg( $b ) ) {
				return $content; // un CTA d'activités arrive déjà avant
			}
		}
		// Jamais collée à un autre CTA : si le bloc qui précède en est un (hôtels…), après le premier paragraphe de la section.
		$before = array_values( array_filter( $real, static fn( $i ) => $i < $at ) );
		$prev   = $before ? $blocks[ end( $before ) ] : null;
		if ( $prev && str_starts_with( (string) $prev['blockName'], 'tt/' ) && isset( $h2[0] ) ) {
			$first = array_values( array_filter( $paras, static fn( $i ) => $i > $h2[0] && $i < $at ) );
			if ( ! $first ) {
				return $content;
			}
			$at = $first[0] + 1;
		}
		$cta = array( 'blockName' => 'tt/cta-card', 'attrs' => tt_article_cta_attrs( get_the_ID() ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
		array_splice( $blocks, $at, 0, array( $cta ) );
		return serialize_blocks( $blocks );
	},
	8
);
