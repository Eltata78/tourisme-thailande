<?php
/**
 * Données structurées des pages qui affichent la carte des visites (schema.org).
 *
 * Fiche géolocalisée : TouristAttraction (nom, description, photo, coordonnées), rattachée à sa destination
 * quand elle s'y trouve vraiment (moins de 40 km du centre : une excursion lointaine n'est pas « dans » Bangkok).
 * Hub : TouristDestination et ses attractions. Les nœuds rejoignent le graphe Yoast, dont la WebPage
 * pointe vers le lieu (about, contentLocation). Sans Yoast, le graphe est publié seul.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

const TT_PLACE_RADIUS_KM = 40;

/**
 * Nœuds schema.org de la page courante, ou tableau vide si elle n'affiche pas de carte.
 *
 * @return array{nodes:array<int,array>,about:string,location:string}
 */
function tt_place_schema(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array( 'nodes' => array(), 'about' => '', 'location' => '' );
	$post  = is_page() ? get_queried_object() : null;
	if ( ! $post instanceof WP_Post || ! has_block( 'tt/map', $post ) ) {
		return $cache;
	}
	$map = tt_map_places( $post );
	if ( ! $map['places'] ) {
		return $cache; // pas de repère : la carte ne s'affiche pas
	}
	$hub      = $map['hub'];
	$hub_geo  = tt_geo( $hub->ID );
	$dest     = (string) get_post_meta( $hub->ID, 'tt_destination', true );
	$inside   = static fn( WP_Post $p ) => $hub_geo && $dest && tt_km( tt_geo( $p->ID ), $hub_geo ) <= TT_PLACE_RADIUS_KM;
	$dest_ref = $dest ? array( '@id' => get_permalink( $hub ) . '#place' ) : null;

	if ( $post->ID === $hub->ID ) {
		$node = tt_schema_destination( $hub );
		$in   = array_values( array_filter( $map['places'], $inside ) );
		if ( $in ) {
			$node['includesAttraction'] = array_map( static fn( $p ) => tt_schema_attraction( $p, false ), $in );
		}
		$cache = array( 'nodes' => array( $node ), 'about' => $node['@id'], 'location' => $node['@id'] );
	} elseif ( tt_geo( $post->ID ) ) {
		$node  = tt_schema_attraction( $post, true );
		$nodes = array();
		if ( $dest_ref && $inside( $post ) ) {
			$node['containedInPlace']           = $dest_ref;
			$node['address']['addressLocality'] = $dest;
			$nodes[]                            = tt_schema_destination( $hub, false );
		}
		array_unshift( $nodes, $node );
		$cache = array( 'nodes' => $nodes, 'about' => $node['@id'], 'location' => $node['@id'] );
	} elseif ( $dest_ref ) {
		// Activité sans lieu fixe (cours, balade…) : la page se déroule dans la destination.
		$node  = tt_schema_destination( $hub, false );
		$cache = array( 'nodes' => array( $node ), 'about' => '', 'location' => $node['@id'] );
	}
	return $cache;
}

/**
 * TouristAttraction d'une fiche.
 */
function tt_schema_attraction( WP_Post $p, bool $full ): array {
	$geo  = tt_geo( $p->ID );
	$node = array(
		'@type' => 'TouristAttraction',
		'@id'   => get_permalink( $p ) . '#place',
		'name'  => (string) get_post_meta( $p->ID, 'tt_card_title', true ) ?: wp_strip_all_tags( get_the_title( $p ) ),
		'url'   => get_permalink( $p ),
		'geo'   => array( '@type' => 'GeoCoordinates', 'latitude' => $geo[0], 'longitude' => $geo[1] ),
	);
	$img = get_the_post_thumbnail_url( $p, 'full' );
	if ( $img ) {
		$node['image'] = $img;
	}
	if ( $full ) {
		$intro = (string) get_post_meta( $p->ID, 'tt_intro', true );
		if ( '' !== $intro ) {
			$node['description'] = $intro;
		}
		$node['address'] = array( '@type' => 'PostalAddress', 'addressCountry' => 'TH' );
	}
	return $node;
}

/**
 * TouristDestination d'un hub ; version courte quand elle n'est que référencée.
 */
function tt_schema_destination( WP_Post $hub, bool $full = true ): array {
	$node = array(
		'@type' => 'TouristDestination',
		'@id'   => get_permalink( $hub ) . '#place',
		'name'  => (string) get_post_meta( $hub->ID, 'tt_destination', true ) ?: wp_strip_all_tags( get_the_title( $hub ) ),
		'url'   => get_permalink( $hub ),
	);
	$geo = tt_geo( $hub->ID );
	if ( $geo ) {
		$node['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => $geo[0], 'longitude' => $geo[1] );
	}
	if ( $full ) {
		$intro = (string) get_post_meta( $hub->ID, 'tt_intro', true );
		if ( '' !== $intro ) {
			$node['description'] = $intro;
		}
		$img = get_the_post_thumbnail_url( $hub, 'full' );
		if ( $img ) {
			$node['image'] = $img;
		}
		$node['containedInPlace'] = array( '@type' => 'Country', 'name' => 'Thaïlande', 'identifier' => 'TH' );
	}
	return $node;
}

// Avec Yoast : les lieux rejoignent son graphe et la WebPage les désigne.
add_filter(
	'wpseo_schema_graph',
	static function ( $graph ) {
		$s = tt_place_schema();
		if ( ! $s['nodes'] || ! is_array( $graph ) ) {
			return $graph;
		}
		foreach ( $graph as &$piece ) {
			$types = (array) ( $piece['@type'] ?? array() );
			if ( in_array( 'WebPage', $types, true ) ) {
				if ( $s['about'] ) {
					$piece['about'] = array( '@id' => $s['about'] );
				}
				$piece['contentLocation'] = array( '@id' => $s['location'] );
			}
		}
		unset( $piece );
		return array_merge( $graph, $s['nodes'] );
	}
);

// Sans Yoast : graphe publié seul.
add_action(
	'wp_head',
	static function () {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return;
		}
		$s = tt_place_schema();
		if ( $s['nodes'] ) {
			printf(
				'<script type="application/ld+json">%s</script>' . "\n",
				wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $s['nodes'] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
			);
		}
	},
	30
);
