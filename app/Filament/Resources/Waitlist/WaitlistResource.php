<?php

namespace App\Filament\Resources\Waitlist;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Filament\Resources\Waitlist\Pages\ListWaitlist;
use App\Filament\Resources\Waitlist\Tables\WaitlistTable;
use App\Models\Registration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WaitlistResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string|null $slug = 'waitlist';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Files d\'attente par session';

    protected static ?string $modelLabel = 'Inscription en file d\'attente';

    protected static ?string $pluralModelLabel = 'Files d\'attente par session';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestion des inscriptions';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', RegistrationStatus::Waitlist)
            ->whereHas('conversationTable', fn (Builder $q) =>
                $q->where('status', SessionStatus::Scheduled)
                  ->where('scheduled_at', '>', now())
            )
            ->with(['user', 'conversationTable.level']);
    }

    public static function table(Table $table): Table
    {
        return WaitlistTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaitlist::route('/'),
        ];
    }
}
