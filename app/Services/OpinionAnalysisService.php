<?php

namespace App\Services;

use App\Services\RedisOpinionService;
use App\Services\LocalLlmService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class OpinionAnalysisService
{
    private const ANALYSIS_CACHE_PREFIX = 'opinion_analysis:';
    private const BATCH_ANALYSIS_PREFIX = 'batch_analysis:';
    private const CACHE_TTL = 7 * 24 * 60 * 60; // 7 days

    public function __construct(
        private RedisOpinionService $redisOpinionService,
        private LocalLlmService $llmService
    ) {}

    /**
     * Analyze a single opinion for sentiment, topics, and insights
     */
    public function analyzeOpinion(array $opinion): array
    {
        $cacheKey = self::ANALYSIS_CACHE_PREFIX . $opinion['id'];
        
        // Check if analysis already exists
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // Prepare the analysis prompt
        $prompt = $this->buildAnalysisPrompt($opinion);
        
        try {
            // Call LLM for analysis
            $llmResponse = $this->llmService->analyze($prompt);
            
            // Parse and structure the response
            $analysis = $this->parseAnalysisResponse($llmResponse, $opinion);
            
            // Cache the results
            Cache::put($cacheKey, $analysis, self::CACHE_TTL);
            
            Log::info("Analyzed opinion {$opinion['id']} for case: {$opinion['case_name']}");
            
            return $analysis;
            
        } catch (\Exception $e) {
            Log::error("Failed to analyze opinion {$opinion['id']}: " . $e->getMessage());
            return $this->getDefaultAnalysis($opinion);
        }
    }

    /**
     * Analyze multiple opinions in batch for efficiency
     */
    public function analyzeBatch(Collection $opinions, int $batchSize = 5): array
    {
        $results = [];
        $batches = $opinions->chunk($batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchKey = self::BATCH_ANALYSIS_PREFIX . md5($batch->pluck('id')->implode(','));
            
            // Check if batch analysis exists
            if ($cached = Cache::get($batchKey)) {
                $results = array_merge($results, $cached);
                continue;
            }
            
            $batchResults = [];
            
            try {
                // Build batch analysis prompt
                $prompt = $this->buildBatchAnalysisPrompt($batch);
                
                // Call LLM for batch analysis
                $llmResponse = $this->llmService->analyze($prompt);
                
                // Parse batch response
                $batchAnalysis = $this->parseBatchAnalysisResponse($llmResponse, $batch);
                
                foreach ($batch as $index => $opinion) {
                    $analysis = $batchAnalysis[$index] ?? $this->getDefaultAnalysis($opinion);
                    $batchResults[$opinion['id']] = $analysis;
                    
                    // Cache individual analysis
                    Cache::put(
                        self::ANALYSIS_CACHE_PREFIX . $opinion['id'],
                        $analysis,
                        self::CACHE_TTL
                    );
                }
                
                // Cache batch results
                Cache::put($batchKey, $batchResults, self::CACHE_TTL);
                
                $results = array_merge($results, $batchResults);
                
                Log::info("Analyzed batch {$batchIndex} with " . $batch->count() . " opinions");
                
            } catch (\Exception $e) {
                Log::error("Failed to analyze batch {$batchIndex}: " . $e->getMessage());
                
                // Fall back to individual analysis for this batch
                foreach ($batch as $opinion) {
                    $results[$opinion['id']] = $this->analyzeOpinion($opinion);
                }
            }
        }
        
        return $results;
    }

    /**
     * Analyze opinions by type (dissent, concurrence, majority, plurality)
     */
    public function analyzeByType(string $opinionType, int $limit = 100): array
    {
        $opinions = $this->redisOpinionService->getOpinionsByType($opinionType)->take($limit);
        
        if ($opinions->isEmpty()) {
            return ['error' => "No opinions found for type: {$opinionType}"];
        }
        
        return $this->analyzeBatch($opinions);
    }

    /**
     * Generate thematic analysis across multiple opinions
     */
    public function generateThematicAnalysis(Collection $opinions, string $theme = 'general'): array
    {
        $cacheKey = 'thematic_analysis:' . $theme . ':' . md5($opinions->pluck('id')->implode(','));
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $prompt = $this->buildThematicAnalysisPrompt($opinions, $theme);
            $llmResponse = $this->llmService->analyze($prompt);
            
            // Check if LLM response indicates failure
            $analysisText = $llmResponse;
            if (str_contains($llmResponse, 'Analysis failed') || str_contains($llmResponse, 'falling back')) {
                $analysisText = $this->generateFallbackThematicAnalysis($opinions, $theme);
            }
            
            $analysis = [
                'theme' => $theme,
                'opinion_count' => $opinions->count(),
                'analysis' => $analysisText,
                'cases_analyzed' => $opinions->pluck('case_name')->unique()->take(10)->values(),
                'time_period' => [
                    'earliest' => $opinions->min('case_decision_date'),
                    'latest' => $opinions->max('case_decision_date')
                ],
                'opinion_types' => $opinions->countBy('opinion_type'),
                'generated_at' => now()->toISOString(),
                'analysis_method' => str_contains($llmResponse, 'Analysis failed') ? 'fallback' : 'llm'
            ];
            
            Cache::put($cacheKey, $analysis, self::CACHE_TTL);
            
            return $analysis;
            
        } catch (\Exception $e) {
            Log::error("Failed to generate thematic analysis: " . $e->getMessage());
            
            // Generate fallback analysis instead of returning error
            $fallbackAnalysis = $this->generateFallbackThematicAnalysis($opinions, $theme);
            
            $analysis = [
                'theme' => $theme,
                'opinion_count' => $opinions->count(),
                'analysis' => $fallbackAnalysis,
                'cases_analyzed' => $opinions->pluck('case_name')->unique()->take(10)->values(),
                'time_period' => [
                    'earliest' => $opinions->min('case_decision_date'),
                    'latest' => $opinions->max('case_decision_date')
                ],
                'opinion_types' => $opinions->countBy('opinion_type'),
                'generated_at' => now()->toISOString(),
                'analysis_method' => 'fallback_exception',
                'error_note' => 'LLM unavailable, using fallback analysis'
            ];
            
            Cache::put($cacheKey, $analysis, self::CACHE_TTL);
            
            return $analysis;
        }
    }

    /**
     * Get sentiment distribution across opinion types
     */
    public function getSentimentDistribution(): array
    {
        $cacheKey = 'sentiment_distribution';
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        $opinionTypes = ['dissent', 'concurrence', 'majority', 'plurality'];
        $distribution = [];
        
        foreach ($opinionTypes as $type) {
            $opinions = $this->redisOpinionService->getOpinionsByType($type)->take(50);
            $analysis = $this->analyzeBatch($opinions);
            
            $sentiments = collect($analysis)->pluck('sentiment')->countBy();
            $distribution[$type] = [
                'total_analyzed' => count($analysis),
                'sentiments' => $sentiments,
                'average_sentiment_score' => collect($analysis)->avg('sentiment_score')
            ];
        }
        
        Cache::put($cacheKey, $distribution, self::CACHE_TTL);
        
        return $distribution;
    }

    /**
     * Build analysis prompt for a single opinion
     */
    private function buildAnalysisPrompt(array $opinion): string
    {
        return "Analyze this Supreme Court opinion for sentiment, key topics, legal themes, and judicial philosophy. 

Case: {$opinion['case_name']}
Justice: {$opinion['justice_name']}
Opinion Type: {$opinion['opinion_type']}
Decision Date: {$opinion['case_decision_date']}
Opinion Text: " . substr($opinion['opinion_text'] ?? 'No text available', 0, 2000) . "

Please provide a JSON response with the following structure:
{
    \"sentiment\": \"positive|negative|neutral\",
    \"sentiment_score\": 0.0-1.0,
    \"confidence\": 0.0-1.0,
    \"key_topics\": [\"topic1\", \"topic2\", \"topic3\"],
    \"legal_themes\": [\"constitutional_law\", \"civil_rights\", \"etc\"],
    \"judicial_philosophy\": \"conservative|liberal|moderate\",
    \"tone\": \"formal|passionate|analytical|etc\",
    \"complexity\": \"high|medium|low\",
    \"historical_significance\": \"high|medium|low\",
    \"summary\": \"Brief 2-3 sentence summary of the opinion's main points\"
}";
    }

    /**
     * Build batch analysis prompt for multiple opinions
     */
    private function buildBatchAnalysisPrompt(Collection $opinions): string
    {
        $prompt = "Analyze these Supreme Court opinions for sentiment, topics, and themes. Provide analysis for each opinion:\n\n";
        
        foreach ($opinions as $index => $opinion) {
            $prompt .= "Opinion " . ($index + 1) . ":\n";
            $prompt .= "Case: {$opinion['case_name']}\n";
            $prompt .= "Justice: {$opinion['justice_name']}\n";
            $prompt .= "Type: {$opinion['opinion_type']}\n";
            $prompt .= "Text: " . substr($opinion['opinion_text'] ?? 'No text available', 0, 1000) . "\n\n";
        }
        
        $prompt .= "Provide a JSON array with analysis for each opinion in the same order, using this structure for each:
{
    \"sentiment\": \"positive|negative|neutral\",
    \"sentiment_score\": 0.0-1.0,
    \"key_topics\": [\"topic1\", \"topic2\"],
    \"legal_themes\": [\"theme1\", \"theme2\"],
    \"judicial_philosophy\": \"conservative|liberal|moderate\",
    \"summary\": \"Brief summary\"
}";
        
        return $prompt;
    }

    /**
     * Build thematic analysis prompt
     */
    private function buildThematicAnalysisPrompt(Collection $opinions, string $theme): string
    {
        $caseSummary = $opinions->take(20)->map(function ($opinion) {
            return "- {$opinion['case_name']} ({$opinion['opinion_type']} by {$opinion['justice_name']})";
        })->implode("\n");
        
        return "Analyze these Supreme Court opinions for thematic patterns related to '{$theme}':

Cases analyzed ({$opinions->count()} total):
{$caseSummary}

Please provide a comprehensive thematic analysis covering:
1. Major themes and patterns
2. Evolution of judicial thinking over time
3. Differences between opinion types (majority, dissent, concurrence)
4. Key legal concepts and constitutional principles
5. Notable quotes or phrases that exemplify the themes
6. Historical context and significance

Focus on providing insights that would be valuable for legal scholars, historians, and the general public interested in Supreme Court jurisprudence.";
    }

    /**
     * Generate fallback thematic analysis when LLM is unavailable
     */
    private function generateFallbackThematicAnalysis(Collection $opinions, string $theme): string
    {
        $timeSpan = $this->getTimeSpan($opinions);
        $notableCases = $this->getNotableCases($opinions, $theme);
        $opinionTypes = $opinions->countBy('opinion_type');
        
        $analysis = "**Thematic Analysis: {$theme}**\n\n";
        
        $analysis .= "This analysis examines {$opinions->count()} Supreme Court opinions spanning from {$timeSpan['start']} to {$timeSpan['end']}, ";
        $analysis .= "focusing on themes related to {$theme}.\n\n";
        
        $analysis .= "**Key Observations:**\n\n";
        
        // Time period analysis
        if ($timeSpan['years'] > 50) {
            $analysis .= "• **Historical Evolution**: The {$timeSpan['years']}-year span represents a significant period in American jurisprudence, ";
            $analysis .= "showing the evolution of judicial thinking on {$theme}-related issues.\n\n";
        }
        
        // Notable cases
        if (!empty($notableCases)) {
            $analysis .= "• **Landmark Cases**: Several historically significant cases appear in this dataset, including ";
            $analysis .= implode(', ', array_slice($notableCases, 0, 5));
            if (count($notableCases) > 5) {
                $analysis .= ", and " . (count($notableCases) - 5) . " others";
            }
            $analysis .= ".\n\n";
        }
        
        // Opinion type analysis
        $analysis .= "• **Opinion Distribution**: ";
        $analysis .= "The analysis includes {$opinionTypes['majority']} majority opinions, ";
        $analysis .= "{$opinionTypes['dissent']} dissents, {$opinionTypes['concurrence']} concurrences, ";
        $analysis .= "and {$opinionTypes['plurality']} plurality opinions, providing diverse judicial perspectives.\n\n";
        
        // Theme-specific analysis
        $analysis .= $this->getThemeSpecificAnalysis($theme, $opinions);
        
        $analysis .= "\n**Methodology Note**: This analysis was generated using historical case metadata and established legal scholarship. ";
        $analysis .= "For deeper textual analysis, consider running the full LLM analysis when the service is available.";
        
        return $analysis;
    }

    /**
     * Get time span information from opinions
     */
    private function getTimeSpan(Collection $opinions): array
    {
        $dates = $opinions->pluck('case_decision_date')->filter()->sort();
        $start = $dates->first();
        $end = $dates->last();
        
        $startYear = $start ? date('Y', strtotime($start)) : 'unknown';
        $endYear = $end ? date('Y', strtotime($end)) : 'unknown';
        
        return [
            'start' => $startYear,
            'end' => $endYear,
            'years' => $endYear && $startYear ? $endYear - $startYear : 0
        ];
    }

    /**
     * Identify notable cases based on historical significance
     */
    private function getNotableCases(Collection $opinions, string $theme): array
    {
        $landmarkCases = [
            'Brown v. Board of Education',
            'Plessy v. Ferguson', 
            'Dred Scott v. Sandford',
            'The Civil Rights Cases',
            'Worcester v. Georgia',
            'Marbury v. Madison',
            'Fletcher v. Peck',
            'Gibbons v. Ogden',
            'McCulloch v. Maryland',
            'Slaughter-House Cases',
            'Munn v. Illinois',
            'United States v. E. C. Knight Company'
        ];
        
        return $opinions->pluck('case_name')
            ->intersect($landmarkCases)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Generate theme-specific analysis
     */
    private function getThemeSpecificAnalysis(string $theme, Collection $opinions): string
    {
        switch (strtolower($theme)) {
            case 'civil_rights':
                return "• **Civil Rights Evolution**: The cases span critical periods in American civil rights history, " .
                       "from early constitutional foundations through Reconstruction-era decisions. " .
                       "Notable patterns include the Court's shifting approach to federal vs. state authority " .
                       "in protecting individual rights, and the evolution from restrictive to more expansive " .
                       "interpretations of constitutional protections.\n\n";
                       
            case 'commerce':
            case 'interstate_commerce':
                return "• **Commerce Clause Development**: These opinions trace the evolution of interstate commerce " .
                       "regulation, showing the Court's changing interpretation of federal power over economic activity. " .
                       "Early cases established foundational principles, while later decisions expanded or contracted " .
                       "federal regulatory authority.\n\n";
                       
            case 'federalism':
                return "• **Federal-State Relations**: The analysis reveals ongoing tensions between federal and state " .
                       "authority, with opinions reflecting different judicial philosophies about the proper balance " .
                       "of power in the federal system. Dissents often advocate for stronger state sovereignty.\n\n";
                       
            case 'constitutional_interpretation':
                return "• **Interpretive Methods**: These cases demonstrate varying approaches to constitutional " .
                       "interpretation, from strict constructionism to living constitution theories. " .
                       "Opinion types show different judicial philosophies in action.\n\n";
                       
            default:
                return "• **Judicial Patterns**: The opinions reveal evolving judicial approaches to {$theme}, " .
                       "with dissenting opinions often presaging future majority positions and concurrences " .
                       "offering nuanced middle grounds on complex legal issues.\n\n";
        }
    }

    /**
     * Parse LLM analysis response
     */
    private function parseAnalysisResponse(string $response, array $opinion): array
    {
        try {
            // Try to extract JSON from the response
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $parsed = json_decode($matches[0], true);
                if ($parsed) {
                    return array_merge($parsed, [
                        'opinion_id' => $opinion['id'],
                        'analyzed_at' => now()->toISOString()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to parse analysis response for opinion {$opinion['id']}: " . $e->getMessage());
        }
        
        // Fallback: basic text analysis
        return $this->getDefaultAnalysis($opinion);
    }

    /**
     * Parse batch analysis response
     */
    private function parseBatchAnalysisResponse(string $response, Collection $opinions): array
    {
        try {
            // Try to extract JSON array from response
            if (preg_match('/\[.*\]/s', $response, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed)) {
                    return $parsed;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to parse batch analysis response: " . $e->getMessage());
        }
        
        // Fallback: return default analysis for each opinion
        return $opinions->map(fn($opinion) => $this->getDefaultAnalysis($opinion))->values()->toArray();
    }

    /**
     * Get default analysis when LLM analysis fails
     */
    private function getDefaultAnalysis(array $opinion): array
    {
        return [
            'opinion_id' => $opinion['id'],
            'sentiment' => 'neutral',
            'sentiment_score' => 0.5,
            'confidence' => 0.1,
            'key_topics' => ['legal_analysis'],
            'legal_themes' => ['constitutional_law'],
            'judicial_philosophy' => 'moderate',
            'tone' => 'formal',
            'complexity' => 'medium',
            'historical_significance' => 'medium',
            'summary' => 'Supreme Court opinion analysis unavailable',
            'analyzed_at' => now()->toISOString(),
            'analysis_method' => 'default'
        ];
    }

    /**
     * Clear all analysis cache
     */
    public function clearAnalysisCache(): int
    {
        $patterns = [
            self::ANALYSIS_CACHE_PREFIX . '*',
            self::BATCH_ANALYSIS_PREFIX . '*',
            'thematic_analysis:*',
            'sentiment_distribution'
        ];

        $deleted = 0;
        foreach ($patterns as $pattern) {
            $keys = Cache::getRedis()->keys($pattern);
            if (!empty($keys)) {
                $deleted += Cache::getRedis()->del($keys);
            }
        }

        return $deleted;
    }
}