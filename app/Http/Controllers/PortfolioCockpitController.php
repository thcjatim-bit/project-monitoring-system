<?php

namespace App\Http\Controllers;

use App\Exports\PortfolioCockpitExport;
use App\Queries\PortfolioCockpitQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class PortfolioCockpitController extends Controller
{
    public function index(Request $request, PortfolioCockpitQuery $query): View
    {
        $input = $request->only(['project', 'mitra', 'periode', 'risiko']);

        try {
            return view('portfolio.index', $query->for($request->user(), $input));
        } catch (Throwable $exception) {
            report($exception);

            return view('portfolio.index', $query->errorState($request->user(), $input));
        }
    }

    public function export(
        Request $request,
        PortfolioCockpitQuery $query,
        PortfolioCockpitExport $export,
    ): Response {
        $input = $request->only(['project', 'mitra', 'periode', 'risiko']);

        try {
            return $export->download($query->for($request->user(), $input));
        } catch (Throwable $exception) {
            report($exception);

            return $export->download($query->errorState($request->user(), $input), 503);
        }
    }
}
