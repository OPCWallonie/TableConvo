<?php

namespace App\Filament\Pages;

use App\Settings\LegalSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageLegalSettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Mentions légales';

    protected static ?string $title = 'Documents légaux';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 13;

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = app(LegalSettings::class)->toArray();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Conditions générales de vente')
                    ->description('PDF affiché sur la page /cgv. Si absent, un placeholder "À paraître" est affiché.')
                    ->schema([
                        FileUpload::make('cgv_pdf_path')
                            ->label('PDF des CGV')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('public')
                            ->directory('legal')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->helperText('Format PDF uniquement, max 5 Mo'),
                    ]),

                Section::make('Politique de confidentialité')
                    ->description('PDF affiché sur la page /confidentialite.')
                    ->schema([
                        FileUpload::make('privacy_pdf_path')
                            ->label('PDF de la politique de confidentialité')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('public')
                            ->directory('legal')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->helperText('Format PDF uniquement, max 5 Mo'),
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
        $settings = app(LegalSettings::class);
        $settings->fill($data)->save();

        Notification::make()
            ->title('Documents légaux enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
