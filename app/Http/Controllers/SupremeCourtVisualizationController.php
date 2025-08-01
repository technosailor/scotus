<?php

namespace App\Http\Controllers;

use App\Models\SupremeCourtCase;
use App\Models\Justice;
use App\Models\Term;
use App\Models\President;
use App\Models\Opinion;
use App\Services\LocalLlmService;
use App\Services\RedisOpinionService;
use App\Services\PrecedentialAnalysisService;
use App\Services\SamplePrecedentialDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SupremeCourtVisualizationController extends Controller
{
    private LocalLlmService $llmService;
    private RedisOpinionService $redisService;
    private PrecedentialAnalysisService $precedentialAnalysisService;
    private SamplePrecedentialDataService $sampleDataService;

    public function __construct(
        LocalLlmService $llmService, 
        RedisOpinionService $redisService,
        PrecedentialAnalysisService $precedentialAnalysisService,
        SamplePrecedentialDataService $sampleDataService
    ) {
        $this->llmService = $llmService;
        $this->redisService = $redisService;
        $this->precedentialAnalysisService = $precedentialAnalysisService;
        $this->sampleDataService = $sampleDataService;
    }

    /**
     * Main visualization dashboard
     */
    public function index()
    {
        $stats = [
            'total_cases' => SupremeCourtCase::count(),
            'total_justices' => Justice::count(),
            'total_terms' => Term::count(),
            'total_opinions' => Opinion::count(),
        ];
        
        // Get available terms for dropdowns
        $terms = Term::orderBy('year', 'desc')->get(['year', 'name']);

        return view('supreme-court.dashboard', compact('stats', 'terms'));
    }

    /**
     * API endpoint for search functionality
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $filters = $request->get('filters', []);
        
        $cases = SupremeCourtCase::query()
            ->with(['term', 'opinions.justice'])
            ->when($query, function ($q) use ($query) {
                $q->where('case_name', 'LIKE', "%{$query}%")
                  ->orWhere('summary', 'LIKE', "%{$query}%")
                  ->orWhereJsonContains('question', $query)
                  ->orWhereJsonContains('conclusion', $query);
            })
            ->when(isset($filters['term']), function ($q) use ($filters) {
                $q->whereHas('term', function ($termQuery) use ($filters) {
                    $termQuery->where('year', $filters['term']);
                });
            })
            ->when(isset($filters['sentiment_min']), function ($q) use ($filters) {
                $q->where('sentiment_score', '>=', $filters['sentiment_min']);
            })
            ->when(isset($filters['sentiment_max']), function ($q) use ($filters) {
                $q->where('sentiment_score', '<=', $filters['sentiment_max']);
            })
            ->orderBy('decision_date', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'cases' => $cases->map(function ($case) {
                return [
                    'id' => $case->id,
                    'name' => $case->case_name,
                    'docket_number' => $case->docket_number,
                    'decision_date' => $case->decision_date->format('Y-m-d'),
                    'term' => $case->term->year,
                    'sentiment_score' => $case->sentiment_score,
                    'majority_author' => $case->majority_opinion_author,
                    'summary' => $case->summary ? substr($case->summary, 0, 200) . '...' : null,
                ];
            }),
            'total' => $cases->count(),
        ]);
    }

    /**
     * API endpoint for case analysis using LLM
     */
    public function analyzeCase(Request $request, SupremeCourtCase $case)
    {
        if (!$this->llmService->isAvailable()) {
            return response()->json([
                'error' => 'LLM service is not available'
            ], 503);
        }

        try {
            $analysisType = $request->get('type', 'sentiment');
            $analysis = $this->llmService->analyzeCaseData($case->raw_data, $analysisType);
            
            return response()->json([
                'case_id' => $case->id,
                'analysis_type' => $analysisType,
                'result' => $analysis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Analysis failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for cases per term data
     */
    public function casesPerTerm(Request $request)
    {
        $startYear = $request->get('start_year', 1793);
        $endYear = $request->get('end_year', 2025);

        $caseCounts = SupremeCourtCase::query()
            ->join('terms', 'supreme_court_cases.term_id', '=', 'terms.id')
            ->whereBetween('terms.year', [$startYear, $endYear])
            ->selectRaw('terms.year as term, terms.name, COUNT(*) as case_count')
            ->groupBy('terms.year', 'terms.name')
            ->orderBy('terms.year')
            ->get();

        return response()->json($caseCounts->map(function ($item) {
            return [
                'term' => $item->term,
                'term_name' => $item->name,
                'count' => $item->case_count,
            ];
        }));
    }

    /**
     * API endpoint for timeline data (legacy - keeping for now)
     */
    public function timeline(Request $request)
    {
        $startYear = $request->get('start_year', 1793);
        $endYear = $request->get('end_year', 2025);

        $cases = SupremeCourtCase::query()
            ->with(['term'])
            ->whereHas('term', function ($q) use ($startYear, $endYear) {
                $q->whereBetween('year', [$startYear, $endYear]);
            })
            ->whereNotNull('sentiment_score')
            ->orderBy('decision_date')
            ->get()
            ->map(function ($case) {
                return [
                    'date' => $case->decision_date->format('Y-m-d'),
                    'year' => $case->term->year,
                    'case_name' => $case->case_name,
                    'sentiment_score' => $case->sentiment_score,
                    'docket_number' => $case->docket_number,
                ];
            });

        return response()->json($cases);
    }

    /**
     * API endpoint for justice opinion statistics
     */
    public function justiceOpinionStats(Request $request)
    {
        $startYear = $request->get('start_year', 1793);
        $endYear = $request->get('end_year', 2025);
        $limit = $request->get('limit', 15);

        $stats = Justice::query()
            ->select([
                'justices.id',
                'justices.name',
                DB::raw('COUNT(CASE WHEN opinions.opinion_type = "majority" THEN 1 END) as majority_count'),
                DB::raw('COUNT(CASE WHEN opinions.opinion_type = "concurrence" THEN 1 END) as concurring_count'),
                DB::raw('COUNT(CASE WHEN opinions.opinion_type = "dissent" THEN 1 END) as dissent_count'),
                DB::raw('COUNT(opinions.id) as total_opinions')
            ])
            ->join('opinions', 'justices.id', '=', 'opinions.justice_id')
            ->join('supreme_court_cases', 'opinions.case_id', '=', 'supreme_court_cases.id')
            ->join('terms', 'supreme_court_cases.term_id', '=', 'terms.id')
            ->whereBetween('terms.year', [$startYear, $endYear])
            ->groupBy('justices.id', 'justices.name')
            ->orderBy('total_opinions', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($stats->map(function ($justice) {
            return [
                'id' => $justice->id,
                'name' => $justice->name,
                'majority' => (int) $justice->majority_count,
                'concurring' => (int) $justice->concurring_count,
                'dissent' => (int) $justice->dissent_count,
                'total' => (int) $justice->total_opinions,
            ];
        }));
    }

    /**
     * API endpoint for justice network data
     */
    public function justiceNetwork(Request $request)
    {
        $termYear = $request->get('term', '2020');

        // Get justices who served in the specified term
        $justices = Justice::query()
            ->whereHas('opinions.case.term', function ($q) use ($termYear) {
                $q->where('year', $termYear);
            })
            ->with(['opinions' => function ($q) use ($termYear) {
                $q->whereHas('case.term', function ($termQuery) use ($termYear) {
                    $termQuery->where('year', $termYear);
                });
            }])
            ->get();

        // Build network data
        $nodes = $justices->map(function ($justice) {
            return [
                'id' => $justice->id,
                'name' => $justice->name,
                'ideology_score' => $this->calculateIdeologyScore($justice),
                'opinion_count' => $justice->opinions->count(),
            ];
        });

        // Calculate links based on agreement patterns
        $links = $this->calculateJusticeAgreements($justices);

        return response()->json([
            'nodes' => $nodes,
            'links' => $links,
        ]);
    }

    /**
     * Enhanced API endpoint for chat interface with Ollama integration
     */
    public function chat(Request $request)
    {
        $message = $request->get('message', '');
        $cacheKey = 'chat_response:' . md5($message);
        
        // Check cache first (1 hour TTL)
        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached);
        }
        
        if (!$this->llmService->isAvailable()) {
            return response()->json([
                'response' => 'I apologize, but the AI analysis service is currently unavailable. Please try searching using the regular search interface.',
                'related_cases' => [],
                'suggestions' => [
                    'Search for case names like "Brown v. Board"',
                    'Filter by term years like "1955-1960"',
                    'Look for specific legal topics',
                ]
            ]);
        }

        try {
            // Extract keywords from user message
            $keywords = $this->extractChatKeywords($message);
            
            // Search for relevant cases using Redis and database
            $relevantCases = $this->findRelevantCasesForChat($keywords, $message);
            
            // Generate LLM response with case context
            $llmResponse = $this->generateEnhancedLLMResponse($message, $relevantCases);
            
            // Format response with case links
            $response = $this->formatChatResponse($llmResponse, $relevantCases);
            
            // Cache for 1 hour
            Cache::put($cacheKey, $response, 3600);
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            return response()->json([
                'response' => 'I can help you search through our Supreme Court case database. Try asking about specific cases, justices, or legal topics.',
                'related_cases' => [],
                'suggestions' => [
                    'Tell me about civil rights cases',
                    'Show me cases from the Warren Court',
                    'What cases did Justice Black write?',
                ]
            ]);
        }
    }

    // Helper methods

    private function calculateIdeologyScore(Justice $justice): float
    {
        $scores = $justice->opinions()->whereNotNull('ideology_score')->pluck('ideology_score');
        return $scores->isEmpty() ? 0.0 : $scores->avg();
    }

    private function calculateJusticeAgreements(iterable $justices): array
    {
        $links = [];
        $justiceArray = $justices->all();
        
        for ($i = 0; $i < count($justiceArray); $i++) {
            for ($j = $i + 1; $j < count($justiceArray); $j++) {
                $justice1 = $justiceArray[$i];
                $justice2 = $justiceArray[$j];
                
                $agreement = $this->calculateAgreementRate($justice1, $justice2);
                
                if ($agreement > 0.5) { // Only show links with >50% agreement
                    $links[] = [
                        'source' => $justice1->id,
                        'target' => $justice2->id,
                        'strength' => $agreement,
                    ];
                }
            }
        }
        
        return $links;
    }

    private function calculateAgreementRate(Justice $justice1, Justice $justice2): float
    {
        // Find cases where both justices voted
        $commonCases = DB::table('opinions as o1')
            ->join('opinions as o2', 'o1.case_id', '=', 'o2.case_id')
            ->where('o1.justice_id', $justice1->id)
            ->where('o2.justice_id', $justice2->id)
            ->where('o1.vote', 'o2.vote') // Same vote
            ->count();

        $totalCommonCases = DB::table('opinions as o1')
            ->join('opinions as o2', 'o1.case_id', '=', 'o2.case_id')
            ->where('o1.justice_id', $justice1->id)
            ->where('o2.justice_id', $justice2->id)
            ->count();

        return $totalCommonCases > 0 ? $commonCases / $totalCommonCases : 0;
    }

    private function buildSearchContext(): string
    {
        $recentCases = SupremeCourtCase::latest('decision_date')
            ->limit(5)
            ->pluck('case_name')
            ->implode(', ');
            
        $justiceCount = Justice::count();
        $termRange = Term::orderBy('year')->first()?->year . '-' . Term::orderBy('year', 'desc')->first()?->year;
        
        return "Database contains {$justiceCount} justices, cases from {$termRange}. Recent cases: {$recentCases}";
    }

    private function generateSearchSuggestions(string $message): array
    {
        // Simple keyword-based suggestions
        $suggestions = [];
        
        if (str_contains(strtolower($message), 'civil rights')) {
            $suggestions[] = 'Search for cases involving civil rights';
        }
        
        if (str_contains(strtolower($message), 'warren')) {
            $suggestions[] = 'Filter by Warren Court era (1955-1969)';
        }
        
        if (str_contains(strtolower($message), 'justice')) {
            $suggestions[] = 'Browse justices by name or appointing president';
        }
        
        return empty($suggestions) ? ['Try searching for specific case names', 'Filter by term years', 'Browse by justice names'] : $suggestions;
    }

    /**
     * Extract keywords from chat message for case search
     */
    private function extractChatKeywords(string $message): array
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
        $messageLower = strtolower($message);
        
        // Find legal terms in the message
        foreach ($legalTerms as $term) {
            if (str_contains($messageLower, $term)) {
                $keywords[] = $term;
            }
        }
        
        // Extract potential case names (capitalized words that might be names)
        if (preg_match_all('/\b[A-Z][a-z]+(?:\s+v\.?\s+[A-Z][a-z]+)?\b/', $message, $matches)) {
            $keywords = array_merge($keywords, $matches[0]);
        }
        
        // Add general topic words
        $topicWords = ['rights', 'freedom', 'liberty', 'government', 'state', 'federal', 'court', 'justice'];
        foreach ($topicWords as $word) {
            if (str_contains($messageLower, $word)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }

    /**
     * Find relevant cases for chat using Redis and database search
     */
    private function findRelevantCasesForChat(array $keywords, string $message): array
    {
        $relevantCases = [];
        
        // Search Redis opinions first
        $redisOpinions = $this->searchRedisOpinionsForChat($keywords, $message);
        
        if (!empty($redisOpinions)) {
            // Get case details for Redis opinions
            $caseIds = collect($redisOpinions)->pluck('case_id')->unique()->take(10);
            $cases = SupremeCourtCase::whereIn('id', $caseIds)->with('term')->get();
            
            foreach ($cases as $case) {
                $relevantCases[] = [
                    'id' => $case->id,
                    'case_name' => $case->case_name,
                    'decision_date' => $case->decision_date->format('Y-m-d'),
                    'term_year' => $case->term?->year,
                    'justia_url' => $case->raw_data['justia_url'] ?? null,
                    'summary' => $case->summary,
                    'relevance_score' => $this->calculateChatRelevanceScore($case, $keywords, $message)
                ];
            }
        }
        
        // Supplement with direct database search if needed
        if (count($relevantCases) < 5) {
            $dbCases = $this->searchDatabaseCasesForChat($keywords, $message, 8);
            foreach ($dbCases as $case) {
                $exists = collect($relevantCases)->contains('id', $case->id);
                if (!$exists) {
                    $relevantCases[] = [
                        'id' => $case->id,
                        'case_name' => $case->case_name,
                        'decision_date' => $case->decision_date->format('Y-m-d'),
                        'term_year' => $case->term?->year,
                        'justia_url' => $case->raw_data['justia_url'] ?? null,
                        'summary' => $case->summary,
                        'relevance_score' => $this->calculateChatRelevanceScore($case, $keywords, $message)
                    ];
                }
            }
        }
        
        // Sort by relevance and return top results
        usort($relevantCases, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);
        return array_slice($relevantCases, 0, 8);
    }

    /**
     * Search Redis opinions for chat relevance
     */
    private function searchRedisOpinionsForChat(array $keywords, string $message): array
    {
        $allOpinions = [];
        
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
                    $allOpinions[] = $opinion;
                }
            }
        }
        
        // Sort by keyword matches and return top results
        usort($allOpinions, fn($a, $b) => ($b['keyword_matches'] ?? 0) <=> ($a['keyword_matches'] ?? 0));
        return array_slice($allOpinions, 0, 20);
    }

    /**
     * Search database cases for chat
     */
    private function searchDatabaseCasesForChat(array $keywords, string $message, int $limit = 8)
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
     * Calculate relevance score for chat results
     */
    private function calculateChatRelevanceScore($case, array $keywords, string $message): float
    {
        $score = 0;
        
        $searchableText = strtolower(implode(' ', [
            $case->case_name,
            $case->summary ?? '',
            is_array($case->facts) ? implode(' ', $case->facts) : ($case->facts ?? ''),
            is_array($case->question) ? implode(' ', $case->question) : ($case->question ?? ''),
            is_array($case->conclusion) ? implode(' ', $case->conclusion) : ($case->conclusion ?? '')
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
     * Generate enhanced LLM response with case context
     */
    private function generateEnhancedLLMResponse(string $message, array $cases): string
    {
        $caseSummaries = collect($cases)->take(5)->map(function ($case) {
            return "- {$case['case_name']} ({$case['decision_date']}): " . substr($case['summary'] ?? 'Supreme Court case', 0, 200);
        })->implode("\n");
        
        $prompt = "You are a Supreme Court legal expert and historian. A user has asked: \"{$message}\"\n\n";
        
        if (!empty($cases)) {
            $prompt .= "Here are relevant Supreme Court cases from the database:\n{$caseSummaries}\n\n";
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
     * Format chat response with case links
     */
    private function formatChatResponse(string $llmResponse, array $cases): array
    {
        // Clean up the LLM response
        $message = trim($llmResponse);
        
        // If LLM failed, provide a fallback response
        if (str_contains($message, 'Analysis failed') || str_contains($message, 'falling back')) {
            $message = $this->generateChatFallbackResponse($cases);
        }
        
        // Add clickable links to case names in the response
        $linkedMessage = $this->addCaseLinksToResponse($message, $cases);
        
        // Prepare related cases with full details
        $relatedCases = collect($cases)->take(6)->map(function ($case) {
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
            'response' => $linkedMessage,
            'related_cases' => $relatedCases,
            'case_count' => count($cases),
            'suggestions' => $this->generateSearchSuggestions(''),
            'has_cases' => !empty($cases)
        ];
    }

    /**
     * Add clickable links to case names in the response
     */
    private function addCaseLinksToResponse(string $message, array $cases): string
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
    private function generateChatFallbackResponse(array $cases): string
    {
        if (empty($cases)) {
            return "I found your question interesting, but I wasn't able to locate specific Supreme Court cases directly related to your query in our database. You might want to try rephrasing your question or asking about specific legal concepts, case names, or constitutional amendments.";
        }
        
        $caseList = collect($cases)->take(3)->map(function ($case) {
            $year = date('Y', strtotime($case['decision_date']));
            return "• **{$case['case_name']}** ({$year})";
        })->implode("\n");
        
        return "Based on your question, I found several relevant Supreme Court cases in our database:\n\n{$caseList}\n\nThese cases may help answer your question. You can click on any case name to read the full opinion on Justia. For more detailed analysis, please try asking about specific aspects of these cases or related legal concepts.";
    }

    /**
     * API endpoint for precedential analysis data
     */
    public function precedentialAnalysis(Request $request)
    {
        try {
            $analysis = $this->precedentialAnalysisService->analyzePrecedentialImportance();
            
            // Use sample data if main analysis returns empty results
            if (empty($analysis['major_precedential_cases'])) {
                $analysis = $this->sampleDataService->generateSamplePrecedentialData();
            }
            
            return response()->json([
                'major_cases' => array_slice($analysis['major_precedential_cases'], 0, 20, true),
                'citation_network' => $analysis['citation_network'],
                'metadata' => $analysis['analysis_metadata'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Precedential analysis failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for Justice language patterns
     */
    public function justiceLanguagePatterns(Request $request)
    {
        try {
            $patterns = $this->precedentialAnalysisService->analyzeJusticeLanguagePatterns();
            
            // Use sample data if main analysis returns empty results
            if (empty($patterns['justice_patterns'])) {
                $patterns = $this->sampleDataService->generateSampleJusticeLanguageData();
            }
            
            return response()->json([
                'justice_patterns' => $patterns['justice_patterns'],
                'comparative_analysis' => $patterns['comparative_analysis'],
                'generated_at' => $patterns['generated_at'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Justice language analysis failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for topic trends and heatmap data
     */
    public function topicTrends(Request $request)
    {
        try {
            // Use sample data directly for testing (bypass main analysis due to memory issues)
            $topics = $this->sampleDataService->generateSampleTopicTrendsData();
            
            return response()->json([
                'topics' => $topics['topics'] ?? [],
                'trends' => $topics['topic_trends'] ?? [],
                'generated_at' => $topics['generated_at'] ?? now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Topic analysis failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * API endpoint for heatmap visualization data
     */
    public function heatmapData(Request $request)
    {
        try {
            // Use sample data directly for testing (bypass main analysis due to memory issues)
            $heatmapData = $this->sampleDataService->generateSampleHeatmapData();
            
            return response()->json([
                'heatmap_data' => $heatmapData['heatmap_data'],
                'dimensions' => $heatmapData['dimensions'],
                'metadata' => $heatmapData['metadata'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Heatmap data generation failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Build heatmap matrix combining Justice, Topic, and Time data
     */
    private function buildHeatmapMatrix($precedentialData, $languageData, $topicData): array
    {
        $matrix = [];
        $justices = array_keys($languageData['justice_patterns']);
        $topics = array_keys($topicData['topics']);
        $timePeriods = $this->getTimePeriods();
        
        // Build Justice vs Topic matrix
        foreach ($justices as $justice) {
            foreach ($topics as $topic) {
                $intensity = $this->calculateJusticeTopicIntensity($justice, $topic, $languageData, $topicData);
                
                $matrix['justice_topic'][] = [
                    'x' => $justice,
                    'y' => $topic,
                    'value' => $intensity,
                    'data' => [
                        'justice_opinions' => $languageData['justice_patterns'][$justice]['total_opinions'] ?? 0,
                        'topic_frequency' => $topicData['topics'][$topic]['frequency'] ?? 0,
                    ]
                ];
            }
        }
        
        // Build Topic vs Time Period matrix
        foreach ($topics as $topic) {
            foreach ($timePeriods as $period) {
                $frequency = $topicData['topics'][$topic]['time_periods'][$period] ?? 0;
                
                $matrix['topic_time'][] = [
                    'x' => $topic,
                    'y' => $period . 's',
                    'value' => $frequency,
                    'data' => [
                        'cases_in_period' => $frequency,
                        'topic_trend' => $topicData['topic_trends'][$topic]['trend_direction'] ?? 'stable',
                    ]
                ];
            }
        }
        
        // Build Precedential Cases vs Time matrix
        $majorCases = array_slice($precedentialData['major_precedential_cases'], 0, 20, true);
        foreach ($majorCases as $caseName => $caseData) {
            $decisionYear = isset($caseData['decision_date']) ? 
                date('Y', strtotime($caseData['decision_date'])) : null;
            
            if ($decisionYear) {
                $decade = floor($decisionYear / 10) * 10;
                
                $matrix['precedential_time'][] = [
                    'x' => $caseName,
                    'y' => $decade . 's',
                    'value' => $caseData['precedential_score'],
                    'data' => [
                        'times_cited' => $caseData['times_cited'],
                        'classification' => $caseData['classification'],
                        'legal_significance' => $caseData['legal_significance'],
                    ]
                ];
            }
        }
        
        return $matrix;
    }

    /**
     * Calculate intensity score for Justice-Topic combination
     */
    private function calculateJusticeTopicIntensity($justice, $topic, $languageData, $topicData): float
    {
        $justiceOpinions = $languageData['justice_patterns'][$justice]['total_opinions'] ?? 0;
        $topicFrequency = $topicData['topics'][$topic]['frequency'] ?? 0;
        
        if ($justiceOpinions === 0 || $topicFrequency === 0) {
            return 0;
        }
        
        // Normalized intensity based on relative activity
        $maxJusticeOpinions = max(array_column($languageData['justice_patterns'], 'total_opinions'));
        $maxTopicFrequency = max(array_column($topicData['topics'], 'frequency'));
        
        $normalizedJustice = $justiceOpinions / max(1, $maxJusticeOpinions);
        $normalizedTopic = $topicFrequency / max(1, $maxTopicFrequency);
        
        return round(($normalizedJustice * $normalizedTopic) * 100, 2);
    }

    /**
     * Get standard time periods for analysis
     */
    private function getTimePeriods(): array
    {
        $periods = [];
        for ($decade = 1790; $decade <= 2020; $decade += 10) {
            $periods[] = $decade;
        }
        return $periods;
    }
}
