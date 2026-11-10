<?php
/**
 * Bloc « mois par mois » : une carte photo par guide mensuel du pays (articles {pays}-janvier…).
 *
 * Accroches : une ligne par mois dans l'attribut `texts` (12 lignes), sinon les textes par défaut.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

// Saison et accroche de chaque mois : profil du pays (tt_site()['months'] et ['seasons']).
$tt_slugs   = array( 'janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre' );
$tt_names   = array( 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre' );
$tt_seasons = (array) tt_site()['seasons'];
$tt_months  = array();
foreach ( $tt_slugs as $tt_i => $tt_slug ) {
	$tt_m        = tt_site()['months'][ $tt_i ] ?? array( (string) array_key_first( $tt_seasons ), '' );
	$tt_months[] = array( $tt_slug, $tt_names[ $tt_i ], isset( $tt_seasons[ $tt_m[0] ] ) ? $tt_m[0] : (string) array_key_first( $tt_seasons ), $tt_m[1] );
}
$tt_custom = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $attributes['texts'] ) ) ) );
$tt_now    = (int) wp_date( 'n' ) - 1;
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'tt-months2' ) ); ?>>
	<div class="tt-months2__legend">
		<?php foreach ( $tt_seasons as $tt_key => $tt_s ) : ?>
			<span class="is-<?php echo esc_attr( $tt_key ); ?>"><b><?php echo esc_html( $tt_s[0] ); ?></b> <?php echo esc_html( $tt_s[1] ); ?></span>
		<?php endforeach; ?>
	</div>
	<div class="tt-months2__grid">
		<?php
		foreach ( $tt_months as $tt_i => $tt_m ) :
			$tt_post = get_page_by_path( tt_country_slug() . '-' . $tt_m[0], OBJECT, 'post' );
			$tt_url  = $tt_post ? get_permalink( $tt_post ) : home_url( '/' . tt_country_slug() . '-' . $tt_m[0] . '/' );
			$tt_img  = $tt_post ? get_the_post_thumbnail( $tt_post, 'tt-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '';
			$tt_text = $tt_custom[ $tt_i ] ?? $tt_m[3];
			?>
			<a class="tt-month is-<?php echo esc_attr( $tt_m[2] ); ?><?php echo $tt_i === $tt_now ? ' is-now' : ''; ?>" href="<?php echo esc_url( $tt_url ); ?>">
				<span class="tt-month__media"><?php echo $tt_img; // phpcs:ignore ?></span>
				<span class="tt-month__season"><?php echo esc_html( $tt_seasons[ $tt_m[2] ][0] ); ?></span>
				<?php if ( $tt_i === $tt_now ) : ?><span class="tt-month__now">Ce mois-ci</span><?php endif; ?>
				<span class="tt-month__body">
					<b><?php echo esc_html( $tt_m[1] ); ?></b>
					<?php if ( '' !== $tt_text ) : ?><span class="tt-month__txt"><?php echo esc_html( $tt_text ); ?></span><?php endif; ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
