<?php
/**
 * Mise en place à distance d'un nouveau site (mot de passe d'application d'un administrateur) :
 * réglages que l'API REST de WordPress n'ouvre pas, limités à une liste fermée.
 *
 * GET  /wp-json/tt/v1/setup                      valeurs actuelles des réglages autorisés
 * POST /wp-json/tt/v1/setup {"options": {...}}   écrit les réglages autorisés, ignore les autres
 *
 * Autorisés : structure des permaliens, visibilité pour les moteurs, et les réglages des extensions
 * du setup (Yoast `wpseo*`, WP External Links `wpel-*`, Rate My Post `rmp_*`, LiteSpeed `litespeed.conf.*`).
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le nom d'option fait-il partie des réglages qu'on peut écrire à distance ?
 */
function tt_setup_allowed( string $name ): bool {
	if ( in_array( $name, array( 'permalink_structure', 'category_base', 'tag_base', 'blog_public' ), true ) ) {
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
						$names = array_merge( array( 'permalink_structure', 'category_base', 'tag_base', 'blog_public' ), $names );
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
