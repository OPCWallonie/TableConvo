<?php

namespace App\Filament\Resources\CardTypes;

use App\Filament\Resources\CardTypes\Pages\CreateCardType;
use App\Filament\Resources\CardTypes\Pages\EditCardType;
use App\Filament\Resources\CardTypes\Pages\ListCardTypes;
use App\Filament\Resources\CardTypes\Schemas\CardTypeForm;
use App\Filament\Resources\CardTypes\Tables\CardTypesTable;
use App\Models\CardType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CardTypeResource extends Resource
{
    protected static ?string $model = CardType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $navigationLabel = 'Types de cartes';

    protected static ?string $modelLabel = 'Type de carte';

    protected static ?string $pluralModelLabel = 'Types de cartes';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CardTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCardTypes::route('/'),
            'create' => CreateCardType::route('/create'),
            'edit' => EditCardType::route('/{record}/edit'),
        ];
    }
}
