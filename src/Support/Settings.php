<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Pushery\VisualFeedback\VisualFeedbackServiceProvider;

/**
 * Typed, drift-safe reader for the package config.
 *
 * `mergeConfigFrom` only merges the TOP level, so a consumer who publishes the config
 * and later upgrades keeps their old nested arrays — any key added upstream is then
 * simply absent from their file. Reading `config('visual-feedback.foo.bar')` directly
 * would yield null and silently behave as if the feature were off/unlimited.
 *
 * Every read here therefore has a safe code default, and the SECURITY-relevant reads
 * (abuse limits, attachment caps, error handling) degrade CLOSED: a missing or invalid
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

    public function __construct(private Repository $config) {}

    public function enabled(): bool
    {
        // Cosmetic-ish master switch: absence defaults to enabled (the documented default).
        return (bool) ($this->config->get('visual-feedback.enabled') ?? true);
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
        $variant = $this->config->get('visual-feedback.ui.variant', 'auto');

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
     * Say why `auto` serves the plain tree although the package IS installed, and answer no.
     *
     * The refusal used to be silent, and a silent degradation is the worse kind: a host whose
     * WireKit was too old for this tree got the plain one, with the larger stylesheet, and nothing
     * in the log said why. It surfaced only where a test happened to look for a WireKit marker.
     *
     * It is asked once per boot, from the view paths, so it cannot flood a log. The package name
     * is a parameter for the same reason `packageSatisfiesWireKitFloor()` takes one: the suite's
     * own vendor tree always carries a WireKit new enough, and an installed package below the
     * floor is what reaches the warning honestly.
     */
    public function warnPlainTree(string $package): bool
    {
        if (! InstalledVersions::isInstalled($package) || VisualFeedbackServiceProvider::packageSatisfiesWireKitFloor($package)) {
            return false;
        }

        Log::warning(sprintf(
            '[visual-feedback] ui.variant is auto and %s %s is installed, but the WireKit tree needs %s or later, so the plain tree is served. Update the package, or set ui.variant to wirekit or plain to choose a tree yourself.',
            $package,
            InstalledVersions::getPrettyVersion($package) ?? 'unknown',
            VisualFeedbackServiceProvider::WIREKIT_MINIMUM,
        ));

        return false;
    }

    /**
     * The configured abuse driver, as a name — NOT validated against a fixed list.
     *
     * It used to be whitelisted to `builtin|botgate|none`, which quietly made the extension point
     * impossible: a host registering its own gate under any other key could never select it,
     * because this degraded the name to `builtin` before AbuseGateRegistry ever saw it.
     *
     * The safety intent behind that whitelist is kept, and moved to where it can actually be
     * checked: a name with no registered gate yields the floor alone AND a warning
     * (AbuseGateRegistry::additional()), which is strictly louder than degrading in silence. No
     * value of this setting can reduce protection — the registry only ever ADDS gates on top of a
     * floor that is unconditional.
     */
    public function abuseDriver(): string
    {
        $driver = $this->config->get('visual-feedback.abuse.driver');

        return is_string($driver) && $driver !== '' ? $driver : 'builtin';
    }

    /**
     * The Blade view rendered as the challenge region inside the form, or null for none.
     *
     * Null is the default and the state of every install that wires no challenge, so "no view" has
     * to be the cheap, silent path rather than an error. A non-string is treated as null for the
     * same reason a missing key is: this decides what gets RENDERED, and a broken value must not
     * take the form down with it.
     */
    public function challengeView(): ?string
    {
        $view = $this->config->get('visual-feedback.abuse.challenge_view');

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

    /** Server-anchored minimum fill time (seconds). Missing/invalid → the default trap. */
    public function minFillSeconds(): int
    {
        return $this->nonNegativeInt('visual-feedback.abuse.min_fill_seconds', 3);
    }

    /**
     * Whether the builtin driver lets a submission through when its OWN check errors.
     * Only an explicit `open` opens it; a missing key degrades CLOSED.
     */
    public function abuseOpensOnError(): bool
    {
        return $this->config->get('visual-feedback.abuse.on_error') === 'open';
    }

    /** Max attachments per report. Missing/invalid → the default cap (never unlimited). */
    public function maxFiles(): int
    {
        return $this->positiveInt('visual-feedback.attachments.max_files', 5);
    }

    // maxFileSize() and maxTotalSize() used to sit here and had NO production caller. The caps
    // they described are real and enforced — by AttachmentPolicy::maxFileBytes() and by
    // AttachmentValidator, each reading the config itself — so these were a second, unused
    // implementation of the same rule.
    //
    // Worse than dead code: SettingsBehaviourTest asserted through them that the caps "never
    // become unlimited", which is a guarantee about a path no request takes. The assurance read
    // as coverage of the upload perimeter and covered nothing. It lives with the enforcers now,
    // in AttachmentPolicyDefaultsTest and AttachmentValidatorDefaultsTest.
    //
    // maxFiles() stays: it has four callers.

    /**
     * How one of the configurable form fields is meant to behave: `off`, `optional` or `required`.
     *
     * ONE vocabulary and ONE place, and that is the whole point of this method. Until 0.9.0 the
     * same question was answered twice in two different shapes: `fields.<f>.enabled` decided
     * whether `subject` and `phone` appeared at all, while `guests.require_name` / `require_email`
     * decided whether name and email were mandatory — and NOTHING decided whether those two
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
     * for a host who only ever set the OLD environment variable, since that file folds it in.
     * Steps 2 and 3 exist for the other case the class docblock above describes: a consumer who
     * published the config before this release and whose file therefore has no `mode` key at all.
     *
     * Degrades toward the visible, not the hidden: an unreadable or unknown value yields the
     * field's documented default rather than silently removing an input from the form.
     */
    public function fieldMode(string $field): string
    {
        $mode = $this->config->get("visual-feedback.fields.{$field}.mode");

        if (is_string($mode) && in_array($mode = strtolower(trim($mode)), self::FIELD_MODES, true)) {
            return $mode;
        }

        $enabled = $this->config->get("visual-feedback.fields.{$field}.enabled");

        if ($enabled === false) {
            return self::FIELD_OFF;
        }

        if ($this->config->get("visual-feedback.guests.require_{$field}") === true) {
            return self::FIELD_REQUIRED;
        }

        // `enabled === true` is checked LAST of the three and it is not redundant: without it a
        // host who published an older config and switched the phone field ON would fall through
        // to the shipped default, which for that field is `off` — their setting silently undone
        // by the very code meant to honor it. Caught by the control arm beside the `false` one,
        // which is there precisely because reading a boolean at all satisfies the `false` case.
        if ($enabled === true) {
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
        $value = $this->config->get($key);

        return is_int($value) && $value > 0 ? $value : $default;
    }

    private function nonNegativeInt(string $key, int $default): int
    {
        $value = $this->config->get($key);

        return is_int($value) && $value >= 0 ? $value : $default;
    }
}
