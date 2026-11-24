<?php

use App\Http\Controllers\AuthenticatedSessionController;
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

Route::middleware('auth')->group(function (): void {
    Route::get('/app', [ImageGenerationController::class, 'create'])->name('generations.create');
    Route::get('/app/generations', [ImageGenerationController::class, 'index'])->name('generations.index');
    Route::post('/app/generations', [ImageGenerationController::class, 'store'])->name('generations.store');
    Route::post('/app/generations/{generation}/refresh', [ImageGenerationController::class, 'refresh'])
        ->whereNumber('generation')->middleware('throttle:12,1')->name('generations.refresh');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
