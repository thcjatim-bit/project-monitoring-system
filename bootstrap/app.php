<?php

use App\Http\Middleware\EnsureUserHasIzin;
use App\Http\Middleware\SetTenantDatabaseContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('web', SetTenantDatabaseContext::class);
        $middleware->alias(['izin' => EnsureUserHasIzin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
