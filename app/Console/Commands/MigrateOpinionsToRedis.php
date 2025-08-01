<?php

namespace App\Console\Commands;

use App\Services\RedisOpinionService;
use Illuminate\Console\Command;

class MigrateOpinionsToRedis extends Command
{
    protected $signature = 'opinions:migrate-to-redis 
                          {--batch-size=1000 : Number of opinions to process in each batch}
                          {--clear-first : Clear existing Redis opinion data before migration}
                          {--dry-run : Show what would be migrated without making changes}';

    protected $description = 'Migrate clean opinion data from SQLite to Redis';

    private RedisOpinionService $redisOpinionService;

    public function __construct(RedisOpinionService $redisOpinionService)
    {
        parent::__construct();
        $this->redisOpinionService = $redisOpinionService;
    }

    public function handle(): int
    {
        $this->info('Starting opinion migration from SQLite to Redis...');
        $this->newLine();

        $batchSize = (int) $this->option('batch-size');
        $clearFirst = $this->option('clear-first');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Show current Redis statistics
        $currentStats = $this->redisOpinionService->getStatistics();
        $this->displayStatistics('Current Redis Statistics', $currentStats);

        if ($clearFirst && !$isDryRun) {
            $this->info('Clearing existing Redis opinion data...');
            $deleted = $this->redisOpinionService->clearAll();
            $this->info("Cleared {$deleted} keys from Redis");
            $this->newLine();
        }

        if ($isDryRun) {
            $this->info('Dry run completed. Use --no-dry-run to actually migrate the data.');
            return 0;
        }

        // Start migration
        $startTime = microtime(true);
        
        try {
            // Add progress callback
            $progressCallback = function ($stats, $batchDuration) {
                $percentage = round(($stats['migrated'] / max($stats['total_opinions'], 1)) * 100, 1);
                $this->info("Batch {$stats['batches_processed']}: {$stats['migrated']}/{$stats['total_opinions']} migrated ({$percentage}%) - {$batchDuration}s");
            };
            
            $results = $this->redisOpinionService->migrateToRedis($batchSize, $progressCallback);
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info("Migration completed in {$duration} seconds");
            $this->displayResults($results);

            // Show final Redis statistics
            $finalStats = $this->redisOpinionService->getStatistics();
            $this->displayStatistics('Final Redis Statistics', $finalStats);

            return 0;

        } catch (\Exception $e) {
            $this->error('Error during migration: ' . $e->getMessage());
            return 1;
        }
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('Migration Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Opinions', number_format($results['total_opinions'])],
                ['Successfully Migrated', number_format($results['migrated'])],
                ['Errors', number_format($results['errors'])],
                ['Success Rate', round(($results['migrated'] / max($results['total_opinions'], 1)) * 100, 2) . '%'],
            ]
        );
    }

    private function displayStatistics(string $title, array $stats): void
    {
        $this->newLine();
        $this->info($title . ':');
        
        $rows = [
            ['Total Opinions', number_format($stats['total_opinions'] ?? 0)],
            ['Dissenting Opinions', number_format($stats['dissenting_opinions'] ?? 0)],
            ['Concurring Opinions', number_format($stats['concurring_opinions'] ?? 0)],
            ['Majority Opinions', number_format($stats['majority_opinions'] ?? 0)],
            ['Plurality Opinions', number_format($stats['plurality_opinions'] ?? 0)],
        ];
        
        // Only show none opinions if they exist in the stats
        if (isset($stats['none_opinions']) && $stats['none_opinions'] > 0) {
            $rows[] = ['None/Other Opinions', number_format($stats['none_opinions'])];
        }
        
        $this->table(['Opinion Type', 'Count'], $rows);
    }
}