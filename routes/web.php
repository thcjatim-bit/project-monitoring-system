<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/masuk', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', fn () => view('dashboard', ['user' => request()->user()]))->middleware('izin:read_dashboard')->name('dashboard');
    Route::get('/projects', [ProjectController::class, 'index'])->middleware('izin:read_project')->name('projects.index');
    Route::get('/projects/buat', [ProjectController::class, 'create'])->middleware('izin:create_project')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('izin:create_project')->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->middleware('izin:update_project')->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('izin:delete_project')->name('projects.destroy');
    Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::middleware('izin:manage_users')->group(function (): void {
        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/admin/users', [AdminController::class, 'createUser'])->name('admin.users.create');
        Route::patch('/admin/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('admin.users.toggle');
        Route::post('/admin/users/{user}/reset', [AdminController::class, 'resetCredentials'])->name('admin.users.reset');
    });
    Route::post('/admin/mitras', [AdminController::class, 'onboardMitra'])->middleware('izin:manage_mitras')->name('admin.mitras.create');
    Route::get('/admin/warehouses', [AdminController::class, 'warehouses'])->middleware('izin:manage_warehouses')->name('admin.warehouses');
    Route::post('/admin/warehouses/{warehouse}/users', [AdminController::class, 'assignWarehouse'])->middleware('izin:manage_warehouses')->name('admin.warehouses.assign');
    Route::delete('/admin/warehouses/{warehouse}/users/{user}', [AdminController::class, 'unassignWarehouse'])->middleware('izin:manage_warehouses')->name('admin.warehouses.unassign');
    Route::post('/webhooks/waha', [AdminController::class, 'wahaWebhook'])->withoutMiddleware(['web']);
});
