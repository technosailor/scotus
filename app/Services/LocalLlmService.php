<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LocalLlmService
{
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.base_url', 'http://localhost:11434');
        $this->model = config('services.ollama.model', 'llama3.2');
    }

    /**
     * Analyze Supreme Court case data using local LLM
     */
    public function analyzeCaseData(array $caseData, string $analysisType = 'sentiment'): array
    {
        $cacheKey = 'llm_analysis:' . md5(json_encode($caseData) . $analysisType);
        
        return Cache::remember($cacheKey, 3600, function () use ($caseData, $analysisType) {
            $prompt = $this->buildPrompt($caseData, $analysisType);
            
            try {
                $response = Http::timeout(60)->post("{$this->baseUrl}/api/generate", [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.3,
                        'top_p' => 0.9,
                    ]
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    return $this->parseResponse($result['response'], $analysisType);
                }
            } catch (\Exception $e) {
                Log::error('Local LLM analysis failed', [
                    'error' => $e->getMessage(),
                    'analysis_type' => $analysisType
                ]);
            }

            return ['error' => 'Analysis failed'];
        });
    }

    /**
     * Analyze sentiment of Supreme Court opinions
     */
    public function analyzeSentiment(string $opinionText): float
    {
        $cacheKey = 'sentiment:' . md5($opinionText);
        
        return Cache::remember($cacheKey, 7200, function () use ($opinionText) {
            $prompt = "Analyze the sentiment of this Supreme Court opinion text. Return only a number between -1.0 (very negative) and 1.0 (very positive):\n\n" . substr($opinionText, 0, 2000);
            
            try {
                $response = Http::timeout(30)->post("{$this->baseUrl}/api/generate", [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => ['temperature' => 0.1]
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $sentimentText = trim($result['response']);
                    
                    // Extract numerical value from response
                    if (preg_match('/-?\d+\.?\d*/', $sentimentText, $matches)) {
                        return max(-1.0, min(1.0, (float) $matches[0]));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Sentiment analysis failed', ['error' => $e->getMessage()]);
            }

            return 0.0; // Neutral sentiment on failure
        });
    }

    /**
     * Extract key insights from Supreme Court cases
     */
    public function extractInsights(array $cases): array
    {
        $prompt = $this->buildInsightsPrompt($cases);
        
        try {
            $response = Http::timeout(120)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'insights' => $result['response'],
                    'generated_at' => now(),
                    'model_used' => $this->model
                ];
            }
        } catch (\Exception $e) {
            Log::error('Insights extraction failed', ['error' => $e->getMessage()]);
        }

        return ['error' => 'Failed to extract insights'];
    }

    /**
     * General analysis method for opinion analysis service
     */
    public function analyze(string $prompt): string
    {
        $cacheKey = 'llm_general_analysis:' . md5($prompt);
        
        return Cache::remember($cacheKey, 3600, function () use ($prompt) {
            try {
                $response = Http::timeout(90)->post("{$this->baseUrl}/api/generate", [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.3,
                        'top_p' => 0.9,
                        'num_predict' => 1000, // Limit response length
                    ]
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    return $result['response'] ?? 'No response generated';
                }
                
                Log::error('LLM analysis request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                
            } catch (\Exception $e) {
                Log::error('LLM analysis failed', [
                    'error' => $e->getMessage(),
                    'prompt_length' => strlen($prompt)
                ]);
            }

            return 'Analysis failed - falling back to default';
        });
    }

    /**
     * Check if Ollama is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/version");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get available models
     */
    public function getAvailableModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");
            
            if ($response->successful()) {
                $data = $response->json();
                return collect($data['models'] ?? [])->pluck('name')->toArray();
            }
        } catch (\Exception $e) {
            Log::error('Failed to get available models', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function buildPrompt(array $caseData, string $analysisType): string
    {
        $basePrompt = "You are an expert Supreme Court legal analyst. Analyze the following Supreme Court case data:\n\n";
        $basePrompt .= json_encode($caseData, JSON_PRETTY_PRINT) . "\n\n";

        switch ($analysisType) {
            case 'sentiment':
                return $basePrompt . "Analyze the sentiment and tone of this case. Consider the legal implications, the court's reasoning, and the overall impact. Return a JSON object with 'sentiment_score' (-1.0 to 1.0), 'confidence' (0.0 to 1.0), and 'reasoning'.";
                
            case 'justice_analysis':
                return $basePrompt . "Analyze the voting patterns and opinions of the justices in this case. Identify who wrote the majority opinion, concurring opinions, and dissents. Return a JSON object with detailed analysis.";
                
            case 'legal_themes':
                return $basePrompt . "Identify the main legal themes, constitutional principles, and precedents involved in this case. Return a JSON object with 'themes', 'constitutional_issues', and 'precedents_cited'.";
                
            default:
                return $basePrompt . "Provide a comprehensive analysis of this Supreme Court case including key legal issues, voting patterns, and historical significance.";
        }
    }

    private function buildInsightsPrompt(array $cases): string
    {
        $prompt = "You are a Supreme Court historian and legal scholar. Analyze these Supreme Court cases and provide key insights:\n\n";
        
        foreach (array_slice($cases, 0, 10) as $case) { // Limit to prevent token overflow
            $prompt .= "Case: " . ($case['case_name'] ?? 'Unknown') . "\n";
            $prompt .= "Date: " . ($case['decision_date'] ?? 'Unknown') . "\n";
            $prompt .= "Summary: " . substr($case['summary'] ?? '', 0, 200) . "\n\n";
        }

        $prompt .= "Provide insights about:\n";
        $prompt .= "1. Common legal themes and trends\n";
        $prompt .= "2. Evolution of judicial philosophy\n";
        $prompt .= "3. Impact on American law and society\n";
        $prompt .= "4. Notable voting patterns or coalitions\n";
        $prompt .= "5. Historical significance\n\n";
        $prompt .= "Keep the analysis concise but insightful.";

        return $prompt;
    }

    private function parseResponse(string $response, string $analysisType): array
    {
        // Try to parse JSON response first
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // If not JSON, return structured response based on analysis type
        switch ($analysisType) {
            case 'sentiment':
                // Extract sentiment score from text
                if (preg_match('/-?\d+\.?\d*/', $response, $matches)) {
                    return [
                        'sentiment_score' => max(-1.0, min(1.0, (float) $matches[0])),
                        'confidence' => 0.7,
                        'reasoning' => $response
                    ];
                }
                break;
                
            default:
                return [
                    'analysis' => $response,
                    'type' => $analysisType,
                    'generated_at' => now()->toISOString()
                ];
        }

        return ['raw_response' => $response];
    }
}