<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pushery\VisualFeedback\VisualFeedbackServiceProvider;

/**
 * Typed, drift-safe reader for the package config.
 *
 * The provider merges the shipped defaults under a published config section by section, so a
 * key added in a later release reaches a host who published before it. A published list still
 * stands as written, though, and a host's file can give any key a value of the wrong type.
 * Reading `config('visual-feedback.foo.bar')` directly would then yield null or that value and
 * silently behave as if the feature were off/unlimited.
 *
 * Every read here therefore has a safe code default, and the security-relevant reads
 * (abuse limits, attachment caps, error handling) degrade closed: a missing or invalid
 * key never yields a laxer value than the documented default. Cosmetic reads may
 * default open. This is the mechanism behind the config drift-absicherung.
 */
final readonly class Settings
{
    public const string FIELD_OFF = 'off';

    public const string FIELD_OPTIONAL = 'optional';

    public const string FIELD_REQUIRED = 'required';

    /** @var list<string> */
    public const array FIELD_MODES = [self::FIELD_OFF, self::FIELD_OPTIONAL, self::FIELD_REQUIRED];

    /**
     * The default for each configurable field, and the reason `phone` differs.
     *
     * A phone number is the one piece of contact data a feedback form has no use for by default:
     * it is the most sensitive of the three, it invites a channel nobody staffed, and a host who
     * wants it can say so. The other three start visible and optional — the lowest bar a reporter
     * has to clear before their report is filed.
     *
     * `message` is deliberately absent. A feedback form without a message is not a feedback form,
     * and a switch nobody may turn is a lie in the configuration tree.
     *
     * @var array<string, string>
     */
    public const array FIELD_DEFAULTS = [
        'subject' => self::FIELD_OPTIONAL,
        'name' => self::FIELD_OPTIONAL,
        'email' => self::FIELD_OPTIONAL,
        'phone' => self::FIELD_OFF,
    ];

    /**
     * The configuration of the request being served, asked for at every read.
     *
     * Settings is a singleton, and a singleton that held the repository it was built with would
     * answer from that moment on: under Octane every request runs on a copy of the configuration,
     * and a value a middleware or a tenant bootstrap sets for one request would go unseen.
     */
    private function config(): Repository
    {
        return app(Repository::class);
    }

    public function enabled(): bool
    {
        // Cosmetic-ish master switch: absence defaults to enabled (the documented default).
        return EnvFlag::boolean($this->config()->get('visual-feedback.enabled'), true);
    }

    /**
     * Which view tree to render: `auto`, `plain` or `wirekit`.
     *
     * An unrecognized value falls back to `auto` rather than throwing. A typo in a host's `.env`
     * must not take their feedback widget off the page — `auto` still renders something correct,
     * which is the failure direction to prefer.
     */
    public function uiVariant(): string
    {
        $variant = $this->config()->get('visual-feedback.ui.variant', 'auto');

        return in_array($variant, ['auto', 'plain', 'wirekit'], true) ? $variant : 'auto';
    }

    /**
     * Whether the WireKit tree is the one that should render.
     *
     * `auto` asks whether a new enough WireKit is actually installed, so a host that has it gets
     * the token-styled tree without configuring anything and a host that does not is never
     * served templates whose components do not exist. `wirekit` forces it — and forcing it
     * without the package present is a host's own decision to make, so it is not second-guessed
     * here; the components will simply fail to resolve, loudly, which is the right outcome for
     * an explicit setting.
     */
    public function servesWireKitViews(): bool
    {
        $variant = $this->uiVariant();

        if ($variant !== 'auto') {
            return $variant === 'wirekit';
        }

        return VisualFeedbackServiceProvider::wireKitIsUsable() || $this->warnPlainTree('pushery/wirekit');
    }

    /**
     * Say why `auto` serves the plain tree although the package is installed, and answer no.
     *
     * A silent refusal would be the worse kind of degradation: a host whose WireKit is too old
     * for this tree gets the plain one, with the larger stylesheet, and nothing in the log would
     * say why.
     *
     * It is asked on every boot, from the view paths, and under PHP-FPM every request boots, as
     * every artisan call and scheduler tick does. So the line is written once a day per installed
     * version: the first boot claims a cache key with add(), which only an absent key takes, and
     * every later boot that day stays quiet. A cache that cannot be reached at boot lets the line
     * through rather than hide it. The package name is a parameter, as in
     * `packageSatisfiesWireKitFloor()`, so the warning can be reached with any installed package
     * below the floor, not only with an old WireKit.
     */
    public function warnPlainTree(string $package): bool
    {
        if (! InstalledVersions::isInstalled($package) || VisualFeedbackServiceProvider::packageSatisfiesWireKitFloor($package)) {
            return false;
        }

        $version = InstalledVersions::getPrettyVersion($package) ?? 'unknown';

        if (! rescue(static fn (): bool => Cache::add('visual-feedback:plain-tree-warned:'.$package.':'.$version, true, 86_400), true, false)) {
            return false;
        }

        Log::warning(sprintf(
            '[visual-feedback] ui.variant is auto and %s %s is installed, but the WireKit tree needs %s or later, so the plain tree is served. Update the package, or set ui.variant to wirekit or plain to choose a tree yourself.',
            $package,
            $version,
            VisualFeedbackServiceProvider::WIREKIT_MINIMUM,
        ));

        return false;
    }

    /**
     * Whether only a signed-in reporter may see and use the widget.
     *
     * The strongest cost brake this package can offer, because it removes the anonymous surface
     * rather than bounding it: no trigger, no form, and a submit from a guest session refused.
     * Everything under `abuse` is a limit on traffic that is still allowed to arrive.
     *
     * Ships off, unlike `abuse.global_rate_limit` beside it, and the difference is who it can
     * hurt. A ceiling that is too low costs an install some reports on its worst day; turning
     * this on for a host that never asked would delete their entire guest audience silently. A
     * default may be restrictive about volume and must not be restrictive about who.
     *
     * An unreadable value reads as off for the same reason: `false` is the documented default, so
     * degrading to it is degrading to what the file promises.
     */
    public function requiresAuthentication(): bool
    {
        // `filter_var` rather than `=== true` or a `(bool)` cast, and the two mistakes it avoids
        // point in opposite directions. A host who publishes the config and writes `1` or `'yes'`
        // means yes, and a strict identity check would quietly ignore them. A cast would do the
        // reverse and read the string `'off'` as on, because every non-empty string is truthy —
        // which is the exact trap the shipped config file documents at length for its env reads.
        return filter_var(
            $this->config()->get('visual-feedback.require_authentication'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        ) === true;
    }

    /**
     * The configured abuse driver, as a name — not validated against a fixed list.
     *
     * A whitelist such as `builtin|botgate|none` would quietly make the extension point
     * impossible: a host registering its own gate under any other key could never select it,
     * because the name would degrade to `builtin` before AbuseGateRegistry ever saw it.
     *
     * The safety intent behind such a whitelist is kept where it can actually be checked: a name
     * with no registered gate yields the floor alone and a warning
     * (AbuseGateRegistry::additional()), which is strictly louder than degrading in silence. No
     * value of this setting can reduce protection — the registry only ever adds gates on top of a
     * floor that is unconditional.
     */
    public function abuseDriver(): string
    {
        $driver = $this->config()->get('visual-feedback.abuse.driver');

        return is_string($driver) && $driver !== '' ? $driver : 'builtin';
    }

    /**
     * The Blade view rendered as the challenge region inside the form, or null for none.
     *
     * Null is the default and the state of every install that wires no challenge, so "no view" has
     * to be the cheap, silent path rather than an error. A non-string is treated as null for the
     * same reason a missing key is: this decides what gets rendered, and a broken value must not
     * take the form down with it.
     */
    public function challengeView(): ?string
    {
        $view = $this->config()->get('visual-feedback.abuse.challenge_view');

        return is_string($view) && $view !== '' ? $view : null;
    }

    /** Authenticated per-hour submit limit. Missing/invalid → the restrictive default. */
    public function rateLimit(): int
    {
        return $this->positiveInt('visual-feedback.abuse.rate_limit', 30);
    }

    /** Guest per-hour, per-IP submit limit. Missing/invalid → the restrictive default. */
    public function guestRateLimit(): int
    {
        return $this->positiveInt('visual-feedback.abuse.guest_rate_limit', 5);
    }

    /**
     * The instance-wide per-hour cap, counted across every reporter and every address. `0` is off.
     *
     * The two limits above count per subject, and a distributed sender never meets either. A
     * thousand addresses that each stay under the guest limit produce a thousand reports an hour
     * between them, and on the mail channel every one of those is a message with its attachments
     * at a provider that bills per message and per byte. Nothing in this package bounded that.
     *
     * Note the asymmetry with `positiveInt()`, which every other cap here uses: `0` is honored as
     * "switched off" rather than discarded as invalid, because an operator has to be able to
     * decline a ceiling they know is wrong for them. Everything unreadable still falls back to the
     * shipped cap, and so does an absent key — which is the case that matters, because a config
     * file published before this key existed cannot be distinguished from one that omits it on
     * purpose, and the installs most exposed to the bill are the ones that never read a changelog.
     */
    public function globalRateLimit(): int
    {
        $value = $this->config()->get('visual-feedback.abuse.global_rate_limit');

        return is_int($value) && $value >= 0 ? $value : 1_000;
    }

    /** Server-anchored minimum fill time (seconds). Missing/invalid → the default trap. */
    public function minFillSeconds(): int
    {
        return $this->nonNegativeInt('visual-feedback.abuse.min_fill_seconds', 3);
    }

    /**
     * Whether the builtin driver lets a submission through when its own check errors.
     * Only an explicit `open` opens it; a missing key degrades closed.
     */
    public function abuseOpensOnError(): bool
    {
        return $this->config()->get('visual-feedback.abuse.on_error') === 'open';
    }

    /**
     * Whether an additional driver lets a submission through when its own check throws.
     *
     * Separate from `abuseOpensOnError()` above, and the defaults point opposite ways on purpose.
     * The builtin floor degrades closed on a missing key, because its own failure is a cache
     * outage on this host. An added gate is a third party's: a Turnstile outage would otherwise
     * silence every feedback form that uses it, so the shipped default stays `open`.
     *
     * A host who pays per delivered report wants the other trade, and `closed` is that: while
     * the driver does not answer, submissions are refused rather than waved through with only
     * the floor underneath.
     */
    public function additionalGateOpensOnError(string $driver): bool
    {
        $configured = $this->config()->get("visual-feedback.abuse.drivers.{$driver}.on_error")
            // The scalar default, for the case the map structurally cannot serve. `drivers` is
            // keyed by a name the host chooses, and no environment variable can express a map —
            // so without it a consumer who does not publish the configuration would have no way
            // to harden the one driver they run, short of publishing the whole file and giving up
            // every default it would otherwise follow, for this one word.
            //
            // The map still wins wherever it speaks: a published `'turnstile' => ['on_error' =>
            // 'closed']` is the finer instrument and the reason the map exists. This only answers
            // when the map is silent about this driver.
            ?? $this->config()->get('visual-feedback.abuse.driver_on_error');

        // Anything that is not the explicit word stays open, which is the shipped behavior. This
        // is the one place in this class where an unreadable value degrades permissive, and it is
        // deliberate: a typo in a per-driver key must not take a consumer's form offline.
        return $configured !== 'closed';
    }

    // maxFiles(), maxFileSize() and maxTotalSize() used to sit here and had no production
    // caller. The caps they described are real and enforced — by AttachmentPolicy and by
    // AttachmentValidator, each reading the config itself — so these were a second, unused
    // implementation of the same rule, and maxFiles() even read `0` differently from the policy
    // that enforces the count.
    //
    // Worse than dead code: tests asserted through them that the caps "never become unlimited",
    // which is a guarantee about a path no request takes. The assurance read as coverage of the
    // upload perimeter and covered nothing. It lives with the enforcers, in
    // AttachmentPolicyDefaultsTest and AttachmentValidatorDefaultsTest.

    /**
     * How one of the configurable form fields is meant to behave: `off`, `optional` or `required`.
     *
     * One vocabulary and one place, and that is the whole point of this method. Until 0.9.0 the
     * same question was answered twice in two different shapes: `fields.<f>.enabled` decided
     * whether `subject` and `phone` appeared at all, while `guests.require_name` / `require_email`
     * decided whether name and email were mandatory — and nothing decided whether those two
     * appeared, because the view rendered them for every guest unconditionally. A host who wanted
     * the email box gone had no key to set.
     *
     * Both old shapes still answer, because they are published and sit in other people's files:
     *
     *   1. `fields.<f>.mode`             — the current key, and what the shipped config writes
     *   2. `fields.<f>.enabled === false` — the old off-switch, from a published older config
     *   3. `guests.require_<f> === true`  — the old required-switch, same origin
     *   4. the field's own default
     *
     * Step 1 normally wins outright, because the shipped config always sets `mode` — including
     * for a host who only ever set the old environment variable, since that file folds it in.
     * Steps 2 and 3 exist for a consumer who published the config before 0.9.0 and whose file
     * therefore has no `mode` key at all. The section merge would fill one in from the shipped
     * file; for a field such a file describes in the older keys, the provider clears it again.
     *
     * Degrades toward the visible, not the hidden. A mode somebody wrote that is none of the three
     * words, a typo such as `reqired` or an unknown one such as `mandatory`, shows the field as
     * `optional`: whoever wrote it asked for the field, and falling back to the default would
     * silently remove the phone input, whose default is `off`. Only a mode nobody wrote, null
     * or blank, yields the field's documented default.
     *
     * `true` and `false` are read, not degraded: env() turns `…_MODE=false` into a boolean, and
     * whoever wrote it meant the field off, the way the retired `enabled` switch said it.
     */
    public function fieldMode(string $field): string
    {
        $mode = $this->config()->get("visual-feedback.fields.{$field}.mode");

        if (is_string($mode) && in_array($mode = strtolower(trim($mode)), self::FIELD_MODES, true)) {
            return $mode;
        }

        if (is_bool($mode)) {
            return $mode ? self::FIELD_OPTIONAL : self::FIELD_OFF;
        }

        $enabled = $this->config()->get("visual-feedback.fields.{$field}.enabled");

        if ($enabled === false) {
            return self::FIELD_OFF;
        }

        if ($this->config()->get("visual-feedback.guests.require_{$field}") === true) {
            return self::FIELD_REQUIRED;
        }

        // `enabled === true` is checked last of the three and it is not redundant: without it a
        // host who published an older config and switched the phone field on would fall through
        // to the shipped default, which for that field is `off` — their setting silently undone
        // by the very code meant to honor it. Caught by the control arm beside the `false` one,
        // which is there precisely because reading a boolean at all satisfies the `false` case.
        if ($enabled === true) {
            return self::FIELD_OPTIONAL;
        }

        if ($mode !== null && $mode !== '') {
            return self::FIELD_OPTIONAL;
        }

        return self::FIELD_DEFAULTS[$field] ?? self::FIELD_OPTIONAL;
    }

    /** Does the form render this field at all? */
    public function fieldIsShown(string $field): bool
    {
        return $this->fieldMode($field) !== self::FIELD_OFF;
    }

    /** Must the reporter fill it in? A field that is off is never required — see fieldMode(). */
    public function fieldIsRequired(string $field): bool
    {
        return $this->fieldMode($field) === self::FIELD_REQUIRED;
    }

    private function positiveInt(string $key, int $default): int
    {
        $value = $this->config()->get($key);

        return is_int($value) && $value > 0 ? $value : $default;
    }

    private function nonNegativeInt(string $key, int $default): int
    {
        $value = $this->config()->get($key);

        return is_int($value) && $value >= 0 ? $value : $default;
    }
}
