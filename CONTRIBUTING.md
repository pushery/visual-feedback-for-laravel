# Contributing

Thanks for considering a contribution. **Issues are the way in** — please read the next
section before you spend time on a patch, because the way this package is published makes
a pull request the one form of help that cannot be accepted.

## How this package is published

The GitHub repository that carries this file, and the Packagist package built from it, are
a **generated mirror**. Development happens in a private repository; each release rebuilds
the public tree from an allowlist — `src`, `config`, `database`, `routes`, `resources`,
`lang`, `art`, `dist`, plus the manifest and these top-level documents — and replaces the
public branch with the result.

Two consequences, and neither of them is about the quality of the contribution:

- **A pull request against the public repository cannot be merged into the package.** The
  next release rebuilds the branch from the private tree, so a merge here would be
  overwritten and a patch that is only here would never reach Packagist. The maintainers
  would have to close it, and that wastes work that was offered in good faith.
- **The tests are not in the published tree.** `tests/`, `phpunit.xml.dist` and the build
  tooling are development files and are deliberately not part of the release, so a checkout
  of the public repository has nothing to run a suite against. That is why there is no
  "run the suite" step below.

## Reporting an issue

This is the channel that works, and it is a real one — the issue templates are shipped with
the mirror and the maintainers read them.

Use the GitHub issue templates (bug report / feature request). Include:

- the package version and the Laravel, Livewire and PHP versions,
- a **minimal reproduction** — the smallest Blade page, configuration and steps that show it,
- what you expected and what happened instead.

Never paste secrets, credentials or a real reporter's data into an issue.

**Have a fix already?** Describe it in the issue, or link a branch on your own fork for the
maintainers to read. A diff in a comment is welcome and useful; it just travels into the
private repository by hand rather than through a merge button.

Security problems do **not** belong in an issue. Follow [SECURITY.md](SECURITY.md) and
report them privately.

## Reproducing locally

To run the package against your own application, `composer require` it as usual. To poke at
the source, fork or clone the mirror — that gives you `src`, `config`, `lang` and the shipped
views, which is enough to narrow most reports down.

**PHP 8.4.1 or newer** if you install the development dependencies, even though the package
itself installs on 8.4.0. The test toolchain raises the floor: Pest 5 pulls
`symfony/process`, which requires `>=8.4.1`. On exactly 8.4.0 `composer install` therefore
fails with a message about `symfony/process` rather than about Pest. Upgrade the patch
version; nothing else is wrong.

## Quality bar

This is what a report is measured against, and what a fix has to keep intact.

The package holds itself to Laravel Pint, Larastan at `max`, Rector, and Pest with 100% line
and type coverage, plus mutation testing, a real-browser end-to-end suite, and cross-engine
tests against real PostgreSQL and MySQL 8.4 (the engines it runs on in production). The
public API stays stable within a major version, and every consumer-visible change carries a
`CHANGELOG.md` entry.

If you find behavior that contradicts any of that, the issue is worth filing on its own —
a documented promise the package does not keep is a defect like any other.
