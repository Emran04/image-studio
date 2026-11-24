<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CreditPurchaseController;
use App\Http\Controllers\ImageGenerationController;
use App\Http\Controllers\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', function (): Response {
    return Inertia::render('Home', [
        'appName' => config('app.name'),
        'workspaceUrl' => route('generations.create', absolute: false),
    ]);
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
});

Route::get('/admin-login', [AuthenticatedSessionController::class, 'createAdmin'])->name('admin.login');
Route::post('/admin-login', [AuthenticatedSessionController::class, 'storeAdmin'])->name('admin.login.store');

Route::middleware(['auth', 'can:view-admin'])->group(function (): void {
    Route::get('/admin', AdminDashboardController::class)->middleware('cache.headers:private;no_store')->name('admin.index');
    Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroyAdmin'])->name('admin.logout');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/app', [ImageGenerationController::class, 'create'])->name('generations.create');
    Route::get('/app/generations', [ImageGenerationController::class, 'index'])->name('generations.index');
    Route::post('/app/credits', CreditPurchaseController::class)->name('credits.store');
    Route::post('/app/generations', [ImageGenerationController::class, 'store'])->name('generations.store');
    Route::post('/app/generations/{generation}/refresh', [ImageGenerationController::class, 'refresh'])
        ->whereNumber('generation')->middleware('throttle:12,1')->name('generations.refresh');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
