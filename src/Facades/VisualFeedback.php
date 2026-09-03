<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void extend(string $key, Closure $factory)
 * @method static void extendAbuse(string $driver, Closure $factory)
 *
 * @see \Pushery\VisualFeedback\VisualFeedback
 */
final class VisualFeedback extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Pushery\VisualFeedback\VisualFeedback::class;
    }
}
