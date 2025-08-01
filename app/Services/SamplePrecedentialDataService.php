<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SamplePrecedentialDataService
{
    private const CACHE_TTL = 3600;

    /**
     * Generate sample precedential analysis data for demonstration
     */
    public function generateSamplePrecedentialData(): array
    {
        $cacheKey = 'sample_precedential_data';
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $majorCases = [
            'Brown v. Board of Education' => [
                'precedential_score' => 2450.5,
                'times_cited' => 89,
                'references_made' => 12,
                'case_age_years' => 70,
                'decision_date' => '1954-05-17',
                'classification' => 'Landmark',
                'legal_significance' => 'Civil Rights - Racial Equality',
                'citation_density' => 1.271
            ],
            'Roe v. Wade' => [
                'precedential_score' => 2100.2,
                'times_cited' => 76,
                'references_made' => 18,
                'case_age_years' => 51,
                'decision_date' => '1973-01-22',
                'classification' => 'Landmark',
                'legal_significance' => 'Privacy Rights - Reproductive Freedom',
                'citation_density' => 1.490
            ],
            'Miranda v. Arizona' => [
                'precedential_score' => 1875.8,
                'times_cited' => 67,
                'references_made' => 9,
                'case_age_years' => 58,
                'decision_date' => '1966-06-13',
                'classification' => 'Landmark',
                'legal_significance' => 'Criminal Procedure - Self-Incrimination',
                'citation_density' => 1.155
            ],
            'Marbury v. Madison' => [
                'precedential_score' => 3200.0,
                'times_cited' => 124,
                'references_made' => 3,
                'case_age_years' => 221,
                'decision_date' => '1803-02-24',
                'classification' => 'Landmark',
                'legal_significance' => 'Constitutional Law - Judicial Review',
                'citation_density' => 0.561
            ],
            'Plessy v. Ferguson' => [
                'precedential_score' => 1650.3,
                'times_cited' => 45,
                'references_made' => 7,
                'case_age_years' => 128,
                'decision_date' => '1896-05-18',
                'classification' => 'Major',
                'legal_significance' => 'Civil Rights - Separate but Equal',
                'citation_density' => 0.352
            ],
            'Gideon v. Wainwright' => [
                'precedential_score' => 1420.7,
                'times_cited' => 52,
                'references_made' => 11,
                'case_age_years' => 61,
                'decision_date' => '1963-03-18',
                'classification' => 'Major',
                'legal_significance' => 'Criminal Procedure - Right to Counsel',
                'citation_density' => 0.852
            ],
            'New York Times Co. v. Sullivan' => [
                'precedential_score' => 1380.5,
                'times_cited' => 49,
                'references_made' => 14,
                'case_age_years' => 60,
                'decision_date' => '1964-03-09',
                'classification' => 'Major',
                'legal_significance' => 'First Amendment - Freedom of Press',
                'citation_density' => 0.817
            ],
            'Mapp v. Ohio' => [
                'precedential_score' => 1250.0,
                'times_cited' => 43,
                'references_made' => 8,
                'case_age_years' => 63,
                'decision_date' => '1961-06-19',
                'classification' => 'Major',
                'legal_significance' => 'Fourth Amendment - Search and Seizure',
                'citation_density' => 0.683
            ]
        ];

        $result = [
            'major_precedential_cases' => $majorCases,
            'citation_network' => $this->generateCitationNetwork($majorCases),
            'analysis_metadata' => [
                'total_cases_analyzed' => 8293,
                'citation_relationships' => 2847,
                'generated_at' => now()->toISOString(),
            ]
        ];

        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Generate sample justice language patterns
     */
    public function generateSampleJusticeLanguageData(): array
    {
        $cacheKey = 'sample_justice_language_data';
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $justicePatterns = [
            'Warren, Earl' => [
                'total_opinions' => 156,
                'opinion_types' => ['majority' => 89, 'concurrence' => 31, 'dissent' => 36],
                'language_metrics' => [
                    'avg_word_count' => 2840,
                    'complexity_score' => 15.2,
                    'formality_score' => 23.8
                ]
            ],
            'Scalia, Antonin' => [
                'total_opinions' => 287,
                'opinion_types' => ['majority' => 178, 'concurrence' => 43, 'dissent' => 66],
                'language_metrics' => [
                    'avg_word_count' => 3200,
                    'complexity_score' => 18.7,
                    'formality_score' => 28.9
                ]
            ],
            'Ginsburg, Ruth Bader' => [
                'total_opinions' => 198,
                'opinion_types' => ['majority' => 112, 'concurrence' => 29, 'dissent' => 57],
                'language_metrics' => [
                    'avg_word_count' => 2650,
                    'complexity_score' => 16.8,
                    'formality_score' => 26.4
                ]
            ],
            'Thomas, Clarence' => [
                'total_opinions' => 245,
                'opinion_types' => ['majority' => 134, 'concurrence' => 48, 'dissent' => 63],
                'language_metrics' => [
                    'avg_word_count' => 3100,
                    'complexity_score' => 17.5,
                    'formality_score' => 27.2
                ]
            ],
        ];

        $comparativeAnalysis = [
            'most_prolific' => [
                'Scalia, Antonin' => 287,
                'Thomas, Clarence' => 245,
                'Ginsburg, Ruth Bader' => 198,
                'Warren, Earl' => 156,
            ],
            'most_complex_language' => [
                'Scalia, Antonin' => 18.7,
                'Thomas, Clarence' => 17.5,
                'Ginsburg, Ruth Bader' => 16.8,
                'Warren, Earl' => 15.2,
            ],
            'most_formal_language' => [
                'Scalia, Antonin' => 28.9,
                'Thomas, Clarence' => 27.2,
                'Ginsburg, Ruth Bader' => 26.4,
                'Warren, Earl' => 23.8,
            ],
            'dissent_specialists' => [
                'Thomas, Clarence' => 25.7,
                'Warren, Earl' => 23.1,
                'Ginsburg, Ruth Bader' => 28.8,
                'Scalia, Antonin' => 23.0,
            ]
        ];

        $result = [
            'justice_patterns' => $justicePatterns,
            'comparative_analysis' => $comparativeAnalysis,
            'generated_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Generate sample topic trends data
     */
    public function generateSampleTopicTrendsData(): array
    {
        $cacheKey = 'sample_topic_trends_data';
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $topics = [
            'Civil Rights' => [
                'frequency' => 287,
                'time_periods' => [
                    1950 => 12,
                    1960 => 45,
                    1970 => 23,
                    1980 => 18,
                    1990 => 15,
                    2000 => 8,
                    2010 => 6
                ],
                'cases' => []
            ],
            'Constitutional Law' => [
                'frequency' => 456,
                'time_periods' => [
                    1950 => 23,
                    1960 => 34,
                    1970 => 41,
                    1980 => 52,
                    1990 => 48,
                    2000 => 39,
                    2010 => 32
                ],
                'cases' => []
            ],
            'Criminal Procedure' => [
                'frequency' => 234,
                'time_periods' => [
                    1950 => 8,
                    1960 => 28,
                    1970 => 35,
                    1980 => 42,
                    1990 => 38,
                    2000 => 29,
                    2010 => 18
                ],
                'cases' => []
            ],
            'First Amendment' => [
                'frequency' => 189,
                'time_periods' => [
                    1950 => 5,
                    1960 => 18,
                    1970 => 25,
                    1980 => 28,
                    1990 => 32,
                    2000 => 27,
                    2010 => 22
                ],
                'cases' => []
            ]
        ];

        $topicTrends = [];
        foreach ($topics as $topic => $data) {
            $timePeriods = $data['time_periods'];
            $topicTrends[$topic] = [
                'peak_decade' => array_search(max($timePeriods), $timePeriods),
                'total_frequency' => $data['frequency'],
                'decade_distribution' => $timePeriods,
                'trend_direction' => $this->calculateTrendDirection($timePeriods),
            ];
        }

        $result = [
            'topics' => $topics,
            'topic_trends' => $topicTrends,
            'generated_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Generate heatmap data combining all analyses
     */
    public function generateSampleHeatmapData(): array
    {
        $precedentialData = $this->generateSamplePrecedentialData();
        $languageData = $this->generateSampleJusticeLanguageData();
        $topicData = $this->generateSampleTopicTrendsData();

        $heatmapMatrix = [];
        $justices = array_keys($languageData['justice_patterns']);
        $topics = array_keys($topicData['topics']);
        $timePeriods = [1950, 1960, 1970, 1980, 1990, 2000, 2010];

        // Justice vs Topic matrix
        foreach ($justices as $justice) {
            foreach ($topics as $topic) {
                $intensity = rand(0, 100);
                $heatmapMatrix['justice_topic'][] = [
                    'x' => $justice,
                    'y' => $topic,
                    'value' => $intensity,
                    'data' => [
                        'justice_opinions' => $languageData['justice_patterns'][$justice]['total_opinions'],
                        'topic_frequency' => $topicData['topics'][$topic]['frequency'],
                    ]
                ];
            }
        }

        // Topic vs Time matrix
        foreach ($topics as $topic) {
            foreach ($timePeriods as $period) {
                $frequency = $topicData['topics'][$topic]['time_periods'][$period] ?? 0;
                $heatmapMatrix['topic_time'][] = [
                    'x' => $topic,
                    'y' => $period . 's',
                    'value' => $frequency,
                    'data' => [
                        'cases_in_period' => $frequency,
                        'topic_trend' => $topicData['topic_trends'][$topic]['trend_direction'],
                    ]
                ];
            }
        }

        // Precedential Cases vs Time matrix
        foreach ($precedentialData['major_precedential_cases'] as $caseName => $caseData) {
            $decisionYear = date('Y', strtotime($caseData['decision_date']));
            $decade = floor($decisionYear / 10) * 10;
            
            $heatmapMatrix['precedential_time'][] = [
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

        return [
            'heatmap_data' => $heatmapMatrix,
            'dimensions' => [
                'justices' => $justices,
                'topics' => $topics,
                'time_periods' => $timePeriods,
            ],
            'metadata' => [
                'total_cases' => 8293,
                'major_cases_count' => count($precedentialData['major_precedential_cases']),
                'generated_at' => now()->toISOString(),
            ],
        ];
    }

    // Helper methods

    private function generateCitationNetwork(array $majorCases): array
    {
        $network = [];
        
        foreach ($majorCases as $caseName => $data) {
            $network[$caseName] = [
                'case_id' => null,
                'decision_date' => $data['decision_date'],
                'references_to' => [],
                'referenced_by' => [],
                'total_references_made' => $data['references_made'],
                'total_times_cited' => $data['times_cited'],
            ];
        }
        
        return $network;
    }

    private function calculateTrendDirection(array $timePeriods): string
    {
        $decades = array_keys($timePeriods);
        $counts = array_values($timePeriods);
        
        if (count($counts) < 3) return 'stable';
        
        $recentDecades = array_slice($counts, -3);
        $earlierDecades = array_slice($counts, 0, -3);
        
        if (empty($earlierDecades)) return 'stable';
        
        $recentAvg = array_sum($recentDecades) / count($recentDecades);
        $earlierAvg = array_sum($earlierDecades) / count($earlierDecades);
        
        if ($recentAvg > $earlierAvg * 1.2) return 'increasing';
        if ($recentAvg < $earlierAvg * 0.8) return 'decreasing';
        return 'stable';
    }
}