# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.x     | Yes                |
| < 1.0   | No                 |

## Reporting a Vulnerability

If you discover a security vulnerability within Laravel AI Bridge, please report it responsibly.

**Do not open a public GitHub issue.**

Instead, send an email to **eduardo@edfavieljr.dev** with the following information:

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

You will receive a response within 48 hours acknowledging your report. We will work with you to understand and address the issue before any public disclosure.

## What Counts as a Security Vulnerability

This package handles third-party API credentials. The following are considered security issues:

- **API key exposure** — keys leaked through logs, stack traces, URLs, or database storage
- **Sanitization bypass** — sensitive data stored in `request_data` or `response_data` fields without redaction
- **Logging of credentials** — API keys or tokens appearing in log files
- **Injection attacks** — prompt injection that bypasses expected behavior in a way that exposes system internals

## What Is Not a Security Vulnerability

- Feature requests
- Bug reports that don't involve credential exposure or data leakage
- Issues with third-party provider APIs themselves

## Disclosure Policy

Once a vulnerability is confirmed and fixed, we will:

1. Release a patch version as soon as possible
2. Credit the reporter (unless they prefer to remain anonymous)
3. Add an entry to the CHANGELOG.md describing the fix without exposing exploit details

Thank you for helping keep Laravel AI Bridge secure.
