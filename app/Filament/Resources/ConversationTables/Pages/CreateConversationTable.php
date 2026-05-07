<?php

namespace App\Filament\Resources\ConversationTables\Pages;

use App\Filament\Resources\ConversationTables\ConversationTableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConversationTable extends CreateRecord
{
    protected static string $resource = ConversationTableResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
