<?php
/**
 * En-tête des pages simples, des résultats de recherche et de la 404 : fil d'Ariane, titre, accroche.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_title = $attributes['title'];
$tt_intro = $attributes['intro'];
if ( is_search() ) {
	global $wp_query;
	$tt_n     = (int) $wp_query->found_posts;
	$tt_title = $tt_title ?: sprintf( 'Résultats pour « %s »', get_search_query( false ) );
	$tt_intro = $tt_intro ?: ( $tt_n
		? sprintf( _n( '%s guide ou page correspond à ta recherche.', '%s guides et pages correspondent à ta recherche.', $tt_n, 'tt' ), number_format_i18n( $tt_n ) )
		: 'Aucun guide ne correspond à cette recherche. Essaie un autre mot, ou pars de nos destinations.' );
} elseif ( is_404() ) {
	$tt_title = $tt_title ?: 'Cette page n\'existe plus';
	$tt_intro = $tt_intro ?: 'Elle a peut-être changé d\'adresse. Cherche une destination, une visite ou un guide :';
} else {
	$tt_post  = get_post( $block->context['postId'] ?? null );
	$tt_title = $tt_title ?: ( $tt_post ? ( (string) get_post_meta( $tt_post->ID, 'tt_h1', true ) ?: get_the_title( $tt_post ) ) : '' );
	$tt_intro = $tt_intro ?: ( $tt_post ? (string) get_post_meta( $tt_post->ID, 'tt_intro', true ) : '' );
}
?>
<header <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-pagehead' ) ); ?>>
	<?php if ( ! is_404() && function_exists( 'yoast_breadcrumb' ) ) : ?>
		<?php yoast_breadcrumb( '<nav class="tt-crumb" aria-label="Fil d\'Ariane">', '</nav>' ); ?>
	<?php endif; ?>
	<h1><?php echo esc_html( $tt_title ); ?></h1>
	<?php if ( $tt_intro ) : ?><p><?php echo esc_html( $tt_intro ); ?></p><?php endif; ?>
	<?php if ( $attributes['search'] ) : ?>
		<div class="tt-pagehead__search"><?php echo tt_search_form( 'head' ); // phpcs:ignore ?></div>
	<?php endif; ?>
</header>
