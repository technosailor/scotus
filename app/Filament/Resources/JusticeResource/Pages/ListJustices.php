<?php

namespace App\Filament\Resources\JusticeResource\Pages;

use App\Filament\Resources\JusticeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJustices extends ListRecords
{
    protected static string $resource = JusticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
