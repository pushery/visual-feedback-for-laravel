# Changelog

All notable changes to `pushery/visual-feedback-for-laravel` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Every entry that changes what a consuming application has to do carries an **Upgrade** note. A release without one is a release you can take without reading.

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
- **Optional bridges to the rest of the fleet, each a switched-off feature when its package is absent.** `pushery/webhooks-for-laravel` takes over the fan-out, `pushery/matomo-analytics-for-laravel` tracks accepted reports as events, and `privacy.source = legal-consent` puts the sentence `pushery/legal-consent-for-laravel` published on the acknowledgement checkbox.
- **A Laravel Boost skill**, so an agent working in a consuming application knows how to adopt the package.

### Requirements

Needs PHP 8.4+, Laravel 13+ and Livewire 4.3+. The optional bridges have floors of their own: WireKit 2.21+, webhooks-for-laravel 2.0+, legal-consent-for-laravel 0.10+ and matomo-analytics-for-laravel 0.17+.

Three things are the host application's job, and the widget cannot supply them: a `<meta name="viewport" content="width=device-width, initial-scale=1">`, a `<main>` landmark, and one `<h1>`.

Two settings decide whether parts of the package work at all, and both live outside it. Attachments ride Livewire's global upload endpoint, so `temporary_file_upload.rules` in `config/livewire.php` governs the real byte cap — without it, it is Livewire's 12 MB rather than the 5 MB this package documents. And the package registers no schedule, because a package does not write into the host's scheduler: add `visual-feedback:prune` and `visual-feedback:sweep-orphans` to your own `routes/console.php`, or neither ever runs.

Everything above is covered in full at <https://docs.pushery.com/visual-feedback-for-laravel/>.

[Unreleased]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.1.0...HEAD

[0.1.0]: https://github.com/pushery/visual-feedback-for-laravel/releases/tag/v0.1.0
