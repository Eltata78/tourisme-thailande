<?php
/**
 * Images en WebP : tailles générées, copie de l'original, et WebP servi à la place des JPEG et PNG.
 *
 * - Les tailles (thumbnail, large, tt-card, tt-hero…) sont créées en WebP, qualité 70.
 * - Chaque original JPEG ou PNG reçoit une copie WebP à la même taille (taille « tt-full » des métadonnées),
 *   servie à sa place partout (src et srcset). L'original reste sur le serveur.
 * - Vaut pour chaque nouvel envoi (médiathèque, Content Machine) ; rattrapage des images déjà en ligne :
 *   migration/images_webp.py.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

// Qualité WebP : 70 (86 par défaut). Plus léger que la conversion à la volée du CDN, sans perte visible sur des photos.
const TT_WEBP_QUALITY = 70;

add_filter(
	'image_editor_output_format',
	static fn( array $formats ): array => array( 'image/jpeg' => 'image/webp', 'image/png' => 'image/webp' ) + $formats
);
add_filter( 'wp_editor_set_quality', static fn( $quality, $mime ) => 'image/webp' === $mime ? TT_WEBP_QUALITY : $quality, 10, 2 );

/**
 * Copie WebP de l'original (même taille) : créée si besoin, ajoutée aux métadonnées sous la taille « tt-full ».
 *
 * @param array $meta Métadonnées de l'image (celles en cours de création à l'envoi, sinon celles enregistrées).
 * @return array Métadonnées, avec tt-full si l'original est un JPEG ou un PNG.
 */
function tt_webp_full( int $id, array $meta ): array {
	$file = get_attached_file( $id );
	if ( empty( $meta['file'] ) || ! $file || ! preg_match( '/\.(jpe?g|png)$/i', $file ) || ! file_exists( $file ) ) {
		return $meta;
	}
	$name = pathinfo( $file, PATHINFO_FILENAME ) . '-' . (int) $meta['width'] . 'x' . (int) $meta['height'] . '.webp';
	$path = dirname( $file ) . '/' . $name;
	if ( ! file_exists( $path ) ) {
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return $meta;
		}
		$editor->set_quality( TT_WEBP_QUALITY );
		$saved = $editor->save( $path, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			return $meta;
		}
	}
	$size = wp_getimagesize( $path );
	$meta['sizes']['tt-full'] = array(
		'file'      => $name,
		'width'     => (int) ( $size[0] ?? $meta['width'] ),
		'height'    => (int) ( $size[1] ?? $meta['height'] ),
		'mime-type' => 'image/webp',
		'filesize'  => (int) filesize( $path ),
	);
	return $meta;
}

// À l'envoi : copie WebP de l'original créée avec les autres tailles.
add_filter(
	'wp_generate_attachment_metadata',
	static fn( $meta, $id, $context = 'create' ) => is_array( $meta ) && 'create' === $context ? tt_webp_full( (int) $id, $meta ) : $meta,
	10,
	3
);

// La copie WebP remplace l'original en src (image pleine taille demandée) et dans le srcset (même largeur).
add_filter(
	'wp_get_attachment_image_src',
	static function ( $image, $id ) {
		$webp = is_array( $image ) && empty( $image[3] ) ? ( wp_get_attachment_metadata( $id )['sizes']['tt-full'] ?? null ) : null;
		if ( $webp && (int) $webp['width'] === (int) $image[1] ) {
			$image[0] = trailingslashit( dirname( $image[0] ) ) . $webp['file'];
		}
		return $image;
	},
	10,
	2
);
add_filter(
	'wp_calculate_image_srcset',
	static function ( $sources, $size, $src, $meta ) {
		$webp = $meta['sizes']['tt-full'] ?? null;
		if ( is_array( $sources ) && $webp && isset( $sources[ $webp['width'] ] ) ) {
			$sources[ $webp['width'] ]['url'] = trailingslashit( dirname( $sources[ $webp['width'] ]['url'] ) ) . $webp['file'];
		}
		return $sources;
	},
	10,
	4
);
