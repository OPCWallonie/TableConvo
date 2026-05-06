<?php

namespace App\Filament\Pages;

use App\Settings\InvoicingSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageInvoicingSettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Facturation';

    protected static ?string $title = 'Paramètres de facturation';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 11;

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = app(InvoicingSettings::class)->toArray();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Numérotation des factures')
                    ->columns(2)
                    ->schema([
                        TextInput::make('invoice_number_prefix')
                            ->label('Préfixe')
                            ->default('FAC')
                            ->maxLength(10)
                            ->helperText('Ex : FAC, FACT, INV'),
                        TextInput::make('invoice_number_format')
                            ->label('Format')
                            ->default('{prefix}-{year}-{number:05d}')
                            ->maxLength(100)
                            ->helperText('{prefix}, {year}, {number:05d} sont les variables disponibles'),
                        Toggle::make('invoice_number_yearly_reset')
                            ->label('Réinitialiser le compteur chaque année')
                            ->helperText('Si activé, la numérotation repart à 1 chaque 1er janvier'),
                    ]),

                Section::make('TVA')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_vat_rate')
                            ->label('Taux de TVA par défaut')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('%')
                            ->default(21.00)
                            ->helperText('21% = taux standard belge'),
                        TextInput::make('payment_terms_days')
                            ->label('Délai de paiement (jours)')
                            ->numeric()
                            ->default(0)
                            ->helperText('0 = paiement immédiat'),
                        Toggle::make('vat_exempt')
                            ->label('Exonération de TVA')
                            ->helperText('Si activé, la mention légale ci-dessous apparaît sur les factures')
                            ->columnSpanFull(),
                        Textarea::make('vat_exempt_legal_mention')
                            ->label('Mention légale exonération TVA')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Ex : TVA non applicable, art. 44 §4 du Code de la TVA'),
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
        $settings = app(InvoicingSettings::class);
        $settings->fill($data)->save();

        Notification::make()
            ->title('Paramètres de facturation enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
