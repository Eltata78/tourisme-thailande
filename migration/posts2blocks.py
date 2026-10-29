"""Conversion des articles : blocs HTML maison et bannières Shortcoder -> blocs du thème.

Usage : python3 -I posts2blocks.py <posts.json> <liens_resolus.tsv> <media.json> <sortie.json>

- Encadrés (« Le savais-tu », « À retenir », boîtes à style en ligne) -> groupes aux styles tt-savais / tt-retenir / tt-alerte.
- CTA hôtel (Trip) -> formulaire tt/trip-search ; CTA activité et bannières GYG -> tt/cta-card ; sélections de 3 activités -> tt/gyg-card.
- Programmes horaires (Noël, Nouvel An, Full Moon) -> groupe tt-timeline avec une liste d'étapes.
- FAQ maison -> blocs Détails ; tableaux HTML -> bloc Tableau ; boutons Trip d'hôtel -> bloc Boutons.
- Espaceurs supprimés, émojis retirés du texte (sauf FAQ Yoast, dont le HTML doit rester identique à ses attributs).
Tout ce qui n'est pas reconnu reste tel quel et est signalé dans le journal.
"""
import html
import json
import os
import re
import sys
import unicodedata
import urllib.parse as up

from bs4 import BeautifulSoup

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from html2blocks import EMOJI, block, clean, list_block, paragraph, table, to_blocks  # noqa: E402

TOKEN = re.compile(r'<!--\s+(/)?wp:([a-z0-9/-]+)(\s+(\{.*?\}))?\s+(/)?-->', re.S)
TRIP_KEYS = {'359': 'bangkok', '36030': 'ayutthaya', '622': 'pattaya', '623': 'chiang-mai', '647': 'chiang-rai', '725': 'phuket',
             '1228': 'koh-phi-phi', '1229': 'koh-samui', '19957': 'koh-tao'}
TRIP_NAMES = {'bangkok': 'Bangkok', 'ayutthaya': 'Ayutthaya', 'pattaya': 'Pattaya', 'chiang-mai': 'Chiang Mai', 'chiang-rai': 'Chiang Rai', 'phuket': 'Phuket',
              'krabi': 'Krabi', 'koh-phi-phi': 'Koh Phi Phi', 'koh-samui': 'Koh Samui', 'koh-tao': 'Koh Tao', 'ban-krut': 'Ban Krut'}
# Destination dont parle l'article (titre ou slug) -> requête d'activités ; la première trouvée l'emporte.
DESTS = [('full moon', 'Koh Phangan'), ('koh phangan', 'Koh Phangan'), ('koh samui', 'Koh Samui'), ('samui', 'Koh Samui'), ('koh tao', 'Koh Tao'),
         ('phi phi', 'Koh Phi Phi'), ('koh lanta', 'Koh Lanta'), ('krabi', 'Krabi'), ('ao nang', 'Krabi'), ('james bond', 'Phuket'), ('phuket', 'Phuket'),
         ('khao lak', 'Khao Lak'), ('chiang mai', 'Chiang Mai'), ('chiang rai', 'Chiang Rai'), ('pai', 'Pai'), ('ayutthaya', 'Ayutthaya'),
         ('kanchanaburi', 'Kanchanaburi'), ('hua hin', 'Hua Hin'), ('pattaya', 'Pattaya'), ('bangkok', 'Bangkok')]


def plain(s):
    return unicodedata.normalize('NFKD', s.lower()).encode('ascii', 'ignore').decode()


def txt(s):
    s = EMOJI.sub('', html.unescape(re.sub('<[^>]+>', '', s or ''))).replace('\xa0', ' ')
    return re.sub(r'\s+', ' ', s).strip()


def esc(s):
    return html.escape(s, quote=False)


def split_blocks(content):
    """Blocs de premier niveau : (nom, attributs JSON, texte complet). Le texte hors bloc est rendu tel quel."""
    out, depth, pos, start, name, attrs = [], 0, 0, 0, '', None
    for m in TOKEN.finditer(content):
        closing, nm, _, a, selfclose = m.groups()
        if depth == 0 and m.start() > pos and content[pos:m.start()].strip():
            out.append(('freeform', None, content[pos:m.start()]))
        if selfclose:
            if depth == 0:
                out.append((nm, a, m.group(0)))
                pos = m.end()
            continue
        if not closing:
            if depth == 0:
                start, name, attrs = m.start(), nm, a
            depth += 1
        else:
            depth -= 1
            if depth == 0:
                out.append((name, attrs, content[start:m.end()]))
                pos = m.end()
    if content[pos:].strip():
        out.append(('freeform', None, content[pos:]))
    return out


def scrub(block_text):
    """Retire les émojis du HTML d'un bloc natif, sans toucher aux commentaires de bloc (attributs)."""
    parts = re.split(r'(<!--.*?-->)', block_text, flags=re.S)
    for i in range(0, len(parts), 2):
        p = EMOJI.sub('', parts[i])
        parts[i] = re.sub(r'(<(?:p|li|h[1-6]|td|th|strong|figcaption)(?:\s[^>]*)?>)[ \t\xa0]+', r'\1', p)
    return ''.join(parts)


def group(style, inner_blocks):
    return (f'<!-- wp:group {{"className":"is-style-tt-{style}","layout":{{"type":"default"}}}} -->\n'
            f'<div class="wp-block-group is-style-tt-{style}">' + '\n\n'.join(inner_blocks) + '</div>\n<!-- /wp:group -->')


def styled_paragraph(cls, inner):
    return f'<!-- wp:paragraph {{"className":"{cls}"}} -->\n<p class="{cls}">{inner}</p>\n<!-- /wp:paragraph -->'


def buttons(label, url):
    return ('<!-- wp:buttons -->\n<div class="wp-block-buttons"><!-- wp:button -->\n'
            f'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{html.escape(url)}" target="_blank" rel="sponsored nofollow noopener">{esc(label)}</a></div>\n'
            '<!-- /wp:button --></div>\n<!-- /wp:buttons -->')


class Converter:
    def __init__(self, resolved, media):
        self.resolved = resolved
        self.media = media

    # ---------- liens ----------
    def target(self, url):
        return self.resolved.get(url.rstrip('/'), url)

    def gyg_url(self, url):
        """Lien GetYourGuide déroulé, sans paramètres de suivi (le tag est remis à l'affichage)."""
        r = self.target(url)
        u = up.urlparse(r)
        if 'getyourguide' not in u.netloc:
            return url
        path = re.sub(r'^/fr-fr', '', u.path)
        return 'https://www.getyourguide.fr' + path

    def trip_dest(self, url):
        u = up.urlparse(self.target(url))
        q = dict(up.parse_qsl(u.query))
        if '/hotels/list' not in u.path:
            return None
        if q.get('provinceId') == '10129':
            return 'krabi'
        return TRIP_KEYS.get(q.get('city', ''))

    def media_id(self, url):
        base = re.sub(r'-\d+x\d+(?=\.\w+$)', '', url.split('?')[0].rsplit('/', 1)[-1]).lower()
        return self.media.get(base, 0)

    # ---------- blocs HTML ----------
    def html_block(self, raw, ctx):
        body = re.sub(r'(?s)<style.*?</style>|<script.*?</script>|<!--.*?-->|<!DOCTYPE[^>]*>|</?(?:html|head|body|meta)\b[^>]*>', '', raw).strip()
        if not body:
            ctx['log'].append('bloc HTML de style seul supprimé')
            return []
        soup = BeautifulSoup(body, 'html.parser')
        root = next((c for c in soup.contents if getattr(c, 'name', None)), None)
        if root is None:
            return [paragraph(esc(txt(body)))]
        cls = ' '.join(root.get('class', []))
        style = root.get('style', '')
        if root.name == 'figure' and root.find('table'):
            return self.tables(root)
        if 'quiz-container' in cls or 'figure' == root.name:
            ctx['log'].append(f'bloc HTML conservé ({root.name}.{cls or "style"})')
            return [f'<!-- wp:html -->\n{raw.strip()}\n<!-- /wp:html -->']
        if 'savais-tu' in cls:
            t = root.select_one('.savais-tu__title')
            c = root.select_one('.savais-tu__content')
            return [self.callout('savais', txt(str(t)) if t else 'Le savais-tu ?', c.decode_contents() if c else '')]
        if 'tt-retenir' in cls:
            return [self.callout_from(root, 'retenir')]
        if 'cta-thai' in cls:
            return self.cta_hotel(root, ctx)
        if 'cta-bangkok' in cls:
            return self.cta_activity(root, ctx)
        if re.search(r'(timeline-container|program-container|-plan\b)', cls):
            return [self.timeline(root)]
        if 'faq-th' in cls or 'faq-section' in cls:
            return self.faq(root)
        if root.name == 'table' or root.find('table'):
            return self.tables(root)
        if root.name == 'a':
            return [self.button(root, ctx)]
        if root.name in ('div', 'aside', 'section') and root.find(['p', 'h2', 'h3', 'h4', 'ul']):
            return [self.callout_from(root, self.variant(root, style))]
        if root.name in ('div', 'aside', 'section') and txt(root.decode_contents()):
            # Texte directement dans la boîte : « 💡 <strong>Bon à savoir :</strong> … »
            inner = root.decode_contents().strip()
            m = re.match(r'^[^<\w]*<strong>([^<]{2,60}?)\s*:?\s*</strong>\s*:?\s*(.*)$', inner, re.S)
            title, rest = (txt(m.group(1)), m.group(2)) if m else ('', inner)
            return [self.callout(self.variant(root, style, title), title, f'<p>{rest}</p>')]
        if root.name in ('strong', 'p', 'span'):
            return [paragraph(root.decode_contents())]
        ctx['log'].append(f'bloc HTML non reconnu conservé ({root.name}.{cls})')
        return [f'<!-- wp:html -->\n{raw.strip()}\n<!-- /wp:html -->']

    def variant(self, root, style, title=''):
        first = root.find(['p', 'h2', 'h3', 'h4'])
        title = plain(title or (txt(str(first)) if first else ''))
        if re.search(r'retenir|retiens|essentiel|en bref|resume', title):
            return 'retenir'
        if re.search(r'attention|danger|risque|arnaque|interdit', title):
            return 'alerte'
        if re.search(r'savais|bon a savoir|info|conseil|astuce', title):
            return 'savais'
        if re.search(r'#a51931|#c62828|#d32f2f|#e53935', style.lower()):
            return 'alerte'
        if '#2d2a4a' in style.lower():
            return 'retenir'
        return 'savais'

    def callout_from(self, root, variant):
        """Encadré dont le premier paragraphe en gras est le titre."""
        first = next((c for c in root.find_all(['p', 'h2', 'h3', 'h4'], recursive=False)), None) or root.find(['p', 'h2', 'h3', 'h4'])
        title = txt(str(first)) if first else ''
        bold = first is not None and (first.name != 'p' or re.match(r'^\s*(<[^>]+>\s*)*<(strong|b)>', first.decode_contents()) and len(txt(''.join(str(x) for x in first.find_all(['strong', 'b'])))) >= len(title) - 3)
        if bold and len(title) <= 90:
            first.extract()
        else:
            title = ''
        return self.callout(variant, title, root.decode_contents())

    def callout(self, variant, title, inner_html):
        inner = re.sub(r'<span class="emoji-icon">.*?</span>', '', inner_html)
        blocks = to_blocks(clean(re.sub(r'</?(div|aside|section)\b[^>]*>', '', inner)))
        head = [paragraph(f'<strong>{esc(title.rstrip(" :"))}</strong>')] if title else []
        return group(variant, head + blocks)

    # ---------- CTA ----------
    def cta_hotel(self, root, ctx):
        a = root.find('a')
        url = a.get('href', '') if a else ''
        u = up.urlparse(self.target(url))
        if 'hotel' in u.path:
            # Destination de l'article si Trip la connaît, sinon celle du lien d'origine (souvent un lien générique vers Bangkok).
            mine = next((k for k, n in TRIP_NAMES.items() if n == ctx['query']), None)
            dest = mine or self.trip_dest(url)
            ctx['log'].append('CTA hôtel -> formulaire Trip' + (f' ({dest})' if dest else ''))
            attrs = {'title': 'Trouve ton hôtel' + (f' à {TRIP_NAMES[mine]}' if mine else ' en Thaïlande'), 'placement': 'hotel', 'layout': 'band'}
            if dest:
                attrs['city'] = dest
            return [block('tt/trip-search', attrs)]
        return self.cta_activity(root, ctx)

    def cta_activity(self, root, ctx):
        a = root.find('a')
        head = root.find(['h', 'h2', 'h3']) or root.select_one('.cta-title, .cta-thai-title')
        texts = [txt(str(p)) for p in root.find_all('p') if not (head is not None and p is head)]
        url = a.get('href', '') if a else ''
        attrs = {'title': txt(str(head)) if head else 'Les activités à réserver', 'text': next((t for t in texts if t), ''),
                 'label': txt(a.get_text()) if a else 'Voir les activités', 'placement': 'cta'}
        full = self.gyg_url(url) if url else ''
        if 'getyourguide' in full:
            attrs['url'] = full
        else:
            attrs['query'] = ctx['query']  # lien Trip d'activités : les activités passent par GetYourGuide
        ctx['log'].append('CTA activité -> carte de réservation')
        return [block('tt/cta-card', {k: v for k, v in attrs.items() if v})]

    def button(self, a, ctx):
        url = a.get('href', '')
        label = txt(a.get_text())
        if 'trip.com' in url and re.search(r'disponibilit|r[ée]serv', plain(label)):
            label = 'Voir les disponibilités'
        ctx['log'].append('lien-bouton -> bloc Boutons')
        return buttons(label or 'Voir les disponibilités', url)

    # ---------- programmes, FAQ, tableaux ----------
    def timeline(self, root):
        for e in root.select('.emoji-icon'):
            e.decompose()
        is_item = lambda el: any(re.search(r'(^|-)item(-|$)|^event$', c) for c in el.get('class', []))  # noqa: E731
        is_day = lambda el: any('day' in c for c in el.get('class', []))  # noqa: E731
        is_tip = lambda el: any('tip' in c for c in el.get('class', []))  # noqa: E731
        head = root.find(['h', 'h1', 'h2', 'h3'])
        header = root.find(class_=re.compile(r'header'))
        sub = header.find('p') if header else None
        blocks = [paragraph(f'<strong>{esc(txt(str(head)))}</strong>')] if head else []
        if sub is not None:
            blocks.append(paragraph(esc(txt(str(sub)))))
        steps, tips = [], []

        def flush():
            if steps:
                blocks.append(list_block(''.join(f'<li>{s}</li>' for s in steps), True))
                steps.clear()
        for el in root.find_all(True):
            if el.find_parent(is_item) or el.find_parent(is_tip):
                continue
            if is_day(el):
                flush()
                blocks.append(styled_paragraph('tt-tl-day', esc(txt(str(el)))))
            elif is_item(el):
                time = el.find(class_=re.compile(r'time'))
                title = el.find(class_=re.compile(r'title')) or el.find(['h3', 'h4'])
                t_time, t_title = txt(str(time)) if time else '', txt(str(title)) if title else ''
                for x in (time, title):
                    if x is not None:
                        x.extract()
                tops = [u for u in el.find_all('ul') if u.find_parent('ul') is None]  # les sous-listes restent dans leur liste
                lists = ''.join(str(u) for u in tops)
                for u in tops:
                    u.extract()
                text = '<br>'.join(t for t in (re.sub(r'\s+', ' ', p.decode_contents()).strip() for p in el.find_all('p')) if t)
                text = EMOJI.sub('', text)
                raw = (f'<span class="tt-tl-time">{esc(t_time)}</span>' if t_time else '') + (f'<strong>{esc(t_title)}</strong>' if t_title else '')
                raw += text  # le titre de l'étape est déjà sur sa propre ligne
                if lists:
                    raw += re.sub(r'\s+', ' ', clean(lists))
                steps.append(raw)
            elif is_tip(el):
                tips.append(re.sub(r'\s+', ' ', EMOJI.sub('', el.decode_contents())).strip())
        flush()
        for t in tips:
            blocks.append(styled_paragraph('is-style-tt-tip', re.sub(r'</?(p|div|h\d)\b[^>]*>', ' ', t).strip()))
        return group('timeline', blocks)

    def faq(self, root):
        out = []
        for h in root.find_all('h2'):
            out.append(f'<!-- wp:heading -->\n<h2 class="wp-block-heading">{esc(txt(str(h)))}</h2>\n<!-- /wp:heading -->')
        for item in root.select('details, .accordion-item'):
            q = item.find(['summary', 'button']) or item.find(class_=re.compile('header'))
            ans = item.find(class_=re.compile(r'content|answer')) or item
            if q is not None:
                question = txt(str(q))
                q.extract()
                inner = '\n'.join(to_blocks(clean(ans.decode_contents()))) or paragraph(esc(txt(str(ans))))
                out.append(f'<!-- wp:details -->\n<details class="wp-block-details"><summary>{esc(question)}</summary>{inner}</details>\n<!-- /wp:details -->')
        return out

    def tables(self, root):
        out = []
        for t in ([root] if root.name == 'table' else root.find_all('table')):
            for s in t.select('.picto'):
                s.decompose()
            inner = re.sub(r'<(table|thead|tbody|tr|th|td)\b[^>]*>', r'<\1>', t.decode_contents())
            inner = re.sub(r'</?span\b[^>]*>', '', inner)
            out.append(table(clean(inner)))
        return out

    # ---------- bannières Shortcoder ----------
    def shortcode(self, text, ctx):
        m = re.search(r'\[sc name="([^"]+)"(.*?)\]', text, re.S)
        if not m:
            return [text]
        name, a = m.group(1), dict(re.findall(r'(\w+)="([^"]*)"', m.group(2)))
        if name == 'gyg-horizontal':
            img = a.get('image', '')
            label = txt(a.get('texte_cta', ''))
            attrs = {'title': txt(a.get('titre', '')), 'text': txt(a.get('description', '')), 'rating': a.get('note', ''), 'reviews': a.get('avis', ''),
                     'label': label if 0 < len(label) <= 28 else 'Voir les disponibilités', 'url': self.gyg_url(a.get('url', '')), 'placement': 'banniere'}
            mid = self.media_id(img) if img else 0
            attrs['imageId' if mid else 'imageUrl'] = mid or img
            ctx['log'].append('bannière GYG -> carte de réservation')
            return [block('tt/cta-card', {k: v for k, v in attrs.items() if v})]
        if name == 'activite-gyg':
            cards = []
            for i in '123':
                if not a.get('titre' + i):
                    continue
                img = a.get('img' + i, '')
                mid = self.media_id(img) if img else 0
                card = {'title': txt(a['titre' + i]), 'rating': a.get('note' + i, '').replace('.', ','),
                        'reviews': f"{int(a['avis' + i]):,}".replace(',', ' ') if a.get('avis' + i, '').isdigit() else a.get('avis' + i, ''),
                        'meta': txt(a.get('duree' + i, '')), 'url': self.gyg_url(a.get('url' + i, '')), 'placement': 'selection'}
                card['imageId' if mid else 'imageUrl'] = mid or img
                cards.append(block('tt/gyg-card', {k: v for k, v in card.items() if v}))
            ctx['log'].append(f'sélection de {len(cards)} activités -> cartes activité')
            return ['<!-- wp:group {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"200px"}} -->\n<div class="wp-block-group">'
                    + '\n'.join(cards) + '</div>\n<!-- /wp:group -->']
        ctx['log'].append(f'shortcode {name} conservé')
        return [text]

    def freeform(self, text, ctx):
        """HTML hors blocs (articles en mode classique) : éléments simples -> blocs natifs, boîtes -> encadrés."""
        out, buf = [], []
        soup = BeautifulSoup(re.sub(r'(?s)<style.*?</style>', '', text), 'html.parser')

        def flush():
            if ''.join(buf).strip():
                out.extend(to_blocks(clean(''.join(buf))))
            buf.clear()
        for el in soup.contents:
            if getattr(el, 'name', None) in ('div', 'aside', 'section', 'table', 'figure'):
                flush()
                out.extend(self.html_block(str(el), ctx))
            else:
                buf.append(str(el))
        flush()
        ctx['log'].append('HTML hors blocs converti en blocs')
        return out

    # ---------- article ----------
    def repair(self, c):
        """Insertions de liens internes mal placées par un outil : paragraphe glissé dans un intertitre,
        élément de liste ajouté sans son bloc, « n » laissé à la place d'un retour à la ligne."""
        c = re.sub(r'(</h[1-6]>)n\s*(<!-- wp:paragraph -->.*?<!-- /wp:paragraph -->)\s*(<!-- /wp:heading -->)', r'\1\n\3\n\n\2', c, flags=re.S)
        c = re.sub(r'</li>n(<li>)', '</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n\\1', c)
        c = re.sub(r'<strong>(<a [^>]*>)<strong>([^<]*)</strong></a>', r'\1<strong>\2</strong></a>', c)  # gras ouvert deux fois autour d'un lien
        return re.sub(r'(</(?:p|li|h[1-6]|ul|ol|table|figure)>)n(?=\s*<)', r'\1\n', c)

    def convert(self, post):
        post = dict(post, content=self.repair(post['content']))
        title = plain(post['title'] + ' ' + post['slug'].replace('-', ' '))
        query = next((q for k, q in DESTS if re.search(r'\b' + re.escape(k) + r'\b', title)), 'Thaïlande')
        ctx = {'log': [], 'query': query}
        out = []
        for name, _attrs, text in split_blocks(post['content']):
            if name == 'spacer':
                continue
            if name == 'html':
                inner = re.sub(r'^<!--\s+wp:html\s+-->|<!--\s+/wp:html\s+-->$', '', text.strip())
                out.extend(self.html_block(inner, ctx))
            elif name == 'shortcoder/shortcoder':
                out.extend(self.shortcode(text, ctx))
            elif name.startswith('yoast/'):
                out.append(text.strip())
            elif name == 'freeform':
                out.extend(self.freeform(text, ctx))
            else:
                # Blocs HTML rangés dans un groupe ou des colonnes : convertis sur place.
                text = re.sub(r'<!--\s+wp:html\s+-->(.*?)<!--\s+/wp:html\s+-->', lambda m: '\n\n'.join(self.html_block(m.group(1), ctx)), text, flags=re.S)
                out.append(scrub(text.strip()))
        content = '\n\n'.join(b for b in out if b)
        content = re.sub(r'<!-- wp:list-item -->\s*<li>n</li>\s*<!-- /wp:list-item -->\s*', '', content)  # élément vide laissé par la même erreur
        content = content.replace('https://preprod.tourisme-thailande.fr', 'https://tourisme-thailande.fr')
        return {'id': post['id'], 'slug': post['slug'], 'content': content, 'meta': {'tt_gyg_query': query}, 'log': ctx['log']}


def main():
    posts = json.load(open(sys.argv[1]))
    resolved = {}
    for line in open(sys.argv[2]):
        if '\t' in line:
            s, r = line.rstrip('\n').split('\t', 1)
            resolved[s.rstrip('/')] = r or s
    media = {}
    for m in json.load(open(sys.argv[3])):
        media[re.sub(r'-\d+x\d+(?=\.\w+$)', '', m['source_url'].rsplit('/', 1)[-1]).lower()] = m['id']
    conv = Converter(resolved, media)
    only = set(sys.argv[5:])
    result = [conv.convert(p) for p in posts if not only or p['slug'] in only]
    json.dump(result, open(sys.argv[4], 'w'), ensure_ascii=False)
    print(len(result), 'articles,', sum(r['content'].count('<!-- wp:') for r in result), 'blocs,',
          sum(r['content'].count('<!-- wp:html') for r in result), 'blocs HTML restants')


if __name__ == '__main__':
    main()
