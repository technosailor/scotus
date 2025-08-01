<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OpinionResource\Pages;
use App\Filament\Resources\OpinionResource\RelationManagers;
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

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    
    protected static ?string $navigationLabel = 'All Opinions';

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
                Tables\Columns\TextColumn::make('opinion_type')
                    ->label('Opinion Type')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : 'N/A')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vote')
                    ->label('Vote')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords($state) : 'N/A')
                    ->searchable()
                    ->sortable()
                    ->badge(),
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('ideology_score')
                    ->label('Ideology Score')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListOpinions::route('/'),
            'create' => Pages\CreateOpinion::route('/create'),
            'view' => Pages\ViewOpinion::route('/{record}'),
            'edit' => Pages\EditOpinion::route('/{record}/edit'),
        ];
    }
}
