"""Génère le contenu de démonstration (home, Bangkok, menu) en blocs, pour le local ou le preprod.

Usage : python3 build_content.py <local|preprod> <dossier des dumps> <fichier de sortie JSON>
"""
import html
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from html2blocks import block, clean, details, heading, image, paragraph, to_blocks  # noqa: E402

MODE, DUMP, OUT = sys.argv[1], sys.argv[2], sys.argv[3]

# Clés d'images -> URL d'origine sur le site (pour retrouver l'ID de la médiathèque du preprod).
U = 'https://tourisme-thailande.fr/wp-content/uploads/'
IMG_URLS = {
    'hero': U + '2025/11/robin-noguier-sydwCr54rf0-unsplash-1.jpg', 'bangkok': U + '2025/11/bangkok-thailande-6.webp',
    'chiangmai': U + '2025/11/chiang-mai-2.webp', 'phuket': U + '2025/11/max-bottinger-UxzDksvcWcY-unsplash.jpg',
    'krabi': U + '2025/11/humphrey-m-TejFa7VW5e4-unsplash.jpg', 'samui': U + '2025/11/nehal-patel-Plr-yS4xiao-unsplash.jpg',
    'chiangrai': U + '2025/11/anicka-martin-dRLUQjhk0Uk-unsplash.jpg', 'watarun': U + '2025/11/wat-arun.jpg',
    'plongee': U + '2025/11/nick-fewings-ENSc57B2lxs-unsplash.jpg', 'snorkel': U + '2025/11/andres-medina-kuc9KBX-9vY-unsplash.jpg',
    'bateau': U + '2025/11/connor-gan-dABvBgCcJ5c-unsplash.jpg', 'inthanon': U + '2025/12/Parc-National-de-Doi-Inthanon-2.jpg',
    'bkcar0': U + '2025/11/bangkok-thailande-6.webp',
    'box_grandpalaisbangkok': U + '2025/11/great-palace-bangkok-4-scaled.webp', 'box_watphrakaew': U + '2025/11/Wat-Phra-Kaew-1.webp',
    'box_watpho': U + '2025/11/wat-pho-vignette.jpg', 'box_watarun': U + '2025/11/wat-arun5.jpg',
    'box_chaophraya': U + '2025/11/chao-phraya-riviere-6.png', 'box_rattanakosin': U + '2025/11/Rattanakosin-4.jpg',
    'box_marchechatuchak': U + '2025/11/marche-de-Chatuchak-4.jpg', 'box_damnoensaduak': U + '2025/11/Damnoen-Saduak-1.jpg',
}

if MODE == 'local':
    local = {m['post_title']: (m['ID'], m['guid']) for m in json.load(open(os.path.join(DUMP, 'local_media.json')))}
    def img(k):
        return local[k]
else:
    by_url = {}
    for m in json.load(open(os.path.join(DUMP, 'dump/media.json'))):
        by_url[m['source_url']] = m['id']
        for s in (m.get('media_details') or {}).get('sizes', {}).values():
            by_url.setdefault(s.get('source_url'), m['id'])
    def img(k):
        u = IMG_URLS[k]
        return by_url[u], u.replace('https://tourisme-thailande.fr', 'https://preprod.tourisme-thailande.fr')


def group(inner, attrs=None, cls='', tag='div'):
    attrs = dict(attrs or {})
    if cls:
        attrs['className'] = cls
    if tag != 'div':
        attrs['tagName'] = tag
    classes = ' '.join(x for x in ['wp-block-group', 'align' + attrs['align'] if attrs.get('align') else '', cls,
                                   'has-' + attrs['backgroundColor'] + '-background-color has-background' if attrs.get('backgroundColor') else ''] if x)
    return f'<!-- wp:group {json.dumps(attrs, ensure_ascii=False)} -->\n<{tag} class="{classes}">' + '\n'.join(inner) + f'</{tag}>\n<!-- /wp:group -->'


SECTION = {'align': 'full', 'layout': {'type': 'constrained', 'contentSize': '1240px'}}


def section(inner, mist=False):
    a = json.loads(json.dumps(SECTION))
    if mist:
        a['backgroundColor'] = 'mist'
    return group(inner, a, 'tt-section')


def head(title, intro=''):
    parts = [heading(title)] + ([paragraph(intro)] if intro else [])
    return group(parts, {'layout': {'type': 'default'}}, 'tt-head')


def grid(inner, minw='230px'):
    return group(inner, {'layout': {'type': 'grid', 'minimumColumnWidth': minw}})


def button(label, url, outline=True):
    cls = ' is-style-outline' if outline else ''
    return ('<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->\n'
            '<div class="wp-block-buttons"><!-- wp:button {"className":"' + cls.strip() + '"} -->\n'
            f'<div class="wp-block-button{cls}"><a class="wp-block-button__link wp-element-button" href="{url}">{label}</a></div>\n'
            '<!-- /wp:button --></div>\n<!-- /wp:buttons -->')


def card(title, k, place, kind, rating, reviews, meta, price, query, placement='home-top'):
    i, _ = img(k)
    return block('tt/gyg-card', {'title': title, 'imageId': i, 'place': place, 'kind': kind, 'rating': rating, 'reviews': reviews,
                                 'meta': meta, 'price': price, 'query': query, 'placement': placement})


# ------------------------------------------------------------------ HOME
hero_id, _ = img('hero')
dest_links = ' '.join(f'<a href="/{s}/">{n}</a>' for n, s in [('Bangkok', 'bangkok'), ('Chiang Mai', 'chiang-mai'), ('Phuket', 'phuket'), ('Krabi', 'krabi'),
                                                             ('Koh Samui', 'koh-samui'), ('Koh Phi Phi', 'koh-phi-phi'), ('Koh Tao', 'koh-tao'), ('Pattaya', 'pattaya'),
                                                             ('Ayutthaya', 'ayutthaya'), ('Chiang Rai', 'chiang-rai'), ('Ban Krut', 'ban-krut')])
regions = []
for k, title, links in [('chiangrai', 'Le Nord', [('Chiang Mai', 'chiang-mai'), ('Chiang Rai', 'chiang-rai')]),
                        ('bangkok', 'Bangkok et le Centre', [('Bangkok', 'bangkok'), ('Ayutthaya', 'ayutthaya'), ('Pattaya', 'pattaya')]),
                        ('samui', 'Golfe de Thaïlande', [('Koh Samui', 'koh-samui'), ('Koh Tao', 'koh-tao'), ('Ban Krut', 'ban-krut')]),
                        ('phuket', "Mer d'Andaman", [('Phuket', 'phuket'), ('Krabi', 'krabi'), ('Koh Phi Phi', 'koh-phi-phi')])]:
    i, u = img(k)
    regions.append(group([image(i, u), heading(title, 3), paragraph(' · '.join(f'<a href="/{s}/">{n}</a>' for n, s in links))], {'layout': {'type': 'default'}}, 'tt-region'))

MONTHS = [('Janvier', 'janvier', 'Sec et doux partout, pleine haute saison'), ('Février', 'fevrier', 'Le meilleur mois pour combiner les deux côtes'),
          ('Mars', 'mars', "La chaleur monte, la mer d'Andaman reste calme"), ('Avril', 'avril', 'Le mois le plus chaud, Songkran le 13'),
          ('Mai', 'mai', 'Premières pluies côté Phuket et Krabi'), ('Juin', 'juin', "Averses l'après-midi, Golfe ensoleillé"),
          ('Juillet', 'juillet', 'Koh Samui et Koh Tao en pleine saison'), ('Août', 'aout', 'Prix en baisse à Phuket, foule dans le Golfe'),
          ('Septembre', 'septembre', 'Le plus pluvieux, sauf autour de Samui'), ('Octobre', 'octobre', 'Le Nord sèche, le Golfe se mouille'),
          ('Novembre', 'novembre', 'Loy Krathong et Yi Peng à Chiang Mai'), ('Décembre', 'decembre', 'Haute saison, réserve tôt')]
months = [group([heading(f'<a href="/thailande-{s}/">{n}</a>', 3), paragraph(t)], {'layout': {'type': 'default'}}) for n, s, t in MONTHS]

latest = ('<!-- wp:query {"queryId":2,"query":{"perPage":4,"postType":"post","order":"desc","orderBy":"date","inherit":false},"className":"tt-latest"} -->\n'
          '<div class="wp-block-query tt-latest"><!-- wp:post-template -->\n'
          '<!-- wp:post-featured-image {"isLink":true} /-->\n<!-- wp:group {"layout":{"type":"default"}} -->\n<div class="wp-block-group">'
          '<!-- wp:post-terms {"term":"category"} /-->\n<!-- wp:post-title {"isLink":true,"level":3} /-->\n<!-- wp:post-excerpt {"excerptLength":24} /--></div>\n<!-- /wp:group -->\n'
          '<!-- /wp:post-template --></div>\n<!-- /wp:query -->')

wi, wu = img('watarun')
team = (f'<!-- wp:media-text {{"mediaId":{wi},"mediaType":"image","mediaWidth":42,"verticalAlignment":"center","imageFill":false,"className":"tt-team"}} -->\n'
        f'<div class="wp-block-media-text is-stacked-on-mobile is-vertically-aligned-center tt-team" style="grid-template-columns:42% auto"><figure class="wp-block-media-text__media"><img src="{wu}" alt="" class="wp-image-{wi} size-full"/></figure>'
        '<div class="wp-block-media-text__content">' + heading('Un guide écrit depuis la Thaïlande') +
        paragraph('Agence francophone basée en Thaïlande, nous organisons des voyages sur mesure partout dans le pays. Notre équipe, installée sur place, travaille directement avec les guides, chauffeurs et hébergements locaux.') +
        paragraph('Tu peux réserver et payer en France, en toute sécurité, au prix local.') +
        '<!-- wp:buttons -->\n<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} -->\n<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/qui-sommes-nous/">Qui sommes-nous ?</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->'
        '</div></div>\n<!-- /wp:media-text -->')

home = '\n\n'.join([
    block('tt/hero', {'variant': 'home', 'widget': 'search', 'title': 'Tourisme en Thaïlande', 'subtitle': "Le guide pour préparer ton voyage, de Bangkok aux îles d'Andaman.", 'imageId': hero_id}),
    group([paragraph('<strong>Destinations</strong> ' + dest_links)], {'align': 'full', 'layout': {'type': 'default'}}, 'tt-quick'),
    section([head('Les activités les plus réservées', "Excursions, plongée, cours de cuisine : nos coups de cœur, avec annulation gratuite jusqu'à 24 h avant."),
             grid([card('Baptême de plongée à Koh Tao, matériel inclus', 'plongee', 'Koh Tao', 'Plongée', '4,8', '1 240', "Demi-journée · Prise en charge à l'hôtel", '59', 'Koh Tao plongée'),
                   card('Les 4 îles de Krabi en bateau longtail', 'bateau', 'Krabi', 'Excursion en bateau', '4,7', '3 410', '6 heures · Déjeuner inclus', '32', 'Krabi 4 îles'),
                   card('Doi Inthanon : cascades, pagodes royales et rizières', 'inthanon', 'Chiang Mai', 'Journée', '4,9', '860', '10 heures · Guide francophone', '45', 'Doi Inthanon'),
                   card('Maya Bay et Phi Phi en speedboat', 'snorkel', 'Koh Phi Phi', 'Snorkeling', '4,6', '2 980', '7 heures · Départ de Phuket', '38', 'Phi Phi speedboat')]),
             button('Voir toutes les activités', '/activites-thailande/')]),
    section([head('Choisis ta région', 'Montagnes du Nord, temples du Centre ou plages des deux côtes : chaque région a sa saison et son rythme.'), grid(regions)], mist=True),
    section([head('La Thaïlande mois par mois', "Mousson, fêtes, affluence et prix changent beaucoup d'un mois à l'autre, et d'une côte à l'autre."),
             group(months, {'layout': {'type': 'grid', 'minimumColumnWidth': '220px'}}, 'tt-months')]),
    section([head('Derniers guides'), latest, button('Tous les articles', '/blog/')], mist=True),
    section([team]),
])

# ------------------------------------------------------------------ BANGKOK
bkk = json.load(open(os.path.join(DUMP, 'dump/bkk.json')))
out, cur, faq, lead_done = [], None, [], False
for o in bkk:
    if o[0] == 'h':
        t = html.unescape(o[1]).strip()
        if o[2] == 'h1':
            continue
        if t.startswith('Vous cherchez un hôtel'):
            cur = 'hotel'
            continue
        if t.startswith('Découvrez nos autres'):
            cur = 'others'
            continue
        if t.startswith('FAQ'):
            t = 'Questions fréquentes sur Bangkok'
        if t.startswith('Visites'):
            t = 'Que visiter à Bangkok ?'
        if cur == 'hotel':
            out.append(block('tt/trip-search', {'placement': 'ou-dormir', 'layout': 'band'}))
        cur = t
        out.append(heading(t))
    elif o[0] == 'txt':
        if cur == 'hotel':
            continue
        out.extend(to_blocks(clean(o[1])))
    elif o[0] == 'box':
        pass
    elif o[0] == 'faq':
        for n, it in enumerate(o[1]['ekit_accordion_items']):
            out.append(details(it['acc_title'], clean(it['acc_content']), n == 0))

# Grille des incontournables : une carte par sous-page (bloc tt/card-grid à venir, ici en blocs core).
must = []
for o in bkk:
    if o[0] == 'box' and '/bangkok/' in o[2]:
        k = 'box_' + re.sub(r'[^a-z0-9]+', '', o[2].rstrip('/').split('/')[-1].lower())
        try:
            i, u = img(k)
        except KeyError:
            continue
        rel = o[2].replace('https://tourisme-thailande.fr', '')
        must.append(group([image(i, u), paragraph(f'<strong><a href="{rel}">{html.escape(html.unescape(o[1]))}</a></strong>')], {'layout': {'type': 'default'}}, 'tt-must'))
idx = next(n for n, b in enumerate(out) if 'Que visiter à Bangkok' in b)
acts = grid([card('Temples et canaux en bateau longtail', 'box_watarun', 'Bangkok', 'Visite guidée', '4,8', '1 520', '4 heures · Guide francophone', '27', 'Bangkok temples', 'que-visiter'),
             card('Street food de Chinatown à la nuit tombée', 'box_marchechatuchak', 'Bangkok', 'Gastronomie', '4,9', '2 100', '3 heures · 10 dégustations', '39', 'Bangkok street food', 'que-visiter'),
             card("Grand Palais et Wat Phra Kaew sans file d'attente", 'box_grandpalaisbangkok', 'Bangkok', 'Visite guidée', '4,7', '3 860', '3 heures · Billets inclus', '42', 'Grand Palais Bangkok', 'que-visiter')], '200px')
out[idx + 1:idx + 1] = [group(must, {'layout': {'type': 'grid', 'minimumColumnWidth': '150px'}}, 'tt-must-grid'), heading('Les activités les plus réservées à Bangkok', 3), acts]

bangkok = '\n\n'.join(out)

# ------------------------------------------------------------------ MENU (méga-menu)
def L(t, u):
    return {'title': t, 'url': u}

MENU = [
    {'title': 'Destinations', 'url': '/destinations/', 'cols': [
        {'title': 'Le Nord', 'links': [L('Chiang Mai', '/chiang-mai/'), L('Chiang Rai', '/chiang-rai/')]},
        {'title': 'Bangkok et le Centre', 'links': [L('Bangkok', '/bangkok/'), L('Ayutthaya', '/ayutthaya/'), L('Pattaya', '/pattaya/')]},
        {'title': 'Golfe de Thaïlande', 'links': [L('Koh Samui', '/koh-samui/'), L('Koh Tao', '/koh-tao/'), L('Ban Krut', '/ban-krut/')]},
        {'title': "Mer d'Andaman", 'links': [L('Phuket', '/phuket/'), L('Krabi', '/krabi/'), L('Koh Phi Phi', '/koh-phi-phi/')]},
        {'title': 'Bangkok : guide complet de la ville', 'feat': '/bangkok/', 'desc': 'Temples, marchés, quartiers et transports'}]},
    {'title': 'Activités', 'url': '/activites-thailande/', 'cols': [
        {'title': 'Par activité', 'links': [L('Plongée', '/plongee-thailande/'), L('Snorkeling', '/snorkeling-thailande/'), L('Excursions en bateau', '/bateau-thailande/'), L('Randonnée', '/randonnee-thailande/'), L('Yoga', '/yoga-thailande/')]},
        {'title': 'Par destination', 'links': [L('Activités à Bangkok', '/bangkok/'), L('Activités à Chiang Mai', '/chiang-mai/'), L('Activités à Phuket', '/phuket/'), L('Activités à Krabi', '/krabi/'), L('Activités à Koh Samui', '/koh-samui/')]},
        {'title': 'Les plus réservées', 'links': [L('Plongée à Koh Tao', '/plongee-thailande/plongee-koh-tao/'), L('Doi Inthanon', '/chiang-mai/parc-national-doi-inthanon/'), L('Elephant Nature Park', '/chiang-mai/elephant-nature-park/'), L('Full Moon Party', '/full-moon-party/')]},
        {'title': 'Plongée à Phuket', 'feat': '/plongee-thailande/plongee-phuket/', 'desc': 'Spots, saisons et sorties à réserver'}]},
    {'title': 'Hôtels', 'url': '/meilleurs-hotels-thailande/', 'cols': [
        {'title': 'Bangkok', 'links': [L('Meilleurs hôtels', '/meilleurs-hotels-bangkok/'), L('Hôtels de luxe', '/hotels-luxe-bangkok/'), L('Hôtels de charme', '/hotels-charme-bangkok/'), L('Hôtels avec piscine', '/hotels-piscine-bangkok/'), L('Hôtels pas chers', '/hotels-pas-cher-bangkok/')]},
        {'title': 'Phuket', 'links': [L('Meilleurs hôtels', '/meilleurs-hotels-phuket/'), L('Hôtels de luxe', '/hotels-luxe-phuket/'), L('Hôtels en bord de plage', '/hotels-plage-phuket/'), L('Hôtels pas chers', '/hotels-pas-cher-phuket/')]},
        {'title': 'Krabi', 'links': [L('Meilleurs hôtels', '/meilleurs-hotels-krabi/'), L('Hôtels de luxe', '/hotels-luxe-krabi/'), L('Hôtels en bord de plage', '/hotels-plage-krabi/'), L('Hôtels pas chers', '/hotels-pas-cher-krabi/')]},
        {'title': 'Les meilleurs hôtels en Thaïlande', 'feat': '/meilleurs-hotels-thailande/', 'desc': 'Notre sélection, de Bangkok aux îles'}]},
    {'title': 'Préparer son voyage', 'url': '/blog/', 'cols': [
        {'title': 'Formalités', 'links': [L('Visa étudiant', '/visa-etudiant-thailande-demarches/'), L('Drone : la réglementation', '/drone-thailande-reglementation/'), L('Cigarette électronique', '/cigarette-electronique-vapotage-thailande/')]},
        {'title': 'Argent', 'links': [L('Monnaie et change', '/monnaie-thailande/'), L('Pourboires', '/pourboires-en-thailande/'), L("Prix d'un massage", '/combien-coute-massage-thailande/')]},
        {'title': 'Sur place', 'links': [L('Décalage horaire', '/decalage-horaire-thailande/'), L('Prise électrique', '/prise-electrique-thailande/'), L('Animaux dangereux', '/animaux-dangereux-thailande/'), L('Moustiques', '/moustiques-thailande/')]},
        {'title': 'Quand partir', 'links': [L(n, f'/thailande-{s}/') for n, s, _ in MONTHS]},
        {'title': 'Partir en novembre', 'feat': '/thailande-novembre/', 'desc': 'Loy Krathong, Yi Peng et retour de la saison sèche'}]},
    {'title': 'Blog', 'url': '/blog/', 'cols': [
        {'title': 'Culture', 'links': [L('Histoire & culture', '/category/histoire-culture/'), L('Religion & spiritualité', '/category/religion-spiritualite/'), L('Société & mode de vie', '/category/societe-mode-de-vie/'), L('Langue', '/category/langue/')]},
        {'title': 'Voyager', 'links': [L('Tourisme', '/category/tourisme/'), L('Nature et paysages', '/category/nature-et-paysage/'), L('Gastronomie', '/category/gastronomie/'), L('Shopping', '/category/shopping/')]},
        {'title': 'Pratique', 'links': [L('Réglementation & formalités', '/category/reglementation-formalite/'), L('Transport', '/category/transport/'), L('Étudier', '/category/etudier/'), L('Loisirs', '/category/loisirs/'), L('Actualités', '/category/actualites/')]},
        {'title': 'Monnaie en Thaïlande', 'feat': '/monnaie-thailande/', 'desc': 'Le dernier guide publié'}]},
]

bk_img, _ = img('bkcar0')
json.dump({'home': home, 'bangkok': bangkok, 'bangkok_thumb': bk_img, 'menu': MENU,
           'bangkok_intro': 'Temples dorés, marchés flottants, rooftops et street food : tout pour organiser ton séjour dans la capitale.'},
          open(OUT, 'w'), ensure_ascii=False)
print('home', len(home), 'bangkok', len(bangkok), 'blocks', bangkok.count('<!-- wp:'))
