# Engineering guidelines: redesign and new features

Approved by the owner on 2026-10-29 (decision ق-31) for the site redesign and the features that come with it. These rules add to [AGENTS.md](../AGENTS.md); when they conflict, stop and ask the owner.

The redesign reference is the owner's handoff package `ATHER-Agent-Handoff`: its preview, `AGENT-PROMPT.md` and `INTEGRATION-GUIDE.md`. It is the only authority for how the product looks.

## 1. Who owns what: frontend and backend

**The split:** Codex owns the **frontend**, and Claude owns the **backend**. Claude also reviews every frontend PR before the owner merges it.

| | Frontend (Codex) | Backend (Claude) |
|---|---|---|
| **Owns** | Presentation markup: `render…` functions and templates in `includes/*_presentation.php`, and the HTML parts of handlers. Also CSS and JavaScript in `public/assets/`, and the visible copy | `handlers/` request logic, data and validation in `includes/` and `src/`, `database/`, `config/routes.php`, authorization, sessions, email and Auth0 |
| **Never changes** | Field names, form actions and methods, queries, validation, authorization, the database | How pages look |
| **When it needs something from the other side** | Stops and asks; never works around it | Updates the feature contract (rule 2) and tells the frontend |

Inside a handler, presentation is the HTML it outputs. Everything before the output (input, checks, writes, redirects) is backend.

## 2. A contract for every feature that has both sides

Before frontend work starts on such a feature, the backend delivers a short contract in the PR or the handoff. It lists:
- **Route:** the URL and method (GET or POST).
- **Template data:** every value the template receives, with its name, type and meaning.
- **Form fields:** the exact names, maximum lengths and which are required.
- **States:** the error and success messages and when each appears.
- **After saving:** where the request redirects.

Neither side changes a contract alone.

## 3. File organization

- **New page:** a handler in `handlers/` plus one line in `config/routes.php`. Nothing goes in `public/` except static assets.
- **One responsibility per file:** logic and presentation live in separate files. Never mix saving with HTML.
- **CSS:**
  - prefer the existing stylesheets;
  - a page that needs its own styles gets one file;
  - **never** add a second, competing styling system.
- **File size:** keep PHP files under about 600 lines. Split by responsibility when they grow ([ARCHITECTURE.md](ARCHITECTURE.md)).
- **Names:** follow the repository's prefixes: `owner…`, `evidenceHub…`, `render…`.

## 4. Clean code

- **Clear names:** a name says what the thing is or does (`loadUnreadMessageCount()`, not `getData2()`).
- **One job per function.**
- **No duplication:** when the same code appears twice, extract it.
- **Comments say why, not what.**
- **No dead code:** no unused functions, no commented-out lines.
- **The smallest correct change:** do not reformat a file to change a line.

## 5. No over-engineering

- **Build what is needed now.** No speculative extension points.
- **No new library or framework** without the owner's approval.
- **No layers or patterns** (interfaces, factories, event buses, …) where a plain function or file does the job.
- **The simplest solution that works and is tested is the right one.**
- **No settings or switches** for something that has only one state.

## 6. Non-negotiables for both sides

- **Security:**
  - CSRF on every form;
  - escape every owner-entered value;
  - scope every query to the account owner (`AuthorizedPortfolioContext`);
  - no secrets in code or logs.
- **Without JavaScript:** everything works. JavaScript only enhances.
- **Coverage:** Arabic RTL and English LTR, phone and desktop (390px and 1280px, no horizontal scroll), light and dark.
- **No fake controls:** a control either works for real or is not shown. Never show a success message for something that did not happen.
- **Tests:**
  - every change is tested;
  - never weaken or delete an assertion to pass;
  - if a test pins markup that legitimately changed, update only that selector and explain why in the PR.
- **Workflow:**
  - one branch and worktree per task;
  - a Draft PR with its evidence;
  - never merge, deploy, run live migrations or touch the running stack. Only the owner orders a merge or a release.

## 7. The UI/UX Pro Max skill

The project-local skill `.agents/skills/ui-ux-pro-max` (see [ui-ux-pro-max.md](ui-ux-pro-max.md)) is a **quality checker** for the frontend.

- **Use it for:**
  - accessibility: contrast, keyboard, labels;
  - touch targets of at least 44px;
  - responsive layout without horizontal scroll;
  - forms: visible labels, errors next to the field;
  - navigation and performance;
  - its pre-delivery checklist.
- **Never use it to choose** colors, fonts, styles or a design system. The owner's handoff package already decides how the product looks.
- **When the skill suggests something that contradicts the package,** follow the package. List the suggestion in the PR for the owner to decide.

## 8. Evidence Hub is a special track

- **Comparison first:** Evidence Hub's design changes only after the owner approves a comparison document. It maps every current section and action to the new design and proposes for each: stays, moves, removed from the interface, or new.
- **Removal needs a written owner decision:** no section or action is removed without one. Removing something from the interface does not delete its data or rules.
- **Frozen rules and contracts stay unchanged:** the text evidence rules, contracts v1…vN, taxonomies and recommendation rules (see [AGENTS.md](../AGENTS.md)).
- **New capabilities** (for example supporting sources) go through a new contract version, with the owner's approval.
