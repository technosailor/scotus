<?php

namespace App\Filament\Presidents\Resources\PresidentResource\Pages;

use App\Filament\Presidents\Resources\PresidentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPresidents extends ListRecords
{
    protected static string $resource = PresidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
