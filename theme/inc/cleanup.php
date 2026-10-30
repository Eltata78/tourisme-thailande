<?php
/**
 * Allègement du <head> et des scripts inutiles.
 */

defined( 'ABSPATH' ) || exit;

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

add_filter( 'emoji_svg_url', '__return_false' );

// Pas de jQuery côté visiteur, sauf si un plugin le demande explicitement.
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_admin() && ! is_customize_preview() ) {
			wp_deregister_script( 'wp-embed' );
		}
	},
	100
);

// Notes des lecteurs (Rate My Post) : seulement là où elles s'affichent (articles, pages notées), sans bloquer le rendu.
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_singular( 'post' ) && ! ( is_page() && tt_rating_page( get_queried_object() ) ) ) {
			wp_dequeue_script( 'rate-my-post' );
			wp_dequeue_style( 'rate-my-post' );
		} else {
			wp_script_add_data( 'rate-my-post', 'strategy', 'defer' );
		}
	},
	100
);

// Police des étoiles préchargée seulement là où elles s'affichent (articles, pages notées, listes d'articles).
add_filter( 'rmp_font_preload', static fn( $preload ) => $preload && ( is_singular( 'post' ) || is_home() || is_archive() || ( is_page() && tt_rating_page( get_queried_object() ) ) ) );

// Page carte : connexion ouverte d'avance vers le serveur des fonds de carte.
add_filter(
	'wp_resource_hints',
	static function ( array $urls, string $type ): array {
		if ( 'preconnect' === $type && is_page() && has_block( 'tt/explorer', get_queried_object() ) ) {
			$urls[] = array( 'href' => 'https://tiles.openfreemap.org', 'crossorigin' => 'anonymous' );
		}
		return $urls;
	},
	10,
	2
);
