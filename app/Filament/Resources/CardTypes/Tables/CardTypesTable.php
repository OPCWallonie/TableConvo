<?php

namespace App\Filament\Resources\CardTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CardTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sessions_count')
                    ->label('Sessions')
                    ->sortable()
                    ->suffix(' sessions'),
                TextColumn::make('price')
                    ->label('Prix TTC')
                    ->sortable()
                    ->money('EUR'),
                TextColumn::make('validity_months')
                    ->label('Validité')
                    ->sortable()
                    ->suffix(' mois'),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Actif'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records, $action): void {
                            $total = $records->count();
                            $protectedCount = 0;

                            foreach ($records as $record) {
                                if (
                                    $record->cards()->withTrashed()->exists() ||
                                    $record->orderItems()->exists()
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
                ]),
            ])
            ->defaultSort('price');
    }
}
