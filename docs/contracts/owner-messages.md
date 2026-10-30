# Contract: message read state and the unread badge

Backend: Claude (B-03). Frontend: Codex (slice FE-06 for the Messages page, and the sidebar badge). Rule 2 of [ENGINEERING_GUIDELINES.md](../ENGINEERING_GUIDELINES.md): neither side changes this contract alone.

## Owner decisions

- **A message becomes read when the Owner opens it**, like email. Visiting the list alone changes nothing.
- **Messages received before this feature are read.** The badge counts only messages that arrive afterwards.
- **The Owner can mark an opened message unread again.**

## Route: `/owner_messages.php`

| Request | Effect |
|---|---|
| `GET` | The list, as today. |
| `GET ?message={id}` of the Owner's message | Shows it and **marks it read**. Marking again keeps the first read time. A foreign or unknown id gets `404` and writes nothing. |
| `HEAD` | Never writes. |
| `POST` | Only the "mark unread" form below. |

### "Mark unread" form

| Field | Value |
|---|---|
| `method`, `action` | `POST`, `owner_messages.php` |
| `action` | `mark_unread` (the only accepted value) |
| `id` | The message id |
| `csrf_token` | `getCsrfToken()` |

**Responses:**
- Success: `303` back to `owner_messages.php`.
- Invalid CSRF: `403`.
- Another action, or a missing or invalid id: `400`.
- A message that is not the Owner's: `404`.

## Template data on the Messages page

Each row of `$messages`, and `$selectedMessage`, now also has `read_at`. It is `null` when the message is unread, and a timestamp string otherwise. Owner-visible text (`name`, `email`, `message`) is untrusted and must be escaped, as before.

The current markup is a minimal baseline: a "New" pill on unread rows, `data-message-state="unread|read"` on each card, and the "mark unread" button in the opened message. FE-06 replaces it with the reference design.

## Sidebar badge: `ownerWorkspaceUnreadMessageCount(): ?int`

- Call it **only while rendering the navigation**. It reuses the connection and context the handler already authorized, so it costs one `COUNT` query and no second authorization.
- **What it returns:**

  | Return | Show |
  |---|---|
  | `null` | **No badge.** No authorized Owner in this request, or the count could not be read. Never show an error, and never show `0` instead. |
  | `0` | No badge. |
  | `1` or more | The number, with an accessible label that says how many messages are unread (the reference uses ", one unread message"). |

## Not part of this contract

Notifications, email alerts and replying inside the site (phase C: P-01–P-03).
