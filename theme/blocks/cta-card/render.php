<?php
/**
 * Carte de réservation : photo, accroche et bouton vers l'offre partenaire.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_id    = (int) ( $block->context['postId'] ?? get_the_ID() );
$tt_ctx   = tt_destination_context( $tt_id );
$tt_cmp   = tt_cmp( $attributes['placement'], $tt_id );
$tt_query = $attributes['query'] ?: ( $tt_ctx['gyg'] ?? get_the_title( $tt_id ) );
$tt_url   = $attributes['url'] ? tt_gyg_tag( $attributes['url'], $tt_cmp ) : tt_gyg_url( $tt_query, $tt_cmp );
$tt_img   = (int) $attributes['imageId'] ?: (int) get_post_thumbnail_id( $tt_id );
$tt_title = $attributes['title'] ?: ( $tt_ctx ? 'Les activités à faire à ' . $tt_ctx['name'] : 'Les activités à réserver' );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-cta' . ( $tt_img ? '' : ' is-plain' ) ) ); ?>>
	<?php if ( $tt_img ) : ?>
		<div class="tt-cta__media"><?php echo wp_get_attachment_image( $tt_img, 'tt-card', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?></div>
	<?php endif; ?>
	<div class="tt-cta__body">
		<?php if ( $attributes['kicker'] ) : ?><p class="tt-cta__kicker"><?php echo esc_html( $attributes['kicker'] ); ?></p><?php endif; ?>
		<p class="tt-cta__title"><?php echo esc_html( $tt_title ); ?></p>
		<?php if ( $attributes['text'] ) : ?><p class="tt-cta__text"><?php echo esc_html( $attributes['text'] ); ?></p><?php endif; ?>
		<a class="tt-btn tt-btn--gold" href="<?php echo esc_url( $tt_url ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php echo esc_html( $attributes['label'] ?: 'Voir les activités' ); ?></a>
	</div>
</div>
