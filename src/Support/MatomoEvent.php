<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Pushery\VisualFeedback\Data\Report;

/**
 * The category and the action an accepted report is tracked under in Matomo.
 *
 * `matomo.event` names them for every widget, with `{mode}` for the widget's mode, `modal` or
 * `inline`. `matomo.instances` overrides them for a widget mounted with that `instance` name, so a
 * contact form beside the feedback widget can count as `contact`. An instance is read only from
 * what the host declared there. The widget seals its name, and another caller of the pipeline can
 * hand in any: a name the host never declared finds no entry, so it cannot invent an event category
 * of its own. A value that is no text, or nothing once trimmed, is the shipped name, and every name
 * is held to one line.
 */
final class MatomoEvent
{
    public const string CATEGORY = 'visual-feedback';

    public const string ACTION = 'submit';

    /**
     * @param  array<mixed>  $settings  the `matomo` section of the configuration
     * @return array{category: string, action: string}
     */
    public static function names(Report $report, array $settings): array
    {
        $event = is_array($settings['event'] ?? null) ? $settings['event'] : [];
        $instances = is_array($settings['instances'] ?? null) ? $settings['instances'] : [];
        $instance = $report->instance !== null && is_array($instances[$report->instance] ?? null)
            ? $instances[$report->instance]
            : [];

        return [
            'category' => self::name([$instance['category'] ?? null, $event['category'] ?? null], self::CATEGORY, $report->mode),
            'action' => self::name([$instance['action'] ?? null, $event['action'] ?? null], self::ACTION, $report->mode),
        ];
    }

    /**
     * The first candidate that is text and not blank, with `{mode}` filled in, or the shipped name.
     *
     * @param  list<mixed>  $candidates
     */
    private static function name(array $candidates, string $default, string $mode): string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $name = trim((string) preg_replace('/\s+/u', ' ', str_replace('{mode}', $mode, $candidate)));

            if ($name !== '') {
                return $name;
            }
        }

        return $default;
    }
}
