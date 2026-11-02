# Tourisme Thaïlande : thème et migration

- `theme/` : thème enfant de Twenty Twenty-Five, déployé dans `wp-content/themes/tourisme-thailande`.
- `migration/` : scripts de conversion Elementor -> blocs Gutenberg et de mise en place du contenu.

## Thème

- Couleurs, typographie (Archivo variable auto-hébergée) et espacements dans `theme/theme.json`.
- Header : bloc `tt/site-header`, alimenté par le menu WordPress affecté à l'emplacement « Menu principal ».
  Niveau 1 = barre, niveau 2 = colonnes du méga-menu (classe CSS `tt-feat` = carte image), niveau 3 = liens.
- Footer : template part `footer`, entièrement en blocs natifs, modifiable dans l'éditeur de site.
- Templates : `front-page`, `page`, `page-destination`, `page-activite`, `page-catalogue`, `single`, `single-guide`, `home` (blog), `archive` (catégories), `author`, `search`, `404`.
- Listes d'articles : grilles de 4 colonnes, 16 par page (page 1 : dernier article en grand + 12), rubriques en pastilles (`tt/topics`), présentation de l'auteur (`tt/author-card`).

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
| `tt/card-grid` | Grille de cartes : sous-pages, pages sœurs, destinations, autres activités ou sélection d'IDs |
| `tt/brand` | Logo du site (emblème et nom, lien vers l'accueil), utilisé dans le footer ; le header appelle `tt_brand()` |
| `tt/page-head` | En-tête centré des pages simples, de la recherche et de la 404 (fil d'Ariane, H1, accroche, recherche) |
| `tt/authors` | Cartes des auteurs (photo, présentation, lien vers leurs guides), page « Qui sommes-nous » |
| `tt/suggestions` | Destinations, catalogue, blog et offres pour la recherche tapée (recherche sans résultat, 404) |
| `tt/explorer` | Mode carte : grande carte de toutes les activités géolocalisées, filtres par type, « Aller à » une destination, liste de la zone affichée |
| `tt/catalog` | Catalogue d'activités : en-tête, raccourcis par type, filtres (destination, type), recherche, tri et grille de cartes |
| `tt/cta-card` | Carte de réservation : photo, titre, texte court, bouton vers l'offre (tag partenaire ajouté) |
| `tt/map` | Carte des fiches de la destination (MapLibre auto-hébergé + fonds OpenFreeMap, noms en français) : repère photo, fiche cliquable vers l'offre GetYourGuide de l'activité |
| `tt/toc` | Sommaire automatique (H2 du contenu) |
| `tt/topics` | Rubriques du blog en pastilles |
| `tt/author-card` | Présentation de l'auteur (photo, rôle, bio, nombre d'articles, profils) |
| `tt/mobile-bar` | Barre de réservation mobile |

Tous les blocs sont rendus côté serveur. Syntaxe pour le MCP, par exemple :

```
<!-- wp:tt/gyg-card {"title":"Les 4 îles de Krabi","imageId":123,"place":"Krabi","kind":"Excursion","rating":"4,7","reviews":"3 410","meta":"6 heures","price":"32","url":"https://www.getyourguide.fr/...","placement":"top-krabi"} /-->
```

## Métadonnées de page (API REST)

`tt_h1`, `tt_destination`, `tt_gyg_query`, `tt_trip_city`, `tt_intro`, `tt_price_from`, `tt_synonyms`, `tt_card_title`, `tt_kind`, `tt_geo`, `tt_gyg_url`.
Elles se règlent dans l'éditeur, panneau « Tourisme Thaïlande » des réglages de la page (ou de l'article pour `tt_gyg_query`, `tt_kind`, `tt_card_title`).
Une sous-page hérite de la destination de son hub parent. `tt_h1` remplace le titre dans le hero, `tt_card_title` sur les cartes.
`tt_trip_city` est une clé de `tt_trip_destinations()` (ex. `koh-samui`) ; `tt_geo` vaut « lat,lng » (repère de la fiche sur la carte) ;
`tt_gyg_url` est le lien GetYourGuide de l'activité de la fiche (sinon la carte renvoie vers la recherche `tt_gyg_query`).

Pages au gabarit Activité (`page-activite`) : `tt_card_title` sert de libellé aux boutons (header, barre mobile, choix du formulaire),
`tt_gyg_query` de requête d'offres (ex. « plongée Phuket ») et `tt_trip_city` de destination hôtels (vide pour une page Thaïlande).
Le formulaire du hero propose la page et ses sous-pages (Plongée en Thaïlande, Plongée à Koh Tao…).

Catalogue d'activités (`/activites-thailande/`, gabarit `page-catalogue`, bloc `tt/catalog`) : toute page ou tout article qui a un
type d'activité (`tt_kind` : temples, marches, iles, nature, plongee, sorties, experiences) y apparaît, avec sa destination et son titre court.
Filtres dans l'adresse : `?d=bangkok,phuket&k=temples&q=wat`. Données structurées : `CollectionPage` + `ItemList`.

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

## Migration des pages activité

1. Export des pages (id, slug, parent, titre, image à la une, `_elementor_data`) et `hubs.json` (slug -> ID des guides destination).
2. `python3 -I migration/activites2blocks.py <pages.json> <sortie.json>` : plongée, snorkeling, yoga, randonnée, bateau.
   - sorties Trip.com de la section « Les meilleures sorties » -> sélection de 3 `tt/gyg-card` (requête par type de sortie, sans note ni prix
     tant que les offres réelles ne sont pas choisies) ; pages principales -> cartes vers les sous-pages ;
   - encart « Réserve ta sortie » -> `tt/cta-card` au même endroit ; encart hôtel -> `tt/trip-search` de la destination ;
   - « Découvrez les autres… » -> `tt/card-grid` (pages sœurs, autres activités) ; FAQ -> blocs Détails.
3. `migration/apply_activites.php` (via execute-php, variable `$tt_rows`) : contenu, gabarit `page-activite`, métadonnées
   et image à la une distincte par page. Sauvegarde dans `_tt_backup_content`, `_tt_backup_elementor_mode`, `_tt_backup_template`, `_tt_backup_thumb`.

## Catalogue d'activités

`migration/catalogue.php` (via execute-php, rejouable) : type d'activité de chaque page et des guides d'activité, titres courts des cartes,
création de la page `/activites-thailande/` (gabarit, contenu, métadonnées, titre et description Yoast). Déjà joué sur le preprod, dont la base part en prod à la mise en ligne.

## Cookies

Bandeau de consentement intégré au thème (`inc/consent.php` et `front.js`), sans extension : deux catégories, mesure
d'audience (bibliothèque Google Analytics de Site Kit) et partenaires de réservation (script de suivi des clics vers les
offres, auparavant dans Insert Headers and Footers). Ces scripts sont neutralisés (`type="text/plain"`) et chargés
seulement après accord ; choix gardé 6 mois (cookie `tt_consent`) ; retrait d'accord : cookies `_ga` effacés et page
rechargée. Tout lien vers `#reglages-cookies` (footer, mentions légales) rouvre le bandeau.

## Mode carte

`/carte-thailande/` (gabarit `page-carte`, bloc `tt/explorer`), lien « Carte » dans le header (réglage `map_url` de `tt_partners`)
et « Voir sur la carte » dans le catalogue. Repères : les activités du catalogue qui ont des coordonnées (`tt_geo`, pages et articles).
Mêmes filtres que le catalogue dans l'adresse (`?k=iles&d=phuket`).
Géolocalisation automatique (`inc/geo.php`) : à la publication d'une destination, d'une de ses sous-pages ou d'une activité
du catalogue sans « Coordonnées », le lieu est cherché sur OpenStreetMap autour de la destination (titre, nom entre
parenthèses, version anglaise, slug ; résultat le plus proche), sinon centre de la destination. Activité « partout en
Thaïlande » : pas de repère. Le cache des pages qui l'affichent (catalogue, carte, destination) est vidé. `migration/carte.php` (rejouable) : coordonnées manquantes
et création de la page ; déjà joué sur le preprod, dont la base part en prod à la mise en ligne.

## Mise en ligne (staging Hostinger publié sur la prod)

1. Gel de la prod (aucune publication Content Machine, aucune modification).
2. `python3 -I migration/sync_prod.py <identifiants>` : écart prod / preprod (lecture seule sur la prod) ; avec `--apply`,
   articles et pages nouveaux ou modifiés repris sur le preprod (convertis au nouveau format), images manquantes,
   commentaires, notes des lecteurs (Rate My Post), et suppression des pings créés sur le preprod. Redirections ajoutées
   sur la prod depuis la copie : signalées, à recréer à la main.
3. Sauvegarde complète de la prod (hPanel), puis publication du staging sur la prod (fichiers et base).
4. `python3 -I migration/apres_bascule.py <identifiants>` : état de la prod, traces du domaine preprod dans la base,
   contrôle de toutes les URL des sitemaps (code HTTP, domaine preprod, noindex, Elementor, erreurs PHP) ; avec `--apply`,
   indexation rétablie (`blog_public`), domaine preprod remplacé (valeurs sérialisées comprises, sauf `guid`), empreintes
   des permaliens Yoast recalculées, caches vidés. Les mots de passe d'application sont ceux du preprod (copie de la prod
   du 27 octobre) : celui de Content Machine en fait partie.
5. Search Console (sitemap), Site Kit, Content Machine relu sur les nouveaux blocs, puis retrait de Novamira,
   du gestionnaire de fichiers et des mots de passe d'application.

Extensions désactivées sur le preprod avant publication : Elementor, Elementor Pro, ElementsKit, Hello Plus, CPT UI, ACF,
Insert Headers and Footers, Shortcoder (plus utilisés). Gardées : Yoast, Site Kit, LiteSpeed, Rate My Post (notes des
articles), Redirection, WP External Links (nofollow des liens sortants), Duplicate Post, Akismet.

## Pages simples

`python3 -I migration/pages_simples.py <export.json> <sortie.json>` puis `migration/apply_pages.php` (via execute-php) :
Qui sommes-nous (cartes auteurs, section « Nos liens partenaires » ancrée `#partenaires`) et Mentions légales
(ancres `#donnees-personnelles` et `#cookies`, lien « Confidentialité » du footer). Contenu d'origine gardé dans `_tt_backup_*`.

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

## Mesure (GTM)

Contexte de chaque page (`page_type`, `thematique`, `destination`, `activity_kind`) et clics sur les CTA (`affiliate_click`,
`cta_click`) poussés dans le `dataLayer` (`inc/tracking.php`, `front.js`). Conteneur GTM : identifiant dans l'option
`tt_partners` (clé `gtm_id`), chargé après accord « mesure d'audience » ; il remplace alors la balise GA4 de Site Kit.
Plan de taggage et conteneur à importer : `docs/plan-de-taggage.md`, `docs/gtm-conteneur.json`.

## Images

Tailles générées en WebP (qualité 70), formats du thème `tt-card` (640×480) et `tt-card-s` (320×240) pour les cartes,
`tt-hero` (1920×1080) et `tt-hero-m` (960×540) pour les héros ; pas de 2048 px. Chaque original JPEG ou PNG a une copie
WebP à la même taille (taille `tt-full`), servie à sa place en `src` et en `srcset` (`inc/images.php`). Tout nouvel envoi
(médiathèque, Content Machine) passe par là. Rattrapage des images existantes : `python3 -I migration/images_webp.py
<identifiants> [--apply]` (reprise possible, anciens fichiers gardés sur le serveur, liste dans `_tt_old_sizes`).

## CTA des articles

Chaque article reçoit à l'affichage une carte d'activités (`tt/cta-card`, emplacement de suivi `section-1`) entre le
premier et le deuxième H2, y compris quand le 2e H2 est dans un encadré (`inc/article-cta.php`). Rien n'est ajouté si un
CTA d'activités arrive déjà avant ; jamais collée à un autre CTA. Titre et requête selon la destination de l'article
(`tt_gyg_query`), photo d'une activité du catalogue de cette destination. Le contenu des articles n'est pas modifié.

## Notes des lecteurs (pages)

Rate My Post sur les destinations, lieux et pages activité (`inc/ratings.php`) : widget en fin de page (« Ce guide t'a
été utile ? »), note moyenne sous le titre dès le premier vote, données structurées du plugin (CreativeWorkSeries avec
aggregateRating) complétées de l'adresse, de la description et du lieu décrit (`about`). Demande de note discrète au retour
d'un clic partenaire (nouvel onglet ou bouton Retour), une fois, jamais si déjà noté (`front.js`). Scripts et police du
plugin chargés seulement là où les étoiles s'affichent. Aucune note reprise d'un autre site, aucun vote initialisé.

## Affiliation

Identifiants dans `tt_partners()` (`inc/helpers.php`) : GetYourGuide `partner_id` (+ `cmp` = `tt-{page}-{emplacement}`),
Trip.com `Allianceid` et `SID` (+ `trip_sub1`). Même format que les liens des générateurs des espaces partenaires.
Filet de sécurité (`inc/affiliate.php`) : tout lien GetYourGuide ou Trip.com d'un contenu sans identifiants les reçoit à
l'affichage (emplacement `lien-texte`). 