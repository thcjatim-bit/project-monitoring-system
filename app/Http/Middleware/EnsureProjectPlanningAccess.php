<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectPlanningAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $permission = $user?->mitra_id === null ? 'manage_project_plan' : 'manage_mitra_project';

        abort_unless($user?->hasIzin($permission), 403);

        return $next($request);
    }
}
