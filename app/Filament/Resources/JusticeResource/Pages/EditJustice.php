<?php

namespace App\Filament\Resources\JusticeResource\Pages;

use App\Filament\Resources\JusticeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJustice extends EditRecord
{
    protected static string $resource = JusticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
