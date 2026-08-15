<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CommandCenterController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MaterialInventoryController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPlanningController;
use App\Http\Controllers\ProjectProgressController;
use App\Http\Controllers\ProjectMaterialController;
use App\Http\Controllers\ProjectPhotoController;
use App\Http\Controllers\ProjectStepController;
use App\Http\Controllers\SuratJalanController;
use App\Http\Middleware\SetTenantDatabaseContext;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/masuk', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

// PROTOTYPE Gelombang 1, 2 & 3: no auth/session because the mock data is read-only and in-memory.
// Only expose the throwaway UI outside production.
if (! app()->environment('production')) {
    Route::get('/prototype/gelombang-1', fn () => view('prototypes.gelombang-1'))
        ->withoutMiddleware([
            SetTenantDatabaseContext::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
        ])
        ->name('prototype.gelombang-1');

    Route::get('/prototype/gelombang-2', fn () => view('prototypes.gelombang-2'))
        ->withoutMiddleware([
            SetTenantDatabaseContext::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
        ])
        ->name('prototype.gelombang-2');

    Route::get('/prototype/gelombang-3', fn () => view('prototypes.gelombang-3'))
        ->withoutMiddleware([
            SetTenantDatabaseContext::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
        ])
        ->name('prototype.gelombang-3');
}

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [CommandCenterController::class, 'index'])
        ->middleware(['thc', 'izin:read_dashboard'])
        ->name('dashboard');
    Route::get('/projects', [ProjectController::class, 'index'])->middleware('izin:read_project')->name('projects.index');
    Route::get('/projects/buat', [ProjectController::class, 'create'])->middleware('izin:create_project')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('izin:create_project')->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware('izin:read_project')->name('projects.show');
    Route::middleware(['thc', 'izin:manage_project_plan'])->group(function (): void {
        Route::post('/projects/{project}/rab-jasa', [ProjectPlanningController::class, 'storeRabJasa'])->name('projects.rab-jasa.store');
        Route::put('/projects/{project}/plan', [ProjectPlanningController::class, 'updatePlan'])->name('projects.plan.update');
        Route::post('/projects/{project}/variation-orders', [ProjectPlanningController::class, 'storeVariationOrder'])->name('projects.variation-orders.store');
        Route::patch('/projects/{project}/variation-orders/{variationOrder}/approve', [ProjectPlanningController::class, 'approveVariationOrder'])->name('projects.variation-orders.approve');
    });
    Route::post('/projects/{project}/progress', [ProjectProgressController::class, 'store'])->middleware('izin:report_project_progress')->name('projects.progress.store');
    Route::middleware(['thc', 'izin:verify_project_progress'])->group(function (): void {
        Route::patch('/projects/{project}/progress/{progress}/verify', [ProjectProgressController::class, 'verify'])->name('projects.progress.verify');
        Route::patch('/projects/{project}/progress/{progress}/reject', [ProjectProgressController::class, 'reject'])->name('projects.progress.reject');
    });
    Route::patch('/projects/{project}/step', [ProjectStepController::class, 'update'])->middleware('izin:update_project_step')->name('projects.step.update');
    Route::post('/projects/{project}/rab-material', [ProjectMaterialController::class, 'store'])
        ->middleware(['thc', 'izin:manage_project_material'])
        ->name('projects.rab-material.store');
    Route::post('/projects/{project}/photos', [ProjectPhotoController::class, 'store'])
        ->middleware('izin:upload_project_photo')
        ->name('projects.photos.store');
    Route::get('/projects/{project}/photos/{photo}', [ProjectPhotoController::class, 'show'])
        ->middleware('izin:read_project')
        ->name('projects.photos.show');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->middleware('izin:update_project')->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('izin:delete_project')->name('projects.destroy');
    Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::middleware(['thc', 'izin:manage_users'])->group(function (): void {
        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/admin/users', [AdminController::class, 'createUser'])->name('admin.users.create');
        Route::patch('/admin/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('admin.users.toggle');
        Route::post('/admin/users/{user}/reset', [AdminController::class, 'resetCredentials'])->name('admin.users.reset');
    });
    Route::get('/admin/mitras', [AdminController::class, 'mitras'])
        ->middleware(['thc', 'izin:manage_mitras'])
        ->name('admin.mitras');
    Route::post('/admin/mitras', [AdminController::class, 'onboardMitra'])
        ->middleware(['thc', 'izin:manage_mitras'])
        ->name('admin.mitras.create');
    Route::middleware('thc')->group(function (): void {
        Route::get('/admin/warehouses', [AdminController::class, 'warehouses'])->middleware('izin:manage_warehouses')->name('admin.warehouses');
        Route::post('/admin/warehouses', [AdminController::class, 'createWarehouse'])->middleware('izin:manage_warehouses')->name('admin.warehouses.create');
        Route::patch('/admin/warehouses/{warehouse}', [AdminController::class, 'updateWarehouse'])->middleware('izin:manage_warehouses')->name('admin.warehouses.update');
        Route::patch('/admin/warehouses/{warehouse}/deactivate', [AdminController::class, 'deactivateWarehouse'])->middleware('izin:manage_warehouses')->name('admin.warehouses.deactivate');
        Route::post('/admin/warehouses/{warehouse}/users', [AdminController::class, 'assignWarehouse'])->middleware('izin:manage_warehouses')->name('admin.warehouses.assign');
        Route::delete('/admin/warehouses/{warehouse}/users/{user}', [AdminController::class, 'unassignWarehouse'])->middleware('izin:manage_warehouses')->name('admin.warehouses.unassign');
    });
    Route::get('/admin/materials', [AdminController::class, 'materials'])->middleware('izin:read_master_data')->name('admin.materials');
    Route::middleware(['thc', 'izin:manage_materials'])->group(function (): void {
        Route::post('/admin/materials', [AdminController::class, 'createMaterial'])->name('admin.materials.create');
        Route::patch('/admin/materials/{material}', [AdminController::class, 'updateMaterial'])->name('admin.materials.update');
        Route::patch('/admin/materials/{material}/deactivate', [AdminController::class, 'deactivateMaterial'])->name('admin.materials.deactivate');
    });
    Route::prefix('/admin/master')->group(function (): void {
        Route::get('/{entity}', [MasterDataController::class, 'index'])->middleware('izin:read_master_data')->name('admin.master.index');
        Route::middleware(['thc', 'izin:manage_master_data'])->group(function (): void {
            Route::post('/{entity}', [MasterDataController::class, 'store'])->name('admin.master.store');
            Route::patch('/{entity}/{id}', [MasterDataController::class, 'update'])->name('admin.master.update');
            Route::patch('/{entity}/{id}/deactivate', [MasterDataController::class, 'deactivate'])->name('admin.master.deactivate');
        });
    });
    Route::post('/webhooks/waha', [AdminController::class, 'wahaWebhook'])->withoutMiddleware(['web']);
    Route::middleware('izin:operate_warehouse')->group(function (): void {
        Route::post('/warehouse/stock/receive', [MaterialInventoryController::class, 'receive'])->name('warehouse.stock.receive');
        Route::post('/warehouse/stock/issue', [MaterialInventoryController::class, 'issue'])->name('warehouse.stock.issue');
        Route::post('/warehouse/stock/drum-split', [MaterialInventoryController::class, 'splitDrum'])->name('warehouse.stock.drum-split');
        Route::post('/warehouse/transfers', [SuratJalanController::class, 'issue'])->name('warehouse.transfers.issue');
        Route::post('/warehouse/transfers/{suratJalan}/receive', [SuratJalanController::class, 'receive'])->name('warehouse.transfers.receive');
        Route::get('/warehouse/transfers/{suratJalan}/print', [SuratJalanController::class, 'print'])->name('warehouse.transfers.print');
        Route::get('/warehouse/transit', [SuratJalanController::class, 'transit'])->name('warehouse.transit');
    });
    Route::middleware(['thc', 'izin:operate_warehouse'])->group(function (): void {
        Route::post('/warehouse/transfers/{suratJalan}/resolve', [SuratJalanController::class, 'resolve'])->name('warehouse.transfers.resolve');
        Route::post('/warehouse/transfers/{suratJalan}/cancel', [SuratJalanController::class, 'cancel'])->name('warehouse.transfers.cancel');
        Route::post('/warehouse/transfers/{suratJalan}/return', [SuratJalanController::class, 'createReturn'])->name('warehouse.transfers.return');
        Route::post('/warehouse/material-transactions/{materialTransaksi}/correct', [SuratJalanController::class, 'correct'])->name('warehouse.material-transactions.correct');
    });
    Route::middleware('izin:read_material_request')->group(function (): void {
        Route::get('/material-requests', [MaterialRequestController::class, 'index'])->name('material-requests.index');
        Route::get('/material-requests/{materialRequest}', [MaterialRequestController::class, 'show'])->name('material-requests.show');
    });
    Route::post('/material-requests', [MaterialRequestController::class, 'store'])
        ->middleware('izin:create_material_request')
        ->name('material-requests.store');
    Route::middleware(['thc', 'izin:approve_material_request'])->group(function (): void {
        Route::patch('/material-requests/{materialRequest}/approve', [MaterialRequestController::class, 'approve'])->name('material-requests.approve');
        Route::patch('/material-requests/{materialRequest}/reject', [MaterialRequestController::class, 'reject'])->name('material-requests.reject');
    });
});
