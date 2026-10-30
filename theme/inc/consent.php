<?php
/**
 * Consentement aux cookies (léger, sans extension) : rien qui dépose un cookie tiers ne se charge avant l'accord.
 *
 * - Mesure d'audience : la bibliothèque Google Analytics de Site Kit (gtag.js) est neutralisée (type text/plain)
 *   et chargée par front.js après accord ; la configuration de Site Kit reste en place, sans effet sans la bibliothèque.
 * - Partenaires de réservation : le script de suivi des clics vers les offres d'activités, chargé de la même façon.
 * Choix gardé 6 mois dans le cookie tt_consent ; le lien « Cookies » du footer rouvre le bandeau.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catégories de cookies : clé => [libellé, description].
 */
function tt_consent_categories(): array {
	return array(
		'a' => array( 'Mesure d\'audience', 'Google Analytics : pages vues et parcours sur le site, de façon agrégée, pour savoir quels guides sont utiles.' ),
		'p' => array( 'Partenaires de réservation', 'Suivi des clics vers les sites de réservation d\'activités, pour savoir quelles réservations viennent de nos guides.' ),
	);
}

// Bibliothèque Google Analytics de Site Kit : chargée seulement après accord (catégorie « a »).
add_filter(
	'script_loader_tag',
	static function ( string $tag, string $handle ): string {
		if ( 'google_gtagjs' !== $handle || is_admin() ) {
			return $tag;
		}
		// Seule la balise qui charge la bibliothèque est neutralisée ; les scripts en ligne de Site Kit restent actifs.
		return (string) preg_replace( '#<script ([^>]*?)src=(["\'])#', '<script type="text/plain" data-tt-consent="a" $1data-src=$2', $tag, 1 );
	},
	10,
	2
);

// Script de suivi des partenaires d'activités (avant : extension Insert Headers and Footers), après accord (catégorie « p »).
add_action(
	'wp_head',
	static function () {
		printf(
			'<script type="text/plain" data-tt-consent="p" data-src="https://widget.getyourguide.com/dist/pa.umd.production.min.js" data-gyg-partner-id="%s"></script>' . "\n",
			esc_attr( tt_partners()['gyg_partner_id'] )
		);
	},
	20
);

// Bandeau : caché par défaut, affiché par front.js tant qu'aucun choix n'est enregistré.
add_action(
	'wp_footer',
	static function () {
		$privacy = home_url( '/mentions-legales/#cookies' );
		?>
		<div class="tt-consent" id="tt-consent" role="dialog" aria-labelledby="tt-consent-t" aria-describedby="tt-consent-d" hidden>
			<p class="tt-consent__t" id="tt-consent-t">Cookies</p>
			<p id="tt-consent-d">On utilise des cookies pour mesurer l'audience du site et savoir quelles réservations viennent de nos guides. Tu peux tout accepter, tout refuser ou choisir.</p>
			<div class="tt-consent__choices" hidden>
				<?php foreach ( tt_consent_categories() as $tt_key => $tt_cat ) : ?>
					<label><input type="checkbox" name="<?php echo esc_attr( $tt_key ); ?>"><b><?php echo esc_html( $tt_cat[0] ); ?></b><span><?php echo esc_html( $tt_cat[1] ); ?></span></label>
				<?php endforeach; ?>
			</div>
			<div class="tt-consent__acts">
				<button type="button" class="tt-btn tt-btn--indigo" data-consent="none">Tout refuser</button>
				<button type="button" class="tt-btn tt-btn--indigo" data-consent="all">Tout accepter</button>
			</div>
			<p class="tt-consent__more">
				<button type="button" data-consent="choose">Choisir</button>
				<a href="<?php echo esc_url( $privacy ); ?>">En savoir plus</a>
			</p>
		</div>
		<?php
	},
	5
);
