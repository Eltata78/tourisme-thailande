<?php
/**
 * Mode carte : toutes les activités géolocalisées du catalogue sur une grande carte de la Thaïlande.
 * La liste des activités est dans le HTML (liens explorables) ; la carte, les filtres et la synchronisation
 * liste/carte se font dans front.js. Les filtres reprennent ceux du catalogue (?k=type&d=destination).
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
$tt_cmp   = tt_cmp( $attributes['placement'], $tt_post->ID );
$tt_geo   = array_values( array_filter( $tt_items, static fn( $it ) => null !== $it['geo'] ) );
$tt_other = array_values( array_filter( $tt_items, static fn( $it ) => null === $it['geo'] ) );
$tt_by_k  = array_count_values( wp_list_pluck( $tt_geo, 'kind' ) );
$tt_h1    = (string) get_post_meta( $tt_post->ID, 'tt_h1', true ) ?: get_the_title( $tt_post );
$tt_label = static fn( $d ) => 'Thaïlande' === $d ? 'Toute la Thaïlande' : $d;

// Lien d'affiliation de l'activité s'il est renseigné, sinon la recherche de l'activité (suivi par emplacement).
$tt_offer = static fn( $it, $cmp ) => $it['gyg'] ? tt_gyg_tag( $it['gyg'], $cmp ) : tt_gyg_url( $it['query'], $cmp );
// Description courte : l'accroche de la page, sinon le début de l'extrait de l'article.
$tt_desc = static function ( $it ) {
	$d = (string) get_post_meta( $it['id'], 'tt_intro', true );
	if ( '' === $d ) {
		$d = wp_trim_words( html_entity_decode( wp_strip_all_tags( get_the_excerpt( $it['id'] ) ), ENT_QUOTES, 'UTF-8' ), 30 );
	}
	return $d;
};
$tt_cmp_list = tt_cmp( $attributes['placement'] . '-liste', $tt_post->ID );
$tt_pins     = array();
foreach ( $tt_geo as $tt_it ) {
	$tt_pins[] = array(
		'id'  => $tt_it['id'],
		'lat' => $tt_it['geo'][0],
		'lng' => $tt_it['geo'][1],
		't'   => $tt_it['title'],
		'u'   => $tt_it['url'],
		'g'   => $tt_offer( $tt_it, $tt_cmp ),
		'b'   => $tt_it['gyg'] ? 'Voir les disponibilités' : 'Voir les offres',
		'i'   => $tt_it['img'] ? (string) wp_get_attachment_image_url( $tt_it['img'], 'tt-card' ) : '',
		'm'   => $tt_it['img'] ? (string) wp_get_attachment_image_url( $tt_it['img'], 'thumbnail' ) : '',
		'k'   => $tt_it['kind'],
		'd'   => $tt_it['dslug'],
	);
}
// Accès rapide : les destinations qui ont des activités sur la carte, cadrées sur leurs repères.
$tt_dests = array();
foreach ( $tt_geo as $tt_it ) {
	$tt_dests[ $tt_it['dslug'] ] = $tt_it['dest'];
}
asort( $tt_dests );
$tt_cfg = array(
	'pins'  => $tt_pins,
	'style' => apply_filters( 'tt_map_style', 'https://tiles.openfreemap.org/styles/positron' ),
);
$tt_list = get_page_by_path( 'activites-thailande' );
// Ligne de la liste : en-tête cliquable qui déplie photo, description, article et réservation.
$tt_row = static function ( $it ) use ( $tt_kinds, $tt_label, $tt_desc, $tt_offer, $tt_cmp_list ) {
	$panel = 'tt-ex-' . (int) $it['id'];
	?>
	<button type="button" class="tt-explorer__head" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel ); ?>">
		<span class="tt-explorer__thumb"><?php echo $it['img'] ? wp_get_attachment_image( $it['img'], 'thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ) : ''; // phpcs:ignore ?></span>
		<span class="tt-explorer__txt">
			<small><?php echo esc_html( $tt_kinds[ $it['kind'] ] . ' · ' . $tt_label( $it['dest'] ) ); ?></small>
			<b><?php echo esc_html( $it['title'] ); ?></b>
		</span>
	</button>
	<div class="tt-explorer__more" id="<?php echo esc_attr( $panel ); ?>" hidden>
		<?php if ( $it['img'] ) : ?>
			<?php echo wp_get_attachment_image( $it['img'], 'tt-card', false, array( 'alt' => '', 'loading' => 'lazy', 'class' => 'tt-explorer__photo', 'sizes' => '(max-width: 900px) 92vw, 310px' ) ); // phpcs:ignore ?>
		<?php endif; ?>
		<?php $d = $tt_desc( $it ); ?>
		<?php if ( $d ) : ?><p><?php echo esc_html( $d ); ?></p><?php endif; ?>
		<div class="tt-explorer__acts">
			<a class="tt-btn tt-btn--out" href="<?php echo esc_url( $it['url'] ); ?>">Voir notre article</a>
			<a class="tt-btn tt-btn--gold" href="<?php echo esc_url( $tt_offer( $it, $tt_cmp_list ) ); ?>" target="_blank" rel="sponsored nofollow noopener">Réserver</a>
		</div>
	</div>
	<?php
};
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-explorer' ) ); ?> data-tt-explorer="<?php echo esc_attr( wp_json_encode( $tt_cfg ) ); ?>">
	<div class="tt-explorer__bar">
		<h1><?php echo esc_html( $tt_h1 ); ?></h1>
		<div class="tt-explorer__chips" role="group" aria-label="Type d'activité">
			<button type="button" data-ex-kind="" aria-pressed="true">Tout <em><?php echo count( $tt_geo ); ?></em></button>
			<?php foreach ( $tt_kinds as $tt_k => $tt_name ) : ?>
				<?php if ( empty( $tt_by_k[ $tt_k ] ) ) { continue; } ?>
				<button type="button" data-ex-kind="<?php echo esc_attr( $tt_k ); ?>" aria-pressed="false"><?php echo esc_html( $tt_name ); ?> <em><?php echo (int) $tt_by_k[ $tt_k ]; ?></em></button>
			<?php endforeach; ?>
		</div>
		<div class="tt-explorer__tools">
			<label class="tt-explorer__go"><span>Aller à</span>
				<select data-ex-dest>
					<option value="">Toute la Thaïlande</option>
					<?php foreach ( $tt_dests as $tt_slug => $tt_dest ) : ?>
						<option value="<?php echo esc_attr( $tt_slug ); ?>"><?php echo esc_html( $tt_dest ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( $tt_list ) : ?>
				<a class="tt-explorer__listlink" href="<?php echo esc_url( get_permalink( $tt_list ) ); ?>" data-ex-listlink>Vue liste</a>
			<?php endif; ?>
		</div>
	</div>
	<div class="tt-explorer__body">
		<aside class="tt-explorer__side" id="tt-ex-list" aria-label="Activités de la zone affichée">
			<p class="tt-explorer__count" data-ex-count aria-live="polite"><?php echo esc_html( sprintf( '%d activités sur la carte', count( $tt_geo ) ) ); ?></p>
			<ul class="tt-explorer__list">
				<?php foreach ( $tt_geo as $tt_it ) : ?>
					<li data-ex-id="<?php echo (int) $tt_it['id']; ?>"><?php $tt_row( $tt_it ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $tt_other ) : ?>
				<div class="tt-explorer__other">
					<p>Partout en Thaïlande</p>
					<ul class="tt-explorer__list">
						<?php foreach ( $tt_other as $tt_it ) : ?>
							<li data-ex-k="<?php echo esc_attr( $tt_it['kind'] ); ?>"><?php $tt_row( $tt_it ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</aside>
		<div class="tt-explorer__map tt-map" role="region" aria-label="Carte des activités"></div>
		<button type="button" class="tt-explorer__toggle" data-ex-toggle aria-controls="tt-ex-list" aria-expanded="false">Liste</button>
	</div>
</section>
