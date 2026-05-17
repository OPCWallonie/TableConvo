<?php

namespace App\Filament\Resources\CardTypes\Pages;

use App\Filament\Resources\CardTypes\CardTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCardType extends EditRecord
{
    protected static string $resource = CardTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function ($record, $action): void {
                    $blocking = [];

                    if ($record->cards()->withTrashed()->exists()) {
                        $blocking[] = 'cartes vendues';
                    }

                    if ($record->orderItems()->exists()) {
                        $blocking[] = 'lignes de commandes';
                    }

                    if (empty($blocking)) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Suppression impossible')
                        ->body(
                            'Cet élément est référencé par : ' . implode(', ', $blocking) . '. ' .
                            'Pour des raisons de traçabilité, la suppression n\'est pas autorisée. ' .
                            'Vous pouvez en revanche renommer ce type de carte ou contacter le support pour archivage.'
                        )
                        ->persistent()
                        ->send();

                    $action->cancel();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
