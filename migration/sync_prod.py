"""Rattrapage avant bascule : contenus publiés ou modifiés sur la prod depuis la copie du preprod.

Usage : python3 -I migration/sync_prod.py <fichier identifiants user:app-password> [--apply]

Sans --apply : liste ce qui a changé sur la prod (articles, pages, médias, commentaires).
Avec --apply : copie ces contenus sur le preprod. Articles convertis au nouveau format (posts2blocks),
images manquantes téléchargées depuis la prod, commentaires recopiés (sans les pings ni le spam).
La prod n'est jamais modifiée : on n'y fait que des lectures.
"""
import json
import os
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from posts2blocks import Converter  # noqa: E402

PROD, PRE = 'https://tourisme-thailande.fr', 'https://preprod.tourisme-thailande.fr'
AUTH = open(sys.argv[1]).read().strip()
APPLY = '--apply' in sys.argv


def php(site, code):
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False) as f:
        json.dump({'input': {'code': code}}, f)
    r = subprocess.run(['curl', '-sS', '-u', AUTH, '-H', 'Content-Type: application/json', '-X', 'POST', '--data-binary', '@' + f.name,
                        site + '/wp-json/wp-abilities/v1/abilities/novamira/execute-php/run'], capture_output=True, text=True)
    os.unlink(f.name)
    return json.loads(r.stdout)['return_value']


INVENTORY = r"""
global $wpdb;
return array(
	'rows' => $wpdb->get_results( "SELECT ID, post_type, post_status, post_name, post_modified_gmt, guid FROM {$wpdb->posts} WHERE post_type IN ('post','page','attachment') AND post_status IN ('publish','draft','private','future','pending','inherit')", ARRAY_A ),
	'comments' => $wpdb->get_results( "SELECT comment_ID, comment_post_ID, comment_type, comment_approved, comment_date_gmt, comment_author_email, MD5(comment_content) h FROM {$wpdb->comments}", ARRAY_A ),
	'synced' => $wpdb->get_results( "SELECT p.post_type, p.post_name, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_tt_sync_from'", ARRAY_A ),
);
"""

EXPORT = r"""
$ids = json_decode( '%s', true );
$out = array();
foreach ( $ids as $id ) {
	$p = get_post( (int) $id );
	$meta = array();
	foreach ( get_post_meta( $p->ID ) as $k => $v ) {
		if ( 0 === strpos( $k, '_yoast_wpseo_' ) || 0 === strpos( $k, 'tt_' ) || 0 === strpos( $k, 'rmp_' ) ) { $meta[ $k ] = maybe_unserialize( $v[0] ); }
	}
	$thumb = (int) get_post_thumbnail_id( $p->ID );
	$out[] = array( 'id' => $p->ID, 'type' => $p->post_type, 'slug' => $p->post_name, 'title' => $p->post_title, 'content' => $p->post_content,
		'excerpt' => $p->post_excerpt, 'status' => $p->post_status, 'date_gmt' => $p->post_date_gmt, 'author' => get_the_author_meta( 'user_login', $p->post_author ),
		'cats' => wp_get_post_categories( $p->ID, array( 'fields' => 'slugs' ) ), 'meta' => $meta, 'comment_status' => $p->comment_status,
		'thumb_url' => $thumb ? wp_get_attachment_url( $thumb ) : '', 'modified_gmt' => $p->post_modified_gmt );
}
return $out;
"""

COMMENTS = r"""
global $wpdb;
$ids = array_map( 'intval', json_decode( '%s', true ) );
if ( ! $ids ) { return array(); }
$rows = $wpdb->get_results( "SELECT c.*, p.post_type, p.post_name FROM {$wpdb->comments} c JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE c.comment_ID IN (" . implode( ',', $ids ) . ")", ARRAY_A );
return $rows;
"""

IMPORT = r"""
$rows = json_decode( base64_decode( '%s' ), true );
$out = array();
foreach ( $rows as $r ) {
	$cur = get_posts( array( 'post_type' => $r['type'], 'name' => $r['slug'], 'post_status' => 'any', 'numberposts' => 1 ) );
	$author = get_user_by( 'login', $r['author'] );
	$data = array( 'post_type' => $r['type'], 'post_name' => $r['slug'], 'post_title' => wp_slash( $r['title'] ), 'post_content' => wp_slash( $r['content'] ),
		'post_excerpt' => wp_slash( $r['excerpt'] ), 'post_status' => $r['status'], 'post_date_gmt' => $r['date_gmt'], 'post_date' => get_date_from_gmt( $r['date_gmt'] ),
		'comment_status' => $r['comment_status'], 'ping_status' => 'closed' );
	if ( $author ) { $data['post_author'] = $author->ID; }
	if ( $cur ) {
		$data['ID'] = $cur[0]->ID;
		if ( '' === get_post_meta( $cur[0]->ID, '_tt_backup_content', true ) ) { update_post_meta( $cur[0]->ID, '_tt_backup_content', wp_slash( $cur[0]->post_content ) ); }
	}
	$id = wp_insert_post( $data, true );
	if ( is_wp_error( $id ) ) { $out[] = array( $r['slug'], $id->get_error_message() ); continue; }
	if ( $r['cats'] ) {
		wp_set_post_categories( $id, array_filter( array_map( static function ( $s ) { $t = get_category_by_slug( $s ); return $t ? $t->term_id : 0; }, $r['cats'] ) ) );
	}
	foreach ( $r['meta'] as $k => $v ) { update_post_meta( $id, $k, is_string( $v ) ? wp_slash( $v ) : $v ); }
	update_post_meta( $id, '_tt_sync_from', $r['modified_gmt'] );
	update_post_meta( $id, '_elementor_edit_mode', '' );
	// Image à la une : celle du preprod si le fichier y est déjà, sinon téléchargée depuis la prod.
	if ( $r['thumb_url'] ) {
		$file = wp_basename( $r['thumb_url'] );
		$att  = get_posts( array( 'post_type' => 'attachment', 'numberposts' => 1, 'meta_query' => array( array( 'key' => '_wp_attached_file', 'value' => $file, 'compare' => 'LIKE' ) ) ) );
		if ( $att ) {
			set_post_thumbnail( $id, $att[0]->ID );
		} else {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$aid = media_sideload_image( $r['thumb_url'], $id, null, 'id' );
			if ( ! is_wp_error( $aid ) ) { set_post_thumbnail( $id, $aid ); }
		}
	}
	$out[] = array( $r['slug'], 'ok', $id );
}
delete_transient( 'tt_search_index_v1' );
return $out;
"""

IMPORT_COMMENTS = r"""
$rows = json_decode( base64_decode( '%s' ), true );
$map = array(); $out = array();
usort( $rows, static fn( $a, $b ) => (int) $a['comment_ID'] <=> (int) $b['comment_ID'] );
foreach ( $rows as $c ) {
	$p = get_posts( array( 'post_type' => $c['post_type'], 'name' => $c['post_name'], 'post_status' => 'any', 'numberposts' => 1 ) );
	if ( ! $p ) { $out[] = array( $c['comment_ID'], 'article introuvable' ); continue; }
	$parent = (int) $c['comment_parent'];
	$data = array( 'comment_post_ID' => $p[0]->ID, 'comment_author' => $c['comment_author'], 'comment_author_email' => $c['comment_author_email'],
		'comment_author_url' => $c['comment_author_url'], 'comment_author_IP' => $c['comment_author_IP'], 'comment_date' => $c['comment_date'],
		'comment_date_gmt' => $c['comment_date_gmt'], 'comment_content' => $c['comment_content'], 'comment_approved' => $c['comment_approved'],
		'comment_agent' => $c['comment_agent'], 'comment_type' => $c['comment_type'], 'comment_parent' => $map[ $parent ] ?? 0, 'user_id' => (int) $c['user_id'] );
	$id = wp_insert_comment( wp_slash( $data ) );
	$map[ (int) $c['comment_ID'] ] = $id;
	$out[] = array( $c['comment_ID'], $id ? 'ok' : 'erreur' );
}
return $out;
"""


def main():
    prod, pre = php(PROD, INVENTORY), php(PRE, INVENTORY)
    key = lambda r: (r['post_type'], r['post_name'] or '#' + r['ID'])  # noqa: E731
    p_rows, s_rows = {key(r): r for r in prod['rows']}, {key(r): r for r in pre['rows']}
    # Date de la copie : dernier contenu identique (même ID, même date de modification) sur les deux sites.
    s_by_id = {r['ID']: r for r in pre['rows']}
    t0 = max(r['post_modified_gmt'] for r in prod['rows'] if r['ID'] in s_by_id and s_by_id[r['ID']]['post_modified_gmt'] == r['post_modified_gmt'])
    new = [r for k, r in p_rows.items() if k not in s_rows]
    # Déjà repris lors d'un passage précédent : même version de la prod (date de modification gardée en méta).
    synced = {(r['post_type'], r['post_name']): r['meta_value'] for r in pre.get('synced') or []}
    changed = [r for k, r in p_rows.items() if k in s_rows and r['post_modified_gmt'] > t0 and synced.get(k) != r['post_modified_gmt']]
    new_media = [r for r in new if r['post_type'] == 'attachment']
    todo = [r for r in new + changed if r['post_type'] in ('post', 'page')]
    # Commentaires : ceux de la prod absents du preprod (même date, même auteur), hors pings et spam. Le texte n'entre pas
    # dans la comparaison : la copie du preprod y a remplacé le domaine.
    s_sig = {(c['comment_date_gmt'], c['comment_author_email']) for c in pre['comments']}
    new_comments = [c for c in prod['comments'] if (c['comment_date_gmt'], c['comment_author_email']) not in s_sig
                    and c['comment_type'] in ('comment', '') and c['comment_approved'] in ('0', '1')]
    # Pings créés sur le preprod après la copie (liens internes, pendant la migration) : à supprimer.
    stray = [c['comment_ID'] for c in pre['comments'] if c['comment_type'] == 'pingback' and c['comment_date_gmt'] > t0]
    print('copie du preprod :', t0)
    print('à reprendre :', [(r['post_type'], r['post_name'], 'nouveau' if r in new else 'modifié') for r in todo])
    print('médias nouveaux :', [r['guid'] for r in new_media])
    print('commentaires nouveaux :', len(new_comments), '| pings parasites du preprod :', len(stray))
    if not APPLY:
        return
    if todo:
        rows = php(PROD, EXPORT % json.dumps([r['ID'] for r in todo]))
        media = php(PRE, "return array_map( static fn( $a ) => array( 'id' => $a->ID, 'source_url' => wp_get_attachment_url( $a->ID ) ), get_posts( array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => 'inherit' ) ) );")
        resolved = {}
        for line in open(os.path.join(HERE, 'liens_courts.tsv')):
            if '\t' in line:
                s, r = line.rstrip('\n').split('\t', 1)
                resolved[s.rstrip('/')] = r or s
        conv = Converter(resolved, {os.path.basename(m['source_url']).lower(): m['id'] for m in media})
        for r in rows:
            if r['type'] == 'post':
                c = conv.convert({'id': r['id'], 'slug': r['slug'], 'title': r['title'], 'content': r['content']})
                r['content'] = c['content'].replace('https://tourisme-thailande.fr', PRE)
                r['meta'].setdefault('tt_gyg_query', c['meta']['tt_gyg_query'])
                print('  converti', r['slug'], c['log'][:6])
            else:
                r['content'] = r['content'].replace(PROD, PRE)
        import base64
        print(php(PRE, IMPORT % base64.b64encode(json.dumps(rows, ensure_ascii=False).encode()).decode()))
    if new_comments:
        import base64
        full = php(PROD, COMMENTS % json.dumps([c['comment_ID'] for c in new_comments]))
        print('commentaires :', php(PRE, IMPORT_COMMENTS % base64.b64encode(json.dumps(full, ensure_ascii=False).encode()).decode()))
    if stray:
        print('pings supprimés :', php(PRE, 'foreach ( %s as $id ) { wp_delete_comment( $id, true ); } return count( %s );' % (json.dumps([int(i) for i in stray]), json.dumps([int(i) for i in stray]))))
    php(PRE, "do_action( 'litespeed_purge_all' ); return 1;")


if __name__ == '__main__':
    main()
