<?php

namespace App\Filament\Resources\ConversationTables\Pages;

use App\Filament\Resources\ConversationTables\ConversationTableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConversationTables extends ListRecords
{
    protected static string $resource = ConversationTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
