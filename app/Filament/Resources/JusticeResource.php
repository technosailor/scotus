<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JusticeResource\Pages;
use App\Filament\Resources\JusticeResource\RelationManagers;
use App\Models\Justice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class JusticeResource extends Resource
{
    protected static ?string $model = Justice::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    
    protected static ?string $navigationLabel = 'Justices';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('oyez_id')
                    ->required(),
                Forms\Components\TextInput::make('identifier')
                    ->required(),
                Forms\Components\TextInput::make('first_name')
                    ->required(),
                Forms\Components\TextInput::make('last_name')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('thumbnail_url')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('length_of_service')
                    ->numeric(),
                Forms\Components\Textarea::make('href')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('view_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('roles')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('length_of_service')
                    ->label('Years of Service')
                    ->formatStateUsing(function (?int $state): string {
                        if (!$state) return 'N/A';
                        
                        $years = floor($state / 365);
                        $months = floor(($state % 365) / 30);
                        $days = $state % 30;
                        
                        $parts = [];
                        if ($years > 0) $parts[] = $years . ' year' . ($years !== 1 ? 's' : '');
                        if ($months > 0) $parts[] = $months . ' month' . ($months !== 1 ? 's' : '');
                        if ($days > 0) $parts[] = $days . ' day' . ($days !== 1 ? 's' : '');
                        
                        return !empty($parts) ? implode(', ', $parts) : '0 days';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('view_count')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListJustices::route('/'),
            'create' => Pages\CreateJustice::route('/create'),
            'view' => Pages\ViewJustice::route('/{record}'),
            'edit' => Pages\EditJustice::route('/{record}/edit'),
        ];
    }
}
