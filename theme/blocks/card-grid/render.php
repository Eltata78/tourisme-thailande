<?php
/**
 * Grille de cartes vers des pages du site, toujours à jour (pas de liste figée dans le contenu).
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_post = get_post( $block->context['postId'] ?? null );
if ( ! $tt_post ) {
	return;
}
$tt_args = array(
	'post_type'      => 'page',
	'post_status'    => 'publish',
	'posts_per_page' => (int) $attributes['limit'],
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'no_found_rows'  => true,
);
switch ( $attributes['mode'] ) {
	case 'siblings':
		$tt_args['post_parent']  = $tt_post->post_parent;
		$tt_args['post__not_in'] = array( $tt_post->ID );
		break;
	case 'destinations':
		$tt_args['post_parent']  = 0;
		$tt_args['meta_key']     = 'tt_destination'; // phpcs:ignore WordPress.DB.SlowDBQuery
		$tt_args['meta_compare'] = 'EXISTS';
		$tt_args['post__not_in'] = array( $tt_post->ID, $tt_post->post_parent );
		break;
	case 'ids':
		$tt_args['post__in'] = array_filter( array_map( 'absint', explode( ',', $attributes['ids'] ) ) );
		$tt_args['orderby']  = 'post__in';
		break;
	default:
		$tt_args['post_parent'] = $tt_post->ID;
}
if ( 'siblings' === $attributes['mode'] && ! $tt_post->post_parent ) {
	return;
}
$tt_pages = get_posts( $tt_args );
if ( ! $tt_pages ) {
	return;
}
$tt_cols = max( 2, min( 6, (int) $attributes['columns'] ) );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-cards2', 'style' => '--cols:' . $tt_cols ) ); ?>>
	<?php foreach ( $tt_pages as $tt_p ) : ?>
		<?php
		$tt_title = get_post_meta( $tt_p->ID, 'tt_card_title', true ) ?: get_the_title( $tt_p );
		$tt_dest  = 'destinations' === $attributes['mode'] ? (string) get_post_meta( $tt_p->ID, 'tt_destination', true ) : '';
		?>
		<a class="tt-card2" href="<?php echo esc_url( get_permalink( $tt_p ) ); ?>">
			<span class="tt-card2__media"><?php echo get_the_post_thumbnail( $tt_p, 'tt-card', array( 'loading' => 'lazy', 'alt' => '' ) ); // phpcs:ignore ?></span>
			<b><?php echo esc_html( $tt_dest ?: $tt_title ); ?></b>
		</a>
	<?php endforeach; ?>
</div>
