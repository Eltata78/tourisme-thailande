"""Conversion des pages destination Elementor (hubs et sous-pages) en blocs Gutenberg.

Usage : python3 elementor2blocks.py <dossier el/> <pages.json> <sortie.json>
  - el/ : un fichier {id}.json par page (données Elementor) + index.json [{id, slug, parent, title, link}]
  - pages.json : export REST des pages (pour l'image à la une)

La sortie liste, par page : contenu en blocs, métadonnées (H1, accroche, destination…),
image à la une à poser si absente, et un journal des choix faits pendant la conversion.
"""
import html
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from html2blocks import EMOJI, block, clean, details, heading, image, paragraph, table, to_blocks  # noqa: E402

EL, PAGES, OUT = sys.argv[1], sys.argv[2], sys.argv[3]

HUB_META = {
    'bangkok': ('Bangkok', 359, 'bkk, krung thep'), 'chiang-mai': ('Chiang Mai', 623, 'chiangmai, cnx'), 'pattaya': ('Pattaya', 622, ''),
    'phuket': ('Phuket', 725, ''), 'ayutthaya': ('Ayutthaya', 1225, ''), 'chiang-rai': ('Chiang Rai', 1223, ''), 'koh-samui': ('Koh Samui', 1229, 'samui'),
    'koh-phi-phi': ('Koh Phi Phi', 1240, 'phi phi, maya bay'), 'krabi': ('Krabi', 1240, 'ao nang'), 'koh-tao': ('Koh Tao', 1230, 'tao'), 'ban-krut': ('Ban Krut', 0, 'ban krood'),
}
GYG = 'https://www.getyourguide.fr/s/?q={q}&partner_id=YADMZ9Q&cmp={cmp}'


def txt(s):
    return html.unescape(re.sub('<[^>]+>', '', s or '')).replace('\xa0', ' ').strip()


def norm_title(s):
    s = EMOJI.sub('', txt(s)).strip()
    s = re.sub(r'\bGuide Complet\b', 'guide complet', s)
    s = re.sub(r'\bde la Ville\b', 'de la ville', s).replace('Vielle ville', 'Vieille ville').replace(' : Deviens ', ' : deviens ')
    return re.sub(r' : (Le|La|Les|Un|Une|L’|L\')\b', lambda m: ' : ' + m.group(1).lower(), s)


def lead_blocks(h):
    """Premier paragraphe en style « chapeau », la suite en paragraphes normaux."""
    blocks = to_blocks(clean(h))
    if blocks and blocks[0].startswith('<!-- wp:paragraph -->'):
        inner = re.search(r'<p>(.*)</p>', blocks[0], re.S).group(1)
        blocks[0] = '<!-- wp:paragraph {"className":"is-style-tt-lead"} -->\n<p class="is-style-tt-lead">' + inner + '</p>\n<!-- /wp:paragraph -->'
    return blocks


def gallery(items):
    imgs = '\n'.join(
        f'<!-- wp:image {{"id":{i["id"]},"sizeSlug":"large","linkDestination":"none","lightbox":{{"enabled":true}}}} -->\n'
        f'<figure class="wp-block-image size-large"><img src="{i["url"]}" alt="" class="wp-image-{i["id"]}"/></figure>\n<!-- /wp:image -->'
        for i in items if i.get('id'))
    return ('<!-- wp:gallery {"linkTo":"none","anchor":"galerie","className":"tt-gallery"} -->\n'
            f'<figure class="wp-block-gallery has-nested-images columns-default is-cropped tt-gallery" id="galerie">{imgs}</figure>\n<!-- /wp:gallery -->')


def buttons(label, url):
    return ('<!-- wp:buttons -->\n<div class="wp-block-buttons"><!-- wp:button -->\n'
            f'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{html.escape(url)}" target="_blank" rel="sponsored nofollow noopener">{html.escape(label)}</a></div>\n'
            '<!-- /wp:button --></div>\n<!-- /wp:buttons -->')


TIP = re.compile(r"^(petit tip[^:]{0,15}|tip|conseil|astuce|un conseil[^:]{0,25}|le conseil[^:]{0,25}|attention|bon à savoir|à savoir)\s*:", re.I)
TIP_LABEL = re.compile(r'\s*(?:<(strong|b)>)?\s*([^<:]{2,45}?)\s*(</(?:strong|b)>)?\s*:\s*(</(?:strong|b)>)?\s*')


def tipify(b):
    """Paragraphe « Petit tip : … », « Conseil : … » -> paragraphe au style Astuce, libellé seul en gras."""
    m = re.fullmatch(r'<!-- wp:paragraph -->\n<p>(.*)</p>\n<!-- /wp:paragraph -->', b, re.S)
    if not m:
        return b
    inner, t = m.group(1).strip(), txt(m.group(1))
    tm = TIP.match(t)
    if not tm or len(t) - tm.end() < 20:
        return b
    w = re.fullmatch(r'<(strong|b)>(.*)</\1>', inner, re.S)
    if w and f'</{w.group(1)}>' not in w.group(2):
        inner = w.group(2)
    lab = TIP_LABEL.match(inner)
    if not lab:
        return b
    rest = inner[lab.end():]
    if lab.group(1) and not (lab.group(3) or lab.group(4)):
        rest = f'<{lab.group(1)}>' + rest
    label = lab.group(2).strip()
    label = label[0].upper() + label[1:]
    return ('<!-- wp:paragraph {"className":"is-style-tt-tip"} -->\n'
            f'<p class="is-style-tt-tip"><strong>{label} :</strong> {rest.strip()}</p>\n<!-- /wp:paragraph -->')


LONE_LINK = re.compile(r'<!-- wp:paragraph -->\n<p>(?:À lire (?:également|aussi) ?: ?)?(<a href="[^"]+">)(?:<strong>)?([^<]+)(?:</strong>)?</a>\.?</p>\n<!-- /wp:paragraph -->')


def related(blocks):
    """« À lire également : <lien> » suivi d'autres liens seuls -> un encadré « À lire aussi » avec une liste."""
    out, i = [], 0
    while i < len(blocks):
        m = LONE_LINK.fullmatch(blocks[i])
        if m and 'À lire' in blocks[i]:
            links = [m]
            while i + 1 < len(blocks) and LONE_LINK.fullmatch(blocks[i + 1]) and 'À lire' not in blocks[i + 1]:
                i += 1
                links.append(LONE_LINK.fullmatch(blocks[i]))
            lis = ''.join(f'<!-- wp:list-item -->\n<li>{lk.group(1)}{lk.group(2).strip()}</a></li>\n<!-- /wp:list-item -->' for lk in links)
            out.append('<!-- wp:group {"className":"tt-related","layout":{"type":"default"}} -->\n<div class="wp-block-group tt-related">'
                       '<!-- wp:paragraph -->\n<p><strong>À lire aussi</strong></p>\n<!-- /wp:paragraph -->\n\n'
                       f'<!-- wp:list -->\n<ul class="wp-block-list">{lis}</ul>\n<!-- /wp:list --></div>\n<!-- /wp:group -->')
        else:
            out.append(blocks[i])
        i += 1
    return out


def widgets(data):
    out = []

    def walk(els):
        for e in els:
            if e.get('elType') == 'widget' and e.get('widgetType') != 'spacer':
                out.append(e)
            walk(e.get('elements', []))
    walk(data.get('elements', []))
    return out


def convert(page, hub, data, demo=None):
    is_hub = hub is None
    dest = HUB_META[page['slug']][0] if is_hub else HUB_META[hub['slug']][0]
    subject = re.sub(r'\s+(à|de|sur)\s+' + re.escape(dest) + r'\b.*$', '', txt(page['title'])).strip()
    query = dest if is_hub else f'{subject} {dest}'
    w = widgets(data)
    blocks, log, meta = [], [], {}
    gallery_items, address, intro_done = [], '', False
    faq_title = None
    i = 0
    pending_map = False

    def next_heading(j):
        while j < len(w) and w[j]['widgetType'] in ('image', 'breadcrumbs'):
            j += 1
        return txt(w[j]['settings'].get('title', '')) if j < len(w) and w[j]['widgetType'] == 'heading' else ''

    for e in w:
        if e['widgetType'] == 'google_maps':
            address = txt(e['settings'].get('address', ''))
    while i < len(w):
        e, s, t = w[i], w[i]['settings'], w[i]['widgetType']
        if t == 'heading':
            title = norm_title(s.get('title', ''))
            level = s.get('header_size', 'h2')
            if level == 'h1':
                meta['tt_h1'] = title
                i += 1
                continue
            low = title.lower()
            # Encart de réservation (hôtel ou activité) : remplacé par un formulaire partenaire.
            if re.match(r'^(réserv|vous cherchez|tu cherches)', low):
                j, btn = i + 1, None
                while j < len(w) and w[j]['widgetType'] in ('text-editor', 'button', 'image'):
                    if w[j]['widgetType'] == 'button':
                        btn = w[j]['settings']
                    j += 1
                btxt = (btn or {}).get('text', '') if btn else ''
                burl = (btn or {}).get('link', {}).get('url', '') if btn else ''
                if 'hôtel' in low or 'hôtel' in btxt.lower():
                    blocks.append(block('tt/trip-search', {'placement': 'hotel', 'layout': 'band'}))
                    log.append('encart hôtel -> formulaire Trip.com')
                elif 'gyg.me' in burl or 'getyourguide' in burl:
                    blocks.append(heading(f'Réserver {subject}' if not is_hub else f'Réserver une activité à {dest}'))
                    blocks.append(buttons('Voir les disponibilités', burl))
                    log.append('encart activité GYG -> bouton GYG conservé')
                else:
                    blocks.append(block('tt/gyg-search', {'placement': 'activites', 'layout': 'band',
                                                          'title': f'Réserve ta visite : {subject}' if not is_hub else f'Les activités à réserver à {dest}',
                                                          'choices': (f'{subject}|{query}\nToutes les activités à {dest}|{dest}' if not is_hub else '')}))
                    log.append('encart activité Trip.com -> formulaire GetYourGuide')
                i = j
                continue
            if low.startswith('visites') and 'activit' in low:
                blocks.append(heading(f'Que visiter à {dest} ?'))
                blocks.append(block('tt/card-grid', {'mode': 'children', 'columns': 4}))
                if demo:
                    blocks.extend(demo)
                    log.append('blocs GYG de démonstration insérés')
                else:
                    blocks.append(block('tt/gyg-search', {'placement': 'que-visiter', 'layout': 'band', 'title': f'Les activités à réserver à {dest}'}))
                if address:
                    blocks.append(block('tt/map', {'address': address if address.lower() != dest.lower() else dest, 'zoom': 12}))
                    address = ''
                i += 1
                while i < len(w) and w[i]['widgetType'] == 'image-box':
                    i += 1
                continue
            if low.startswith('découvrez'):
                if is_hub:
                    blocks.append(heading('Nos autres destinations en Thaïlande'))
                    blocks.append(block('tt/card-grid', {'mode': 'destinations', 'columns': 4, 'limit': 10}))
                else:
                    blocks.append(heading(f'Les autres visites à {dest}'))
                    blocks.append(block('tt/card-grid', {'mode': 'siblings', 'columns': 4, 'limit': 8}))
                i += 1
                while i < len(w) and w[i]['widgetType'] == 'image-box':
                    i += 1
                continue
            if low.startswith('faq'):
                faq_title = 'Questions fréquentes' + (f' sur {subject}' if subject else '')
                if address and not is_hub:
                    blocks.append(block('tt/map', {'address': f'{subject}, {dest}', 'zoom': 15}))
                    address = ''
                blocks.append(heading(faq_title))
                i += 1
                continue
            if 'comment se rendre' in low and not is_hub:
                pending_map = True
            elif pending_map and address:
                blocks.append(block('tt/map', {'address': f'{subject}, {dest}', 'zoom': 15}))
                address, pending_map = '', False
            if dest == 'Phuket' and 'koh tao' in low:
                title = title.replace('Koh Tao', 'Phuket')
                log.append('titre corrigé : « Comment se déplacer à Koh Tao ? » -> Phuket')
            blocks.append(heading(title, int(level[1]) if level in ('h2', 'h3', 'h4') else 2))
            last_h2 = title
        elif t == 'text-editor':
            if not intro_done and not any(b.startswith('<!-- wp:heading') for b in blocks):
                blocks.extend(lead_blocks(s.get('editor', '')))
                first = re.sub(r'\s+', ' ', txt(re.sub(r'<br\s*/?>', ' ', re.split(r'</p>', s.get('editor', ''))[0])))
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
                tb = to_blocks(clean(s.get('editor', '')))
                # Un intertitre qui répète le titre de section juste au-dessus n'apporte rien.
                if tb and tb[0].startswith('<!-- wp:heading') and blocks and blocks[-1].startswith('<!-- wp:heading') and txt(tb[0]) == txt(blocks[-1]):
                    tb = tb[1:]
                for n, b in enumerate(tb):
                    lone = re.fullmatch(r'<!-- wp:paragraph -->\n<p><a href="([^"]*trip\.com[^"]*)">([^<]+)</a></p>\n<!-- /wp:paragraph -->', b)
                    if lone and 'hôtel' not in lone.group(2).lower():
                        tb[n] = buttons('Voir les activités sur GetYourGuide', GYG.format(q=query.replace(' ', '%20'), cmp=f"tt-{page['slug']}-bouton"))
                        log.append(f'lien Trip.com « {lone.group(2)} » -> GetYourGuide')
                blocks.extend(tb)
        elif t == 'image-carousel':
            gallery_items = [{'id': c.get('id'), 'url': c.get('url')} for c in s.get('carousel', [])]
            if gallery_items:
                blocks.append(gallery(gallery_items))
        elif t == 'image':
            im = s.get('image', {})
            nh = next_heading(i + 1).lower()
            if re.match(r'^(vous cherchez|tu cherches)', nh) or not im.get('id'):
                log.append('image décorative avant encart hôtel supprimée')
            else:
                blocks.append(image(im['id'], im.get('url', '')))
        elif t == 'html':
            h = s.get('html', '')
            if '<table' in h:
                inner = re.search(r'<table[^>]*>(.*)</table>', clean(re.sub(r'<(table|thead|tbody|tr|th|td)\b[^>]*>', r'<\1>', h)), re.S)
                if inner:
                    blocks.append(table(inner.group(1)))
            elif 'trip.com/partners' in h:
                log.append('widget Trip.com en iframe supprimé')
            elif '<style' in h:
                pass
            else:
                log.append('bloc HTML non converti : ' + re.sub(r'\s+', ' ', txt(h))[:60])
        elif t == 'button':
            url, label = s.get('link', {}).get('url', ''), txt(s.get('text', ''))
            if 'gyg.me' in url or 'getyourguide' in url:
                blocks.append(buttons('Voir les disponibilités', url))
            elif 'trip.com' in url and 'hôtel' in label.lower():
                blocks.append(block('tt/trip-search', {'placement': 'hotel', 'layout': 'band'}))
            elif 'trip.com' in url:
                generic = not label or re.match(r'^(clique[zr]? ici|ici)$', label.lower())
                blocks.append(buttons('Voir les activités sur GetYourGuide' if generic else label, GYG.format(q=query.replace(' ', '%20'), cmp=f"tt-{page['slug']}-bouton")))
                log.append(f'bouton Trip.com « {label} » -> GetYourGuide')
            elif url:
                blocks.append(buttons(label, url))
        elif t == 'elementskit-accordion':
            items = s.get('ekit_accordion_items', [])
            if faq_title is None:
                blocks.append(heading('Questions fréquentes'))
            for n, it in enumerate(items):
                blocks.append(details(txt(it.get('acc_title', '')), clean(it.get('acc_content', '')), n == 0))
        i += 1
    if address:
        blocks.append(block('tt/map', {'address': address, 'zoom': 13}))
    if is_hub:
        name, city, syn = HUB_META[page['slug']]
        meta.update({'tt_destination': name, 'tt_gyg_query': name, 'tt_trip_city': city, 'tt_synonyms': syn})
        meta['tt_card_title'] = name
    else:
        meta['tt_gyg_query'] = query
        meta['tt_card_title'] = (subject or norm_title(page['title'])).split(' : ')[0]
    content = '\n\n'.join(tipify(b) for b in related(blocks))
    return content.replace('https://preprod.tourisme-thailande.fr', 'https://tourisme-thailande.fr'), meta, gallery_items, log


def demo_bangkok(img_ids):
    c = lambda **k: block('tt/gyg-card', k)  # noqa: E731
    cards = [c(title='Temples et canaux en bateau longtail', imageId=img_ids['wat-arun'], place='Bangkok', kind='Visite guidée', rating='4,8', reviews='1 520', meta='4 heures · Guide francophone', price='27', query='Bangkok temples', placement='que-visiter'),
             c(title='Street food de Chinatown à la nuit tombée', imageId=img_ids['marche-chatuchak'], place='Bangkok', kind='Gastronomie', rating='4,9', reviews='2 100', meta='3 heures · 10 dégustations', price='39', query='Bangkok street food', placement='que-visiter'),
             c(title="Grand Palais et Wat Phra Kaew sans file d'attente", imageId=img_ids['grand-palais-bangkok'], place='Bangkok', kind='Visite guidée', rating='4,7', reviews='3 860', meta='3 heures · Billets inclus', price='42', query='Grand Palais Bangkok', placement='que-visiter')]
    grid = ('<!-- wp:group {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"200px"}} -->\n<div class="wp-block-group">' + '\n'.join(cards) + '</div>\n<!-- /wp:group -->')
    spot = block('tt/gyg-spotlight', {'title': 'Marché flottant de Damnoen Saduak et marché sur la voie ferrée de Maeklong', 'imageId': img_ids['damnoen-saduak'],
                                      'points': "Départ tôt pour arriver avant les cars de touristes\nBalade en barque dans les canaux du marché\nLe train qui traverse le marché de Maeklong, vu du quai",
                                      'facts': 'Durée | 6 heures\nDépart | Ton hôtel\nLangue | Français\nAnnulation | Gratuite 24 h avant',
                                      'rating': '4,6', 'reviews': '5 300', 'price': '35', 'query': 'Damnoen Saduak Maeklong',
                                      'guideLabel': 'Lire notre guide de Damnoen Saduak', 'guideUrl': '/bangkok/damnoen-saduak/'})
    comp = block('tt/gyg-compare', {'title': 'Marchés flottants : quelle excursion choisir ?', 'criteria': 'Durée\nPour qui\nLe plus',
                                    'items': json.dumps([{'name': 'Damnoen Saduak, matinée', 'imageId': img_ids['damnoen-saduak'], 'values': ['5 heures', 'Premier voyage', 'Barque incluse'], 'price': '29', 'query': 'Damnoen Saduak'},
                                                         {'name': 'Damnoen + marché de Maeklong', 'imageId': img_ids['chao-phraya'], 'best': True, 'values': ['6 heures', 'Voir les deux en une fois', 'Barque + train'], 'price': '35', 'query': 'Damnoen Saduak Maeklong'},
                                                         {'name': 'Amphawa, en fin de journée', 'imageId': img_ids['rattanakosin'], 'values': ['7 heures', 'Ambiance locale', 'Lucioles le soir'], 'price': '39', 'query': 'Amphawa'}], ensure_ascii=False)})
    return [heading('Les activités les plus réservées à Bangkok', 3), grid, spot, comp]


idx = json.load(open(os.path.join(EL, 'index.json')))
feat = {p['id']: p['featured_media'] for p in json.load(open(PAGES))}
by_id = {x['id']: x for x in idx}
result = []
for page in idx:
    hub = by_id.get(page['parent'])
    data = json.load(open(os.path.join(EL, f"{page['id']}.json")))
    demo = None
    if page['slug'] == 'bangkok':
        boxes = {}
        for e in widgets(data):
            if e['widgetType'] == 'image-box':
                boxes[e['settings'].get('link', {}).get('url', '').rstrip('/').split('/')[-1]] = e['settings'].get('image', {}).get('id')
        demo = demo_bangkok(boxes)
    content, meta, gal, log = convert(page, hub, data, demo)
    result.append({'id': page['id'], 'slug': page['slug'], 'hub': page['parent'] == 0, 'content': content, 'meta': meta,
                   'set_thumbnail': (gal[0]['id'] if (not feat.get(page['id']) and gal) else 0), 'log': log,
                   'title': (HUB_META[page['slug']][0] if page['parent'] == 0 else None)})
json.dump(result, open(OUT, 'w'), ensure_ascii=False)
print(len(result), 'pages,', sum(r['content'].count('<!-- wp:') for r in result), 'blocs')
for r in result[:3] + [x for x in result if x['hub']][:2]:
    print(r['slug'], r['meta'].get('tt_h1'), '|', r['meta'].get('tt_intro', '')[:80], '|', r['log'][:4])
