<?php

namespace App\Filament\Pages;

use App\Settings\SessionDefaultsSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSessionDefaultsSettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Tables de conversation';

    protected static ?string $title = 'Paramètres par défaut des tables';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 16;

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = app(SessionDefaultsSettings::class)->toArray();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Valeurs par défaut à la création d\'une table')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_duration_minutes')
                            ->label('Durée par défaut (minutes)')
                            ->numeric()
                            ->minValue(15)
                            ->default(90)
                            ->suffix('min'),
                        TextInput::make('default_max_participants')
                            ->label('Nombre max. de participants par défaut')
                            ->numeric()
                            ->minValue(1)
                            ->default(8)
                            ->suffix('pers.'),
                        TextInput::make('default_location')
                            ->label('Lieu par défaut')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex : Salle A, 3ème étage'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->getSchema('form')->getState();
        $settings = app(SessionDefaultsSettings::class);
        $settings->fill($data)->save();

        Notification::make()
            ->title('Paramètres des tables enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
