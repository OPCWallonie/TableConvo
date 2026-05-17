<?php

namespace App\Filament\Resources\GlobalWaitlistPool\Tables;

use App\Actions\GlobalWaitlist\DismissGlobalWaitlistEntryAction;
use App\Actions\GlobalWaitlist\FindCompatibleSessionsForGlobalEntryAction;
use App\Actions\GlobalWaitlist\ReassignFromGlobalWaitlistAction;
use App\Enums\GlobalWaitlistSource;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GlobalWaitlistPoolTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requested_at')
                    ->label('En attente depuis')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (GlobalWaitlistEntry $record): string => $record->requested_at->diffForHumans()),

                TextColumn::make('user.full_name')
                    ->label('Personne')
                    ->searchable(['users.first_name', 'users.last_name']),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('level.code')
                    ->label('Niveau souhaité')
                    ->badge()
                    ->color('info'),

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (GlobalWaitlistSource $state): string => $state->label())
                    ->color(fn (GlobalWaitlistSource $state): string => $state->color()),

                TextColumn::make('admin_reason')
                    ->label('Raison admin')
                    ->limit(40)
                    ->tooltip(fn (GlobalWaitlistEntry $record): ?string =>
                        strlen((string) $record->admin_reason) > 40 ? $record->admin_reason : null
                    ),

                TextColumn::make('createdBy.full_name')
                    ->label('Créé par')
                    ->toggleable(),
            ])
            ->defaultSort('requested_at', 'asc')
            ->filters([
                SelectFilter::make('level_id')
                    ->label('Niveau')
                    ->options(fn () => Level::orderBy('sort_order')->pluck('code', 'id')),

                SelectFilter::make('source')
                    ->label('Source')
                    ->options(
                        collect(GlobalWaitlistSource::cases())
                            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                    ),

                Filter::make('requested_at')
                    ->label("Période d'entrée")
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Du'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q) => $q->whereDate('requested_at', '>=', $data['from']))
                        ->when($data['to'] ?? null, fn ($q) => $q->whereDate('requested_at', '<=', $data['to']))
                    ),

                SelectFilter::make('created_by')
                    ->label('Créé par')
                    ->options(fn () => User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                        ->pluck('first_name', 'id')
                    ),
            ])
            ->recordActions([
                Action::make('reassign')
                    ->label('Réassigner')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('primary')
                    ->visible(fn (GlobalWaitlistEntry $record) =>
                        app(FindCompatibleSessionsForGlobalEntryAction::class)->execute($record)->isNotEmpty()
                    )
                    ->modalHeading(fn (GlobalWaitlistEntry $record): string =>
                        "Réassigner {$record->user->full_name}"
                    )
                    ->form(fn (GlobalWaitlistEntry $record): array => [
                        Select::make('target_table_id')
                            ->label('Session cible')
                            ->required()
                            ->options(
                                app(FindCompatibleSessionsForGlobalEntryAction::class)
                                    ->execute($record)
                                    ->mapWithKeys(fn (ConversationTable $t): array => [
                                        $t->id => sprintf(
                                            '%s — %s · %s',
                                            $t->scheduled_at->translatedFormat('d M H:i'),
                                            $t->topic,
                                            $t->registered_count < $t->max_participants
                                                ? ($t->max_participants - $t->registered_count) . ' place(s) libre(s)'
                                                : 'complet, ' . $t->waitlist_count . ' en attente'
                                        ),
                                    ])
                                    ->toArray()
                            ),
                    ])
                    ->action(function (GlobalWaitlistEntry $record, array $data): void {
                        $target = ConversationTable::findOrFail($data['target_table_id']);
                        $registration = app(ReassignFromGlobalWaitlistAction::class)
                            ->execute($record, $target, auth()->user());
                        Notification::make()
                            ->title("Personne réassignée vers « {$target->topic} »")
                            ->success()
                            ->send();
                    }),

                Action::make('dismiss')
                    ->label('Retirer du vivier')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->modalHeading(fn (GlobalWaitlistEntry $record): string =>
                        "Retirer {$record->user->full_name} du vivier"
                    )
                    ->form([
                        Textarea::make('reason')
                            ->label('Motif')
                            ->required()
                            ->minLength(3)
                            ->maxLength(500)
                            ->helperText('Ce motif sera enregistré dans le journal d\'audit.'),
                    ])
                    ->action(function (GlobalWaitlistEntry $record, array $data): void {
                        $result = app(DismissGlobalWaitlistEntryAction::class)
                            ->execute($record, auth()->user(), $data['reason'], false);
                        Notification::make()
                            ->title("{$record->user->full_name} retiré(e) du vivier")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Aucune personne au vivier')
            ->emptyStateDescription('Le vivier est utilisé quand aucune session compatible n\'est disponible au moment d\'un retrait.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
}
