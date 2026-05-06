<?php

namespace App\Filament\Pages;

use App\Settings\MollieSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ManageMollieSettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Paiement Mollie';

    protected static ?string $title = 'Paramètres Mollie';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 12;

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(MollieSettings::class);
        $this->data = [
            'api_key' => $settings->api_key,
            'test_mode' => $settings->test_mode,
            'webhook_secret' => $settings->webhook_secret,
        ];
    }

    public function form(Schema $schema): Schema
    {
        $stubNotice = empty($this->data['api_key'])
            ? new HtmlString('<div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800"><strong>Mode stub actif :</strong> Aucune clé API renseignée. Les paiements seront simulés localement sans appel à Mollie.</div>')
            : null;

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Connexion Mollie')
                    ->schema(array_filter([
                        $stubNotice ? Placeholder::make('stub_notice')
                            ->label('')
                            ->content($stubNotice) : null,
                        TextInput::make('api_key')
                            ->label('Clé API Mollie')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Laissez vide pour activer le mode stub (simulation locale). Trouvez votre clé sur dashboard.mollie.com'),
                        Toggle::make('test_mode')
                            ->label('Mode test')
                            ->helperText('Utiliser les clés de test Mollie (test_xxxx). Désactivez pour la production.'),
                        TextInput::make('webhook_secret')
                            ->label('Secret webhook (optionnel)')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Pour la vérification de signature future'),
                    ])),
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
        $settings = app(MollieSettings::class);
        $settings->fill($data)->save();

        Notification::make()
            ->title('Paramètres Mollie enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
