<?php

namespace App\Filament\Cases\Resources\SupremeCourtCaseResource\Pages;

use App\Filament\Cases\Resources\SupremeCourtCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupremeCourtCase extends EditRecord
{
    protected static string $resource = SupremeCourtCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
