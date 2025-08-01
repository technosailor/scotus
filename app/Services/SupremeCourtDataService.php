<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\JustiaDataEnrichmentService;

class SupremeCourtDataService
{
    private JustiaDataEnrichmentService $enrichmentService;
    private string $jsonDirectory;

    public function __construct(JustiaDataEnrichmentService $enrichmentService)
    {
        $this->enrichmentService = $enrichmentService;
        $this->jsonDirectory = base_path('json');
    }

    /**
     * Get case data with Redis-first, JSON fallback strategy
     */
    public function getCaseData(string $caseIdentifier): ?array
    {
        // Step 1: Try to get enriched data from Redis/Cache
        $cacheKey = "supreme_court_case_{$caseIdentifier}";
        $cachedData = Cache::get($cacheKey);
        
        if ($cachedData) {
            Log::info("Retrieved case data from cache: {$caseIdentifier}");
            return $cachedData;
        }

        // Step 2: Try to find and load from JSON files
        $jsonFile = $this->findJsonFile($caseIdentifier);
        
        if (!$jsonFile) {
            Log::warning("No JSON file found for case: {$caseIdentifier}");
            return null;
        }

        $caseData = $this->loadJsonFile($jsonFile);
        
        if (!$caseData) {
            Log::error("Failed to load JSON file: {$jsonFile}");
            return null;
        }

        // Step 3: Cache the loaded data for future requests
        Cache::put($cacheKey, $caseData, 86400 * 7); // Cache for 7 days
        
        Log::info("Loaded case data from JSON and cached: {$caseIdentifier}");
        return $caseData;
    }

    /**
     * Get multiple cases with batch processing
     */
    public function getCases(array $caseIdentifiers): array
    {
        $results = [];
        
        foreach ($caseIdentifiers as $identifier) {
            $caseData = $this->getCaseData($identifier);
            if ($caseData) {
                $results[$identifier] = $caseData;
            }
        }
        
        return $results;
    }

    /**
     * Get case data by citation (e.g., "10us87", "11us164")
     */
    public function getCaseByCitation(string $citation): ?array
    {
        // Normalize citation format
        $normalizedCitation = strtolower($citation);
        
        return $this->getCaseData($normalizedCitation);
    }

    /**
     * Get cases by term (e.g., "1789-1850", "1900-1940")
     */
    public function getCasesByTerm(string $term): array
    {
        $cacheKey = "supreme_court_term_{$term}";
        $cachedTermData = Cache::get($cacheKey);
        
        if ($cachedTermData) {
            Log::info("Retrieved term data from cache: {$term}");
            return $cachedTermData;
        }

        // Find all JSON files for the term
        $pattern = $this->jsonDirectory . "/{$term}.*.json";
        $files = glob($pattern);
        
        $cases = [];
        foreach ($files as $file) {
            $caseData = $this->loadJsonFile($file);
            if ($caseData) {
                $identifier = $this->extractIdentifierFromFilename($file);
                $cases[$identifier] = $caseData;
            }
        }
        
        // Cache term data for 1 hour
        Cache::put($cacheKey, $cases, 3600);
        
        Log::info("Loaded {count} cases for term: {$term}", ['count' => count($cases)]);
        return $cases;
    }

    /**
     * Search cases by various criteria
     */
    public function searchCases(array $criteria): array
    {
        $cacheKey = 'supreme_court_search_' . md5(json_encode($criteria));
        $cachedResults = Cache::get($cacheKey);
        
        if ($cachedResults) {
            return $cachedResults;
        }

        $results = [];
        $pattern = $this->jsonDirectory . '/*.json';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            $caseData = $this->loadJsonFile($file);
            if ($caseData && $this->matchesCriteria($caseData, $criteria)) {
                $identifier = $this->extractIdentifierFromFilename($file);
                $results[$identifier] = $caseData;
            }
        }
        
        // Cache search results for 30 minutes
        Cache::put($cacheKey, $results, 1800);
        
        return $results;
    }

    /**
     * Get enriched case data (with Justia content)
     */
    public function getEnrichedCaseData(string $caseIdentifier): ?array
    {
        $caseData = $this->getCaseData($caseIdentifier);
        
        if (!$caseData) {
            return null;
        }

        // If already enriched, return as-is
        if (isset($caseData['enriched_data']) || isset($caseData['decision_type_extracted'])) {
            return $caseData;
        }

        // Try to enrich the case data
        $justiaUrl = $this->enrichmentService->extractJustiaUrl($caseData);
        
        if ($justiaUrl) {
            $enrichedData = $this->enrichmentService->enrichCaseData($justiaUrl);
            if ($enrichedData) {
                $caseData['enriched_data'] = $enrichedData;
                $caseData['enrichment_source'] = 'justia';
                $caseData['enrichment_timestamp'] = now()->toISOString();
            }
        }

        // Extract decision type if available
        $decisionType = $this->enrichmentService->extractDecisionType($caseData);
        if ($decisionType) {
            $caseData['decision_type_extracted'] = $decisionType;
        }

        // Update cache with enriched data
        $cacheKey = "supreme_court_case_{$caseIdentifier}";
        Cache::put($cacheKey, $caseData, 86400 * 7);
        
        return $caseData;
    }

    /**
     * Get case statistics from cache or compute from files
     */
    public function getCaseStatistics(): array
    {
        $cacheKey = 'supreme_court_statistics';
        $cached = Cache::get($cacheKey);
        
        if ($cached) {
            return $cached;
        }

        $stats = [
            'total_cases' => 0,
            'cases_by_term' => [],
            'cases_by_decision_type' => [],
            'enriched_cases' => 0,
            'per_curiam_cases' => 0,
        ];

        $files = glob($this->jsonDirectory . '/*.json');
        
        foreach ($files as $file) {
            $caseData = $this->loadJsonFile($file);
            if (!$caseData) continue;
            
            $stats['total_cases']++;
            
            // Count by term
            if (isset($caseData['term'])) {
                $term = $caseData['term'];
                $stats['cases_by_term'][$term] = ($stats['cases_by_term'][$term] ?? 0) + 1;
            }
            
            // Count by decision type
            if (isset($caseData['decision_type_extracted'])) {
                $type = $caseData['decision_type_extracted'];
                $stats['cases_by_decision_type'][$type] = ($stats['cases_by_decision_type'][$type] ?? 0) + 1;
                
                if ($type === 'per curiam') {
                    $stats['per_curiam_cases']++;
                }
            }
            
            // Count enriched cases
            if (isset($caseData['enriched_data'])) {
                $stats['enriched_cases']++;
            }
        }
        
        // Cache statistics for 1 hour
        Cache::put($cacheKey, $stats, 3600);
        
        return $stats;
    }

    /**
     * Find JSON file by case identifier
     */
    private function findJsonFile(string $identifier): ?string
    {
        // Try exact match first
        $patterns = [
            $this->jsonDirectory . "/{$identifier}.json",
            $this->jsonDirectory . "/*{$identifier}*.json",
            $this->jsonDirectory . "/*{$identifier}.json",
        ];
        
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if (!empty($files)) {
                return $files[0]; // Return first match
            }
        }
        
        return null;
    }

    /**
     * Load and parse JSON file
     */
    private function loadJsonFile(string $filepath): ?array
    {
        if (!file_exists($filepath)) {
            return null;
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            return null;
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("JSON decode error in file: {$filepath}", [
                'error' => json_last_error_msg()
            ]);
            return null;
        }
        
        return $data;
    }

    /**
     * Extract case identifier from filename
     */
    private function extractIdentifierFromFilename(string $filepath): string
    {
        $filename = basename($filepath, '.json');
        return $filename;
    }

    /**
     * Check if case data matches search criteria
     */
    private function matchesCriteria(array $caseData, array $criteria): bool
    {
        foreach ($criteria as $field => $value) {
            switch ($field) {
                case 'name':
                    if (isset($caseData['name']) && 
                        stripos($caseData['name'], $value) === false) {
                        return false;
                    }
                    break;
                    
                case 'term':
                    if (isset($caseData['term']) && $caseData['term'] !== $value) {
                        return false;
                    }
                    break;
                    
                case 'decision_type':
                    if (isset($caseData['decision_type_extracted']) && 
                        $caseData['decision_type_extracted'] !== $value) {
                        return false;
                    }
                    break;
                    
                case 'year':
                    if (isset($caseData['citation']['year']) && 
                        $caseData['citation']['year'] != $value) {
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    }

    /**
     * Clear all cached case data
     */
    public function clearCache(): void
    {
        // This would require a more sophisticated cache tagging system
        // For now, we can clear specific known patterns
        $patterns = [
            'supreme_court_case_*',
            'supreme_court_term_*',
            'supreme_court_search_*',
            'supreme_court_statistics',
        ];
        
        Log::info('Clearing Supreme Court case cache');
        // Note: This is a simplified approach - in production you'd want cache tagging
    }
}