<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Visual Feedback for Laravel
    |--------------------------------------------------------------------------
    |
    | The single, complete configuration surface for the package. Publish it with
    | `php artisan vendor:publish --tag=visual-feedback-config`. Every option is
    | documented inline. Security-sensitive reads (abuse limits, attachment caps)
    | degrade CLOSED when a key is missing — the package's Settings accessor never
    | reads a laxer value than the documented default.
    |
    */

    // Master switch — a kill switch that needs no code change.
    //
    // False means, precisely: the widget renders one empty hidden element (a Livewire
    // component must have a root, so that is as close to nothing as markup gets), the
    // <x-visual-feedback::fab>, <x-visual-feedback::trigger> and <x-visual-feedback::scripts>
    // components render nothing at all — so the capture bundle is not requested either — and
    // the submit path refuses every request before it touches a rate limiter, a cache
    // or a disk. The refusal is VISIBLE — a page that was already open when you flipped the
    // switch tells its reporter the form is off, rather than showing a success screen for a
    // report nobody received. A `ReportRejected` event carries `RejectionReason::Disabled`, so
    // an operator can tell "switched off" from "under attack" in the same listener.
    'enabled' => env('VISUAL_FEEDBACK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    |
    | The feedback categories offered in the widget. Project-neutral and freely
    | extensible: add, rename, or remove a key here and add its matching label to
    | every locale's lang file. `visual` is this package's core category — the
    | screenshot exists for exactly that. Labels live ONLY in lang
    | (`visual-feedback::messages.categories.<key>`), never here, so a missing
    | label falls back gracefully instead of leaking a raw translation key.
    |
    */
    'categories' => ['bug', 'visual', 'content', 'feature', 'question', 'other'],

    /*
    |--------------------------------------------------------------------------
    | Form fields
    |--------------------------------------------------------------------------
    |
    | Toggles and max lengths for the reporter's input fields. The limits are CODE
    | POINTS, not bytes: they become Laravel `max:` rules on string attributes, and
    | that rule measures with `mb_strlen`. So `message` at 50 000 admits up to ~200 KB
    | of UTF-8, which is exactly why the shipped migration gives it `mediumText`
    | rather than MySQL's 64 KB `text` — size your own column the same way if you
    | write reports somewhere else. The shipped limits do stay inside the shipped
    | columns, and the package's own suite holds them there.
    | `message` is always present; `subject` and `phone` are optional.
    |
    */
    'fields' => [
        'subject' => [
            'enabled' => env('VISUAL_FEEDBACK_FIELD_SUBJECT', true),
            'max_length' => 150,
        ],
        'message' => [
            'max_length' => 50_000,
        ],
        'phone' => [
            'enabled' => env('VISUAL_FEEDBACK_FIELD_PHONE', false),
            'max_length' => 32,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest reporters
    |--------------------------------------------------------------------------
    |
    | The unauthenticated path. Name and email are always OFFERED to a guest;
    | these switches make them REQUIRED. A guest email, when given, is always
    | validated as a real address. Authenticated users never see these fields —
    | their identity comes from the auth guard.
    |
    */
    'guests' => [
        'require_name' => env('VISUAL_FEEDBACK_GUEST_REQUIRE_NAME', false),
        'require_email' => env('VISUAL_FEEDBACK_GUEST_REQUIRE_EMAIL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Uploaded files (and the screenshot). The disk defaults to a PRIVATE local
    | disk — never a public one. Reports may contain sensitive screenshots, so a
    | public disk would expose them by URL. If you point this at S3, keep the
    | bucket private and serve via temporary URLs.
    |
    */
    'attachments' => [
        'disk' => env('VISUAL_FEEDBACK_ATTACHMENTS_DISK', 'local'),
        'directory' => env('VISUAL_FEEDBACK_ATTACHMENTS_DIR', 'visual-feedback'),
        'max_files' => 5,
        'max_file_size' => 5 * 1024 * 1024,   // 5 MB per file (bytes)
        // Bytes, and it bounds the UPLOADS only — the screenshot is validated on its own
        // path against `screenshot.max_bytes` and never enters this sum. With both at their
        // shipped defaults one report can therefore carry 15 MB + 8 MB = 23 MB, which is the
        // number to size a disk quota or an MTA limit against, not this one.
        'max_total_size' => 15 * 1024 * 1024,  // 15 MB per report (bytes)
        'mimes' => ['jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'mp4', 'quicktime'],
        'max_image_dimension' => 15_000,        // px, either edge — decompression-bomb guard
        'max_image_pixels' => 100_000_000,      // 100 MP — decompression-bomb guard
    ],

    /*
    |--------------------------------------------------------------------------
    | Screenshot
    |--------------------------------------------------------------------------
    |
    | The client-side capture. `strategy` is the capture cascade: `auto` tries the
    | native Screen Capture API first and falls back to the DOM renderer; `native`
    | and `dom` force one; `off` disables the screenshot entirely. `debug` turns on
    | the library's own logging — leave it off in production, but know that silent
    | cross-origin discards are invisible without it.
    |
    */
    'screenshot' => [
        'strategy' => env('VISUAL_FEEDBACK_SCREENSHOT_STRATEGY', 'auto'), // auto|native|dom|off
        'scale' => env('VISUAL_FEEDBACK_SCREENSHOT_SCALE', 2),
        // A SERVER-side cap. The browser is not told about it, so an over-large capture is
        // uploaded and then refused — lower this and a reporter gets a capture→reject loop with
        // no way out, because nothing on the client knows to shrink. Sized against what the DOM
        // renderer produces at `scale`, not chosen freely.
        'max_bytes' => 8 * 1024 * 1024,
        'viewport_only' => true,                 // capture the visible viewport, not the full page
        // The forced background for a page whose html AND body backgrounds are both
        // transparent while color-scheme is dark (the UA canvas is invisible to the renderer).
        // Any set background wins via the native cascade; this is only the last resort.
        'dark_fallback_color' => env('VISUAL_FEEDBACK_SCREENSHOT_DARK_FALLBACK', '#111827'),
        // Open <dialog> elements are always hidden — not togglable, because the key that
        // once offered it was read by nothing, and a switch that does nothing is worse than
        // no switch: somebody sets it and stops looking. If it needs to become configurable,
        // it needs a reader first.
        //
        // An `<img src="*.svg">` is NOT fetched and inlined. It goes through the
        // renderer's ordinary image path under `useCORS`, so a cross-origin SVG served
        // without CORS headers is missing from the capture like any other cross-origin
        // image — and one served from your own origin is captured like any other image.
        // Nothing here needs configuring; it is written down because an inlining pass is
        // the thing people assume, and assuming it hides a CORS problem behind a format.
        'flatten_custom_elements' => true,
        'iframe_placeholder' => true,
        'redact_attribute' => 'data-visual-feedback-redact',
        'debug' => env('VISUAL_FEEDBACK_SCREENSHOT_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    |
    | Browser/environment metadata collected with the report. Freshly measured
    | client-side and re-validated server-side. Trim `collect` to only what you need.
    |
    | No raw IP address is ever stored, and none reaches the report, this metadata,
    | the database or any channel — the optional migration ships no `ip_address`
    | column at all. The address IS read at submit, for one purpose: the guest rate
    | limit keys on a SHA-256 of it, which lives in your cache for the length of the
    | window and nowhere else. Written out because you may need it for your own
    | processing record: a hashed IP is pseudonymous, not anonymous.
    |
    */
    'metadata' => [
        'collect' => [
            'url', 'title', 'referrer', 'user_agent',
            'viewport_width', 'viewport_height',
            'screen_width', 'screen_height',
            'device_pixel_ratio', 'scroll_y',
            'language', 'languages', 'timezone', 'platform',
            'touch', 'online', 'cookies_enabled', 'dark_mode',
        ],
        'max_value_length' => 2_000,
        'user_agent_max' => 512,
    ],

    /*
    |--------------------------------------------------------------------------
    | Abuse protection
    |--------------------------------------------------------------------------
    |
    | The built-in floor (honeypot + time trap + rate limits) is ALWAYS active,
    | even under another driver. `driver` selects an ADDITIONAL layer that runs on
    | top of the floor and can only ever reject more, never less: `builtin` (floor
    | only), `none` (floor still on), or ANY key you register yourself with
    | `VisualFeedback::extendAbuse($key, fn () => new YourGate)`. The set is
    | deliberately open — naming a key with no gate registered logs a warning and
    | leaves the floor carrying the request, it never protects nothing silently.
    | Limits are per hour. `on_error` only applies to the builtin driver: `open`
    | lets a submission through if the check itself errors.
    |
    */
    'abuse' => [
        'driver' => env('VISUAL_FEEDBACK_ABUSE_DRIVER', 'builtin'), // builtin|none|any key you register
        'rate_limit' => 30,        // per authenticated user per hour
        'guest_rate_limit' => 5,   // per guest IP per hour
        'min_fill_seconds' => 3,   // server-anchored time trap
        'on_error' => 'open',      // open|closed — builtin only

        /*
         * A Blade view rendered inside the form, for a challenge widget your gate verifies.
         * Null renders nothing at all. The view is included as-is inside `wire:ignore`, so
         * Livewire's DOM morphing cannot destroy a third-party widget between updates — the
         * failure that costs everyone who wires one of these by hand. Bind whatever the widget
         * produces into `challenge` (e.g. `wire:model="challenge.token"`); it reaches your gate
         * on `ReportAttempt::$challenge`, untouched and untrusted.
         */
        'challenge_view' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy acknowledgement
    |--------------------------------------------------------------------------
    |
    | The privacy notice shown to guests before they submit. `source` chooses where
    | the notice text/URL comes from: null (none), `url` (the `url` below),
    | `legal-consent` (the pushery/legal-consent bridge), or a fully-qualified class
    | name implementing the privacy-notice contract. Distinct from consent.
    |
    */
    'privacy' => [
        'source' => env('VISUAL_FEEDBACK_PRIVACY_SOURCE'), // null|url|legal-consent|FQCN
        'url' => env('VISUAL_FEEDBACK_PRIVACY_URL'),

        // Which pushery/legal-consent document the `legal-consent` source reads its
        // acknowledgement sentence from. Only used by that source.
        //
        // `url` above stays REQUIRED with this source: legal-consent registers no public route
        // that displays a document (deliberately — the page belongs to your app), and a required
        // checkbox whose full text cannot be opened is an uninformed clickwrap. So point `url` at
        // your own privacy page; this source only replaces the checkbox LABEL with the sentence
        // that document actually publishes, in the reporter's locale.
        'document_key' => env('VISUAL_FEEDBACK_PRIVACY_DOCUMENT_KEY', 'privacy'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery channels
    |--------------------------------------------------------------------------
    |
    | Where a report is delivered. Each channel is its own array (not a bare
    | boolean) so every channel — not just mail — gets independent queue tuning.
    | `database` uses the optional migration; it is off by default.
    |
    */
    'channels' => [
        'mail' => [
            'enabled' => env('VISUAL_FEEDBACK_CHANNEL_MAIL', true),
            'queue' => env('VISUAL_FEEDBACK_MAIL_QUEUE'),
            'tries' => 3,
            'backoff' => 30,
        ],
        'database' => [
            'enabled' => env('VISUAL_FEEDBACK_CHANNEL_DATABASE', false),
            'queue' => env('VISUAL_FEEDBACK_DATABASE_QUEUE'),
            'tries' => 3,
            'backoff' => 30,
        ],
        'webhook' => [
            'enabled' => env('VISUAL_FEEDBACK_CHANNEL_WEBHOOK', false),
            'queue' => env('VISUAL_FEEDBACK_WEBHOOK_QUEUE'),
            'tries' => 3,
            'backoff' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail channel
    |--------------------------------------------------------------------------
    |
    | The mailed report. `locale` renders the mail in a fixed locale (null = the
    | app's current/fallback locale); set it to `reporter` to render in the
    | reporter's locale. `reply_to_reporter` sets Reply-To to the reporter's email
    | when they gave one, so a maintainer can reply directly.
    |
    */
    'mail' => [
        'to' => env('VISUAL_FEEDBACK_MAIL_TO'),
        'from' => [
            'address' => env('VISUAL_FEEDBACK_MAIL_FROM_ADDRESS'),
            'name' => env('VISUAL_FEEDBACK_MAIL_FROM_NAME'),
        ],
        'reply_to_reporter' => true,
        'locale' => env('VISUAL_FEEDBACK_MAIL_LOCALE'), // null|<locale>|reporter
        'attach_files' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook channel
    |--------------------------------------------------------------------------
    |
    | The signed webhook fallback used when pushery/webhooks-for-laravel is not
    | installed. The request carries three headers: `X-Visual-Feedback-Signature`,
    | `X-Visual-Feedback-Timestamp` and `X-Visual-Feedback-Id` (the report UUID, your
    | idempotency key).
    |
    | The signature is a hex HMAC-SHA256 over "{timestamp}.{rawBody}" — the timestamp,
    | a literal dot, then the exact bytes of the request body — keyed with `secret`.
    | NOT over the body alone: the timestamp is inside the MAC so a captured request
    | cannot be replayed later with its own header rewritten. A verifier that hashes
    | the body alone rejects every genuine delivery. The verification recipe is on the
    | Delivery channels page.
    |
    */
    'webhook' => [
        'url' => env('VISUAL_FEEDBACK_WEBHOOK_URL'),
        // REQUIRED for the fallback sender, not optional: an empty HMAC key yields a
        // signature anybody who knows the URL can reproduce, so a secretless channel
        // reports itself unavailable and is skipped rather than signing with nothing. A
        // receiver that ignores the signature header is satisfied by any string.
        'secret' => env('VISUAL_FEEDBACK_WEBHOOK_SECRET'),
        'timeout' => 5,
        // The receiver may be a third party, so the payload never carries attachment
        // paths/binaries. Set this false to also drop reporter PII (name/email/id),
        // leaving only `is_guest` in the reporter block.
        'include_reporter' => env('VISUAL_FEEDBACK_WEBHOOK_INCLUDE_REPORTER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database channel
    |--------------------------------------------------------------------------
    |
    | The optional reports table. Its migration is published rather than
    | auto-loaded: run `--tag=visual-feedback-migrations` only if you enable the
    | database channel.
    |
    */
    'database' => [
        'table' => 'visual_feedback_reports',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Housekeeping, and it takes TWO commands rather than one. `visual-feedback:prune`
    | reads `reports_days` and `prune_delivered_only`; `orphan_attachments_min_age` is
    | read by `visual-feedback:sweep-orphans` and by nothing else — so tuning it while
    | scheduling only `prune` sweeps no orphan at all. `reports_days` deletes reports
    | older than N days (null = keep forever). `orphan_attachments_min_age` (minutes)
    | must stay ABOVE the queue retry horizon so a still-delivering report's files are
    | never swept. `prune_delivered_only` keeps undelivered reports until they land —
    | judged from the delivery snapshot stored on the row, and only for the OTHER
    | channels: the database channel writes that snapshot before settling its own
    | receipt, so its own entry always reads `pending` and would hold back every row.
    |
    */
    'retention' => [
        'reports_days' => env('VISUAL_FEEDBACK_RETENTION_DAYS'),
        'orphan_attachments_min_age' => 1_440, // minutes (24 h) — > queue retry horizon
        'prune_delivered_only' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    |
    | The default trigger surface. `trigger` is `fab` (the package places a
    | floating action button), `inline` (no modal — the form renders in the page,
    | as a contact form does), or `none` (no button; you place your own trigger or
    | dispatch `visual-feedback:open` yourself).
    |
    | A `mode` mount prop on the widget overrides this per instance, so a page can
    | carry both an inline form and a modal.
    |
    | `assets` is the base URL the published JS bundle is served from.
    |
    */
    'ui' => [
        'trigger' => env('VISUAL_FEEDBACK_UI_TRIGGER', 'fab'), // fab|inline|none
        'position' => env('VISUAL_FEEDBACK_UI_POSITION', 'bottom-right'),
        'assets' => env('VISUAL_FEEDBACK_UI_ASSETS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context providers
    |--------------------------------------------------------------------------
    |
    | Globally registered ReportContextProvider class names. They add structured
    | context (app version, tenant, feature flags, …) to every report. Per-instance
    | context is passed as widget mount props instead.
    |
    */
    'context_providers' => [],

];
