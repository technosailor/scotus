<?php

namespace App\Filament\Presidents\Resources\PresidentResource\Pages;

use App\Filament\Presidents\Resources\PresidentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePresident extends CreateRecord
{
    protected static string $resource = PresidentResource::class;
}
