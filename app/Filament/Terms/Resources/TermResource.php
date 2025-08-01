<?php

namespace App\Filament\Terms\Resources;

use App\Filament\Terms\Resources\TermResource\Pages;
use App\Filament\Terms\Resources\TermResource\RelationManagers;
use App\Models\Term;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TermResource extends Resource
{
    protected static ?string $model = Term::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = 'Court Terms';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('year')
                    ->required()
                    ->numeric()
                    ->minValue(1789)
                    ->maxValue(2030),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('term_start')
                    ->label('Term Start Date'),
                Forms\Components\DatePicker::make('term_end')
                    ->label('Term End Date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('year')
                    ->label('Term Year')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Term Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('term_start')
                    ->label('Start Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('term_end')
                    ->label('End Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cases_count')
                    ->label('Cases Decided')
                    ->counts('cases')
                    ->sortable()
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_cases')
                    ->label('Terms with Cases')
                    ->query(fn (Builder $query): Builder => $query->has('cases'))
                    ->default(),
                Tables\Filters\Filter::make('year_range')
                    ->form([
                        Forms\Components\TextInput::make('from_year')
                            ->numeric()
                            ->label('From Year'),
                        Forms\Components\TextInput::make('to_year')
                            ->numeric()
                            ->label('To Year'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_year'],
                                fn (Builder $query, $year): Builder => $query->where('year', '>=', $year),
                            )
                            ->when(
                                $data['to_year'],
                                fn (Builder $query, $year): Builder => $query->where('year', '<=', $year),
                            );
                    })
            ])
            ->defaultSort('year', 'desc')
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
            'index' => Pages\ListTerms::route('/'),
            'create' => Pages\CreateTerm::route('/create'),
            'edit' => Pages\EditTerm::route('/{record}/edit'),
        ];
    }
}
