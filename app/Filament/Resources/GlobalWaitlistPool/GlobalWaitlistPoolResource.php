<?php

namespace App\Filament\Resources\GlobalWaitlistPool;

use App\Enums\GlobalWaitlistEntryStatus;
use App\Filament\Resources\GlobalWaitlistPool\Pages\ListGlobalWaitlistPool;
use App\Filament\Resources\GlobalWaitlistPool\Tables\GlobalWaitlistPoolTable;
use App\Models\GlobalWaitlistEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GlobalWaitlistPoolResource extends Resource
{
    protected static ?string $model = GlobalWaitlistEntry::class;

    protected static string|null $slug = 'pool';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Vivier global';

    protected static ?string $modelLabel = 'Entrée vivier';

    protected static ?string $pluralModelLabel = 'Vivier global';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestion des inscriptions';

    protected static ?int $navigationSort = 11;

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
            ->where('status', GlobalWaitlistEntryStatus::Pending)
            ->with(['user', 'level', 'createdBy']);
    }

    public static function table(Table $table): Table
    {
        return GlobalWaitlistPoolTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGlobalWaitlistPool::route('/'),
        ];
    }
}
