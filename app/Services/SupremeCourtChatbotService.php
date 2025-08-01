<?php

namespace App\Services;

use App\Services\LocalLlmService;
use App\Services\RedisOpinionService;
use App\Models\SupremeCourtCase;
use App\Models\Opinion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class SupremeCourtChatbotService
{
    private const CHAT_CACHE_PREFIX = 'chat_response:';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private LocalLlmService $llmService,
        private RedisOpinionService $redisService
    ) {}

    /**
     * Process a user question and return an AI response with relevant cases
     */
    public function processQuestion(string $question, array $context = []): array
    {
        $cacheKey = self::CHAT_CACHE_PREFIX . md5($question . serialize($context));
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            // Extract relevant topics/keywords from the question
            $keywords = $this->extractKeywords($question);
            
            // Search for relevant cases based on keywords
            $relevantCases = $this->findRelevantCases($keywords, $question);
            
            // Generate LLM response with case context
            $llmResponse = $this->generateLLMResponse($question, $relevantCases, $context);
            
            // Parse and format the response
            $response = $this->formatResponse($llmResponse, $relevantCases);
            
            Cache::put($cacheKey, $response, self::CACHE_TTL);
            
            Log::info("Chatbot processed question", [
                'question_length' => strlen($question),
                'cases_found' => count($relevantCases),
                'response_length' => strlen($response['message'])
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error("Chatbot error: " . $e->getMessage());
            return $this->getErrorResponse("I'm having trouble processing your question right now. Please try again.");
        }
    }

    /**
     * Extract keywords and legal concepts from user question
     */
    private function extractKeywords(string $question): array
    {
        $legalTerms = [
            // Constitutional concepts
            'due process', 'equal protection', 'commerce clause', 'first amendment', 
            'fourth amendment', 'fifth amendment', 'fourteenth amendment', 'interstate commerce',
            'federalism', 'separation of powers', 'judicial review', 'constitutional interpretation',
            
            // Civil rights topics
            'civil rights', 'segregation', 'discrimination', 'voting rights', 'affirmative action',
            'desegregation', 'racial equality', 'civil liberties', 'freedom of speech',
            
            // Legal concepts
            'precedent', 'stare decisis', 'originalism', 'textualism', 'living constitution',
            'strict scrutiny', 'rational basis', 'intermediate scrutiny',
            
            // Court terms
            'majority opinion', 'dissent', 'concurrence', 'plurality', 'unanimous',
            'landmark case', 'overrule', 'overturn',
            
            // Time periods
            'warren court', 'burger court', 'rehnquist court', 'roberts court',
            'new deal', 'reconstruction', 'progressive era'
        ];
        
        $keywords = [];
        $questionLower = strtolower($question);
        
        // Find legal terms in the question
        foreach ($legalTerms as $term) {
            if (str_contains($questionLower, $term)) {
                $keywords[] = $term;
            }
        }
        
        // Extract potential case names (capitalized words that might be names)
        if (preg_match_all('/\b[A-Z][a-z]+(?:\s+v\.?\s+[A-Z][a-z]+)?\b/', $question, $matches)) {
            $keywords = array_merge($keywords, $matches[0]);
        }
        
        // Add general topic words
        $topicWords = ['rights', 'freedom', 'liberty', 'government', 'state', 'federal', 'court', 'justice'];
        foreach ($topicWords as $word) {
            if (str_contains($questionLower, $word)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }

    /**
     * Find relevant Supreme Court cases based on keywords and question context
     */
    private function findRelevantCases(array $keywords, string $question): Collection
    {
        $relevantCases = collect();
        
        // Search in Redis opinions first
        $redisOpinions = $this->searchRedisOpinions($keywords, $question);
        
        if ($redisOpinions->isNotEmpty()) {
            // Get case details for Redis opinions
            $caseIds = $redisOpinions->pluck('case_id')->unique();
            $cases = SupremeCourtCase::whereIn('id', $caseIds)->with('term')->get();
            
            foreach ($cases as $case) {
                $relevantCases->push([
                    'id' => $case->id,
                    'case_name' => $case->case_name,
                    'decision_date' => $case->decision_date,
                    'term_year' => $case->term?->year,
                    'justia_url' => $case->raw_data['justia_url'] ?? null,
                    'summary' => $case->summary,
                    'facts' => $case->facts,
                    'question' => $case->question,
                    'conclusion' => $case->conclusion,
                    'relevance_score' => $this->calculateRelevanceScore($case, $keywords, $question),
                    'opinions' => $redisOpinions->where('case_id', $case->id)->values()
                ]);
            }
        }
        
        // Supplement with direct database search if needed
        if ($relevantCases->count() < 5) {
            $dbCases = $this->searchDatabaseCases($keywords, $question, 10);
            foreach ($dbCases as $case) {
                if ($relevantCases->where('id', $case->id)->isEmpty()) {
                    $relevantCases->push([
                        'id' => $case->id,
                        'case_name' => $case->case_name,
                        'decision_date' => $case->decision_date,
                        'term_year' => $case->term?->year,
                        'justia_url' => $case->raw_data['justia_url'] ?? null,
                        'summary' => $case->summary,
                        'facts' => $case->facts,
                        'question' => $case->question,
                        'conclusion' => $case->conclusion,
                        'relevance_score' => $this->calculateRelevanceScore($case, $keywords, $question),
                        'opinions' => collect()
                    ]);
                }
            }
        }
        
        // Sort by relevance and return top results
        return $relevantCases->sortByDesc('relevance_score')->take(8);
    }

    /**
     * Search Redis opinions for relevant content
     */
    private function searchRedisOpinions(array $keywords, string $question): Collection
    {
        $allOpinions = collect();
        
        // Search each opinion type
        foreach (['dissent', 'concurrence', 'majority', 'plurality'] as $type) {
            $opinions = $this->redisService->getOpinionsByType($type);
            
            foreach ($opinions as $opinion) {
                $searchText = strtolower(implode(' ', [
                    $opinion['case_name'] ?? '',
                    $opinion['justice_name'] ?? '',
                    $opinion['opinion_text'] ?? ''
                ]));
                
                $matches = 0;
                foreach ($keywords as $keyword) {
                    if (str_contains($searchText, strtolower($keyword))) {
                        $matches++;
                    }
                }
                
                if ($matches > 0) {
                    $opinion['keyword_matches'] = $matches;
                    $allOpinions->push($opinion);
                }
            }
        }
        
        return $allOpinions->sortByDesc('keyword_matches')->take(20);
    }

    /**
     * Search database cases for relevant content
     */
    private function searchDatabaseCases(array $keywords, string $question, int $limit = 10): Collection
    {
        $query = SupremeCourtCase::with('term');
        
        // Search in case names, summaries, facts, questions, conclusions
        $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhere('case_name', 'LIKE', "%{$keyword}%")
                  ->orWhere('summary', 'LIKE', "%{$keyword}%")
                  ->orWhere('facts', 'LIKE', "%{$keyword}%")
                  ->orWhere('question', 'LIKE', "%{$keyword}%")
                  ->orWhere('conclusion', 'LIKE', "%{$keyword}%");
            }
        });
        
        return $query->limit($limit)->get();
    }

    /**
     * Calculate relevance score for a case
     */
    private function calculateRelevanceScore(SupremeCourtCase $case, array $keywords, string $question): float
    {
        $score = 0;
        
        $searchableText = strtolower(implode(' ', [
            $case->case_name,
            $case->summary ?? '',
            $case->facts ?? '',
            $case->question ?? '',
            $case->conclusion ?? ''
        ]));
        
        // Score based on keyword matches
        foreach ($keywords as $keyword) {
            $keywordLower = strtolower($keyword);
            $matches = substr_count($searchableText, $keywordLower);
            
            // Weight matches in case name more heavily
            if (str_contains(strtolower($case->case_name), $keywordLower)) {
                $score += 5;
            }
            
            $score += $matches * 1.5;
        }
        
        // Boost score for landmark cases
        $landmarkCases = [
            'Brown v. Board of Education', 'Roe v. Wade', 'Miranda v. Arizona',
            'Marbury v. Madison', 'Plessy v. Ferguson', 'Dred Scott v. Sandford',
            'Gideon v. Wainwright', 'Mapp v. Ohio', 'Worcester v. Georgia'
        ];
        
        if (in_array($case->case_name, $landmarkCases)) {
            $score += 3;
        }
        
        return $score;
    }

    /**
     * Generate LLM response with case context
     */
    private function generateLLMResponse(string $question, Collection $cases, array $context): string
    {
        $caseSummaries = $cases->take(5)->map(function ($case) {
            return "- {$case['case_name']} ({$case['decision_date']}): " . substr($case['summary'] ?? 'Supreme Court case', 0, 200);
        })->implode("\n");
        
        $prompt = "You are a Supreme Court legal expert and historian. A user has asked: \"{$question}\"\n\n";
        
        if ($cases->isNotEmpty()) {
            $prompt .= "Here are relevant Supreme Court cases from the database:\n{$caseSummaries}\n\n";
        }
        
        if (!empty($context)) {
            $prompt .= "Previous conversation context:\n" . implode("\n", array_slice($context, -3)) . "\n\n";
        }
        
        $prompt .= "Please provide a comprehensive answer that:\n";
        $prompt .= "1. Directly answers the user's question\n";
        $prompt .= "2. References specific cases by name when relevant\n";
        $prompt .= "3. Provides historical context and legal significance\n";
        $prompt .= "4. Explains complex legal concepts in accessible terms\n";
        $prompt .= "5. Mentions important justices and their reasoning when applicable\n";
        $prompt .= "6. Keeps the response engaging and informative (aim for 2-4 paragraphs)\n\n";
        $prompt .= "Focus on accuracy and cite specific cases when making claims. If you're unsure about something, acknowledge it.";
        
        return $this->llmService->analyze($prompt);
    }

    /**
     * Format the response with cases and links
     */
    private function formatResponse(string $llmResponse, Collection $cases): array
    {
        // Clean up the LLM response
        $message = trim($llmResponse);
        
        // If LLM failed, provide a fallback response
        if (str_contains($message, 'Analysis failed') || str_contains($message, 'falling back')) {
            $message = $this->generateFallbackResponse($cases);
        }
        
        // Extract mentioned case names from the response and link them
        $linkedMessage = $this->addCaseLinks($message, $cases);
        
        // Prepare related cases with full details
        $relatedCases = $cases->take(6)->map(function ($case) {
            return [
                'id' => $case['id'],
                'case_name' => $case['case_name'],
                'decision_date' => $case['decision_date'],
                'term_year' => $case['term_year'],
                'justia_url' => $case['justia_url'],
                'summary' => $case['summary'] ? substr($case['summary'], 0, 300) . '...' : null,
                'relevance_score' => round($case['relevance_score'], 2)
            ];
        })->values();
        
        return [
            'message' => $linkedMessage,
            'related_cases' => $relatedCases,
            'case_count' => $cases->count(),
            'response_time' => now()->toISOString(),
            'has_cases' => $cases->isNotEmpty()
        ];
    }

    /**
     * Add clickable links to case names in the response
     */
    private function addCaseLinks(string $message, Collection $cases): string
    {
        foreach ($cases as $case) {
            $caseName = $case['case_name'];
            $justiaUrl = $case['justia_url'];
            
            if ($justiaUrl && str_contains($message, $caseName)) {
                // Replace case name with linked version
                $linkedName = "<a href=\"{$justiaUrl}\" target=\"_blank\" class=\"case-link font-semibold text-blue-600 hover:text-blue-800 underline\">{$caseName}</a>";
                $message = str_replace($caseName, $linkedName, $message);
            }
        }
        
        return $message;
    }

    /**
     * Generate fallback response when LLM is unavailable
     */
    private function generateFallbackResponse(Collection $cases): string
    {
        if ($cases->isEmpty()) {
            return "I found your question interesting, but I wasn't able to locate specific Supreme Court cases directly related to your query in our database. You might want to try rephrasing your question or asking about specific legal concepts, case names, or constitutional amendments.";
        }
        
        $caseList = $cases->take(3)->map(function ($case) {
            $year = $case['decision_date'] ? date('Y', strtotime($case['decision_date'])) : 'Unknown';
            return "• **{$case['case_name']}** ({$year})";
        })->implode("\n");
        
        return "Based on your question, I found several relevant Supreme Court cases in our database:\n\n{$caseList}\n\nThese cases may help answer your question. You can click on any case name to read the full opinion on Justia. For more detailed analysis, please try asking about specific aspects of these cases or related legal concepts.";
    }

    /**
     * Get error response
     */
    private function getErrorResponse(string $message): array
    {
        return [
            'message' => $message,
            'related_cases' => [],
            'case_count' => 0,
            'response_time' => now()->toISOString(),
            'has_cases' => false,
            'error' => true
        ];
    }

    /**
     * Get conversation suggestions based on available data
     */
    public function getConversationSuggestions(): array
    {
        return [
            "What were the key civil rights cases in the 1950s?",
            "How did the Supreme Court's view of interstate commerce evolve?",
            "Tell me about famous dissenting opinions",
            "What cases established the principle of judicial review?",
            "How did the Court handle segregation cases?",
            "What are some landmark First Amendment cases?",
            "Explain the constitutional basis for federal vs state power",
            "What cases dealt with due process rights?"
        ];
    }
}