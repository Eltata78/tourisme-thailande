<?php
/**
 * Comparateur d'activités.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_items = json_decode( (string) $attributes['items'], true );
if ( ! is_array( $tt_items ) || count( $tt_items ) < 2 ) {
	return;
}
$tt_id   = (int) ( $block->context['postId'] ?? get_the_ID() );
$tt_cmp  = tt_cmp( $attributes['placement'], $tt_id );
$tt_crit = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $attributes['criteria'] ) ) ) );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-cmp' ) ); ?>>
	<?php if ( $attributes['title'] ) : ?><p class="tt-cmp__t"><?php echo esc_html( $attributes['title'] ); ?></p><?php endif; ?>
	<div class="tt-cmp__grid" style="--n:<?php echo (int) min( 4, count( $tt_items ) ); ?>">
		<?php foreach ( array_slice( $tt_items, 0, 4 ) as $tt_it ) : ?>
			<?php
			$tt_best = ! empty( $tt_it['best'] );
			$tt_url  = ! empty( $tt_it['url'] ) ? tt_gyg_tag( $tt_it['url'], $tt_cmp ) : tt_gyg_url( $tt_it['query'] ?? $tt_it['name'], $tt_cmp );
			?>
			<div class="tt-cmp__col<?php echo $tt_best ? ' is-best' : ''; ?>">
				<?php if ( $tt_best ) : ?><span class="tt-cmp__best">Le plus réservé</span><?php endif; ?>
				<span class="tt-cmp__media"><?php echo ! empty( $tt_it['imageId'] ) ? wp_get_attachment_image( (int) $tt_it['imageId'], 'tt-card', false, array( 'loading' => 'lazy', 'alt' => '' ) ) : ''; // phpcs:ignore ?></span>
				<b><?php echo esc_html( $tt_it['name'] ?? '' ); ?></b>
				<?php foreach ( $tt_crit as $tt_i => $tt_c ) : ?>
					<div class="tt-cmp__row"><span><?php echo esc_html( $tt_c ); ?></span><span><?php echo esc_html( $tt_it['values'][ $tt_i ] ?? '' ); ?></span></div>
				<?php endforeach; ?>
				<?php if ( ! empty( $tt_it['price'] ) ) : ?><p class="tt-cmp__price">dès <b><?php echo esc_html( $tt_it['price'] ); ?> €</b></p><?php endif; ?>
				<a class="tt-btn <?php echo $tt_best ? 'tt-btn--gold' : 'tt-btn--out'; ?>" href="<?php echo esc_url( $tt_url ); ?>" target="_blank" rel="sponsored nofollow noopener">Voir l'offre</a>
			</div>
		<?php endforeach; ?>
	</div>
</div>
