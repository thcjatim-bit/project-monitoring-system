<?php

namespace App\Http\Middleware;

use App\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWarehouseAssignment
{
    public function handle(Request $request, Closure $next, string $izin): Response
    {
        $warehouse = $request->route('warehouse');
        abort_unless($warehouse instanceof Warehouse && $request->user()?->canOperateWarehouse($warehouse, $izin), 403);

        return $next($request);
    }
}
