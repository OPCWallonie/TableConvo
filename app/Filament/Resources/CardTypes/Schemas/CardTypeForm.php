<?php

namespace App\Filament\Resources\CardTypes\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CardTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations du type de carte')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex : Carte 10 sessions'),
                        TextInput::make('sessions_count')
                            ->label('Nombre de sessions')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(10)
                            ->suffix('sessions'),
                        TextInput::make('price')
                            ->label('Prix TTC')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->suffix('€')
                            ->helperText('Prix toutes taxes comprises. Le HT et la TVA sont calculés automatiquement.'),
                        TextInput::make('validity_months')
                            ->label('Validité')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(12)
                            ->suffix('mois'),
                        Toggle::make('is_active')
                            ->label('Actif (visible dans le catalogue)')
                            ->default(true),
                    ]),
            ]);
    }
}
