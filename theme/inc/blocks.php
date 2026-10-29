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

// Style de paragraphe « chapeau » (introduction des guides).
add_action(
	'init',
	static function () {
		register_block_style( 'core/paragraph', array( 'name' => 'tt-tip', 'label' => 'Astuce' ) );
	// Encadrés d'article : un groupe dont le premier paragraphe en gras sert de titre.
	register_block_style( 'core/group', array( 'name' => 'tt-savais', 'label' => 'Le savais-tu' ) );
	register_block_style( 'core/group', array( 'name' => 'tt-retenir', 'label' => 'À retenir' ) );
	register_block_style( 'core/group', array( 'name' => 'tt-alerte', 'label' => 'Attention' ) );
	register_block_style( 'core/group', array( 'name' => 'tt-timeline', 'label' => 'Programme horaire' ) );
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
