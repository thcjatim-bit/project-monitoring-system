<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiOptionsController extends ApiController
{
    public function __invoke(Request $request): Response
    {
        return response()->noContent()->header('Allow', 'GET, HEAD, OPTIONS');
    }
}
