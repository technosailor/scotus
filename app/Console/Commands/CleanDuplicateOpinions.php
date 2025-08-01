<?php

namespace App\Console\Commands;

use App\Models\Opinion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanDuplicateOpinions extends Command
{
    protected $signature = 'opinions:clean-duplicates 
                          {--dry-run : Show what would be cleaned without making changes}
                          {--batch-size=1000 : Number of records to process in each batch}';

    protected $description = 'Clean duplicate opinion records';

    public function handle(): int
    {
        $this->info('Starting duplicate opinion cleanup...');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Count total duplicates first
        $totalDuplicateCount = DB::table('opinions')
            ->select('case_id', 'justice_id', 'opinion_type', 'vote', DB::raw('COUNT(*) as count'))
            ->groupBy('case_id', 'justice_id', 'opinion_type', 'vote')
            ->having('count', '>', 1)
            ->count();
            
        $this->info("Found approximately {$totalDuplicateCount} groups with duplicates");
        
        if ($isDryRun) {
            // Show some sample duplicates without loading all into memory
            $sampleGroups = DB::table('opinions')
                ->select('case_id', 'justice_id', 'opinion_type', 'vote', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'))
                ->groupBy('case_id', 'justice_id', 'opinion_type', 'vote')
                ->having('count', '>', 1)
                ->limit(10)
                ->get();
                
            $this->table(
                ['Case ID', 'Justice ID', 'Opinion Type', 'Vote', 'Count', 'Keep ID'],
                $sampleGroups->map(function ($group) {
                    return [
                        $group->case_id,
                        $group->justice_id,
                        $group->opinion_type,
                        $group->vote,
                        $group->count,
                        $group->keep_id
                    ];
                })->toArray()
            );
            
            $this->info('Dry run completed. Use --no-dry-run to actually clean the duplicates.');
            return 0;
        }

        // Clean duplicates using direct SQL for better performance
        $cleaned = 0;
        $errors = 0;

        try {
            // Create a temporary table with unique records (keep oldest ID per group)
            DB::statement("
                CREATE TEMPORARY TABLE opinions_unique AS
                SELECT MIN(id) as keep_id, case_id, justice_id, opinion_type, vote
                FROM opinions
                GROUP BY case_id, justice_id, opinion_type, vote
            ");
            
            // Delete all records that are not in the unique list
            $cleaned = DB::delete("
                DELETE FROM opinions 
                WHERE id NOT IN (SELECT keep_id FROM opinions_unique)
            ");
            
            // Clean up temp table
            DB::statement("DROP TABLE opinions_unique");
            
        } catch (\Exception $e) {
            $this->error("Error during cleanup: " . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info("Cleanup completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Records cleaned', $cleaned],
                ['Errors', $errors],
            ]
        );

        // Show final stats
        $remainingDuplicates = Opinion::select('case_id', 'justice_id', 'opinion_type', 'vote', DB::raw('COUNT(*) as count'))
            ->groupBy('case_id', 'justice_id', 'opinion_type', 'vote')
            ->having('count', '>', 1)
            ->count();

        $this->info("Remaining duplicate groups: {$remainingDuplicates}");

        return 0;
    }
}