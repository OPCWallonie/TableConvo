<?php

namespace App\Filament\Pages;

use App\Livewire\Admin\CardDesignPreview;
use App\Settings\ThemeSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ManageThemeSettings extends Page
{
    protected string $view = 'filament.pages.settings.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?string $navigationLabel = 'Apparence';

    protected static ?string $title = 'Paramètres d\'apparence';

    protected static \UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 19;

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = app(ThemeSettings::class)->toArray();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Couleurs')
                    ->description('Ces couleurs s\'appliquent au front public et à l\'espace membre. L\'interface admin Filament n\'est pas affectée.')
                    ->columns(3)
                    ->schema([
                        ColorPicker::make('color_primary')
                            ->label('Couleur primaire')
                            ->helperText('Boutons d\'action, liens, états actifs.')
                            ->live(),

                        ColorPicker::make('color_accent')
                            ->label('Couleur d\'accent')
                            ->helperText('Badges, alertes, mises en avant.')
                            ->live(),

                        ColorPicker::make('color_surface')
                            ->label('Fond de page')
                            ->helperText('Couleur de fond générale de l\'espace membre.')
                            ->live(),
                    ]),

                Section::make('Contraste WCAG AA')
                    ->description('Le contraste doit être ≥ 4.5:1 pour le texte normal (WCAG AA).')
                    ->schema([
                        Placeholder::make('contrast_info')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $primary = $get('color_primary') ?? '#2563eb';
                                $surface = $get('color_surface') ?? '#f3f4f6';

                                $ratioLightOnPrimary  = $this->contrastRatio('#ffffff', $primary);
                                $ratioDarkOnPrimary   = $this->contrastRatio('#111111', $primary);
                                $ratioDarkOnSurface   = $this->contrastRatio('#111111', $surface);

                                $badge = fn (float $r): string => $r >= 4.5
                                    ? "<span style='color:#16a34a;font-weight:600;'>✓ {$r}:1</span>"
                                    : "<span style='color:#d97706;font-weight:600;'>⚠ {$r}:1 — contraste insuffisant (WCAG AA)</span>";

                                return new HtmlString(
                                    "<div class='space-y-1 text-sm'>"
                                    . "<div>Texte blanc sur fond primaire : {$badge($ratioLightOnPrimary)}</div>"
                                    . "<div>Texte foncé sur fond primaire : {$badge($ratioDarkOnPrimary)}</div>"
                                    . "<div>Texte foncé sur fond de page : {$badge($ratioDarkOnSurface)}</div>"
                                    . "</div>"
                                );
                            }),
                    ]),

                Section::make('Design de la carte virtuelle')
                    ->schema([
                        Select::make('card_design')
                            ->label('Modèle de carte')
                            ->options([
                                'stamp'     => 'Tampon (skeuomorphique)',
                                'wallet'    => 'Wallet (moderne)',
                                'editorial' => 'Éditorial (haut de gamme)',
                                'swiss'     => 'Swiss (minimaliste)',
                            ])
                            ->required()
                            ->rule('in:stamp,wallet,editorial,swiss')
                            ->helperText('Choisissez le rendu visuel des cartes dans l\'espace membre.')
                            ->live(),

                        Livewire::make(CardDesignPreview::class, fn (Get $get): array => [
                            'design'       => $get('card_design') ?? 'stamp',
                            'primaryColor' => $get('color_primary') ?? '#2563eb',
                            'accentColor'  => $get('color_accent') ?? '#d97706',
                            'surfaceColor' => $get('color_surface') ?? '#f3f4f6',
                        ])->key(fn (Get $get): string => md5(
                            ($get('card_design') ?? 'stamp')
                            . ($get('color_primary') ?? '#2563eb')
                            . ($get('color_accent') ?? '#d97706')
                        )),
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

        if (!in_array($data['card_design'] ?? '', ['stamp', 'wallet', 'editorial', 'swiss'])) {
            Notification::make()->title('Design invalide.')->danger()->send();
            return;
        }

        $settings = app(ThemeSettings::class);
        $settings->fill($data)->save();

        Notification::make()
            ->title('Paramètres d\'apparence enregistrés.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    private function contrastRatio(string $hex1, string $hex2): float
    {
        $l1 = $this->relativeL($hex1);
        $l2 = $this->relativeL($hex2);
        [$lighter, $darker] = $l1 >= $l2 ? [$l1, $l2] : [$l2, $l1];
        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    private function relativeL(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $lin = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        return 0.2126 * $lin(hexdec(substr($hex, 0, 2)) / 255)
            + 0.7152 * $lin(hexdec(substr($hex, 2, 2)) / 255)
            + 0.0722 * $lin(hexdec(substr($hex, 4, 2)) / 255);
    }
}
