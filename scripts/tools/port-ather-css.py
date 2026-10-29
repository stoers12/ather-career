"""Port the owner's reference CSS (ATHER-UI-Package/assets/app.css) to public/assets/css/ather.css.

Every rule is kept verbatim and in order. The only changes, each listed in the
output header, are:
  1. @font-face blocks with base64 data are replaced by four url() faces.
  2. Root token rules (#ather-navigation, [data-theme=light|dark]) lose their
     --ath-* and color-scheme declarations; the product's theme blocks (light
     base, explicit dark, device dark) are prepended instead.
  3. `.ath-nav button` also matches links: `.ath-nav :is(button, a)`.
  4. The standalone export page rules (html, body, dark body) are scoped to
     opted-in pages and follow the product theme attribute.
Usage: python -I scripts/tools/port-ather-css.py <ATHER-UI-Package/assets/app.css> public/assets/css/ather.css
"""
import re
import sys

src, out = sys.argv[1], sys.argv[2]
css = open(src, encoding='utf-8').read()
css = re.sub(r'@font-face\{[^}]+\}', '', css)

ROOT_TOKEN_SELECTORS = {
    '#ather-navigation',
    '#ather-navigation[data-theme=light]',
    '#ather-navigation[data-theme=dark]',
}
counts = {'token_rules': 0, 'nav_button': 0, 'export_rules': 0}


def strip_tokens(body):
    kept = [d for d in body.split(';') if d.strip() and not re.match(r'\s*(--ath-[a-z-]+|color-scheme)\s*:', d)]
    return ';'.join(kept)


def rewrite_rule(selector, body):
    clean = re.sub(r'/\*.*?\*/', '', selector, flags=re.S).strip()
    if clean in ROOT_TOKEN_SELECTORS:
        counts['token_rules'] += 1
        body = strip_tokens(body)
        if not body.strip():
            return ''
    if clean == 'html':
        counts['export_rules'] += 1
        selector = 'html:has(> body.ath-app)'
    elif clean == 'body':
        counts['export_rules'] += 1
        selector = 'body.ath-app'
    elif clean == 'body:has(#ather-navigation[data-theme=dark])':
        counts['export_rules'] += 1
        return ('body.ath-app:has(#ather-navigation[data-ath-theme=dark]) {' + body + '}\n'
                '@media (prefers-color-scheme: dark) { body.ath-app:has(#ather-navigation[data-ath-theme=system]) {' + body + '} }')
    if '.ath-nav button' in selector:
        counts['nav_button'] += selector.count('.ath-nav button')
        selector = selector.replace('.ath-nav button', '.ath-nav :is(button, a)')
    return selector.strip() + ' {' + body + '}'


def walk(text, media=None):
    pieces, i = [], 0
    while i < len(text):
        brace = text.find('{', i)
        if brace < 0:
            break
        head = text[i:brace]
        if head.strip().startswith('@'):
            depth, j = 1, brace + 1
            while depth and j < len(text):
                depth += {'{': 1, '}': -1}.get(text[j], 0)
                j += 1
            inner = walk(text[brace + 1:j - 1], head.strip())
            if inner.strip():
                pieces.append(head.strip() + ' {\n' + inner + '\n}')
            i = j
        else:
            end = text.find('}', brace)
            rule = rewrite_rule(head, text[brace + 1:end])
            if rule:
                pieces.append(rule)
            i = end + 1
    return '\n'.join(pieces)


ported = walk(css)
assert '[data-theme=' not in ported.replace('[data-theme=dark]', ''), 'unexpected theme selector left'

LIGHT = ('--ath-bg:#f6f5f0;--ath-surface:#ffffff;--ath-raised:#f0f2ed;--ath-sidebar:#163629;--ath-text:#1c3028;'
         '--ath-muted:#59685f;--ath-line:#e0e5dc;--ath-input-line:#87968b;--ath-green:#235d45;--ath-on-green:#fff;'
         '--ath-soft:#e8f0e6;--ath-gold:#84682e;--ath-danger:#9a2828;--ath-danger-bg:#fff0ee;--ath-shadow:0 15px 36px #14382914;'
         'color-scheme:light')
DARK = ('--ath-bg:#121b19;--ath-surface:#1c2722;--ath-raised:#25312a;--ath-text:#edf3ea;--ath-muted:#afc0b1;'
        '--ath-line:#3a493e;--ath-input-line:#819783;--ath-green:#b8d4aa;--ath-on-green:#163025;--ath-soft:#2e4231;'
        '--ath-gold:#dbc69b;--ath-danger:#ffb4ae;--ath-danger-bg:#382725;--ath-shadow:0 15px 36px #0004;color-scheme:dark')

header = f'''/*
 * Ather design system (decisions ق-27, ق-28, ق-29). GENERATED from the owner's
 * reference package ATHER-UI-Package/assets/app.css: every rule verbatim and in
 * order, so the product renders exactly as the reference. Changes from the
 * source, all mechanical (see docs/DESIGN_SYSTEM.md):
 *   - fonts: base64 @font-face -> public/assets/fonts/*.woff2;
 *   - theme: the {counts['token_rules']} root token rules lose their tokens; the blocks below
 *     set them from data-ath-theme (light | dark | system = device);
 *   - links: `.ath-nav button` -> `.ath-nav :is(button, a)` ({counts['nav_button']} selectors);
 *   - export page: html/body rules scoped to body.ath-app ({counts['export_rules']} rules).
 * Product adaptations for server rendering live in ather-adapters.css.
 */
@font-face{{font-family:'Ather Arabic';font-style:normal;font-weight:400;font-display:swap;src:url("../fonts/ibm-plex-sans-arabic-400.woff2") format("woff2")}}
@font-face{{font-family:'Ather Arabic';font-style:normal;font-weight:600;font-display:swap;src:url("../fonts/ibm-plex-sans-arabic-600.woff2") format("woff2")}}
@font-face{{font-family:'Ather Sans';font-style:normal;font-weight:400;font-display:swap;src:url("../fonts/inter-400.woff2") format("woff2")}}
@font-face{{font-family:'Ather Sans';font-style:normal;font-weight:600;font-display:swap;src:url("../fonts/inter-600.woff2") format("woff2")}}
#ather-navigation {{{LIGHT}}}
#ather-navigation[data-ath-theme=dark] {{{DARK}}}
@media (prefers-color-scheme: dark) {{ #ather-navigation[data-ath-theme=system] {{{DARK}}} }}
'''
open(out, 'w', encoding='utf-8', newline='\n').write(header + ported + '\n')
print(counts, len(header + ported))
