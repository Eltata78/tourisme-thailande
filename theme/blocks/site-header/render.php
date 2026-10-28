<?php
/**
 * En-tête : logo, méga-menu, recherche, bouton de réservation.
 *
 * Structure attendue du menu « Menu principal » :
 * - niveau 1 : entrées de la barre ;
 * - niveau 2 : colonnes du méga-menu (le libellé sert de titre de colonne).
 *   Une entrée de niveau 2 avec la classe CSS `tt-feat` devient une carte image
 *   (image à la une de la page liée, sous-titre = description de l'élément) ;
 * - niveau 3 : liens de la colonne.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_tree  = array();
$tt_locs  = get_nav_menu_locations();
$tt_items = ! empty( $tt_locs['primary'] ) ? wp_get_nav_menu_items( $tt_locs['primary'] ) : array();
if ( $tt_items ) {
	_wp_menu_item_classes_by_context( $tt_items );
	$tt_by_id = array();
	foreach ( $tt_items as $tt_item ) {
		$tt_item->tt_children        = array();
		$tt_by_id[ $tt_item->ID ] = $tt_item;
	}
	foreach ( $tt_items as $tt_item ) {
		$tt_parent = (int) $tt_item->menu_item_parent;
		if ( $tt_parent && isset( $tt_by_id[ $tt_parent ] ) ) {
			$tt_by_id[ $tt_parent ]->tt_children[] = $tt_item;
		} elseif ( ! $tt_parent ) {
			$tt_tree[] = $tt_item;
		}
	}
}

$tt_chev   = '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m2.5 4.5 3.5 3.5 3.5-3.5"/></svg>';
$tt_search = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
$tt_burger = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';
$tt_cta    = tt_header_cta();

$tt_link = static function ( $item ): string {
	return sprintf( '<a href="%s"%s>%s</a>', esc_url( $item->url ), $item->current ? ' aria-current="page"' : '', esc_html( $item->title ) );
};

$tt_feat = static function ( $item ): string {
	$img = ( 'post_type' === $item->type && $item->object_id ) ? get_the_post_thumbnail( (int) $item->object_id, 'tt-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '';
	return sprintf(
		'<a class="tt-feat" href="%s">%s<span class="t"><b>%s</b><span>%s</span></span></a>',
		esc_url( $item->url ),
		$img ? '<span class="ph">' . $img . '</span>' : '',
		esc_html( $item->title ),
		esc_html( $item->description )
	);
};
?>
<header <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-header' ) ); ?>>
	<div class="tt-header__bar">
		<a class="tt-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			$tt_logo = (int) get_theme_mod( 'custom_logo' );
			echo $tt_logo
				? wp_get_attachment_image( $tt_logo, 'medium', false, array( 'alt' => get_bloginfo( 'name' ), 'class' => 'tt-logo', 'fetchpriority' => 'high', 'loading' => 'eager' ) )
				: '<span class="tt-logo-text">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
			?>
		</a>
		<?php if ( $tt_tree ) : ?>
		<nav class="tt-nav" aria-label="Menu principal">
			<?php foreach ( $tt_tree as $tt_top ) : ?>
				<div class="tt-nav__item<?php echo $tt_top->tt_children ? ' has-mega' : ''; ?>">
					<a class="tt-nav__top" href="<?php echo esc_url( $tt_top->url ); ?>"<?php echo $tt_top->tt_children ? ' aria-haspopup="true"' : ''; ?>><?php echo esc_html( $tt_top->title ); ?><?php echo $tt_top->tt_children ? $tt_chev : ''; // phpcs:ignore ?></a>
					<?php if ( $tt_top->tt_children ) : ?>
						<div class="tt-mega"><div class="tt-mega__in">
							<?php
							foreach ( $tt_top->tt_children as $tt_col ) {
								if ( in_array( 'tt-feat', (array) $tt_col->classes, true ) ) {
									echo $tt_feat( $tt_col ); // phpcs:ignore
									continue;
								}
								echo '<div class="tt-mega__col"><p class="tt-mega__h">' . esc_html( $tt_col->title ) . '</p><ul' . ( count( $tt_col->tt_children ) > 7 ? ' class="two"' : '' ) . '>';
								foreach ( $tt_col->tt_children as $tt_l ) {
									echo '<li>' . $tt_link( $tt_l ) . '</li>'; // phpcs:ignore
								}
								echo '</ul></div>';
							}
							?>
						</div></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
		<div class="tt-header__actions">
			<button type="button" class="tt-ico" data-tt-toggle="tt-search-panel" aria-expanded="false" aria-controls="tt-search-panel" aria-label="Rechercher"><?php echo $tt_search; // phpcs:ignore ?></button>
			<?php if ( $tt_tree ) : ?>
			<button type="button" class="tt-ico tt-burger" data-tt-toggle="tt-drawer" aria-expanded="false" aria-controls="tt-drawer" aria-label="Menu"><?php echo $tt_burger; // phpcs:ignore ?></button>
			<?php endif; ?>
			<a class="tt-btn tt-btn--gold tt-header__cta" href="<?php echo esc_url( $tt_cta['url'] ); ?>"><?php echo esc_html( $tt_cta['label'] ); ?></a>
		</div>
	</div>
	<div class="tt-search-panel" id="tt-search-panel" hidden>
		<?php echo tt_search_form( 'header' ); // phpcs:ignore ?>
	</div>
	<?php if ( $tt_tree ) : ?>
	<div class="tt-drawer" id="tt-drawer" hidden>
		<?php foreach ( $tt_tree as $tt_top ) : ?>
			<?php if ( ! $tt_top->tt_children ) : ?>
				<a class="tt-drawer__link" href="<?php echo esc_url( $tt_top->url ); ?>"><?php echo esc_html( $tt_top->title ); ?></a>
			<?php else : ?>
				<details>
					<summary><?php echo esc_html( $tt_top->title ); ?></summary>
					<ul>
						<?php
						foreach ( $tt_top->tt_children as $tt_col ) {
							$tt_links = $tt_col->tt_children ? $tt_col->tt_children : array( $tt_col );
							foreach ( $tt_links as $tt_l ) {
								echo '<li>' . $tt_link( $tt_l ) . '</li>'; // phpcs:ignore
							}
						}
						?>
					</ul>
				</details>
			<?php endif; ?>
		<?php endforeach; ?>
		<a class="tt-btn tt-btn--gold" href="<?php echo esc_url( $tt_cta['url'] ); ?>"><?php echo esc_html( $tt_cta['label'] ); ?></a>
	</div>
	<?php endif; ?>
</header>
