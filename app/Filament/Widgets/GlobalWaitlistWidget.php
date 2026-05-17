<?php

namespace App\Filament\Widgets;

use App\Actions\GlobalWaitlist\DismissGlobalWaitlistEntryAction;
use App\Actions\GlobalWaitlist\FindCompatibleSessionsForGlobalEntryAction;
use App\Actions\GlobalWaitlist\ReassignFromGlobalWaitlistAction;
use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\GlobalWaitlistSource;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class GlobalWaitlistWidget extends BaseWidget
{
    protected string $view = 'filament.widgets.global-waitlist-widget';

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Vivier global d\'attente';

    protected static ?string $subheading = 'Personnes en attente sans session';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected function getTableQuery(): Builder
    {
        return GlobalWaitlistEntry::query()
            ->where('status', GlobalWaitlistEntryStatus::Pending)
            ->with(['user', 'level'])
            ->orderBy('requested_at', 'asc')
            ->limit(10);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getTableQuery())
            ->columns([
                TextColumn::make('user.full_name')
                    ->label('Personne'),

                TextColumn::make('level.code')
                    ->label('Niveau')
                    ->badge()
                    ->color('info'),

                TextColumn::make('waiting_days')
                    ->label('En attente depuis')
                    ->state(fn (GlobalWaitlistEntry $record): string =>
                        $record->waitingDays . ' jour' . ($record->waitingDays > 1 ? 's' : '')
                    )
                    ->color(fn (GlobalWaitlistEntry $record): string => match (true) {
                        $record->waitingDays >= 30 => 'danger',
                        $record->waitingDays >= 14 => 'warning',
                        default                    => 'gray',
                    }),

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (GlobalWaitlistSource $state): string => $state->label())
                    ->color(fn (GlobalWaitlistSource $state): string => $state->color()),
            ])
            ->recordActions([
                // Action dupliquée depuis GlobalWaitlistPoolTable — §0.3 : pas d'abstraction
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
                        app(ReassignFromGlobalWaitlistAction::class)
                            ->execute($record, $target, auth()->user());
                        Notification::make()
                            ->title("Personne réassignée vers « {$target->topic} »")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Aucune personne en attente au vivier')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
}
