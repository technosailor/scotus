<?php

namespace App\Services;

use App\Models\Justice;
use App\Models\President;
use App\Models\Term;
use App\Models\SupremeCourtCase;
use App\Models\Opinion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SupremeCourtDataIngestionService
{
    private LocalLlmService $llmService;
    
    public function __construct(LocalLlmService $llmService)
    {
        $this->llmService = $llmService;
    }

    /**
     * Ingest all Supreme Court JSON files from the json directory
     */
    public function ingestAllFiles(): array
    {
        $jsonPath = base_path('json');
        $results = [
            'processed' => 0,
            'errors' => 0,
            'justices_created' => 0,
            'presidents_created' => 0,
            'terms_created' => 0,
            'cases_created' => 0,
            'opinions_created' => 0,
        ];

        if (!is_dir($jsonPath)) {
            throw new \Exception("JSON directory not found: {$jsonPath}");
        }

        $files = glob($jsonPath . '/*.json');
        
        foreach ($files as $file) {
            try {
                $this->processJsonFile($file, $results);
                $results['processed']++;
            } catch (\Exception $e) {
                Log::error('Failed to process JSON file', [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Process a single JSON file
     */
    public function processJsonFile(string $filePath, array &$results): void
    {
        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);

        if (!$data) {
            throw new \Exception("Invalid JSON in file: {$filePath}");
        }

        DB::transaction(function () use ($data, &$results) {
            // Extract term from filename or data
            $term = $this->extractTerm($data);
            $termModel = $this->createOrUpdateTerm($term, $results);

            // Process justices from the case data
            $justices = $this->processJustices($data, $results);

            // Create the case
            $case = $this->createCase($data, $termModel, $results);

            // Process opinions and votes
            $this->processOpinions($data, $case, $justices, $results);

            // Analyze case with LLM if available
            $this->analyzeCase($case, $data);
        });
    }

    /**
     * Extract term year from data or filename
     */
    private function extractTerm(array $data): string
    {
        // Always prefer the actual decision date for term determination
        $decisionDate = $this->extractDecisionDate($data);
        if ($decisionDate) {
            return $decisionDate->format('Y');
        }
        
        // Extract from citation year
        if (isset($data['citation']['year'])) {
            return (string) $data['citation']['year'];
        }

        // Only use the term field if it's a single year, not a range
        if (isset($data['term'])) {
            $term = $data['term'];
            // If it's a single 4-digit year, use it
            if (preg_match('/^\d{4}$/', $term)) {
                return $term;
            }
            // If it's a range like "1789-1850", we can't determine the exact year
            // Fall through to default
        }

        // Default fallback to a reasonable early year
        return '1789';
    }

    /**
     * Create or update term
     */
    private function createOrUpdateTerm(string $termYear, array &$results): Term
    {
        $term = Term::firstOrCreate(
            ['year' => $termYear],
            [
                'name' => $termYear . ' Term',
                'term_start' => Carbon::createFromDate($termYear, 10, 1),
                'term_end' => Carbon::createFromDate((int)$termYear + 1, 6, 30),
            ]
        );

        if ($term->wasRecentlyCreated) {
            $results['terms_created']++;
        }

        return $term;
    }

    /**
     * Process all justices from the case data
     */
    private function processJustices(array $data, array &$results): array
    {
        $justices = [];

        // Process justices from decisions/votes
        if (isset($data['decisions'])) {
            foreach ($data['decisions'] as $decision) {
                if (isset($decision['votes'])) {
                    foreach ($decision['votes'] as $vote) {
                        if (isset($vote['member'])) {
                            $justice = $this->createOrUpdateJustice($vote['member'], $results);
                            $justices[$justice->oyez_id] = $justice;
                        }
                    }
                }
            }
        }

        // Process justices from heard_by court composition
        if (isset($data['heard_by'])) {
            foreach ($data['heard_by'] as $court) {
                if (isset($court['members'])) {
                    foreach ($court['members'] as $member) {
                        $justice = $this->createOrUpdateJustice($member, $results);
                        $justices[$justice->oyez_id] = $justice;
                    }
                }
            }
        }

        return $justices;
    }

    /**
     * Create or update a justice
     */
    private function createOrUpdateJustice(array $memberData, array &$results): Justice
    {
        $appointingPresident = null;
        
        // Extract appointing president from roles
        if (isset($memberData['roles'])) {
            foreach ($memberData['roles'] as $role) {
                if (isset($role['appointing_president'])) {
                    $appointingPresident = $role['appointing_president'];
                    break;
                }
            }
        }

        // Create or find president if we have one
        $president = null;
        if ($appointingPresident) {
            $president = President::firstOrCreate(
                ['name' => $appointingPresident],
                ['party' => $this->inferPartyFromPresident($appointingPresident)]
            );
            
            if ($president->wasRecentlyCreated) {
                $results['presidents_created']++;
            }
        }

        $justice = Justice::updateOrCreate(
            ['oyez_id' => (string) $memberData['ID']],
            [
                'name' => $memberData['name'] ?? 'Unknown',
                'first_name' => $this->extractFirstName($memberData['name'] ?? ''),
                'last_name' => $memberData['last_name'] ?? 'Unknown',
                'length_of_service' => $memberData['length_of_service'] ?? null,
                'view_count' => $memberData['view_count'] ?? 0,
                'identifier' => $memberData['identifier'] ?? 'unknown',
                'thumbnail_url' => $memberData['thumbnail']['href'] ?? null,
                'href' => $memberData['href'] ?? null,
                'roles' => $memberData['roles'] ?? [],
            ]
        );

        if ($justice->wasRecentlyCreated) {
            $results['justices_created']++;
        }

        return $justice;
    }

    /**
     * Create a Supreme Court case
     */
    private function createCase(array $data, Term $term, array &$results): SupremeCourtCase
    {
        $caseName = $data['name'] ?? 'Unknown Case';
        $decisionDate = $this->extractDecisionDate($data) ?? Carbon::now();
        $uniqueHash = hash('sha256', $caseName . $decisionDate->format('Y-m-d'));
        
        // Check if case already exists
        $existingCase = SupremeCourtCase::where('unique_hash', $uniqueHash)->first();
        if ($existingCase) {
            return $existingCase;
        }
        
        $case = SupremeCourtCase::create([
            'unique_hash' => $uniqueHash,
            'oyez_id' => (string) ($data['ID'] ?? null),
            'case_name' => $caseName,
            'docket_number' => $data['docket_number'] ?? null,
            'term_id' => $term->id,
            'href' => $data['href'] ?? null,
            'summary' => $this->stripHtml($data['facts_of_the_case'] ?? null),
            'facts' => isset($data['facts_of_the_case']) ? [$this->stripHtml($data['facts_of_the_case'])] : null,
            'question' => isset($data['question']) ? [$this->stripHtml($data['question'])] : null,
            'conclusion' => isset($data['conclusion']) ? [$this->stripHtml($data['conclusion'])] : null,
            'decision_date' => $decisionDate,
            'sentiment_score' => null, // Will be populated by LLM analysis
            'majority_opinion_author' => $this->extractMajorityAuthor($data),
            'concurring_justices' => $this->extractConcurringJustices($data),
            'dissenting_justices' => $this->extractDissentingJustices($data),
            'raw_data' => $data,
        ]);

        $results['cases_created']++;
        return $case;
    }

    /**
     * Process opinions and votes
     */
    private function processOpinions(array $data, SupremeCourtCase $case, array $justices, array &$results): void
    {
        if (!isset($data['decisions'])) {
            return;
        }

        foreach ($data['decisions'] as $decision) {
            if (!isset($decision['votes'])) {
                continue;
            }

            foreach ($decision['votes'] as $vote) {
                if (!isset($vote['member']) || !isset($justices[$vote['member']['ID']])) {
                    continue;
                }

                $justice = $justices[$vote['member']['ID']];

                Opinion::create([
                    'case_id' => $case->id,
                    'justice_id' => $justice->id,
                    'opinion_type' => $this->normalizeOpinionType($vote['opinion_type'] ?? 'none'),
                    'vote' => $this->normalizeVote($vote['vote'] ?? 'unknown'),
                    'ideology_score' => $vote['ideology'] ?? null,
                    'seniority' => $vote['seniority'] ?? null,
                    'joining_justices' => $this->extractJoiningJustices($vote),
                ]);

                $results['opinions_created']++;
            }
        }
    }

    /**
     * Analyze case with LLM
     */
    private function analyzeCase(SupremeCourtCase $case, array $data): void
    {
        if (!$this->llmService->isAvailable()) {
            return;
        }

        try {
            // Prepare case text for sentiment analysis
            $caseText = implode(' ', [
                $case->facts_of_the_case ?? '',
                $case->question ?? '',
                $case->conclusion ?? '',
            ]);

            if (strlen($caseText) > 100) {
                $sentimentScore = $this->llmService->analyzeSentiment($caseText);
                $case->update(['sentiment_score' => $sentimentScore]);
            }

            // Analyze case data for additional insights
            $analysis = $this->llmService->analyzeCaseData($data, 'legal_themes');
            if (isset($analysis['themes'])) {
                // Store themes in case metadata or separate table
                Log::info('LLM Analysis for case', [
                    'case_id' => $case->id,
                    'themes' => $analysis['themes'] ?? null
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LLM analysis failed for case', [
                'case_id' => $case->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Helper methods

    private function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? 'Unknown';
    }

    private function inferPartyFromPresident(string $president): ?string
    {
        $republicans = [
            'Dwight D. Eisenhower', 'Richard Nixon', 'Gerald Ford', 'Ronald Reagan', 
            'George H. W. Bush', 'George W. Bush', 'Donald Trump'
        ];
        
        $democrats = [
            'Franklin D. Roosevelt', 'Harry S. Truman', 'John F. Kennedy', 
            'Lyndon B. Johnson', 'Jimmy Carter', 'Bill Clinton', 'Barack Obama', 
            'Joe Biden'
        ];

        if (in_array($president, $republicans)) {
            return 'Republican';
        }
        
        if (in_array($president, $democrats)) {
            return 'Democratic';
        }

        return null;
    }

    private function convertTimestamp(?int $timestamp): ?Carbon
    {
        if (!$timestamp) {
            return null;
        }

        try {
            return Carbon::createFromTimestamp($timestamp);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function stripHtml(?string $text): ?string
    {
        if (!$text) {
            return null;
        }

        return strip_tags($text);
    }

    private function extractDecisionDate(array $data): ?Carbon
    {
        if (!isset($data['timeline'])) {
            return null;
        }

        foreach ($data['timeline'] as $event) {
            if (isset($event['event']) && $event['event'] === 'Decided' && isset($event['dates'][0])) {
                return $this->convertTimestamp($event['dates'][0]);
            }
        }

        return null;
    }

    private function extractArguedDate(array $data): ?Carbon
    {
        if (!isset($data['timeline'])) {
            return null;
        }

        foreach ($data['timeline'] as $event) {
            if (isset($event['event']) && $event['event'] === 'Argued' && isset($event['dates'][0])) {
                return $this->convertTimestamp($event['dates'][0]);
            }
        }

        return null;
    }

    private function normalizeOpinionType(string $type): string
    {
        $normalized = strtolower(trim($type));
        
        switch ($normalized) {
            case 'majority':
                return 'majority';
            case 'dissent':
            case 'dissenting':
                return 'dissent';
            case 'concur':
            case 'concurring':
                return 'concurrence';
            case 'plurality':
                return 'plurality';
            case 'none':
            default:
                return 'none';
        }
    }

    private function normalizeVote(string $vote): string
    {
        $normalized = strtolower(trim($vote));
        
        switch ($normalized) {
            case 'majority':
                return 'majority';
            case 'minority':
            case 'dissent':
            case 'dissenting':
                return 'minority';
            case 'unknown':
            case 'none':
            default:
                return 'majority'; // Default to majority if unclear
        }
    }

    private function extractJoiningJustices(array $vote): ?array
    {
        if (!isset($vote['joining']) || !is_array($vote['joining'])) {
            return null;
        }

        return collect($vote['joining'])->map(function ($justice) {
            return [
                'name' => $justice['name'] ?? 'Unknown',
                'oyez_id' => $justice['ID'] ?? null,
            ];
        })->toArray();
    }

    private function calculateImportanceScore(array $data): int
    {
        $score = 1; // Base score

        // Higher view count indicates more important case
        if (isset($data['view_count']) && $data['view_count'] > 0) {
            $score += min(5, floor($data['view_count'] / 100));
        }

        // Cases with oral arguments tend to be more important
        if (isset($data['oral_argument_audio']) && count($data['oral_argument_audio']) > 0) {
            $score += 2;
        }

        // Cases with multiple written opinions tend to be more important
        if (isset($data['written_opinion']) && count($data['written_opinion']) > 2) {
            $score += 1;
        }

        return min(10, $score); // Cap at 10
    }

    private function extractMajorityAuthor(array $data): ?string
    {
        if (!isset($data['decisions'])) {
            return null;
        }

        foreach ($data['decisions'] as $decision) {
            if (isset($decision['votes'])) {
                foreach ($decision['votes'] as $vote) {
                    if (isset($vote['opinion_type']) && $vote['opinion_type'] === 'majority') {
                        return $vote['member']['name'] ?? null;
                    }
                }
            }
        }

        return null;
    }

    private function extractConcurringJustices(array $data): ?array
    {
        if (!isset($data['decisions'])) {
            return null;
        }

        $concurring = [];
        foreach ($data['decisions'] as $decision) {
            if (isset($decision['votes'])) {
                foreach ($decision['votes'] as $vote) {
                    if (isset($vote['opinion_type']) && in_array($vote['opinion_type'], ['concur', 'concurring'])) {
                        $concurring[] = $vote['member']['name'] ?? 'Unknown';
                    }
                }
            }
        }

        return empty($concurring) ? null : $concurring;
    }

    private function extractDissentingJustices(array $data): ?array
    {
        if (!isset($data['decisions'])) {
            return null;
        }

        $dissenting = [];
        foreach ($data['decisions'] as $decision) {
            if (isset($decision['votes'])) {
                foreach ($decision['votes'] as $vote) {
                    if (isset($vote['opinion_type']) && in_array($vote['opinion_type'], ['dissent', 'dissenting'])) {
                        $dissenting[] = $vote['member']['name'] ?? 'Unknown';
                    } elseif (isset($vote['vote']) && $vote['vote'] === 'minority') {
                        $dissenting[] = $vote['member']['name'] ?? 'Unknown';
                    }
                }
            }
        }

        return empty($dissenting) ? null : $dissenting;
    }
}