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
$tt_title = '' !== $attributes['title'] ? $attributes['title'] : ( $tt_post ? ( (string) get_post_meta( $tt_id, 'tt_h1', true ) ?: get_the_title( $tt_post ) ) : '' );
$tt_sub   = '' !== $attributes['subtitle'] ? $attributes['subtitle'] : (string) get_post_meta( $tt_id, 'tt_intro', true );
if ( '' === $tt_sub && $tt_post && has_excerpt( $tt_post ) ) {
	$tt_sub = get_the_excerpt( $tt_post );
}
// L'accroche reprend souvent la première phrase du chapeau : inutile de la lire deux fois de suite.
if ( '' === $attributes['subtitle'] && '' !== $tt_sub && $tt_post ) {
	$tt_norm  = static fn( $s ) => preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES, 'UTF-8' ) );
	$tt_start = $tt_norm( mb_substr( $tt_post->post_content, 0, 2500 ) );
	if ( false !== mb_strpos( $tt_start, mb_substr( $tt_norm( $tt_sub ), 0, 40 ) ) ) {
		$tt_sub = '';
	}
}
$tt_ctx  = tt_destination_context( $tt_id );
$tt_act  = $tt_ctx ? null : tt_activity_context( $tt_id );
$tt_home = 'home' === $attributes['variant'];

// Pas de destination hôtels connue : on propose les activités à la place.
$tt_widget_type = $attributes['widget'];
if ( 'trip' === $tt_widget_type && $tt_ctx && ! $tt_ctx['trip'] ) {
	$tt_widget_type = 'gyg';
}

$tt_widget = '';
switch ( $tt_widget_type ) {
	case 'search':
		$tt_widget = tt_search_form( 'hero' );
		break;
	case 'trip':
		$tt_widget = tt_trip_form(
			array(
				'uid'   => 'hero',
				'title' => $attributes['widgetTitle'] ?: ( $tt_ctx ? 'Ton hôtel à ' . $tt_ctx['name'] : 'Ton hôtel en Thaïlande' ),
				'dest'  => $tt_ctx['trip'] ?? '',
				'cmp'   => tt_cmp( 'hero', $tt_id ),
			)
		);
		break;
	case 'gyg':
		$tt_widget = tt_gyg_form(
			array(
				'uid'   => 'hero',
				'title'   => $attributes['widgetTitle'] ?: ( $tt_ctx ? 'Que faire à ' . $tt_ctx['name'] . ' ?' : 'Trouve ton activité' ),
				'query'   => $tt_ctx['gyg'] ?? ( $tt_act['gyg'] ?? get_the_title( $tt_id ) ),
				'choices' => $tt_act ? tt_activity_choices( $tt_post ) : array(),
				'cmp'     => tt_cmp( 'hero', $tt_id ),
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
					<h1<?php echo mb_strlen( $tt_title ) > 26 ? ' class="is-long"' : ''; ?>><?php echo esc_html( $tt_title ); ?></h1>
					<?php if ( $tt_sub ) : ?><p><?php echo esc_html( $tt_sub ); ?></p><?php endif; ?>
				<?php endif; ?>
			</div>
			<?php if ( $tt_widget && ! $tt_home ) : ?>
				<div class="tt-hero__box"><?php echo $tt_widget; // phpcs:ignore ?></div>
			<?php endif; ?>
		</div>
	</div>
</section>
