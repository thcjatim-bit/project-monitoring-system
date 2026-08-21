<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureMitraUser;
use App\Http\Middleware\EnsureProjectPlanningAccess;
use App\Http\Middleware\EnsureThcUser;
use App\Http\Middleware\EnsureTransitReadAccess;
use App\Http\Middleware\EnsureUserHasIzin;
use App\Http\Middleware\EnsureUserManagementAccess;
use App\Http\Middleware\EnsureWarehouseAssignment;
use App\Http\Middleware\SetTenantDatabaseContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('photos:sync')->hourly()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('web', SetTenantDatabaseContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetTenantDatabaseContext::class);
        $middleware->alias([
            'izin' => EnsureUserHasIzin::class,
            'thc' => EnsureThcUser::class,
            'mitra' => EnsureMitraUser::class,
            'project-planning' => EnsureProjectPlanningAccess::class,
            'transit.read' => EnsureTransitReadAccess::class,
            'warehouse' => EnsureWarehouseAssignment::class,
            'user-management' => EnsureUserManagementAccess::class,
            'api.key' => AuthenticateApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
