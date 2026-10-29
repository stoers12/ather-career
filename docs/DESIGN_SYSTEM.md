# Ather design system

The redesign (decisions ق-27 and ق-28) uses one shared stylesheet, `public/assets/css/ather.css`. Its values come from the owner's reference prototype (`ATHER-UI-Package`, its final "editorial workspace" tokens). The prototype is a visual reference only. Pages are server-rendered PHP, work without JavaScript and follow the strict CSP.

## Opting in

Every rule is scoped to `body.ath-app`. A page changes only when its layout adds that class (the owner shell does it in UI-02), so existing pages are untouched until they move. `DesignSystemTest` rejects any unscoped selector.

## Theme

- **Light** is the base palette.
- **Dark** applies in two cases:
  - the device prefers dark and the owner has not chosen light;
  - `owner_theme.js` has recorded an explicit `data-evidence-hub-theme="dark"`.
- **Without JavaScript** the device preference decides.
- The two dark blocks must stay identical; the test enforces it.
- **The sidebar** (`.ath-sidebar`) keeps its forest palette in both themes.

## Tokens

| Token | Light | Dark | Use |
|---|---|---|---|
| `--ath-bg` | `#f6f5f0` | `#121b19` | Page background |
| `--ath-surface` | `#ffffff` | `#1c2722` | Cards, fields |
| `--ath-raised` | `#f0f2ed` | `#25312a` | Hover, tags, quiet fills |
| `--ath-text` | `#1c3028` | `#edf3ea` | Body text |
| `--ath-muted` | `#59685f` | `#afc0b1` | Secondary text |
| `--ath-line` | `#e0e5dc` | `#3a493e` | Borders, dividers |
| `--ath-input-line` | `#87968b` | `#819783` | Field borders (3:1 non-text contrast) |
| `--ath-green` | `#235d45` | `#b8d4aa` | Primary action, success, links |
| `--ath-on-green` | `#ffffff` | `#163025` | Text on green |
| `--ath-soft` | `#e8f0e6` | `#2e4231` | Next-step banners, status badges |
| `--ath-gold` | `#84682e` | `#dbc69b` | Eyebrows, the wordmark's first letter |
| `--ath-danger` / `--ath-danger-bg` | `#9a2828` / `#fff0ee` | `#ffb4ae` / `#382725` | Errors |
| `--ath-sidebar` | `#163629` | `#163629` | Navigation rail |

Every text/background pair in use meets WCAG AA (4.5:1), and field borders meet 3:1. `DesignSystemTest` computes the ratios for the light, dark and sidebar palettes. A token change that breaks AA fails the build.

## Type

- **Arabic:** IBM Plex Sans Arabic 400/600 as `Ather Arabic`.
- **Latin:** Inter 400/600 as `Ather Sans`.
  - Use `.ath-latin` (or `lang="en"`) for names, URLs and technologies inside Arabic text.
- **Public display headings:** Georgia (system).
- **Font files:** `public/assets/fonts/*.woff2`, extracted unchanged from the prototype. They are subsets: characters outside a subset fall back to the system font.
- **Licenses:** OFL, in `resources/licenses/`.
- **Sizes:** body 14px/1.7, `h1` 29px (25px at 660px and below), `h2` 20px, `h3` 16px.

## Components

| Class | Element |
|---|---|
| `.ath-wordmark` | The ATHER wordmark; the first letter is gold |
| `.ath-eyebrow` | Small gold label above a heading |
| `.ath-page-heading` | Title row with description and actions |
| `.ath-breadcrumb` | Location trail; mark the current item `aria-current="page"` |
| `.ath-button` + `.ath-button-primary` / `-ghost` / `-danger` | Links and buttons look the same |
| `.ath-panel` | Card surface |
| `.ath-next-banner` | "Your next step" band |
| `.ath-callout` | Quiet note with a gold edge |
| `.ath-empty` | Empty state |
| `.ath-status`, `.ath-status-muted` | Published / draft badges |
| `.ath-tag` | Technology chips (Latin face) |
| `.ath-field` with `label`, `small`, `.ath-field-error` | Form field. Mark invalid inputs `aria-invalid="true"` |
| `.ath-error-summary` | Form error summary |

## Rules for pages

1. **Do not change behavior.** Keep field names, form actions, links and messages.
2. **Styles live in CSS files only:** no `style=` attributes, no `<style>` elements (CSP `style-src 'self'`).
3. **Icons are inline SVG rendered by PHP** (Lucide), never a JavaScript icon library.
4. **Escape every owner-authored value.** Wrap labels of unknown direction in `<bdi dir="auto">`.
5. **Check every page** in RTL and LTR, at 390px and 1280px, in light and dark, with no horizontal overflow.
