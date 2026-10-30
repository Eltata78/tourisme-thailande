<?php
/**
 * Suggestions quand on ne trouve pas : destinations, catalogue, blog, et offres pour la recherche tapée.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_hubs = get_pages(
	array(
		'parent'      => 0,
		'meta_key'    => 'tt_destination', // phpcs:ignore WordPress.DB.SlowDBQuery
		'sort_column' => 'menu_order,post_title',
	)
);
$tt_q    = is_search() ? trim( get_search_query( false ) ) : '';
$tt_cat  = get_page_by_path( 'activites-thailande' );
$tt_blog = (int) get_option( 'page_for_posts' );
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-suggest' ) ); ?>>
	<?php if ( $tt_hubs ) : ?>
		<h2>Nos destinations</h2>
		<ul class="tt-suggest__chips">
			<?php foreach ( $tt_hubs as $tt_h ) : ?>
				<li><a href="<?php echo esc_url( get_permalink( $tt_h ) ); ?>"><?php echo esc_html( get_post_meta( $tt_h->ID, 'tt_destination', true ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<div class="tt-suggest__actions">
		<?php if ( $tt_cat ) : ?><a class="tt-btn tt-btn--indigo" href="<?php echo esc_url( get_permalink( $tt_cat ) ); ?>">Toutes les activités</a><?php endif; ?>
		<?php if ( $tt_blog ) : ?><a class="tt-btn tt-btn--out" href="<?php echo esc_url( get_permalink( $tt_blog ) ); ?>">Le blog</a><?php endif; ?>
		<?php if ( '' !== $tt_q ) : ?>
			<a class="tt-btn tt-btn--gold" href="<?php echo esc_url( tt_gyg_url( $tt_q, tt_cmp( 'recherche-vide' ) ) ); ?>" target="_blank" rel="sponsored nofollow noopener"><?php echo esc_html( sprintf( 'Voir les offres « %s »', $tt_q ) ); ?></a>
		<?php endif; ?>
	</div>
</section>
