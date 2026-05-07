<?php

namespace App\Filament\Resources\ConversationTables\Schemas;

use App\Enums\SessionStatus;
use App\Models\Level;
use App\Models\User;
use App\Settings\SessionDefaultsSettings;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConversationTableForm
{
    public static function configure(Schema $schema): Schema
    {
        $defaults = app(SessionDefaultsSettings::class);

        return $schema->components([

            Section::make('La session')
                ->columns(2)
                ->schema([
                    TextInput::make('topic')
                        ->label('Sujet')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->placeholder('Ex : Parler de son travail au quotidien'),

                    Select::make('level_id')
                        ->label('Niveau')
                        ->options(
                            Level::where('is_active', true)
                                ->orderBy('sort_order')
                                ->pluck('name', 'id')
                        )
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('status')
                        ->label('Statut')
                        ->options(
                            collect(SessionStatus::cases())
                                ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                        )
                        ->default(SessionStatus::Scheduled->value)
                        ->required(),

                    DateTimePicker::make('scheduled_at')
                        ->label('Date et heure')
                        ->required()
                        ->native(false)
                        ->minDate(now()->startOfDay())
                        ->displayFormat('d/m/Y H:i')
                        ->seconds(false),

                    TextInput::make('duration_minutes')
                        ->label('Durée (minutes)')
                        ->numeric()
                        ->minValue(1)
                        ->default($defaults->default_duration_minutes)
                        ->suffix('min'),
                ]),

            Section::make('Lieu et participants')
                ->columns(2)
                ->schema([
                    TextInput::make('location')
                        ->label('Lieu')
                        ->maxLength(255)
                        ->default($defaults->default_location)
                        ->columnSpanFull(),

                    TextInput::make('max_participants')
                        ->label('Participants max')
                        ->numeric()
                        ->minValue(1)
                        ->default($defaults->default_max_participants),
                ]),

            Section::make('Informations complémentaires')
                ->columns(1)
                ->collapsed()
                ->schema([
                    Textarea::make('description')
                        ->label('Description')
                        ->nullable()
                        ->rows(3)
                        ->maxLength(2000),

                    Select::make('animator_id')
                        ->label('Animateur (optionnel)')
                        ->options(
                            fn () => User::orderBy('last_name')
                                ->get()
                                ->mapWithKeys(fn ($u) => [$u->id => $u->full_name . ' — ' . $u->email])
                        )
                        ->nullable()
                        ->searchable()
                        ->placeholder('Aucun'),
                ]),
        ]);
    }
}
