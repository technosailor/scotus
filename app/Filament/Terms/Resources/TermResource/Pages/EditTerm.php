<?php

namespace App\Filament\Terms\Resources\TermResource\Pages;

use App\Filament\Terms\Resources\TermResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTerm extends EditRecord
{
    protected static string $resource = TermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
