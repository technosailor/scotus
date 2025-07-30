<?php

namespace App\Console\Commands;

use App\Models\HistoricalRecord;
use App\Models\ImportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportHistoricalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'historical:import {file : Path to JSON file} {--chunk=1000 : Records per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical JSON data into the database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');

        if (!Storage::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting import from: {$filePath}");

        $importLog = ImportLog::create([
            'filename' => basename($filePath),
            'records_imported' => 0,
            'records_failed' => 0,
            'status' => 'processing',
            'imported_by' => 1, // Default user or get from auth
        ]);

        try {
            $jsonContent = Storage::get($filePath);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON: ' . json_last_error_msg());
            }

            $totalRecords = count($data);
            $this->info("Found {$totalRecords} records to import");

            $progressBar = $this->output->createProgressBar($totalRecords);
            $progressBar->start();

            $imported = 0;
            $failed = 0;
            $errors = [];

            $chunks = array_chunk($data, $chunkSize);

            foreach ($chunks as $chunk) {
                DB::beginTransaction();

                try {
                    foreach ($chunk as $record) {
                        try {
                            $this->importRecord($record);
                            $imported++;
                        } catch (\Exception $e) {
                            $failed++;
                            $errors[] = [
                                'record' => $record,
                                'error' => $e->getMessage(),
                            ];
                        }

                        $progressBar->advance();
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Chunk import failed: " . $e->getMessage());
                }
            }

            $progressBar->finish();
            $this->newLine();

            $importLog->update([
                'records_imported' => $imported,
                'records_failed' => $failed,
                'errors' => $failed > 0 ? $errors : null,
                'status' => 'completed',
            ]);

            $this->info("Import completed!");
            $this->info("Imported: {$imported} records");

            if ($failed > 0) {
                $this->warn("Failed: {$failed} records");
            }

            return 0;

        } catch (\Exception $e) {
            $importLog->update([
                'status' => 'failed',
                'errors' => [['error' => $e->getMessage()]],
            ]);

            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Import a single record.
     *
     * @param array $record
     * @return void
     * @throws \Exception
     */
    private function importRecord(array $record): void
    {
        // Validate required fields
        $required = ['year', 'category', 'region'];
        foreach ($required as $field) {
            if (!isset($record[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Extract standard fields
        $standardFields = [
            'year' => (int) $record['year'],
            'category' => $record['category'],
            'subcategory' => $record['subcategory'] ?? null,
            'region' => $record['region'],
            'country' => $record['country'] ?? null,
            'primary_value' => $record['value'] ?? $record['primary_value'] ?? null,
            'unit' => $record['unit'] ?? null,
            'notes' => $record['notes'] ?? null,
            'source' => $record['source'] ?? null,
        ];

        // Store any additional fields in JSON
        $additionalData = array_diff_key($record, $standardFields);
        $standardFields['data'] = $additionalData;

        HistoricalRecord::create($standardFields);
    }
}
