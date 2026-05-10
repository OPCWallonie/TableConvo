<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ThemeSettings extends Settings
{
    public string $color_primary = '#2563eb';

    public string $color_accent = '#d97706';

    public string $color_surface = '#f3f4f6';

    public string $card_design = 'stamp';

    public static function group(): string
    {
        return 'theme';
    }
}
