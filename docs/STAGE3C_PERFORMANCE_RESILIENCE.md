# Stage-3C frontend performance and resilience conventions

Stage-3C records comparative localhost evidence from disposable data. It does
not claim production or real-user performance.

## Local measurement evidence

Chrome 152.0.7977.83 and Edge 152.0.4191.66 were driven through temporary CDP
scripts against isolated HTTPS Compose projects and a CLI-created, disposable
Owner session. No HTTP test-auth route, Auth0 token, or real browser cookie was
used. Firefox 155.0.1 was installed but had no already-installed safe CDP or
WebDriver automation path, so it was not claimed as tested.

The reproducible baseline was detached commit
`557f600256b01084ecb2c12e84fed6e44f5d7a11`; the post-fix source was the
uncommitted Stage-3C worktree. Both environments used one synthetic published
Owner, profile derivative, seven projects, five skills, four experiences, one
message, public contact, and the same logical content. Each used separate
Compose project, database, media, rate-limit volume, browser profile, and HTTPS
loopback origin.

| Chrome route/profile | Baseline | Post-fix | Delta |
| --- | ---: | ---: | ---: |
| Portfolio desktop cold (requests; FCP/LCP; CLS) | 6; 520/520ms; 0 | 5; 216/216ms; 0 | -1; -304/-304ms; 0 |
| Portfolio desktop warm (requests; FCP/LCP; CLS) | 5; 128/128ms; 0 | 5; 108/108ms; 0 | 0; -20/-20ms; 0 |
| Portfolio 390×844, 150ms/1.6Mbps/750Kbps, 4× CPU cold | 5; 1104/1104ms; 0.103 | 5; 1132/1132ms; 0 | 0; +28/+28ms; -0.103 |
| Portfolio 320×568 warm (FCP/LCP; CLS) | 96/96ms; 0 | 100/100ms; 0 | +4/+4ms; 0 |
| Owner Dashboard/Profile/Projects/Preview desktop FCP/LCP range | 52–104ms | 48–100ms | neutral local variation |
| Semantic 404 FCP/LCP | 28/28ms | 28/28ms | 0 |

The cold Portfolio transfer mix was effectively unchanged: HTML about 4.6KB,
CSS about 8.2KB, JavaScript about 3.4KB, and the eager profile PNG about
136.5KB. The baseline made one additional initial project-media request; the
post-fix first view deferred it. No route produced an unexpected application
failure, console error, page error, duplicate application request, or
application long task above 50ms in the throttled mobile measurement. These
values vary by local host and are comparative only.

## Delivery and loading rules

Public and Owner pages remain PHP-rendered. Their local versioned CSS and
deferred JavaScript are the only application frontend resources; no third-party
browser dependency or build pipeline is introduced. The public Hero profile
image is eager, high-priority, and asynchronously decoded. Project images stay
native-lazy below the fold, while their existing visual containers reserve their
16:9 space.

The public Project navigator has a CSS initial window only after the Portfolio
JavaScript marks the document enhanced. It matches the three-, two-, and
one-card windows used by the navigator, avoiding a first-render collapse. With
JavaScript unavailable or failed, all server-rendered projects remain visible.

## Resilience and compatibility

Portfolio enhancements tolerate a missing `IntersectionObserver`; scrollspy
observation is skipped while navigation, skills, project navigation, experience
pagination, mobile navigation, feedback focus, and back-to-top initialization
continue. Optional application JavaScript remains deferred and server-rendered
navigation/forms remain primary fallbacks.

Fresh Chrome and Edge checks covered 1440×900, 390×844, and 320×568 public
views; authenticated Dashboard, Profile (including Skills), Projects,
Experiences, Messages and message detail, Publication, and Preview views; and a
semantic 404. No-JavaScript Chrome rendering kept all seven project cards and
the Owner Profile form available. CDP failure injection for the hero image,
project image, logo, and deferred Portfolio script retained usable layout and
server-rendered content; script failure removed the enhancement marker and
showed all seven cards. There is no separate font resource and no active browser
JSON consumer, so those failure cases are not applicable. The optional
`IntersectionObserver` fallback was also exercised without a page error.

Published Portfolios receive a server-built, escaped canonical URL. Private
Preview and Owner pages retain `noindex,nofollow` and do not publish a canonical
identity. Canonical metadata uses only the configured public origin and the
validated public slug.

## Measurement boundary

Chrome was measured through temporary CDP tooling on disposable containers at
desktop, mobile, and narrow widths, including cold/warm cache, a synthetic 3G
profile with 4x CPU throttling, script/image failure injection, and
JavaScript-disabled rendering. These localhost measurements are diagnostic
only. CDN behavior, real network variability, edge compression, TLS, and
real-user telemetry remain Stage-4/Stage-5 concerns.
