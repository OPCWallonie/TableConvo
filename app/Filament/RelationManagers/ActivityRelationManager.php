<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Historique';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $ownerRecord = $this->ownerRecord;

        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Action')
                    ->limit(60),

                TextColumn::make('event')
                    ->label('Événement')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    }),

                TextColumn::make('causer_id')
                    ->label('Par')
                    ->getStateUsing(fn (Activity $record): string => $record->causer?->full_name ?? 'Système'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([50, 100])
            ->recordActions([
                Action::make('view_details')
                    ->label('Détails')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->modalHeading('Détails')
                    ->modalContent(fn (Activity $record) => view(
                        'filament.activity-log.details-modal',
                        ['activity' => $record]
                    ))
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ])
            ->toolbarActions([
                Action::make('voir_tous_les_logs')
                    ->label('Voir tous les logs')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (): string => ActivityLogResource::getUrl('index') . '?' . http_build_query([
                        'tableFilters' => [
                            'subject_type' => ['value' => get_class($ownerRecord)],
                            'subject_id'   => ['value' => $ownerRecord->getKey()],
                        ],
                    ]))
                    ->openUrlInNewTab(),
            ]);
    }
}
