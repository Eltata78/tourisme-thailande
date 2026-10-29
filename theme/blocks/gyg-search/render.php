<?php
/**
 * Bloc formulaire GetYourGuide.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_id    = (int) ( $block->context['postId'] ?? get_the_ID() );
$tt_ctx   = tt_destination_context( $tt_id );
$tt_query = $tt_ctx['gyg'] ?? ( (string) get_post_meta( $tt_id, 'tt_gyg_query', true ) ?: 'Thaïlande' );
$tt_uid   = sanitize_key( $attributes['placement'] ) . '-' . substr( md5( wp_json_encode( $attributes ) ), 0, 4 );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-wdg-wrap is-' . $attributes['layout'] ) ); ?>>
	<?php
	echo tt_gyg_form( // phpcs:ignore
		array(
			'uid'     => $tt_uid,
			'title'   => $attributes['title'] ?: ( $tt_ctx ? 'Que faire à ' . $tt_ctx['name'] . ' ?' : 'Trouve ton activité' ),
			'query'   => $tt_query,
			'choices' => tt_parse_choices( $attributes['choices'], $tt_query ),
			'cmp'     => tt_cmp( $attributes['placement'], $tt_id ),
			'compact' => 'compact' === $attributes['layout'],
		)
	);
	?>
</div>
