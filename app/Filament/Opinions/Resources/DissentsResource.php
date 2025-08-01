<?php

namespace App\Filament\Opinions\Resources;

use App\Models\Opinion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DissentsResource extends Resource
{
    protected static ?string $model = Opinion::class;

    protected static ?string $navigationIcon = 'heroicon-o-x-circle';
    
    protected static ?string $navigationLabel = 'Dissenting Opinions';
    
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('opinion_type', 'dissent');
    }

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
                Tables\Columns\TextColumn::make('vote')
                    ->label('Vote')
                    ->searchable()
                    ->badge()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('case.decision_date')
                    ->label('Decision Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('case.term.year')
                    ->label('Term')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('justice')
                    ->relationship('justice', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('case.term')
                    ->relationship('case.term', 'year')
                    ->searchable()
                    ->preload()
                    ->label('Term'),
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

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Opinions\Resources\DissentsResource\Pages\ListDissents::route('/'),
        ];
    }
}