<?php

namespace App\Filament\Resources\ActivityLog;

use App\Filament\Resources\ActivityLog\Pages\ListActivityLogs;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use App\Models\User;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Journal d\'audit';

    protected static ?string $modelLabel = 'Entrée de journal';

    protected static ?string $pluralModelLabel = 'Journal d\'audit';

    protected static ?string $slug = 'activity-logs';

    protected static \UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Action')
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('subject_type')
                    ->label('Entité')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),

                TextColumn::make('subject_id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('causer_id')
                    ->label('Auteur')
                    ->getStateUsing(fn (Activity $record): string => $record->causer?->full_name ?? 'Système'),

                TextColumn::make('event')
                    ->label('Événement')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created'  => 'success',
                        'updated'  => 'warning',
                        'deleted'  => 'danger',
                        default    => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Type d\'entité')
                    ->options(fn (): array => Activity::distinct()
                        ->pluck('subject_type')
                        ->filter()
                        ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)])
                        ->toArray()),

                SelectFilter::make('causer_id')
                    ->label('Auteur')
                    ->options(fn (): array => User::orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (User $u): array => [$u->id => $u->full_name])
                        ->toArray()),

                SelectFilter::make('event')
                    ->label('Événement')
                    ->options([
                        'created' => 'Créé',
                        'updated' => 'Modifié',
                        'deleted' => 'Supprimé',
                    ]),

                Filter::make('created_at')
                    ->label('Période')
                    ->form([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('to')->label('Au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Du ' . $data['from'];
                        }
                        if ($data['to'] ?? null) {
                            $indicators[] = 'Au ' . $data['to'];
                        }
                        return $indicators;
                    }),

                Filter::make('subject_id')
                    ->label('ID sujet')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('value')
                            ->label('ID')
                            ->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))),
            ])
            ->recordActions([
                Action::make('view_details')
                    ->label('Voir détails')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->modalHeading('Détails de l\'entrée')
                    ->modalContent(fn (Activity $record) => view(
                        'filament.activity-log.details-modal',
                        ['activity' => $record]
                    ))
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
