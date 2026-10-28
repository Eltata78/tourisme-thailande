<?php
/**
 * Hero : image prioritaire (LCP), H1, accroche et formulaire de réservation.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_post  = get_post( $block->context['postId'] ?? null );
$tt_id    = $tt_post ? $tt_post->ID : 0;
$tt_img   = (int) $attributes['imageId'] ?: (int) get_post_thumbnail_id( $tt_id );
$tt_title = '' !== $attributes['title'] ? $attributes['title'] : ( $tt_post ? get_the_title( $tt_post ) : '' );
$tt_sub   = '' !== $attributes['subtitle'] ? $attributes['subtitle'] : (string) get_post_meta( $tt_id, 'tt_intro', true );
if ( '' === $tt_sub && $tt_post && has_excerpt( $tt_post ) ) {
	$tt_sub = get_the_excerpt( $tt_post );
}
$tt_ctx  = tt_destination_context( $tt_id );
$tt_home = 'home' === $attributes['variant'];

$tt_widget = '';
switch ( $attributes['widget'] ) {
	case 'search':
		$tt_widget = tt_search_form( 'hero' );
		break;
	case 'trip':
		$tt_widget = tt_trip_form(
			array(
				'uid'   => 'hero',
				'title' => $attributes['widgetTitle'] ?: ( $tt_ctx ? 'Ton hôtel à ' . $tt_ctx['name'] : 'Ton hôtel en Thaïlande' ),
				'city'  => $tt_ctx['trip'] ?? 0,
				'cmp'   => tt_cmp( 'hero', $tt_id ),
			)
		);
		break;
	case 'gyg':
		$tt_widget = tt_gyg_form(
			array(
				'uid'   => 'hero',
				'title' => $attributes['widgetTitle'] ?: ( $tt_ctx ? 'Que faire à ' . $tt_ctx['name'] . ' ?' : 'Trouve ton activité' ),
				'query' => $tt_ctx['gyg'] ?? get_the_title( $tt_id ),
				'cmp'   => tt_cmp( 'hero', $tt_id ),
			)
		);
		break;
}

$tt_classes = 'tt-hero tt-hero--' . ( $tt_home ? 'home' : 'page' );
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => $tt_classes ) ); ?>>
	<?php
	if ( $tt_img ) {
		echo wp_get_attachment_image(
			$tt_img,
			'tt-hero',
			false,
			array(
				'class'         => 'tt-hero__img',
				'alt'           => '',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
				'sizes'         => '100vw',
			)
		);
	}
	?>
	<div class="tt-hero__in">
		<?php if ( ! $tt_home && function_exists( 'yoast_breadcrumb' ) ) : ?>
			<?php yoast_breadcrumb( '<nav class="tt-crumb" aria-label="Fil d\'Ariane">', '</nav>' ); ?>
		<?php endif; ?>
		<div class="tt-hero__grid<?php echo ( $tt_widget && ! $tt_home ) ? ' has-widget' : ''; ?>">
			<div class="tt-hero__txt">
				<?php if ( $tt_home ) : ?>
					<h1><?php echo esc_html( $tt_title ); ?><?php if ( $tt_sub ) : ?> <span><?php echo esc_html( $tt_sub ); ?></span><?php endif; ?></h1>
					<?php echo $tt_widget; // phpcs:ignore ?>
				<?php else : ?>
					<h1><?php echo esc_html( $tt_title ); ?></h1>
					<?php if ( $tt_sub ) : ?><p><?php echo esc_html( $tt_sub ); ?></p><?php endif; ?>
					<?php if ( $attributes['photos'] ) : ?><a class="tt-hero__photos" href="#galerie">Voir les <?php echo (int) $attributes['photos']; ?> photos</a><?php endif; ?>
				<?php endif; ?>
			</div>
			<?php if ( $tt_widget && ! $tt_home ) : ?>
				<div class="tt-hero__box"><?php echo $tt_widget; // phpcs:ignore ?></div>
			<?php endif; ?>
		</div>
	</div>
</section>
