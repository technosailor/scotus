<?php

use App\Http\Controllers\Api\VisualizationController;

Route::get('/', function () {
    return view('welcome');
});

// Visualization dashboard route
Route::get('/dashboard', [VisualizationController::class, 'index'])->name('dashboard');
