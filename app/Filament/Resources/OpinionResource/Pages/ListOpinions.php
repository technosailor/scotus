<?php

namespace App\Filament\Resources\OpinionResource\Pages;

use App\Filament\Resources\OpinionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOpinions extends ListRecords
{
    protected static string $resource = OpinionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
