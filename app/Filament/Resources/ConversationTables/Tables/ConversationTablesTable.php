<?php

namespace App\Filament\Resources\ConversationTables\Tables;

use App\Actions\Session\CancelSessionAction;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\Level;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationTablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('topic')
                    ->label('Sujet')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('level.code')
                    ->label('Niveau')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // Compteur inscrits / max — lit registered_count injecté par getEloquentQuery()
                TextColumn::make('registered_count')
                    ->label('Inscrits')
                    ->formatStateUsing(
                        fn ($state, ConversationTable $record) =>
                            ($state ?? 0) . ' / ' . $record->max_participants
                    )
                    ->color(fn ($state, ConversationTable $record): string => match (true) {
                        ($state ?? 0) >= $record->max_participants         => 'danger',
                        ($state ?? 0) >= (int) ($record->max_participants * 0.75) => 'warning',
                        default                                             => 'success',
                    })
                    ->sortable(),

                // Compteur liste d'attente — lit waitlist_count injecté par getEloquentQuery()
                TextColumn::make('waitlist_count')
                    ->label('Attente')
                    ->formatStateUsing(fn ($state) => $state ?? 0)
                    ->color(fn ($state): string => ($state ?? 0) > 0 ? 'warning' : 'gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (SessionStatus $state) => $state->label())
                    ->color(fn (SessionStatus $state) => $state->color()),
            ])
            ->filters([
                SelectFilter::make('level_id')
                    ->label('Niveau')
                    ->options(Level::orderBy('sort_order')->pluck('code', 'id')),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(
                        collect(SessionStatus::cases())
                            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                    ),

                SelectFilter::make('period')
                    ->label('Période')
                    ->options([
                        'upcoming' => 'À venir',
                        'past'     => 'Passées',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'upcoming' => $query->where('scheduled_at', '>=', now()),
                        'past'     => $query->where('scheduled_at', '<', now()),
                        default    => $query,
                    }),
            ])
            ->recordActions([
                Action::make('voir_inscrits')
                    ->label('Inscrits')
                    ->icon(Heroicon::OutlinedUsers)
                    ->color('info')
                    ->modalHeading(
                        fn (ConversationTable $record) =>
                            'Inscrits — ' . $record->topic . ' (' . $record->scheduled_at->format('d/m/Y') . ')'
                    )
                    ->modalContent(
                        fn (ConversationTable $record) => view(
                            'filament.modals.registrations-list',
                            ['table' => $record]
                        )
                    )
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),

                Action::make('cancel_session')
                    ->label('Annuler')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->modalHeading(fn (ConversationTable $record) =>
                        'Annuler la session du ' . $record->scheduled_at->format('d/m/Y') . ' ?'
                    )
                    ->modalDescription('Cette action est irréversible. Tous les inscrits seront notifiés et leurs séances recréditées si applicable.')
                    ->form([
                        Textarea::make('reason')
                            ->label("Raison de l'annulation")
                            ->required()
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(function (ConversationTable $record, array $data): void {
                        app(CancelSessionAction::class)->execute($record, auth()->user(), $data['reason']);
                    })
                    ->successNotificationTitle('Session annulée avec succès')
                    ->visible(fn (ConversationTable $record): bool =>
                        $record->status === SessionStatus::Scheduled && $record->scheduled_at->isFuture()
                    ),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('scheduled_at', 'asc');
    }
}
