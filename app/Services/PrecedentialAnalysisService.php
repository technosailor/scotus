<?php

namespace App\Services;

use App\Models\SupremeCourtCase;
use App\Models\Opinion;
use App\Services\RedisOpinionService;
use App\Services\LocalLlmService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PrecedentialAnalysisService
{
    private const CACHE_PREFIX = 'precedential_analysis:';
    private const CACHE_TTL = 86400; // 24 hours

    public function __construct(
        private RedisOpinionService $redisService,
        private LocalLlmService $llmService
    ) {}

    /**
     * Analyze case citations to determine precedential importance
     */
    public function analyzePrecedentialImportance(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'precedential_importance';
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $citationData = $this->extractCaseReferences();
        $precedentialRankings = $this->calculatePrecedentialScores($citationData);
        $majorCases = $this->identifyMajorPrecedentialCases($precedentialRankings);

        $result = [
            'citation_network' => $citationData,
            'precedential_rankings' => $precedentialRankings,
            'major_precedential_cases' => $majorCases,
            'analysis_metadata' => [
                'total_cases_analyzed' => count($citationData),
                'citation_relationships' => $this->countCitationRelationships($citationData),
                'generated_at' => now()->toISOString(),
            ]
        ];

        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Extract case references from opinion text using LLM and pattern matching
     */
    private function extractCaseReferences(): array
    {
        $allOpinions = $this->getAllOpinionsWithText();
        $citationNetwork = [];
        
        Log::info("Starting case reference extraction from {$allOpinions->count()} opinions");

        foreach ($allOpinions as $index => $opinion) {
            if ($index % 100 === 0) {
                Log::info("Processed {$index} opinions for case references");
            }

            $caseName = $opinion['case_name'] ?? 'Unknown Case';
            
            if (!isset($citationNetwork[$caseName])) {
                $citationNetwork[$caseName] = [
                    'case_id' => $opinion['case_id'] ?? null,
                    'decision_date' => $opinion['decision_date'] ?? null,
                    'references_to' => [],
                    'referenced_by' => [],
                    'total_references_made' => 0,
                    'total_times_cited' => 0,
                ];
            }

            // Extract case references from opinion text
            $references = $this->findCaseReferencesInText($opinion['opinion_text'] ?? '');
            
            foreach ($references as $referencedCase) {
                if ($referencedCase !== $caseName) {
                    $citationNetwork[$caseName]['references_to'][] = $referencedCase;
                    $citationNetwork[$caseName]['total_references_made']++;
                    
                    // Initialize referenced case if not exists
                    if (!isset($citationNetwork[$referencedCase])) {
                        $citationNetwork[$referencedCase] = [
                            'case_id' => null,
                            'decision_date' => null,
                            'references_to' => [],
                            'referenced_by' => [],
                            'total_references_made' => 0,
                            'total_times_cited' => 0,
                        ];
                    }
                    
                    $citationNetwork[$referencedCase]['referenced_by'][] = $caseName;
                    $citationNetwork[$referencedCase]['total_times_cited']++;
                }
            }
        }

        return $citationNetwork;
    }

    /**
     * Find case references in opinion text using pattern matching and LLM
     */
    private function findCaseReferencesInText(string $text): array
    {
        $references = [];
        
        // Pattern 1: Standard case citation format "Case v. Case"
        preg_match_all('/\b([A-Z][a-zA-Z\s&\.\']+(?:v\.?\s+[A-Z][a-zA-Z\s&\.\']+)+)\b/', $text, $matches);
        if (!empty($matches[1])) {
            $references = array_merge($references, $matches[1]);
        }

        // Pattern 2: Common case name patterns
        preg_match_all('/\b(Brown v\.?\s+Board|Roe v\.?\s+Wade|Miranda v\.?\s+Arizona|Marbury v\.?\s+Madison|Plessy v\.?\s+Ferguson)\b/i', $text, $matches);
        if (!empty($matches[1])) {
            $references = array_merge($references, $matches[1]);
        }

        // Pattern 3: "In [Case Name]" references
        preg_match_all('/\bIn\s+([A-Z][a-zA-Z\s&\.\']+(?:v\.?\s+[A-Z][a-zA-Z\s&\.\']+)+)\b/', $text, $matches);
        if (!empty($matches[1])) {
            $references = array_merge($references, $matches[1]);
        }

        // Clean and normalize case names
        $references = array_map(function($ref) {
            return $this->normalizeCaseName(trim($ref));
        }, $references);

        // Remove duplicates and filter valid cases
        return array_unique(array_filter($references, function($ref) {
            return strlen($ref) > 10 && str_contains($ref, 'v');
        }));
    }

    /**
     * Normalize case names for consistent matching
     */
    private function normalizeCaseName(string $caseName): string
    {
        // Standardize "v." vs "v"
        $caseName = preg_replace('/\s+v\.?\s+/', ' v. ', $caseName);
        
        // Remove extra whitespace
        $caseName = preg_replace('/\s+/', ' ', $caseName);
        
        // Trim and title case
        return trim($caseName);
    }

    /**
     * Calculate precedential scores based on citation frequency and other factors
     */
    private function calculatePrecedentialScores(array $citationNetwork): array
    {
        $scores = [];
        
        foreach ($citationNetwork as $caseName => $data) {
            $timesCited = $data['total_times_cited'];
            $caseAge = $this->calculateCaseAge($data['decision_date']);
            $referencesMade = $data['total_references_made'];
            
            // Base score from citation frequency
            $citationScore = $timesCited * 10;
            
            // Age factor - older landmark cases get bonus
            $ageFactor = $caseAge > 50 ? 1.5 : ($caseAge > 20 ? 1.2 : 1.0);
            
            // Authority factor - cases that cite many others show legal scholarship
            $authorityFactor = min(2.0, 1 + ($referencesMade * 0.01));
            
            // Calculate final precedential score
            $precedentialScore = $citationScore * $ageFactor * $authorityFactor;
            
            $scores[$caseName] = [
                'precedential_score' => round($precedentialScore, 2),
                'times_cited' => $timesCited,
                'references_made' => $referencesMade,
                'case_age_years' => $caseAge,
                'decision_date' => $data['decision_date'],
                'citation_density' => $timesCited > 0 ? round($timesCited / max(1, $caseAge), 3) : 0,
                'authority_factor' => round($authorityFactor, 2),
            ];
        }
        
        // Sort by precedential score
        uasort($scores, function($a, $b) {
            return $b['precedential_score'] <=> $a['precedential_score'];
        });
        
        return $scores;
    }

    /**
     * Identify major precedential cases based on multiple criteria
     */
    private function identifyMajorPrecedentialCases(array $precedentialRankings): array
    {
        $majorCases = [];
        $threshold = $this->calculatePrecedentialThreshold($precedentialRankings);
        
        foreach ($precedentialRankings as $caseName => $data) {
            if ($data['precedential_score'] >= $threshold || $data['times_cited'] >= 10) {
                $majorCases[$caseName] = array_merge($data, [
                    'classification' => $this->classifyPrecedentialImportance($data),
                    'legal_significance' => $this->assessLegalSignificance($caseName, $data),
                ]);
            }
        }
        
        return array_slice($majorCases, 0, 50, true); // Top 50 most important cases
    }

    /**
     * Analyze Justice language patterns in opinions and dissents
     */
    public function analyzeJusticeLanguagePatterns(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'justice_language_patterns';
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $justicePatterns = [];
        $allOpinions = $this->getAllOpinionsWithText();
        
        Log::info("Starting Justice language pattern analysis");

        foreach ($allOpinions as $opinion) {
            $justiceName = $opinion['justice_name'] ?? 'Unknown Justice';
            $opinionType = $opinion['opinion_type'] ?? 'unknown';
            $opinionText = $opinion['opinion_text'] ?? '';
            
            if (!isset($justicePatterns[$justiceName])) {
                $justicePatterns[$justiceName] = [
                    'total_opinions' => 0,
                    'opinion_types' => [],
                    'language_metrics' => [
                        'avg_word_count' => 0,
                        'total_words' => 0,
                        'complexity_score' => 0,
                        'formality_score' => 0,
                    ],
                    'common_phrases' => [],
                    'legal_concepts' => [],
                    'writing_style' => [],
                ];
            }
            
            // Count opinion types
            if (!isset($justicePatterns[$justiceName]['opinion_types'][$opinionType])) {
                $justicePatterns[$justiceName]['opinion_types'][$opinionType] = 0;
            }
            $justicePatterns[$justiceName]['opinion_types'][$opinionType]++;
            $justicePatterns[$justiceName]['total_opinions']++;
            
            // Analyze language patterns
            $languageMetrics = $this->analyzeTextLanguagePatterns($opinionText);
            $this->updateJusticeLanguageMetrics($justicePatterns[$justiceName], $languageMetrics);
        }
        
        // Calculate final averages and patterns
        foreach ($justicePatterns as $justice => &$patterns) {
            if ($patterns['total_opinions'] > 0) {
                $patterns['language_metrics']['avg_word_count'] = round(
                    $patterns['language_metrics']['total_words'] / $patterns['total_opinions']
                );
            }
        }
        
        $result = [
            'justice_patterns' => $justicePatterns,
            'comparative_analysis' => $this->compareJusticeLanguageStyles($justicePatterns),
            'generated_at' => now()->toISOString(),
        ];
        
        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Extract major topics from cases using LLM analysis
     */
    public function extractMajorTopics(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'major_topics';
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $topicData = [];
        $allCases = SupremeCourtCase::with('term')->get();
        
        Log::info("Starting major topics extraction from {$allCases->count()} cases");

        foreach ($allCases as $case) {
            $topics = $this->extractTopicsFromCase($case);
            
            foreach ($topics as $topic) {
                if (!isset($topicData[$topic])) {
                    $topicData[$topic] = [
                        'cases' => [],
                        'frequency' => 0,
                        'time_periods' => [],
                        'associated_justices' => [],
                    ];
                }
                
                $topicData[$topic]['cases'][] = [
                    'id' => $case->id,
                    'name' => $case->case_name,
                    'date' => $case->decision_date,
                    'term' => $case->term?->year,
                ];
                
                $topicData[$topic]['frequency']++;
                
                // Track time periods
                $decade = floor($case->term?->year / 10) * 10;
                if (!isset($topicData[$topic]['time_periods'][$decade])) {
                    $topicData[$topic]['time_periods'][$decade] = 0;
                }
                $topicData[$topic]['time_periods'][$decade]++;
            }
        }
        
        $result = [
            'topics' => $topicData,
            'topic_trends' => $this->analyzeTopicTrends($topicData),
            'generated_at' => now()->toISOString(),
        ];
        
        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    // Helper methods
    
    private function getAllOpinionsWithText(): Collection
    {
        $allOpinions = collect();
        
        foreach (['dissent', 'concurrence', 'majority', 'plurality'] as $type) {
            $opinions = $this->redisService->getOpinionsByType($type);
            $allOpinions = $allOpinions->concat($opinions);
        }
        
        return $allOpinions->filter(function($opinion) {
            return !empty($opinion['opinion_text']);
        });
    }

    private function calculateCaseAge($decisionDate): int
    {
        if (!$decisionDate) return 0;
        
        try {
            $date = is_string($decisionDate) ? new \DateTime($decisionDate) : $decisionDate;
            return now()->year - $date->format('Y');
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countCitationRelationships(array $citationNetwork): int
    {
        $count = 0;
        foreach ($citationNetwork as $data) {
            $count += count($data['references_to']);
        }
        return $count;
    }

    private function calculatePrecedentialThreshold(array $rankings): float
    {
        $scores = array_column($rankings, 'precedential_score');
        return count($scores) > 0 ? array_sum($scores) / count($scores) * 1.5 : 0;
    }

    private function classifyPrecedentialImportance(array $data): string
    {
        $score = $data['precedential_score'];
        $timesCited = $data['times_cited'];
        
        if ($score > 1000 || $timesCited > 50) return 'Landmark';
        if ($score > 500 || $timesCited > 25) return 'Major';
        if ($score > 100 || $timesCited > 10) return 'Significant';
        return 'Notable';
    }

    private function assessLegalSignificance(string $caseName, array $data): string
    {
        $knownLandmarks = [
            'Brown v. Board of Education' => 'Civil Rights - Racial Equality',
            'Roe v. Wade' => 'Privacy Rights - Reproductive Freedom',
            'Miranda v. Arizona' => 'Criminal Procedure - Self-Incrimination',
            'Marbury v. Madison' => 'Constitutional Law - Judicial Review',
            'Plessy v. Ferguson' => 'Civil Rights - Separate but Equal',
        ];
        
        foreach ($knownLandmarks as $landmark => $significance) {
            if (str_contains($caseName, explode(' v. ', $landmark)[0])) {
                return $significance;
            }
        }
        
        return 'Constitutional Interpretation';
    }

    private function analyzeTextLanguagePatterns(string $text): array
    {
        $wordCount = str_word_count($text);
        $sentences = preg_split('/[.!?]+/', $text);
        $avgSentenceLength = $wordCount / max(1, count($sentences));
        
        // Simple complexity indicators
        $complexWords = preg_match_all('/\b\w{8,}\b/', $text);
        $complexityScore = $wordCount > 0 ? ($complexWords / $wordCount) * 100 : 0;
        
        // Legal formality indicators
        $formalPhrases = ['constitutional', 'precedent', 'jurisdiction', 'appellant', 'respondent'];
        $formalityScore = 0;
        foreach ($formalPhrases as $phrase) {
            $formalityScore += substr_count(strtolower($text), $phrase);
        }
        
        return [
            'word_count' => $wordCount,
            'avg_sentence_length' => round($avgSentenceLength, 1),
            'complexity_score' => round($complexityScore, 2),
            'formality_score' => $formalityScore,
        ];
    }

    private function updateJusticeLanguageMetrics(array &$patterns, array $metrics): void
    {
        $patterns['language_metrics']['total_words'] += $metrics['word_count'];
        $patterns['language_metrics']['complexity_score'] += $metrics['complexity_score'];
        $patterns['language_metrics']['formality_score'] += $metrics['formality_score'];
    }

    private function compareJusticeLanguageStyles(array $justicePatterns): array
    {
        $comparison = [
            'most_prolific' => [],
            'most_complex_language' => [],
            'most_formal_language' => [],
            'dissent_specialists' => [],
        ];
        
        foreach ($justicePatterns as $justice => $patterns) {
            if ($patterns['total_opinions'] < 5) continue; // Minimum threshold
            
            // Most prolific
            $comparison['most_prolific'][$justice] = $patterns['total_opinions'];
            
            // Language complexity
            $avgComplexity = $patterns['total_opinions'] > 0 ? 
                $patterns['language_metrics']['complexity_score'] / $patterns['total_opinions'] : 0;
            $comparison['most_complex_language'][$justice] = round($avgComplexity, 2);
            
            // Formality
            $avgFormality = $patterns['total_opinions'] > 0 ? 
                $patterns['language_metrics']['formality_score'] / $patterns['total_opinions'] : 0;
            $comparison['most_formal_language'][$justice] = round($avgFormality, 2);
            
            // Dissent percentage
            $dissentCount = $patterns['opinion_types']['dissent'] ?? 0;
            $dissentRate = $patterns['total_opinions'] > 0 ? 
                ($dissentCount / $patterns['total_opinions']) * 100 : 0;
            $comparison['dissent_specialists'][$justice] = round($dissentRate, 1);
        }
        
        // Sort each category
        arsort($comparison['most_prolific']);
        arsort($comparison['most_complex_language']);
        arsort($comparison['most_formal_language']);
        arsort($comparison['dissent_specialists']);
        
        // Take top 10 in each category
        foreach ($comparison as &$category) {
            $category = array_slice($category, 0, 10, true);
        }
        
        return $comparison;
    }

    private function extractTopicsFromCase($case): array
    {
        // Basic topic extraction based on case summary and facts
        $text = implode(' ', [
            $case->case_name ?? '',
            $case->summary ?? '',
            is_array($case->facts) ? implode(' ', $case->facts) : ($case->facts ?? ''),
            is_array($case->question) ? implode(' ', $case->question) : ($case->question ?? ''),
        ]);
        
        $topics = [];
        
        // Define major legal topic categories
        $topicPatterns = [
            'Civil Rights' => ['civil rights', 'discrimination', 'equality', 'segregation', 'voting rights'],
            'Constitutional Law' => ['constitutional', 'amendment', 'due process', 'equal protection'],
            'Criminal Procedure' => ['criminal', 'search', 'seizure', 'miranda', 'confession'],
            'First Amendment' => ['speech', 'religion', 'press', 'assembly', 'petition'],
            'Commerce Clause' => ['commerce', 'interstate', 'regulation', 'trade'],
            'Privacy Rights' => ['privacy', 'reproductive', 'contraception', 'abortion'],
            'Labor Law' => ['labor', 'union', 'employment', 'worker'],
            'Corporate Law' => ['corporation', 'business', 'contract', 'antitrust'],
            'Immigration' => ['immigration', 'alien', 'deportation', 'citizenship'],
            'Environmental Law' => ['environment', 'pollution', 'conservation'],
        ];
        
        $textLower = strtolower($text);
        
        foreach ($topicPatterns as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($textLower, $keyword)) {
                    $topics[] = $topic;
                    break; // Only add topic once per case
                }
            }
        }
        
        return array_unique($topics);
    }

    private function analyzeTopicTrends(array $topicData): array
    {
        $trends = [];
        
        foreach ($topicData as $topic => $data) {
            $timePeriods = $data['time_periods'];
            ksort($timePeriods);
            
            $trends[$topic] = [
                'peak_decade' => array_search(max($timePeriods), $timePeriods),
                'total_frequency' => $data['frequency'],
                'decade_distribution' => $timePeriods,
                'trend_direction' => $this->calculateTrendDirection($timePeriods),
            ];
        }
        
        return $trends;
    }

    private function calculateTrendDirection(array $timePeriods): string
    {
        if (count($timePeriods) < 2) return 'stable';
        
        $decades = array_keys($timePeriods);
        $counts = array_values($timePeriods);
        
        $recentDecades = array_slice($counts, -3); // Last 3 decades
        $earlierDecades = array_slice($counts, 0, -3);
        
        if (empty($earlierDecades)) return 'stable';
        
        $recentAvg = array_sum($recentDecades) / count($recentDecades);
        $earlierAvg = array_sum($earlierDecades) / count($earlierDecades);
        
        if ($recentAvg > $earlierAvg * 1.2) return 'increasing';
        if ($recentAvg < $earlierAvg * 0.8) return 'decreasing';
        return 'stable';
    }
}