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
