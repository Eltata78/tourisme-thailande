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

/**
 * FAQ Yoast affichée comme les FAQ des pages destination : questions repliables (première ouverte).
 * Le balisage FAQPage de Yoast est calculé à partir des attributs du bloc, il n'est pas touché.
 */
add_filter(
	'render_block_yoast/faq-block',
	static function ( string $html, array $block ): string {
		$items = $block['attrs']['questions'] ?? array();
		if ( ! $items ) {
			return $html;
		}
		$out = '';
		foreach ( array_values( $items ) as $i => $q ) {
			$question = trim( wp_strip_all_tags( (string) ( $q['jsonQuestion'] ?? '' ) ) );
			$answer   = trim( (string) ( $q['jsonAnswer'] ?? '' ) );
			if ( '' === $question ) {
				continue;
			}
			$out .= sprintf(
				'<details class="wp-block-details" id="%s"%s><summary>%s</summary><p>%s</p></details>',
				esc_attr( (string) ( $q['id'] ?? '' ) ),
				0 === $i ? ' open' : '',
				esc_html( $question ),
				wp_kses_post( $answer )
			);
		}
		return '<div class="tt-faq">' . $out . '</div>';
	},
	10,
	2
);
