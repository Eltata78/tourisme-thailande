"""Conversion des pages activité Elementor (plongée, snorkeling, yoga, randonnée, bateau) en blocs Gutenberg.

Usage : python3 activites2blocks.py <pages.json> <sortie.json>
  - pages.json : export [{id, slug, parent, title, thumb, data (Elementor), link}]

Les sorties Trip.com des pages (produits d'activité) deviennent des cartes d'offres partenaire ciblées
(requête par type de sortie) ; l'encart « Réserve ta sortie » devient une carte de réservation, l'encart hôtel
le formulaire Trip.com de la destination. Les CTA restent répartis : sélection en début de page, carte au milieu,
hôtels en fin de page.
"""
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from elementor2blocks import GYG_LINKS, gallery, lead_blocks, norm_title, related, tipify, txt, widgets  # noqa: E402
from html2blocks import block, clean, details, heading, image, table, to_blocks  # noqa: E402

PLACES = {'phuket': 'Phuket', 'koh-phi-phi': 'Koh Phi Phi', 'koh-samui': 'Koh Samui', 'koh-tao': 'Koh Tao', 'krabi': 'Krabi'}
# Activité : mot de recherche, nom, article (« la plongée »), carte de réservation (titre, texte, bouton), titre « ailleurs ».
ACTS = {
    'plongee': dict(word='plongée', name='Plongée', the='la plongée', cta='Réserve ta sortie plongée {at}',
                    text='Baptêmes, sorties à la journée et formations PADI à réserver en ligne, la plupart avec annulation gratuite.',
                    label='Voir les sorties plongée', elsewhere='Où plonger ailleurs en Thaïlande ?'),
    'snorkeling': dict(word='snorkeling', name='Snorkeling', the='le snorkeling', cta='Réserve ta sortie snorkeling {at}',
                       text='Excursions en bateau avec masque et tuba, à la journée ou au coucher du soleil, la plupart avec annulation gratuite.',
                       label='Voir les sorties snorkeling', elsewhere='À découvrir aussi'),
    'yoga': dict(word='yoga', name='Yoga', the='le yoga', cta='Réserve ton cours de yoga {at}',
                 text='Cours à la séance, stages et retraites à réserver en ligne.', label='Voir les cours de yoga'),
    'randonnee': dict(word='randonnée', name='Randonnée', the='la randonnée', cta='Réserve ta randonnée {at}',
                      text="Treks d'une journée ou de plusieurs jours avec un guide local, à réserver en ligne.", label='Voir les randonnées'),
    'bateau': dict(word='excursion bateau', name='Excursions en bateau', the='les excursions en bateau', cta='Réserve ton excursion en bateau {at}',
                   text='Tours des îles, sorties à la journée et croisières au coucher du soleil, la plupart avec annulation gratuite.',
                   label='Voir les excursions'),
}
# Photos des destinations (image à la une des hubs) pour les cartes par lieu des pages sans sous-pages.
DEST_IMG = {'bangkok': 736, 'chiang-mai': 819, 'chiang-rai': 1087, 'koh-phi-phi': 1193, 'koh-samui': 1057, 'phuket': 1242}
SHORT = {u: t for u, t in GYG_LINKS.values() if t}

# Pages : activité, destination, image à la une (distincte par page : les grilles de pages les montrent côte à côte),
# et sélection d'offres de la section « meilleures sorties » : (titre, type, requête ou lien produit, image).
PAGES = {
    'plongee-thailande': dict(act='plongee'),
    'plongee-phuket': dict(act='plongee', place='phuket', thumb=1562, cards=[
        ('Journée plongée aux îles Racha Yai et Racha Noi', 'Sortie à la journée', 'plongée Racha Phuket', 1763),
        ('Croisière plongée aux îles Similan', 'Plongeurs certifiés', 'croisière plongée Similan', 1765),
        ('Baptême de plongée à Phuket', 'Débutant', 'baptême plongée Phuket', 1762)]),
    'plongee-koh-phi-phi': dict(act='plongee', place='koh-phi-phi', thumb=1569, cards=[
        ('Deux plongées autour des îles Phi Phi', 'Sortie à la journée', 'plongée Phi Phi', 1766),
        ('Baptême de plongée à Koh Phi Phi', 'Débutant', 'baptême plongée Phi Phi', 1762),
        ("Shark Point et l'épave du King Cruiser", 'Plongeurs certifiés', 'plongée King Cruiser Shark Point', 1764)]),
    'plongee-koh-samui': dict(act='plongee', place='koh-samui', thumb=1565, cards=[
        ('Journée plongée à Sail Rock', 'Sortie à la journée', 'plongée Sail Rock', 1766),
        ('Plongée à Koh Tao au départ de Koh Samui', 'Sortie à la journée', 'plongée Koh Tao depuis Koh Samui', 1764),
        ('Baptême de plongée à Koh Samui', 'Débutant', 'baptême plongée Koh Samui', 1762)]),
    'plongee-koh-tao': dict(act='plongee', place='koh-tao', thumb=1568, cards=[
        ('Baptême de plongée à Koh Tao', 'Débutant', 'baptême plongée Koh Tao', 1894),
        ('Formation PADI Open Water à Koh Tao', 'Formation', 'PADI Open Water Koh Tao', 1896),
        ('Chumphon Pinnacle et Sail Rock', 'Plongeurs certifiés', 'plongée Chumphon Pinnacle Koh Tao', 1895)]),
    'plongee-krabi': dict(act='plongee', place='krabi', thumb=1566, cards=[
        ('Baptême de plongée aux îles de Krabi', 'Débutant', 'baptême plongée Krabi', 4096),
        ("Deux plongées à Koh Phi Phi au départ d'Ao Nang", 'Sortie à la journée', 'plongée Phi Phi depuis Krabi', 1204),
        ("Shark Point et l'épave du King Cruiser", 'Plongeurs certifiés', 'plongée King Cruiser Krabi', 1206)]),
    'snorkeling-thailande': dict(act='snorkeling'),
    'snorkeling-koh-phi-phi': dict(act='snorkeling', place='koh-phi-phi', thumb=1589, cards=[
        ('Maya Bay et Pileh Lagoon en speedboat', 'Journée en mer', 'Maya Bay snorkeling', 2024),
        ('Snorkeling au coucher du soleil et plancton lumineux', 'Fin de journée', 'Phi Phi coucher de soleil snorkeling', 2026),
        ('Bamboo Island et Mosquito Island', 'Journée en mer', 'Bamboo Island snorkeling', 2023)]),
    'snorkeling-koh-samui': dict(act='snorkeling', place='koh-samui', thumb=1569, cards=[
        ("Parc marin d'Ang Thong en bateau", 'Journée en mer', 'Ang Thong snorkeling', 1567),
        ('Île aux cochons et snorkeling à Koh Tan', 'Demi-journée', 'https://gyg.me/U5rhK83e', 1565),
        ('Koh Tao et Koh Nang Yuan au départ de Koh Samui', 'Journée en mer', 'Koh Tao Koh Nang Yuan depuis Koh Samui', 1566)]),
    'yoga-thailande': dict(act='yoga', cards=[
        ('Yoga à Chiang Mai', 'Cours et retraites', 'yoga Chiang Mai', DEST_IMG['chiang-mai']),
        ('Yoga à Koh Samui', 'Cours et retraites', 'yoga Koh Samui', DEST_IMG['koh-samui']),
        ('Yoga à Bangkok', 'Cours à la séance', 'yoga Bangkok', DEST_IMG['bangkok'])]),
    'randonnee-thailande': dict(act='randonnee', cards=[
        ('Trek dans les montagnes de Chiang Mai', 'Une ou plusieurs journées', 'trek Chiang Mai', DEST_IMG['chiang-mai']),
        ('Randonnée autour de Chiang Rai', 'Villages du nord', 'trek Chiang Rai', DEST_IMG['chiang-rai']),
        ('Jungle du parc national de Khao Sok', 'Jungle et lac', 'Khao Sok', 1683)]),
    'bateau-thailande': dict(act='bateau', cards=[
        ('Îles Phi Phi et Maya Bay en speedboat', 'Journée en mer', 'Phi Phi speedboat', DEST_IMG['koh-phi-phi']),
        ('Baie de Phang Nga au départ de Phuket', 'Journée en mer', 'baie de Phang Nga Phuket', DEST_IMG['phuket']),
        ("Parc marin d'Ang Thong depuis Koh Samui", 'Journée en mer', 'Ang Thong bateau', DEST_IMG['koh-samui'])]),
}
# Erreurs de copier-coller des pages d'origine.
FIXES = {
    'plongee-koh-tao': [('Plonger à Phuket, c’est', 'Plonger à Koh Tao, c’est')],
    'snorkeling-thailande': [('Nos conseils pour ta sortie plongée en Thaïlande', 'Nos conseils pour ta sortie snorkeling en Thaïlande'),
                             ('meilleure période pour faire de la plongée en Thaïlande', 'meilleure période pour faire du snorkeling en Thaïlande')],
}


def fix_heading(t):
    t = re.sub(r'^A (?=\w)', 'À ', t)
    t = re.sub(r'(\S)\?$', r'\1 ?', t)
    return re.sub(r'\s*!$', '', t)


def card_grid(cards, place, placement='selection'):
    """Sélection d'offres (format validé sur Bangkok) : 3 cartes, sans note ni prix tant qu'on n'a pas les offres réelles."""
    out = []
    for title, kind, q, img in cards:
        a = {'title': title, 'imageId': img, 'kind': kind, 'place': place, 'placement': placement}
        if q.startswith('http'):
            a['url'] = GYG_LINKS[q][0]
        else:
            a['query'] = q
        out.append(block('tt/gyg-card', a))
    return ('<!-- wp:group {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"200px"}} -->\n<div class="wp-block-group">'
            + '\n'.join(out) + '</div>\n<!-- /wp:group -->')


def convert(page, by_slug):
    conf = PAGES[page['slug']]
    act, key = ACTS[conf['act']], conf.get('place')
    place = PLACES.get(key, 'Thaïlande')
    at = f'à {place}' if key else 'en Thaïlande'
    query = f"{act['word']} {place}"
    w = widgets({'elements': page['data']})
    blocks, log, meta = [], [], {}
    gallery_items, intro_done, faq = [], False, False
    i = 0
    while i < len(w):
        e, s, t = w[i], w[i]['settings'], w[i]['widgetType']
        if t == 'heading':
            title = fix_heading(norm_title(s.get('title', '')))
            level = s.get('header_size', 'h2')
            low = title.lower()
            if level == 'h1':
                meta['tt_h1'] = title
            elif low.startswith('réserve'):
                # Encart « Réserve ta sortie » (titre, texte, bouton Trip.com) -> carte de réservation, sans intertitre :
                # le texte qui suit continue la section en cours.
                j = i + 1
                while j < len(w) and w[j]['widgetType'] in ('text-editor', 'button') and not (w[j]['widgetType'] == 'text-editor' and w[j - 1]['widgetType'] == 'button'):
                    j += 1
                blocks.append(block('tt/cta-card', {'title': act['cta'].format(at=at), 'text': act['text'], 'label': act['label'],
                                                    'query': query, 'imageId': '__gal__', 'placement': 'reserver'}))
                log.append('encart Trip.com « Réserve ta sortie » -> carte de réservation')
                i = j
                continue
            elif re.match(r'^(vous cherchez|tu cherches)', low):
                j = i + 1
                while j < len(w) and w[j]['widgetType'] == 'button':
                    j += 1
                blocks.append(block('tt/trip-search', {'placement': 'hotel', 'layout': 'band'}))
                log.append('encart hôtel -> formulaire Trip.com' + (f' ({place})' if key else ''))
                i = j
                continue
            elif low.startswith('découvr'):
                j = i + 1
                while j < len(w) and w[j]['widgetType'] == 'image-box':
                    j += 1
                if not page['parent']:
                    blocks.append(heading('Les autres activités à faire en Thaïlande'))
                    blocks.append(block('tt/card-grid', {'mode': 'activities', 'columns': 4}))
                elif conf['act'] == 'plongee':
                    blocks.append(heading(act['elsewhere']))
                    blocks.append(block('tt/card-grid', {'mode': 'siblings', 'columns': 4}))
                else:
                    # Une seule page sœur : on y ajoute la plongée au même endroit et le guide de la destination.
                    sisters = [p for p in PAGES if p.startswith(conf['act'] + '-') and p != page['slug'] and PAGES[p].get('place')]
                    ids = [by_slug[x]['id'] for x in [f'plongee-{key}'] + sisters + [key] if x in by_slug]
                    blocks.append(heading(act['elsewhere']))
                    blocks.append(block('tt/card-grid', {'mode': 'ids', 'ids': ','.join(map(str, ids)), 'columns': 3}))
                i = j
                continue
            elif low.startswith('faq'):
                blocks.append(heading(f"Questions fréquentes sur {act['the']} {at}"))
                faq = True
            else:
                blocks.append(heading(title, int(level[1]) if level in ('h2', 'h3', 'h4') else 2))
                # Section « meilleures sorties / destinations » : cartes vers les sous-pages ou sélection d'offres.
                if re.match(r'^(les meilleures|quelle sortie)', low):
                    j = i + 1
                    while j < len(w) and w[j]['widgetType'] in ('text-editor', 'html'):
                        j += 1
                    if j < len(w) and w[j]['widgetType'] == 'image-box' or low.startswith('quelle sortie'):
                        k = j
                        while k < len(w) and w[k]['widgetType'] == 'image-box':
                            k += 1
                        inner = w[i + 1:j]
                        for x in inner:
                            if x['widgetType'] == 'text-editor':
                                blocks.extend(to_blocks(clean(x['settings'].get('editor', ''))))
                            elif x['widgetType'] == 'html' and '<table' in x['settings'].get('html', ''):
                                blocks.append(html_table(x['settings']['html']))
                        if conf.get('cards'):
                            blocks.append(card_grid(conf['cards'], place if key else ''))
                            log.append(f"{k - j} cartes d'origine -> sélection de {len(conf['cards'])} offres")
                        else:
                            n = len(PAGES_CHILDREN.get(page['slug'], []))
                            blocks.append(block('tt/card-grid', {'mode': 'children', 'columns': 4 if n % 4 == 0 else 3 if n > 2 else 2}))  # 5 -> 3 + 2
                            log.append('cartes vers les sous-pages')
                        i = k
                        continue
        elif t == 'text-editor':
            h = s.get('editor', '')
            for a, b in FIXES.get(page['slug'], []):
                if a in h:
                    h = h.replace(a, b)
                    log.append(f'corrigé : « {a} »')
            if not intro_done and not any(b.startswith('<!-- wp:heading') for b in blocks):
                blocks.extend(lead_blocks(h))
                first = re.sub(r'\s+', ' ', txt(re.sub(r'<br\s*/?>', ' ', re.split(r'</p>', h)[0])))
                first = re.sub(r'([.!?])(?=[A-ZÀ-Ý])', r'\1 ', first)
                sent = re.split(r'(?<=[.!?…])\s', first)
                intro = sent[0] if sent else first
                if len(intro) > 200:
                    intro = intro[:200].rsplit(' ', 1)[0].rstrip(',;:') + '…'
                elif len(sent) > 1 and len(intro) + len(sent[1]) < 170:
                    intro += ' ' + sent[1]
                meta['tt_intro'] = intro
                intro_done = True
            else:
                tb = to_blocks(clean(h))
                if tb and tb[0].startswith('<!-- wp:heading') and blocks and blocks[-1].startswith('<!-- wp:heading') and txt(tb[0]) == txt(blocks[-1]):
                    tb = tb[1:]
                blocks.extend(tb)
        elif t == 'image-carousel':
            gallery_items = [{'id': c.get('id'), 'url': c.get('url')} for c in s.get('carousel', [])]
            if gallery_items:
                blocks.append(gallery(gallery_items))
        elif t == 'image':
            im = s.get('image', {})
            nxt = next((x for x in w[i + 1:] if x['widgetType'] == 'heading'), None)
            if not im.get('id') or (nxt and re.match(r'^(vous cherchez|tu cherches)', txt(nxt['settings'].get('title', '')).lower())):
                log.append('image décorative avant encart hôtel supprimée')
            else:
                blocks.append(image(im['id'], im.get('url', '')))
        elif t == 'html':
            h = s.get('html', '')
            if '<table' in h:
                blocks.append(html_table(h))
            elif '<style' not in h:
                log.append('bloc HTML non converti : ' + re.sub(r'\s+', ' ', txt(h))[:60])
        elif t == 'elementskit-accordion':
            if not faq:
                blocks.append(heading(f"Questions fréquentes sur {act['the']} {at}"))
            for n, it in enumerate(s.get('ekit_accordion_items', [])):
                blocks.append(details(txt(it.get('acc_title', '')), clean(it.get('acc_content', '')), n == 0))
        elif t == 'button':
            log.append('bouton isolé ignoré : ' + txt(s.get('text', '')))
        i += 1

    meta.update({'tt_gyg_query': query, 'tt_trip_city': key or '',
                 'tt_card_title': f"{act['name']} {at}" if key or conf['act'] != 'bateau' else act['name']})
    content = '\n\n'.join(tipify(b) for b in related(blocks))
    content = re.sub(r'<p>(?:\s*<br\s*/?>)+\s*', '<p>', content)  # saut de ligne en tête de paragraphe
    gal = [g['id'] for g in gallery_items if g.get('id') and g['id'] != conf.get('thumb', page['thumb'])]
    content = content.replace('"imageId": "__gal__"', f'"imageId": {gal[2 if len(gal) > 2 else 0]}' if gal else '"imageId": 0')
    return content.replace('https://preprod.tourisme-thailande.fr', 'https://tourisme-thailande.fr'), meta, log


def html_table(h):
    inner = re.search(r'<table[^>]*>(.*)</table>', clean(re.sub(r'<(table|thead|tbody|tr|th|td)\b[^>]*>', r'<\1>', h)), re.S)
    return table(inner.group(1)) if inner else ''


src, out = sys.argv[1], sys.argv[2]
pages = json.load(open(src))
by_slug = {p['slug']: p for p in pages}
PAGES_CHILDREN = {p['slug']: [c['slug'] for c in pages if c['parent'] == p['id']] for p in pages}
# Guides des destinations (pour les cartes « à découvrir aussi ») : identifiants sur le site.
by_slug.update({k: {'id': v} for k, v in json.load(open(os.path.join(os.path.dirname(src), 'hubs.json'))).items()})
result = []
for page in pages:
    content, meta, log = convert(page, by_slug)
    conf = PAGES[page['slug']]
    result.append({'id': page['id'], 'slug': page['slug'], 'content': content, 'meta': meta, 'log': log,
                   'thumbnail': conf.get('thumb', 0)})
json.dump(result, open(out, 'w'), ensure_ascii=False)
print(len(result), 'pages,', sum(r['content'].count('<!-- wp:') for r in result), 'blocs')
for r in result:
    print(r['slug'], '|', r['meta'].get('tt_h1'), '|', r['meta']['tt_card_title'], '|', r['meta'].get('tt_intro', '')[:70])
    for line in r['log']:
        print('   -', line)
