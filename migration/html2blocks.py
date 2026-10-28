"""Conversion de HTML nettoyé en blocs Gutenberg natifs.

Utilisé par la migration Elementor -> Gutenberg : chaque fragment HTML (text-editor
Elementor, bloc HTML d'article) est nettoyé puis découpé en blocs core.
"""
import html
import json
import re

EMOJI = re.compile('[\U0001F000-\U0001FAFF☀-➿️‍]+')


def clean(h: str) -> str:
    """Retire les attributs de style, répare les tableaux, transforme les faux intertitres."""
    h = re.sub(r' data-(start|end)="\d+"', '', h)
    h = re.sub(r' (style|class|id|target|border|cellpadding|cellspacing|width|height|align)="[^"]*"', '', h)
    h = h.replace('<table><th><tr>', '<table><thead><tr>').replace('</tr></th><tbody>', '</tr></thead><tbody>')
    h = re.sub(r'<h([2-4])>\s*' + EMOJI.pattern + r'\s*', r'<h\1>', h)
    h = re.sub(r'<p>\s*<strong>([^<]{2,70})</strong>\s*</p>', r'<h3>\1</h3>', h)
    for emo, cls in (('🟩', 'is-good'), ('🟧', 'is-ok'), ('🟥', 'is-bad')):
        h = re.sub(r'<td>\s*' + emo + r'\s*([^<]*)</td>', r'<td><mark class="tt-st ' + cls + r'">\1</mark></td>', h)
    h = re.sub(r'<p>(?:&nbsp;|\s|<br\s*/?>)*</p>', '', h)
    h = re.sub(r'<span>([^<]*)</span>', r'\1', h)
    return EMOJI.sub('', h).strip()


def _attrs(d: dict) -> str:
    return (' ' + json.dumps(d, ensure_ascii=False)) if d else ''


def paragraph(inner: str) -> str:
    return f'<!-- wp:paragraph -->\n<p>{inner.strip()}</p>\n<!-- /wp:paragraph -->'


def heading(text: str, level: int = 2) -> str:
    a = {} if level == 2 else {'level': level}
    return f'<!-- wp:heading{_attrs(a)} -->\n<h{level} class="wp-block-heading">{text.strip()}</h{level}>\n<!-- /wp:heading -->'


def list_block(inner: str, ordered: bool = False) -> str:
    items = re.findall(r'<li>(.*?)</li>', inner, re.S)
    tag = 'ol' if ordered else 'ul'
    lis = '\n'.join(f'<!-- wp:list-item -->\n<li>{i.strip()}</li>\n<!-- /wp:list-item -->' for i in items)
    a = {'ordered': True} if ordered else {}
    return f'<!-- wp:list{_attrs(a)} -->\n<{tag} class="wp-block-list">{lis}</{tag}>\n<!-- /wp:list -->'


def table(inner: str) -> str:
    inner = re.sub(r'\s*\n\s*', '', inner)
    return f'<!-- wp:table -->\n<figure class="wp-block-table"><table>{inner}</table></figure>\n<!-- /wp:table -->'


def details(summary: str, body_html: str, open_: bool = False) -> str:
    a = {'showContent': True} if open_ else {}
    inner = '\n'.join(to_blocks(body_html)) or paragraph(body_html)
    return (f'<!-- wp:details{_attrs(a)} -->\n<details class="wp-block-details"{" open" if open_ else ""}><summary>{html.escape(summary)}</summary>'
            f'{inner}</details>\n<!-- /wp:details -->')


def image(att_id: int, url: str, alt: str = '') -> str:
    return (f'<!-- wp:image {{"id":{att_id},"sizeSlug":"large","linkDestination":"none"}} -->\n'
            f'<figure class="wp-block-image size-large"><img src="{url}" alt="{html.escape(alt)}" class="wp-image-{att_id}"/></figure>\n<!-- /wp:image -->')


TOKEN = re.compile(r'<(p|h2|h3|h4|ul|ol|table)\b[^>]*>(.*?)</\1>', re.S)


def to_blocks(h: str) -> list:
    """Découpe un fragment HTML nettoyé en blocs. Le texte hors balise devient un paragraphe."""
    out, pos = [], 0
    for m in TOKEN.finditer(h):
        stray = h[pos:m.start()].strip()
        if stray and re.sub('<[^>]+>', '', stray).strip():
            out.append(paragraph(stray))
        tag, inner = m.group(1), m.group(2)
        if tag == 'p':
            if re.sub('<[^>]+>', '', inner).strip():
                out.append(paragraph(inner))
        elif tag in ('h2', 'h3', 'h4'):
            out.append(heading(re.sub(r'</?strong>', '', inner), int(tag[1])))
        elif tag in ('ul', 'ol'):
            out.append(list_block(inner, tag == 'ol'))
        elif tag == 'table':
            out.append(table(inner))
        pos = m.end()
    tail = h[pos:].strip()
    if tail and re.sub('<[^>]+>', '', tail).strip():
        out.append(paragraph(tail))
    return out


def block(name: str, attrs: dict) -> str:
    """Bloc dynamique auto-fermant (blocs tt/*)."""
    return f'<!-- wp:{name}{_attrs(attrs)} /-->'
