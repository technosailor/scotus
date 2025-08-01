<?php

namespace App\Services;

use App\Models\Opinion;
use App\Models\SupremeCourtCase;
use App\Models\Justice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class RedisOpinionService
{
    private const CACHE_TTL = 7 * 24 * 60 * 60; // 7 days
    private const OPINION_PREFIX = 'opinion:';
    private const CASE_OPINIONS_PREFIX = 'case_opinions:';
    private const JUSTICE_OPINIONS_PREFIX = 'justice_opinions:';
    private const OPINION_TYPE_PREFIX = 'opinion_type:';

    /**
     * Migrate all opinions from SQLite to Redis with progress callback
     */
    public function migrateToRedis(int $batchSize = 1000, ?callable $progressCallback = null): array
    {
        $stats = [
            'migrated' => 0,
            'errors' => 0,
            'total_opinions' => Opinion::count(),
            'batches_processed' => 0,
        ];

        Log::info('Starting opinion migration from SQLite to Redis', $stats);

        Opinion::with(['case', 'justice'])
            ->chunk($batchSize, function ($opinions) use (&$stats, $progressCallback) {
                $batchStart = microtime(true);
                
                foreach ($opinions as $opinion) {
                    try {
                        $this->storeOpinion($opinion);
                        $stats['migrated']++;
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        Log::error("Error migrating opinion {$opinion->id}: " . $e->getMessage());
                    }
                }
                
                $stats['batches_processed']++;
                $batchDuration = round(microtime(true) - $batchStart, 2);
                
                if ($progressCallback) {
                    $progressCallback($stats, $batchDuration);
                }
            });

        Log::info('Opinion migration completed', $stats);
        return $stats;
    }

    /**
     * Store a single opinion in Redis with multiple indexes
     */
    public function storeOpinion(Opinion $opinion): void
    {
        // Map 'none' opinion types to 'concurrence' for better categorization
        $normalizedOpinionType = $opinion->opinion_type === 'none' ? 'concurrence' : $opinion->opinion_type;
        
        $opinionData = [
            'id' => $opinion->id,
            'case_id' => $opinion->case_id,
            'justice_id' => $opinion->justice_id,
            'opinion_type' => $normalizedOpinionType,
            'original_opinion_type' => $opinion->opinion_type, // Keep original for reference
            'vote' => $opinion->vote,
            'opinion_text' => $opinion->opinion_text,
            'sentiment_score' => $opinion->sentiment_score,
            'ideology_score' => $opinion->ideology_score,
            'seniority' => $opinion->seniority,
            'joining_justices' => $opinion->joining_justices,
            'oyez_href' => $opinion->oyez_href,
            'created_at' => $opinion->created_at?->toISOString(),
            'updated_at' => $opinion->updated_at?->toISOString(),
            // Include related data for easier access
            'case_name' => $opinion->case?->case_name,
            'case_decision_date' => $opinion->case?->decision_date?->toISOString(),
            'justice_name' => $opinion->justice?->name,
        ];

        // Store the main opinion record
        Cache::put(
            self::OPINION_PREFIX . $opinion->id,
            $opinionData,
            self::CACHE_TTL
        );

        // Index by case
        $this->addToIndex(
            self::CASE_OPINIONS_PREFIX . $opinion->case_id,
            $opinion->id
        );

        // Index by justice
        $this->addToIndex(
            self::JUSTICE_OPINIONS_PREFIX . $opinion->justice_id,
            $opinion->id
        );

        // Index by normalized opinion type
        $this->addToIndex(
            self::OPINION_TYPE_PREFIX . $normalizedOpinionType,
            $opinion->id
        );
    }

    /**
     * Get an opinion by ID from Redis
     */
    public function getOpinion(int $opinionId): ?array
    {
        return Cache::get(self::OPINION_PREFIX . $opinionId);
    }

    /**
     * Get all opinions for a case
     */
    public function getOpinionsByCase(int $caseId): Collection
    {
        $opinionIds = $this->getFromIndex(self::CASE_OPINIONS_PREFIX . $caseId);
        return $this->getOpinionsByIds($opinionIds);
    }

    /**
     * Get all opinions by a justice
     */
    public function getOpinionsByJustice(int $justiceId): Collection
    {
        $opinionIds = $this->getFromIndex(self::JUSTICE_OPINIONS_PREFIX . $justiceId);
        return $this->getOpinionsByIds($opinionIds);
    }

    /**
     * Get opinions by type (dissent, concurring, majority, etc.)
     */
    public function getOpinionsByType(string $opinionType): Collection
    {
        $opinionIds = $this->getFromIndex(self::OPINION_TYPE_PREFIX . $opinionType);
        return $this->getOpinionsByIds($opinionIds);
    }

    /**
     * Get opinions with pagination
     */
    public function getOpinionsPaginated(string $type = null, int $page = 1, int $perPage = 50): array
    {
        $opinionIds = $type 
            ? $this->getFromIndex(self::OPINION_TYPE_PREFIX . $type)
            : $this->getAllOpinionIds();

        $total = count($opinionIds);
        $offset = ($page - 1) * $perPage;
        $paginatedIds = array_slice($opinionIds, $offset, $perPage);

        return [
            'data' => $this->getOpinionsByIds($paginatedIds),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Search opinions by text content
     */
    public function searchOpinions(string $query, string $type = null): Collection
    {
        $opinionIds = $type 
            ? $this->getFromIndex(self::OPINION_TYPE_PREFIX . $type)
            : $this->getAllOpinionIds();

        $matchingOpinions = collect();

        foreach ($opinionIds as $opinionId) {
            $opinion = $this->getOpinion($opinionId);
            if (!$opinion) continue;

            // Search in case name, justice name, and opinion text
            $searchableText = strtolower(
                ($opinion['case_name'] ?? '') . ' ' .
                ($opinion['justice_name'] ?? '') . ' ' .
                ($opinion['opinion_text'] ?? '')
            );

            if (str_contains($searchableText, strtolower($query))) {
                $matchingOpinions->push($opinion);
            }
        }

        return $matchingOpinions;
    }

    /**
     * Get statistics about opinions in Redis
     */
    public function getStatistics(): array
    {
        return [
            'total_opinions' => count($this->getAllOpinionIds()),
            'dissenting_opinions' => count($this->getFromIndex(self::OPINION_TYPE_PREFIX . 'dissent')),
            'concurring_opinions' => count($this->getFromIndex(self::OPINION_TYPE_PREFIX . 'concurrence')),
            'majority_opinions' => count($this->getFromIndex(self::OPINION_TYPE_PREFIX . 'majority')),
            'plurality_opinions' => count($this->getFromIndex(self::OPINION_TYPE_PREFIX . 'plurality')),
        ];
    }

    /**
     * Clear all opinion data from Redis
     */
    public function clearAll(): int
    {
        $patterns = [
            self::OPINION_PREFIX . '*',
            self::CASE_OPINIONS_PREFIX . '*',
            self::JUSTICE_OPINIONS_PREFIX . '*',
            self::OPINION_TYPE_PREFIX . '*',
        ];

        $deleted = 0;
        foreach ($patterns as $pattern) {
            $keys = Cache::getRedis()->keys($pattern);
            if (!empty($keys)) {
                $deleted += Cache::getRedis()->del($keys);
            }
        }

        // Also clear any cache keys that might be interfering
        Cache::flush();

        return $deleted;
    }

    /**
     * Add an opinion ID to an index
     */
    private function addToIndex(string $indexKey, int $opinionId): void
    {
        $existing = Cache::get($indexKey, []);
        if (!in_array($opinionId, $existing)) {
            $existing[] = $opinionId;
            Cache::put($indexKey, $existing, self::CACHE_TTL);
        }
    }

    /**
     * Get opinion IDs from an index
     */
    private function getFromIndex(string $indexKey): array
    {
        return Cache::get($indexKey, []);
    }

    /**
     * Get multiple opinions by their IDs
     */
    private function getOpinionsByIds(array $opinionIds): Collection
    {
        $opinions = collect();
        
        foreach ($opinionIds as $opinionId) {
            $opinion = $this->getOpinion($opinionId);
            if ($opinion) {
                $opinions->push($opinion);
            }
        }

        return $opinions;
    }

    /**
     * Get all opinion IDs (from all type indexes)
     */
    private function getAllOpinionIds(): array
    {
        $allIds = [];
        $types = ['dissent', 'concurrence', 'majority', 'plurality'];
        
        foreach ($types as $type) {
            $ids = $this->getFromIndex(self::OPINION_TYPE_PREFIX . $type);
            $allIds = array_merge($allIds, $ids);
        }

        return array_unique($allIds);
    }
}