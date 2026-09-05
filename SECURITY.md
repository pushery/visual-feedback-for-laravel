# Security Policy

## Supported versions

While this package is in its `0.x` line, security fixes are released against the latest minor version only.

| Version | Supported |
|---|---|
| `0.x` (latest) | :white_check_mark: |
| older | :x: |

## Reporting a vulnerability

**Please do not open a public issue for security vulnerabilities.**

Report them privately through GitHub's [private vulnerability reporting](https://github.com/pushery/visual-feedback-for-laravel/security/advisories/new) (the "Report a vulnerability" button on the repository's Security tab). Include:

- a description of the vulnerability and its impact,
- the steps to reproduce it,
- the affected version(s),
- and, if possible, a suggested fix.

You can expect an acknowledgment within **3 business days** and an assessment of the report, including a remediation timeline, within **10 business days**. We will keep you informed throughout and credit you in the release notes once a fix ships, unless you prefer to remain anonymous.

## Dependency updates

Known advisories are flagged by GitHub's Dependabot **alerts** — in the development repository, not here. This repository is a read-only mirror of the released tree, so its own Security tab shows nothing and releases arrive as tags. Updates themselves are reviewed and merged by hand — nothing is auto-merged, because the heavy suite runs on a self-hosted gate rather than on a runner that could vouch for an unattended merge.

That is the update path, and it is the weaker half of the two: with no `composer.lock` in the repository, the dependency graph those alerts read sees only the direct requirements, and anything resolved beneath them is invisible to it. The checks below are the ones that read the full resolution. They run on every gate rather than on a schedule, and they are hard failures rather than reports:

- `composer audit` **fails the build** on a known advisory.
- The package ships **no `composer.lock`** — a library resolves per consumer — so every gate run resolves fresh against the newest version each constraint allows. A breaking release surfaces here before it reaches you.
- A separate lane installs and runs the **declared minimums**, so the floor this package publishes is exercised rather than assumed.
- A weekly lane builds and tests against the **next PHP minor**.
