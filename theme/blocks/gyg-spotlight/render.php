<?php
/**
 * Activité coup de cœur.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

if ( '' === $attributes['title'] ) {
	return;
}
$tt_id     = (int) ( $block->context['postId'] ?? get_the_ID() );
$tt_cmp    = tt_cmp( $attributes['placement'], $tt_id );
$tt_url    = $attributes['url'] ? tt_gyg_tag( $attributes['url'], $tt_cmp ) : tt_gyg_url( $attributes['query'] ?: $attributes['title'], $tt_cmp );
$tt_points = array_filter( array_map( 'trim', preg_split( '/\R/', $attributes['points'] ) ) );
$tt_facts  = array();
foreach ( array_filter( array_map( 'trim', preg_split( '/\R/', $attributes['facts'] ) ) ) as $tt_line ) {
	$tt_parts = array_map( 'trim', explode( '|', $tt_line, 2 ) );
	if ( 2 === count( $tt_parts ) ) {
		$tt_facts[] = $tt_parts;
	}
}
$tt_check = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m3 8.5 3 3 7-7"/></svg>';
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-spot' ) ); ?>>
	<div class="tt-spot__media">
		<?php echo $attributes['imageId'] ? wp_get_attachment_image( (int) $attributes['imageId'], 'large', false, array( 'loading' => 'lazy', 'alt' => '' ) ) : ''; // phpcs:ignore ?>
		<?php if ( $attributes['kicker'] ) : ?><span class="tt-spot__badge"><?php echo esc_html( $attributes['kicker'] ); ?></span><?php endif; ?>
	</div>
	<div class="tt-spot__body">
		<p class="tt-spot__title"><?php echo esc_html( $attributes['title'] ); ?></p>
		<?php if ( $attributes['rating'] ) : ?>
			<span class="tt-stars">★★★★★<em><?php echo esc_html( $attributes['rating'] . ( $attributes['reviews'] ? ' (' . $attributes['reviews'] . ' avis)' : '' ) ); ?></em></span>
		<?php endif; ?>
		<?php if ( $tt_points ) : ?>
			<ul class="tt-check"><?php foreach ( $tt_points as $tt_pt ) : ?><li><?php echo $tt_check; // phpcs:ignore ?><span><?php echo esc_html( $tt_pt ); ?></span></li><?php endforeach; ?></ul>
		<?php endif; ?>
		<?php if ( $tt_facts ) : ?>
			<dl class="tt-spot__facts"><?php foreach ( $tt_facts as $tt_f ) : ?><div><dt><?php echo esc_html( $tt_f[0] ); ?></dt><dd><?php echo esc_html( $tt_f[1] ); ?></dd></div><?php endforeach; ?></dl>
		<?php endif; ?>
		<div class="tt-spot__foot">
			<?php if ( $attributes['price'] ) : ?><p class="tt-spot__price"><span>dès</span> <b><?php echo esc_html( $attributes['price'] ); ?> €</b><span> / personne</span></p><?php endif; ?>
			<div class="tt-spot__btns">
				<a class="tt-btn tt-btn--gold" href="<?php echo esc_url( $tt_url ); ?>" target="_blank" rel="sponsored nofollow noopener">Voir les disponibilités</a>
				<?php if ( $attributes['guideUrl'] ) : ?><a class="tt-spot__guide" href="<?php echo esc_url( $attributes['guideUrl'] ); ?>"><?php echo esc_html( $attributes['guideLabel'] ?: 'Lire notre guide' ); ?></a><?php endif; ?>
			</div>
		</div>
	</div>
</div>
