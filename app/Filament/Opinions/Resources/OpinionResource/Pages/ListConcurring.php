<?php

namespace App\Filament\Opinions\Resources\OpinionResource\Pages;

use App\Filament\Opinions\Resources\OpinionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListConcurring extends ListRecords
{
    protected static string $resource = OpinionResource::class;
    
    protected static ?string $title = 'Concurring Opinions';
    
    protected static ?string $navigationLabel = 'Concurring';
    
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    
    protected static ?int $navigationSort = 2;
    
    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    
    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->where('opinion_type', 'concurring');
    }
}