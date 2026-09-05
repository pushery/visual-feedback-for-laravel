# Changelog

All notable changes to `pushery/visual-feedback-for-laravel` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Every entry that changes what a consuming application has to do carries an **Upgrade** note. A release without one is a release you can take without reading.

## [0.5.0] - 2026-09-05

### Added

- **An optional report browser, for reading your feedback without building an admin.** The package's position stays "bring your own admin" — the table is public API and a real console is still yours to build. This is for the other case: filter by mode, category and period, open one report with its screenshot, delete one with its attachment files cleaned up. It renders through whichever view tree your application serves, and neither template contains an Alpine expression, so it works unchanged under a Content-Security-Policy that withholds `unsafe-eval`.

  **Installing the package does not expose it, and that takes two deliberate steps on your side.** The component is registered but has no route, so you reach it only through one you write. And it is gated by `viewVisualFeedbackReports`, which this package names and deliberately does not define — an undefined gate denies in Laravel, so a fresh install answers 403 rather than serving your users' feedback to anyone who guesses the component name. The check runs on every action rather than once at mount, so revoking access takes effect on the next click. [Report browser](https://docs.pushery.com/visual-feedback-for-laravel/report-browser) has the route and gate you need.

  One thing worth knowing before you write that gate: a closure whose `$user` parameter is not nullable is never called for a guest, so it denies while looking like it allows. That is Laravel's behavior rather than this package's, and it is usually what you want — but it surprises everybody once.

## [0.4.2] - 2026-09-05

### Changed

- **The shipped source carries no emoji in its comments.** Thirteen warning markers sat in the docblocks of ten files that install into your `vendor/` directory, and nothing in this package checked for them — so they had grown rather than been chosen. The warnings themselves are unchanged and still in capitals; only the pictograph in front of them is gone. Nothing you call, configure or render behaves differently.

## [0.4.1] - 2026-09-04

### Fixed

- **A widget mounted with its own `categories` offered options the server then refused.** The mount prop is documented as "the category list this widget offers" and the shipped example is a billing widget offering `billing` — a key deliberately absent from the configured list. It rendered as a normal, labeled option, and choosing it produced "The selected category is invalid" on every attempt, with nothing the reporter could do. Validation now accepts what the call site offered — including a list of numeric ids like `['101', '102']`, which PHP turns into ints on its way through an array key and the pipeline used to discard, allowing nothing at all. Trusting that list is safe because the prop is `#[Locked]`: a browser cannot widen it after mount, and a category nobody offered is still refused.
- **The documented install left an application with WireKit on an UNSTYLED widget.** `vendor:publish --tag=visual-feedback` copies the plain templates into `resources/views/vendor/visual-feedback`, and Laravel resolves that path before the ones a package registers — so the plain widget served while `ui.variant` still said `wirekit`. That much is correct and is what makes publishing the way to edit the templates. The stylesheet was not: it asked which tree was *configured*, got `wirekit`, and rendered nothing. The result on the happy path was an unpositioned trigger, an unstyled dialog, and no concealment rule for the honeypot, which lives in that stylesheet. `visual-feedback::style` now follows the tree that actually **resolves**.

  **Upgrade — and it is NOT "nothing to do" for the install this entry is about.** The stylesheet is a view, and `vendor:publish --tag=visual-feedback` copies it to `resources/views/vendor/visual-feedback/style.blade.php`. Laravel resolves your published copy before the package's, so an application that ran the documented install still has the old guard on disk and this fix cannot reach it. Re-publish it — `php artisan vendor:publish --tag=visual-feedback-views --force` — or delete that one file if you never edited it. Check first: a copy you HAVE edited will be overwritten by `--force`. If you never published the views, there is genuinely nothing to do.

  If you ran the umbrella publish and expected the WireKit tree, you have also been on the plain one all along; [View trees](https://docs.pushery.com/visual-feedback-for-laravel/view-trees) says how to get back to it.
- **A fractional `screenshot.scale` was truncated to zero and read back as one.** The capture page tells a host whose shots exceed `screenshot.max_bytes` to lower `screenshot.scale`. `env()` hands back a string, `(int) "0.5"` is `0`, and the client reads `0` as falsy and substitutes `1` — so the documented remedy produced a capture LARGER than configured and said nothing. `1.5` became `1` the same way. Integral values keep their integer type; fractional ones now survive.

## [0.4.0] - 2026-09-04

### Fixed

- **The widget works under a Content-Security-Policy without `unsafe-eval`.** It did not, and the failure was the silent kind. Alpine's CSP build parses directive expressions with a small grammar instead of evaluating them, and 25 of this package's expressions were outside it — including both handlers that open the widget, so under such a policy it could not be opened at all. A rejected expression is not degraded, it is never evaluated: the element gets an empty scope and every directive on it stops working, with nothing thrown and nothing logged. A consuming application collected uncaught errors on every page carrying the widget and switched it off rather than ship that.

  All of it now lives in registered Alpine components rather than in the templates, which is the form that parses under **both** builds — so there is no CSP variant of the views, and the templates are considerably shorter. A guard parses the rendered markup on every run and refuses any expression outside the narrow shape those components use; where Alpine's own CSP parser is installed it additionally re-proves that the shape really is inside the grammar, rather than leaving that claim asserted.

  **Upgrade:** the components are registered by a new bundle, `visual-feedback-widget.iife.js`, which `<x-visual-feedback::scripts />` emits on every page carrying the widget — about 4 KB, against the capture bundle's 268. Re-run `php artisan vendor:publish --tag=visual-feedback-assets --force` so the new file reaches `public/`; `php artisan about` names the state of both. If your policy lists the bundle path explicitly, add the second file to `script-src`. And if you have published and edited the view tree, put logic in a component and call a method from the template — an inline arrow function, a template literal or a bare `document` is what a stricter policy refuses.


## [0.3.0] - 2026-09-04

### Added

- **The published copy of the capture bundle says when it is out of date.** `php artisan about` carries its state under **Visual Feedback**, and with `APP_DEBUG=true` a stale copy also writes a warning to the log. Forgetting `vendor:publish --tag=visual-feedback-assets --force` after an upgrade is the one failure this setup can produce and was the only one with no signal at all — the old copy keeps working and is simply the previous release, so it surfaces weeks later as a report about behavior that was already fixed. The check runs on the server rather than in the browser, which is what makes it work on the very first request after an upgrade: anything shipped in the bundle would be executed by the stale copy itself.

## [0.2.0] - 2026-09-04

### Added

- **Every channel can name its own queue connection**, not just its queue — `channels.mail.connection`, `channels.database.connection`, `channels.webhook.connection`, each with an environment variable. That is the difference between report delivery having its own lane and having its own worker: a host that runs a separate worker for slow or third-party work can now route these jobs to it. Left unset, the job stays on the application default, which is what almost every installation wants.
- **The report mail says who reported it.** A "Reported by" block carries the reporter's name, whether they were a guest or a signed-in member, their email and phone when given, the submission time and the widget mode. This matters most for the shipped default: with only the mail channel enabled the report is persisted nowhere, so anything the mail omitted was gone the moment it was sent. The envelope was never a substitute — `Reply-To` carries the email only while `mail.reply_to_reporter` is on and only when a guest supplied one, and `guests.require_email` ships off; the name had no carrier at all.

### Changed

- **The WireKit view tree is chosen in configuration, not by publishing it.** `ui.variant` takes `auto`, `plain` or `wirekit`, and ships as `auto` — an application with WireKit 2.21 or newer gets the token-styled widget without configuring anything. Until now the only way to use that tree was `vendor:publish`, which meant keeping a COPY of these templates in your application, and a copy is what a package update silently leaves behind. Publishing still works and still wins; its job is now to EDIT the templates rather than to select them. `visual-feedback::style` also renders nothing while the WireKit tree is served, so an `@include` written once no longer ships CSS for a tree that is no longer rendering.

  **Upgrade:** nothing to do if you already publish the WireKit tree — a published view still takes precedence. If you have WireKit installed and want the framework-free tree, set `VISUAL_FEEDBACK_UI_VARIANT=plain`, because `auto` will now pick WireKit for you.

- **A modal widget stamps its open time when it opens, not when it mounts** — and the abuse floor now refuses a submission that carries no open time at all, while the time trap is armed. The two move together on purpose: the old stamp put a second-resolution timestamp into the rendered markup of every page carrying the widget, so the same page was byte-different from one second to the next and no full-page cache, ETag or CDN revalidation downstream could ever hit. Deferring it without closing the trap would have turned "no open time" into a way past the trap.

  **Upgrade:** if you drive the widget from your own tests, a modal now needs `->call('markOpened')` before a submission, exactly as a reporter opening the panel would. Without it the submission is refused as having no open time. An inline widget is unaffected — it is open the moment it renders. Setting `abuse.min_fill_seconds` to `0` disarms the trap and, with it, this refusal.

### Fixed

- **Three capture boundaries the documentation warned about do not exist.** Measured against the bundled renderer in the same browser the suite drives: deep stacking and clipping reproduce exactly — a negatively layered element, an opacity or transform stacking context, and a positioned descendant clipped by an ancestor's `overflow` all came back matching the live page pixel for pixel — and `text-decoration` reproduces to the declared thickness. Both are gone from the list. The `<canvas>`/WebGL/`<video>` entry was too broad in the same direction: a 2D canvas, a video frame and a WebGL canvas created with `preserveDrawingBuffer: true` all reproduce, and only the default `preserveDrawingBuffer: false` case is a real limit. A caution about something that works costs a reader the same as a missing one.
- **The screen-share hint no longer appears where the page forbids screen capture.** `Permissions-Policy: display-capture=()` leaves the API in place and makes the call reject, so the widget promised a permission prompt that never came. It asks the policy now, and the auto cascade skips an attempt it knows will fail. Where a browser exposes no way to ask, the answer is "allowed" — a missing introspection API must not take the native stage away from everyone who has it.
- **The native capture hides the same surfaces the DOM capture does.** It carried its own list of two selectors and knew nothing about the WireKit panel or about teleported overlays, so a reporter on the WireKit tree photographed the feedback form along with the page — the defect the DOM stage had already been fixed for, one stage over. There is one list now, read by both.
- **The screenshot preview carries `loading="lazy"` and `decoding="async"` in both view trees.** It holds a data URL of a full-page capture and is by far the heaviest element the widget renders, so a host auditing their own pages had a finding the package could simply not produce. Lazy rather than eager on purpose: the preview lives inside a panel the reporter has already opened, so it is never the page's LCP element.
- **US spelling throughout.** One British `acknowledgement` had reached the shipped views, the config comments and the published documentation; the spelling ratchet now names that form, so it cannot come back.

### Security

- **Reporter-typed values can no longer inject Markdown into the report mail.** The name, email and phone are rendered through a new escape that neutralizes inline Markdown openers, not just table pipes. Before this, a phone number of `[click me](http://evil.example)` reached the maintainer's inbox as a working link inside a mail that appears to come from their own tooling — measured against the converter Laravel builds for a Markdown mailable, with images arriving the same way. The escape set is deliberately narrow: the plain-text alternative is entity-decoded rather than parsed, so escaping ordinary punctuation would put visible backslashes through names like `Dr. O'Brien-Smith`.


## [0.1.0] - 2026-09-03

First public release.

### Added

- **An in-page feedback widget as a Livewire component, in modal or inline mode.** It ships with a floating button, a standalone trigger component, and a plain window event for hosts that place their own control.
- **Screenshot capture in two stages.** The browser's own screen capture is pixel-exact and asks permission once; where it is unavailable the widget falls back silently to a DOM renderer that works everywhere, including iOS. Each report records which stage produced its image, so an exact picture is distinguishable from a reconstruction.
- **The reporter sees the screenshot before sending, and can discard or retake it.** Nothing is uploaded until they attach it, and a capture the upload perimeter refuses is removed from the widget with that perimeter's own message rather than failing the whole submission.
- **Region redaction through `data-visual-feedback-redact`, effective in both capture stages.** The region is blacked out and input values are cleared before anything is captured.
- **Two view trees.** A framework-free plain tree that needs no build step, and a WireKit tree published over it, which inherits the application's design tokens and builds its trigger from WireKit's own components.
- **Delivery channels: mail, database and signed webhook, each isolated and individually queued.** A channel that fails is logged and settled on its own; the others still deliver. `VisualFeedback::extend()` adds your own.
- **Webhook deliveries are signed with HMAC-SHA256 over `{timestamp}.{rawBody}`**, the timestamp inside the MAC so a captured request cannot be replayed with a rewritten header. The secret is required: without one the channel reports itself unavailable rather than sending unauthenticated.
- **Abuse protection that needs no external service.** A honeypot, a server-anchored time trap and per-user and per-guest-IP rate limits, all three always on — an additional gate layers on top rather than replacing them. `abuse.on_error` decides whether a cache outage costs an hour of counting or refuses the form, and an interactive challenge can be wired through the same seam.
- **An upload perimeter on attachments rather than a check at submit.** Server-side MIME sniffing, byte and count caps, filename sanitization and a decompression-bomb guard. A stored file's extension is derived from its content, so a genuine PNG uploaded as `report.html` is attached as `report.png`.
- **`visual-feedback.enabled` is a kill switch on both halves.** While it is off the components render nothing and the submit path refuses every request before it reaches a rate limiter, a cache or a disk — and says so, in every locale, instead of showing a success screen for a report nobody received.
- **Retention commands: `visual-feedback:prune`, `visual-feedback:forget` and `visual-feedback:sweep-orphans`.** Reports past the retention window go with their attachments, an erasure request is answered by email, and the orphan sweep collects files whose reference count was lost.
- **Report context from the host through a provider contract**, plus per-widget mount overrides for categories, fields, recipient and capture.
- **Seven bundled locales — de, en, es, fr, it, nl, pt — every string translated in an informal register.** Only a category you add yourself needs a label of your own.
- **Built to WCAG 2.1 AA.** Both view trees hold the 44px touch target and the 16px font size below which iOS Safari zooms the page, honor `prefers-reduced-motion`, move focus to the control that was rejected, and announce the character counter from a separate live region once typing has paused.
- **Optional bridges to the rest of the fleet, each a switched-off feature when its package is absent.** `pushery/webhooks-for-laravel` takes over the fan-out, `pushery/matomo-analytics-for-laravel` tracks accepted reports as events, and `privacy.source = legal-consent` puts the sentence `pushery/legal-consent-for-laravel` published on the acknowledgment checkbox.
- **A Laravel Boost skill**, so an agent working in a consuming application knows how to adopt the package.

### Requirements

Needs PHP 8.4+, **Laravel 12 or 13** and Livewire 4.3+. The optional bridges have floors of their own: WireKit 2.21+, webhooks-for-laravel 2.0+, legal-consent-for-laravel 0.10+ and matomo-analytics-for-laravel 0.17+.

Three things are the host application's job, and the widget cannot supply them: a `<meta name="viewport" content="width=device-width, initial-scale=1">`, a `<main>` landmark, and one `<h1>`.

Two settings decide whether parts of the package work at all, and both live outside it. Attachments ride Livewire's global upload endpoint, so `temporary_file_upload.rules` in `config/livewire.php` governs the real byte cap — without it, it is Livewire's 12 MB rather than the 5 MB this package documents. And the package registers no schedule, because a package does not write into the host's scheduler: add `visual-feedback:prune` and `visual-feedback:sweep-orphans` to your own `routes/console.php`, or neither ever runs.

Everything above is covered in full at <https://docs.pushery.com/visual-feedback-for-laravel/>.

[Unreleased]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.5.0...HEAD

[0.5.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.4.2...v0.5.0

[0.4.2]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.4.1...v0.4.2

[0.4.1]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.4.0...v0.4.1

[0.4.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.3.0...v0.4.0

[0.3.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.2.0...v0.3.0

[0.2.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.1.0...v0.2.0

[0.1.0]: https://github.com/pushery/visual-feedback-for-laravel/releases/tag/v0.1.0
