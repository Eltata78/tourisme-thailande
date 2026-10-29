<?php
/**
 * Listes d'articles (blog, catégories, auteurs) : 12 articles par page pour des rangées pleines,
 * titres sans préfixe « Catégorie : », vignette de remplacement pour un article sans image.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( ! is_admin() && $q->is_main_query() && ( $q->is_home() || $q->is_category() || $q->is_tag() || $q->is_author() || $q->is_date() ) ) {
			$q->set( 'posts_per_page', 12 );
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
