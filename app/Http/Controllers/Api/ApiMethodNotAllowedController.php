<?php

namespace App\Http\Controllers\Api;

use App\Support\ApiException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiMethodNotAllowedController extends ApiController
{
    public function __invoke(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                throw new ApiException('resource_not_found', 404, 'Resource API tidak ditemukan.');
            }

            throw new ApiException('method_not_allowed', 405, 'API hanya menyediakan operasi baca.', ['allow' => ['GET', 'HEAD', 'OPTIONS']]);
        });
    }
}
