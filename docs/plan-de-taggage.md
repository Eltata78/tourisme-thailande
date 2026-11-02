# Plan de taggage : clics sur les CTA et performance par page

Objectif : savoir quelles pages d'entrée, quelles thématiques et quels types de page envoient le plus de visiteurs vers
les partenaires (GetYourGuide, Trip.com), et quels CTA (composant, emplacement, hauteur dans la page) fonctionnent.

Fonctionnement :

- **Le site** pousse le contexte de chaque page et chaque clic sur un CTA dans le `dataLayer` (`inc/tracking.php`, `assets/js/front.js`).
- **GTM** lit le `dataLayer` et envoie à GA4. Le conteneur se charge seulement après accord « mesure d'audience » dans le bandeau cookies.
  Une fois le conteneur branché, il remplace la balise GA4 de Site Kit, coupée automatiquement pour ne pas compter deux fois.

## 1. Contexte de page (poussé sur chaque page, avant GTM)

| Variable | Valeurs | Exemple |
|---|---|---|
| `page_type` | `accueil`, `article`, `destination`, `visite`, `activite`, `catalogue`, `carte`, `categorie`, `blog`, `auteur`, `recherche`, `page`, `404` | `visite` |
| `thematique` | article : sa catégorie principale (Yoast) ; catégorie : son nom ; pages : `Destinations`, `Visites`, `Activités`, `Pages` | `Nature et Paysage` |
| `destination` | destination de la page ou de sa page mère, vide pour les articles | `Bangkok` |
| `activity_kind` | type d'activité de la page (catalogue) | `Marchés` |
| `page_slug` | slug de la page (sert au script pour lire l'emplacement des CTA) | `damnoen-saduak` |

## 2. Événements

### `affiliate_click` : clic vers un partenaire (événement clé)

Tout lien vers GetYourGuide (y compris les liens courts gyg.me des anciens articles) ou Trip.com, et tout envoi d'un
formulaire partenaire (recherche d'hôtels, recherche d'activités).

| Paramètre | Contenu | Exemples |
|---|---|---|
| `cta_partner` | partenaire | `getyourguide`, `trip.com` |
| `cta_component` | composant qui porte le CTA | `site-header`, `mobile-bar`, `hero`, `gyg-card`, `gyg-spotlight`, `gyg-compare`, `cta-card`, `gyg-search`, `trip-search`, `map`, `explorer`, `catalog`, `suggestions`, `texte` (lien dans le texte d'un article) |
| `cta_placement` | emplacement, lu dans le paramètre de campagne du lien (`cmp` chez GetYourGuide, `trip_sub1` chez Trip.com) | `header`, `mbar`, `hero`, `rail`, `spotlight`, `card`, `compare`, `banniere`, `carte`, `carte-liste`, `catalogue-vide`, `recherche-vide`, `lien-texte` |
| `cta_text` | texte du bouton ou du lien (100 caractères au plus) | `Voir les disponibilités` |
| `cta_url` | domaine et chemin de la destination, sans paramètres | `www.getyourguide.fr/s/` |
| `cta_position` | hauteur du CTA dans la page, par quart | `0-25` (haut), `25-50`, `50-75`, `75-100` (bas) |

### `cta_click` : clic sur un CTA interne

Liens des blocs du site vers une autre page du site : cartes d'activités, catalogue, carte, suggestions de la recherche,
lien « Carte » et bouton du header. Le menu, le pied de page, le sommaire et les liens du texte ne comptent pas.

| Paramètre | Contenu |
|---|---|
| `cta_component` | comme ci-dessus (`catalog`, `explorer`, `card-grid`, `recherche-suggestion`, `site-header`…) |
| `cta_text` | texte du lien |
| `cta_url` | chemin de la page visée, par exemple `/carte-thailande/` |
| `cta_position` | hauteur dans la page, par quart |

## 3. Conteneur GTM (`docs/gtm-conteneur.json`)

| Élément | Type | Détail |
|---|---|---|
| GA4 - Balise Google (pages vues) | Balise Google | ID `G-YNDMC943HF`, paramètres de page joints, déclencheur Initialisation - Toutes les pages |
| GA4 - affiliate_click (clic partenaire) | Événement GA4 | 6 paramètres `cta_*` + paramètres de page, déclencheur Événement - affiliate_click |
| GA4 - cta_click (CTA interne) | Événement GA4 | 4 paramètres `cta_*` + paramètres de page, déclencheur Événement - cta_click |
| GA4 - Paramètres de page | Variable « Paramètres d'événement » | `page_type`, `thematique`, `destination`, `activity_kind` |
| DLV - … | Variables de couche de données | une par variable du dataLayer |

### Mise en place

1. Dans GTM, créer un conteneur Web pour tourisme-thailande.fr, ou ouvrir celui qui existe.
2. Admin, Importer un conteneur : choisir `gtm-conteneur.json`, espace de travail existant, option **Fusionner**, puis
   **Renommer les éléments en conflit**.
3. Vérifier la balise Google : l'ID doit être `G-YNDMC943HF` (variable « GA4 - ID de mesure »).
4. Publier, puis transmettre l'identifiant du conteneur (`GTM-XXXXXXX`). Il se règle dans l'option WordPress
   `tt_partners` (clé `gtm_id`). Dès qu'il est réglé, le site charge GTM après accord et coupe la balise GA4 de Site Kit.
5. Recette : mode Aperçu de GTM sur le site, accepter les cookies, cliquer un CTA partenaire. On doit voir
   `affiliate_click` avec ses paramètres, puis l'événement dans GA4, Admin, DebugView.

## 4. GA4

1. Admin, Définitions personnalisées : créer une dimension personnalisée **de niveau événement** pour chaque paramètre :
   `page_type`, `thematique`, `destination`, `activity_kind`, `cta_partner`, `cta_component`, `cta_placement`, `cta_text`,
   `cta_url`, `cta_position`. Le nom affiché est libre, le paramètre doit être exact.
2. Admin, Événements clés : marquer `affiliate_click` comme événement clé (il apparaît après les premiers clics).
3. Les données s'affichent dans les rapports sous 24 à 48 h. Les dimensions ne s'appliquent qu'aux données reçues après leur création.

## 5. Lire les résultats (Explorer, Exploration libre)

| Question | Lignes | Valeurs |
|---|---|---|
| Quelles pages d'entrée rapportent ? | Page de destination | Sessions, Événements clés, Taux d'événements clés de la session |
| Quelles thématiques ou types de page cliquent ? | `thematique` ou `page_type` | Vues, Nombre d'événements `affiliate_click` (filtre Nom de l'événement) |
| Quels CTA fonctionnent ? | `cta_component`, puis `cta_placement` | Nombre d'événements `affiliate_click` |
| Haut ou bas de page ? | `cta_position` | Nombre d'événements `affiliate_click` |
| Quelle destination génère des réservations d'activités ? | `destination`, `cta_partner` | Nombre d'événements `affiliate_click` |

Taux de clic d'une thématique : événements `affiliate_click` divisés par les vues des pages de cette thématique.

## 6. Limites

- GA4 ne voit que les visiteurs qui acceptent la mesure d'audience. Les volumes sont donc partiels, mais les comparaisons
  entre pages, thématiques et CTA restent valables.
- Les réservations et les commissions restent dans les espaces partenaires : chez GetYourGuide, par `cmp`
  (`tt-{page}-{emplacement}`) ; chez Trip.com, par `trip_sub1`. Ces chiffres couvrent tous les visiteurs, avec ou sans
  cookies, et donnent le chiffre d'affaires par page et par emplacement. GA4 sert à comprendre le parcours qui y mène.
