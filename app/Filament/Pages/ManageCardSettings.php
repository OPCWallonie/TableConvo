<?php

namespace App\Filament\Pages;

use App\Settings\CardSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageCardSettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $navigationLabel = 'Cartes de sessions';

    protected static ?string $title = 'Paramètres des cartes';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 15;

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(CardSettings::class);
        $this->data = [
            'default_validity_months' => $settings->default_validity_months,
            'default_sessions_count' => $settings->default_sessions_count,
            'default_price_per_card' => $settings->default_price_per_card,
            'expiration_warning_days' => array_map('strval', $settings->expiration_warning_days),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Valeurs par défaut des cartes')
                    ->columns(3)
                    ->schema([
                        TextInput::make('default_sessions_count')
                            ->label('Nombre de sessions par défaut')
                            ->numeric()
                            ->minValue(1)
                            ->default(10)
                            ->suffix('sessions')
                            ->helperText('Valeur proposée à la création d\'un nouveau type de carte.'),
                        TextInput::make('default_validity_months')
                            ->label('Validité par défaut (mois)')
                            ->numeric()
                            ->minValue(1)
                            ->default(12)
                            ->suffix('mois'),
                        TextInput::make('default_price_per_card')
                            ->label('Prix par défaut')
                            ->numeric()
                            ->step(0.01)
                            ->default(250.00)
                            ->suffix('€'),
                    ]),

                Section::make('Alertes d\'expiration')
                    ->schema([
                        TagsInput::make('expiration_warning_days')
                            ->label('Alertes à X jours avant expiration')
                            ->placeholder('Ex : 30, 7')
                            ->helperText('Entrez un nombre puis appuyez Entrée. Par défaut : 30 et 7 jours avant expiration.')
                            ->separator(','),
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
        $data['expiration_warning_days'] = array_map('intval', $data['expiration_warning_days'] ?? []);
        $settings = app(CardSettings::class);
        $settings->fill($data)->save();

        Notification::make()
            ->title('Paramètres des cartes enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
