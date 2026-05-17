<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Lignes de commande';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cardType.name')
                    ->label('Type de carte'),

                TextColumn::make('quantity')
                    ->label('Quantité'),

                TextColumn::make('unit_price_ht')
                    ->label('Prix unitaire HT')
                    ->money('EUR'),

                TextColumn::make('total_ht')
                    ->label('Total HT')
                    ->money('EUR'),

                TextColumn::make('total_ttc')
                    ->label('Total TTC')
                    ->money('EUR')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
