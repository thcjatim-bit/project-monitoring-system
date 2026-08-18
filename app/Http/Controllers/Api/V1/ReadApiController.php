<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Queries\ApiReadQuery;
use App\Support\ApiException;
use App\Support\ApiFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadApiController extends ApiController
{
    public function portfolio(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $query): JsonResponse {
            $filter = $this->filters($request);

            return $this->success($request, $query->portfolio($filter), $filter);
        });
    }

    public function portfolioProjects(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->collection($request, $query, 'portfolio/projects');
    }

    public function decisionQueue(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $query): JsonResponse {
            $filter = $this->filters($request);
            $portfolio = $query->portfolio($filter);
            $page = $this->page($request, collect($portfolio['decision_queue']), $filter, 'portfolio/decision-queue');

            return $this->success($request, $page->items->all(), $filter, $page);
        });
    }

    public function projects(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->collection($request, $query, 'projects');
    }

    public function project(Request $request, string $project, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $project, $query): JsonResponse {
            $filter = $this->filters($request);
            $model = $this->findProject($project, $filter);

            return $this->success($request, $query->projectDetail($model, $filter), $filter);
        });
    }

    public function curve(Request $request, string $project, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $project, $query): JsonResponse {
            $filter = $this->filters($request);
            $model = $this->findProject($project, $filter);

            return $this->success($request, $query->curve($model, $filter), $filter);
        });
    }

    public function stocks(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $query): JsonResponse {
            $filter = $this->filters($request);
            $page = $this->page($request, $query->stocks($filter, $this->principal($request)), $filter, 'stocks');

            return $this->success($request, $page->items->all(), $filter, $page);
        });
    }

    public function materialRequests(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $query): JsonResponse {
            $filter = $this->filters($request);
            $page = $this->page($request, $query->materialRequests($filter), $filter, 'material-requests');

            return $this->success($request, $page->items->all(), $filter, $page);
        });
    }

    public function materialTransactions(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $query): JsonResponse {
            $filter = $this->filters($request);
            $page = $this->page($request, $query->materialTransactions($filter, null, $this->principal($request)), $filter, 'material-transactions');

            return $this->success($request, $page->items->all(), $filter, $page);
        });
    }

    public function materialReconciliations(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $query): JsonResponse {
            $filter = $this->filters($request);
            $page = $this->page($request, $query->reconciliations($filter), $filter, 'material-reconciliations');

            return $this->success($request, $page->items->all(), $filter, $page);
        });
    }

    public function servicePrices(Request $request, ApiReadQuery $query): JsonResponse
    {
        return $this->run($request, function () use ($request, $query): JsonResponse {
            $filter = $this->filters($request);
            $page = $this->page($request, $query->servicePrices($filter), $filter, 'mitra-service-prices');

            return $this->success($request, $page->items->all(), $filter, $page);
        });
    }

    private function collection(Request $request, ApiReadQuery $query, string $endpoint): JsonResponse
    {
        return $this->run($request, function () use ($request, $query, $endpoint): JsonResponse {
            $filter = $this->filters($request);
            $page = $this->page($request, $query->projectRows($filter), $filter, $endpoint);

            return $this->success($request, $page->items->all(), $filter, $page);
        });
    }

    private function findProject(string $identifier, ApiFilter $filter): Project
    {
        if ($filter->projects !== [] && ! in_array($identifier, $filter->projects, true)) {
            throw new ApiException('resource_not_found', 404, 'Project tidak ditemukan.');
        }

        $project = Project::query()->with('mitra')->where('id_project', $identifier)->first();
        if ($project === null) {
            throw new ApiException('resource_not_found', 404, 'Project tidak ditemukan.');
        }
        if ($filter->mitras !== [] && ! in_array($project->mitra?->kode, $filter->mitras, true)) {
            throw new ApiException('resource_not_found', 404, 'Project tidak ditemukan.');
        }

        return $project;
    }
}
