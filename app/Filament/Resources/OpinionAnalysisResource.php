<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OpinionAnalysisResource\Pages;
use App\Services\OpinionAnalysisService;
use App\Services\RedisOpinionService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Collection;

class OpinionAnalysisResource extends Resource
{
    protected static ?string $model = null; // We don't use Eloquent models

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationLabel = 'Opinion Analysis';
    
    protected static ?string $slug = 'opinion-analysis';
    
    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->query(\App\Models\Opinion::query()->whereRaw('1 = 0')) // Dummy query
            ->columns([
                Tables\Columns\TextColumn::make('case_name')
                    ->label('Case Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('justice_name')
                    ->label('Justice')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('opinion_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'dissent' => 'danger',
                        'concurrence' => 'warning',
                        'majority' => 'success',
                        'plurality' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sentiment')
                    ->label('Sentiment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'positive' => 'success',
                        'negative' => 'danger',
                        'neutral' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sentiment_score')
                    ->label('Score')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                Tables\Columns\TextColumn::make('judicial_philosophy')
                    ->label('Philosophy')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'conservative' => 'danger',
                        'liberal' => 'info',
                        'moderate' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('historical_significance')
                    ->label('Significance')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'success',
                        'medium' => 'warning',
                        'low' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('case_decision_date')
                    ->label('Decision Date')
                    ->date('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('opinion_type')
                    ->options([
                        'dissent' => 'Dissent',
                        'concurrence' => 'Concurrence',
                        'majority' => 'Majority',
                        'plurality' => 'Plurality',
                    ]),
                Tables\Filters\SelectFilter::make('sentiment')
                    ->options([
                        'positive' => 'Positive',
                        'negative' => 'Negative',
                        'neutral' => 'Neutral',
                    ]),
                Tables\Filters\SelectFilter::make('judicial_philosophy')
                    ->options([
                        'conservative' => 'Conservative',
                        'liberal' => 'Liberal', 
                        'moderate' => 'Moderate',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Opinion Analysis Details')
                    ->modalContent(fn ($record) => view('filament.resources.opinion-analysis.view-analysis', compact('record'))),
            ])
            ->headerActions([
                Tables\Actions\Action::make('runAnalysis')
                    ->label('Run LLM Analysis')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('primary')
                    ->action(function () {
                        // This would trigger the analysis command
                        redirect()->route('filament.admin.pages.run-analysis');
                    }),
                Tables\Actions\Action::make('viewStatistics')
                    ->label('View Statistics')
                    ->icon('heroicon-o-chart-pie')
                    ->color('info')
                    ->action(function () {
                        redirect()->route('filament.admin.pages.analysis-statistics');
                    }),
            ])
            ->defaultSort('case_decision_date', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Opinion Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('case_name')
                            ->label('Case Name'),
                        Infolists\Components\TextEntry::make('justice_name')
                            ->label('Justice'),
                        Infolists\Components\TextEntry::make('opinion_type')
                            ->label('Opinion Type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('case_decision_date')
                            ->label('Decision Date')
                            ->date(),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Analysis Results')
                    ->schema([
                        Infolists\Components\TextEntry::make('sentiment')
                            ->label('Sentiment')
                            ->badge(),
                        Infolists\Components\TextEntry::make('sentiment_score')
                            ->label('Sentiment Score')
                            ->numeric(decimalPlaces: 3),
                        Infolists\Components\TextEntry::make('confidence')
                            ->label('Confidence')
                            ->numeric(decimalPlaces: 3),
                        Infolists\Components\TextEntry::make('judicial_philosophy')
                            ->label('Judicial Philosophy')
                            ->badge(),
                        Infolists\Components\TextEntry::make('tone')
                            ->label('Tone'),
                        Infolists\Components\TextEntry::make('complexity')
                            ->label('Complexity')
                            ->badge(),
                        Infolists\Components\TextEntry::make('historical_significance')
                            ->label('Historical Significance')
                            ->badge(),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Topics and Themes')
                    ->schema([
                        Infolists\Components\TextEntry::make('key_topics')
                            ->label('Key Topics')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                        Infolists\Components\TextEntry::make('legal_themes')
                            ->label('Legal Themes')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                    ]),
                
                Infolists\Components\Section::make('Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('summary')
                            ->label('Analysis Summary')
                            ->prose(),
                    ]),
                
                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\TextEntry::make('analyzed_at')
                            ->label('Analyzed At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('analysis_method')
                            ->label('Analysis Method'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpinionAnalysis::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Analysis is generated, not created manually
    }

    public static function canEdit($record): bool
    {
        return false; // Analysis results are read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Don't allow deletion of analysis results
    }
}