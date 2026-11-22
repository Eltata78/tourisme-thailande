<?php
/**
 * Flux RSS pour Pinterest : /feed/pinterest/
 *
 * Le flux standard de WordPress ne contient que les articles (posts), alors que les guides
 * destinations sont des pages. Celui-ci reprend les articles et les pages publiés qui ont une
 * image à la une, du plus récent au plus ancien, avec l'image en <enclosure> et <media:content>.
 * Pinterest le lit une fois par jour et crée une épingle par nouvel élément (« Publication
 * automatique » dans les paramètres du compte Pinterest Business) : rien d'autre à faire.
 */

defined( 'ABSPATH' ) || exit;

const TT_PINTEREST_FEED_ITEMS = 60;

/** Pages qui n'ont rien à faire sur Pinterest (légal, contact, catalogue, blog…). */
function tt_pinterest_excluded_slugs(): array {
	return (array) apply_filters(
		'tt_pinterest_excluded_slugs',
		array( 'mentions-legales', 'politique-de-confidentialite', 'qui-sommes-nous', 'contact', 'blog', 'plan-du-site', 'cookies', 'accueil' )
	);
}

add_action(
	'init',
	static function () {
		add_feed( 'pinterest', 'tt_pinterest_feed_render' );
	}
);

// Une seule fois après la mise à jour du thème : la nouvelle adresse /feed/pinterest/ doit être connue des règles de réécriture.
add_action(
	'init',
	static function () {
		if ( get_option( 'tt_pinterest_feed_rules' ) !== '1' ) {
			flush_rewrite_rules( false );
			update_option( 'tt_pinterest_feed_rules', '1', false );
		}
	},
	99
);

/** Image à la une la plus grande possible (Pinterest préfère les grandes images). */
function tt_pinterest_image( int $post_id ): ?array {
	$thumb = get_post_thumbnail_id( $post_id );
	if ( ! $thumb ) {
		return null;
	}
	$src = wp_get_attachment_image_src( $thumb, 'full' );
	if ( ! $src ) {
		return null;
	}
	$file = get_attached_file( $thumb );
	return array(
		'url'    => $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'mime'   => (string) ( get_post_mime_type( $thumb ) ?: 'image/jpeg' ),
		'length' => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0,
		'alt'    => (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ),
	);
}

/** Description courte : méta description Yoast, sinon extrait, sinon début du texte. */
function tt_pinterest_description( WP_Post $post ): string {
	$desc = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
	if ( '' === trim( $desc ) ) {
		$desc = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40, '…' );
	}
	return trim( wp_strip_all_tags( $desc ) );
}

function tt_pinterest_feed_render(): void {
	$excluded = array();
	foreach ( tt_pinterest_excluded_slugs() as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			$excluded[] = $page->ID;
		}
	}
	$front = (int) get_option( 'page_on_front' );
	if ( $front ) {
		$excluded[] = $front;
	}

	$posts = get_posts(
		array(
			'post_type'        => array( 'post', 'page' ),
			'post_status'      => 'publish',
			'posts_per_page'   => TT_PINTEREST_FEED_ITEMS,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'post__not_in'     => $excluded,
			'has_password'     => false,
			'meta_query'       => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
			'suppress_filters' => false,
		)
	);

	header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
	header( 'X-Robots-Tag: noindex, follow', true );

	$site  = get_bloginfo_rss( 'name' );
	$built = $posts ? mysql2date( 'D, d M Y H:i:s +0000', get_post_modified_time( 'Y-m-d H:i:s', true, $posts[0] ), false ) : gmdate( 'D, d M Y H:i:s +0000' );

	echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
	?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title><?php echo esc_html( $site ); ?></title>
	<link><?php echo esc_url( home_url( '/' ) ); ?></link>
	<atom:link href="<?php echo esc_url( home_url( '/feed/pinterest/' ) ); ?>" rel="self" type="application/rss+xml" />
	<description><?php echo esc_html( get_bloginfo( 'description' ) ?: $site ); ?></description>
	<language><?php echo esc_html( get_bloginfo( 'language' ) ); ?></language>
	<lastBuildDate><?php echo esc_html( $built ); ?></lastBuildDate>
	<?php
	foreach ( $posts as $post ) {
		$img = tt_pinterest_image( $post->ID );
		if ( ! $img ) {
			continue;
		}
		$link  = get_permalink( $post );
		$title = wp_strip_all_tags( get_the_title( $post ) );
		$desc  = tt_pinterest_description( $post );
		?>
	<item>
		<title><?php echo esc_html( $title ); ?></title>
		<link><?php echo esc_url( $link ); ?></link>
		<guid isPermaLink="true"><?php echo esc_url( $link ); ?></guid>
		<pubDate><?php echo esc_html( get_post_time( 'D, d M Y H:i:s +0000', true, $post ) ); ?></pubDate>
		<description><?php echo esc_html( $desc ); ?></description>
		<enclosure url="<?php echo esc_url( $img['url'] ); ?>" length="<?php echo (int) $img['length']; ?>" type="<?php echo esc_attr( $img['mime'] ); ?>" />
		<media:content url="<?php echo esc_url( $img['url'] ); ?>" medium="image" type="<?php echo esc_attr( $img['mime'] ); ?>" width="<?php echo (int) $img['width']; ?>" height="<?php echo (int) $img['height']; ?>">
			<media:title><?php echo esc_html( $title ); ?></media:title>
			<?php if ( '' !== $img['alt'] ) : ?>
			<media:description><?php echo esc_html( $img['alt'] ); ?></media:description>
			<?php endif; ?>
		</media:content>
	</item>
		<?php
	}
	?>
</channel>
</rss>
	<?php
}
