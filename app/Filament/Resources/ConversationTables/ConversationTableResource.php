<?php

namespace App\Filament\Resources\ConversationTables;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\ConversationTables\Pages\CreateConversationTable;
use App\Filament\Resources\ConversationTables\Pages\EditConversationTable;
use App\Filament\Resources\ConversationTables\Pages\ListConversationTables;
use App\Filament\Resources\ConversationTables\Schemas\ConversationTableForm;
use App\Filament\Resources\ConversationTables\Tables\ConversationTablesTable;
use App\Models\ConversationTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationTableResource extends Resource
{
    protected static ?string $model = ConversationTable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Tables de conversation';

    protected static ?string $modelLabel = 'Table de conversation';

    protected static ?string $pluralModelLabel = 'Tables de conversation';

    protected static \UnitEnum|string|null $navigationGroup = 'Sessions';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ConversationTableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConversationTablesTable::configure($table);
    }

    /**
     * Injecte registered_count et waitlist_count sur chaque enregistrement
     * afin que les colonnes du tableau lisent des attributs déjà chargés (zéro N+1).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'registrations as registered_count' => fn (Builder $q) => $q->where('status', RegistrationStatus::Registered->value),
                'registrations as waitlist_count'   => fn (Builder $q) => $q->where('status', RegistrationStatus::Waitlist->value),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListConversationTables::route('/'),
            'create' => CreateConversationTable::route('/create'),
            'edit'   => EditConversationTable::route('/{record}/edit'),
        ];
    }
}
