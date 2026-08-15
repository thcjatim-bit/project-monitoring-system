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

        if ($request->user()->hasIzin('read_material_request')) {
            try {
                $pendingMaterialRequests = $commandCenterQuery->pendingMaterialRequests();
            } catch (Throwable $exception) {
                report($exception);
                $materialRequestError = 'Antrean Request Material belum dapat dimuat. Coba lagi atau buka modul sumbernya.';
            }
        }

        return view('dashboard', [
            'user' => $request->user(),
            'pendingMaterialRequests' => $pendingMaterialRequests,
            'materialRequestError' => $materialRequestError,
        ]);
    }
}
