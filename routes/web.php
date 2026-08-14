<?php

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
    Route::get('/projects', fn () => view('projects.index'))->middleware('izin:read_project')->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('izin:create_project')->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->middleware('izin:update_project')->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('izin:delete_project')->name('projects.destroy');
    Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
