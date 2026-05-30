<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Models\CompanyJoinRequest;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PendingJoinRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'joinRequests';

    protected static ?string $title = 'Demandes d\'adhésion';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('user')->orderBy('requested_at', 'desc'))
            ->columns([
                TextColumn::make('user.full_name')
                    ->label('Demandeur')
                    ->getStateUsing(fn (CompanyJoinRequest $record): string => $record->user->full_name),
                TextColumn::make('user.email')
                    ->label('E-mail')
                    ->getStateUsing(fn (CompanyJoinRequest $record): string => $record->user->email),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state->value ?? $state) {
                        'pending'   => 'En attente',
                        'approved'  => 'Approuvée',
                        'rejected'  => 'Rejetée',
                        'cancelled' => 'Annulée',
                        default     => $state,
                    })
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        'pending'   => 'warning',
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        'cancelled' => 'gray',
                        default     => 'gray',
                    }),
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('requested_at')
                    ->label('Demandé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc');
    }
}
