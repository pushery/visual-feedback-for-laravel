<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Contracts\Config\Repository;
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

        return VisualFeedbackServiceProvider::wireKitIsUsable();
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
