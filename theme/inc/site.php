<?php
/**
 * Destination du site : un même thème pour tourisme-thailande.fr et les sites des autres pays.
 *
 * L'option `tt_site` choisit un profil (`profile` : thailande, grece, portugal…) et peut en surcharger
 * chaque valeur. Sans option, le profil est celui de la Thaïlande : tourisme-thailande.fr ne change pas.
 * L'option est ouverte à l'API REST (/wp/v2/settings) pour régler un nouveau site à distance.
 *
 * @package tt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saisons du bloc mois par mois : trois teintes (cool, hot, wet) dont le libellé change selon le pays.
 * Mois : [saison, accroche] dans l'ordre de janvier à décembre.
 */
function tt_site_profiles(): array {
	$generic_kinds = array(
		'culture'     => 'Sites et culture',
		'iles'        => 'Îles et bateau',
		'nature'      => 'Nature et randonnée',
		'plongee'     => 'Plongée et snorkeling',
		'gastronomie' => 'Gastronomie et marchés',
		'sorties'     => 'Spectacles et sorties',
		'experiences' => 'Expériences et loisirs',
	);
	$generic_ideas = array( 'Visite guidée', 'Billet coupe-file', 'Excursion à la journée', 'Excursion en bateau', 'Croisière', 'Snorkeling', 'Plongée', 'Randonnée', 'Kayak', 'Tour à vélo', 'Visite gastronomique', 'Cours de cuisine', 'Dégustation', 'Spectacle', 'Quad', 'Transfert aéroport' );
	$summer        = array(
		'seasons' => array(
			'hot'  => array( 'Haute saison', 'juin à août' ),
			'cool' => array( 'Intersaison', 'avril-mai, septembre-octobre' ),
			'wet'  => array( 'Basse saison', 'novembre à mars' ),
		),
		'months'  => array( 'wet', 'wet', 'wet', 'cool', 'cool', 'hot', 'hot', 'hot', 'cool', 'cool', 'wet', 'wet' ),
	);

	return array(
		'thailande' => array(
			'name'                => 'Thaïlande',
			'in'                  => 'en Thaïlande',
			'the'                 => 'la Thaïlande',
			'whole'               => 'Toute la Thaïlande',
			'everywhere'          => 'Partout en Thaïlande',
			'country_code'        => 'th',
			'brand'               => 'Tourisme Thaïlande',
			'emblem'              => 'assets/img/emblem.webp',
			'search_placeholder'  => 'Bangkok, Wat Pho, full moon party…',
			'catalog_placeholder' => 'Rechercher : Wat Pho, plongée, éléphants…',
			'activity_placeholder' => 'Marché flottant, Muay Thai…',
			'ideas'               => array( 'Visite guidée', 'Temples', 'Marché flottant', 'Marché de nuit', 'Street food', 'Cours de cuisine thaï', 'Muay Thai', 'Massage thaï', 'Croisière', 'Excursion en bateau', 'Excursion dans les îles', 'Snorkeling', 'Plongée', 'Sanctuaire d\'éléphants', 'Randonnée', 'Kayak', 'Tyrolienne', 'Tour à vélo', 'Spectacle', 'Excursion à la journée', 'Transfert aéroport' ),
			'kinds'               => array(
				'temples'     => 'Temples et culture',
				'marches'     => 'Marchés',
				'iles'        => 'Îles et bateau',
				'nature'      => 'Nature et parcs',
				'plongee'     => 'Plongée et snorkeling',
				'sorties'     => 'Spectacles et sorties',
				'experiences' => 'Expériences et loisirs',
			),
			'regions'             => array(
				'Le Nord'              => array( 'Chiang Mai', 'Chiang Rai', 'Pai', 'Mae Hong Son' ),
				'Bangkok et le Centre' => array( 'Bangkok', 'Ayutthaya', 'Pattaya', 'Kanchanaburi', 'Lopburi' ),
				'Golfe de Thaïlande'   => array( 'Koh Samui', 'Koh Phangan', 'Koh Tao', 'Ban Krut', 'Hua Hin' ),
				"Mer d'Andaman"        => array( 'Phuket', 'Krabi', 'Koh Phi Phi', 'Koh Lanta', 'Khao Lak' ),
			),
			'seasons'             => array(
				'cool' => array( 'Saison fraîche', 'novembre à février' ),
				'hot'  => array( 'Saison chaude', 'mars à mai' ),
				'wet'  => array( 'Mousson', 'juin à octobre' ),
			),
			'months'              => array(
				array( 'cool', 'Sec et doux partout, pleine haute saison' ),
				array( 'cool', 'Le meilleur mois pour combiner les deux côtes' ),
				array( 'hot', "La chaleur monte, la mer d'Andaman reste calme" ),
				array( 'hot', 'Le mois le plus chaud, Songkran le 13' ),
				array( 'hot', 'Premières pluies côté Phuket et Krabi' ),
				array( 'wet', "Averses l'après-midi, Golfe ensoleillé" ),
				array( 'wet', 'Koh Samui et Koh Tao en pleine saison' ),
				array( 'wet', 'Prix en baisse à Phuket, foule dans le Golfe' ),
				array( 'wet', 'Le plus pluvieux, sauf autour de Samui' ),
				array( 'wet', 'Le Nord sèche, le Golfe se mouille' ),
				array( 'cool', 'Loy Krathong et Yi Peng à Chiang Mai' ),
				array( 'cool', 'Haute saison, réserve tôt' ),
			),
			'trip'                => array(
				'bangkok'     => array( 'Bangkok', array( 'city' => 359 ) ),
				'ayutthaya'   => array( 'Ayutthaya', array( 'city' => 36030 ) ),
				'pattaya'     => array( 'Pattaya', array( 'city' => 622 ) ),
				'chiang-mai'  => array( 'Chiang Mai', array( 'city' => 623 ) ),
				'chiang-rai'  => array( 'Chiang Rai', array( 'city' => 647 ) ),
				'phuket'      => array( 'Phuket', array( 'city' => 725 ) ),
				'krabi'       => array( 'Krabi', array( 'city' => -1, 'provinceId' => 10129, 'countryId' => 4, 'searchType' => 'P', 'searchWord' => 'Krabi', 'searchValue' => '39|10129*39*10129*1' ) ),
				'koh-phi-phi' => array( 'Koh Phi Phi', array( 'city' => 1228 ) ),
				'koh-samui'   => array( 'Koh Samui', array( 'city' => 1229 ) ),
				'koh-tao'     => array( 'Koh Tao', array( 'city' => 19957 ) ),
				'ban-krut'    => array( 'Ban Krut', array( 'cityId' => 62158, 'provinceId' => 11042, 'countryId' => 4, 'searchType' => 'LM', 'optionId' => 8248394, 'searchValue' => '13|8248394*13*11.3451418|99.5644769|Ban Krut Beach|8248394|1', 'old' => 1 ) ),
			),
			'footer'              => array(
				array( 'Réserver sur place', array( array( 'Muay Thai à Bangkok', '/ou-voir-muay-thai-bangkok/' ), array( 'Se faire tatouer un Sak Yant', '/sak-yant-tatouage/' ), array( 'Île aux cochons à Koh Samui', '/koh-samui/ile-aux-cochons-koh-madsum-samui/' ), array( 'Nager avec le requin-baleine', '/requin-baleine-thailande/' ), array( 'Surf en Thaïlande', '/surf-thailande/' ) ) ),
				array( 'Se déplacer', array( array( 'Le train de nuit', '/voyager-train-de-nuit-thailande/' ), array( 'Réserver un vol intérieur', '/reserver-vol-interieur-thailande/' ), array( 'Grab et Bolt', '/applications-transport-thailande-grab-bolt/' ), array( 'Les applications indispensables', '/meilleures-applications-voyage-thailande/' ) ) ),
				array( 'Bien préparer', array( array( 'Voyager avec un bébé', '/voyager-thailande-avec-bebe/' ), array( 'Trousse à pharmacie', '/trousse-a-pharmacie-thailande/' ), array( "Numéros d'urgence", '/numeros-urgence-utiles-thailande/' ), array( "Boire l'eau du robinet ?", '/boire-eau-robinet-thailande/' ), array( 'Thaïlande ou Philippines ?', '/philippines-ou-thailande-choisir-destination/' ) ) ),
			),
			'gtm_id'              => 'GTM-M8XRRSXB',
			'cmp'                 => 'tt',
		),

		'grece'     => array(
			'name'                 => 'Grèce',
			'in'                   => 'en Grèce',
			'the'                  => 'la Grèce',
			'whole'                => 'Toute la Grèce',
			'everywhere'           => 'Partout en Grèce',
			'country_code'         => 'gr',
			'search_placeholder'   => 'Santorin, Acropole, Météores…',
			'catalog_placeholder'  => 'Rechercher : Acropole, catamaran, Balos…',
			'activity_placeholder' => 'Catamaran, Acropole…',
			'regions'              => array(
				'Athènes et le continent' => array( 'Athènes', 'Delphes', 'Météores', 'Nauplie', 'Thessalonique', 'Mont Olympe' ),
				'Cyclades'                => array( 'Santorin', 'Mykonos', 'Naxos', 'Paros', 'Milos' ),
				'Crète'                   => array( 'Héraklion', 'La Canée', 'Réthymnon', 'Agios Nikolaos', 'Elounda' ),
				'Dodécanèse'              => array( 'Rhodes', 'Kos', 'Lindos' ),
				'Îles Ioniennes'          => array( 'Corfou', 'Zante', 'Céphalonie', 'Lefkada' ),
			),
		) + $summer,

		'portugal'  => array(
			'name'                 => 'Portugal',
			'in'                   => 'au Portugal',
			'the'                  => 'le Portugal',
			'whole'                => 'Tout le Portugal',
			'everywhere'           => 'Partout au Portugal',
			'country_code'         => 'pt',
			'search_placeholder'   => 'Lisbonne, Sintra, grotte de Benagil…',
			'catalog_placeholder'  => 'Rechercher : Benagil, fado, Douro…',
			'activity_placeholder' => 'Benagil, croisière sur le Douro…',
			'regions'              => array(
				'Lisbonne et sa région' => array( 'Lisbonne', 'Sintra', 'Cascais', 'Óbidos', 'Nazaré', 'Fátima' ),
				'Porto et le Nord'      => array( 'Porto', 'Vallée du Douro', 'Braga', 'Guimarães', 'Aveiro' ),
				'Algarve'               => array( 'Faro', 'Lagos', 'Albufeira', 'Benagil', 'Portimão', 'Tavira' ),
				'Îles'                  => array( 'Madère', 'Funchal', 'Açores' ),
			),
			'seasons'              => array(
				'hot'  => array( 'Haute saison', 'juin à septembre' ),
				'cool' => array( 'Intersaison', 'avril-mai, octobre' ),
				'wet'  => array( 'Basse saison', 'novembre à mars' ),
			),
			'months'               => array( 'wet', 'wet', 'wet', 'cool', 'cool', 'hot', 'hot', 'hot', 'hot', 'cool', 'wet', 'wet' ),
		),

		'mexique'   => array(
			'name'                 => 'Mexique',
			'in'                   => 'au Mexique',
			'the'                  => 'le Mexique',
			'whole'                => 'Tout le Mexique',
			'everywhere'           => 'Partout au Mexique',
			'country_code'         => 'mx',
			'search_placeholder'   => 'Tulum, cenotes, Chichén Itzá…',
			'catalog_placeholder'  => 'Rechercher : cenote, Chichén Itzá, Xcaret…',
			'activity_placeholder' => 'Cenote, ruines mayas…',
			'regions'              => array(
				'Riviera Maya et Yucatán' => array( 'Cancún', 'Playa del Carmen', 'Tulum', 'Isla Mujeres', 'Cozumel', 'Holbox', 'Bacalar', 'Mérida', 'Valladolid', 'Chichén Itzá' ),
				'Mexico et le Centre'     => array( 'Mexico', 'Teotihuacán', 'Puebla', 'Guanajuato', 'San Miguel de Allende' ),
				'Le Sud'                  => array( 'Oaxaca', 'San Cristóbal de las Casas', 'Palenque' ),
				'Côte Pacifique'          => array( 'Puerto Vallarta', 'Los Cabos', 'La Paz' ),
			),
			'seasons'              => array(
				'cool' => array( 'Saison sèche', 'novembre à avril' ),
				'hot'  => array( 'Saison chaude', 'mai-juin' ),
				'wet'  => array( 'Saison des pluies', 'juillet à octobre' ),
			),
			'months'               => array( 'cool', 'cool', 'cool', 'cool', 'hot', 'hot', 'wet', 'wet', 'wet', 'wet', 'cool', 'cool' ),
		),

		'turquie'   => array(
			'name'                 => 'Turquie',
			'in'                   => 'en Turquie',
			'the'                  => 'la Turquie',
			'whole'                => 'Toute la Turquie',
			'everywhere'           => 'Partout en Turquie',
			'country_code'         => 'tr',
			'search_placeholder'   => 'Istanbul, Cappadoce, Pamukkale…',
			'catalog_placeholder'  => 'Rechercher : montgolfière, Bosphore, hammam…',
			'activity_placeholder' => 'Montgolfière, croisière sur le Bosphore…',
			'regions'              => array(
				'Istanbul'           => array( 'Istanbul' ),
				'Cappadoce'          => array( 'Cappadoce', 'Göreme', 'Ürgüp' ),
				'Côte égéenne'       => array( 'Izmir', 'Éphèse', 'Kusadasi', 'Bodrum', 'Pamukkale' ),
				'Côte méditerranéenne' => array( 'Antalya', 'Side', 'Alanya', 'Kemer', 'Fethiye', 'Marmaris' ),
			),
		) + $summer,

		'albanie'   => array(
			'name'                 => 'Albanie',
			'in'                   => 'en Albanie',
			'the'                  => "l'Albanie",
			'whole'                => "Toute l'Albanie",
			'everywhere'           => 'Partout en Albanie',
			'country_code'         => 'al',
			'search_placeholder'   => 'Ksamil, Tirana, Berat…',
			'catalog_placeholder'  => 'Rechercher : Ksamil, Butrint, Blue Eye…',
			'activity_placeholder' => 'Bateau à Ksamil, Butrint…',
			'regions'              => array(
				'Riviera albanaise' => array( 'Ksamil', 'Saranda', 'Himara', 'Dhërmi', 'Butrint' ),
				'Tirana et le Centre' => array( 'Tirana', 'Durrës', 'Kruja', 'Berat' ),
				'Le Sud'            => array( 'Gjirokastër', 'Vlora' ),
				'Le Nord'           => array( 'Shkodër', 'Theth', 'Valbona', 'Lac de Koman' ),
			),
		) + $summer,

		'canaries'  => array(
			'name'                 => 'Canaries',
			'in'                   => 'aux Canaries',
			'the'                  => 'les Canaries',
			'whole'                => 'Toutes les Canaries',
			'everywhere'           => 'Partout aux Canaries',
			'country_code'         => 'es',
			'schema_place'         => array( 'AdministrativeArea', 'Îles Canaries', 'ES-CN' ),
			'search_placeholder'   => 'Teide, Lanzarote, Siam Park…',
			'catalog_placeholder'  => 'Rechercher : Teide, dauphins, Timanfaya…',
			'activity_placeholder' => 'Teide, observation des dauphins…',
			'regions'              => array(
				'Tenerife'       => array( 'Tenerife', 'Teide', 'Los Cristianos', 'Costa Adeje', 'Puerto de la Cruz', 'Los Gigantes' ),
				'Gran Canaria'   => array( 'Gran Canaria', 'Las Palmas', 'Maspalomas', 'Puerto de Mogán' ),
				'Lanzarote'      => array( 'Lanzarote', 'Timanfaya', 'Playa Blanca', 'Puerto del Carmen' ),
				'Fuerteventura'  => array( 'Fuerteventura', 'Corralejo', 'Morro Jable' ),
				'Autres îles'    => array( 'La Palma', 'La Gomera', 'El Hierro' ),
			),
			'seasons'              => array(
				'cool' => array( 'Hiver doux', 'novembre à mars' ),
				'hot'  => array( 'Été', 'juin à septembre' ),
				'wet'  => array( 'Intersaison', 'avril-mai, octobre' ),
			),
			'months'               => array( 'cool', 'cool', 'cool', 'wet', 'wet', 'hot', 'hot', 'hot', 'hot', 'wet', 'cool', 'cool' ),
		),

		'egypte'    => array(
			'name'                 => 'Égypte',
			'in'                   => 'en Égypte',
			'the'                  => "l'Égypte",
			'whole'                => "Toute l'Égypte",
			'everywhere'           => 'Partout en Égypte',
			'country_code'         => 'eg',
			'search_placeholder'   => 'Louxor, pyramides de Gizeh, Hurghada…',
			'catalog_placeholder'  => 'Rechercher : pyramides, croisière sur le Nil, snorkeling…',
			'activity_placeholder' => 'Pyramides, croisière sur le Nil…',
			'regions'              => array(
				'Le Caire et Gizeh' => array( 'Le Caire', 'Gizeh', 'Saqqarah' ),
				'Vallée du Nil'     => array( 'Louxor', 'Assouan', 'Abou Simbel', 'Edfou', 'Kom Ombo' ),
				'Mer Rouge'         => array( 'Hurghada', 'Sharm el-Sheikh', 'Marsa Alam', 'El Gouna', 'Safaga', 'Dahab' ),
				'Le Nord'           => array( 'Alexandrie' ),
			),
			'seasons'              => array(
				'cool' => array( 'Saison idéale', 'octobre à avril' ),
				'hot'  => array( 'Saison chaude', 'juin à août' ),
				'wet'  => array( 'Intersaison', 'mai, septembre' ),
			),
			'months'               => array( 'cool', 'cool', 'cool', 'cool', 'wet', 'hot', 'hot', 'hot', 'wet', 'cool', 'cool', 'cool' ),
		),

		'indonesie' => array(
			'name'                 => 'Indonésie',
			'in'                   => 'en Indonésie',
			'the'                  => "l'Indonésie",
			'whole'                => "Toute l'Indonésie",
			'everywhere'           => 'Partout en Indonésie',
			'country_code'         => 'id',
			'search_placeholder'   => 'Ubud, Gili Air, Bromo…',
			'catalog_placeholder'  => 'Rechercher : Nusa Penida, rizières, Komodo…',
			'activity_placeholder' => 'Nusa Penida, volcan Bromo…',
			'regions'              => array(
				'Bali'               => array( 'Ubud', 'Seminyak', 'Canggu', 'Uluwatu', 'Sanur', 'Amed', 'Nusa Penida', 'Nusa Lembongan' ),
				'Lombok et les Gili' => array( 'Lombok', 'Gili Trawangan', 'Gili Air', 'Gili Meno' ),
				'Java'               => array( 'Yogyakarta', 'Borobudur', 'Bromo', 'Ijen', 'Jakarta' ),
				'Flores et Komodo'   => array( 'Labuan Bajo', 'Komodo' ),
				'Autres îles'        => array( 'Sumatra', 'Sulawesi', 'Raja Ampat' ),
			),
			'seasons'              => array(
				'cool' => array( 'Saison sèche', 'avril à octobre' ),
				'hot'  => array( 'Haute saison', 'juillet-août' ),
				'wet'  => array( 'Saison des pluies', 'novembre à mars' ),
			),
			'months'               => array( 'wet', 'wet', 'wet', 'cool', 'cool', 'cool', 'hot', 'hot', 'cool', 'cool', 'wet', 'wet' ),
		),

		'vietnam'   => array(
			'name'                 => 'Vietnam',
			'in'                   => 'au Vietnam',
			'the'                  => 'le Vietnam',
			'whole'                => 'Tout le Vietnam',
			'everywhere'           => 'Partout au Vietnam',
			'country_code'         => 'vn',
			'search_placeholder'   => "Baie d'Halong, Hoi An, Hanoi…",
			'catalog_placeholder'  => "Rechercher : baie d'Halong, Ninh Binh, delta du Mékong…",
			'activity_placeholder' => "Croisière dans la baie d'Halong…",
			'regions'              => array(
				'Le Nord'   => array( 'Hanoi', "Baie d'Halong", 'Ninh Binh', 'Sapa', 'Ha Giang' ),
				'Le Centre' => array( 'Hué', 'Da Nang', 'Hoi An', 'Phong Nha' ),
				'Le Sud'    => array( 'Hô Chi Minh-Ville', 'Delta du Mékong', 'Phu Quoc', 'Nha Trang', 'Dalat', 'Mui Ne' ),
			),
			'seasons'              => array(
				'cool' => array( 'Saison idéale', 'février à avril' ),
				'hot'  => array( 'Saison chaude', 'mai à août' ),
				'wet'  => array( 'Pluies et fraîcheur', 'septembre à janvier' ),
			),
			'months'               => array( 'wet', 'cool', 'cool', 'cool', 'hot', 'hot', 'hot', 'hot', 'wet', 'wet', 'wet', 'wet' ),
		),

		'croatie'   => array(
			'name'                 => 'Croatie',
			'in'                   => 'en Croatie',
			'the'                  => 'la Croatie',
			'whole'                => 'Toute la Croatie',
			'everywhere'           => 'Partout en Croatie',
			'country_code'         => 'hr',
			'search_placeholder'   => 'Dubrovnik, Plitvice, Hvar…',
			'catalog_placeholder'  => 'Rechercher : Krka, grotte bleue, Hvar…',
			'activity_placeholder' => 'Grotte bleue, parc de Krka…',
			'regions'              => array(
				'Dalmatie du Sud'  => array( 'Dubrovnik', 'Korčula', 'Mljet', 'Cavtat' ),
				'Dalmatie centrale' => array( 'Split', 'Trogir', 'Hvar', 'Brač', 'Vis', 'Makarska', 'Šibenik', 'Krka' ),
				'Dalmatie du Nord' => array( 'Zadar', 'Kornati', 'Plitvice' ),
				'Istrie et Kvarner' => array( 'Rovinj', 'Pula', 'Poreč', 'Rijeka' ),
				'Zagreb'           => array( 'Zagreb' ),
			),
		) + $summer,
	) + array( '_generic' => array( 'kinds' => $generic_kinds, 'ideas' => $generic_ideas ) );
}

/**
 * Réglages du site : profil choisi par l'option `tt_site`, surchargé par ses autres clés.
 */
function tt_site(): array {
	static $site = null;
	if ( null === $site ) {
		$opt      = (array) get_option( 'tt_site', array() );
		$profiles = tt_site_profiles();
		$slug     = sanitize_key( $opt['profile'] ?? 'thailande' );
		$profile  = $profiles[ $slug ] ?? $profiles['thailande'];
		$generic  = $profiles['_generic'];
		$site     = array_merge(
			array(
				'slug'                 => $slug,
				'brand'                => 'Tourisme ' . $profile['name'],
				'emblem'               => '',
				'schema_place'         => array( 'Country', $profile['name'], strtoupper( $profile['country_code'] ?? '' ) ),
				'kinds'                => $generic['kinds'],
				'ideas'                => $generic['ideas'],
				'trip'                 => array(),
				// Préfixe des identifiants de campagne (cmp, trip_sub1) : distingue les sites dans les rapports partenaires.
				'cmp'                  => $slug,
				// Colonnes de liens du pied de page (bloc tt/footer-links) : [titre, [[libellé, adresse]…]]…
				'footer'               => array(
					array(
						'Explorer',
						array(
							array( 'Activités ' . $profile['in'], '/activites-' . sanitize_title( $profile['name'] ) . '/' ),
							array( 'La carte interactive', '/carte-' . sanitize_title( $profile['name'] ) . '/' ),
						),
					),
				),
				'gtm_id'               => '',
				'search_placeholder'   => '',
				'catalog_placeholder'  => '',
				'activity_placeholder' => '',
			),
			$profile,
			array_diff_key( $opt, array( 'profile' => 1 ) )
		);
		// Mois : [saison, accroche] ; un profil peut ne donner que la saison de chaque mois.
		$site['months'] = array_map( static fn( $m ) => is_array( $m ) ? $m + array( 'cool', '' ) : array( (string) $m, '' ), array_values( (array) ( $site['months'] ?? array() ) ) );
	}
	return $site;
}

/** Nom du pays (« Thaïlande »), qui sert aussi de requête d'offres par défaut. */
function tt_country(): string {
	return (string) tt_site()['name'];
}

/** Slug du pays dans les adresses : /activites-{slug}/, /carte-{slug}/, articles {slug}-janvier… */
function tt_country_slug(): string {
	return sanitize_title( tt_country() );
}

/** Adresse du catalogue d'activités (/activites-thailande/). */
function tt_catalog_slug(): string {
	return 'activites-' . tt_country_slug();
}

/**
 * Textes des modèles (templates/, parts/) propres au pays : {{pays}}, {{le_pays}}, {{en_pays}}, {{marque}},
 * {{domaine}} (Tourisme-Thailande.fr), {{annee}}, {{catalogue}} (activites-thailande), {{carte}} (carte-thailande).
 */
function tt_site_tokens(): array {
	static $tokens = null;
	if ( null === $tokens ) {
		$site   = tt_site();
		$host   = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$tokens = array(
			'{{pays}}'      => $site['name'],
			'{{le_pays}}'   => $site['the'] ?? $site['name'],
			'{{en_pays}}'   => $site['in'],
			'{{marque}}'    => $site['brand'],
			'{{domaine}}'   => ucwords( $host, '-' ),
			'{{annee}}'     => wp_date( 'Y' ),
			'{{catalogue}}' => tt_catalog_slug(),
			'{{carte}}'     => 'carte-' . tt_country_slug(),
		);
		$tokens = array_map( static fn( $v ) => htmlspecialchars( (string) $v, ENT_NOQUOTES, 'UTF-8', false ), $tokens );
	}
	return $tokens;
}

add_filter(
	'render_block',
	static fn( $html ) => is_string( $html ) && str_contains( $html, '{{' ) ? strtr( $html, tt_site_tokens() ) : $html
);

/**
 * Réglages du site ouverts à l'API REST, pour configurer un nouveau site sans accès au serveur :
 * POST /wp/v2/settings {"tt_site": {"profile": "grece"}, "tt_partners": {...}}.
 */
add_action(
	'init',
	static function () {
		foreach ( array( 'tt_site', 'tt_partners' ) as $tt_opt ) {
			register_setting(
				'tt',
				$tt_opt,
				array(
					'type'         => 'object',
					'default'      => array(),
					'show_in_rest' => array(
						'schema' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				)
			);
		}
	}
);
