<?php
/**
 * favicon.ico à la racine du site, tiré de l'icône du site (Réglages > Général).
 *
 * Chez Hostinger, /favicon.ico est servi comme un fichier statique : WordPress ne voit jamais la
 * requête et ne peut pas la rediriger vers l'icône du site, d'où un 404 tant que le fichier n'existe pas.
 * Le thème écrit donc ABSPATH/favicon.ico (PNG 16, 32 et 48 px dans un conteneur ICO) dès qu'une icône
 * du site est réglée, puis à chaque changement d'icône. Un favicon.ico posé à la main avant
 * (tourisme-thailande.fr) n'est jamais écrasé.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/** Chemin du favicon à la racine du site. */
function tt_favicon_path(): string {
	return ABSPATH . 'favicon.ico';
}

/**
 * Conteneur ICO fait de PNG (lu par tous les navigateurs actuels) à partir d'une image carrée.
 *
 * @param string $source Fichier image (PNG, JPEG ou WebP).
 * @return string Octets du .ico, vide si l'image ne se lit pas.
 */
function tt_favicon_build( string $source ): string {
	if ( ! function_exists( 'imagecreatefromstring' ) || ! is_readable( $source ) ) {
		return '';
	}
	$img = @imagecreatefromstring( (string) file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $img ) {
		return '';
	}
	$pngs = array();
	foreach ( array( 16, 32, 48 ) as $size ) {
		$out = imagecreatetruecolor( $size, $size );
		imagealphablending( $out, false );
		imagesavealpha( $out, true );
		imagefill( $out, 0, 0, imagecolorallocatealpha( $out, 0, 0, 0, 127 ) );
		imagecopyresampled( $out, $img, 0, 0, 0, 0, $size, $size, imagesx( $img ), imagesy( $img ) );
		ob_start();
		imagepng( $out );
		$pngs[ $size ] = (string) ob_get_clean();
		imagedestroy( $out );
	}
	imagedestroy( $img );

	$ico    = pack( 'vvv', 0, 1, count( $pngs ) );
	$offset = 6 + 16 * count( $pngs );
	foreach ( $pngs as $size => $data ) {
		$ico    .= pack( 'CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen( $data ), $offset );
		$offset += strlen( $data );
	}
	return $ico . implode( '', $pngs );
}

/**
 * Écrit favicon.ico depuis l'icône du site si elle a changé depuis la dernière écriture.
 * L'option tt_favicon retient l'icône déjà écrite (ou « manual » si un fichier existait avant le thème).
 */
function tt_favicon_sync(): void {
	$icon = (int) get_option( 'site_icon' );
	$done = (string) get_option( 'tt_favicon', '' );
	if ( ! $icon || 'manual' === $done || (string) $icon === $done ) {
		return;
	}
	if ( get_transient( 'tt_favicon_retry' ) ) {
		return;
	}
	$path = tt_favicon_path();
	if ( '' === $done && file_exists( $path ) ) {
		update_option( 'tt_favicon', 'manual' );
		return;
	}
	$source = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $icon ) : get_attached_file( $icon );
	$ico    = $source ? tt_favicon_build( (string) $source ) : '';
	if ( '' === $ico || false === @file_put_contents( $path, $ico ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		set_transient( 'tt_favicon_retry', 1, DAY_IN_SECONDS ); // Pas d'essai à chaque page si l'écriture échoue.
		return;
	}
	update_option( 'tt_favicon', (string) $icon );
}

add_action( 'init', 'tt_favicon_sync', 20 );
