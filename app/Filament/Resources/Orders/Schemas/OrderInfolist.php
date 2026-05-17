<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informations commande')
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Date')
                        ->dateTime('d/m/Y H:i'),

                    TextEntry::make('invoice.invoice_number')
                        ->label('N° facture')
                        ->placeholder('Non générée'),

                    TextEntry::make('user.full_name')
                        ->label('Client'),

                    TextEntry::make('status')
                        ->label('Statut')
                        ->badge()
                        ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                        ->color(fn (OrderStatus $state): string => $state->color()),
                ]),

            Section::make('Société (au moment de la commande)')
                ->columns(2)
                ->schema([
                    TextEntry::make('company_name')
                        ->label('Société')
                        ->getStateUsing(fn (Order $record): string => data_get($record->company_snapshot, 'name', '—')),

                    TextEntry::make('company_vat')
                        ->label('N° TVA')
                        ->getStateUsing(fn (Order $record): string => data_get($record->company_snapshot, 'vat_number', '—')),

                    TextEntry::make('company_street')
                        ->label('Adresse')
                        ->getStateUsing(fn (Order $record): string => data_get($record->company_snapshot, 'street', '—')),

                    TextEntry::make('company_city')
                        ->label('Localité')
                        ->getStateUsing(fn (Order $record): string =>
                            trim(data_get($record->company_snapshot, 'postal_code', '') . ' ' . data_get($record->company_snapshot, 'city', '')) ?: '—'
                        ),
                ]),

            Section::make('Détails financiers')
                ->columns(3)
                ->schema([
                    TextEntry::make('total_ht')
                        ->label('Total HT')
                        ->money('EUR'),

                    TextEntry::make('total_vat')
                        ->label('TVA')
                        ->money('EUR'),

                    TextEntry::make('total_ttc')
                        ->label('Total TTC')
                        ->money('EUR'),

                    TextEntry::make('paid_at')
                        ->label('Payée le')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('Non payée'),

                    TextEntry::make('mollie_payment_id')
                        ->label('Référence Mollie')
                        ->placeholder('—'),
                ]),
        ]);
    }
}
