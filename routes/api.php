<?php

use App\Http\Controllers\DataRetrievalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Supreme Court Case Data API Routes
Route::prefix('cases')->group(function () {
    // Individual case retrieval
    Route::get('/{identifier}', [DataRetrievalController::class, 'getCase'])
        ->name('api.cases.show');
    
    // Enriched case data (includes Justia content)
    Route::get('/{identifier}/enriched', [DataRetrievalController::class, 'getEnrichedCase'])
        ->name('api.cases.enriched');
    
    // Get case by citation (e.g., /api/cases/citation/10us87)
    Route::get('/citation/{citation}', [DataRetrievalController::class, 'getCaseByCitation'])
        ->name('api.cases.citation');
    
    // Get cases by term (e.g., /api/cases/term/1789-1850)
    Route::get('/term/{term}', [DataRetrievalController::class, 'getCasesByTerm'])
        ->name('api.cases.term');
    
    // Search cases
    Route::get('/search', [DataRetrievalController::class, 'searchCases'])
        ->name('api.cases.search');
    
    // Batch case retrieval
    Route::post('/batch', [DataRetrievalController::class, 'getBatchCases'])
        ->name('api.cases.batch');
});

// System routes
Route::prefix('system')->group(function () {
    // Get database statistics
    Route::get('/statistics', [DataRetrievalController::class, 'getStatistics'])
        ->name('api.system.statistics');
    
    // Clear cache (admin endpoint)
    Route::post('/cache/clear', [DataRetrievalController::class, 'clearCache'])
        ->name('api.system.cache.clear');
});