<?php

namespace App\Filament\Pages;

use App\Settings\CompanySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageCompanySettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Société (vendeur)';

    protected static ?string $title = 'Paramètres société';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = app(CompanySettings::class)->toArray();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identification légale')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Raison sociale')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('legal_form')
                            ->label('Forme juridique')
                            ->placeholder('SRL, SA, ASBL…')
                            ->maxLength(50),
                        TextInput::make('vat_number')
                            ->label('Numéro TVA')
                            ->placeholder('BE0123456789')
                            ->maxLength(30),
                        TextInput::make('rpm')
                            ->label('RPM (ville)')
                            ->placeholder('RPM Liège')
                            ->maxLength(100),
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
                            ->default('Belgique')
                            ->maxLength(100),
                    ]),

                Section::make('Coordonnées')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email_contact')
                            ->label('E-mail de contact')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('website')
                            ->label('Site web')
                            ->url()
                            ->maxLength(255),
                    ]),

                Section::make('Coordonnées bancaires')
                    ->columns(3)
                    ->schema([
                        TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(34)
                            ->columnSpan(2),
                        TextInput::make('bic')
                            ->label('BIC/SWIFT')
                            ->maxLength(11),
                        TextInput::make('bank_name')
                            ->label('Nom de la banque')
                            ->maxLength(100),
                    ]),

                Section::make('Logo')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo (PNG/SVG recommandé)')
                            ->image()
                            ->disk('public')
                            ->directory('logos')
                            ->visibility('public')
                            ->maxSize(2048),
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
        $settings = app(CompanySettings::class);
        $settings->fill(array_map(fn ($v) => $v ?? '', $data))->save();

        Notification::make()
            ->title('Paramètres société enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
