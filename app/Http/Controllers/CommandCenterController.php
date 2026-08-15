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
        $activeUserCounts = ['total' => 0, 'thc' => 0, 'mitra' => 0];
        $activeUserError = null;
        $recentMitraOnboardings = collect();
        $recentMitraOnboardingError = null;
        $pendingMaterialRequests = collect();
        $materialRequestError = null;
        $delayedTransits = collect();
        $transitError = null;
        $criticalStocks = collect();
        $criticalStockError = null;
        $warehouseReadiness = collect();
        $warehouseReadinessError = null;
        $activityFeed = collect();
        $activityFeedError = null;

        $warehouseReadinessPermissions = [
            'manage_warehouses' => $request->user()->hasIzin('manage_warehouses'),
            'read_master_data' => $request->user()->hasIzin('read_master_data'),
            'operate_warehouse' => $request->user()->hasIzin('operate_warehouse'),
        ];
        $warehouseReadinessVisible = in_array(true, $warehouseReadinessPermissions, true);

        try {
            $activityFeed = $commandCenterQuery->activityFeed($request->user());
        } catch (Throwable $exception) {
            report($exception);
            $activityFeedError = 'Aktivitas lintas operasional belum dapat dimuat. Coba lagi atau buka modul sumbernya.';
        }

        if ($request->user()->hasIzin('manage_users')) {
            try {
                $activeUserCounts = $commandCenterQuery->activeUserCounts();
            } catch (Throwable $exception) {
                report($exception);
                $activeUserError = 'Ringkasan User aktif belum dapat dimuat. Coba lagi atau buka modul sumbernya.';
            }
        }

        if ($request->user()->hasIzin('manage_mitras')) {
            try {
                $recentMitraOnboardings = $commandCenterQuery->recentMitraOnboardings();
            } catch (Throwable $exception) {
                report($exception);
                $recentMitraOnboardingError = 'Onboarding Mitra terbaru belum dapat dimuat. Coba lagi atau buka modul sumbernya.';
            }
        }

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

        if ($warehouseReadinessVisible) {
            try {
                $warehouseReadiness = $commandCenterQuery->warehouseReadiness($request->user());
            } catch (Throwable $exception) {
                report($exception);
                $warehouseReadinessError = 'Kesiapan Warehouse belum dapat dimuat. Coba lagi atau buka modul sumbernya.';
            }
        }

        return view('dashboard', [
            'user' => $request->user(),
            'activeUserCounts' => $activeUserCounts,
            'activeUserError' => $activeUserError,
            'recentMitraOnboardings' => $recentMitraOnboardings,
            'recentMitraOnboardingError' => $recentMitraOnboardingError,
            'pendingMaterialRequests' => $pendingMaterialRequests,
            'materialRequestError' => $materialRequestError,
            'delayedTransits' => $delayedTransits,
            'transitError' => $transitError,
            'criticalStocks' => $criticalStocks,
            'criticalStockError' => $criticalStockError,
            'warehouseReadiness' => $warehouseReadiness,
            'warehouseReadinessError' => $warehouseReadinessError,
            'warehouseReadinessVisible' => $warehouseReadinessVisible,
            'warehouseReadinessPermissions' => $warehouseReadinessPermissions,
            'activityFeed' => $activityFeed,
            'activityFeedError' => $activityFeedError,
        ]);
    }
}
