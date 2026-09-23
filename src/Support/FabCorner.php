<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The corner the floating trigger sits in, spelled the one way both view trees understand.
 *
 * `ui.position` is read LOGICALLY: `end` is the side a line of text ends on, so a right-to-left
 * page mirrors the trigger the way it mirrors the rest of its layout. WireKit's FAB only speaks
 * that vocabulary. The plain tree used to place the same value physically, so one configuration
 * put the trigger in two different corners of a right-to-left page, depending on which tree
 * served it. Both trees now ask this class, and the corner can no longer differ between them.
 *
 * The four physical spellings the earlier releases documented keep working as their
 * left-to-right reading: `bottom-right` is `bottom-end`. A consumer who never touched the value
 * sees no change in a left-to-right application, where the two readings are the same corner.
 *
 * An unknown value is the default corner. The plain tree used to turn it into a class no rule
 * matched, and the button kept `position: fixed` with no offsets, pinned wherever it happened to
 * sit in the flow. That read as a layout bug rather than a typo.
 */
final class FabCorner
{
    public const string DEFAULT = 'bottom-end';

    /**
     * Every accepted spelling, onto the logical corner it names.
     *
     * @var array<string, 'bottom-end'|'bottom-start'|'top-end'|'top-start'>
     */
    private const array SPELLINGS = [
        'bottom-end' => 'bottom-end',
        'bottom-start' => 'bottom-start',
        'top-end' => 'top-end',
        'top-start' => 'top-start',
        'bottom-right' => 'bottom-end',
        'bottom-left' => 'bottom-start',
        'top-right' => 'top-end',
        'top-left' => 'top-start',
    ];

    /**
     * @return 'bottom-end'|'bottom-start'|'top-end'|'top-start'
     */
    public static function of(mixed $position): string
    {
        return is_string($position) ? (self::SPELLINGS[$position] ?? self::DEFAULT) : self::DEFAULT;
    }
}
