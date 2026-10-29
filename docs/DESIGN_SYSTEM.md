# Ather design system

The owner workspace and the public portfolio are redesigned to match the owner's reference package **exactly** (decisions ق-27, ق-28, ق-29). The package is `ATHER-UI-Package`, an interactive prototype whose `assets/app.js` renders the screens and whose `assets/app.css` styles them. The product renders the same markup on the server, in PHP, and uses the same stylesheet.

## The stylesheet is the reference, ported

`public/assets/css/ather.css` is **generated** from the package's `assets/app.css` by `scripts/tools/port-ather-css.py`. Every rule is kept verbatim and in its original order, so cascade, specificity and every value are identical to the reference. The script makes only these mechanical changes, which it records in the file header:

| Change | Why |
|---|---|
| Base64 `@font-face` blocks become four `url()` faces in `public/assets/fonts/*.woff2` (IBM Plex Sans Arabic and Inter, 400/600, extracted unchanged; OFL licenses in `resources/licenses/`) | Cacheable files; CSP `font-src 'self'` |
| The reference's root token rules (`#ather-navigation`, `[data-theme=light\|dark]`) lose their `--ath-*` declarations. Three product blocks set them instead: light (base), `data-ath-theme=dark`, and `data-ath-theme=system` under `prefers-color-scheme: dark` | The product theme follows the device unless the owner chooses, and works without JavaScript |
| `.ath-nav button` → `.ath-nav :is(button, a)` | Navigation items are links on the server |
| The standalone page rules (`html`, `body`, the dark body) are scoped to `body.ath-app` and follow `data-ath-theme` | The reference's framed page (16px margin, centered 1280px rounded app) applies only to opted-in pages |

**Regenerating:** if the owner sends a new package, run `python -I scripts/tools/port-ather-css.py <ATHER-UI-Package/assets/app.css> public/assets/css/ather.css`. Never edit `ather.css` by hand. Product-only adaptations for server rendering (for example `<details>` instead of script-driven toggles) belong in a separate adapters stylesheet, so the port stays reproducible.

## Root and theme

- **Root:** every rule is under `#ather-navigation`, the reference's root element. Pages that opt in render that element with the reference's attributes:
  - `dir`, `lang`;
  - `data-page`, the reference's page key;
  - `data-ath-theme`: `system`, then `light` or `dark` once `owner_theme.js` applies the owner's choice.
- **Outside the root:** the only rules are the reference's page frame (`body.ath-app`, `.export-frame`).
- **Enforcement:** `DesignSystemTest` rejects any other selector.

## Tokens

The light and dark values are the reference's final ("editorial workspace") palette:

| Token | Light | Dark |
|---|---|---|
| `--ath-bg` | `#f6f5f0` | `#121b19` |
| `--ath-surface` | `#ffffff` | `#1c2722` |
| `--ath-raised` | `#f0f2ed` | `#25312a` |
| `--ath-text` | `#1c3028` | `#edf3ea` |
| `--ath-muted` | `#59685f` | `#afc0b1` |
| `--ath-line` | `#e0e5dc` | `#3a493e` |
| `--ath-input-line` | `#87968b` | `#819783` |
| `--ath-green` / `--ath-on-green` | `#235d45` / `#ffffff` | `#b8d4aa` / `#163025` |
| `--ath-soft` | `#e8f0e6` | `#2e4231` |
| `--ath-gold` | `#84682e` | `#dbc69b` |
| `--ath-danger` / `--ath-danger-bg` | `#9a2828` / `#fff0ee` | `#ffb4ae` / `#382725` |

**The sidebar** has its own palette in both themes, defined by the reference on `.ath-sidebar`, with background `#163629`.

**Contrast:** every text/background pair meets WCAG AA (4.5:1), and field borders meet 3:1; `DesignSystemTest` computes the ratios for light, dark and the sidebar.

## Building a page

1. **Find the screen** in the package. Read its template function in `assets/app.js` (for example `overview()`, `projects()`, `profile()`), and its screenshot in the program folder (`المرجع\لقطات`).
2. **Render the same markup in PHP:**
   - the same elements, nesting and class names (`ath-*`, `an-*`, `eh-*`);
   - the same text, in Arabic and English, from the template's `t('…','…')` pairs.

   Replace only what is interactive in the prototype with real forms and links. Keep every field name, form action and existing hook of the product.
3. **Icons:** Lucide icons as inline SVG (the package's `lucide.min.js` holds the exact paths). Never load an icon script.
4. **Styles:** no `style=` attributes or `<style>` elements (CSP). If the server version needs a rule the reference does not have, put it in the adapters stylesheet with a comment explaining why.
5. **Checks:** compare with the reference screenshot at 1440 Arabic light, 1440 English dark and 390 Arabic light, plus 390 without JavaScript.
