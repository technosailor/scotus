<?php

namespace App\Http\Controllers;

use App\Services\SupremeCourtDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DataRetrievalController extends Controller
{
    private SupremeCourtDataService $dataService;

    public function __construct(SupremeCourtDataService $dataService)
    {
        $this->dataService = $dataService;
    }

    /**
     * Get a specific case by identifier
     */
    public function getCase(string $identifier): JsonResponse
    {
        $caseData = $this->dataService->getCaseData($identifier);
        
        if (!$caseData) {
            return response()->json([
                'error' => 'Case not found',
                'identifier' => $identifier
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $caseData,
            'source' => $this->determineDataSource($caseData)
        ]);
    }

    /**
     * Get enriched case data (includes Justia content if available)
     */
    public function getEnrichedCase(string $identifier): JsonResponse
    {
        $caseData = $this->dataService->getEnrichedCaseData($identifier);
        
        if (!$caseData) {
            return response()->json([
                'error' => 'Case not found',
                'identifier' => $identifier
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $caseData,
            'enriched' => isset($caseData['enriched_data']),
            'decision_type' => $caseData['decision_type_extracted'] ?? null,
            'source' => $this->determineDataSource($caseData)
        ]);
    }

    /**
     * Get cases by citation (e.g., /api/cases/citation/10us87)
     */
    public function getCaseByCitation(string $citation): JsonResponse
    {
        $caseData = $this->dataService->getCaseByCitation($citation);
        
        if (!$caseData) {
            return response()->json([
                'error' => 'Case not found',
                'citation' => $citation
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $caseData,
            'source' => $this->determineDataSource($caseData)
        ]);
    }

    /**
     * Get all cases for a specific term
     */
    public function getCasesByTerm(string $term): JsonResponse
    {
        $cases = $this->dataService->getCasesByTerm($term);
        
        return response()->json([
            'success' => true,
            'term' => $term,
            'count' => count($cases),
            'data' => $cases
        ]);
    }

    /**
     * Search cases with various criteria
     */
    public function searchCases(Request $request): JsonResponse
    {
        $criteria = $request->only(['name', 'term', 'decision_type', 'year']);
        
        if (empty($criteria)) {
            return response()->json([
                'error' => 'No search criteria provided'
            ], 400);
        }
        
        $results = $this->dataService->searchCases($criteria);
        
        return response()->json([
            'success' => true,
            'criteria' => $criteria,
            'count' => count($results),
            'data' => $results
        ]);
    }

    /**
     * Get statistics about the case database
     */
    public function getStatistics(): JsonResponse
    {
        $stats = $this->dataService->getCaseStatistics();
        
        return response()->json([
            'success' => true,
            'statistics' => $stats,
            'generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Get multiple cases by identifiers (batch request)
     */
    public function getBatchCases(Request $request): JsonResponse
    {
        $identifiers = $request->input('identifiers', []);
        
        if (empty($identifiers) || !is_array($identifiers)) {
            return response()->json([
                'error' => 'Invalid or missing identifiers array'
            ], 400);
        }
        
        $cases = $this->dataService->getCases($identifiers);
        
        return response()->json([
            'success' => true,
            'requested' => count($identifiers),
            'found' => count($cases),
            'data' => $cases
        ]);
    }

    /**
     * Clear cache (admin endpoint)
     */
    public function clearCache(): JsonResponse
    {
        $this->dataService->clearCache();
        
        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully'
        ]);
    }

    /**
     * Determine the source of the data for response metadata
     */
    private function determineDataSource(array $caseData): string
    {
        if (isset($caseData['enriched_data'])) {
            return 'redis_cache_with_justia_enrichment';
        }
        
        if (isset($caseData['decision_type_extracted'])) {
            return 'redis_cache_with_extracted_data';
        }
        
        return 'redis_cache_or_json_file';
    }
}