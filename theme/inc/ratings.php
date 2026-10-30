<?php
/**
 * Notes des lecteurs (Rate My Post) sur les pages destination, lieux et activités, comme sur les articles.
 *
 * - Widget de notation en fin de page : le plugin gère le vote, l'anti-doublon et les données structurées
 *   (CreativeWorkSeries avec aggregateRating, dès le premier vote, comme sur les articles). Textes adaptés à un guide.
 * - Note moyenne sous le titre (hero), dès le premier vote, qui mène au widget.
 * - Données structurées : la note porte sur le guide (ce que les lecteurs notent), relié au lieu décrit (about).
 * - Scripts du plugin chargés seulement là où le widget s'affiche (inc/cleanup.php) ; demande de note au retour
 *   d'un clic partenaire : front.js.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page notée : destination ou une de ses sous-pages (lieux, visites), ou page activité. Ni catalogue ni carte.
 *
 * @param int|WP_Post|null $post Page (par défaut, celle en cours).
 */
function tt_rating_page( $post = null ): bool {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return false;
	}
	$tpl = get_page_template_slug( $post );
	if ( in_array( $tpl, array( 'page-catalogue', 'page-carte' ), true ) ) {
		return false;
	}
	return null !== tt_destination_context( $post->ID ) || 'page-activite' === $tpl || '' !== (string) get_post_meta( $post->ID, 'tt_kind', true );
}

/**
 * Note moyenne et nombre de votes, ou null sans vote.
 *
 * @return array{0:float,1:int}|null
 */
function tt_rating( int $post_id ): ?array {
	$count = (int) get_post_meta( $post_id, 'rmp_vote_count', true );
	if ( $count < 1 ) {
		return null;
	}
	$avg = class_exists( 'Rate_My_Post_Common' ) ? (float) Rate_My_Post_Common::get_average_rating( $post_id ) : (float) get_post_meta( $post_id, 'rmp_rating_val_sum', true ) / $count;
	return array( round( $avg, 1 ), $count );
}

// Widget de notation à la fin du contenu des pages notées.
add_filter(
	'render_block_core/post-content',
	static function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? 0 );
		if ( ! is_page() || $id !== get_queried_object_id() || ! tt_rating_page( $id ) || ! shortcode_exists( 'ratemypost' ) ) {
			return $html;
		}
		return $html . '<section class="tt-rating" id="tt-rating">' . do_shortcode( '[ratemypost id="' . $id . '"]' ) . '</section>';
	},
	10,
	3
);

// Textes du widget sur ces pages : on note un guide.
add_filter(
	'rmp_custom_strings',
	static function ( $strings, $post_id ) {
		if ( $post_id && tt_rating_page( (int) $post_id ) ) {
			$strings['rateTitle']    = 'Ce guide t’a été utile ?';
			$strings['rateSubtitle'] = 'Clique sur une étoile pour le noter.';
		}
		return $strings;
	},
	10,
	2
);

// Données structurées du plugin, sur ces pages : adresse et description du guide, lieu décrit.
add_filter(
	'rmp_raw_structured_data',
	static function ( $schema, $post_id ) {
		if ( ! tt_rating_page( (int) $post_id ) ) {
			return $schema;
		}
		$schema['url'] = get_permalink( $post_id );
		$desc          = (string) get_post_meta( $post_id, 'tt_intro', true ) ?: (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( '' !== $desc && false === strpos( $desc, '%%' ) ) {
			$schema['description'] = wp_strip_all_tags( $desc );
		}
		$about = tt_place_schema()['about'] ?? '';
		if ( $about ) {
			$schema['about'] = array( '@id' => $about );
		}
		return $schema;
	},
	10,
	2
);
