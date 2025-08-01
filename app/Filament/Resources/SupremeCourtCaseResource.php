<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupremeCourtCaseResource\Pages;
use App\Filament\Resources\SupremeCourtCaseResource\RelationManagers;
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
                Forms\Components\TextInput::make('oyez_id'),
                Forms\Components\TextInput::make('case_name')
                    ->required(),
                Forms\Components\TextInput::make('docket_number'),
                Forms\Components\DatePicker::make('decision_date')
                    ->required(),
                Forms\Components\TextInput::make('term_id')
                    ->required()
                    ->numeric(),
                Forms\Components\Textarea::make('href')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('summary')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('facts')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('question')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('conclusion')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sentiment_score')
                    ->numeric(),
                Forms\Components\TextInput::make('majority_opinion_author'),
                Forms\Components\Textarea::make('concurring_justices')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('dissenting_justices')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('raw_data')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('unique_hash'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('oyez_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('case_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('docket_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('decision_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('term_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sentiment_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('majority_opinion_author')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('unique_hash')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'view' => Pages\ViewSupremeCourtCase::route('/{record}'),
            'edit' => Pages\EditSupremeCourtCase::route('/{record}/edit'),
        ];
    }
}
