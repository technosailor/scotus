<?php

namespace App\Filament\Opinions\Resources\OpinionResource\Pages;

use App\Filament\Opinions\Resources\OpinionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDissents extends ListRecords
{
    protected static string $resource = OpinionResource::class;
    
    protected static ?string $title = 'Dissenting Opinions';
    
    protected static ?string $navigationLabel = 'Dissents';
    
    protected static ?string $navigationIcon = 'heroicon-o-x-circle';
    
    protected static ?int $navigationSort = 3;
    
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
        return parent::getTableQuery()->where('opinion_type', 'dissent');
    }
}