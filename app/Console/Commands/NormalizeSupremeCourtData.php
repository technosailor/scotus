<?php

namespace App\Console\Commands;

use App\Services\DataNormalizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NormalizeSupremeCourtData extends Command
{
    protected $signature = 'supreme-court:normalize 
                          {--batch-size=100 : Number of cases to process in each batch}
                          {--dry-run : Show what would be processed without making changes}';

    protected $description = 'Normalize and deduplicate Supreme Court case data';

    private DataNormalizationService $normalizationService;

    public function __construct(DataNormalizationService $normalizationService)
    {
        parent::__construct();
        $this->normalizationService = $normalizationService;
    }

    public function handle(): int
    {
        $this->info('Starting Supreme Court data normalization...');
        $this->newLine();

        $batchSize = (int) $this->option('batch-size');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Show initial statistics
        $initialStats = $this->normalizationService->getStatistics();
        $this->displayStatistics('Initial Statistics', $initialStats);

        if ($isDryRun) {
            $this->info('Dry run completed. Use --no-dry-run to actually normalize the data.');
            return 0;
        }

        // Start normalization
        $startTime = microtime(true);
        
        try {
            $results = $this->normalizationService->normalizeAllCases($batchSize);
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info("Normalization completed in {$duration} seconds");
            $this->displayResults($results);

            // Show final statistics
            $finalStats = $this->normalizationService->getStatistics();
            $this->displayStatistics('Final Statistics', $finalStats);

            return 0;

        } catch (\Exception $e) {
            $this->error('Error during normalization: ' . $e->getMessage());
            Log::error('Data normalization failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('Normalization Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Cases Processed', number_format($results['processed'])],
                ['Duplicates Found', number_format($results['duplicates_found'])],
                ['Duplicates Merged', number_format($results['duplicates_merged'])],
                ['Cases Enriched', number_format($results['enriched'])],
                ['Errors', number_format($results['errors'])],
            ]
        );
    }

    private function displayStatistics(string $title, array $stats): void
    {
        $this->newLine();
        $this->info($title . ':');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Cases', number_format($stats['total_cases'])],
                ['Cases with Duplicates', number_format($stats['cases_with_duplicates'])],
                ['Enriched Cases', number_format($stats['enriched_cases'])],
                ['Total Justices', number_format($stats['total_justices'])],
                ['Total Opinions', number_format($stats['total_opinions'])],
                ['Total Terms', number_format($stats['total_terms'])],
            ]
        );
    }
}