# Tourisme Thaïlande : thème et migration

- `theme/` : thème enfant de Twenty Twenty-Five, déployé dans `wp-content/themes/tourisme-thailande`.
- `migration/` : scripts de conversion Elementor -> blocs Gutenberg et de mise en place du contenu.

## Thème

- Couleurs, typographie (Archivo variable auto-hébergée) et espacements dans `theme/theme.json`.
- Header : bloc `tt/site-header`, alimenté par le menu WordPress affecté à l'emplacement « Menu principal ».
  Niveau 1 = barre, niveau 2 = colonnes du méga-menu (classe CSS `tt-feat` = carte image), niveau 3 = liens.
- Footer : template part `footer`, entièrement en blocs natifs, modifiable dans l'éditeur de site.
- Templates : `front-page`, `page`, `page-destination`, `page-activite`, `single`, `single-guide`, `archive`, `search`, `404`.

## Blocs

| Bloc | Rôle |
| --- | --- |
| `tt/site-header` | Logo, méga-menu, recherche, bouton de réservation contextuel |
| `tt/hero` | Image LCP, H1, accroche, formulaire (recherche, Trip.com, GetYourGuide) |
| `tt/search-box` | Recherche avec suggestions instantanées |
| `tt/trip-search` | Formulaire hôtels Trip.com (dates, voyageurs, âges des enfants) |
| `tt/gyg-search` | Formulaire activités GetYourGuide (envie + date) |
| `tt/gyg-card` | Carte activité GetYourGuide |
| `tt/gyg-spotlight` | Mise en avant d'une activité (points forts, infos pratiques, prix, lien) |
| `tt/gyg-compare` | Comparatif de 2 à 4 activités sur les mêmes critères |
| `tt/card-grid` | Grille de cartes : sous-pages, pages sœurs, destinations ou sélection d'IDs |
| `tt/cta-card` | Carte de réservation : photo, titre, texte court, bouton vers l'offre (tag partenaire ajouté) |
| `tt/map` | Carte des visites (MapLibre auto-hébergé + fonds OpenFreeMap, noms en français), chargée à l'approche du bloc |
| `tt/toc` | Sommaire automatique (H2 du contenu) |
| `tt/mobile-bar` | Barre de réservation mobile |

Tous les blocs sont rendus côté serveur. Syntaxe pour le MCP, par exemple :

```
<!-- wp:tt/gyg-card {"title":"Les 4 îles de Krabi","imageId":123,"place":"Krabi","kind":"Excursion","rating":"4,7","reviews":"3 410","meta":"6 heures","price":"32","url":"https://www.getyourguide.fr/...","placement":"top-krabi"} /-->
```

## Métadonnées de page (API REST)

`tt_h1`, `tt_destination`, `tt_gyg_query`, `tt_trip_city`, `tt_intro`, `tt_price_from`, `tt_synonyms`, `tt_card_title`, `tt_geo`, `tt_map_pins`.
Une sous-page hérite de la destination de son hub parent. `tt_h1` remplace le titre dans le hero, `tt_card_title` sur les cartes.
`tt_trip_city` est une clé de `tt_trip_destinations()` (ex. `koh-samui`) ; `tt_geo` vaut « lat,lng » ; `tt_map_pins` liste en JSON
les lieux cités par un guide sans page dédiée (repères de la carte).

Style de paragraphe « Astuce » (`is-style-tt-tip`).

## Réglages partenaires

Option `tt_partners` : `gyg_partner_id`, `gyg_domain`, `trip_alliance_id`, `trip_sid`, `cta_default_label`, `cta_default_url`.
Chaque lien porte un identifiant de campagne `tt-{slug}-{emplacement}` (`cmp` chez GetYourGuide, `trip_sub1` chez Trip.com).
Hôtels : fr.trip.com en euros, paramètres de recherche par destination repris des liens générés dans l'espace partenaire Trip
(`tt_trip_destinations()` dans `inc/helpers.php` ; Krabi = province, Ban Krut = lieu). Aucun nom de partenaire n'est affiché sur le site.

## Migration des pages destination

1. `python3 -I migration/elementor2blocks.py <dossier _elementor_data> <pages.json> <sortie.json>` :
   convertit les pages Elementor (hubs et sous-pages) en blocs natifs et calcule les métadonnées.
   Coordonnées : `migration/geo.json` (pages) et `migration/geo_pins.json` (lieux des hubs), géocodées avec OpenStreetMap.
2. `migration/apply_destinations.php` (via execute-php, variable `$tt_rows`) : applique le contenu,
   le template `page-destination` et les métadonnées. Le contenu Elementor d'origine est gardé
   dans `_tt_backup_content`, `_tt_backup_elementor_mode`, `_tt_backup_template` et `_tt_backup_title`.
