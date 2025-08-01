<?php

namespace App\Console\Commands;

use App\Models\Opinion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDuplicateConcurrenceOpinions extends Command
{
    protected $signature = 'opinions:clean-concurrence-duplicates 
                          {--dry-run : Show what would be deleted without making changes}';

    protected $description = 'Remove duplicate concurrence opinions, keeping the oldest record by ID';

    public function handle(): int
    {
        $this->info('Starting duplicate concurrence opinion cleanup...');
        $this->newLine();

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Show initial statistics
        $totalConcurrence = Opinion::where('opinion_type', 'concurrence')->count();
        $this->info("Total concurrence opinions: " . number_format($totalConcurrence));

        // Find duplicate groups
        $duplicateGroups = Opinion::where('opinion_type', 'concurrence')
            ->selectRaw('case_id, justice_id, opinion_type, COUNT(*) as duplicate_count, MIN(id) as keep_id')
            ->groupBy('case_id', 'justice_id', 'opinion_type')
            ->having('duplicate_count', '>', 1)
            ->orderBy('duplicate_count', 'desc')
            ->get();

        $totalDuplicateGroups = $duplicateGroups->count();
        $this->info("Duplicate groups found: " . number_format($totalDuplicateGroups));

        if ($totalDuplicateGroups === 0) {
            $this->info('No duplicates found!');
            return 0;
        }

        // Show sample duplicates
        $this->newLine();
        $this->info('Sample duplicate groups:');
        $this->table(
            ['Case ID', 'Justice ID', 'Count', 'Keep ID'],
            $duplicateGroups->take(10)->map(function ($group) {
                return [
                    $group->case_id,
                    $group->justice_id,
                    $group->duplicate_count,
                    $group->keep_id,
                ];
            })->toArray()
        );

        if ($isDryRun) {
            // Calculate what would be deleted
            $totalToDelete = 0;
            foreach ($duplicateGroups as $group) {
                $totalToDelete += ($group->duplicate_count - 1); // Keep 1, delete the rest
            }
            
            $this->newLine();
            $this->info("Would delete " . number_format($totalToDelete) . " duplicate records");
            $this->info("Would keep " . number_format($totalConcurrence - $totalToDelete) . " unique records");
            $this->info('Use --no-dry-run to actually perform the cleanup');
            return 0;
        }

        // Confirm before proceeding
        if (!$this->confirm('Do you want to proceed with deleting duplicate concurrence opinions?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->newLine();
        $this->info('Deleting duplicate concurrence opinions...');

        $deletedCount = 0;
        $progressBar = $this->output->createProgressBar($totalDuplicateGroups);
        $progressBar->start();

        foreach ($duplicateGroups as $group) {
            // Delete all duplicates except the one with the minimum ID
            $deleted = Opinion::where('case_id', $group->case_id)
                ->where('justice_id', $group->justice_id)
                ->where('opinion_type', 'concurrence')
                ->where('id', '>', $group->keep_id) // Keep the record with minimum ID
                ->delete();
            
            $deletedCount += $deleted;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->newLine();

        // Show final statistics
        $finalCount = Opinion::where('opinion_type', 'concurrence')->count();
        
        $this->info('Cleanup completed successfully!');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Initial Concurrence Opinions', number_format($totalConcurrence)],
                ['Duplicate Groups Found', number_format($totalDuplicateGroups)],
                ['Records Deleted', number_format($deletedCount)],
                ['Final Concurrence Opinions', number_format($finalCount)],
                ['Records Saved', number_format($totalConcurrence - $deletedCount)],
            ]
        );

        // Verify no duplicates remain
        $remainingDuplicates = Opinion::where('opinion_type', 'concurrence')
            ->selectRaw('case_id, justice_id, opinion_type, COUNT(*) as count')
            ->groupBy('case_id', 'justice_id', 'opinion_type')
            ->having('count', '>', 1)
            ->count();

        if ($remainingDuplicates === 0) {
            $this->info('✅ All duplicates successfully removed!');
        } else {
            $this->warn("⚠️  {$remainingDuplicates} duplicate groups still remain");
        }

        return 0;
    }
}