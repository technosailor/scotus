<?php

namespace App\Filament\Resources\OpinionResource\Pages;

use App\Filament\Resources\OpinionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOpinion extends EditRecord
{
    protected static string $resource = OpinionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
