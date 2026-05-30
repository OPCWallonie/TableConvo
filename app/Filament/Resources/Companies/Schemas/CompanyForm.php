<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom de la société')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('vat_number')
                            ->label('Numéro de TVA')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(30)
                            ->placeholder('BE0123456789'),
                        TextInput::make('billing_email')
                            ->label('E-mail de facturation')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('email_domain')
                            ->label('Domaine email pro (auto-trust)')
                            ->helperText('Ex: acme-sa.be — laissez vide pour désactiver l\'auto-trust à l\'inscription.')
                            ->maxLength(255)
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Adresse')
                    ->columns(3)
                    ->schema([
                        TextInput::make('street')
                            ->label('Rue et numéro')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('postal_code')
                            ->label('Code postal')
                            ->maxLength(20),
                        TextInput::make('city')
                            ->label('Ville')
                            ->maxLength(100)
                            ->columnSpan(2),
                        TextInput::make('country')
                            ->label('Pays')
                            ->required()
                            ->default('Belgique')
                            ->maxLength(100),
                    ]),
            ]);
    }
}
