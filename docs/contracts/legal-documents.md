# Contract: Privacy Policy, Terms of Use and registration consent (L-02)

Backend: Claude. Frontend: Codex, who consumes this contract without changing it, except for the one switch described below.

## Documents and versions

`includes/legal_documents.php`:
- **`LEGAL_DOCUMENT_VERSIONS`** = `['privacy' => '1.0', 'terms' => '1.0']`. Raise a version whenever the published text changes what users agree to. The PR template asks about this on every PR.
- **`legalPrivacyContactEmail()`** returns `PRIVACY_CONTACT_EMAIL` when it is a valid address, otherwise `null`. Pages must handle `null` by omitting the address, and must never hard-code one.

The owner's approved text is the source:
- `L-02-مسودة-سياسة-الخصوصية.md`
- `L-02-مسودة-شروط-الاستخدام.md`

Both are in the planning folder `جاهزية الإطلاق`. Each document is Arabic and English. The pages are **not published** while any ⏳ promise in them is still unfulfilled (L-03, L-06, L-08, L-09).

## Registration consent

`POST /owner_register.php` accepts an optional `accept_legal` field alongside the existing fields (`csrf_token`, plus `invitation_code` in invite mode):

| `accept_legal` | Result |
|---|---|
| absent | Allowed while `LEGAL_CONSENT_REQUIRED` is `false` |
| `1` | The current versions are remembered in the fresh registration session |
| any other value | 403 |

When `LEGAL_CONSENT_REQUIRED` is `true` and `accept_legal` is absent, the response is 303 to `/signup.php?legal=required`.

The remembered versions are written to `policy_acceptances` **inside the transaction that creates the user**:
- one row per document;
- consent ticked against an older version is not recorded as current;
- when consent is required and missing, the user is not created, and the OIDC callback shows the existing registration-denied page;
- `owner_oidc_callback.php` is unchanged.

`policy_acceptances` (migration 029) is append-only per version, and its rows are deleted with their user.

## Frontend tasks (Codex)

1. **Pages:**
   - Routes `/privacy` and `/terms` in `config/routes.php`, with handlers that render the approved text in the visitor's locale (Arabic RTL or English LTR).
   - Each page shows the version and effective date, and the contact from `legalPrivacyContactEmail()`.
   - Public, indexable and cacheable, like other public pages.
2. **Links:** add Privacy and Terms links to the public portfolio footer, the sign-in and sign-up pages, and the owner Settings → Help area.
3. **Sign-up consent:** add a required checkbox `accept_legal` (value `1`) with a label linking both documents, for example «أوافق على شروط الاستخدام وسياسة الخصوصية». Show an announced error for `?legal=required`.
4. **The switch:** in the same PR, set `LEGAL_CONSENT_REQUIRED` to `true`, and flip the expectation in `tests/Unit/LegalConsentTest.php` (`testWithoutConsentNothingIsRecordedWhileConsentIsOptional`) to assert that registration is denied without consent. This is the only backend change allowed.
