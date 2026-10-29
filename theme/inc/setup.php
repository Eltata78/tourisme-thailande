<?php
/**
 * Supports du thème, menus et assets.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'custom-logo', array( 'height' => 150, 'width' => 247, 'flex-width' => true ) );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/main.css' );
		remove_theme_support( 'core-block-patterns' );
		register_nav_menus(
			array(
				'primary' => 'Menu principal (méga-menu)',
			)
		);
		add_image_size( 'tt-card', 640, 480, true );
		add_image_size( 'tt-hero', 1920, 1080, true );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_dequeue_style( 'twentytwentyfive-style' );
		wp_enqueue_style( 'tt-main', TT_URI . '/assets/css/main.css', array(), filemtime( TT_DIR . '/assets/css/main.css' ) );
		wp_enqueue_script(
			'tt-front',
			TT_URI . '/assets/js/front.js',
			array(),
			filemtime( TT_DIR . '/assets/js/front.js' ),
			array( 'strategy' => 'defer', 'in_footer' => true )
		);
		wp_localize_script(
			'tt-front',
			'ttFront',
			array(
				'searchIndex' => rest_url( 'tt/v1/search-index' ),
				'maplib'      => get_theme_file_uri( 'assets/vendor/maplibre/' ),
				'gyg'         => array(
					'domain'  => tt_partners()['gyg_domain'],
					'partner' => tt_partners()['gyg_partner_id'],
				),
				'trip'        => array(
					'alliance' => tt_partners()['trip_alliance_id'],
					'sid'      => tt_partners()['trip_sid'],
				),
			)
		);
	},
	20
);

// Précharge la police variable : un seul fichier pour toutes les graisses et largeurs.
add_action(
	'wp_head',
	static function () {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( TT_URI . '/assets/fonts/archivo-latin-var.woff2' )
		);
	},
	1
);

/**
 * Titre et description SEO de Yoast ouverts à l'API REST (articles), pour que Content Machine
 * les écrive à la publication comme ceux des articles écrits à la main.
 */
add_action(
	'init',
	static function () {
		foreach ( array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ) as $key ) {
			register_post_meta(
				'post',
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
);
