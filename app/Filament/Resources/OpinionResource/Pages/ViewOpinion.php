<?php

namespace App\Filament\Resources\OpinionResource\Pages;

use App\Filament\Resources\OpinionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOpinion extends ViewRecord
{
    protected static string $resource = OpinionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
