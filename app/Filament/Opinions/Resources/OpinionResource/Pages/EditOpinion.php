<?php

namespace App\Filament\Opinions\Resources\OpinionResource\Pages;

use App\Filament\Opinions\Resources\OpinionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOpinion extends EditRecord
{
    protected static string $resource = OpinionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
