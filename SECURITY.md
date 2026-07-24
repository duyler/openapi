# Security Policy

## Supported Versions

Only the latest minor release line receives security fixes.

| Version | Supported |
|---------|-----------|
| 1.x     | ✅        |
| < 1.0   | ❌        |

## Reporting a Vulnerability

If you discover a security vulnerability in `duyler/openapi`, please **do not**
open a public GitHub issue. Instead, use GitHub's Security Advisory feature:

1. Go to https://github.com/duyler/openapi/security/advisories/new
2. Click "Report a vulnerability"
3. Provide a description, affected versions, and a proof of concept

We will acknowledge receipt within 48 hours and provide an initial assessment
within 5 business days.

## Scope

The following are considered security vulnerabilities:

- Bypass of validation guards (ReDoS, billion-laughs, XXE, path traversal)
- Information leakage via exception messages, stack traces, or logs
- Denial-of-service via attacker-controlled schemas or payloads
- Authentication/authorization bypass in security scheme validation

The following are **not** security vulnerabilities:

- Validation of an invalid spec that should have been caught at authoring time
- Performance issues with legitimately complex schemas
- Missing support for a JSON Schema keyword not in the supported subset

If you are unsure whether an issue qualifies, please report it privately — we will triage.
