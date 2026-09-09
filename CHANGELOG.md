# Changelog

All notable changes to `pushery/visual-feedback-for-laravel` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Every entry that changes what a consuming application has to do carries an **Upgrade** note. A release without one is a release you can take without reading.

## [0.9.2] - 2026-09-09

### Fixed

- `WebhooksPlatform::isInstalled()` checked only that the package's class exists, which says nothing about whether the facade resolves -- that additionally needs the provider to have registered `WebhookManager`. It gates the platform path inside a queue job, so the throw marked the receipt FAILED while the built-in signed sender sat right underneath as a fallback an honest `false` would have used. It now checks the bindings too.
- The CSP section of the integration contract listed two of the three shipped bundles. The missing one is the renderer, which the DOM stage loads at the moment of capture, so everything looked correct until a reporter had already pressed the button -- and on iOS there is no second path.
- The `challenge_view` example omitted its `abuse` level. A missing key falls back to the shipped default without an error, so the decoy success screen the page promises would not have appeared.
- Two pages said there is deliberately no admin UI while the report browser documents one. Both re-scoped rather than deleted: nothing is installed and no console ships, and the browser is the opt-in exception that stays unreachable until routed and gated.

## [0.9.1] - 2026-09-09

### Fixed

- **Error messages are in a red box now, in both view trees.** They rendered as ordinary body text: the plain tree tinted them and drew no box, the WireKit tree did neither — so "something went wrong, please try again" sat in the same color and weight as "up to 5 files, 5 MB each" a couple of lines above it. A reporter scanning the form read the refusal as more advice. Every one of the four alert regions per tree is painted, not just the one at the bottom.
- **The form has vertical rhythm between its field groups in the WireKit tree.** It had none at all, so each label sat flush against the field above it and read as that field's caption rather than the next one's. This is why the reported symptom was about grouping rather than tightness.
- **The capture block no longer sits flush against the message field in the plain tree**, and the privacy link no longer has the submit button overlapping it in the WireKit tree. The first had no rule because that tree's rhythm hangs on labels and the block opens with a button; the second because the link is an inline box, which takes no vertical margin, so an `inline-flex` button beside it stayed on the same line.
- **The "you captured a screenshot but have not attached it" refusal disappears when you discard the capture.** Discarding cleared the pending flag; the refusal that flag had caused was cleared by nothing, so pressing Send, reading the message and then pressing Discard left an instruction to resolve something already resolved. Only that refusal is cleared — an unrelated one on screen stays.
- **The floating trigger rests in the corner instead of against one edge.** Its two offsets came from different spacing tokens in the design system, 16px to the side and 12px to the bottom.
- **An analytics outage can no longer refuse a report.** The optional Matomo bridge asked whether the facade class exists, which is true from the moment Composer autoloads it, and not whether it can resolve — which additionally needs the package's service provider to have registered its interface. An application that installs the package and skips that provider therefore had a bridge that reported itself available and then threw, inside the submit path, taking the report with it. A lost event costs nothing; a lost report is the one thing this package exists to prevent.
- **Upgrade:** nothing to do. If you override `.visual-feedback-error` or any of the widget's spacing in your own stylesheet, check it against the new rules once — the class names are unchanged and the new box is painted from `.visual-feedback-alert--shown`.

## [0.9.0] - 2026-09-08

### Added

- **Every form field now answers one question with one word.** `fields.<field>.mode` is `off`, `optional` or `required` — one vocabulary, one place. Until now the same question was split in two and half of it was missing: `fields.*.enabled` decided whether the subject and phone fields appeared, `guests.require_*` decided whether name and email were mandatory, and nothing at all decided whether those two appeared. The views rendered them for every guest unconditionally, so a host who wanted the email box gone had no key to set.
- Each field has its own environment variable — `VISUAL_FEEDBACK_FIELD_SUBJECT_MODE`, `_NAME_MODE`, `_EMAIL_MODE`, `_PHONE_MODE` — and one page can differ from the rest without touching the environment: `<livewire:visual-feedback.report-widget :fields="['email' => 'required', 'subject' => 'off']" />`.
- **A field set to `off` is dropped, not merely hidden.** Livewire properties are writable from the browser, so a crafted request could otherwise set an address on a form that never rendered the box. The off state belongs to the submission, not to the markup. A field set to `required` is marked as such in the markup, in both view trees, so the form says what it wants before it refuses.
- `message` deliberately has no mode. A feedback form without a message is not a feedback form, and a switch nobody may turn is a lie in the configuration file.
- **Upgrade:** nothing to do. The four older variables — `VISUAL_FEEDBACK_FIELD_SUBJECT`, `VISUAL_FEEDBACK_FIELD_PHONE`, `VISUAL_FEEDBACK_GUEST_REQUIRE_NAME`, `VISUAL_FEEDBACK_GUEST_REQUIRE_EMAIL` — still work, and so does a `config/visual-feedback.php` you published before this release. Prefer `mode` in anything new; the configuration page maps each old name to its replacement.

### Changed

- **A report with no subject now says what it is about.** The subject field is optional by design, so a report arriving without one is the ordinary case — and the mail then fell back to the bare category label, which makes an inbox of twenty reports read as "Bug / Bug / Feature / Bug". Every line identical, none of them telling you which to open. The subject line is now the category plus the first words of the message, cut on characters rather than bytes and moved back to a word boundary where one is close.
- `VISUAL_FEEDBACK_MAIL_SUBJECT_EXCERPT` sets how much of the message goes in. It defaults to 60 characters, which is measured rather than picked: the longest category label this package ships is 16, the separator costs 3, so the whole line stays at or under 79 — inside what a mail client shows in a list view. Set it to `0` for the old behavior, the category alone.
- **`ext-intl` is no longer required.** It was in the manifest from the first commit and the shipped code never called a single intl function — not directly, not through a dependency, and not through Laravel's `Number` helper, which this package explicitly declines to use. Composer refuses to install on a PHP without an extension it is asked for, and `intl` is absent from plenty of ordinary images, so the requirement excluded hosts and bought nothing.
- **Upgrade:** nothing to do, and one thing you may now undo — if you installed `intl` only for this package, it is no longer needed on its account.

### Fixed

- The documentation page for configuration now carries a block you can paste into `.env` whole, with every variable commented at its own default and grouped by the job rather than alphabetically, plus recipes for the things people actually ask: send reports elsewhere, name the sender, insist on an email address, ask for nothing but the report, read rendered mail in the log while developing.
- The bundled Boost skill went from naming three of the environment variables to twenty. Boost reads that file inside your application, so it is where an assistant answers configuration questions about this package — and it covered a fraction of the surface.

## [0.8.0] - 2026-09-08

### Added

- **A bundle that did not load now says so in the console.** It is the one failure this package produces that is completely silent: under Alpine's CSP build an unregistered `x-data` is an empty scope rather than a ReferenceError, so the markup renders server-side, the panel is drawn, and every control on it does nothing. A consuming application spent four hours on it and removed the widget before it was understood.
- Each bundle checks the OTHER one's island, because the one that is still running is the one that can speak. The check is gated on Alpine having actually walked the island, which is what makes it free of false alarms rather than merely unlikely to raise one: both bundles register on `alpine:init`, which fires before the walk, so an island that carries the marker and no component was never going to get one.
- **What it does not cover, stated rather than implied:** if both bundles are dropped, no code of this package runs and nothing in the browser can report it. The server side covers that — `PublishedBundle::warnIfUnusable()` logs an error when `public/vendor/visual-feedback/` is empty, ungated by `app.debug` — but only for a locally served copy. The gap this closes is the one the server cannot see at all: a configured assets base URL where one file is reachable and the other is not, or an integrity digest that matches one and not the other.
- **Upgrade:** nothing to do. If you serve the bundles from your own origin or a CDN, a console error naming the file is now what you get instead of a widget that renders and ignores you.

### Changed

- **`pushery/legal-consent-for-laravel` reaches 0.25.** The constraint listed every minor up to 0.24, so 0.25 was installable by nobody using this package — which is what a per-minor list costs in a 0.x dependency: it locks a consumer out by omission rather than by decision. Nothing here needed changing for it; the bridge reads the acceptance fingerprint and the tenant off the returned document, and both have been there since 0.7.

### Fixed

- **The report mail names its sender again.** `mail.from.name` shipped with no default, so a host who never set the variable — the normal case, because nothing pointed at it — got a From header carrying the bare address. It reads like an unattended relay rather than like the product somebody just gave feedback on, and it was *worse* than the framework value it overrides: `config/mail.php` ships `MAIL_FROM_NAME` and nearly every application sets it. It falls back to `APP_NAME` now, the way Laravel's own mail config does.
- **Four WireKit buttons in the report browser were passing a prop that does not exist.** `variant="ghost"` where the prop is `surface`, and one `variant="danger"` where it is `intent`. WireKit's strict validation logs and renders anyway, so the buttons appeared with the default surface and every render wrote a warning into the consuming application's log — a consumer found them in *their* log, through a checker meant to measure their own integration.
- All four are corrected, not the three that were reported: the fourth needed a different prop from the other three, so replacing the reported string everywhere would have left one button wrong and still writing to your log.
- **Upgrade:** nothing to do. Set `VISUAL_FEEDBACK_MAIL_FROM_NAME` if you want a sender name other than your application's.
- **`loading="lazy"` is off the screenshot preview, in both view trees — for real this time.** The comment directly above the element said the attribute was gone, and it was still three lines below it, through two releases. A consumer holding a published copy of that view kept the whole copy for exactly that one attribute.
- It is the broken-image finding one page later rather than a small waste. A lazy image inside a `display:none` parent is never requested, so `naturalWidth` stays 0 and every browser checker reads it as broken. The `x-if` in 0.7.0 fixed the case *before* the first capture; it could not fix the case *after* attaching, because `x-if` switches on the preview URL while the container switches on the capture status — and attaching sets the status without clearing the URL.
- Only the preview lost the attribute. The report browser still loads its attachments lazily on purpose: those images sit in a scrollable list on a page you opened deliberately, which is the case the attribute was designed for.
- **Upgrade:** if you published the widget view to strip that attribute yourself, the published copy can go.
- **A widget in your layout no longer answers ordinary navigation with a 419.** The time-trap anchor was a `#[Locked]` public property, and a locked property throws during **hydration** — before any method of the component runs, so nothing in a consuming application can catch it. Livewire's own `wire:navigate` machinery sends the unchanged value back, so a widget that survives navigation tripped it on every transition: measured in production at 40 events in 30 days, 16 of them inside 20 seconds from a single browser walking the site.
- **The anchor is held on the server now, and that is a tightening rather than a relaxation.** The lock only ever stopped the *write*; the value still traveled to the browser in the snapshot, readable by anyone who looked. There is no property any more — nothing to read, nothing to write, nothing for hydration to reject — and the time trap works exactly as before: a form filled in faster than a person could is still refused, and an ordinary one still goes through.
- It lives in the session rather than the cache on purpose: on the `array` cache driver the stamp would be lost between the request that opens the form and the one that submits it, and a missing stamp is *refused* while the trap is armed — a failure that would fall on legitimate reporters and look exactly like the abuse it exists to stop.
- A side effect worth having: an **inline** widget's markup is byte-stable now too. The stamp is no longer in the snapshot, so nothing downstream that keys on the response body — a full-page cache, an ETag, a CDN — sees a page that changes every second. Only the modal was stable before.
- **Upgrade:** nothing to do, unless you read `$openedAt` off the component yourself — it is gone. `markOpened()` is unchanged and is still the way to simulate opening the form in a test. A browser tab left open across the deployment holds a snapshot that still names the property and will get one error on its next Livewire request; a reload clears it.
- **The two sentences that report success now look like success.** "Screenshot attached" and the thank-you after a submit rendered as ordinary body text — the thank-you in a bare `<p>`, the capture line under `.visual-feedback-capture-status`, a class that carried no rule in either tree, directly beneath the native-capture hint it was indistinguishable from. Both now carry a success tone and a check mark, in both view trees.
- The tone is measured rather than picked: 5.02:1 on the light surface and 8.42:1 on the dark one, so both clear the 4.5:1 AA floor for body text, and a test re-derives those numbers from the stylesheet instead of quoting them. The WireKit tree takes the kit's own `--color-wk-success-text`.
- **The color is deliberately not the only carrier** (WCAG 1.4.1): green alone says "success" to everyone except the readers who need a confirmation most. The check mark beside it is `aria-hidden`, because the sentence already says it and both of these sit in a live region.
- The two *progress* lines — "Capturing…" and "Uploading…" — stay muted on purpose and have their own arm. Tinting those too would make the color mean "something is happening", and then it reports success nowhere.
- **Upgrade:** nothing to do. A host who published the views and wants a different green overrides `--vf-success` (plain tree) or `--color-wk-success-text` (WireKit tree).
- **The WireKit view tree gets its layout back.** It emits the same `visual-feedback-*` class names as the plain tree, and the plain stylesheet — where those rules live — withholds itself for that tree entirely. So `.visual-feedback-preview-actions` lost `display: flex` along with its gap and the Attach / Discard / Retake row rendered with no space between the buttons at all, the screenshot button clung to the attachments field, Send clung to the file-limit hint, and "Send another" clung to the thank-you line. A consumer photographed four such places.
- `@include('visual-feedback::style')` now renders a small **layout-only** block for the WireKit tree instead of nothing. Every value is a WireKit token with the plain tree's own number as a fallback, and there is no `--vf-*` palette in it — colors are what had to stay out, and "no stylesheet at all" was the implementation of that rather than the rule.
- Three controls that were bare siblings of a paragraph or a link — the capture button, the submit button and "send another" — carry a class in both trees now. There was previously not even a selector a host could have hung their own rule on.
- **The honeypot's concealment rule reaches the WireKit tree, which closes a documented hole.** Both trees hide that field two ways, and a policy forbidding style **attributes** (`style-src-attr 'none'`, which any `style-src` without an attribute clause implies) drops the inline one. On the WireKit tree there was nothing behind it, and the integration contract told the host to write the rule themselves. A visible honeypot is filled in by real people, and a honeypot hit is answered with the success screen on purpose — so their report was thanked for and discarded.
- **Upgrade:** keep `@include('visual-feedback::style')` in your layout. On the WireKit tree it used to render nothing, so removing it looked free; it is not any more. If you wrote the honeypot rule by hand on that advice, it is now redundant rather than wrong.
- **A screenshot the reporter captured but did not attach is no longer thrown away on Send.** The capture lives only in their browser until Attach uploads it, so pressing Send from the preview filed the report without it — no hint, no error, and "Thanks — your feedback was sent." afterwards. Somebody takes a screenshot because words were not enough, and that was the part that disappeared. Send is now refused while a capture is waiting, the message names the two ways out, and focus moves to the Attach button.
- Deliberately not an auto-attach: Discard exists because a capture is sometimes meant not to be sent, and choosing for the reporter overrides exactly that intent.
- **Upgrade:** nothing to do. If you drive the capture component yourself rather than through the shipped widget, it gained an `onPending` seam beside `onStage` — the default implementation sets the component's `screenshotPending` property, and a custom integration that never calls it simply keeps the old behavior.
- **A report is no longer reported as delivered by a mailer that cannot deliver.** `MAIL_MAILER=log`, `array` and `null` all let `Mailer::send()` succeed while the message goes nowhere — that is their correct behavior — so a report walked the entire happy path: no exception, no retry, no failed job, a `delivered` receipt, a `ReportDelivered` event, its attachments released, and "Thanks — your feedback was sent." on screen. From the outside a swallowed delivery was indistinguishable from a real one at every single seam. It happened in production, to a host that had configured nothing: Laravel's own default is `env('MAIL_MAILER', 'log')`.
- The mail channel now reports itself unavailable for those, resolving `failover` and `roundrobin` into their members first — a chain that ends on `log` works only for as long as the real transport has a good day, and reports success from the moment it does not. An unknown or unconfigured mailer is deliberately **not** refused: Laravel throws for it, the job retries and the receipt settles `failed`, which is a trail rather than a silence.
- **A submission nothing was asked to carry no longer shows the reporter a success screen.** `SubmissionResult` can now tell "handed to at least one channel" from "accepted and dropped", and the widget follows it. The decoy success a honeypot hit renders is unchanged and is the opposite pairing — accepted `false`, success `true` — so the two cannot drift into each other.
- **The `log` transport is named as a privacy question, not just a debugging convenience.** It writes the whole message into your log — the reporter's free text, their address in `Reply-To`, the screenshot as base64 — and onward into every error tracker the log stack feeds. One measured report was 117 kB of plaintext.
- **Upgrade — two things to check, and the second one may turn a test red.**
  1. If `MAIL_MAILER` is unset or set to `log`, `array` or `null` in an environment that is meant to deliver, the mail channel stops there and says so in the log. Set a real transport, or set `VISUAL_FEEDBACK_MAIL_REQUIRE_DELIVERABLE_TRANSPORT=false` to keep sending through it on purpose. The check is never applied while your application runs its tests, where `array` is the correct answer.
  2. A test that submits a report and asserts the success state now needs a delivery channel that is enabled **and** available — a `mail.to`, or the database or webhook channel switched on. Without one the widget reports a failure, which is the point of the change. Twenty-two arms of this package's own suite were in exactly that state and were passing for a reason that had stopped being true.
- **The cache-busting token on the script tags names the bytes being served, not the installed package.** It was read from `Composer\InstalledVersions`, which answers about `vendor/`, and a consuming application reported `?id=v0.4.1` on a page where v0.5.0 was installed. However a host gets there, the shape of the mistake is the point: reconciling `vendor/` with `public/` is the entire job of the republish this token exists to force, so a token that describes one while the browser fetches the other cannot do it. A hash of the file cannot disagree with the file.
- It also closes a case the version never covered: a re-publish that changes the bundle without a version bump left the URL standing, so a browser kept the old copy.
- The package version is still the fallback where there is no local file to hash — a configured assets base URL, or an install that never published — because neither can be answered by hashing and moving the URL there would be noise. The decision moved out of the Blade template into `PublishedBundle`, where it can be tested.
- **Upgrade:** nothing to do. Every bundle URL changes once on the release, which is a single cache miss and exactly what the token is for.

## [0.7.0] - 2026-09-06

### Fixed

- **Every boolean setting now understands `off`, `no`, `on` and `yes`.** `env()` converts exactly four spellings — `true`, `false`, `empty`, `null` — and hands back everything else as a string, and a non-empty string is truthy. So `VISUAL_FEEDBACK_ENABLED=off` read as **on**, and the same held for the channel switches, the field toggles and the guest requirements: eleven keys in all. Nothing threw and nothing logged; from the outside it looked like the package ignored the setting.
- One of those eleven carried a `(bool)` cast, which was no safer than the ten without it — `(bool) 'off'` is `true`. A cast confirms the non-empty string rather than converting it, and it reads like a safeguard, which is the part that costs.
- An unreadable value now falls back to what the shipped config declares rather than to `false`, so a typo no longer switches a feature off. An empty value (`KEY=`) and a literal `KEY=null` still read as `false`, which is what Laravel does with any env value.
- **Upgrade:** check your `.env` for any of the eleven `VISUAL_FEEDBACK_*` booleans set to something other than `true` or `false`. A key you meant to switch off with `off` or `no` was on until now and will be off after this release — the correction may look like a behavior change on the day you upgrade.
- **The screenshot preview no longer sits in the document before there is a screenshot.** It was hidden with `x-show`, which sets `display:none` and leaves the element in the DOM, so every page carrying the widget held an `<img>` with an empty `src`. An empty `src` resolves against the page URL, so the browser fetched the HTML document as an image and discarded it — a request per page view, an entry in every accessibility tree, and a broken image for anything that checks. One consuming application's browser suite went red on 54 pages from this single element. The preview is rendered by `x-if` now, and exists exactly when it has a source.
- **Upgrade:** nothing to do. If you published the widget view and carried that markup into your own copy, the same two lines are worth taking across.
- **Subresource Integrity now covers the renderer bundle too, which is the largest of the three.** `VISUAL_FEEDBACK_UI_ASSETS_INTEGRITY=true` put a digest on the two `<script>` tags a page renders. The third file, `visual-feedback-renderer.iife.js`, is not loaded by a tag at all — the capture bundle appends it at capture time, from the same base URL — so it arrived from your CDN unverified while the two small ones were checked.
- Not a regression, and the distinction is worth having: integrity was introduced in 0.6.0, the same release that made the renderer its own file, so it shipped covering two files of three rather than losing coverage it once had. Two correct changes in one release whose interaction nobody saw.
- **The bundled Boost skill and the configuration page counted two shipped JavaScript files where there are three.** The skill's CSP advice mattered most: a `script-src` that lists paths rather than a directory needs all three names, and a consumer who listed two got a capture button that works right up to the moment somebody uses it. Both places now name the files. The sentence about the two `<script>` **tags** is unchanged and was never wrong — files are three, tags are two.
- **The privacy page now lists every field the widget collects.** All seventeen, with what each one is and where it comes from, because you need that list to write your own privacy notice and reading the config file to reconstruct it was work this documentation should have saved you. It also says plainly that the fields are jointly identifying, and which knob narrows them.
- **Upgrade:** nothing to do. If you serve the bundles from a foreign origin with integrity switched on, that origin now has to allow CORS for the renderer as well — it is fetched with `crossorigin="anonymous"`, which a digest requires. Serving from your own `public/` is unaffected either way.

### Security

- **The referrer is cut to its origin in the browser, before it is sent.** 0.6.0 took `referrer` out of the shipped `metadata.collect` allowlist, so the server drops it and it never reaches a report, a mail or the queue. It was still transmitted, though, and "discarded server-side" describes the database rather than the way there — Telescope records request payloads by default, and so do many APM agents. Under Laravel's default `Referrer-Policy` a same-origin navigation hands over the full path, which is why this one field can carry a working password-reset or magic-link token.
- Deleting it in the browser would have been the smaller change and the worse one: the design here is that the client sends and the server allowlist decides, so a host who adds `referrer` to their own `collect` list is meant to receive it. Truncating keeps the question a bug report actually asks — which *site* the person came from — and drops the part that can carry someone else's secret.
- **Upgrade:** nothing to do unless you opted `referrer` into `metadata.collect` yourself, in which case you now receive scheme and host instead of the full URL.

## [0.6.0] - 2026-09-05

### Added

- **Optional Subresource Integrity on the two script tags**, for the case where you serve the
  bundles from an origin this application does not own.

  `VISUAL_FEEDBACK_UI_ASSETS_INTEGRITY=true` adds a `sha384` digest and
  `crossorigin="anonymous"`. The digest is taken from the bundles inside the installed package,
  which is the whole point: it is the value a divergence is measured against, so taking it from
  the copy under suspicion would prove nothing. It does nothing while the bundles come from your
  own `public/` — those are same-origin already.

  **Off by default, and that is the careful answer rather than the lazy one.** Turning it on is a
  statement that the origin mirrors this release's bundles byte for byte. A CDN that re-minifies,
  or that still carries the previous version, fails the check — the browser drops the script, and
  a widget whose bundle never loaded renders perfectly and does nothing. Nobody should acquire
  that by upgrading.

  **Upgrade:** nothing to do.

- **`ReportRejected` now says WHICH of the three refusals fired.** They all report
  `RejectionReason::Honeypot`, and one of them is not a bot.

  A filled honeypot, a fill faster than a human manages, and a submission that carries no form-open
  time at all are three different situations reported under one name. The third is a broken
  integration rather than traffic: an adapter that never stamps the open time turns every
  submission into a decoy success, silently, while the only signal you receive looks like bot
  volume.

  The event's `detail` now carries `honeypot`, `too_fast` or `open_time_missing`. The enum is
  unchanged — it is small on purpose, and adding a case to it would be an API change.

  **Upgrade:** nothing to do. A listener that ignores `detail` behaves exactly as before. If you
  alert on rejection volume, `open_time_missing` is the value worth paging on.

### Changed

- **The DOM renderer is fetched when a screenshot is taken, not when the page loads.** The
  always-loaded capture bundle goes from **263 KB to 16 KB**.

  html2canvas-pro is ~246 KB of what that bundle weighed, and it is reached only when a reporter
  opens the widget, chooses a screenshot, **and** the browser's native Screen Capture API is
  unavailable or declined — the fallback path of a fallback path. Every visitor was parsing it on
  every page. A consuming application measured the widget roughly doubling their landing page's
  same-origin JavaScript.

  The ESM build has always code-split this dependency. The classic `<script>` build could not —
  esbuild does not split a classic script — so it is now a **third file**,
  `visual-feedback-renderer.iife.js`, appended by the capture loader at the moment it is needed.

  **Upgrade:** re-publish the assets (`php artisan vendor:publish --tag=visual-feedback-assets
  --force`), which you do after every upgrade anyway. If you serve the bundles yourself from
  `ui.assets`, upload the new file too — its URL is derived from the script that loads it, so it
  has to sit beside `visual-feedback.iife.js`. A `script-src 'self'` policy needs no change; a
  policy that enumerates exact script paths needs the new one added.

### Removed

- **`window.VisualFeedback.version` is gone.** It said `0.1.0` while the package shipped 0.5.5.

  The constant had been in the entry module from the very first build, nothing in the package read
  it, and no page documents it. It also cannot be made true: a Composer package carries no version
  of its own — Packagist derives one from the tag — and injecting a number at build time would make
  `dist/` differ between a development tree and a tagged one, which the reproducible-bundle check
  refuses by construction.

  **Upgrade:** if you read that property, take the version from the script tag's `?id=` instead. It
  is resolved at request time from the installed package, and it is the number that is true.

### Fixed

- **Four locales called the same thing by two different names.** A Spanish reporter sent an
  `informe` and found it listed as a `reporte`.

  The widget and the report browser are one surface to the person using them: somebody submits a
  thing and then looks for it in a list. `es`, `it`, `nl` and `pt` used one word in the widget and
  another in the browser — `informe`/`reporte`, `segnalazione`/`report`, `rapport`/`report`,
  `relatório`/`report`. Every one of those translations was correct on its own, which is why key
  parity could not see it: the two files have different keys, and nothing compared their words.

  Each browser file now uses the word its own widget file already used. `de` keeps `Report` in
  both, which is a decision rather than drift — a real loanword, and the locale rules exempt those
  by name.

  **Upgrade:** nothing to do, unless you have published the translations into your own
  application, in which case re-publish or apply the same wording.

- **The guest rate limit now counts an IPv6 visitor by their /64, not by the address they picked
  for that request.** On IPv6 the limit had no effect at all.

  A residential IPv6 assignment is at least a /64 — 2^64 addresses the same person may use — and
  changing the interface identifier between requests takes one `ip addr add`. No proxy, no botnet,
  no cooperation from anyone. Every request therefore landed in its own bucket, and five per hour
  became unlimited. Measured: `2001:db8:1:2::1`, `::2` and `::ffff:ffff:ffff:ffff` produced three
  unrelated cache keys while sharing one network.

  /64 is the boundary the internet hands out, which is why it is the one used. Folding further
  would put unrelated customers of one provider in a shared bucket. **IPv4 is unchanged** — a /24
  there is a neighborhood, not a household.

  A malformed or absent address keeps its previous behavior and shares the `unknown` bucket: an
  adapter that cannot say who is calling gets the strictest treatment available, not an exemption.

  **Upgrade:** nothing to do. If you count on per-address buckets for IPv6 visitors — a lab, a
  test harness that rotates addresses within one /64 — raise `guest_rate_limit`, because those
  requests now share a bucket.

### Security

- **`referrer` no longer ships in the collected metadata, and a referrer that is collected keeps
  only its origin.** It could carry a working password-reset link into a report.

  Under `Referrer-Policy: strict-origin-when-cross-origin` — Laravel's default, and what a careful
  application sets — a browser sends the **full URL including the path** on a **same-origin**
  navigation. The "strict-origin" half governs cross-origin requests only.

  A Laravel application routinely has routes whose path is itself the credential:
  `reset-password/{token}`, `email/verify/{id}/{hash}`, a magic link, a team invitation. The page
  somebody lands on after one of those carries the widget — for a password reset that is the
  ordinary path — so a report filed there wrote a working link into its metadata, out by mail,
  through a queue, and possibly into a shared inbox.

  Checking whether those routes render the widget is the wrong check and it passes: the leak is
  not the page reported **from**, it is the one before it.

  Two changes, because the second is what holds when somebody undoes the first. The key is out of
  the shipped `metadata.collect` list, and `MetadataSanitizer` now reduces a `referrer` to its
  scheme, host and port whatever the list says. `url` is untouched — that one is the report's
  subject, the reporter chose to file from there, and its path is the diagnosis.

  **Upgrade:** nothing to do, and check nothing. If you had added `referrer` to `metadata.collect`
  yourself it keeps working and now records the origin only. Reports already stored are not
  rewritten; if your application has token-bearing routes, that stored metadata is worth a look.

  Reported from a consuming application during a 0.5.5 rollout.

## [0.5.5] - 2026-09-05

### Changed

- **The documentation names the field length caps, and links the page it had been hiding.** A
  reader can now find both from where they start.

  The three caps — `subject` 150 characters, `message` 50 000, `phone` 32 — were documented only
  in the config file's own comments, which you read after publishing it. They are the bounds a
  reporter runs into, so they belong on the configuration page as well; the counting is in code
  points rather than bytes, which is what makes `message` at 50 000 admit roughly 200 KB of UTF-8.

  And the report browser had a documentation page that neither the README nor the portal's
  landing page linked to. It has shipped since 0.5.0 and was findable only by noticing it in the
  sidebar — so the question it answers, whether you can read your feedback back without building
  a console, went unanswered for anyone who did not go looking.

  No behavior changed in this release. It exists because the documentation site publishes from a
  release rather than from the development branch, so a correction to it is not visible until one
  happens.

## [0.5.4] - 2026-09-05

### Changed

- **`SECURITY.md` no longer advertises an automated dependency-update channel.** The page said
  dependencies are kept current automatically and named the tool that opens the update pull
  requests. That has never been true for this package: no such pull request has ever been opened
  against it. A security page is what you read before adopting a package, and a promise there is
  exactly the kind you would reasonably stop checking yourself — so it now describes only what is
  verifiable from the repository: advisories flagged by Dependabot alerts, updates reviewed and
  merged by hand, and the four checks that already ran on every gate as hard failures rather than
  reports. Nothing about the checks changed; only the claim above them did.

## [0.5.3] - 2026-09-05

### Fixed

- **A widget whose assets were never published failed completely silently.** If `public/vendor/visual-feedback/` is empty the bundles 404, no Alpine component is ever registered, and under Alpine's CSP build that is an **empty scope rather than an exception** — the panel renders server-side, and every control on it quietly does nothing. This package could already tell: its published-bundle detector has always been able to report `not published`, and the warning it fed only ever acted on `stale`, so the worst case was the one case it stayed silent for. It now says so, in the log, **including in production** — an out-of-date copy is cosmetic and stays behind `app.debug`, an inert widget is not. The message names the publish command and the path it looked for.

  **What this looks like when it bites, so you can recognize it in a report:** every status branch of the screenshot block renders at once, so a "retake" button appears three times; the character counter sits at `0` because the markup's literal never gets replaced; and the preview shows while the capture is still loading. One cause, four symptoms — all of them fixed by publishing the assets, none of them by touching the panel.

  **Upgrade:** run `php artisan vendor:publish --tag=visual-feedback-assets --force` after upgrading, as always. If you have ever wondered whether it worked, this release will tell you.

### Changed

- **`PublishedBundle::warnIfStale()` is now `warnIfUnusable()`.** The old name stopped describing what it does once it reports a missing copy as well as an out-of-date one. It is called from a shipped Blade view; if you call it yourself, rename the call.

## [0.5.2] - 2026-09-05

### Fixed

- **0.5.1 told you the wrong fix for half of a problem it had just described.** It said an action named after a JavaScript literal — `true`, `false`, `null`, `undefined` — could not be reached through index access and needed renaming. Measured against Alpine's own parser: `$wire['true'](…)` parses, and it calls your component method rather than the literal, because the string key is masked before Livewire's rewrite ever sees it. **Index access is the cure for both halves.** The two differ in *where* they fail — an operator name at parse time, a literal name at runtime — never in what fixes them.

  **Upgrade:** nothing to do if you took 0.5.1; its shipped code was correct. If you followed its documentation and renamed a method, that rename was unnecessary but harmless.

### Changed

- **The guard behind that advice did not implement it.** 0.5.1 described two classes of dead action and shipped one list of ten names, because the commit that split them was written, tested and then never pushed — the release merged its parent. The consequence was not cosmetic: the extractor filters Livewire's skip list before any check runs, so the four literal names in that list could never match, and an action called `true` or `null` was invisible to the guard whose job is finding dead controls. The split is in, and the check that pins it now covers both halves rather than the operators alone.

## [0.5.1] - 2026-09-05

### Fixed

- **The report browser's delete button did nothing under a strict Content-Security-Policy.** Both view trees shipped `wire:click="delete(…)"`. Livewire turns that into `$wire.delete(…)` before handing it to Alpine, and on a page whose policy withholds `unsafe-eval` Alpine parses directive expressions instead of compiling them — where `delete` is a JavaScript **keyword**, not the identifier the grammar expects. The expression was therefore never evaluated: the button rendered, looked entirely normal, and did nothing, with nothing thrown and nothing logged. Deleting a report is the one action whose failure you only notice by going back to check. Index access (`$wire['delete'](…)`) parses under both builds, so a single version serves every application.

  **This matters beyond the one button if you are writing your own console against the component**, and it splits in two. An action named after an **operator** — `delete in instanceof new typeof void` — is rewritten to `$wire.<name>` and never parses. An action named after a **literal** — `true false null undefined` — is left alone by Livewire, parses fine, and then calls the literal at runtime. Both give you a control that silently does nothing, and **index access cures both**: `$wire['delete'](…)`, `$wire['true'](…)`. (0.5.1 said the literal case needed a rename instead. It does not — see 0.5.2.)

  **Upgrade:** take 0.5.1 if you serve the browser under a policy without `unsafe-eval`. Nothing else changed, no configuration moves, and there is nothing to republish.

### Changed

- **0.4.2 said the shipped source carries no emoji in its comments, and that held only for the PHP files.** Four markers remained in the Blade templates that install into your vendor directory, three of them from before that release. They are gone now, so the claim is true for the whole shipped tree rather than most of it.

- **0.5.0's release note claimed the browser contained no Alpine expression. It did.** "No `x-` directive" is not the same as "no Alpine", because a `wire:` action expression is evaluated by the same parser — and that mistaken reading is what let the defect above ship. The sentence is corrected below and on the documentation page rather than quietly dropped, because it is the reasoning that failed, not just the wording.

## [0.5.0] - 2026-09-05

### Added

- **An optional report browser, for reading your feedback without building an admin.** The package's position stays "bring your own admin" — the table is public API and a real console is still yours to build. This is for the other case: filter by mode, category and period, open one report with its screenshot, delete one with its attachment files cleaned up. It renders through whichever view tree your application serves, and neither template carries an `x-` directive. **The rest of this sentence was wrong, and 0.5.1 fixes what it hid:** it read "so it works unchanged under a Content-Security-Policy that withholds `unsafe-eval`", and it did not — a `wire:` expression meets that policy too.

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

[Unreleased]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.9.2...HEAD

[0.9.2]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.9.1...v0.9.2

[0.9.1]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.9.0...v0.9.1

[0.9.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.8.0...v0.9.0

[0.8.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.7.0...v0.8.0

[0.7.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.6.0...v0.7.0

[0.6.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.5.5...v0.6.0

[0.5.5]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.5.4...v0.5.5

[0.5.4]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.5.3...v0.5.4

[0.5.3]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.5.2...v0.5.3

[0.5.2]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.5.1...v0.5.2

[0.5.1]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.5.0...v0.5.1

[0.5.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.4.2...v0.5.0

[0.4.2]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.4.1...v0.4.2

[0.4.1]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.4.0...v0.4.1

[0.4.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.3.0...v0.4.0

[0.3.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.2.0...v0.3.0

[0.2.0]: https://github.com/pushery/visual-feedback-for-laravel/compare/v0.1.0...v0.2.0

[0.1.0]: https://github.com/pushery/visual-feedback-for-laravel/releases/tag/v0.1.0
