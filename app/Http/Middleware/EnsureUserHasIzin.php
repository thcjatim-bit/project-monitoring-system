<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasIzin
{
    public function handle(Request $request, Closure $next, string $izin): Response
    {
        abort_unless($request->user()?->hasIzin($izin), 403);

        return $next($request);
    }
}
