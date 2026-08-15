<?php

namespace App\Http\Controllers;

use App\Queries\CommandCenterQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CommandCenterController extends Controller
{
    public function index(Request $request, CommandCenterQuery $commandCenterQuery): View
    {
        $pendingMaterialRequests = collect();
        $materialRequestError = null;
        $delayedTransits = collect();
        $transitError = null;
        $criticalStocks = collect();
        $criticalStockError = null;

        if ($request->user()->hasIzin('read_material_request')) {
            try {
                $pendingMaterialRequests = $commandCenterQuery->pendingMaterialRequests();
            } catch (Throwable $exception) {
                report($exception);
                $materialRequestError = 'Antrean Request Material belum dapat dimuat. Coba lagi atau buka modul sumbernya.';
            }
        }

        if ($request->user()->hasIzin('operate_warehouse')) {
            try {
                $delayedTransits = $commandCenterQuery->delayedTransits();
            } catch (Throwable $exception) {
                report($exception);
                $transitError = 'Antrean Transit belum dapat dimuat. Coba lagi atau buka modul sumbernya.';
            }
        }

        if ($request->user()->hasIzin('read_master_data')) {
            try {
                $criticalStocks = $commandCenterQuery->criticalStocks();
            } catch (Throwable $exception) {
                report($exception);
                $criticalStockError = 'Ringkasan stok kritis belum dapat dimuat. Coba lagi atau buka modul sumbernya.';
            }
        }

        return view('dashboard', [
            'user' => $request->user(),
            'pendingMaterialRequests' => $pendingMaterialRequests,
            'materialRequestError' => $materialRequestError,
            'delayedTransits' => $delayedTransits,
            'transitError' => $transitError,
            'criticalStocks' => $criticalStocks,
            'criticalStockError' => $criticalStockError,
        ]);
    }
}
