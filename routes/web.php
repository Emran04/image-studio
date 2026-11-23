<?php

use App\Http\Controllers\ImageGenerationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', function (): Response {
    return Inertia::render('Home', [
        'appName' => config('app.name'),
    ]);
})->name('home');

Route::get('/app', [ImageGenerationController::class, 'create'])->name('generations.create');
Route::post('/app/generations', [ImageGenerationController::class, 'store'])->name('generations.store');
