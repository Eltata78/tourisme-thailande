<?php
/**
 * Tourisme (Thaïlande et autres destinations) : thème enfant de Twenty Twenty-Five.
 * Le pays du site se règle dans l'option tt_site (voir inc/site.php).
 */

defined( 'ABSPATH' ) || exit;

define( 'TT_VERSION', '0.1.0' );
define( 'TT_DIR', get_stylesheet_directory() );
define( 'TT_URI', get_stylesheet_directory_uri() );

foreach ( array( 'site', 'remote-setup', 'helpers', 'forms', 'setup', 'cleanup', 'headings', 'destination', 'catalog', 'schema', 'archive', 'search', 'blocks', 'compat', 'authors', 'consent', 'tracking', 'images', 'geo', 'article-cta', 'ratings', 'affiliate', 'favicon' ) as $tt_file ) {
	require_once TT_DIR . '/inc/' . $tt_file . '.php';
}
