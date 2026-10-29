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
| `tt/map` | Carte des fiches de la destination (MapLibre auto-hébergé + fonds OpenFreeMap, noms en français) : repère photo, fiche cliquable vers l'offre GetYourGuide de l'activité |
| `tt/toc` | Sommaire automatique (H2 du contenu) |
| `tt/mobile-bar` | Barre de réservation mobile |

Tous les blocs sont rendus côté serveur. Syntaxe pour le MCP, par exemple :

```
<!-- wp:tt/gyg-card {"title":"Les 4 îles de Krabi","imageId":123,"place":"Krabi","kind":"Excursion","rating":"4,7","reviews":"3 410","meta":"6 heures","price":"32","url":"https://www.getyourguide.fr/...","placement":"top-krabi"} /-->
```

## Métadonnées de page (API REST)

`tt_h1`, `tt_destination`, `tt_gyg_query`, `tt_trip_city`, `tt_intro`, `tt_price_from`, `tt_synonyms`, `tt_card_title`, `tt_geo`, `tt_gyg_url`.
Une sous-page hérite de la destination de son hub parent. `tt_h1` remplace le titre dans le hero, `tt_card_title` sur les cartes.
`tt_trip_city` est une clé de `tt_trip_destinations()` (ex. `koh-samui`) ; `tt_geo` vaut « lat,lng » (repère de la fiche sur la carte) ;
`tt_gyg_url` est le lien GetYourGuide de l'activité de la fiche (sinon la carte renvoie vers la recherche `tt_gyg_query`).

Style de paragraphe « Astuce » (`is-style-tt-tip`).

## Réglages partenaires

Option `tt_partners` : `gyg_partner_id`, `gyg_domain`, `trip_alliance_id`, `trip_sid`, `cta_default_label`, `cta_default_url`.
Chaque lien porte un identifiant de campagne `tt-{slug}-{emplacement}` (`cmp` chez GetYourGuide, `trip_sub1` chez Trip.com).
Hôtels : fr.trip.com en euros, paramètres de recherche par destination repris des liens générés dans l'espace partenaire Trip
(`tt_trip_destinations()` dans `inc/helpers.php` ; Krabi = province, Ban Krut = lieu). Aucun nom de partenaire n'est affiché sur le site.

## Migration des pages destination

1. `python3 -I migration/elementor2blocks.py <dossier _elementor_data> <pages.json> <sortie.json>` :
   convertit les pages Elementor (hubs et sous-pages) en blocs natifs et calcule les métadonnées.
   Coordonnées des fiches : `migration/geo.json`, géocodées avec OpenStreetMap.
2. `migration/apply_destinations.php` (via execute-php, variable `$tt_rows`) : applique le contenu,
   le template `page-destination` et les métadonnées. Le contenu Elementor d'origine est gardé
   dans `_tt_backup_content`, `_tt_backup_elementor_mode`, `_tt_backup_template` et `_tt_backup_title`.

## Migration des articles

1. Export du contenu des articles (id, slug, contenu) depuis le site.
2. `python3 -I migration/posts2blocks.py <articles.json> migration/liens_courts.tsv <media.json> <sortie.json>` :
   - encadrés HTML -> groupes aux styles « Le savais-tu » (`is-style-tt-savais`), « À retenir » (`is-style-tt-retenir`), « Attention » (`is-style-tt-alerte`) ;
   - CTA hôtel Trip -> `tt/trip-search` (destination de l'article si Trip la connaît) ; CTA activité et bannières Shortcoder -> `tt/cta-card` ;
     sélections de 3 activités -> `tt/gyg-card` ; programmes horaires -> groupe `is-style-tt-timeline` ; FAQ maison -> blocs Détails ;
   - espaceurs et émojis retirés (sauf FAQ Yoast) ; requête d'activités de l'article dans la meta `tt_gyg_query`.
   `migration/liens_courts.tsv` : liens courts gyg.me / trip.com/t déroulés (cible réelle de chaque lien).
3. `migration/apply_posts.php` (via execute-php, variable `$tt_rows`) : contenu d'origine gardé dans `_tt_backup_content`
   (et `_tt_backup_elementor_mode` pour les articles qui étaient marqués Elementor).
