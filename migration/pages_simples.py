"""Pages simples Elementor (Qui sommes-nous, Mentions légales) -> blocs Gutenberg, gabarit Page.

Usage : python3 pages_simples.py <export.json> <sortie.json>
  - export.json : [{id, slug, title, data (Elementor)}]

Le texte reste celui des pages ; on retire le code parasite (copier-coller), le domaine du preprod,
le titre H1 répété (le gabarit l'affiche), et on ajoute des ancres utiles au footer.
"""
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from elementor2blocks import widgets  # noqa: E402
from html2blocks import block, clean, heading, paragraph, to_blocks  # noqa: E402

# Ancre de certains intertitres (liens du footer : « Confidentialité », « Nos liens partenaires »).
ANCHORS = {'Protection des données personnelles': 'donnees-personnelles', 'Cookies': 'cookies'}
PARTNERS = [
    heading('Nos liens partenaires').replace('<!-- wp:heading -->', '<!-- wp:heading {"anchor":"partenaires"} -->')
    .replace('<h2 class="wp-block-heading">', '<h2 class="wp-block-heading" id="partenaires">'),
    paragraph('Pour financer le site sans publicité envahissante, certains liens de nos guides sont des liens partenaires, '
              'notamment pour réserver des activités et des hôtels. Si vous réservez après avoir cliqué sur l’un d’eux, '
              'nous percevons une petite commission, sans aucun surcoût pour vous.'),
    paragraph('Ces partenariats ne changent rien à nos recommandations : nous ne mettons en avant que des lieux et des activités '
              'que nous conseillerions de toute façon.'),
]


def tidy(b):
    """Intertitres sans gras ni deux-points final, ancres, domaine de production."""
    m = re.fullmatch(r'<!-- wp:heading -->\n<h2 class="wp-block-heading">(.*)</h2>\n<!-- /wp:heading -->', b, re.S)
    if m:
        text = re.sub(r'</?strong>', '', m.group(1)).strip().rstrip(' :')
        b = heading(text)
        if text in ANCHORS:
            a = ANCHORS[text]
            b = b.replace('<!-- wp:heading -->', '<!-- wp:heading {"anchor":"%s"} -->' % a).replace('<h2 class="wp-block-heading">', '<h2 class="wp-block-heading" id="%s">' % a)
    return b.replace('preprod.tourisme-thailande.fr', 'tourisme-thailande.fr')


def convert(page):
    out, images = [], 0
    for e in widgets({'elements': page['data']}):
        t, s = e['widgetType'], e['settings']
        if t == 'text-editor':
            out.extend(to_blocks(clean(s.get('editor', ''))))
        elif t == 'heading' and s.get('header_size', 'h2') != 'h1':
            out.append(heading(s.get('title', '')))
        elif t == 'image':
            # Photos des fondateurs : remplacées par les cartes auteurs (photo, présentation, lien vers leurs guides).
            images += 1
            if images == 1:
                out.append(block('tt/authors', {}))
    out = [tidy(b) for b in out]
    if page['slug'] == 'qui-sommes-nous':
        last = next(i for i in range(len(out) - 1, -1, -1) if 'Merci pour votre soutien' in out[i])
        out[last:last] = PARTNERS
    return '\n\n'.join(out)


pages = json.load(open(sys.argv[1]))
rows = [{'id': p['id'], 'slug': p['slug'], 'content': convert(p), 'template': '', 'meta': {}} for p in pages]
json.dump(rows, open(sys.argv[2], 'w'), ensure_ascii=False)
for r in rows:
    print(r['slug'], r['content'].count('<!-- wp:'), 'blocs', re.findall(r'<h2[^>]*>(.*?)</h2>', r['content']))
