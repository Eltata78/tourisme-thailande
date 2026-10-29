<?php
/**
 * Barre de réservation mobile.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_id  = (int) ( $block->context['postId'] ?? get_the_ID() );
$tt_ctx = tt_destination_context( $tt_id );
$tt_q   = $tt_ctx ? '' : (string) get_post_meta( $tt_id, 'tt_gyg_query', true ); // article : sa destination, ou « Thaïlande »
$tt_cmp = tt_cmp( 'mbar', $tt_id );

if ( $attributes['url'] ) {
	$tt_url = str_contains( $attributes['url'], 'getyourguide.' ) ? tt_gyg_tag( $attributes['url'], $tt_cmp ) : $attributes['url'];
} elseif ( $attributes['query'] || $tt_ctx || $tt_q ) {
	$tt_url = tt_gyg_url( $attributes['query'] ?: ( $tt_ctx['gyg'] ?? $tt_q ), $tt_cmp );
} else {
	$tt_url = tt_header_cta()['url'];
}
$tt_label = $attributes['label'] ?: ( $tt_ctx ? 'Activités à ' . $tt_ctx['name'] : ( $tt_q ? ( 'Thaïlande' === $tt_q ? 'Activités en Thaïlande' : 'Activités à ' . $tt_q ) : tt_partners()['cta_default_label'] ) );
$tt_sub   = $attributes['sub'] ?: ( $tt_ctx && $tt_ctx['price'] ? sprintf( 'Annulation gratuite · dès %s €', number_format_i18n( $tt_ctx['price'] ) ) : 'Annulation gratuite possible' );
$tt_btn   = $attributes['button'] ?: 'Voir les activités';
$tt_ext   = ! str_starts_with( $tt_url, home_url() );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-mbar' ) ); ?> data-tt-mbar>
	<p><b><?php echo esc_html( $tt_label ); ?></b><span><?php echo esc_html( $tt_sub ); ?></span></p>
	<a class="tt-btn tt-btn--gold" href="<?php echo esc_url( $tt_url ); ?>"<?php echo $tt_ext ? ' target="_blank" rel="sponsored nofollow noopener"' : ''; ?>><?php echo esc_html( $tt_btn ); ?></a>
</div>
