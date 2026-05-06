<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Level;
use App\Models\Company;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations personnelles')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Prénom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Adresse e-mail')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->maxLength(255),
                    ]),

                Section::make('Société & Niveau')
                    ->columns(2)
                    ->schema([
                        Select::make('company_id')
                            ->label('Société')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('level_id')
                            ->label('Niveau CECRL')
                            ->relationship('level', 'code')
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('level_assigned_at')
                            ->label('Niveau attribué le')
                            ->displayFormat('d/m/Y H:i'),
                        DateTimePicker::make('email_verified_at')
                            ->label('E-mail vérifié le')
                            ->displayFormat('d/m/Y H:i'),
                    ]),
            ]);
    }
}
