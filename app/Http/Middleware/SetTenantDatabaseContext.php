<?php

namespace App\Http\Middleware;

use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantDatabaseContext
{
    public function __construct(private TenantDatabaseContext $tenantDatabaseContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantDatabaseContext->forUser($request->user());

        return $next($request);
    }
}
