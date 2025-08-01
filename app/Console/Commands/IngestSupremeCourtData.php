<?php

namespace App\Console\Commands;

use App\Services\SupremeCourtDataIngestionService;
use Illuminate\Console\Command;

class IngestSupremeCourtData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supreme-court:ingest
                           {--file= : Process a specific JSON file}
                           {--force : Force re-processing of existing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest Supreme Court case data from JSON files';

    private SupremeCourtDataIngestionService $ingestionService;

    public function __construct(SupremeCourtDataIngestionService $ingestionService)
    {
        parent::__construct();
        $this->ingestionService = $ingestionService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Supreme Court data ingestion...');
        
        try {
            $startTime = now();
            
            if ($file = $this->option('file')) {
                $results = $this->processSingleFile($file);
            } else {
                $results = $this->ingestionService->ingestAllFiles();
            }
            
            $duration = now()->diffInSeconds($startTime);
            
            $this->displayResults($results, $duration);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Ingestion failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function processSingleFile(string $file): array
    {
        $results = [
            'processed' => 0,
            'errors' => 0,
            'justices_created' => 0,
            'presidents_created' => 0,
            'terms_created' => 0,
            'cases_created' => 0,
            'opinions_created' => 0,
        ];
        
        $filePath = base_path('json/' . $file);
        
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        
        $this->info("Processing file: {$file}");
        
        $this->ingestionService->processJsonFile($filePath, $results);
        $results['processed'] = 1;
        
        return $results;
    }
    
    private function displayResults(array $results, int $duration): void
    {
        $this->info('Ingestion completed successfully!');
        $this->line('');
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files Processed', $results['processed']],
                ['Errors', $results['errors']],
                ['Justices Created', $results['justices_created']],
                ['Presidents Created', $results['presidents_created']],
                ['Terms Created', $results['terms_created']],
                ['Cases Created', $results['cases_created']],
                ['Opinions Created', $results['opinions_created']],
                ['Duration (seconds)', $duration],
            ]
        );
        
        if ($results['errors'] > 0) {
            $this->warn("There were {$results['errors']} errors during processing. Check the logs for details.");
        }
    }
}
