<?php

namespace App\Filament\Resources\Waitlist\Tables;

use App\Actions\Registration\FindEligibleTargetSessionsAction;
use App\Actions\Registration\MoveRegistrationAction;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WaitlistTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('En attente depuis')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (Registration $record): string => $record->created_at->diffForHumans()),

                TextColumn::make('user.full_name')
                    ->label('Personne')
                    ->searchable(['users.first_name', 'users.last_name']),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('conversationTable.topic')
                    ->label('Session')
                    ->limit(40),

                TextColumn::make('conversationTable.scheduled_at')
                    ->label('Date session')
                    ->dateTime('d/m H:i')
                    ->sortable(),

                TextColumn::make('conversationTable.level.code')
                    ->label('Niveau')
                    ->badge()
                    ->color('info'),

                TextColumn::make('waitlist_position')
                    ->label('Position')
                    ->state(fn (Registration $record): string => '#' . $record->waitlist_position)
                    ->alignCenter()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                SelectFilter::make('level')
                    ->label('Niveau')
                    ->options(fn () => Level::orderBy('sort_order')->pluck('code', 'id'))
                    ->query(fn (Builder $query, array $data) =>
                        filled($data['value'] ?? null)
                            ? $query->whereHas('conversationTable', fn ($q) => $q->where('level_id', $data['value']))
                            : $query
                    ),

                Filter::make('waiting_since')
                    ->label('En attente depuis le')
                    ->form([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('to')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['to'] ?? null, fn ($q) => $q->whereDate('created_at', '<=', $data['to']))
                    ),

                Filter::make('session_date')
                    ->label('Date de session')
                    ->form([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('to')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q) => $q->whereHas('conversationTable', fn ($ct) =>
                            $ct->whereDate('scheduled_at', '>=', $data['from'])
                        ))
                        ->when($data['to'] ?? null, fn ($q) => $q->whereHas('conversationTable', fn ($ct) =>
                            $ct->whereDate('scheduled_at', '<=', $data['to'])
                        ))
                    ),
            ])
            ->recordActions([
                Action::make('redirect')
                    ->label('Réorienter')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('primary')
                    ->visible(fn (Registration $record) =>
                        app(FindEligibleTargetSessionsAction::class)->execute($record)->isNotEmpty()
                    )
                    ->modalHeading(fn (Registration $record) =>
                        "Réorienter {$record->user->full_name}"
                    )
                    ->modalDescription(fn (Registration $record) =>
                        "Inscription actuelle : {$record->conversationTable->topic} du {$record->conversationTable->scheduled_at->translatedFormat('d F Y')}. La personne sera placée en queue de la liste d'attente de la session choisie."
                    )
                    ->form(fn (Registration $record) => [
                        Select::make('target_table_id')
                            ->label('Réorienter vers')
                            ->required()
                            ->helperText('Sessions futures du même niveau. Les places libres apparaissent en premier.')
                            ->options(
                                app(FindEligibleTargetSessionsAction::class)
                                    ->execute($record)
                                    ->mapWithKeys(function (ConversationTable $t): array {
                                        $free  = $t->max_participants - $t->registered_count;
                                        $label = sprintf(
                                            '%s — %s · %s',
                                            $t->scheduled_at->translatedFormat('d M H:i'),
                                            $t->topic,
                                            $free > 0
                                                ? "{$free} place" . ($free > 1 ? 's' : '') . " libre" . ($free > 1 ? 's' : '')
                                                : "complet, {$t->waitlist_count} en attente"
                                        );
                                        return [$t->id => $label];
                                    })
                                    ->toArray()
                            ),
                    ])
                    ->action(function (Registration $record, array $data): void {
                        $target = ConversationTable::findOrFail($data['target_table_id']);
                        app(MoveRegistrationAction::class)->execute(
                            $record,
                            $target,
                            auth()->user(),
                            'admin_redirect'
                        );
                        Notification::make()
                            ->title('Personne réorientée')
                            ->body("{$record->user->full_name} a été réorienté(e) vers « {$target->topic} ».")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Aucune personne en liste d\'attente')
            ->emptyStateDescription('Toutes les sessions ont des places disponibles ou aucune inscription en attente.')
            ->emptyStateIcon(Heroicon::OutlinedClock);
    }
}
