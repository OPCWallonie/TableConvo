<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('invoice.invoice_number')
                    ->label('N° facture')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('invoice', fn ($q) => $q->where('invoice_number', 'like', "%{$search}%"))
                    )
                    ->placeholder('—'),

                TextColumn::make('user.full_name')
                    ->label('Client')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('user', fn ($q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                        )
                    )
                    ->sortable(),

                TextColumn::make('company_snapshot')
                    ->label('Société')
                    ->getStateUsing(fn (Order $record): string => data_get($record->company_snapshot, 'name', '—'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_ht')
                    ->label('Montant HT')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('total_ttc')
                    ->label('Montant TTC')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),

                TextColumn::make('paid_at')
                    ->label('Payée le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(
                        collect(OrderStatus::cases())
                            ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])
                    ),

                Filter::make('current_month')
                    ->label('Ce mois')
                    ->query(fn (Builder $query) => $query
                        ->whereMonth('paid_at', now()->month)
                        ->whereYear('paid_at', now()->year)
                    ),

                Filter::make('last_12_months')
                    ->label('12 derniers mois')
                    ->query(fn (Builder $query) => $query
                        ->where('paid_at', '>=', now()->subMonths(12))
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
