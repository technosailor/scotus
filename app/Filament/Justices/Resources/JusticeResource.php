<?php

namespace App\Filament\Justices\Resources;

use App\Filament\Justices\Resources\JusticeResource\Pages;
use App\Filament\Justices\Resources\JusticeResource\RelationManagers;
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
    
    protected static ?string $navigationLabel = 'Supreme Court Justices';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('identifier')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('oyez_id')
                            ->label('Oyez ID')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('length_of_service')
                            ->numeric()
                            ->suffix('days'),
                        Forms\Components\TextInput::make('thumbnail_url')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('view_count')
                            ->numeric()
                            ->default(0),
                    ]),
                Forms\Components\Textarea::make('href')
                    ->columnSpanFull()
                    ->rows(2),
                Forms\Components\KeyValue::make('roles')
                    ->columnSpanFull()
                    ->label('Court Roles and Appointments'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('Photo')
                    ->circular()
                    ->size(50),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('identifier')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('length_of_service')
                    ->numeric()
                    ->sortable()
                    ->suffix(' days'),
                Tables\Columns\TextColumn::make('opinions_count')
                    ->getStateUsing(fn (Justice $record): int => $record->opinions()->count())
                    ->label('Total Opinions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('appointing_president')
                    ->options(function () {
                        return \App\Models\Justice::query()
                            ->get()
                            ->flatMap(fn($justice) => collect($justice->roles)->pluck('appointing_president'))
                            ->unique()
                            ->filter()
                            ->mapWithKeys(fn($president) => [$president => $president]);
                    })
                    ->query(function ($query, array $data) {
                        if (filled($data['value'])) {
                            $query->whereJsonContains('roles', [['appointing_president' => $data['value']]]);
                        }
                    }),
            ])
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
            'index' => Pages\ListJustices::route('/'),
            'create' => Pages\CreateJustice::route('/create'),
            'edit' => Pages\EditJustice::route('/{record}/edit'),
        ];
    }
}
