<?php

namespace App\Filament\Resources\SupremeCourtCaseResource\Pages;

use App\Filament\Resources\SupremeCourtCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSupremeCourtCase extends ViewRecord
{
    protected static string $resource = SupremeCourtCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
