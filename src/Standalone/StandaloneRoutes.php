<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Standalone;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The two routes the standalone widget talks to, registered where the host calls this.
 *
 * Registering them is the opt-in: an application that never calls this has no route of the widget's at all. They run
 * without a session, a cookie or a CSRF token, because the page that carries the widget has none of them. Should the
 * host register them inside a group that adds those, the session middleware is taken off again here, so a form
 * served from a shared cache can still be sent.
 */
final class StandaloneRoutes
{
    /** The route that hands out a form token. */
    public const string FORM = 'visual-feedback.standalone.form';

    /** The route a report is sent to. */
    public const string REPORTS = 'visual-feedback.standalone.reports';

    /**
     * The middleware of Laravel's `web` group that needs a session or a cookie. A class name loads nothing, so one that
     * an application's stack leaves out costs nothing. The `web` group checks request forgery with
     * PreventRequestForgery; the two older names extend it and are listed for a stack that names one of them itself.
     */
    private const array SESSION_MIDDLEWARE = [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        ValidateCsrfToken::class,
        VerifyCsrfToken::class,
    ];

    /** Register `GET {prefix}/standalone/form` and `POST {prefix}/standalone/reports`. */
    public static function register(string $prefix = 'visual-feedback'): void
    {
        Route::prefix(trim($prefix, '/').'/standalone')
            ->withoutMiddleware(self::SESSION_MIDDLEWARE)
            ->group(static function (): void {
                Route::get('form', [StandaloneController::class, 'form'])->name(self::FORM);
                Route::post('reports', [StandaloneController::class, 'store'])->name(self::REPORTS);
            });
    }
}
