<?php

namespace App\Filament\Resources\ConversationTables\Pages;

use App\Filament\Resources\ConversationTables\ConversationTableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConversationTable extends EditRecord
{
    protected static string $resource = ConversationTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
