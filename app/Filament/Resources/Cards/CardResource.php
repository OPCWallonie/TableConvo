<?php

namespace App\Filament\Resources\Cards;

use App\Enums\CardStatus;
use App\Filament\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\Cards\Pages\EditCard;
use App\Filament\Resources\Cards\Pages\ListCards;
use App\Models\Card;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CardResource extends Resource
{
    protected static ?string $model = Card::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Cartes';

    protected static ?string $modelLabel = 'Carte';

    protected static ?string $pluralModelLabel = 'Cartes';

    protected static \UnitEnum|string|null $navigationGroup = 'Gestion des membres';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.full_name')
                    ->label('Membre')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->sortable(),

                TextColumn::make('cardType.name')
                    ->label('Type')
                    ->sortable(),

                TextColumn::make('sessions_remaining')
                    ->label('Séances restantes')
                    ->formatStateUsing(fn (Card $record): string => $record->sessions_remaining . ' / ' . $record->sessions_total)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (CardStatus $state): string => match ($state) {
                        CardStatus::Active   => 'Active',
                        CardStatus::Expired  => 'Expirée',
                        CardStatus::Refunded => 'Remboursée',
                    })
                    ->color(fn (CardStatus $state): string => match ($state) {
                        CardStatus::Active   => 'success',
                        CardStatus::Expired  => 'danger',
                        CardStatus::Refunded => 'warning',
                    }),

                TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->color(fn (Card $record): string => $record->expires_at->isPast() ? 'danger' : 'success'),

                TextColumn::make('purchased_at')
                    ->label('Achetée le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('purchased_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ActivityRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCards::route('/'),
            'edit'  => EditCard::route('/{record}/edit'),
        ];
    }
}
