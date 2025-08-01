<?php

namespace App\Filament\Opinions\Resources;

use App\Filament\Opinions\Resources\OpinionResource\Pages;
use App\Filament\Opinions\Resources\OpinionResource\RelationManagers;
use App\Models\Opinion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OpinionResource extends Resource
{
    protected static ?string $model = Opinion::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    
    protected static ?string $navigationLabel = 'All Opinions';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('case_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('justice_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('opinion_type')
                    ->required(),
                Forms\Components\TextInput::make('vote')
                    ->required(),
                Forms\Components\Textarea::make('opinion_text')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sentiment_score')
                    ->numeric(),
                Forms\Components\TextInput::make('ideology_score')
                    ->numeric(),
                Forms\Components\TextInput::make('seniority')
                    ->numeric(),
                Forms\Components\Textarea::make('joining_justices')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('oyez_href')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('case.case_name')
                    ->label('Case Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('justice.name')
                    ->label('Justice')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('opinion_type')
                    ->label('Opinion Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'majority' => 'success',
                        'concurring' => 'warning', 
                        'dissent' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('vote')
                    ->label('Vote')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'majority' => 'success',
                        'minority' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('case.decision_date')
                    ->label('Decision Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('case.term.year')
                    ->label('Term')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('sentiment_score')
                    ->label('Sentiment')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ideology_score')
                    ->label('Ideology')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('opinion_type')
                    ->label('Opinion Type')
                    ->options([
                        'majority' => 'Majority',
                        'concurring' => 'Concurring',
                        'dissent' => 'Dissent',
                    ]),
                Tables\Filters\SelectFilter::make('justice')
                    ->relationship('justice', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('case.term')
                    ->relationship('case.term', 'year')
                    ->searchable()
                    ->preload()
                    ->label('Term'),
                Tables\Filters\Filter::make('decision_date')
                    ->form([
                        Forms\Components\DatePicker::make('decided_from')
                            ->label('Decided From'),
                        Forms\Components\DatePicker::make('decided_until')
                            ->label('Decided Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['decided_from'],
                                fn (Builder $query, $date): Builder => $query->whereHas('case', fn ($q) => $q->whereDate('decision_date', '>=', $date)),
                            )
                            ->when(
                                $data['decided_until'],
                                fn (Builder $query, $date): Builder => $query->whereHas('case', fn ($q) => $q->whereDate('decision_date', '<=', $date)),
                            );
                    })
            ])
            ->defaultSort('case.decision_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpinions::route('/'),
            'dissents' => Pages\ListDissents::route('/dissents'),
            'concurring' => Pages\ListConcurring::route('/concurring'),
            'create' => Pages\CreateOpinion::route('/create'),
            'edit' => Pages\EditOpinion::route('/{record}/edit'),
        ];
    }
}
