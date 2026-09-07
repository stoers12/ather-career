# Stage-2C ownership and integrity boundaries

## Owner authority

The authenticated Owner is the current Auth0/OIDC identity mapped to the
server-side internal User session. `requireAuthenticatedUser()` validates that
session against the active `users` row and its authorization version for every
Owner request. `requireOwnedPortfolioContext()` then derives the sole
Portfolio from `portfolios.owner_user_id`; no form, query, JSON, route, or
hidden identifier selects the Owner or Portfolio.

All private reads and mutations use that context. A child identifier is only a
candidate and is constrained by its Owner-controlled parent in the effective
query (`resource id AND portfolio_id = :authorized_portfolio_id`, or the
equivalent recipient Portfolio predicate for messages). A missing or foreign
candidate has the same sanitized not-found result. No response reports a
foreign Owner, Portfolio, or database identifier.

| Domain | Parent and enforced predicate | Public eligibility |
| --- | --- | --- |
| User / Owner identity | validated session -> current active `users` row | never public |
| Profile and skills | `personal_info.portfolio_id` / `skills.portfolio_id` = authorized Portfolio | profile data only through a published active Portfolio; contact fields additionally require visibility |
| Projects and experiences | `projects.portfolio_id` / `experiences.portfolio_id` = authorized Portfolio | only through a published active Portfolio |
| Publication and slug | `portfolios.id` plus `owner_user_id` = authorized context | `is_published = 1`, active owning User, normalized unique slug |
| Messages | `recipient_portfolio_id` = authorized Portfolio | contact creation resolves a published active Portfolio first; messages are never public |
| Private media | authorized Profile or Project lookup, then managed-key Portfolio check | never public |
| Public media / derived media | published slug -> published active Portfolio -> matching Profile or Project | same published eligibility as the requested Portfolio |

Presentation derivatives are deterministic managed files derived from a
Profile original or Project original. They are never a client-selected path or
standalone authority record.

## Operation inventory

The following is the active request-path inventory. `Owner context` always
means the server-side context described above, never an identifier submitted by
a form or URL.

| Domain and operation | Trusted authority and parent | Effective query predicate | Missing / foreign result | Transaction, media effect, and compensation |
| --- | --- | --- | --- | --- |
| OIDC User / onboarding read and initialization | validated OIDC session -> active User; new Portfolio is parented by that User | User lookup matches issuer, subject, active status, and authorization version; Portfolio lookup matches `owner_user_id = authenticated_user_id` | session fails before data is returned; no caller-selected User is accepted | `account_operations.php` already wraps dependent User/Portfolio initialization in one transaction; no media effect |
| Profile read, create, and update | Owner context -> sole Portfolio | Profile reads and writes use `personal_info.id = :resource_id AND portfolio_id = :authorized_portfolio_id`; creation writes the context Portfolio only | `null`/sanitized 404 or validation result; foreign profile is indistinguishable from missing | profile initialization, content update, and image-reference update use `runDatabaseTransaction()`; a newly staged image is removed on write failure |
| Profile image replacement / removal | Owner context -> owned Profile | profile reference update uses the same Profile predicate; private-media lookup first resolves that owned Profile | sanitized 404 when no owned profile/image exists | commit new/null reference first; only then retire the prior managed original and derivative. A failed replacement removes only request-created files |
| Skill list, create, update, delete | Owner context -> Portfolio | list/insert use `portfolio_id = :authorized_portfolio_id`; update/delete use `id = :resource_id AND portfolio_id = :authorized_portfolio_id` | missing and cross-Portfolio candidates return the same sanitized 404; duplicate name is validation 422 | each Owner mutation is bounded by `runDatabaseTransaction()`; no filesystem effect |
| Project list, create, update, delete | Owner context -> Portfolio | list/insert use context Portfolio; find/update/delete use `id = :resource_id AND portfolio_id = :authorized_portfolio_id` | private cross-Portfolio reads are absent; mutation returns sanitized 404 without reflecting the foreign ID | project write is transactional. Upload originals/derivatives are request-owned until the write commits; failed writes remove them. Previous media is removed only after no same-Portfolio Project still references its generated key |
| Experience list, create, update, delete | Owner context -> Portfolio | list/insert use context Portfolio; update/delete use `id = :resource_id AND portfolio_id = :authorized_portfolio_id` | missing and cross-Portfolio candidates both yield sanitized 404 | each Owner mutation is bounded by `runDatabaseTransaction()`; no filesystem effect |
| Slug reserve, publish, unpublish, publication state read | Owner context -> Portfolio | lifecycle state and mutation use `portfolios.id = :authorized_portfolio_id AND owner_user_id = :authenticated_user_id` | missing/foreign context is rejected before action dispatch; incomplete publication is validation, duplicate slug is sanitized conflict | every lifecycle sequence is one transaction; unique index is authoritative for duplicate-slug races; no filesystem effect |
| Owner messages / public contact persistence | Owner message list/read uses Owner context; public contact starts from published slug context | Owner message predicate is `recipient_portfolio_id = :authorized_portfolio_id`; public insert receives the Portfolio only after published/active resolution | private cross-Owner message reads are absent; missing/unpublished public targets are 404 and write no message | contact persistence is one insert after eligibility validation, so no dependent write or media side effect exists |
| Private profile and project media reads | Owner context -> owned Profile/Project | descriptor query joins/constrains the requested Profile or Project to `portfolio_id = :authorized_portfolio_id`, then validates generated key/collection/Portfolio | missing and foreign resources return the same sanitized 404/403 route behavior; file is never opened first | read-only; path resolver remains under private storage root; no cleanup |
| Public Portfolio, JSON, and media reads | requested normalized slug -> published Portfolio with active User | public context requires matching `public_slug`, `is_published = 1`, and active owner; child Profile/Project/media is then constrained to that Portfolio | unknown, inactive, unpublished, or mismatched child is 404; JSON uses its explicit public allowlist | read-only; only server-generated managed media keys are resolved. Presentation derivatives remain tied to their original type and Portfolio |

## Transaction boundaries

`runDatabaseTransaction()` is called at Owner mutation use-case boundaries:
profile initialization/update/image reference changes, skill CRUD, project
CRUD, experience CRUD, and public-slug/publication changes. It starts and
commits a transaction only when the caller has not already established one;
it never creates a nested transaction. Every thrown failure rolls back an
owned transaction before the sanitized route handler renders a response.

The underlying repository helpers remain query-only. Their updates and
deletes retain the Owner predicate in the mutation SQL, so a previous lookup
is not used as authorization for an unscoped write.

Single-statement operations without dependent writes retain their existing
small boundary: OIDC User creation and Owner Portfolio creation rely on their
database uniqueness constraints to resolve races, while public contact
persistence inserts only after the request resolves a published public
context. Duplicate slug and duplicate skill violations become conflict or
validation outcomes; PDO/SQL details are logged only through the existing
safe logging boundary and are not returned to clients.

## Database and media compensation

Uploads validate type, size, image content, and storage namespace before a
managed original is permanently placed. The original and presentation
derivative are staged with server-generated keys. Until the transactional
database reference commits, both are request-owned:

1. A failed database reference update removes the newly staged original and
   derivative.
2. A failed derivative/normalization result removes both request-created
   files, including a derivative that was placed before a later check failed.
3. A successful replacement commits the new database reference before the
   old file is considered for deletion.
4. Old Project media is removed only after an Owner-scoped reference query
   confirms no Project in that Portfolio still uses its managed key.

Profile image ownership is singleton-per-Portfolio, while Project reference
checks also protect repaired or legacy shared references. All path resolution
parses a generated managed key and verifies its Portfolio and collection
inside the configured private storage root; user-supplied filesystem paths are
never accepted.

## Verification contract

`scripts/run-phase2-stage2c-integrity-rehearsal.php` is an integration
rehearsal for a disposable database and storage namespace. It covers own and
foreign Profile, Skill, Project, Experience, Message, private-media, public
media, slug, and publication access; malformed collection rejection; duplicate
slugs; public-unpublished denial; rollback after controlled test-only faults;
replacement-media compensation; shared-reference-safe removal; and exact
test-record cleanup. It contains no HTTP failure endpoint or production fault
switch.
