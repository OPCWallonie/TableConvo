<?php

namespace App\Filament\Resources\CardTypes\Pages;

use App\Filament\Resources\CardTypes\CardTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCardTypes extends ListRecords
{
    protected static string $resource = CardTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
