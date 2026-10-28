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
| `tt/toc` | Sommaire automatique (H2 du contenu) |
| `tt/mobile-bar` | Barre de réservation mobile |

Tous les blocs sont rendus côté serveur. Syntaxe pour le MCP, par exemple :

```
<!-- wp:tt/gyg-card {"title":"Les 4 îles de Krabi","imageId":123,"place":"Krabi","kind":"Excursion","rating":"4,7","reviews":"3 410","meta":"6 heures","price":"32","url":"https://www.getyourguide.fr/...","placement":"top-krabi"} /-->
```

## Métadonnées de page (API REST)

`tt_destination`, `tt_gyg_query`, `tt_trip_city`, `tt_intro`, `tt_price_from`, `tt_synonyms`.
Une sous-page hérite de la destination de son hub parent.

## Réglages partenaires

Option `tt_partners` : `gyg_partner_id`, `gyg_domain`, `trip_alliance_id`, `trip_sid`, `cta_default_label`, `cta_default_url`.
Chaque lien porte un identifiant de campagne `tt-{slug}-{emplacement}` (`cmp` chez GetYourGuide, `trip_sub1` chez Trip.com).
