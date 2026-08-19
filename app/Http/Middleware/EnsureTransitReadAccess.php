<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTransitReadAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->hasIzin('operate_warehouse') || ($user?->mitra_id !== null && $user->hasIzin('read_transit')), 403);

        return $next($request);
    }
}
