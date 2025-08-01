<?php

namespace App\Filament\Cases\Resources;

use App\Filament\Cases\Resources\SupremeCourtCaseResource\Pages;
use App\Filament\Cases\Resources\SupremeCourtCaseResource\RelationManagers;
use App\Models\SupremeCourtCase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupremeCourtCaseResource extends Resource
{
    protected static ?string $model = SupremeCourtCase::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'All Cases';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('case_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('docket_number')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('decision_date')
                    ->required(),
                Forms\Components\Select::make('term_id')
                    ->relationship('term', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Textarea::make('summary')
                    ->rows(3),
                Forms\Components\TextInput::make('majority_opinion_author')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('case_name')
                    ->label('Case Name')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('docket_number')
                    ->label('Docket #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('decision_date')
                    ->label('Decision Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('term.year')
                    ->label('Term')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('majority_opinion_author')
                    ->label('Majority Author')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('opinions_count')
                    ->label('Total Opinions')
                    ->counts('opinions')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('term')
                    ->relationship('term', 'year')
                    ->searchable()
                    ->preload(),
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
                                fn (Builder $query, $date): Builder => $query->whereDate('decision_date', '>=', $date),
                            )
                            ->when(
                                $data['decided_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('decision_date', '<=', $date),
                            );
                    })
            ])
            ->defaultSort('decision_date', 'desc')
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
            'index' => Pages\ListSupremeCourtCases::route('/'),
            'create' => Pages\CreateSupremeCourtCase::route('/create'),
            'edit' => Pages\EditSupremeCourtCase::route('/{record}/edit'),
        ];
    }
}
