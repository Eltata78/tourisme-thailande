<?php
/**
 * Mise en place à distance d'un nouveau site (mot de passe d'application d'un administrateur) :
 * réglages que l'API REST de WordPress n'ouvre pas, limités à une liste fermée.
 *
 * GET  /wp-json/tt/v1/setup                      valeurs actuelles des réglages autorisés
 *      (dont tt_google : {"ga4_id": "G-…", "gsc_verify": "…"}, voir inc/tracking.php)
 * POST /wp-json/tt/v1/setup {"options": {...}}   écrit les réglages autorisés, ignore les autres
 *
 * Autorisés : structure des permaliens, visibilité pour les moteurs, et les réglages des extensions
 * du setup (Yoast `wpseo*`, WP External Links `wpel-*`, Rate My Post `rmp_*`, LiteSpeed `litespeed.conf.*`).
 *
 * POST /wp-json/tt/v1/theme-update {"ref": "main"}   met le thème à jour depuis le dépôt GitHub du thème
 *
 * La mise à jour ne télécharge que le dossier theme/ du dépôt Eltata78/tourisme-thailande (branche au choix),
 * installé sous le nom du thème actif : on corrige les 10 sites sans retéléverser le zip à la main.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le nom d'option fait-il partie des réglages qu'on peut écrire à distance ?
 */
function tt_setup_allowed( string $name ): bool {
	if ( in_array( $name, array( 'permalink_structure', 'category_base', 'tag_base', 'blog_public', 'tt_google' ), true ) ) {
		return true;
	}
	return (bool) preg_match( '/^(wpseo[a-z_]*|wpel-[a-z0-9_-]+|rmp_[a-z0-9_]+|litespeed\.conf\.[a-z0-9_.-]+)$/', $name );
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'tt/v1',
			'/setup',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
					'callback'            => static function () {
						global $wpdb;
						$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wpseo%' OR option_name LIKE 'wpel-%' OR option_name LIKE 'rmp\\_%' OR option_name LIKE 'litespeed.conf.%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$names = array_merge( array( 'permalink_structure', 'category_base', 'tag_base', 'blog_public', 'tt_google' ), $names );
						$out   = array();
						foreach ( array_filter( $names, 'tt_setup_allowed' ) as $name ) {
							$out[ $name ] = get_option( $name );
						}
						return rest_ensure_response( $out );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
					'args'                => array(
						'options' => array( 'type' => 'object', 'required' => true ),
					),
					'callback'            => static function ( WP_REST_Request $req ) {
						$done    = array();
						$skipped = array();
						foreach ( (array) $req->get_param( 'options' ) as $name => $value ) {
							$name = (string) $name;
							if ( ! tt_setup_allowed( $name ) ) {
								$skipped[] = $name;
								continue;
							}
							update_option( $name, $value );
							$done[] = $name;
						}
						if ( array_intersect( $done, array( 'permalink_structure', 'category_base', 'tag_base' ) ) ) {
							flush_rewrite_rules( false );
						}
						return rest_ensure_response( array( 'updated' => $done, 'skipped' => $skipped ) );
					},
				),
			)
		);
	}
);

/** Dépôt du thème : seule source acceptée par la mise à jour à distance. */
const TT_THEME_REPO = 'Eltata78/tourisme-thailande';

/**
 * Archive d'une branche du dépôt (dossier racine « dépôt-branche/ », thème dans theme/).
 */
function tt_theme_package_url( string $ref ): string {
	return (string) apply_filters( 'tt_theme_package_url', 'https://codeload.github.com/' . TT_THEME_REPO . '/zip/refs/heads/' . $ref, $ref );
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'tt/v1',
			'/theme-update',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn() => current_user_can( 'install_themes' ) && current_user_can( 'update_themes' ),
				'args'                => array(
					'ref' => array(
						'type'    => 'string',
						'default' => 'main',
						'pattern' => '^[A-Za-z0-9._/-]{1,100}$',
					),
				),
				'callback'            => static function ( WP_REST_Request $req ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/misc.php';
					require_once ABSPATH . 'wp-admin/includes/theme.php';
					require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

					$target = get_stylesheet();
					$before = wp_get_theme( $target )->get( 'Version' );

					// L'archive contient « dépôt-branche/theme/… » : on installe ce sous-dossier sous le nom du thème actif.
					$pick = static function ( $source, $remote_source ) use ( $target ) {
						global $wp_filesystem;
						$found = glob( trailingslashit( $remote_source ) . '*/theme/style.css' );
						if ( ! $found ) {
							return new WP_Error( 'tt_no_theme', 'Dossier theme/ introuvable dans l\'archive.' );
						}
						$dest = trailingslashit( $remote_source ) . $target;
						if ( ! $wp_filesystem->move( dirname( $found[0] ), $dest, true ) ) {
							return new WP_Error( 'tt_move', 'Impossible de préparer le dossier du thème.' );
						}
						return trailingslashit( $dest );
					};
					add_filter( 'upgrader_source_selection', $pick, 10, 2 );
					$skin   = new WP_Ajax_Upgrader_Skin();
					$result = ( new Theme_Upgrader( $skin ) )->install( tt_theme_package_url( (string) $req['ref'] ), array( 'overwrite_package' => true ) );
					remove_filter( 'upgrader_source_selection', $pick, 10 );

					if ( is_wp_error( $result ) ) {
						return $result;
					}
					if ( ! $result ) {
						$errors = $skin->get_error_messages();
						return new WP_Error( 'tt_update_failed', $errors ? implode( ' ', (array) $errors ) : 'Mise à jour du thème impossible.', array( 'status' => 500 ) );
					}
					wp_clean_themes_cache();
					return rest_ensure_response(
						array(
							'theme'  => $target,
							'before' => $before,
							'after'  => wp_get_theme( $target )->get( 'Version' ),
						)
					);
				},
			)
		);
	}
);
