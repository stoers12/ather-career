# Stage-3B responsive and accessibility conventions

Stage-3B applies WCAG 2.2 AA as an engineering reference for applicable
responsive and accessibility checks. It does not claim formal conformance or
certification.

## Responsive and reflow rules

Active public and Owner surfaces are verified at 1440, 1100, 768, 390, 360,
and 320 CSS pixels, plus 844px landscape. Ordinary content must not create
horizontal document scrolling, clipped controls, or inaccessible actions. The
public Portfolio preserves its established three-, two-, and one-column Project
ranges, Hero balance, and Experience/Contact relationship.

At narrow public widths, JavaScript enhances navigation with the mobile dialog.
Without JavaScript, the ordinary section navigation remains visible and usable;
projects and experiences retain their complete server-rendered content.

## Semantics, focus, and feedback

Rendered HTML status pages use a viewport declaration, a meaningful title, and
a primary `main` landmark. Public and Owner pages retain language, primary
headings, current navigation, and skip-to-main behavior. The mobile navigation
is a named modal dialog: opening it moves focus to its close control, makes the
background inert, and Escape or Close restores focus to the trigger.

Public contact validation moves focus to its visible error summary when
JavaScript is available. Owner validation retains the Stage-3A summary behavior.
The public back-to-top link returns focus to the Portfolio heading after its
native anchor navigation; without JavaScript it remains an ordinary anchor.
Owner confirmation dialogs name and describe the requested action.

## Media, contrast, and motion

Informative profile and project images retain contextual alternative text;
decorative SVGs and logos are hidden with empty alternative text or
`aria-hidden`. Public derivatives remain the only public media URLs.

The checked palette combinations meet the applicable contrast reference:
Portfolio cyan on the dark background is 12.03:1, Portfolio muted text on its
surface is 6.97:1, Owner muted text on white is 4.76:1, Owner primary text on
white is 5.17:1, and Owner danger text on white is 4.83:1. Focus visibility is
provided by the established white/blue Owner ring and cyan Portfolio ring.

Public and shared styles respect `prefers-reduced-motion`; smooth scrolling and
non-essential transitions are reduced without suppressing state text.

## Verification boundaries

Chrome/CDP verification includes DOM relationship checks, keyboard workflows,
reduced motion, JavaScript-disabled navigation/forms, 200% zoom, and a 320px
equivalent reflow check. No automated accessibility engine is bundled or added
to the application; deterministic DOM checks and browser evidence supplement,
but do not replace, assistive-technology testing.

Stage-3B closes responsive and accessibility defects within existing surfaces.
It does not begin Stage-3C or add new product features.
