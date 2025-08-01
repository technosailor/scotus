<?php

namespace App\Console\Commands;

use App\Services\JustiaDataEnrichmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnrichSupremeCourtData extends Command
{
    protected $signature = 'supreme-court:enrich 
                           {--file= : Process a specific JSON file}
                           {--dry-run : Show what would be processed without making changes}
                           {--limit=10 : Maximum number of files to process}';

    protected $description = 'Enrich Supreme Court case data by fetching additional information from Justia';

    private JustiaDataEnrichmentService $enrichmentService;

    public function __construct(JustiaDataEnrichmentService $enrichmentService)
    {
        parent::__construct();
        $this->enrichmentService = $enrichmentService;
    }

    public function handle(): int
    {
        $this->info('Starting Supreme Court data enrichment...');

        if ($this->option('file')) {
            return $this->processSingleFile($this->option('file'));
        }

        return $this->processAllFiles();
    }

    private function processSingleFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Processing file: {$filePath}");
        
        $result = $this->processFile($filePath);
        
        if ($result === null) {
            $this->warn("No Justia URL found in: {$filePath}");
            return 0;
        }

        if ($result === false) {
            $this->error("Failed to process: {$filePath}");
            return 1;
        }

        $this->info("Successfully processed: {$filePath}");
        return 0;
    }

    private function processAllFiles(): int
    {
        $jsonDir = base_path('json');
        
        if (!is_dir($jsonDir)) {
            $this->error("JSON directory not found: {$jsonDir}");
            return 1;
        }

        $files = glob($jsonDir . '/*.json');
        $limit = (int)$this->option('limit');
        $processed = 0;
        $enriched = 0;
        $noUrl = 0;
        $perCuriam = 0;
        $errors = 0;

        $this->info("Found " . count($files) . " JSON files");
        
        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
            $this->info("Processing first {$limit} files");
        }

        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();

        foreach ($files as $file) {
            $result = $this->processFile($file);
            
            if ($result === null) {
                $noUrl++;
                $this->warn("\nNo Justia URL found in: " . basename($file));
            } elseif ($result === false) {
                $errors++;
                $this->error("\nFailed to process: " . basename($file));
            } elseif ($result === 'per_curiam') {
                $perCuriam++;
                $this->info("\nRegistered per curiam decision: " . basename($file));
            } else {
                $enriched++;
            }
            
            $processed++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Processing complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files processed', $processed],
                ['Successfully enriched', $enriched],
                ['Per curiam decisions registered', $perCuriam],
                ['No Justia URL found', $noUrl],
                ['Errors', $errors],
            ]
        );

        return $errors > 0 ? 1 : 0;
    }

    private function processFile(string $filePath)
    {
        try {
            if ($this->option('dry-run')) {
                // For dry run, just check what would be processed
                $jsonContent = file_get_contents($filePath);
                $caseData = json_decode($jsonContent, true);

                if (!$caseData) {
                    return false;
                }

                $justiaUrl = $this->enrichmentService->extractJustiaUrl($caseData);
                $decisionType = $this->enrichmentService->extractDecisionType($caseData);
                
                if ($justiaUrl) {
                    $this->line("Would process: {$justiaUrl}");
                    return true;
                } elseif ($decisionType) {
                    $this->line("Would register decision type: {$decisionType}");
                    return $decisionType === 'per curiam' ? 'per_curiam' : true;
                }
                
                return null;
            }

            // Use the service to process the file
            $result = $this->enrichmentService->processJsonFile($filePath);
            
            if ($result === null) {
                return null; // No processing occurred
            }

            // Save the updated data back to the file
            $updatedJson = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if (file_put_contents($filePath, $updatedJson) === false) {
                Log::error("Failed to write enriched data to: {$filePath}");
                return false;
            }

            // Check if this was a per curiam decision without Justia URL
            if (isset($result['decision_type_extracted']) && 
                $result['decision_type_extracted'] === 'per curiam' && 
                !isset($result['enriched_data'])) {
                return 'per_curiam';
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Error processing file: {$filePath}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}