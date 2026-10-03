# Stage-4B edge security, CSP, and abuse protection

## Enforced response contract

Application and Apache responses use the same enforced self-only Content
Security Policy: `default-src 'self'`; `base-uri 'none'`; `object-src 'none'`;
`frame-ancestors 'none'`; explicit self-only script, style, image, font,
connect, form-action, and media sources. No `unsafe-inline`, `unsafe-eval`,
wildcards, external resource origins, report endpoint, or report-only policy
is used. Auth0 remains a top-level redirect destination and is not a CSP
subresource source.

The public Portfolio has no inline scripts, handlers, or style attributes.
Portfolio JavaScript is a self-hosted deferred asset; metric layouts use
bounded CSS classes rather than dynamic style values. This preserves the
existing no-JavaScript presentation fallback.

Apache is the single owner of CSP, `X-Content-Type-Options: nosniff`,
`X-Frame-Options: DENY`, `Referrer-Policy`, and a restrictive
`Permissions-Policy`, using `always` so Apache-generated errors receive
protection. The local Owner Caddy bridge sets the same policy for
proxy-generated responses. PHP applies `Cache-Control: no-store` by default.
Static versioned assets retain the established bounded cache policy. Private
media, Owner, validation, authentication, callback, and error responses are
not shared-cacheable. The OIDC callback uses `Referrer-Policy: no-referrer` to
avoid passing authorization-code query data to a later navigation.

HSTS is intentionally not emitted: there is no real production HTTPS edge in
this repository. Stage-5 selects the production TLS boundary and verifies HSTS
there. COOP, COEP, and CORP were not added because this application has no
demonstrated isolation requirement and must continue serving private media.

## Request size and proxy trust

Apache limits request bodies to 16 MiB, matching the PHP upload boundary. This
limits oversized request exposure without changing valid upload behavior.

Rate limits use the direct peer address by default. Forwarded addresses are
ignored unless `TRUSTED_PROXY_CIDRS` contains a valid explicit comma-separated
IPv4/IPv6 CIDR allow-list. If the direct peer is trusted, only a bounded
`X-Forwarded-For` chain (at most 1,024 bytes and 16 hops) is parsed; malformed
chains fail closed to the non-identifying `unknown` bucket. The resolver walks
from the trusted edge inward and selects the first untrusted hop. Hostnames,
`Forwarded`, `X-Real-IP`, and inbound request IDs are never trusted. Proxy
configuration is validated by readiness when supplied. Stage-5 provides any
deployment-specific CIDRs.

Existing policies remain: OIDC start is 5 requests per 300 seconds keyed by
the safely resolved client address; public Contact is 3 requests per 900
seconds; Owner upload and publication actions are each 20 requests per 900
seconds keyed by durable authorized Owner/Portfolio context. File-backed
state uses exclusive locks, 0600 files, bounded best-effort expiry cleanup,
sanitized `429` responses, `Retry-After`, and `rate_limit_denial` events with
only safe scope/reason metadata.

## Boundaries

Stage-4B does not configure a real domain, DNS, TLS certificate, production
Auth0 URLs, CDN, WAF, external monitoring, deployment, backup, or restore.
Stage-4C covers Backup, Recovery, vulnerability/operational-security closure,
and monitoring readiness. Stage-5 owns actual production domain/DNS/TLS,
Auth0 production configuration, deployment, production acceptance, rollback,
and explicitly authorized merge/push/tag.
