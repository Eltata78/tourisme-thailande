<?php
/**
 * Bloc formulaire Trip.com.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_id  = (int) ( $block->context['postId'] ?? get_the_ID() );
$tt_ctx = tt_destination_context( $tt_id );
$tt_act = $tt_ctx ? null : tt_activity_context( $tt_id );
if ( $tt_act && $tt_act['trip'] ) {
	$tt_ctx = array( 'name' => $tt_act['place'], 'trip' => $tt_act['trip'] ); // activité : les hôtels de sa destination
}
$tt_uid = sanitize_key( $attributes['placement'] ) . '-' . substr( md5( wp_json_encode( $attributes ) ), 0, 4 );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-wdg-wrap is-' . $attributes['layout'] ) ); ?>>
	<?php
	echo tt_trip_form( // phpcs:ignore
		array(
			'uid'     => $tt_uid,
			'title'   => $attributes['title'] ?: ( $tt_ctx ? 'Trouve ton hôtel à ' . $tt_ctx['name'] : 'Trouve ton hôtel' ),
			'dest'    => $attributes['city'] ? tt_trip_key( $attributes['city'] ) : ( $tt_ctx['trip'] ?? '' ),
			'cmp'     => tt_cmp( $attributes['placement'], $tt_id ),
			'compact' => 'compact' === $attributes['layout'],
		)
	);
	?>
</div>
