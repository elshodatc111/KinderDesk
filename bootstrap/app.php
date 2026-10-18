<?php

use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureSecurity;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\EnsureWritable;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);

        $middleware->alias([
            'tenant' => EnsureTenant::class,
            'writable' => EnsureWritable::class,
            'feature' => EnsureFeature::class,
            'super' => EnsureSuperAdmin::class,
            'secure' => EnsureSecurity::class,
        ]);

        // Tashqi tizimlar (Payme, Eskiz) CSRF tokenisiz so'rov yuboradi; ular o'z usulida tekshiriladi
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'lead/*', 'tg/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('webhooks/*') || $request->is('tg/*') || $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
