<?php

use App\Http\Controllers\Api\ApiMethodNotAllowedController;
use App\Http\Controllers\Api\ApiOptionsController;
use App\Http\Controllers\Api\V1\ReadApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.key')->group(function (): void {
    Route::get('/portfolio', [ReadApiController::class, 'portfolio'])->name('api.v1.portfolio');
    Route::get('/portfolio/projects', [ReadApiController::class, 'portfolioProjects'])->name('api.v1.portfolio.projects');
    Route::get('/portfolio/decision-queue', [ReadApiController::class, 'decisionQueue'])->name('api.v1.portfolio.decision-queue');
    Route::get('/projects', [ReadApiController::class, 'projects'])->name('api.v1.projects.index');
    Route::get('/projects/{project}', [ReadApiController::class, 'project'])->name('api.v1.projects.show');
    Route::get('/projects/{project}/curve', [ReadApiController::class, 'curve'])->name('api.v1.projects.curve');
    Route::get('/stocks', [ReadApiController::class, 'stocks'])->name('api.v1.stocks');
    Route::get('/material-requests', [ReadApiController::class, 'materialRequests'])->name('api.v1.material-requests');
    Route::get('/material-transactions', [ReadApiController::class, 'materialTransactions'])->name('api.v1.material-transactions');
    Route::get('/material-reconciliations', [ReadApiController::class, 'materialReconciliations'])->name('api.v1.material-reconciliations');
    Route::get('/mitra-service-prices', [ReadApiController::class, 'servicePrices'])->name('api.v1.mitra-service-prices');
    Route::options('/{path}', ApiOptionsController::class)->where('path', '.*')->name('api.v1.options');
    Route::any('/{path}', ApiMethodNotAllowedController::class)->where('path', '.*')->name('api.v1.method-not-allowed');
});
