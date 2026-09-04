---
name: visual-feedback-for-laravel
description: >
  Install, configure, and apply the Visual Feedback for Laravel package in a Laravel
  application.
license: MIT
metadata:
  author: pushery
---

# Visual Feedback for Laravel

Use this skill when a Laravel application installs or integrates the
`pushery/visual-feedback-for-laravel` package. Laravel Boost surfaces it inside
consuming applications, so keep it focused on adoption — never on package
internals.

## Primary Goal

Apply the package's public API in the smallest correct way for the consuming
application.

## Workflow

### 1. Install

```bash
composer require pushery/visual-feedback-for-laravel
```

The service provider is registered automatically through package discovery.

### 2. Configure

Publish everything at once, or only the configuration file:

```bash
php artisan vendor:publish --tag="visual-feedback"
php artisan vendor:publish --tag="visual-feedback-config"
```

Every option in `config/visual-feedback.php` is documented inline.

Re-run `php artisan vendor:publish --tag="visual-feedback-assets" --force` after each
package update. The bundles under `public/vendor/visual-feedback` are copies, and
a stale copy is the one failure this setup produces on its own.

There are **two** of them, and they fail differently. `visual-feedback.iife.js` is the
capture renderer (~270 KB) and only ships while `screenshot.strategy` is not `off`.
`visual-feedback-widget.iife.js` is ~4 KB, always ships, and registers the Alpine
components the templates bind to — so a page missing that one renders a widget whose
every control silently does nothing.

`php artisan about` says whether that was needed: under **Visual Feedback** it carries a
**Published bundle** line reading `up to date`, `OUT OF DATE` with the command to run, or
`not published`. With `APP_DEBUG=true` a stale copy also writes a warning to the log.

### 3. Apply the package

Three things go in the layout: the stylesheet, the widget, the script tag.

```blade
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('visual-feedback::style')
    @livewireStyles
</head>
<body>
    <main>{{ $slot }}</main>

    @livewire('visual-feedback.report-widget')
    @livewireScripts
    <x-visual-feedback::scripts />
</body>
```

That puts the widget on the page: a floating button appears and opens a modal. It does not
yet deliver anything.

**Give the mail channel a recipient.** It is on by default and ships without an address,
because none could be guessed. Until one is set the channel reports itself unavailable and is
skipped — and the reporter still sees a success screen, so the only trace is two lines in the
log (`an enabled channel reported itself unavailable and was skipped`, then `a report was
accepted but no channel was enabled and available to deliver it`).

```dotenv
VISUAL_FEEDBACK_MAIL_TO=support@example.com
```

Three defaults are worth knowing before changing anything:

- `screenshot.strategy` is `auto`. The browser's own screen capture runs first
  (pixel-exact, asks permission once per capture) and falls back to a DOM renderer
  when it is unavailable or declined. Set it to `dom` when the permission prompt is
  unwanted, `off` to drop screenshots entirely.
- Only the mail channel is on. `database` and `webhook` are opt-in under `channels`.
- `attachments.disk` must be a **private** disk. Screenshots contain whatever the
  reporter had on screen.

Four things the package deliberately does NOT do for the host. None of them stops the
widget from working, which is why they get missed.

**Run a queue worker.** Every channel queues its own job, and a fresh Laravel application
resolves `queue.default` to `database` unless `QUEUE_CONNECTION` says otherwise — so with no
worker consuming the queue the job waits in the `jobs` table and no report is ever delivered,
while the reporter sees the success screen either way. `QUEUE_CONNECTION=sync` delivers
inline instead, which is fine for a small application and poor for a public form: the
reporter then waits on the mail transport.

**Bound Livewire's upload endpoint.** Attachments and screenshots ride Livewire's global
upload endpoint. A file is written to the temporary disk *before* any of this package's code
runs, so the package's caps bound what is accepted, never what is written, and the abuse
floor (which starts at submit) is not in front of it. Livewire's untouched defaults allow
12 MB per file at 60 calls per minute per IP — on a page that is usually public.

```php
// config/livewire.php
'temporary_file_upload' => [
    'rules' => ['required', 'file', 'max:8192'],
    'middleware' => 'throttle:20,1',
],
```

Size `rules` to the largest upload in the whole application; the key is app-global. Use
8192 KB rather than 5120, because screenshots have their own 8 MB cap and a 5 MB rule would
reject a legitimate capture at the endpoint. `throttle:60,1` is Livewire's own default, so
writing that value hardens nothing.

**Schedule the housekeeping commands.** The package registers no schedule — a package does
not write into the host's scheduler. Until these entries exist, `retention.reports_days`
deletes nothing and orphaned attachments accumulate:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('visual-feedback:prune')->daily();
Schedule::command('visual-feedback:sweep-orphans')->daily();
```

Both are safe to add before a retention window is chosen: with `reports_days` unset, prune
is a clean no-op. The sweep is needed even then — the ordinary attachment cleanup runs off a
cache-backed reference count, and a `cache:clear` on deploy loses it; the age-based sweep is
the only thing that collects the residue, and it works without the optional reports table.

**Run `visual-feedback:forget` by hand.** It answers one person's erasure request and does
not belong on a schedule.

**If the application runs a content security policy**, it needs five allowances, and two of the
failures are silent rather than loud:

- `style-src` for `@include('visual-feedback::style')`, which renders an inline `<style>` — without
  a nonce, a hash or `'unsafe-inline'` the widget renders completely unstyled.
- `img-src blob:` for the preview. **This one is the quiet one:** without it the preview box is
  empty while the buttons under it still offer attach, discard and retake, so the reporter attaches
  a screenshot they were never shown — and the documentation promises they review it first.
- `img-src data:` for the DOM capture stage, which redraws inline `<svg>` through a data URL.
  Without it the shot comes back with holes where the icons were.
- `connect-src` and `font-src` for the origins of any inlined SVG or webfont inside the captured
  region.

**You do not need `unsafe-eval`.** That is worth saying because Alpine normally does: its
standard build evaluates every directive expression by constructing a function at runtime.
Alpine ships a CSP build that parses expressions against a small grammar instead, and every
expression this package renders is a method call on a component registered in the bundle —
which parses under both builds. So `livewire.csp_safe => true` needs nothing configured here.

Two consequences worth knowing:

- If your `script-src` lists paths rather than a directory, list **both** bundles. Missing the
  widget bundle is the silent failure above.
- If you publish the view tree and edit it, keep the rule: **put logic in a component and call
  a method from the template.** An arrow function, a template literal, an optional chain or a
  bare `document` in a directive is what the CSP build refuses — and it refuses by not
  evaluating, so the page renders and the control is dead.

A policy that forbids style **attributes** (`style-src-attr 'none'`, or any `style-src` without
`'unsafe-inline'`, which it falls back to) also needs one rule of its own in the WireKit tree —
that tree ships no stylesheet, and the honeypot's concealment then has nothing to fall back on. The
selector and the reasoning are in the
[integration contract](https://docs.pushery.com/visual-feedback-for-laravel/integration-contract).

### 4. Choose where the trigger lives

`ui.trigger` is `fab` by default (the package places the floating button). Use `none`
and place your own, anywhere on the page:

```blade
<x-visual-feedback::trigger class="your-button-class">Report a problem</x-visual-feedback::trigger>
```

The trigger ships **unstyled** in the plain view tree — a bare
`<button class="visual-feedback-trigger">`, and the bundled stylesheet has no rule for that
class. That is on purpose (it should wear the host's buttons, not this package's), so pass
the application's own button classes. With browser defaults alone it renders around 21px
tall; give it at least 44×44 CSS px and a 16px font. The WireKit tree needs none of this —
its trigger is a WireKit button and inherits the design system's sizes.

Any code can open the widget without the component:

```js
window.dispatchEvent(new Event('visual-feedback:open'))
```

### 5. Override the configuration per widget

Six props override the configuration for one widget, so two pages in the same application
can offer different forms. All six are `#[Locked]` — the browser cannot change or widen them
after mount.

| Prop | Type | Default | What it does |
|---|---|---|---|
| `mode` | `?string` | from `ui.trigger` | `modal` or `inline` (form in the page, no modal) |
| `categories` | `array` | from `categories` | the category list this widget offers |
| `context` | `array` | `[]` | context entries attached to this widget's reports |
| `fields` | `array` | from `fields` | per-field visibility, e.g. `['subject' => false]` |
| `recipient` | `?string` | from `mail.to` | where this widget's reports are mailed |
| `withScreenshot` | `?bool` | `null` | `null` follows the default (capture in modal mode only); `false` removes it, `true` adds it |

```blade
@livewire('visual-feedback.report-widget', [
    'mode' => 'inline',
    'categories' => ['bug', 'billing'],
    'fields' => ['subject' => false],
    'recipient' => 'billing@example.com',
    'withScreenshot' => false,
])
```

Three things to get right:

- The mount prop is **`categories`**, not `availableCategories` — the longer name is the
  resolved property on the component, not the argument.
- `withScreenshot` may narrow, never widen: with `screenshot.strategy` set to `off` the
  capture stays off whatever a widget asks for.
- **`recipient` is visible in the page source.** Livewire serializes public properties into
  the `wire:snapshot` attribute, and `#[Locked]` governs writes, not visibility. An address
  mounted here is readable by any visitor or crawler. That is fine for one you would print
  in a footer; for an internal alias, register a channel and pick the address server-side
  instead.

### 6. Redact sensitive regions

No CSS effect hides anything from a screenshot. `filter: blur()` and
`content-visibility: hidden` are not reproduced by the DOM renderer at all — the live page
looks masked and the capture does not. `filter: grayscale()` *is* reproduced, and gray text
is still perfectly readable. Which properties survive also changes between renderer
versions. Mark the region instead; the attribute blacks it out in both capture stages and
clears input values as it goes:

```blade
<div data-visual-feedback-redact>
    <x-account-balance />
</div>
```

### 7. Know the capture limits that touch design work

The DOM stage reconstructs the page rather than photographing it, so a few deviations are worth
knowing before you style anything. The one most likely to reach you is **stacking and clipping**:
an element layered behind another, or clipped by a parent's `overflow`, can come back drawn on top
of it and extending past the edge. Decorative background art overlapping a heading is the usual
way this shows up, and it looks like a rendering bug in the reporter's browser rather than in the
capture.

Two smaller ones: an underline from `text-decoration` is not always reproduced, and an image that
failed to load leaves no trace at all — on screen the browser draws its broken-image affordance
and the `alt` text, in the capture that area is simply empty. A report about a missing image
therefore shows a gap rather than the evidence.

Everything else on a normal page — gradients, `oklch` fills, `box-shadow` and Tailwind's `ring-*`,
sticky headers, tables, web fonts — is reproduced faithfully.

**Which properties survive changes with the renderer version, so treat any list as dated.** This
section used to say the opposite about `box-shadow`: it was painted across the whole element
instead of as a ring, so a `ring-1 ring-*/30` came back with its entire fill tinted, and this text
told you to reach for `outline` or `border` instead. That is no longer true and the workaround is
no longer needed. The current list lives in the
[integration contract](https://docs.pushery.com/visual-feedback-for-laravel/integration-contract),
which is the copy that gets corrected when a dependency moves.

### 8. Use a WireKit-styled widget (optional)

**Nothing to do — an application with WireKit 2.21+ installed already gets it.** The shipped
`ui.variant` is `auto`, which serves the WireKit tree when a new enough WireKit is present and
the framework-free one otherwise. Force either tree when the automatic choice is not what the
application wants:

```php
// config/visual-feedback.php  —  or VISUAL_FEEDBACK_UI_VARIANT
'ui' => ['variant' => 'wirekit'],   // auto|plain|wirekit
```

**Do not publish the tree to select it.** `vendor:publish --tag="visual-feedback-wirekit"` still
works and is the right move when the application genuinely wants to **edit** the templates — but
publishing to merely choose between them leaves the host maintaining a copy of the package's
views, which every update then silently leaves behind. That is what `ui.variant` was added to
replace. The stylesheet needs no attention either way: it carries the same switch and renders
nothing under the WireKit tree.

**Needs WireKit 2.21 or newer** — that tree builds its trigger from `<x-wirekit::fab.button>`
using the `placement` prop and the accessible-name path 2.21 introduced. Check the installed
version before publishing; on an older one the trigger lands in the wrong corner and announces
nothing. Two things differ from the plain tree by design: the trigger is an **icon** button
rather than a text one (its accessible name is the widget heading either way), and
`ui.position` is read **logically**, so `bottom-right` follows the writing direction and
mirrors in a right-to-left application.

### 9. Ask guests to acknowledge a privacy notice (optional)

Guests get an acknowledgment checkbox as soon as `privacy.url` points somewhere. That URL
is what decides it — the reporter has to be able to open the full text, or the tick means
nothing.

```php
// config/visual-feedback.php
'privacy' => [
    'source' => 'url',
    'url' => 'https://example.com/privacy',
],
```

If the application uses `pushery/legal-consent`, set `source` to `legal-consent` and the
checkbox carries the sentence that package actually published, in the reporter's locale,
instead of this one's built-in line:

```php
'privacy' => [
    'source' => 'legal-consent',
    'url' => 'https://example.com/privacy',   // still required — see above
    'document_key' => 'privacy',
],
```

Keep in mind when adopting that:

- It **never writes to the consent ledger** — that API needs a model subject and a guest has
  none. It does record which document was displayed, in reserved `privacy_notice_*` metadata
  keys (key, locale, version, acceptance fingerprint), written server-side and stripped from
  client input unconditionally. That is provenance with the report's lifetime, not a consent
  and not a durable proof: `visual-feedback:prune` and `:forget` remove it with everything
  else. If you need a real consent record, the reporter has to be authenticated and you record
  it in legal-consent yourself.
- A locale with nothing published is normal, not broken: this package ships seven locales,
  legal-consent publishes two by default. The built-in sentence is used and the log says so.
- It falls back to the built-in sentence, loudly, if the document is not a privacy notice,
  if its notice mode gates, or if the published sentence is empty. Watch the log after wiring
  it — a fallback means the configuration is not doing what it looks like it is doing.
- **Needs legal-consent 0.10 or newer.** The bridge reads the acceptance fingerprint and the
  tenant from the returned document, and both arrived in 0.7 — but the floor is 0.10, because that
  is where `ui_wording` became nullable and this bridge publishes documents that carry no
  acceptance sentence. On 0.9 the insert fails outright. Newer is fine: a page that binds nobody
  may carry no acceptance sentence at all, and the bridge falls back to the built-in wording. Composer cannot refuse
  for you, because the package is a suggest rather than a requirement. On an older version,
  switching `privacy.source` to `legal-consent` fails on a page a guest is looking at. Check
  the installed version first.
- Multi-tenant installs are served: each tenant gets its own published sentence. Before 0.7
  this source declined to read at all when tenancy was on, because nothing on the returned
  document said whose it was.

### 10. Add your own abuse gate (optional)

The built-in floor — honeypot, time trap, per-user and per-guest-IP rate limits — always runs, and
no `abuse.driver` value removes it. An additional gate layers ON TOP of it, never instead:

```php
use Pushery\VisualFeedback\Contracts\AbuseGate;
use Pushery\VisualFeedback\Facades\VisualFeedback;

VisualFeedback::extendAbuse('acme', fn (): AbuseGate => new AcmeGate(...));
```

Then set `abuse.driver` to `acme`. Registering alone activates nothing, and a driver name with no
gate registered logs a warning rather than silently protecting nothing — the floor still carries
the request either way.

For an **interactive** challenge (Turnstile, proof-of-work), point `abuse.challenge_view` at a
Blade view. It renders inside the form in both view trees, wrapped in `wire:ignore` — required,
because a challenge widget is third-party DOM with its own JavaScript and Livewire's morphing
would otherwise destroy it on the next update. Bind whatever it produces into `challenge`
(`wire:model="challenge.token"`); it reaches your gate on `ReportAttempt::$challenge`, verbatim
and untrusted.

A rejection is silent by default — a bot must learn nothing. Pass `visible: true` when a human
could have failed it:

```php
use Pushery\VisualFeedback\Abuse\AbuseDecision;
use Pushery\VisualFeedback\Events\RejectionReason;

return AbuseDecision::reject(RejectionReason::ChallengeFailed, visible: true);
```

`RejectionReason` lives under `Events`, not under `Abuse` — it is the enum the
`ReportRejected` event carries, so a gate and a listener name the same value.

## Examples

Attach application context to every report — the thing that turns "it's broken" into a
diagnosable ticket. Implement the context provider contract and register the class under
`context_providers`:

```php
namespace App\Feedback;

use Pushery\VisualFeedback\Contracts\ReportContextProvider;
use Pushery\VisualFeedback\Data\ReportContextEntry;

final class SubscriptionContext implements ReportContextProvider
{
    /** @return list<ReportContextEntry> */
    public function entries(): array
    {
        $team = auth()->user()?->currentTeam;

        if ($team === null) {
            return [];
        }

        return [
            new ReportContextEntry(key: 'team', label: 'Team', value: $team->name),
            new ReportContextEntry(key: 'plan', label: 'Plan', value: $team->plan),
        ];
    }
}
```

```php
// config/visual-feedback.php
'context_providers' => [
    App\Feedback\SubscriptionContext::class,
],
```

Deliver reports somewhere else with a class implementing
`Pushery\VisualFeedback\Contracts\ReportChannel` — `key()`, `isAvailable()` and
`dispatch(Report $report)`. Register the factory in a service provider's `boot()`:

```php
use Pushery\VisualFeedback\Facades\VisualFeedback;

VisualFeedback::extend('slack', fn () => new App\Feedback\SlackReportChannel());
```

If that channel STORES the report for someone to open later, implement
`Pushery\VisualFeedback\Contracts\RetainsReport` alongside it — a marker with no methods.
The package deletes a report's attachments once every channel that had it is transient
(mail, a webhook: they need the file only long enough to send it). A storing channel without
the marker counts as transient, so the files are deleted and nothing fails on the way: the
delivery succeeds, the row is written, and the screenshot is gone when somebody opens it.

React to a report without writing a channel by listening to the events — `ReportSubmitting`,
`ReportSubmitted`, `ReportRejected`, `ReportDelivered`, `ReportDeliveryFailed`,
`ScreenshotAttached`, all in the `Events` namespace:

```php
use Illuminate\Support\Facades\Event;
use Pushery\VisualFeedback\Events\ReportSubmitting;

Event::listen(function (ReportSubmitting $event): void {
    if (str_contains($event->report->message, 'http://')) {
        $event->reject('links are not accepted here');   // the submission stops here
    }
});
```

`ReportSubmitting` is the only one a listener may act on rather than observe, and two things
decide whether the veto works: only a SYNCHRONOUS listener can cancel (the pipeline reads the
decision on the next line, so a queued listener runs and is ignored), and the report is not
validated yet, so treat `$event->report->message` as untrusted input. The string you pass
reaches your logs and `ReportRejected::$detail`, never the reporter's screen.

Then enable it in the config. **The switch is the `enabled` key inside the channel's own
array, never a bare boolean** — `'slack' => true` reads as a missing `enabled` key, which
means off, silently:

```php
// config/visual-feedback.php
'channels' => [
    // …
    'slack' => [
        'enabled' => true,
    ],
],
```

`enabled` is the only key the package reads for a channel of yours; the `connection`, `queue`, `tries` and
`backoff` entries beside it are read by the built-in channels for their own jobs. Channels
are isolated from each other: one failing never stops the rest.

## Anti-Patterns

- Do not document package internals here; keep the skill focused on adoption
  in Laravel applications.
- Do not duplicate the full README; link the deeper reference material instead
  and keep this skill small enough to load and apply quickly. The reference is
  <https://docs.pushery.com/visual-feedback-for-laravel/>.
- Do not store attachments on a public disk, and do not point `attachments.disk` at the
  same disk the application serves user uploads from.
- Do not rely on any CSS effect — `filter`, `mask`, `content-visibility` — to hide sensitive
  content from a screenshot; use `data-visual-feedback-redact`.
- Do not publish the WireKit tree in order to **select** it. `ui.variant` chooses the tree, and
  publishing to choose leaves the application maintaining a copy of the package's templates that
  every update silently leaves behind. Publish it when you want to edit it, not otherwise.
- Do not remove `@include('visual-feedback::style')` from the layout on the assumption that the
  WireKit tree makes it dead. It carries the same switch and renders nothing while that tree is
  serving, so leaving it in costs nothing and keeps the plain tree styled if `ui.variant` ever
  moves back. One thing does depend on it: the stylesheet carries the honeypot's concealment
  rule, so a policy forbidding style attributes needs that rule written by hand — see the CSP
  allowances above.
- Do not write a channel that stores reports without `RetainsReport`. Nothing fails when you
  forget it; the attachments are simply gone by the time anyone opens the report.
- Do not assume a green submit means a delivered report. A missing recipient, an idle queue
  and a rejected webhook signature all end in the same success screen; the log is where the
  difference is.
