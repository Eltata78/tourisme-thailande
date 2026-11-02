<?php
/**
 * Rubriques du blog : « Tous les articles » puis les catégories non vides, hors catégorie par défaut.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_current = is_category() ? get_queried_object_id() : 0;
$tt_blog    = get_option( 'page_for_posts' ) ? get_permalink( (int) get_option( 'page_for_posts' ) ) : home_url( '/' );
$tt_cats    = get_categories(
	array(
		'hide_empty' => true,
		'exclude'    => array( (int) get_option( 'default_category' ) ),
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
if ( ! $tt_cats ) {
	return;
}
?>
<nav <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-topics' ) ); ?> aria-label="Rubriques du blog">
	<a href="<?php echo esc_url( $tt_blog ); ?>"<?php echo $tt_current || ! is_home() ? '' : ' class="is-on" aria-current="page"'; ?>>Tous les articles</a>
	<?php foreach ( $tt_cats as $tt_cat ) : ?>
		<a href="<?php echo esc_url( get_category_link( $tt_cat ) ); ?>"<?php echo $tt_current === $tt_cat->term_id ? ' class="is-on" aria-current="page"' : ''; ?>><?php echo esc_html( $tt_cat->name ); ?></a>
	<?php endforeach; ?>
</nav>
