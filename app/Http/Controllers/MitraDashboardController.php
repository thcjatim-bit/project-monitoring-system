<?php

namespace App\Http\Controllers;

use App\Queries\MitraDashboardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class MitraDashboardController extends Controller
{
    public function index(Request $request, MitraDashboardQuery $query): View
    {
        try {
            $data = $query->for($request->user());
        } catch (Throwable $exception) {
            report($exception);
            $data = [
                'projects' => collect(),
                'projectCounts' => ['active' => 0, 'completed' => 0],
                'stocks' => collect(),
                'requests' => collect(),
                'usages' => collect(),
                'transits' => collect(),
                'activities' => collect(),
                'dashboardError' => true,
            ];
        }

        return view('mitra.dashboard', [...$data, 'user' => $request->user()]);
    }

    public function landing(Request $request): View
    {
        return view('mitra.landing', ['user' => $request->user()]);
    }
}
