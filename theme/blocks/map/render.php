<?php
/**
 * Carte au clic : façade légère, l'iframe Google Maps n'est chargée qu'à la demande.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_post = get_post( $block->context['postId'] ?? null );
$tt_ctx  = $tt_post ? tt_destination_context( $tt_post->ID ) : null;
$tt_addr = trim( $attributes['address'] ) ?: ( $tt_post ? wp_strip_all_tags( get_the_title( $tt_post ) ) . ( $tt_ctx && ! str_contains( get_the_title( $tt_post ), $tt_ctx['name'] ) ? ', ' . $tt_ctx['name'] : '' ) : '' );
if ( '' === $tt_addr ) {
	return;
}
$tt_q     = $tt_addr . ( str_contains( strtolower( remove_accents( $tt_addr ) ), 'thailand' ) ? '' : ', Thaïlande' );
$tt_embed = add_query_arg( array( 'q' => rawurlencode( $tt_q ), 'z' => (int) $attributes['zoom'], 'output' => 'embed' ), 'https://maps.google.com/maps' );
$tt_open  = add_query_arg( array( 'api' => 1, 'query' => rawurlencode( $tt_q ) ), 'https://www.google.com/maps/search/' );
$tt_label = $attributes['label'] ?: 'Situer ' . $tt_addr . ' sur la carte';
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-map' ) ); ?> data-tt-map="<?php echo esc_url( $tt_embed ); ?>">
	<div class="tt-map__face">
		<svg class="tt-map__pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
		<p class="tt-map__t"><?php echo esc_html( $tt_label ); ?></p>
		<div class="tt-map__actions">
			<button type="button" class="tt-btn tt-btn--indigo" data-tt-map-load>Afficher la carte</button>
			<a class="tt-map__ext" href="<?php echo esc_url( $tt_open ); ?>" target="_blank" rel="noopener">Ouvrir dans Google Maps</a>
		</div>
	</div>
</div>
