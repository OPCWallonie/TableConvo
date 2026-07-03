<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Actions\Company\AssignCompanyAdminAction;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reassignAdmin')
                ->label('Réassigner l\'administrateur')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('primary')
                ->form([
                    Select::make('new_admin_id')
                        ->label('Nouveau administrateur')
                        ->options(fn () => $this->getRecord()
                            ->members()
                            ->get()
                            ->pluck('full_name', 'id')
                        )
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    app(AssignCompanyAdminAction::class)->execute(
                        actor: auth()->user(),
                        company: $this->getRecord(),
                        newAdmin: User::findOrFail($data['new_admin_id']),
                    );

                    Notification::make()
                        ->title('Administrateur de la société réassigné.')
                        ->success()
                        ->send();
                })
                ->visible(fn () => auth()->user()?->hasRole('admin')),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
