# Security policy

## Supported versions

Only the latest release gets security fixes. Upgrade to it before reporting an
issue.

| Version | Supported |
| ------- | --------- |
| 0.4.x   | Yes       |
| < 0.4   | No        |

## Reporting a vulnerability

**Do not open a public issue for security problems.**

Report privately through GitHub's
[private vulnerability reporting](https://github.com/oddvalue/filament-draft-recovery/security/advisories/new),
which reaches the maintainers without publishing the details. If you cannot use that, email
<jim@oddvalue.co.uk> instead.

Include the package version, the storage driver in use (`local-storage`,
`database`, or `laravel-drafts`), what an attacker can achieve, and
reproduction steps if you have them.

The maintainers aim to respond within 7 days. If the report holds up, they
will release a fix and publish a GitHub security advisory crediting you, unless
you would rather stay anonymous.

## Scope notes

This package auto-saves in-progress form state so it can be recovered later, so
draft storage is security-relevant by design. Two behaviours are intended, not
vulnerabilities:

- **The `local-storage` driver keeps drafts in plaintext in the browser's
  localStorage.** Anyone with access to the machine, the browser profile, or any
  script running on the page can read them. Browser storage works that way and
  nothing client-side can fix it. Use a server-side driver for sensitive data,
  and see the README's "Security & sensitive data" section for the safeguards
  that do apply.
- **The `database` driver stores payloads unencrypted unless you enable
  `database.encrypt`.** That setting is off by default.

A report that one of those behaviours exists is not a vulnerability. A report
that a documented safeguard fails to hold is one. That covers excluded fields
persisted anyway, password inputs surviving into a draft, drafts leaking
between users, and the logout purge not firing.
