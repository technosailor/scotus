<?php

namespace App\Filament\Resources\SupremeCourtCaseResource\Pages;

use App\Filament\Resources\SupremeCourtCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupremeCourtCases extends ListRecords
{
    protected static string $resource = SupremeCourtCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
