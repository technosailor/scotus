<?php

namespace App\Filament\Resources\SupremeCourtCaseResource\Pages;

use App\Filament\Resources\SupremeCourtCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupremeCourtCase extends EditRecord
{
    protected static string $resource = SupremeCourtCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
