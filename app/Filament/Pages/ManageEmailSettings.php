<?php

namespace App\Filament\Pages;

use App\Settings\EmailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageEmailSettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'E-mails';

    protected static ?string $title = 'Paramètres e-mail';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 17;

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(EmailSettings::class);
        $this->data = [
            'from_email' => $settings->from_email,
            'from_name' => $settings->from_name,
            'reply_to' => $settings->reply_to,
            'admin_notifications_email' => $settings->admin_notifications_email,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Expéditeur')
                    ->columns(2)
                    ->schema([
                        TextInput::make('from_email')
                            ->label('E-mail expéditeur')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Adresse utilisée comme "From" dans tous les envois'),
                        TextInput::make('from_name')
                            ->label('Nom expéditeur')
                            ->maxLength(100)
                            ->default('TableConvo'),
                        TextInput::make('reply_to')
                            ->label('Répondre à (Reply-To)')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Optionnel. Si différent de l\'expéditeur.'),
                    ]),

                Section::make('Notifications administrateur')
                    ->schema([
                        TextInput::make('admin_notifications_email')
                            ->label('E-mail pour les notifications admin')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Reçoit les alertes : nouveau membre sans niveau, liste d\'attente, etc.'),
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
        $settings = app(EmailSettings::class);
        $settings->fill($data)->save();

        Notification::make()
            ->title('Paramètres e-mail enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
