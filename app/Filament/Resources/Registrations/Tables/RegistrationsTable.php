<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Enums\RegistrationStatus;
use App\Models\Level;
use App\Models\Registration;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registered_at')
                    ->label('Date inscription')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('user.full_name')
                    ->label('Personne')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('user', fn ($q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                        )
                    )
                    ->sortable(),

                TextColumn::make('conversationTable.topic')
                    ->label('Session')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('conversationTable', fn ($q) => $q->where('topic', 'like', "%{$search}%"))
                    )
                    ->sortable(),

                TextColumn::make('conversationTable.scheduled_at')
                    ->label('Date session')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('conversationTable.level.code')
                    ->label('Niveau')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),

                TextColumn::make('waitlist_position')
                    ->label('Position')
                    ->formatStateUsing(fn (?int $state): string => $state ? "#{$state}" : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('card_id')
                    ->label('Carte')
                    ->formatStateUsing(fn (?int $state): string => $state ? "Carte #{$state}" : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('registered_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(
                        collect(RegistrationStatus::cases())
                            ->mapWithKeys(fn (RegistrationStatus $s) => [$s->value => $s->label()])
                    ),

                Filter::make('session_future')
                    ->label('Session à venir')
                    ->query(fn (Builder $query) => $query->whereHas('conversationTable',
                        fn ($q) => $q->where('scheduled_at', '>', now())
                    )),

                Filter::make('session_past_30_days')
                    ->label('Session des 30 derniers jours')
                    ->query(fn (Builder $query) => $query->whereHas('conversationTable',
                        fn ($q) => $q->where('scheduled_at', '>', now()->subDays(30))
                                     ->where('scheduled_at', '<=', now())
                    )),

                SelectFilter::make('level_id')
                    ->label('Niveau')
                    ->options(fn () => Level::active()->pluck('code', 'id')->toArray())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $value) => $q->whereHas('conversationTable', fn ($q2) => $q2->where('level_id', $value))
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
