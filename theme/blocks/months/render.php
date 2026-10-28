<?php
/**
 * Bloc « La Thaïlande mois par mois » : une carte photo par guide mensuel.
 *
 * Accroches : une ligne par mois dans l'attribut `texts` (12 lignes), sinon les textes par défaut.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

$tt_months = array(
	array( 'janvier', 'Janvier', 'cool', 'Sec et doux partout, pleine haute saison' ),
	array( 'fevrier', 'Février', 'cool', 'Le meilleur mois pour combiner les deux côtes' ),
	array( 'mars', 'Mars', 'hot', "La chaleur monte, la mer d'Andaman reste calme" ),
	array( 'avril', 'Avril', 'hot', 'Le mois le plus chaud, Songkran le 13' ),
	array( 'mai', 'Mai', 'hot', 'Premières pluies côté Phuket et Krabi' ),
	array( 'juin', 'Juin', 'wet', "Averses l'après-midi, Golfe ensoleillé" ),
	array( 'juillet', 'Juillet', 'wet', 'Koh Samui et Koh Tao en pleine saison' ),
	array( 'aout', 'Août', 'wet', 'Prix en baisse à Phuket, foule dans le Golfe' ),
	array( 'septembre', 'Septembre', 'wet', 'Le plus pluvieux, sauf autour de Samui' ),
	array( 'octobre', 'Octobre', 'wet', 'Le Nord sèche, le Golfe se mouille' ),
	array( 'novembre', 'Novembre', 'cool', 'Loy Krathong et Yi Peng à Chiang Mai' ),
	array( 'decembre', 'Décembre', 'cool', 'Haute saison, réserve tôt' ),
);
$tt_seasons = array(
	'cool' => array( 'Saison fraîche', 'novembre à février' ),
	'hot'  => array( 'Saison chaude', 'mars à mai' ),
	'wet'  => array( 'Mousson', 'juin à octobre' ),
);
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
			$tt_post = get_page_by_path( 'thailande-' . $tt_m[0], OBJECT, 'post' );
			$tt_url  = $tt_post ? get_permalink( $tt_post ) : home_url( '/thailande-' . $tt_m[0] . '/' );
			$tt_img  = $tt_post ? get_the_post_thumbnail( $tt_post, 'tt-card', array( 'loading' => 'lazy', 'alt' => '' ) ) : '';
			$tt_text = $tt_custom[ $tt_i ] ?? $tt_m[3];
			?>
			<a class="tt-month is-<?php echo esc_attr( $tt_m[2] ); ?><?php echo $tt_i === $tt_now ? ' is-now' : ''; ?>" href="<?php echo esc_url( $tt_url ); ?>">
				<span class="tt-month__media"><?php echo $tt_img; // phpcs:ignore ?></span>
				<span class="tt-month__season"><?php echo esc_html( $tt_seasons[ $tt_m[2] ][0] ); ?></span>
				<?php if ( $tt_i === $tt_now ) : ?><span class="tt-month__now">Ce mois-ci</span><?php endif; ?>
				<span class="tt-month__body">
					<b><?php echo esc_html( $tt_m[1] ); ?></b>
					<span class="tt-month__txt"><?php echo esc_html( $tt_text ); ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
