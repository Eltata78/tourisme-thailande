<?php
/**
 * Enregistrement des blocs du thème (rendu serveur, un dossier par bloc).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		wp_register_script(
			'tt-blocks-editor',
			TT_URI . '/assets/js/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-data' ),
			filemtime( TT_DIR . '/assets/js/editor.js' ),
			true
		);
		$names = array();
		foreach ( glob( TT_DIR . '/blocks/*/block.json' ) as $file ) {
			$type = register_block_type( dirname( $file ) );
			if ( $type ) {
				$names[] = $type->name;
			}
		}
		wp_add_inline_script( 'tt-blocks-editor', 'window.ttBlocks = ' . wp_json_encode( $names ) . ';', 'before' );
	}
);

add_action(
	'enqueue_block_editor_assets',
	static function () {
		wp_enqueue_script( 'tt-blocks-editor' );
	}
);

add_filter(
	'block_categories_all',
	static function ( array $cats ): array {
		array_unshift(
			$cats,
			array( 'slug' => 'tt-affiliation', 'title' => 'Réservation et affiliation' ),
			array( 'slug' => 'tt-layout', 'title' => 'Tourisme Thaïlande' )
		);
		return $cats;
	}
);
