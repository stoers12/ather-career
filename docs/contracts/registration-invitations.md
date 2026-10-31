# Contract: registration invitations (L-01)

Invite-only registration for the closed beta. Backend: Claude. Frontend: Codex consumes this contract without changing it.

## Modes

`ATHERCAR_REGISTRATION_ENABLED`:

| Value | Registration |
|---|---|
| `1` | Open (unchanged behaviour) |
| `invite` | Invite-only: a valid code is required |
| anything else (default `0`) | Closed: `/owner_register.php` returns 404 |

PHP helpers: `auth0RegistrationIsEnabled()` and `auth0RegistrationRequiresInvitation()`.

## Starting registration: `POST /owner_register.php`

- **Open mode:** the fields are exactly `csrf_token`, as before.
- **Invite mode:** the fields are exactly `csrf_token` and `invitation_code`, in any order. Any other field set returns 403.
- **`invitation_code` format:** 12 Crockford base32 characters (`0-9 A-H J K M N P-T V-Z`), usually typed as `XXXX-XXXX-XXXX`. Case, hyphens and spaces are ignored. Maximum input is 64 bytes.

Outcomes in invite mode:

| Case | Response |
|---|---|
| Valid, unused, unrevoked, unexpired code | 302 to Auth0, as in open mode |
| Malformed, unknown, used, revoked or expired code (one outcome, no reason disclosed) | 303 to `/signup.php?invitation=invalid` |
| More than 10 attempts per client IP in 15 minutes | 429 with `Retry-After` |

## Consumption

- The code is checked when registration starts, and its id is remembered in the server-side session.
- It is **spent inside the same database transaction that creates the user**.
- If two people start with the same code, exactly one account is created. The other is rolled back and sees the existing registration-denied page, so the OIDC callback is unchanged.
- An identity that already has an account signs in without spending a code.

## Frontend tasks (Codex)

- In invite mode, `/signup.php` shows a required "Invitation code" field. It posts `invitation_code` together with the existing `csrf_token`, with `autocomplete="off"`, `autocapitalize="characters"` and `spellcheck="false"`.
- `?invitation=invalid` shows an announced error, «This invitation code isn't valid», in Arabic and English. The page does not echo the submitted code.
- Open and closed modes stay visually unchanged.

The page needs to know the mode, so `handlers/signup.php` may call `auth0RegistrationRequiresInvitation()`. That read-only call is the only backend touch allowed for the frontend task.

## Operating codes (owner)

Run inside the web container:

```sh
php scripts/ops/registration-invitations.php create --count 30 --days 30 --label "Beta wave 1"
php scripts/ops/registration-invitations.php list
php scripts/ops/registration-invitations.php revoke --id 12
```

- Codes are printed **once**. Only their SHA-256 digest is stored, so a lost code cannot be recovered; revoke it and create a new one.
- `list` shows each code's state (`available`, `used`, `expired` or `revoked`) and never the code itself.

## Storage

Migration `028_registration_invitations.sql` creates the table `registration_invitations`:
- the digest is unique;
- `expires_at` is checked against `created_at`;
- `used_by_user_id` references `users` and becomes `NULL` when that user is deleted (P-05), while the code stays spent.
