<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\GlobalWaitlistEntry;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make()
                ->before(function ($record, $action): void {
                    $blocking = [];

                    if ($record->orders()->withTrashed()->exists()) {
                        $blocking[] = 'commandes';
                    }

                    if ($record->registrations()->withTrashed()->exists()) {
                        $blocking[] = 'inscriptions';
                    }

                    if ($record->cards()->withTrashed()->exists()) {
                        $blocking[] = 'cartes';
                    }

                    if (GlobalWaitlistEntry::withTrashed()->where('created_by', $record->id)->exists()) {
                        $blocking[] = 'entrées vivier créées par cet utilisateur';
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
                            'Vous pouvez en revanche utiliser la suppression simple qui archive l\'utilisateur sans détruire son historique.'
                        )
                        ->persistent()
                        ->send();

                    $action->cancel();
                }),
            RestoreAction::make(),
        ];
    }
}
