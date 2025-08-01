<?php

use App\Http\Controllers\Api\VisualizationController;
use App\Http\Controllers\SupremeCourtVisualizationController;
use App\Http\Controllers\DataRetrievalController;

// Supreme Court visualization as main page
Route::get('/', [SupremeCourtVisualizationController::class, 'index'])->name('supreme-court.dashboard');

// Legacy routes
Route::get('/dashboard', [VisualizationController::class, 'index'])->name('dashboard');
Route::get('/supreme-court', [SupremeCourtVisualizationController::class, 'index']);

// API routes for Supreme Court data
Route::prefix('api/supreme-court')->group(function () {
    Route::get('/search', [SupremeCourtVisualizationController::class, 'search']);
    Route::get('/cases-per-term', [SupremeCourtVisualizationController::class, 'casesPerTerm']);
    Route::get('/justice-opinion-stats', [SupremeCourtVisualizationController::class, 'justiceOpinionStats']);
    Route::get('/timeline', [SupremeCourtVisualizationController::class, 'timeline']);
    Route::get('/justice-network', [SupremeCourtVisualizationController::class, 'justiceNetwork']);
    Route::post('/chat', [SupremeCourtVisualizationController::class, 'chat']);
    Route::post('/cases/{case}/analyze', [SupremeCourtVisualizationController::class, 'analyzeCase']);
    
    // New precedential analysis routes
    Route::get('/precedential-analysis', [SupremeCourtVisualizationController::class, 'precedentialAnalysis']);
    Route::get('/justice-language-patterns', [SupremeCourtVisualizationController::class, 'justiceLanguagePatterns']);
    Route::get('/topic-trends', [SupremeCourtVisualizationController::class, 'topicTrends']);
    Route::get('/heatmap-data', [SupremeCourtVisualizationController::class, 'heatmapData']);
});

// New Redis-first Data Retrieval API
Route::prefix('api/cases')->group(function () {
    Route::get('/{identifier}', [DataRetrievalController::class, 'getCase']);
    Route::get('/{identifier}/enriched', [DataRetrievalController::class, 'getEnrichedCase']);
    Route::get('/citation/{citation}', [DataRetrievalController::class, 'getCaseByCitation']);
    Route::get('/term/{term}', [DataRetrievalController::class, 'getCasesByTerm']);
    Route::get('/search', [DataRetrievalController::class, 'searchCases']);
    Route::post('/batch', [DataRetrievalController::class, 'getBatchCases']);
});

Route::prefix('api/system')->group(function () {
    Route::get('/statistics', [DataRetrievalController::class, 'getStatistics']);
    Route::post('/cache/clear', [DataRetrievalController::class, 'clearCache']);
});

// Redis Opinion API Routes
Route::prefix('api/redis/opinions')->group(function () {
    Route::get('/', [App\Http\Controllers\RedisOpinionController::class, 'index']);
    Route::get('/search', [App\Http\Controllers\RedisOpinionController::class, 'search']);
    Route::get('/statistics', [App\Http\Controllers\RedisOpinionController::class, 'statistics']);
    Route::get('/case/{caseId}', [App\Http\Controllers\RedisOpinionController::class, 'byCase']);
    Route::get('/justice/{justiceId}', [App\Http\Controllers\RedisOpinionController::class, 'byJustice']);
    Route::get('/type/{type}', [App\Http\Controllers\RedisOpinionController::class, 'byType']);
    Route::get('/{opinionId}', [App\Http\Controllers\RedisOpinionController::class, 'show']);
});
