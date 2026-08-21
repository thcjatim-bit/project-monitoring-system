<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserManagementAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $permission = $user?->mitra_id === null ? 'manage_users' : 'manage_mitra_users';

        abort_unless($user?->hasIzin($permission), 403);

        return $next($request);
    }
}
