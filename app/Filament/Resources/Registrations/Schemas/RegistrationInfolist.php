<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Enums\RegistrationStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Inscription')
                ->columns(2)
                ->schema([
                    TextEntry::make('registered_at')
                        ->label("Date d'inscription")
                        ->dateTime('d/m/Y H:i'),

                    TextEntry::make('status')
                        ->label('Statut')
                        ->badge()
                        ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                        ->color(fn (RegistrationStatus $state): string => $state->color()),

                    TextEntry::make('user.full_name')
                        ->label('Personne'),

                    TextEntry::make('user.email')
                        ->label('Email'),
                ]),

            Section::make('Session')
                ->columns(2)
                ->schema([
                    TextEntry::make('conversationTable.topic')
                        ->label('Sujet'),

                    TextEntry::make('conversationTable.scheduled_at')
                        ->label('Date')
                        ->dateTime('d/m/Y H:i'),

                    TextEntry::make('conversationTable.level.code')
                        ->label('Niveau')
                        ->badge()
                        ->color('info'),
                ]),

            Section::make('Détails complémentaires')
                ->columns(2)
                ->schema([
                    TextEntry::make('waitlist_position')
                        ->label("Position liste d'attente")
                        ->formatStateUsing(fn (?int $state): string => $state ? "#{$state}" : '—')
                        ->placeholder('—'),

                    TextEntry::make('card_id')
                        ->label('Carte associée')
                        ->formatStateUsing(fn (?int $state): ?string => $state ? "Carte #{$state}" : null)
                        ->placeholder('Aucune (liste d\'attente)'),

                    TextEntry::make('cancelled_at')
                        ->label('Annulée le')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),

                    TextEntry::make('cancelledBy.full_name')
                        ->label('Annulée par')
                        ->placeholder('—'),
                ]),
        ]);
    }
}
