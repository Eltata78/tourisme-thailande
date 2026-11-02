"""Rend aux articles leur date de dernière modification d'avant la migration.

Usage : python3 -I migration/dates_modif.py <fichier identifiants user:app-password> [--apply] [--site=https://…]

La conversion des articles au nouveau format a mis la date du jour comme date de modification de presque tous les
articles (affichée sous le titre, reprise dans les données structurées et le sitemap). La date d'origine se retrouve :
- dans la méta _tt_sync_from pour les articles repris de la prod par sync_prod.py (date de modification sur la prod) ;
- sinon dans la dernière révision antérieure à la copie du preprod ;
- sinon c'est la date de publication (article jamais modifié).
Seuls les articles (post) sont concernés : les pages ont réellement changé de mise en page.
Sans --apply : liste ce qui serait changé. Avec --apply : dates rétablies (posts et index Yoast), cache purgé ; la date remplacée reste dans _tt_migration_modified.
"""
import json
import os
import subprocess
import sys
import tempfile

T0 = '2026-10-27 13:55:19'  # copie du preprod depuis la prod (GMT), voir sync_prod.py
SITE = next((a[7:] for a in sys.argv if a.startswith('--site=')), 'https://tourisme-thailande.fr')
AUTH = open(sys.argv[1]).read().strip()
APPLY = '--apply' in sys.argv


def php(code):
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False) as f:
        json.dump({'input': {'code': code}}, f)
    r = subprocess.run(['curl', '-sS', '-u', AUTH, '-H', 'Content-Type: application/json', '-X', 'POST', '--data-binary', '@' + f.name,
                        SITE + '/wp-json/wp-abilities/v1/abilities/novamira/execute-php/run'], capture_output=True, text=True)
    os.unlink(f.name)
    return json.loads(r.stdout)['return_value']


CODE = r"""
global $wpdb;
$t0 = '%s'; $apply = %s;
$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name, post_date_gmt, post_modified_gmt FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('publish','future','private','draft','pending') AND post_modified_gmt >= %%s", $t0 ), ARRAY_A );
$out = array( 'source' => array(), 'exemples' => array() );
foreach ( $posts as $p ) {
	$from = (string) get_post_meta( $p['ID'], '_tt_sync_from', true );
	$src  = 'prod';
	if ( '' === $from ) {
		$from = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_parent = %%d AND post_type = 'revision' AND post_name NOT LIKE '%%%%autosave%%%%' AND post_modified_gmt < %%s", $p['ID'], $t0 ) );
		$src  = 'révision';
	}
	if ( '' === $from || $from < $p['post_date_gmt'] ) {
		$from = $p['post_date_gmt'];
		$src  = 'publication';
	}
	$out['source'][ $src ] = ( $out['source'][ $src ] ?? 0 ) + 1;
	if ( count( $out['exemples'] ) < 6 ) {
		$out['exemples'][] = array( $p['post_name'], $p['post_modified_gmt'], $from, $src );
	}
	if ( $apply ) {
		add_post_meta( $p['ID'], '_tt_migration_modified', $p['post_modified_gmt'], true ); // date remplacée, pour revenir en arrière
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => $from, 'post_modified' => get_date_from_gmt( $from ) ), array( 'ID' => $p['ID'] ) );
		$wpdb->update( $wpdb->prefix . 'yoast_indexable', array( 'object_last_modified' => $from ), array( 'object_id' => $p['ID'], 'object_type' => 'post' ) );
		clean_post_cache( (int) $p['ID'] );
	}
}
$out['articles'] = count( $posts );
if ( $apply ) {
	wp_cache_flush();
	do_action( 'litespeed_purge_all' );
}
return $out;
"""

print(json.dumps(php(CODE % (T0, 'true' if APPLY else 'false')), ensure_ascii=False, indent=1))
