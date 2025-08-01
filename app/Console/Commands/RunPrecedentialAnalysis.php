<?php

namespace App\Console\Commands;

use App\Services\PrecedentialAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RunPrecedentialAnalysis extends Command
{
    protected $signature = 'precedential:analyze 
                          {--type=all : Type of analysis to run (all, citations, language, topics)}
                          {--clear-cache : Clear existing analysis cache}
                          {--limit=50 : Limit number of items to process for testing}';

    protected $description = 'Run comprehensive precedential analysis on Supreme Court cases and opinions';

    public function __construct(
        private PrecedentialAnalysisService $analysisService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type');
        $clearCache = $this->option('clear-cache');
        
        if ($clearCache) {
            $this->info('Clearing analysis cache...');
            Cache::forget('precedential_analysis:precedential_importance');
            Cache::forget('precedential_analysis:justice_language_patterns');
            Cache::forget('precedential_analysis:major_topics');
        }

        $this->info("Starting precedential analysis (type: {$type})");
        $startTime = microtime(true);

        try {
            switch ($type) {
                case 'citations':
                    $this->runCitationAnalysis();
                    break;
                case 'language':
                    $this->runLanguageAnalysis();
                    break;
                case 'topics':
                    $this->runTopicAnalysis();
                    break;
                case 'all':
                default:
                    $this->runCitationAnalysis();
                    $this->runLanguageAnalysis();
                    $this->runTopicAnalysis();
                    break;
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("✅ Precedential analysis completed in {$duration} seconds");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Analysis failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function runCitationAnalysis(): void
    {
        $this->info('🔍 Analyzing case citations and precedential importance...');
        
        $results = $this->analysisService->analyzePrecedentialImportance();
        
        $this->info("Found {$results['analysis_metadata']['total_cases_analyzed']} cases with {$results['analysis_metadata']['citation_relationships']} citation relationships");
        
        $this->info('📊 Top 10 Most Precedential Cases:');
        $count = 0;
        foreach ($results['major_precedential_cases'] as $caseName => $data) {
            if (++$count > 10) break;
            
            $this->line("  {$count}. {$caseName}");
            $this->line("     Precedential Score: {$data['precedential_score']}");
            $this->line("     Times Cited: {$data['times_cited']}");
            $this->line("     Classification: {$data['classification']}");
            $this->line("     Legal Significance: {$data['legal_significance']}");
            $this->line('');
        }
    }

    private function runLanguageAnalysis(): void
    {
        $this->info('📝 Analyzing Justice language patterns...');
        
        $results = $this->analysisService->analyzeJusticeLanguagePatterns();
        
        $this->info('🏆 Justice Language Analysis Results:');
        
        // Most prolific writers
        $this->info('Most Prolific Opinion Writers:');
        $count = 0;
        foreach ($results['comparative_analysis']['most_prolific'] as $justice => $opinions) {
            if (++$count > 5) break;
            $this->line("  {$count}. {$justice}: {$opinions} opinions");
        }
        
        $this->line('');
        
        // Most complex language
        $this->info('Most Complex Language Users:');
        $count = 0;
        foreach ($results['comparative_analysis']['most_complex_language'] as $justice => $complexity) {
            if (++$count > 5) break;
            $this->line("  {$count}. {$justice}: {$complexity}% complexity score");
        }
        
        $this->line('');
        
        // Dissent specialists
        $this->info('Dissent Specialists:');
        $count = 0;
        foreach ($results['comparative_analysis']['dissent_specialists'] as $justice => $rate) {
            if (++$count > 5) break;
            $this->line("  {$count}. {$justice}: {$rate}% dissent rate");
        }
    }

    private function runTopicAnalysis(): void
    {
        $this->info('🎯 Analyzing major legal topics...');
        
        $results = $this->analysisService->extractMajorTopics();
        
        $this->info('📈 Major Topic Trends:');
        
        $sortedTopics = $results['topics'];
        uasort($sortedTopics, function($a, $b) {
            return $b['frequency'] <=> $a['frequency'];
        });
        
        $count = 0;
        foreach ($sortedTopics as $topic => $data) {
            if (++$count > 10) break;
            
            $trend = $results['topic_trends'][$topic]['trend_direction'] ?? 'stable';
            $peakDecade = $results['topic_trends'][$topic]['peak_decade'] ?? 'unknown';
            
            $this->line("  {$count}. {$topic}");
            $this->line("     Frequency: {$data['frequency']} cases");
            $this->line("     Trend: {$trend}");
            $this->line("     Peak Decade: {$peakDecade}s");
            
            // Show some example cases
            $exampleCases = array_slice($data['cases'], 0, 3);
            if (!empty($exampleCases)) {
                $this->line("     Notable Cases: " . implode(', ', array_column($exampleCases, 'name')));
            }
            $this->line('');
        }
    }
}