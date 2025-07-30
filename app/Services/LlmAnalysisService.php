<?php

namespace App\Services;

use App\Models\LlmAnalysis;
use App\Models\HistoricalRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LlmAnalysisService
{
    /**
     * @var string
     */
    private string $apiKey;

    /**
     * @var string
     */
    private string $model;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4');
    }

    /**
     * Analyze historical data with context.
     *
     * @param string $query
     * @param array $dataContext
     * @return array
     */
    public function analyzeData(string $query, array $dataContext): array
    {
        $cacheKey = $this->getCacheKey($query, $dataContext);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Check database cache
        $dbCache = LlmAnalysis::where('query_hash', hash('sha256', $cacheKey))->first();
        if ($dbCache) {
            $result = json_decode($dbCache->response, true);
            Cache::put($cacheKey, $result, 3600);
            return $result;
        }

        // Prepare context
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($query, $dataContext);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $analysis = $data['choices'][0]['message']['content'];

                // Save to database
                LlmAnalysis::create([
                    'query_hash' => hash('sha256', $cacheKey),
                    'query' => $query,
                    'parameters' => $dataContext,
                    'response' => json_encode(['analysis' => $analysis]),
                    'model' => $this->model,
                    'tokens_used' => $data['usage']['total_tokens'] ?? null,
                ]);

                $result = ['analysis' => $analysis];
                Cache::put($cacheKey, $result, 3600);

                return $result;
            }
        } catch (\Exception $e) {
            \Log::error('LLM Analysis failed', ['error' => $e->getMessage()]);
        }

        return ['error' => 'Analysis failed'];
    }

    /**
     * Generate insights from time series data.
     *
     * @param array $filters
     * @return array
     */
    public function generateInsights(array $filters): array
    {
        $data = HistoricalRecord::getVisualizationData($filters);

        if ($data->isEmpty()) {
            return ['insights' => 'No data available for the selected filters.'];
        }

        $summary = $this->summarizeData($data);

        $query = "Analyze this historical data and provide key insights about trends, patterns, and significant changes.";

        return $this->analyzeData($query, [
            'summary' => $summary,
            'filters' => $filters,
            'record_count' => $data->count(),
        ]);
    }

    /**
     * Build system prompt for historical data analysis.
     *
     * @return string
     */
    private function buildSystemPrompt(): string
    {
        return "You are an expert historical data analyst with deep knowledge of statistical analysis,
                historical trends, and data visualization. You analyze 200 years of historical data
                to provide meaningful insights, identify patterns, and explain significant changes.
                Your responses should be clear, insightful, and backed by the data provided.";
    }

    /**
     * Build user prompt with context.
     *
     * @param string $query
     * @param array $context
     * @return string
     */
    private function buildUserPrompt(string $query, array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        return "{$query}\n\nData Context:\n{$contextJson}";
    }

    /**
     * Generate cache key.
     *
     * @param string $query
     * @param array $context
     * @return string
     */
    private function getCacheKey(string $query, array $context): string
    {
        return 'llm_analysis:' . md5($query . json_encode($context));
    }

    /**
     * Summarize data for LLM context.
     *
     * @param \Illuminate\Support\Collection $data
     * @return array
     */
    private function summarizeData(\Illuminate\Support\Collection $data): array
    {
        $values = $data->pluck('value')->filter();

        return [
            'min_year' => $data->min('year'),
            'max_year' => $data->max('year'),
            'total_records' => $data->count(),
            'average_value' => round($values->avg(), 2),
            'min_value' => $values->min(),
            'max_value' => $values->max(),
            'categories' => $data->pluck('category')->unique()->values(),
            'regions' => $data->pluck('region')->unique()->values(),
        ];
    }
}
