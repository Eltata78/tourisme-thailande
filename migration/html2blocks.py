"""Conversion de HTML nettoyé en blocs Gutenberg natifs.

Utilisé par la migration Elementor -> Gutenberg : chaque fragment HTML (text-editor
Elementor, bloc HTML d'article) est nettoyé puis découpé en blocs core.
"""
import html
import json
import re

EMOJI = re.compile('[\U0001F000-\U0001FAFF\u2300-\u23FF\u2600-\u27BF\u2B00-\u2BFF\uFE0F\u200d\u20e3]+')


def clean(h: str) -> str:
    """Retire les attributs de style, répare les tableaux, transforme les faux intertitres."""
    h = re.sub(r' data-[a-z-]+="[^"]*"', '', h)
    h = re.sub(r'</?(div|section|article)\b[^>]*>', '', h)
    h = re.sub(r' (style|class|id|target|border|cellpadding|cellspacing|width|height|align)="[^"]*"', '', h)
    h = h.replace('<table><th><tr>', '<table><thead><tr>').replace('</tr></th><tbody>', '</tr></thead><tbody>')
    h = re.sub(r'<h([2-4])>\s*' + EMOJI.pattern + r'\s*', r'<h\1>', h)
    h = re.sub(r'<p>\s*<strong>([^<]{2,70})</strong>\s*</p>', r'<h3>\1</h3>', h)
    for emo, cls in (('🟩', 'is-good'), ('🟧', 'is-ok'), ('🟥', 'is-bad')):
        h = re.sub(r'<td>\s*' + emo + r'\s*([^<]*)</td>', r'<td><mark class="tt-st ' + cls + r'">\1</mark></td>', h)
    h = re.sub(r'<p>(?:&nbsp;|\s|<br\s*/?>)*</p>', '', h)
    h = re.sub(r'<span>([^<]*)</span>', r'\1', h)
    h = EMOJI.sub('', h)
    h = re.sub(r'<(strong|b|em|h[2-4]|p|li|td)>\s+', r'<\1>', h)
    return h.strip()


def _attrs(d: dict) -> str:
    return (' ' + json.dumps(d, ensure_ascii=False)) if d else ''


def paragraph(inner: str) -> str:
    return f'<!-- wp:paragraph -->\n<p>{inner.strip()}</p>\n<!-- /wp:paragraph -->'


def heading(text: str, level: int = 2) -> str:
    a = {} if level == 2 else {'level': level}
    return f'<!-- wp:heading{_attrs(a)} -->\n<h{level} class="wp-block-heading">{text.strip()}</h{level}>\n<!-- /wp:heading -->'


def _split_items(inner: str) -> list:
    """Découpe le contenu d'une liste en <li> de premier niveau (gère les listes imbriquées)."""
    items, depth, start = [], 0, None
    for m in re.finditer(r'<(/?)(li|ul|ol)\b[^>]*>', inner):
        closing, tag = m.group(1) == '/', m.group(2)
        if tag == 'li' and not closing and depth == 0:
            start = m.end()
        if tag in ('ul', 'ol'):
            depth += -1 if closing else 1
        if tag == 'li' and closing and depth == 0 and start is not None:
            items.append(inner[start:m.start()])
            start = None
    return items


def list_item(raw: str) -> str:
    sub = re.search(r'<(ul|ol)\b[^>]*>(.*)</\1>\s*$', raw, re.S)
    text, nested = (raw[:sub.start()], list_block(sub.group(2), sub.group(1) == 'ol')) if sub else (raw, '')
    text = re.sub(r'</p>\s*<p>', '<br>', text)
    text = re.sub(r'</?p>', '', text).strip()
    return f'<!-- wp:list-item -->\n<li>{text}{nested}</li>\n<!-- /wp:list-item -->'


def list_block(inner: str, ordered: bool = False) -> str:
    tag = 'ol' if ordered else 'ul'
    lis = '\n'.join(list_item(i) for i in _split_items(inner))
    a = {'ordered': True} if ordered else {}
    return f'<!-- wp:list{_attrs(a)} -->\n<{tag} class="wp-block-list">{lis}</{tag}>\n<!-- /wp:list -->'


def table(inner: str) -> str:
    inner = re.sub(r'\s*\n\s*', '', inner)
    return f'<!-- wp:table -->\n<figure class="wp-block-table"><table class="has-fixed-layout">{inner}</table></figure>\n<!-- /wp:table -->'


def details(summary: str, body_html: str, open_: bool = False) -> str:
    a = {'showContent': True} if open_ else {}
    inner = '\n'.join(to_blocks(body_html)) or paragraph(body_html)
    return (f'<!-- wp:details{_attrs(a)} -->\n<details class="wp-block-details"{" open" if open_ else ""}><summary>{html.escape(summary)}</summary>'
            f'{inner}</details>\n<!-- /wp:details -->')


def image(att_id: int, url: str, alt: str = '') -> str:
    return (f'<!-- wp:image {{"id":{att_id},"sizeSlug":"large","linkDestination":"none"}} -->\n'
            f'<figure class="wp-block-image size-large"><img src="{url}" alt="{html.escape(alt)}" class="wp-image-{att_id}"/></figure>\n<!-- /wp:image -->')


OPEN = re.compile(r'<(p|h2|h3|h4|ul|ol|table)\b[^>]*>')


def _close(h: str, tag: str, pos: int) -> int:
    """Position de la balise fermante équilibrée de `tag` à partir de pos (fin de la balise ouvrante)."""
    depth = 1
    for m in re.finditer(rf'<(/?){tag}\b[^>]*>', h[pos:]):
        depth += -1 if m.group(1) else 1
        if depth == 0:
            return pos + m.start()
    return -1


def to_blocks(h: str) -> list:
    """Découpe un fragment HTML nettoyé en blocs. Le texte hors balise devient un paragraphe."""
    out, pos = [], 0
    while True:
        m = OPEN.search(h, pos)
        end = _close(h, m.group(1), m.end()) if m else -1
        if not m or end < 0:
            break
        stray = h[pos:m.start()].strip()
        if stray and re.sub('<[^>]+>', '', stray).strip():
            out.append(paragraph(stray))
        tag, inner = m.group(1), h[m.end():end]
        if tag == 'p':
            if re.sub('<[^>]+>', '', inner).strip():
                out.append(paragraph(inner))
        elif tag in ('h2', 'h3', 'h4'):
            out.append(heading(re.sub(r'</?strong>', '', inner), int(tag[1])))
        elif tag in ('ul', 'ol'):
            out.append(list_block(inner, tag == 'ol'))
        elif tag == 'table':
            out.append(table(inner))
        pos = end + len(f'</{tag}>')
    tail = h[pos:].strip()
    if tail and re.sub('<[^>]+>', '', tail).strip():
        out.append(paragraph(tail))
    return out


def block(name: str, attrs: dict) -> str:
    """Bloc dynamique auto-fermant (blocs tt/*)."""
    return f'<!-- wp:{name}{_attrs(attrs)} /-->'
