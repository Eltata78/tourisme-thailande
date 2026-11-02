<?php
/**
 * Carte activité GetYourGuide.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

if ( '' === $attributes['title'] ) {
	return;
}
$tt_id  = (int) ( $block->context['postId'] ?? get_the_ID() );
$tt_cmp = tt_cmp( $attributes['placement'], $tt_id );
$tt_url = '' !== $attributes['url'] ? tt_gyg_tag( $attributes['url'], $tt_cmp ) : tt_gyg_url( $attributes['query'] ?: $attributes['title'], $tt_cmp );
$tt_img = $attributes['imageId']
	? wp_get_attachment_image( (int) $attributes['imageId'], 'tt-card', false, array( 'loading' => 'lazy', 'alt' => '' ) )
	: ( $attributes['imageUrl'] ? sprintf( '<img src="%s" alt="" loading="lazy" width="640" height="480">', esc_url( $attributes['imageUrl'] ) ) : '' );
?>
<article <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-act' ) ); ?>>
	<a class="tt-act__media" href="<?php echo esc_url( $tt_url ); ?>" target="_blank" rel="sponsored nofollow noopener" tabindex="-1" aria-hidden="true">
		<?php echo $tt_img; // phpcs:ignore ?>
		<?php if ( $attributes['place'] ) : ?><span class="tt-tag"><?php echo esc_html( $attributes['place'] ); ?></span><?php endif; ?>
	</a>
	<div class="tt-act__body">
		<?php if ( $attributes['kind'] ) : ?><span class="tt-kicker"><?php echo esc_html( $attributes['kind'] ); ?></span><?php endif; ?>
		<h3 class="tt-act__title"><a href="<?php echo esc_url( $tt_url ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php echo esc_html( $attributes['title'] ); ?></a></h3>
		<?php if ( $attributes['rating'] ) : ?>
			<span class="tt-stars" aria-label="<?php echo esc_attr( 'Note ' . $attributes['rating'] . ' sur 5' ); ?>">★★★★★<em><?php echo esc_html( $attributes['rating'] . ( $attributes['reviews'] ? ' (' . $attributes['reviews'] . ')' : '' ) ); ?></em></span>
		<?php endif; ?>
		<?php if ( $attributes['meta'] ) : ?><span class="tt-act__meta"><?php echo esc_html( $attributes['meta'] ); ?></span><?php endif; ?>
		<div class="tt-act__foot">
			<span><?php echo $attributes['price'] ? 'dès <b>' . esc_html( $attributes['price'] ) . ' €</b>' : 'Prix du jour'; ?></span>
			<a href="<?php echo esc_url( $tt_url ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php echo '' !== $attributes['url'] ? "Voir l'offre" : 'Voir les offres'; ?></a>
		</div>
	</div>
</article>
