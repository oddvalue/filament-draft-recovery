# Security Policy

## Supported versions

Security fixes are applied to the latest released version. Please upgrade to the
current release before reporting an issue.

| Version | Supported |
| ------- | --------- |
| 0.4.x   | Yes       |
| < 0.4   | No        |

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report privately through GitHub's
[private vulnerability reporting](https://github.com/oddvalue/filament-draft-recovery/security/advisories/new),
which notifies the maintainers without disclosing the details publicly. If you
cannot use that, email <jim@oddvalue.co.uk> instead.

Please include the package version, the storage driver in use
(`local-storage`, `database`, or `laravel-drafts`), what an attacker can
achieve, and reproduction steps if you have them.

You can expect an initial response within 7 days. If the report is confirmed, a
fix will be released and a GitHub security advisory published crediting you,
unless you would rather stay anonymous.

## Scope notes

This package auto-saves in-progress form state so it can be recovered later.
That means draft storage is security-relevant by design, and two behaviours are
intended rather than vulnerabilities:

- **The `local-storage` driver keeps drafts in plaintext in the browser's
  localStorage.** Anyone with access to the machine, the browser profile, or any
  script running on the page can read them. This is inherent to browser storage
  and cannot be fixed client-side. Use a server-side driver for sensitive data,
  and see the README's "Security & sensitive data" section for the safeguards
  that do apply.
- **The `database` driver stores payloads unencrypted unless you enable
  `database.encrypt`.** That setting is off by default.

Reports that these behaviours exist are not vulnerabilities. Reports that a
documented safeguard fails to hold — excluded fields being persisted anyway,
password inputs surviving into a draft, drafts leaking between users, or the
logout purge not firing — very much are.
