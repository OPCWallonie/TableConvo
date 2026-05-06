<?php

namespace App\Filament\Pages;

use App\Settings\BookingSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageBookingSettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Réservations';

    protected static ?string $title = 'Paramètres de réservation';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 14;

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = app(BookingSettings::class)->toArray();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Délais d\'inscription et d\'annulation')
                    ->columns(2)
                    ->schema([
                        TextInput::make('registration_deadline_hours')
                            ->label('Délai min. avant session pour s\'inscrire (heures)')
                            ->numeric()
                            ->minValue(1)
                            ->default(24)
                            ->suffix('h')
                            ->helperText('Par défaut : 24h. En deçà de ce délai, l\'inscription est bloquée.'),
                        TextInput::make('cancellation_deadline_business_days')
                            ->label('Délai d\'annulation (jours ouvrables)')
                            ->numeric()
                            ->minValue(1)
                            ->default(3)
                            ->suffix('j.o.')
                            ->helperText('Par défaut : 3 jours ouvrables. Au-delà, la session est perdue.'),
                    ]),

                Section::make('Anti-monopolisation')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_registrations_per_week')
                            ->label('Maximum d\'inscriptions par semaine')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('Par défaut : 1 inscription par semaine par utilisateur.'),
                        TextInput::make('max_future_registrations')
                            ->label('Maximum d\'inscriptions futures simultanées')
                            ->numeric()
                            ->minValue(1)
                            ->default(3)
                            ->helperText('Par défaut : 3 inscriptions futures en cours.'),
                    ]),

                Section::make('Extension de validité des cartes')
                    ->columns(2)
                    ->schema([
                        TextInput::make('post_cancellation_card_extension_days')
                            ->label('Prolongation de validité après annulation admin (jours)')
                            ->numeric()
                            ->minValue(1)
                            ->default(30)
                            ->suffix('j')
                            ->helperText('Nombre de jours ajoutés à la carte si elle expire bientôt.'),
                        TextInput::make('post_cancellation_extension_threshold_days')
                            ->label('Seuil d\'expiration pour déclencher la prolongation (jours)')
                            ->numeric()
                            ->minValue(1)
                            ->default(30)
                            ->suffix('j')
                            ->helperText('Si la carte expire dans X jours ou moins lors de l\'annulation, elle est prolongée.'),
                    ]),

                Section::make('Liste d\'attente')
                    ->schema([
                        Toggle::make('waitlist_auto_promote')
                            ->label('Promotion automatique depuis la liste d\'attente')
                            ->helperText('Désactivé par défaut. Si activé, le premier de la liste est inscrit automatiquement lors d\'une libération de place.'),
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
        $settings = app(BookingSettings::class);
        $settings->fill($data)->save();

        Notification::make()
            ->title('Paramètres de réservation enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
