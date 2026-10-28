"""Déploie theme/ sur un site WordPress via l'ability Novamira « execute-php ».

Usage : python3 deploy.py https://preprod.tourisme-thailande.fr <fichier d'identifiants user:app-password> [--activate]

L'ancienne version du thème est conservée dans wp-content/themes/tourisme-thailande.prev.
Avec l'en-tête X-TT-Safe: hello (mu-plugin tt-safe-switch), la requête tourne sur l'ancien thème :
le déploiement reste possible même si la version active du thème plante.
"""
import base64
import io
import json
import os
import sys
import urllib.request
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE, AUTH = sys.argv[1].rstrip('/'), open(sys.argv[2]).read().strip()
ACTIVATE = '--activate' in sys.argv


def php(code: str, safe: bool = True):
    req = urllib.request.Request(
        SITE + '/wp-json/wp-abilities/v1/abilities/novamira/execute-php/run',
        data=json.dumps({'input': {'code': code}}).encode(),
        headers={'Content-Type': 'application/json', 'Authorization': 'Basic ' + base64.b64encode(AUTH.encode()).decode(),
                 **({'X-TT-Safe': 'hello'} if safe else {})},
        method='POST')
    with urllib.request.urlopen(req, timeout=120) as r:
        return json.load(r)


buf = io.BytesIO()
with zipfile.ZipFile(buf, 'w', zipfile.ZIP_DEFLATED) as z:
    for base, _, files in os.walk(os.path.join(ROOT, 'theme')):
        for f in files:
            full = os.path.join(base, f)
            z.write(full, 'tourisme-thailande/' + os.path.relpath(full, os.path.join(ROOT, 'theme')))
payload = base64.b64encode(buf.getvalue()).decode()

code = r'''
$themes = get_theme_root();
$dir    = $themes . '/tourisme-thailande';
$prev   = $themes . '/tourisme-thailande.prev';
$tmp    = get_temp_dir() . 'tt-theme-' . wp_generate_password( 8, false ) . '.zip';
file_put_contents( $tmp, base64_decode( '%s' ) );
$rm = static function ( $d ) use ( &$rm ) { foreach ( array_diff( scandir( $d ), array( '.', '..' ) ) as $f ) { is_dir( "$d/$f" ) ? $rm( "$d/$f" ) : unlink( "$d/$f" ); } rmdir( $d ); };
if ( is_dir( $prev ) ) { $rm( $prev ); }
if ( is_dir( $dir ) ) { rename( $dir, $prev ); }
$zip = new ZipArchive();
if ( true !== $zip->open( $tmp ) ) { return 'zip illisible'; }
$zip->extractTo( $themes );
$zip->close();
unlink( $tmp );
if ( function_exists( 'opcache_reset' ) ) { opcache_reset(); }
wp_clean_themes_cache();
$t = wp_get_theme( 'tourisme-thailande' );
$out = array( 'version' => $t->get( 'Version' ), 'errors' => $t->errors() ? $t->errors()->get_error_messages() : array(), 'files' => count( $t->get_files( null, -1 ) ) );
if ( %s ) { switch_theme( 'tourisme-thailande' ); $out['active'] = get_stylesheet(); }
return $out;
''' % (payload, 'true' if ACTIVATE else 'false')

print(json.dumps(php(code), ensure_ascii=False))
