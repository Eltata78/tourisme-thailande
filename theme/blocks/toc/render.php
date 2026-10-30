<?php
/**
 * Sommaire automatique (H2 du contenu).
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_post = get_post( $block->context['postId'] ?? null );
if ( ! $tt_post ) {
	return;
}
$tt_items = array();
$tt_walk  = static function ( array $blocks ) use ( &$tt_walk, &$tt_items ) {
	foreach ( $blocks as $b ) {
		if ( 'core/heading' === $b['blockName'] && 2 === (int) ( $b['attrs']['level'] ?? 2 ) ) {
			$text = trim( wp_strip_all_tags( $b['innerHTML'] ) );
			$id   = preg_match( '/\sid="([^"]+)"/', $b['innerHTML'], $m ) ? $m[1] : tt_heading_id( $b['innerHTML'] );
			if ( '' !== $text ) {
				$tt_items[] = array( $id, html_entity_decode( $text ) );
			}
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$tt_walk( $b['innerBlocks'] );
		}
	}
};
$tt_walk( parse_blocks( $tt_post->post_content ) );
if ( count( $tt_items ) < 2 ) {
	return;
}
// Libellés courts : on retire la fin de titre répétée (ex. « à Bangkok »).
$tt_trim = array_filter( array_map( 'trim', explode( ',', $attributes['trim'] ) ) );
$tt_ctx  = tt_destination_context( $tt_post->ID );
$tt_act  = $tt_ctx ? null : tt_activity_context( $tt_post->ID );
if ( $tt_ctx || $tt_act ) {
	$tt_place  = $tt_ctx ? $tt_ctx['name'] : $tt_act['place'];
	$tt_trim[] = 'Thaïlande' === $tt_place ? 'en Thaïlande' : 'à ' . $tt_place;
	$tt_trim[] = 'dans ' . $tt_place;
}
// Sur une fiche, le nom du lieu aussi : « Comment se rendre à Wat Pho » -> « Comment se rendre ».
$tt_name = (string) get_post_meta( $tt_post->ID, 'tt_card_title', true );
if ( $tt_name && $tt_post->post_parent ) {
	foreach ( array( 'à ', 'au ', 'aux ', 'de ', 'du ', 'des ', 'sur ', 'pour ' ) as $tt_prep ) {
		$tt_trim[] = $tt_prep . $tt_name;
	}
}
?>
<nav <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-toc' ) ); ?> aria-label="<?php echo esc_attr( $attributes['title'] ); ?>" data-tt-toc>
	<p class="tt-toc__t"><?php echo esc_html( $attributes['title'] ); ?></p>
	<?php foreach ( $tt_items as $tt_i => $tt_item ) : ?>
		<?php
		$tt_label = preg_replace( '/\s*:\s.*$/u', '', $tt_item[1] ); // « Logistique : où dormir… » -> « Logistique »
		$tt_label = preg_replace( '/\?\s+\S.*$/u', '?', $tt_label ); // « D'où ça vient ? Une histoire… » -> « D'où ça vient ? »
		$tt_label = rtrim( str_ireplace( $tt_trim, '', $tt_label ), ' ?:' );
		?>
		<a href="#<?php echo esc_attr( $tt_item[0] ); ?>"<?php echo 0 === $tt_i ? ' class="is-on"' : ''; ?>><?php echo esc_html( $tt_label ); ?></a>
	<?php endforeach; ?>
</nav>
