"""Après la publication du staging sur la prod : contrôles et remise en ordre de la prod.

Usage : python3 -I migration/apres_bascule.py <fichier identifiants user:app-password> [--apply] [--site=https://…]

Sans --apply : état de la prod (adresse, indexation, thème, extensions), traces du domaine preprod dans la base
(table et colonne), puis contrôle de toutes les URL des sitemaps (code HTTP, domaine preprod, noindex, Elementor).
Avec --apply : indexation rétablie, domaine preprod remplacé partout (valeurs sérialisées comprises, sauf guid),
empreintes des permaliens Yoast recalculées, cache LiteSpeed purgé, puis le même contrôle des URL.
"""
import json
import os
import re
import subprocess
import sys
import tempfile
from concurrent.futures import ThreadPoolExecutor

OLD, NEW = 'preprod.tourisme-thailande.fr', 'tourisme-thailande.fr'
PROD = next((a[7:] for a in sys.argv if a.startswith('--site=')), 'https://' + NEW)  # --site : essai sur un autre site
AUTH = open(sys.argv[1]).read().strip()
APPLY = '--apply' in sys.argv


def php(code):
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False) as f:
        json.dump({'input': {'code': code}}, f)
    r = subprocess.run(['curl', '-sS', '-u', AUTH, '-H', 'Content-Type: application/json', '-X', 'POST', '--data-binary', '@' + f.name,
                        PROD + '/wp-json/wp-abilities/v1/abilities/novamira/execute-php/run'], capture_output=True, text=True)
    os.unlink(f.name)
    return json.loads(r.stdout)['return_value']


STATE = r"""
global $wpdb;
return array(
	'home' => home_url(), 'siteurl' => site_url(), 'blogname' => get_option( 'blogname' ), 'blog_public' => get_option( 'blog_public' ),
	'theme' => get_stylesheet(), 'plugins' => array_map( 'dirname', (array) get_option( 'active_plugins' ) ),
	'yoast_home' => ( get_option( 'wpseo' ) ?: array() )['home_url'] ?? null,
	'indexables' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}yoast_indexable" ),
);
"""

# Colonnes texte de toutes les tables du site qui contiennent l'ancien domaine : [table, colonne, clé primaire, lignes].
SCAN = r"""
global $wpdb;
$old = '%s';
$out = array();
$cols = $wpdb->get_results( $wpdb->prepare( "SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %%s AND TABLE_NAME LIKE %%s AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext')", DB_NAME, $wpdb->esc_like( $wpdb->prefix ) . '%%' ), ARRAY_A );
foreach ( $cols as $col ) {
	$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$col['t']}` WHERE `{$col['c']}` LIKE %%s", '%%' . $wpdb->esc_like( $old ) . '%%' ) );
	if ( $n ) {
		$key = $wpdb->get_row( "SHOW KEYS FROM `{$col['t']}` WHERE Key_name = 'PRIMARY'", ARRAY_A );
		$out[] = array( $col['t'], $col['c'], $key['Column_name'] ?? null, $n );
	}
}
return $out;
"""

# Remplacement ligne par ligne : les valeurs sérialisées sont désérialisées, corrigées puis resérialisées (longueurs justes).
REPLACE = r"""
global $wpdb;
$old = '%s'; $new = '%s';
$cols = json_decode( '%s', true );
$fix = static function ( $v ) use ( &$fix, $old, $new ) {
	if ( is_string( $v ) ) { return str_replace( $old, $new, $v ); }
	if ( is_array( $v ) ) { foreach ( $v as $k => $x ) { $v[ $k ] = $fix( $x ); } return $v; }
	if ( $v instanceof stdClass ) { foreach ( get_object_vars( $v ) as $k => $x ) { $v->$k = $fix( $x ); } }
	return $v;
};
$out = array();
foreach ( $cols as list( $t, $c, $pk ) ) {
	if ( 'guid' === $c || ! $pk ) { $out[] = array( $t, $c, 'ignorée' ); continue; }
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT `$pk` id, `$c` v FROM `$t` WHERE `$c` LIKE %%s", '%%' . $wpdb->esc_like( $old ) . '%%' ), ARRAY_A );
	$done = 0;
	foreach ( $rows as $r ) {
		$v = is_serialized( $r['v'] ) ? serialize( $fix( unserialize( $r['v'] ) ) ) : str_replace( $old, $new, $r['v'] );
		$done += (int) $wpdb->update( $t, array( $c => $v ), array( $pk => $r['id'] ) );
	}
	$out[] = array( $t, $c, $done . '/' . count( $rows ) );
}
// Yoast retrouve un contenu par l'empreinte de son permalien : recalculée là où l'adresse a changé.
$stale = $wpdb->get_results( "SELECT id, permalink FROM {$wpdb->prefix}yoast_indexable WHERE permalink IS NOT NULL AND permalink_hash <> CONCAT( LENGTH( permalink ), ':', MD5( permalink ) )", ARRAY_A );
foreach ( $stale as $r ) {
	$wpdb->update( $wpdb->prefix . 'yoast_indexable', array( 'permalink_hash' => strlen( $r['permalink'] ) . ':' . md5( $r['permalink'] ) ), array( 'id' => $r['id'] ) );
}
$out[] = array( 'yoast_indexable', 'permalink_hash', count( $stale ) );
return $out;
"""

FINISH = r"""
update_option( 'blog_public', '1' );
foreach ( array( 'tt_search_index_v1' ) as $t ) { delete_transient( $t ); }
wp_cache_flush();
do_action( 'litespeed_purge_all' );
return array( 'blog_public' => get_option( 'blog_public' ) );
"""


def get(url):
    """Code HTTP, adresse finale, page, en-têtes de la dernière réponse."""
    with tempfile.NamedTemporaryFile(suffix='.txt', delete=False) as h:
        pass
    r = subprocess.run(['curl', '-sS', '-L', '--max-time', '60', '-D', h.name, '-o', '-', '-w', '\n%{http_code} %{url_effective}', url], capture_output=True, text=True)
    headers = open(h.name).read().split('\n\n')
    os.unlink(h.name)
    body, _, tail = r.stdout.rpartition('\n')
    code, _, final = tail.partition(' ')
    return code, final, body, [x for x in headers if x.strip()][-1:][0] if headers else ''


def crawl():
    urls = []
    for sm in re.findall(r'<loc>([^<]+)</loc>', get(PROD + '/sitemap_index.xml')[2]):
        urls += re.findall(r'<loc>([^<]+)</loc>', get(sm)[2])
    urls = sorted(set(u for u in urls if not re.search(r'\.(jpe?g|png|webp|gif)$', u, re.I)))

    def check(u):
        code, final, body, head = get(u)
        issues = []
        if code != '200':
            issues.append(code)
        if final.rstrip('/') != u.rstrip('/'):
            issues.append('redirigée vers ' + final)
        if OLD in body:
            issues.append('domaine preprod')
        if re.search(r'<meta[^>]+name=["\']robots["\'][^>]+noindex', body) or re.search(r'(?im)^x-robots-tag:.*noindex', head):
            issues.append('noindex')
        if 'elementor' in body.lower():
            issues.append('elementor')
        if re.search(r'(Fatal error|Warning|Notice|Deprecated)</b>:', body):
            issues.append('erreur PHP')
        return u, issues

    with ThreadPoolExecutor(8) as ex:
        results = list(ex.map(check, urls))
    bad = [(u, i) for u, i in results if i]
    print(len(urls), 'URL contrôlées,', len(bad), 'avec un problème')
    for u, i in bad:
        print('  ', u, i)
    code = get(PROD + '/page-qui-n-existe-pas-tt/')[0]
    print('404 de test :', code, '| robots.txt :', ' '.join(get(PROD + '/robots.txt')[2].split())[:200])


def main():
    print('état :', php(STATE))
    found = php(SCAN % OLD)
    print('traces de', OLD, ':', found or 'aucune')
    if APPLY:
        if found:
            print('remplacement :', php(REPLACE % (OLD, NEW, json.dumps(found))))
        print('fin :', php(FINISH))
        print('traces restantes :', php(SCAN % OLD) or 'aucune')
    crawl()


if __name__ == '__main__':
    main()
