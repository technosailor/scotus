<?php

namespace App\Filament\Resources\OpinionAnalysisResource\Pages;

use App\Filament\Resources\OpinionAnalysisResource;
use App\Services\OpinionAnalysisService;
use App\Services\RedisOpinionService;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class ListOpinionAnalysis extends ListRecords
{
    protected static string $resource = OpinionAnalysisResource::class;

    public function __construct()
    {
        parent::__construct();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('runQuickAnalysis')
                ->label('Quick Analysis')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->action(function () {
                    // Run a quick analysis of 10 opinions
                    $this->runQuickAnalysis();
                })
                ->requiresConfirmation()
                ->modalHeading('Run Quick Analysis')
                ->modalDescription('This will analyze 10 sample opinions using LLM. It may take a few minutes.')
                ->modalSubmitActionLabel('Start Analysis'),
                
            Actions\Action::make('viewStatistics')
                ->label('View Statistics')
                ->icon('heroicon-o-chart-pie')
                ->color('info')
                ->url(fn () => route('filament.admin.pages.analysis-dashboard')),
                
            Actions\Action::make('clearCache')
                ->label('Clear Analysis Cache')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->action(function () {
                    $service = app(OpinionAnalysisService::class);
                    $deleted = $service->clearAnalysisCache();
                    
                    $this->notify('success', "Cleared {$deleted} cached analysis entries");
                })
                ->requiresConfirmation(),
        ];
    }

    /**
     * Override to provide custom data from Redis analysis
     */
    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Return a dummy query - we'll override getTableRecords instead
        return \App\Models\Opinion::query()->whereRaw('1 = 0');
    }

    /**
     * Get analyzed opinion data from Redis/cache
     */
    public function getTableRecords(): Paginator
    {
        $page = request('page', 1);
        $perPage = request('tableRecordsPerPage', 25);
        
        // Get sample analyzed data - in a real implementation, you'd fetch from cache/Redis
        $analysisData = $this->getSampleAnalysisData();
        
        // Paginate the results
        $offset = ($page - 1) * $perPage;
        $items = array_slice($analysisData, $offset, $perPage);
        
        return new LengthAwarePaginator(
            collect($items)->map(fn($item) => (object) $item),
            count($analysisData),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    private function runQuickAnalysis(): void
    {
        try {
            $analysisService = app(OpinionAnalysisService::class);
            $redisService = app(RedisOpinionService::class);
            
            // Get 10 sample opinions
            $opinions = $redisService->getOpinionsByType('dissent')->take(5)
                ->concat($redisService->getOpinionsByType('concurrence')->take(5));
            
            if ($opinions->isEmpty()) {
                $this->notify('warning', 'No opinions found in Redis for analysis');
                return;
            }
            
            // Run analysis
            $results = $analysisService->analyzeBatch($opinions, 3);
            
            $this->notify('success', 'Quick analysis completed! Analyzed ' . count($results) . ' opinions.');
            
        } catch (\Exception $e) {
            $this->notify('danger', 'Analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Get sample analysis data for demonstration
     * In a real implementation, this would fetch from Redis/cache
     */
    private function getSampleAnalysisData(): array
    {
        return [
            [
                'id' => 1,
                'case_name' => 'Roe v. Wade',
                'justice_name' => 'Harry Blackmun',
                'opinion_type' => 'majority',
                'sentiment' => 'neutral',
                'sentiment_score' => 0.65,
                'confidence' => 0.85,
                'judicial_philosophy' => 'liberal',
                'tone' => 'analytical',
                'complexity' => 'high',
                'historical_significance' => 'high',
                'key_topics' => ['reproductive_rights', 'privacy', 'constitutional_law'],
                'legal_themes' => ['due_process', 'privacy_rights', 'state_power'],
                'summary' => 'Landmark decision establishing constitutional right to abortion based on privacy rights.',
                'case_decision_date' => '1973-01-22',
                'analyzed_at' => now()->subDays(1)->toISOString(),
                'analysis_method' => 'llm'
            ],
            [
                'id' => 2,
                'case_name' => 'Brown v. Board of Education',
                'justice_name' => 'Earl Warren',
                'opinion_type' => 'majority',
                'sentiment' => 'positive',
                'sentiment_score' => 0.78,
                'confidence' => 0.92,
                'judicial_philosophy' => 'liberal',
                'tone' => 'decisive',
                'complexity' => 'high',
                'historical_significance' => 'high',
                'key_topics' => ['civil_rights', 'education', 'segregation'],
                'legal_themes' => ['equal_protection', 'civil_rights', 'constitutional_interpretation'],
                'summary' => 'Unanimous decision declaring racial segregation in public schools unconstitutional.',
                'case_decision_date' => '1954-05-17',
                'analyzed_at' => now()->subDays(2)->toISOString(),
                'analysis_method' => 'llm'
            ],
            [
                'id' => 3,
                'case_name' => 'Scalia Dissent Example',
                'justice_name' => 'Antonin Scalia',
                'opinion_type' => 'dissent',
                'sentiment' => 'negative',
                'sentiment_score' => 0.25,
                'confidence' => 0.88,
                'judicial_philosophy' => 'conservative',
                'tone' => 'passionate',
                'complexity' => 'high',
                'historical_significance' => 'medium',
                'key_topics' => ['originalism', 'constitutional_interpretation', 'judicial_restraint'],
                'legal_themes' => ['originalism', 'textualism', 'separation_of_powers'],
                'summary' => 'Strong dissent emphasizing originalist interpretation and judicial restraint.',
                'case_decision_date' => '2010-06-28',
                'analyzed_at' => now()->subHours(6)->toISOString(),
                'analysis_method' => 'llm'
            ],
        ];
    }
}