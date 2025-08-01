<?php

namespace App\Console\Commands;

use App\Services\OpinionAnalysisService;
use App\Services\RedisOpinionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AnalyzeOpinionsCommand extends Command
{
    protected $signature = 'opinions:analyze 
                          {--type=all : Opinion type to analyze (all, dissent, concurrence, majority, plurality)}
                          {--limit=50 : Maximum number of opinions to analyze}
                          {--batch-size=5 : Number of opinions to analyze in each batch}
                          {--theme=general : Theme for thematic analysis}
                          {--clear-cache : Clear existing analysis cache before starting}
                          {--sentiment-only : Only generate sentiment distribution}
                          {--thematic-only : Only generate thematic analysis}';

    protected $description = 'Analyze Supreme Court opinions using LLM for sentiment, topics, and themes';

    public function __construct(
        private OpinionAnalysisService $analysisService,
        private RedisOpinionService $redisService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Supreme Court opinion analysis...');
        $this->newLine();

        $type = $this->option('type');
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch-size');
        $theme = $this->option('theme');
        $clearCache = $this->option('clear-cache');
        $sentimentOnly = $this->option('sentiment-only');
        $thematicOnly = $this->option('thematic-only');

        // Clear cache if requested
        if ($clearCache) {
            $this->info('Clearing analysis cache...');
            $deleted = $this->analysisService->clearAnalysisCache();
            $this->info("Cleared {$deleted} cached analysis entries");
            $this->newLine();
        }

        // Show current Redis statistics
        $stats = $this->redisService->getStatistics();
        $this->displayStatistics($stats);

        // Generate sentiment distribution only
        if ($sentimentOnly) {
            return $this->generateSentimentDistribution();
        }

        // Generate thematic analysis only
        if ($thematicOnly) {
            return $this->generateThematicAnalysis($type, $limit, $theme);
        }

        // Full analysis
        return $this->runFullAnalysis($type, $limit, $batchSize);
    }

    private function runFullAnalysis(string $type, int $limit, int $batchSize): int
    {
        $this->info("Analyzing opinions (type: {$type}, limit: {$limit}, batch size: {$batchSize})");
        $this->newLine();

        try {
            if ($type === 'all') {
                return $this->analyzeAllTypes($limit, $batchSize);
            } else {
                return $this->analyzeSpecificType($type, $limit, $batchSize);
            }
        } catch (\Exception $e) {
            $this->error('Analysis failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function analyzeAllTypes(int $limit, int $batchSize): int
    {
        $types = ['dissent', 'concurrence', 'majority', 'plurality'];
        $perTypeLimit = (int) ceil($limit / count($types));
        
        $allResults = [];
        
        foreach ($types as $type) {
            $this->info("Analyzing {$type} opinions (limit: {$perTypeLimit})...");
            
            $opinions = $this->redisService->getOpinionsByType($type)->take($perTypeLimit);
            if ($opinions->isEmpty()) {
                $this->warn("No {$type} opinions found in Redis");
                continue;
            }
            
            $results = $this->analysisService->analyzeBatch($opinions, $batchSize);
            $allResults[$type] = $results;
            
            $this->displayAnalysisResults($type, $results);
        }
        
        // Generate summary
        $this->generateAnalysisSummary($allResults);
        
        return 0;
    }

    private function analyzeSpecificType(string $type, int $limit, int $batchSize): int
    {
        $this->info("Analyzing {$type} opinions...");
        
        $opinions = $this->redisService->getOpinionsByType($type)->take($limit);
        
        if ($opinions->isEmpty()) {
            $this->error("No {$type} opinions found in Redis");
            return 1;
        }
        
        $this->info("Found {$opinions->count()} {$type} opinions to analyze");
        
        $progressBar = $this->output->createProgressBar($opinions->count());
        $progressBar->start();
        
        $results = $this->analysisService->analyzeBatch($opinions, $batchSize);
        
        $progressBar->finish();
        $this->newLine();
        $this->newLine();
        
        $this->displayAnalysisResults($type, $results);
        
        return 0;
    }

    private function generateSentimentDistribution(): int
    {
        $this->info('Generating sentiment distribution analysis...');
        $this->newLine();
        
        $distribution = $this->analysisService->getSentimentDistribution();
        
        foreach ($distribution as $type => $data) {
            $this->info("=== {$type} Opinions ===");
            $this->info("Total analyzed: {$data['total_analyzed']}");
            $this->info("Average sentiment score: " . number_format($data['average_sentiment_score'], 3));
            
            $this->table(
                ['Sentiment', 'Count', 'Percentage'],
                collect($data['sentiments'])->map(function ($count, $sentiment) use ($data) {
                    $percentage = round(($count / $data['total_analyzed']) * 100, 1);
                    return [$sentiment, $count, $percentage . '%'];
                })->values()->toArray()
            );
            
            $this->newLine();
        }
        
        return 0;
    }

    private function generateThematicAnalysis(string $type, int $limit, string $theme): int
    {
        $this->info("Generating thematic analysis for {$type} opinions (theme: {$theme})...");
        $this->newLine();
        
        if ($type === 'all') {
            $opinions = collect();
            foreach (['dissent', 'concurrence', 'majority', 'plurality'] as $opinionType) {
                $typeOpinions = $this->redisService->getOpinionsByType($opinionType)->take($limit / 4);
                $opinions = $opinions->concat($typeOpinions);
            }
        } else {
            $opinions = $this->redisService->getOpinionsByType($type)->take($limit);
        }
        
        if ($opinions->isEmpty()) {
            $this->error("No opinions found for thematic analysis");
            return 1;
        }
        
        $analysis = $this->analysisService->generateThematicAnalysis($opinions, $theme);
        
        if (isset($analysis['error'])) {
            $this->error("Thematic analysis failed: " . $analysis['error']);
            return 1;
        }
        
        $this->displayThematicAnalysis($analysis);
        
        return 0;
    }

    private function displayStatistics(array $stats): void
    {
        $this->info('Current Redis Opinion Statistics:');
        $this->table(
            ['Opinion Type', 'Count'],
            [
                ['Total Opinions', number_format($stats['total_opinions'])],
                ['Dissenting Opinions', number_format($stats['dissenting_opinions'])],
                ['Concurring Opinions', number_format($stats['concurring_opinions'])],
                ['Majority Opinions', number_format($stats['majority_opinions'])],
                ['Plurality Opinions', number_format($stats['plurality_opinions'])],
            ]
        );
        $this->newLine();
    }

    private function displayAnalysisResults(string $type, array $results): void
    {
        if (empty($results)) {
            $this->warn("No analysis results for {$type} opinions");
            return;
        }

        $this->info("=== {$type} Opinion Analysis Results ===");
        
        // Aggregate statistics
        $sentiments = collect($results)->pluck('sentiment')->countBy();
        $avgSentimentScore = collect($results)->avg('sentiment_score');
        $avgConfidence = collect($results)->avg('confidence');
        $topTopics = collect($results)->flatMap(fn($r) => $r['key_topics'] ?? [])->countBy()->sortDesc()->take(5);
        $philosophies = collect($results)->pluck('judicial_philosophy')->countBy();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Analyzed', count($results)],
                ['Average Sentiment Score', number_format($avgSentimentScore, 3)],
                ['Average Confidence', number_format($avgConfidence, 3)],
                ['Most Common Topic', $topTopics->keys()->first() ?? 'N/A'],
                ['Dominant Philosophy', $philosophies->keys()->first() ?? 'N/A'],
            ]
        );
        
        $this->newLine();
        
        // Sentiment distribution
        $this->info('Sentiment Distribution:');
        $this->table(
            ['Sentiment', 'Count', 'Percentage'],
            $sentiments->map(function ($count, $sentiment) use ($results) {
                $percentage = round(($count / count($results)) * 100, 1);
                return [$sentiment, $count, $percentage . '%'];
            })->values()->toArray()
        );
        
        $this->newLine();
        
        // Top topics
        if ($topTopics->isNotEmpty()) {
            $this->info('Top Topics:');
            $this->table(
                ['Topic', 'Mentions'],
                $topTopics->map(fn($count, $topic) => [$topic, $count])->toArray()
            );
            $this->newLine();
        }
        
        // Sample summaries
        $this->info('Sample Analysis Summaries:');
        collect($results)->take(3)->each(function ($analysis, $opinionId) {
            $this->line("Opinion {$opinionId}: " . ($analysis['summary'] ?? 'No summary available'));
        });
        
        $this->newLine();
    }

    private function displayThematicAnalysis(array $analysis): void
    {
        $this->info("=== Thematic Analysis: {$analysis['theme']} ===");
        $this->newLine();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Theme', $analysis['theme']],
                ['Opinions Analyzed', $analysis['opinion_count']],
                ['Time Period', $analysis['time_period']['earliest'] . ' to ' . $analysis['time_period']['latest']],
                ['Generated At', $analysis['generated_at']],
            ]
        );
        
        $this->newLine();
        
        // Opinion type distribution
        if (isset($analysis['opinion_types'])) {
            $this->info('Opinion Type Distribution:');
            $this->table(
                ['Type', 'Count'],
                collect($analysis['opinion_types'])->map(fn($count, $type) => [$type, $count])->toArray()
            );
            $this->newLine();
        }
        
        // Cases analyzed
        if (isset($analysis['cases_analyzed'])) {
            $this->info('Sample Cases Analyzed:');
            foreach ($analysis['cases_analyzed'] as $case) {
                $this->line("- {$case}");
            }
            $this->newLine();
        }
        
        // Main analysis
        $this->info('Analysis:');
        $this->line($analysis['analysis']);
        $this->newLine();
    }

    private function generateAnalysisSummary(array $allResults): void
    {
        $this->info('=== Overall Analysis Summary ===');
        
        $totalAnalyzed = array_sum(array_map('count', $allResults));
        $allSentiments = collect($allResults)->flatMap(fn($results) => collect($results)->pluck('sentiment'))->countBy();
        $allTopics = collect($allResults)->flatMap(fn($results) => collect($results)->flatMap(fn($r) => $r['key_topics'] ?? []))->countBy()->sortDesc()->take(10);
        
        $this->info("Total opinions analyzed: {$totalAnalyzed}");
        $this->newLine();
        
        $this->info('Overall Sentiment Distribution:');
        $this->table(
            ['Sentiment', 'Count', 'Percentage'],
            $allSentiments->map(function ($count, $sentiment) use ($totalAnalyzed) {
                $percentage = round(($count / $totalAnalyzed) * 100, 1);
                return [$sentiment, $count, $percentage . '%'];
            })->values()->toArray()
        );
        
        $this->newLine();
        
        $this->info('Top Topics Across All Opinion Types:');
        $this->table(
            ['Topic', 'Mentions'],
            $allTopics->take(10)->map(fn($count, $topic) => [$topic, $count])->toArray()
        );
        
        $this->newLine();
        $this->info('Analysis complete! Results have been cached for future use.');
    }
}