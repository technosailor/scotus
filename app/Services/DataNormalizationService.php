<?php

namespace App\Services;

use App\Models\SupremeCourtCase;
use App\Models\Justice;
use App\Models\Opinion;
use App\Models\Term;
use App\Models\President;
use App\Models\LlmAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DataNormalizationService
{
    private SupremeCourtDataService $dataService;
    private JustiaDataEnrichmentService $enrichmentService;

    public function __construct(
        SupremeCourtDataService $dataService,
        JustiaDataEnrichmentService $enrichmentService
    ) {
        $this->dataService = $dataService;
        $this->enrichmentService = $enrichmentService;
    }

    /**
     * Normalize and deduplicate all case data
     */
    public function normalizeAllCases(int $batchSize = 100): array
    {
        $stats = [
            'processed' => 0,
            'duplicates_found' => 0,
            'duplicates_merged' => 0,
            'enriched' => 0,
            'errors' => 0,
        ];

        $cases = SupremeCourtCase::select('id', 'unique_hash', 'case_name', 'oyez_id', 'facts', 'question', 'conclusion', 'summary')
            ->where(function ($query) {
                $query->whereNull('facts')
                      ->orWhereNull('question')
                      ->orWhereNull('conclusion')
                      ->orWhereNull('summary');
            })
            ->orderBy('id')
            ->chunk($batchSize, function ($batch) use (&$stats) {
                foreach ($batch as $case) {
                    try {
                        $result = $this->normalizeCase($case);
                        $stats['processed']++;
                        
                        if ($result['is_duplicate']) {
                            $stats['duplicates_found']++;
                            if ($result['merged']) {
                                $stats['duplicates_merged']++;
                            }
                        }
                        
                        if ($result['enriched']) {
                            $stats['enriched']++;
                        }
                        
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        Log::error("Error normalizing case {$case->id}: " . $e->getMessage());
                    }
                }
            });

        return $stats;
    }

    /**
     * Normalize a single case
     */
    public function normalizeCase(SupremeCourtCase $case): array
    {
        $result = [
            'is_duplicate' => false,
            'merged' => false,
            'enriched' => false,
            'original_id' => $case->id,
        ];

        // Step 1: Check for duplicates
        $duplicates = $this->findDuplicates($case);
        
        if ($duplicates->count() > 0) {
            $result['is_duplicate'] = true;
            $primaryCase = $this->mergeDuplicates($case, $duplicates);
            $result['merged'] = true;
            $result['primary_case_id'] = $primaryCase->id;
            $case = $primaryCase; // Continue with the merged case
        }

        // Step 2: Enrich with JSON data if missing
        if ($this->needsEnrichment($case)) {
            $enriched = $this->enrichFromJsonData($case);
            $result['enriched'] = $enriched;
        }

        // Step 3: Extract and normalize related entities
        $this->extractJustices($case);
        $this->extractTerms($case);
        $this->extractOpinions($case);

        return $result;
    }

    /**
     * Find duplicate cases based on various criteria
     */
    private function findDuplicates(SupremeCourtCase $case): \Illuminate\Database\Eloquent\Collection
    {
        $query = SupremeCourtCase::where('id', '!=', $case->id);

        // Primary: Same unique hash
        if ($case->unique_hash) {
            $hashDuplicates = $query->where('unique_hash', $case->unique_hash)->get();
            if ($hashDuplicates->count() > 0) {
                return $hashDuplicates;
            }
        }

        // Secondary: Same oyez_id
        if ($case->oyez_id) {
            $oyezDuplicates = $query->where('oyez_id', $case->oyez_id)->get();
            if ($oyezDuplicates->count() > 0) {
                return $oyezDuplicates;
            }
        }

        // Tertiary: Same case name + decision date
        if ($case->case_name && $case->decision_date) {
            $nameDateDuplicates = $query
                ->where('case_name', $case->case_name)
                ->where('decision_date', $case->decision_date)
                ->get();
            if ($nameDateDuplicates->count() > 0) {
                return $nameDateDuplicates;
            }
        }

        return SupremeCourtCase::whereRaw('1 = 0')->get(); // Return empty Eloquent collection
    }

    /**
     * Merge duplicate cases into the primary case
     */
    private function mergeDuplicates(SupremeCourtCase $primaryCase, \Illuminate\Database\Eloquent\Collection $duplicates): SupremeCourtCase
    {
        DB::transaction(function () use ($primaryCase, $duplicates) {
            foreach ($duplicates as $duplicate) {
                // Merge data - keep the most complete information
                $primaryCase = $this->mergeData($primaryCase, $duplicate);
                
                // Update any foreign key references
                $this->updateForeignKeyReferences($duplicate->id, $primaryCase->id);
                
                // Delete the duplicate
                $duplicate->delete();
                
                Log::info("Merged duplicate case {$duplicate->id} into {$primaryCase->id}");
            }
            
            $primaryCase->save();
        });

        return $primaryCase;
    }

    /**
     * Merge data from duplicate into primary case
     */
    private function mergeData(SupremeCourtCase $primary, SupremeCourtCase $duplicate): SupremeCourtCase
    {
        // Keep the most complete version of each field
        $fields = [
            'oyez_id', 'case_name', 'docket_number', 'decision_date', 
            'summary', 'facts', 'question', 'conclusion', 
            'majority_opinion_author', 'concurring_justices', 'dissenting_justices',
            'sentiment_score', 'href', 'raw_data'
        ];

        foreach ($fields as $field) {
            if (empty($primary->$field) && !empty($duplicate->$field)) {
                $primary->$field = $duplicate->$field;
            } elseif (!empty($primary->$field) && !empty($duplicate->$field)) {
                // If both have data, keep the longer/more detailed version
                if (strlen($duplicate->$field) > strlen($primary->$field)) {
                    $primary->$field = $duplicate->$field;
                }
            }
        }

        // Update unique hash if needed
        if (!$primary->unique_hash && $duplicate->unique_hash) {
            $primary->unique_hash = $duplicate->unique_hash;
        }

        return $primary;
    }

    /**
     * Update foreign key references from old ID to new ID
     */
    private function updateForeignKeyReferences(int $oldId, int $newId): void
    {
        // Update opinions (try both column names for compatibility)
        if (Schema::hasColumn('opinions', 'supreme_court_case_id')) {
            Opinion::where('supreme_court_case_id', $oldId)
                ->update(['supreme_court_case_id' => $newId]);
        } elseif (Schema::hasColumn('opinions', 'case_id')) {
            Opinion::where('case_id', $oldId)
                ->update(['case_id' => $newId]);
        }

        // Update LLM analyses (try both column names for compatibility)
        if (Schema::hasColumn('llm_analyses', 'supreme_court_case_id')) {
            LlmAnalysis::where('supreme_court_case_id', $oldId)
                ->update(['supreme_court_case_id' => $newId]);
        } elseif (Schema::hasColumn('llm_analyses', 'case_id')) {
            LlmAnalysis::where('case_id', $oldId)
                ->update(['case_id' => $newId]);
        }

        // Add other related tables as needed
    }

    /**
     * Check if case needs enrichment from JSON data
     */
    private function needsEnrichment(SupremeCourtCase $case): bool
    {
        return empty($case->facts) || 
               empty($case->question) || 
               empty($case->conclusion) ||
               empty($case->summary);
    }

    /**
     * Enrich case with data from JSON files
     */
    private function enrichFromJsonData(SupremeCourtCase $case): bool
    {
        // Try to find corresponding JSON data
        $identifier = $this->generateIdentifier($case);
        
        if (!$identifier) {
            return false;
        }

        $enrichedData = $this->dataService->getEnrichedCaseData($identifier);
        
        if (!$enrichedData) {
            return false;
        }

        // Update case with enriched data
        $updated = false;

        if (empty($case->facts) && isset($enrichedData['facts_of_the_case'])) {
            $case->facts = $this->cleanHtmlText($enrichedData['facts_of_the_case']);
            $updated = true;
        }

        if (empty($case->question) && isset($enrichedData['question'])) {
            $case->question = $this->cleanHtmlText($enrichedData['question']);
            $updated = true;
        }

        if (empty($case->conclusion) && isset($enrichedData['conclusion'])) {
            $case->conclusion = $this->cleanHtmlText($enrichedData['conclusion']);
            $updated = true;
        }

        if (empty($case->summary) && isset($enrichedData['description'])) {
            $case->summary = $this->cleanHtmlText($enrichedData['description']);
            $updated = true;
        }

        // Add enriched Justia data if available
        if (isset($enrichedData['enriched_data'])) {
            $case->raw_data = json_encode(array_merge(
                json_decode($case->raw_data ?? '{}', true),
                ['enriched_data' => $enrichedData['enriched_data']]
            ));
            $updated = true;
        }

        if ($updated) {
            $case->save();
            Log::info("Enriched case {$case->id} with JSON data");
        }

        return $updated;
    }

    /**
     * Generate identifier for JSON lookup
     */
    private function generateIdentifier(SupremeCourtCase $case): ?string
    {
        // Try various identifier formats
        if ($case->oyez_id) {
            return $case->oyez_id;
        }

        if ($case->href) {
            // Extract from href: https://api.oyez.org/cases/1789-1850/10us87
            if (preg_match('/\/cases\/([^\/]+)\/([^\/]+)$/', $case->href, $matches)) {
                return $matches[1] . '.' . $matches[2];
            }
        }

        return null;
    }

    /**
     * Extract and normalize Justice data
     */
    private function extractJustices(SupremeCourtCase $case): void
    {
        if ($case->raw_data) {
            $rawData = json_decode($case->raw_data, true);
            
            if (isset($rawData['decisions'][0]['votes'])) {
                foreach ($rawData['decisions'][0]['votes'] as $vote) {
                    if (isset($vote['member'])) {
                        $this->createOrUpdateJustice($vote['member'], $case->id);
                    }
                }
            }
        }
    }

    /**
     * Create or update Justice record
     */
    private function createOrUpdateJustice(array $memberData, int $caseId): void
    {
        $justice = Justice::updateOrCreate(
            ['oyez_id' => $memberData['ID']],
            [
                'name' => $memberData['name'],
                'last_name' => $memberData['last_name'],
                'identifier' => $memberData['identifier'],
                'length_of_service' => $memberData['length_of_service'] ?? null,
                'view_count' => $memberData['view_count'] ?? 0,
                'thumbnail_url' => $memberData['thumbnail']['href'] ?? null,
                'href' => $memberData['href'],
            ]
        );

        // Create opinion relationship if it doesn't exist
        // (This would need a pivot table for case-justice relationships)
    }

    /**
     * Extract and normalize Term data
     */
    private function extractTerms(SupremeCourtCase $case): void
    {
        if ($case->raw_data) {
            $rawData = json_decode($case->raw_data, true);
            
            if (isset($rawData['term'])) {
                Term::updateOrCreate(
                    ['name' => $rawData['term']],
                    [
                        'start_year' => $this->extractStartYear($rawData['term']),
                        'end_year' => $this->extractEndYear($rawData['term']),
                    ]
                );
            }
        }
    }

    /**
     * Extract and normalize Opinion data
     */
    private function extractOpinions(SupremeCourtCase $case): void
    {
        if ($case->raw_data) {
            $rawData = json_decode($case->raw_data, true);
            
            if (isset($rawData['written_opinion'])) {
                foreach ($rawData['written_opinion'] as $opinion) {
                    // Use the correct foreign key column based on table structure
                    $caseIdColumn = Schema::hasColumn('opinions', 'supreme_court_case_id') 
                        ? 'supreme_court_case_id' 
                        : 'case_id';
                    
                    Opinion::updateOrCreate(
                        [
                            $caseIdColumn => $case->id,
                            'oyez_href' => $opinion['href'] ?? null
                        ],
                        [
                            'opinion_type' => strtolower($opinion['type']['value'] ?? 'majority'),
                            'vote' => 'majority', // Default, can be refined later
                            'opinion_text' => $opinion['opinion_text'] ?? null,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Clean HTML text content
     */
    private function cleanHtmlText(?string $text): ?string
    {
        if (!$text) return null;
        
        return trim(strip_tags(html_entity_decode($text)));
    }

    /**
     * Extract start year from term string (e.g., "1789-1850" -> 1789)
     */
    private function extractStartYear(string $term): ?int
    {
        if (preg_match('/^(\d{4})/', $term, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Extract end year from term string (e.g., "1789-1850" -> 1850)
     */
    private function extractEndYear(string $term): ?int
    {
        if (preg_match('/(\d{4})$/', $term, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Get normalization statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_cases' => SupremeCourtCase::count(),
            'cases_with_duplicates' => SupremeCourtCase::whereNotNull('unique_hash')
                ->selectRaw('unique_hash, COUNT(*) as count')
                ->groupBy('unique_hash')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'enriched_cases' => SupremeCourtCase::whereNotNull('facts')
                ->whereNotNull('question')
                ->whereNotNull('conclusion')
                ->count(),
            'total_justices' => Justice::count(),
            'total_opinions' => Opinion::count(),
            'total_terms' => Term::count(),
        ];
    }
}