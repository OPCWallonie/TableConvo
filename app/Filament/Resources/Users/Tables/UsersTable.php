<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\User\AnonymizeUserAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name'])
                    ->getStateUsing(fn ($record) => $record->full_name),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('company.name')
                    ->label('Société')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level.code')
                    ->label('Niveau')
                    ->badge()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_name')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('anonymize')
                    ->label('Anonymiser')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anonymiser le compte')
                    ->modalDescription('Cette action est irréversible. Les données personnelles (nom, prénom, e-mail, téléphone) seront définitivement effacées. Les factures sont conservées.')
                    ->modalSubmitActionLabel('Confirmer l\'anonymisation')
                    ->visible(fn (User $record) => auth()->user()?->can('anonymize', $record))
                    ->action(function (User $record): void {
                        app(AnonymizeUserAction::class)->execute($record, performedBy: auth()->user());

                        Notification::make()
                            ->title('Compte anonymisé.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->before(function ($records, $action): void {
                            $total = $records->count();
                            $protectedCount = 0;

                            foreach ($records as $record) {
                                if (
                                    $record->orders()->withTrashed()->exists() ||
                                    $record->registrations()->withTrashed()->exists() ||
                                    $record->cards()->withTrashed()->exists() ||
                                    \App\Models\GlobalWaitlistEntry::withTrashed()->where('created_by', $record->id)->exists()
                                ) {
                                    $protectedCount++;
                                }
                            }

                            if ($protectedCount === 0) {
                                return;
                            }

                            Notification::make()
                                ->danger()
                                ->title('Suppression impossible')
                                ->body(
                                    "{$protectedCount} enregistrement(s) sur {$total} ne peuvent pas être supprimés " .
                                    'car ils sont référencés ailleurs. ' .
                                    'L\'opération a été annulée pour préserver l\'intégrité des données.'
                                )
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
