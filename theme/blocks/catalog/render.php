<?php
/**
 * Catalogue d'activités : en-tête de page, raccourcis par type, filtres et grille.
 * Toutes les cartes sont dans le HTML (liens explorables) ; recherche, filtres et tri se font sans rechargement.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_post  = get_post( $block->context['postId'] ?? null );
$tt_items = tt_catalog_items();
if ( ! $tt_post || ! $tt_items ) {
	return;
}
$tt_kinds = tt_activity_kinds();
$tt_by_k  = array_count_values( wp_list_pluck( $tt_items, 'kind' ) );
$tt_by_d  = array_count_values( wp_list_pluck( $tt_items, 'dest' ) );
$tt_dests = count( array_diff( array_keys( $tt_by_d ), array( 'Thaïlande' ) ) );
$tt_h1    = (string) get_post_meta( $tt_post->ID, 'tt_h1', true ) ?: get_the_title( $tt_post );
$tt_intro = (string) get_post_meta( $tt_post->ID, 'tt_intro', true )
	?: sprintf( 'Visites, excursions, plongée, spectacles : %d activités détaillées dans %d destinations, avec nos conseils et les meilleures offres à réserver.', count( $tt_items ), $tt_dests );

// Image des raccourcis : celle choisie dans les réglages (« type:ID » par ligne), sinon la première activité du type.
$tt_tile_img = array();
foreach ( preg_split( '/[\s,]+/', $attributes['tiles'] ) as $tt_line ) {
	if ( preg_match( '/^([a-z]+):(\d+)$/', trim( $tt_line ), $tt_m ) ) {
		$tt_tile_img[ $tt_m[1] ] = (int) $tt_m[2];
	}
}
foreach ( $tt_items as $tt_it ) {
	if ( empty( $tt_tile_img[ $tt_it['kind'] ] ) && $tt_it['img'] ) {
		$tt_tile_img[ $tt_it['kind'] ] = $tt_it['img'];
	}
}

// Filtre destination : régions dans l'ordre, puis « Partout en Thaïlande ».
$tt_groups = array();
foreach ( $tt_items as $tt_it ) {
	$tt_groups[ $tt_it['region'] ?: 'Partout en Thaïlande' ][ $tt_it['dslug'] ] = $tt_it['dest'];
}
$tt_order  = array_merge( array_keys( tt_regions() ), array( 'Autres destinations', 'Partout en Thaïlande' ) );
$tt_groups = array_merge( array_intersect_key( array_flip( $tt_order ), $tt_groups ), $tt_groups );
$tt_label  = static fn( $d ) => 'Thaïlande' === $d ? 'Toute la Thaïlande' : $d;
$tt_empty  = tt_gyg_url( '__Q__', tt_cmp( 'catalogue-vide', $tt_post->ID ) );
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-catalog' ) ); ?> data-tt-cat>
	<header class="tt-catalog__head">
		<?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
			<?php yoast_breadcrumb( '<nav class="tt-crumb" aria-label="Fil d\'Ariane">', '</nav>' ); ?>
		<?php endif; ?>
		<h1><?php echo esc_html( $tt_h1 ); ?></h1>
		<p><?php echo esc_html( $tt_intro ); ?></p>
		<label class="tt-catalog__search">
			<span class="screen-reader-text">Rechercher une activité</span>
			<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			<input type="search" data-cat-q placeholder="<?php echo esc_attr( $attributes['placeholder'] ); ?>" autocomplete="off">
		</label>
	</header>

	<div class="tt-catalog__tiles" role="group" aria-label="Types d'activité">
		<?php foreach ( $tt_kinds as $tt_k => $tt_name ) : ?>
			<?php if ( empty( $tt_by_k[ $tt_k ] ) ) { continue; } ?>
			<button type="button" class="tt-catalog__tile" data-cat-tile="<?php echo esc_attr( $tt_k ); ?>" aria-pressed="false">
				<span class="tt-catalog__tileimg"><?php echo $tt_tile_img[ $tt_k ] ?? 0 ? wp_get_attachment_image( $tt_tile_img[ $tt_k ], 'tt-card', false, array( 'alt' => '', 'loading' => 'eager', 'sizes' => '(max-width: 900px) 40vw, 170px' ) ) : ''; // phpcs:ignore ?></span>
				<b><?php echo esc_html( $tt_name ); ?></b>
				<em><?php echo esc_html( sprintf( '%d activités', $tt_by_k[ $tt_k ] ) ); ?></em>
			</button>
		<?php endforeach; ?>
	</div>

	<div class="tt-catalog__body">
		<aside class="tt-catalog__side" id="tt-catalog-filters" aria-label="Filtres">
			<div class="tt-catalog__sidehead"><b>Filtrer</b><button type="button" class="tt-catalog__clear" data-cat-clear>Tout effacer</button><button type="button" class="tt-catalog__x" data-cat-close aria-label="Fermer les filtres">×</button></div>
			<div class="tt-catalog__sidebody">
				<fieldset>
					<legend>Destination</legend>
					<?php foreach ( $tt_groups as $tt_region => $tt_list ) : ?>
						<p class="tt-catalog__group"><?php echo esc_html( $tt_region ); ?></p>
						<?php asort( $tt_list ); ?>
						<?php foreach ( $tt_list as $tt_slug => $tt_dest ) : ?>
							<label class="tt-catalog__check"><input type="checkbox" name="d" value="<?php echo esc_attr( $tt_slug ); ?>" data-label="<?php echo esc_attr( $tt_label( $tt_dest ) ); ?>"><span><?php echo esc_html( $tt_label( $tt_dest ) ); ?></span><em><?php echo (int) $tt_by_d[ $tt_dest ]; ?></em></label>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</fieldset>
				<fieldset>
					<legend>Type d'activité</legend>
					<?php foreach ( $tt_kinds as $tt_k => $tt_name ) : ?>
						<?php if ( empty( $tt_by_k[ $tt_k ] ) ) { continue; } ?>
						<label class="tt-catalog__check"><input type="checkbox" name="k" value="<?php echo esc_attr( $tt_k ); ?>" data-label="<?php echo esc_attr( $tt_name ); ?>"><span><?php echo esc_html( $tt_name ); ?></span><em><?php echo (int) $tt_by_k[ $tt_k ]; ?></em></label>
					<?php endforeach; ?>
				</fieldset>
			</div>
			<div class="tt-catalog__sidefoot"><button type="button" class="tt-btn tt-btn--gold is-full" data-cat-close data-cat-apply><?php echo esc_html( sprintf( 'Voir les %d activités', count( $tt_items ) ) ); ?></button></div>
		</aside>

		<div class="tt-catalog__main">
			<div class="tt-catalog__bar">
				<p class="tt-catalog__count" data-cat-count aria-live="polite"><?php echo esc_html( sprintf( '%d activités', count( $tt_items ) ) ); ?></p>
				<div class="tt-catalog__chips" data-cat-chips></div>
				<a class="tt-catalog__maplink" href="<?php echo esc_url( home_url( tt_partners()['map_url'] ) ); ?>" data-cat-maplink><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6.5v13L9 17l6 2.5 6-2.5V4l-6 2.5L9 4Z"/><path d="M9 4v13M15 6.5v13"/></svg><span class="tt-catalog__ml">Voir sur la carte</span><span class="tt-catalog__ml is-short">Carte</span></a>
				<button type="button" class="tt-catalog__open" data-cat-open aria-controls="tt-catalog-filters" aria-expanded="false">Filtrer</button>
				<label class="tt-catalog__sort">Trier
					<select data-cat-sort>
						<option value="dest">Par destination</option>
						<option value="az">De A à Z</option>
						<option value="kind">Par type</option>
					</select>
				</label>
			</div>
			<h2 class="screen-reader-text">Toutes les activités</h2>
			<ul class="tt-catalog__grid">
				<?php foreach ( $tt_items as $tt_i => $tt_it ) : ?>
					<li class="tt-act" data-d="<?php echo esc_attr( $tt_it['dslug'] ); ?>" data-k="<?php echo esc_attr( $tt_it['kind'] ); ?>" data-ki="<?php echo (int) array_search( $tt_it['kind'], array_keys( $tt_kinds ), true ); ?>" data-s="<?php echo esc_attr( $tt_it['search'] ); ?>">
						<span class="tt-act__media">
							<?php echo $tt_it['img'] ? wp_get_attachment_image( $tt_it['img'], 'tt-card', false, array( 'alt' => '', 'loading' => 'lazy', 'sizes' => '(max-width: 600px) 92vw, (max-width: 1180px) 45vw, 300px' ) ) : ''; // phpcs:ignore ?>
							<span class="tt-tag"><?php echo esc_html( $tt_label( $tt_it['dest'] ) ); ?></span>
						</span>
						<div class="tt-act__body">
							<span class="tt-kicker"><?php echo esc_html( $tt_kinds[ $tt_it['kind'] ] ); ?></span>
							<h3 class="tt-act__title"><a href="<?php echo esc_url( $tt_it['url'] ); ?>"><?php echo esc_html( $tt_it['title'] ); ?></a></h3>
							<div class="tt-act__foot"><span class="tt-act__go">Voir l'activité</span></div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="tt-catalog__empty" hidden>
				<p><b>Aucune activité ne correspond.</b> Essaie une autre destination ou efface les filtres.</p>
				<a class="tt-btn tt-btn--gold" data-cat-offers data-href="<?php echo esc_url( $tt_empty ); ?>" href="<?php echo esc_url( tt_gyg_url( 'Thaïlande', tt_cmp( 'catalogue-vide', $tt_post->ID ) ) ); ?>" target="_blank" rel="sponsored nofollow noopener">Voir les offres en ligne</a>
			</div>
		</div>
	</div>
</section>
