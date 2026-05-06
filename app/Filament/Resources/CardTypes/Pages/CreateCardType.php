<?php

namespace App\Filament\Resources\CardTypes\Pages;

use App\Filament\Resources\CardTypes\CardTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCardType extends CreateRecord
{
    protected static string $resource = CardTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
