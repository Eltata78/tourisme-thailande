"""Rattrapage des images déjà en ligne : toutes les tailles régénérées en WebP, formats du thème compris.

Usage : python3 -I migration/images_webp.py <fichier identifiants user:app-password> [--apply] [--site=https://…] [--limit=N] [--ids=1,2]

Pour chaque image JPEG ou PNG : tailles enregistrées (thumbnail, medium, large, tt-card, tt-hero…) recréées en WebP à
partir de l'original (le thème règle le format de sortie, voir inc/images.php), puis le contenu des pages et articles
pointe vers les nouvelles versions (même taille, extension .webp), ce qui garde les srcset. Original JPEG ou PNG :
copie WebP à la même taille (taille « tt-full »), servie à sa place par le thème (inc/images.php) et dans le contenu. Images déjà en WebP ou AVIF :
seules les tailles manquantes sont créées. L'original et les anciens fichiers restent sur le serveur (liens externes,
moteurs d'images) ; leur liste est gardée dans la méta _tt_old_sizes. Reprise possible : les images traitées portent
la méta _tt_webp.
Sans --apply : compte ce qui reste à traiter.
"""
import json
import os
import subprocess
import sys
import tempfile
import time

SITE = next((a[7:] for a in sys.argv if a.startswith('--site=')), 'https://tourisme-thailande.fr')
LIMIT = int(next((a[8:] for a in sys.argv if a.startswith('--limit=')), '0'))
IDS = [int(i) for i in next((a[6:] for a in sys.argv if a.startswith('--ids=')), '').split(',') if i]
AUTH = open(sys.argv[1]).read().strip()
APPLY = '--apply' in sys.argv


def php(code):
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False) as f:
        json.dump({'input': {'code': code}}, f)
    r = subprocess.run(['curl', '-sS', '--max-time', '60', '-u', AUTH, '-H', 'Content-Type: application/json', '-X', 'POST', '--data-binary', '@' + f.name,
                        SITE + '/wp-json/wp-abilities/v1/abilities/novamira/execute-php/run'], capture_output=True, text=True)
    os.unlink(f.name)
    return json.loads(r.stdout)['return_value']


TODO = r"""
global $wpdb;
return array_map( 'intval', $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_tt_webp' WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND m.meta_id IS NULL ORDER BY p.ID" ) );
"""

# Lot : images traitées tant que le lot reste sous ~18 s (limite d'exécution du serveur : 30 s).
BATCH = r"""
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/image.php';
$ids = json_decode( '%s', true );
@set_time_limit( 120 );
$t0 = microtime( true ); $done = array(); $errors = array(); $rewritten = 0;
$registered = array_keys( wp_get_registered_image_subsizes() );
foreach ( $ids as $id ) {
	if ( microtime( true ) - $t0 > 18 ) { break; }
	$meta = wp_get_attachment_metadata( $id );
	$mime = get_post_mime_type( $id );
	if ( ! is_array( $meta ) || empty( $meta['file'] ) || ! file_exists( wp_get_original_image_path( $id ) ) ) {
		update_post_meta( $id, '_tt_webp', 'ignorée' ); $errors[] = array( $id, 'fichier ou métadonnées absents' ); continue;
	}
	// Tailles d'avant, gardées avant tout changement : un lot coupé en route reprend avec la bonne liste.
	$old = get_post_meta( $id, '_tt_sizes_before', true );
	if ( ! is_array( $old ) ) { $old = $meta['sizes'] ?? array(); update_post_meta( $id, '_tt_sizes_before', $old ); }
	$dir = dirname( $meta['file'] ) . '/';
	$base = wp_get_upload_dir()['basedir'] . '/' . $dir;
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $k => $s ) {
		if ( ! file_exists( $base . $s['file'] ) ) { unset( $meta['sizes'][ $k ] ); } // restes d'Elementor : fichiers disparus
	}
	if ( in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
		foreach ( $registered as $k ) { unset( $meta['sizes'][ $k ] ); }
	}
	wp_update_attachment_metadata( $id, $meta );
	$res = wp_update_image_subsizes( $id );
	if ( is_wp_error( $res ) ) {
		$meta['sizes'] = $old; wp_update_attachment_metadata( $id, $meta ); // remis comme avant
		update_post_meta( $id, '_tt_webp', 'erreur' ); $errors[] = array( $id, $res->get_error_message() ); continue;
	}
	$new = wp_get_attachment_metadata( $id )['sizes'] ?? array();
	$moved = array();
	foreach ( $old as $k => $o ) {
		if ( empty( $new[ $k ] ) || $new[ $k ]['file'] === $o['file'] ) { continue; }
		$moved[ $k ] = $o['file'];
		// Même taille (à 2 px près) : le contenu peut pointer vers la version WebP.
		if ( (int) $new[ $k ]['width'] === (int) $o['width'] && abs( (int) $new[ $k ]['height'] - (int) $o['height'] ) <= 2 ) {
			$from = '/uploads/' . $dir . $o['file']; $to = '/uploads/' . $dir . $new[ $k ]['file'];
			$rewritten += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE( post_content, %%s, %%s ) WHERE post_type IN ( 'post', 'page', 'wp_block' ) AND post_content LIKE %%s", $from, $to, '%%' . $wpdb->esc_like( $from ) . '%%' ) );
		}
	}
	// Original JPEG ou PNG : copie WebP à la même taille (taille « tt-full »), servie à sa place par le thème ; le contenu pointe dessus.
	$full = '/uploads/' . $meta['file'];
	$like = '%%' . $wpdb->esc_like( $full ) . '%%';
	if ( in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
		$m2 = tt_webp_full( $id, wp_get_attachment_metadata( $id ) ); // fonction du thème, inc/images.php
		if ( ! empty( $m2['sizes']['tt-full'] ) ) {
			wp_update_attachment_metadata( $id, $m2 );
			$rewritten += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE( post_content, %%s, %%s ) WHERE post_type IN ( 'post', 'page', 'wp_block' ) AND post_content LIKE %%s", $full, '/uploads/' . $dir . $m2['sizes']['tt-full']['file'], $like ) );
		}
	}
	if ( $moved ) { update_post_meta( $id, '_tt_old_sizes', $moved ); }
	update_post_meta( $id, '_tt_webp', 'ok' );
	$done[] = $id;
}
return array( 'done' => $done, 'errors' => $errors, 'rewritten' => $rewritten, 'secondes' => round( microtime( true ) - $t0, 1 ) );
"""

FINISH = r"""
wp_cache_flush();
do_action( 'litespeed_purge_all' );
return 1;
"""


def main():
    todo = php(TODO)
    print('images à traiter :', len(todo))
    if not APPLY or not todo:
        return
    if IDS:
        todo = [i for i in todo if i in IDS]
    if LIMIT:
        todo = todo[:LIMIT]
    total, errors, rewritten, t = 0, [], 0, time.time()
    while todo:
        r = php(BATCH % json.dumps(todo[:40]))
        done = set(r['done']) | {e[0] for e in r['errors']}
        if not done:
            print('aucun progrès sur ce lot, arrêt', r)
            break
        todo = [i for i in todo if i not in done]
        total += len(r['done']); errors += r['errors']; rewritten += r['rewritten']
        print(f"{total} traitées, {len(todo)} restantes, {rewritten} remplacements dans le contenu, lot {r['secondes']} s, {round(time.time() - t)} s au total", flush=True)
        time.sleep(1)
    php(FINISH)
    print('terminé :', total, 'images,', rewritten, 'remplacements, erreurs :', errors)


if __name__ == '__main__':
    main()
