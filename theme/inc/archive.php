<?php
/**
 * Listes d'articles (blog, catégories, auteurs) : grilles de 4 colonnes aux rangées pleines,
 * titres sans préfixe « Catégorie : », vignette de remplacement pour un article sans image.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

// Grilles de 4 colonnes. Blog et catégories : la page 1 met le dernier article en grand (2 × 2) suivi de 12 articles,
// soit 4 rangées pleines ; les pages suivantes en montrent 16. D'où le décalage calculé à partir de la page 2.
const TT_PER_PAGE   = 16;
const TT_FIRST_PAGE = 13;

add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_author() ) {
			$q->set( 'posts_per_page', TT_PER_PAGE );
		} elseif ( $q->is_home() || $q->is_category() || $q->is_tag() || $q->is_date() ) {
			$paged = max( 1, (int) $q->get( 'paged' ) );
			$q->set( 'posts_per_page', 1 === $paged ? TT_FIRST_PAGE : TT_PER_PAGE );
			if ( $paged > 1 ) {
				$q->set( 'offset', TT_FIRST_PAGE + ( $paged - 2 ) * TT_PER_PAGE );
			}
			$q->set( 'tt_feature', true );
		}
	}
);

// Nombre de pages recalculé : 13 articles sur la première, 16 sur les autres.
add_action(
	'wp',
	static function () {
		global $wp_query;
		if ( $wp_query->get( 'tt_feature' ) ) {
			$wp_query->max_num_pages = 1 + (int) ceil( max( 0, $wp_query->found_posts - TT_FIRST_PAGE ) / TT_PER_PAGE );
		}
	}
);

add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

add_filter(
	'render_block_core/post-featured-image',
	static function ( string $html, array $block, $instance ): string {
		if ( '' !== trim( $html ) || empty( $instance->context['postId'] ) ) {
			return $html;
		}
		$id = (int) $instance->context['postId'];
		return sprintf(
			'<figure class="wp-block-post-featured-image tt-noimg"><a href="%s" tabindex="-1" aria-hidden="true"></a></figure>',
			esc_url( get_permalink( $id ) )
		);
	},
	10,
	3
);
