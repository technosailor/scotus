<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConcurringResource\Pages;
use App\Models\Opinion;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConcurringResource extends Resource
{
    protected static ?string $model = Opinion::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    
    protected static ?string $navigationLabel = 'Concurring Opinions';
    
    protected static ?string $slug = 'concurring-opinions';
    
    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('opinion_type', 'concurrence');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('case.case_name')
                    ->label('Case Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->url(fn (Opinion $record): ?string => $record->case?->raw_data['justia_url'] ?? null)
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->extraAttributes(['class' => 'underline hover:no-underline']),
                Tables\Columns\TextColumn::make('justice.name')
                    ->label('Justice')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('vote')
                    ->label('Vote')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords($state) : 'N/A')
                    ->searchable()
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('case.decision_date')
                    ->label('Decision Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('case.term.year')
                    ->label('Term')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('seniority')
                    ->label('Seniority Rank')
                    ->formatStateUsing(function (?int $state): string {
                        if (!$state) return 'N/A';
                        
                        $suffix = match($state % 10) {
                            1 => $state % 100 === 11 ? 'th' : 'st',
                            2 => $state % 100 === 12 ? 'th' : 'nd', 
                            3 => $state % 100 === 13 ? 'th' : 'rd',
                            default => 'th'
                        };
                        
                        return $state . $suffix . ' most senior';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('justice')
                    ->relationship('justice', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('case.decision_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConcurring::route('/'),
        ];
    }
}